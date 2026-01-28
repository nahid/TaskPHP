<?php

namespace Nahid\TaskPHP\Process;

use Exception;
use RuntimeException;
use Nahid\TaskPHP\Contracts\TaskInterface;
use Nahid\TaskPHP\Exceptions\TaskFailedException;
use Nahid\TaskPHP\Exceptions\TimeoutException;
use Nahid\TaskPHP\IPC\Serializer;


class ProcessManager
{
    /** @var array<string, TaskInterface> */
    private $pendingTasks = [];

    /** @var array<int, array{process: resource, pipes: array, taskName: string, startTime: int, outputBuffer: string, errorBuffer: string}> */
    private $runningProcesses = [];


    /** @var array */
    private $results = [];

    /** @var array */
    private $errors = [];

    /** @var int */
    private $concurrencyLimit;

    /** @var int|null */
    private $timeout;

    /** @var Serializer */
    private $serializer;

    /** @var bool */
    private $failFast = true;

    /** @var bool */
    private $terminating = false;

    /** @var int */
    private $outputLimit = 10 * 1024 * 1024; // 10MB default limit

    /** @var ProcessManager[] */
    private static $registry = [];

    /** @var bool */
    private static $shutdownRegistered = false;

    /** @var string */
    private $id;

    /** @var string */
    private $workerPath;

    /** @var \Nahid\TaskPHP\Contracts\TaskBootstrapInterface|string|null */
    private $bootstrap = null;

    public function __construct(array $tasks, int $concurrencyLimit = -1)
    {
        $this->pendingTasks = $tasks;
        $this->concurrencyLimit = $concurrencyLimit > 0 ? $concurrencyLimit : count($tasks);
        if ($this->concurrencyLimit <= 0) {
            $this->concurrencyLimit = 1; // Safety fallback
        }

        $this->serializer = new Serializer();
        // Path to the worker script
        $this->workerPath = __DIR__ . '/../bin/worker.php';

        // Register instance

        $this->id = uniqid();
        self::$registry[$this->id] = $this;

        if (!self::$shutdownRegistered) {
            register_shutdown_function([self::class, 'shutdownHandler']);
            self::$shutdownRegistered = true;
        }

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, [$this, 'signalHandler']);
            pcntl_signal(SIGTERM, [$this, 'signalHandler']);
        }
    }

    public function signalHandler($signo)
    {
        $this->terminating = true;
        $this->killAll();
        exit(1);
    }

    public static function shutdownHandler()
    {
        foreach (self::$registry as $manager) {
            try {
                $manager->wait();
            } catch (\Throwable $e) {
                // Suppress exceptions during shutdown
            }
        }
    }

    public function setTimeout(?int $seconds): void
    {
        $this->timeout = $seconds;
    }

    public function setFailFast(bool $failFast): void
    {
        $this->failFast = $failFast;
    }

    public function setOutputLimit(int $bytes): void
    {
        $this->outputLimit = $bytes;
    }

    /**
     * Set bootstrap for framework initialization.
     *
     * @param \Nahid\TaskPHP\Contracts\TaskBootstrapInterface|string|null $bootstrap
     */
    public function setBootstrap($bootstrap): void
    {
        $this->bootstrap = $bootstrap;
    }

    /**
     * Set bootstrap file path (legacy support).
     * Use setBootstrap() for better DX.
     *
     * @param string|null $path Absolute path to bootstrap file
     * @deprecated Use setBootstrap() instead
     */
    public function setBootstrapPath(?string $path): void
    {
        $this->setBootstrap($path);
    }

    /**
     * Unregister the manager from global registry to prevent blocking on shutdown.
     */
    public function unregister(): void
    {
        unset(self::$registry[$this->id]);
    }

    /**
     * Start initial batch of processes.
     */
    public function start(): void
    {
        $this->replenish();
    }

    /**
     * Wait for all tasks to complete.
     *
     * @return array
     * @throws TaskFailedException
     */
    public function wait(): array
    {
        while (!empty($this->runningProcesses) || !empty($this->pendingTasks)) {
            if ($this->terminating) {
                break;
            }

            $this->checkChildren();
            // Wait up to 10ms for data
            $this->readUpdates(0, 10000);
            $this->checkTimeouts();
            $this->replenish();
        }

        // Unregister from registry as we are done
        unset(self::$registry[$this->id]);

        if (!$this->failFast && !empty($this->errors)) {
            return [
                'results' => $this->results,
                'errors' => $this->errors
            ];
        }

        return $this->results;
    }

    private function replenish(): void
    {
        if ($this->terminating) {
            return;
        }

        while (!empty($this->pendingTasks) && count($this->runningProcesses) < $this->concurrencyLimit) {
            $taskName = array_key_first($this->pendingTasks);
            $task = array_shift($this->pendingTasks); // Get and remove first task

            $this->spawn($taskName, $task);
        }
    }

    private function spawn($taskName, TaskInterface $task): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // STDIN (we write to child)
            1 => ['pipe', 'w'],  // STDOUT (child writes result)
            2 => ['pipe', 'w']   // STDERR (can capture errors)
        ];

        $process = proc_open("php " . escapeshellarg($this->workerPath), $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException("Failed to spawn process for task: $taskName");
        }

        // Get generic status to get PID if needed, though resource ID is key usually.
        // We use resource ID or returned PID.
        $status = proc_get_status($process);
        $pid = $status['pid']; // OS PID
        // We use the PID as key to manage processes consistent with before

        // Write the bootstrap (object or path) and serialized task to the worker
        $payload = [
            'bootstrap' => $this->bootstrap,
            'task' => $task
        ];
        $serializedPayload = $this->serializer->serialize($payload);
        fwrite($pipes[0], $serializedPayload);
        fclose($pipes[0]); // Close STDIN to signal we are done sending task

        // Set non-blocking on reading pipes
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->runningProcesses[$pid] = [
            'process' => $process,
            'pipes' => $pipes,
            'taskName' => $taskName,
            'startTime' => time(),
            'outputBuffer' => '',
            'errorBuffer' => ''
        ];
    }

    private function checkChildren(): void
    {
        // Check for exited children
        foreach ($this->runningProcesses as $pid => $info) {
            $status = proc_get_status($info['process']);

            if (!$status['running']) {
                $this->drainPipe($pid);
                $this->handleCompletedProcess($pid, $status['exitcode']);
            }
        }
    }

    private function readUpdates(int $seconds = 0, int $microseconds = 0): void
    {
        if (empty($this->runningProcesses)) {
            return;
        }

        $read = [];
        $write = null;
        $except = null;
        $map = [];

        foreach ($this->runningProcesses as $pid => $info) {
            // We read from stdout (pipe 1) and stderr (pipe 2)
            $read[] = $info['pipes'][1];
            $map[(int) $info['pipes'][1]] = ['pid' => $pid, 'type' => 1];

            $read[] = $info['pipes'][2];
            $map[(int) $info['pipes'][2]] = ['pid' => $pid, 'type' => 2];
        }

        if (empty($read)) {
            return;
        }

        // Wait up to specified time
        $numChanged = stream_select($read, $write, $except, $seconds, $microseconds);

        if ($numChanged === false) {
            return;
        }

        if ($numChanged > 0) {
            foreach ($read as $r) {
                $meta = $map[(int) $r];
                $this->readNonBlocking($meta['pid'], $meta['type']);
            }
        }
    }

    private function readNonBlocking(int $pid, int $type): void
    {
        if (!isset($this->runningProcesses[$pid])) {
            return;
        }

        $pipes = $this->runningProcesses[$pid]['pipes'];
        $reader = $pipes[$type];

        // Read available data until temporarily empty
        while (true) {
            $chunk = fread($reader, 65536);

            if ($chunk === false || $chunk === '') {
                break;
            }

            if ($type === 1) {
                $this->runningProcesses[$pid]['outputBuffer'] .= $chunk;
            } else {
                $this->runningProcesses[$pid]['errorBuffer'] .= $chunk;
            }

            if (strlen($this->runningProcesses[$pid]['outputBuffer']) > $this->outputLimit) {
                proc_terminate($this->runningProcesses[$pid]['process']);
            }
        }
    }

    private function drainPipe(int $pid): void
    {
        if (!isset($this->runningProcesses[$pid])) {
            return;
        }

        $pipes = $this->runningProcesses[$pid]['pipes'];

        foreach ([1, 2] as $type) {
            $reader = $pipes[$type];
            stream_set_blocking($reader, true);

            while (!feof($reader)) {
                $chunk = fread($reader, 65536);
                if ($chunk === false) {
                    break;
                }
                if ($type === 1) {
                    $this->runningProcesses[$pid]['outputBuffer'] .= $chunk;
                } else {
                    $this->runningProcesses[$pid]['errorBuffer'] .= $chunk;
                }
            }
        }
    }

    private function handleCompletedProcess(int $pid, int $status): void
    {
        $info = $this->runningProcesses[$pid];
        $process = $info['process'];
        $taskName = $info['taskName'];

        unset($this->runningProcesses[$pid]);

        // Close pipes and resource
        fclose($info['pipes'][1]);
        fclose($info['pipes'][2]);
        proc_close($process);

        $exitCode = $status;

        try {
            $rawPayload = trim($info['outputBuffer'] ?? '');

            if ($rawPayload === '') {
                $data = new RuntimeException("Child process produced no output. Possible immediate crash. Stderr: " . ($info['errorBuffer'] ?: 'None'));
                $exitCode = $exitCode ?: 1;
            } else {
                $data = $this->serializer->unserialize($rawPayload);

                if ($data === false && $rawPayload !== serialize(false)) {
                    $preview = strlen($rawPayload) > 100 ? substr($rawPayload, 0, 100) . '...' : $rawPayload;
                    $data = new RuntimeException("Failed to unserialize child output. Raw output: " . $preview . " | Stderr: " . ($info['errorBuffer'] ?: 'None'));
                    $exitCode = 1;
                }
            }
        } catch (\Throwable $e) {
            $data = new RuntimeException("Error processing child output: " . $e->getMessage() . " | Raw output: " . substr($info['outputBuffer'], 0, 100) . " | Stderr: " . ($info['errorBuffer'] ?: 'None'));
            $exitCode = 1;
        }

        if ($exitCode === 0) {
            // Success
            $this->results[$taskName] = $data;
        } else {
            // Failure
            // $data might be the serialized exception or null if crash
            $error = $data;
            if (!$error instanceof \Throwable && !is_array($error)) {
                $error = new RuntimeException("Child process exited with error code $exitCode");
            }

            if ($this->failFast) {
                $this->killAll();
                if ($error instanceof \Throwable) {
                    $message = $error->getMessage();
                    $trace = $error->getTraceAsString();
                } elseif (is_array($error) && isset($error['message'])) {
                    $message = $error['message'];
                    $trace = $error['trace'] ?? '';
                } else {
                    $message = "Unknown error";
                    $trace = "";
                }

                throw new TaskFailedException($message, $pid, $taskName, $trace);
            } else {
                $this->errors[] = [
                    'task' => $taskName,
                    'pid' => $pid,
                    'exception' => $error
                ];
            }
        }
    }

    private function checkTimeouts(): void
    {
        if ($this->timeout === null) {
            return;
        }

        $now = time();
        foreach ($this->runningProcesses as $pid => $info) {
            if (($now - $info['startTime']) > $this->timeout) {
                proc_terminate($info['process']); // Default is SIGTERM
                // Force SIGKILL if needed: proc_terminate($info['process'], 9);


                // Cleanup will happen in checkChildren or we force it here?
                // Waitpid might still need to reap it. 
                // We'll let the next checkChildren reap it, 
                // but we should mark it or handle it so we don't throw multiple times?
                // Actually, if we kill it, it returns valid status.
                // But we want to treat it as timeout specifically.

                // Clean from running immediately to avoid repeated kills?
                // Better to wait for reap.
                // Let's rely on standard waitpid handling to pick up the killed process.
                // BUT we need to know it was a timeout to throw TimeoutException.

                // We can mark it as timed out in a separate list?
                // Or just handle it here.

                unset($this->runningProcesses[$pid]);

                // Reap it explicitly 
                // We should let checkChildren handle proper closure or do it here?
                // If we terminate, checkChildren will see it as not running next loop.
                // But we remove it from runningProcesses here. So we MUST clean up resource.

                fclose($info['pipes'][1]);
                fclose($info['pipes'][2]);
                proc_close($info['process']);


                if ($this->failFast) {
                    $this->killAll();
                    throw new TimeoutException($info['taskName']);
                } else {
                    $this->errors[] = [
                        'task' => $info['taskName'],
                        'pid' => $pid,
                        'exception' => new TimeoutException($info['taskName'])
                    ];
                }
            }
        }
    }

    private function killAll(): void
    {
        foreach ($this->runningProcesses as $pid => $info) {
            proc_terminate($info['process']);
            fclose($info['pipes'][1]);
            fclose($info['pipes'][2]);
            proc_close($info['process']);
        }
        $this->runningProcesses = [];
    }
}
