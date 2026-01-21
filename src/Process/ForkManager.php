<?php

namespace Nahid\PHPTask\Process;

use Exception;
use RuntimeException;
use Nahid\PHPTask\Contracts\TaskInterface;
use Nahid\PHPTask\Exceptions\TaskFailedException;
use Nahid\PHPTask\Exceptions\TimeoutException;
use Nahid\PHPTask\IPC\Pipe;
use Nahid\PHPTask\IPC\Serializer;

class ForkManager
{
    /** @var array<string, TaskInterface> */
    private $pendingTasks = [];

    /** @var array<int, array> [pid => ['pipe' => Pipe, 'taskName' => string, 'startTime' => int]] */
    private $runningProcesses = [];

    /** @var array */
    private $results = [];

    /** @var array */
    private $errors = [];

    /** @var int */
    private $concurrencyLimit;

    /** @var int|null */
    private $timeout;

    /** @var ChildProcess */
    private $childRunner;

    /** @var Serializer */
    private $serializer;

    /** @var bool */
    private $failFast = true;

    /** @var ForkManager[] */
    private static $registry = [];

    /** @var string */
    private $id;

    public function __construct(array $tasks, int $concurrencyLimit = -1)
    {
        $this->pendingTasks = $tasks;
        $this->concurrencyLimit = $concurrencyLimit > 0 ? $concurrencyLimit : count($tasks);
        if ($this->concurrencyLimit <= 0) {
            $this->concurrencyLimit = 1; // Safety fallback
        }

        $this->childRunner = new ChildProcess();
        $this->serializer = new Serializer();

        // Register instance
        $this->id = uniqid();
        self::$registry[$this->id] = $this;
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
            $this->checkChildren();
            $this->checkTimeouts();
            $this->replenish();

            if (!empty($this->runningProcesses)) {
                // Sleep briefly to avoid CPU spin, but short enough to be responsive
                usleep(10000); // 10ms
            }
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
        while (!empty($this->pendingTasks) && count($this->runningProcesses) < $this->concurrencyLimit) {
            $taskName =  array_key_first($this->pendingTasks);
            $task = array_shift($this->pendingTasks); // Get and remove first task

            $this->spawn($taskName, $task);
        }
    }

    private function spawn($taskName, TaskInterface $task): void
    {
        $pipe = new Pipe();
        $pid = pcntl_fork();

        if ($pid == -1) {
            throw new RuntimeException("Failed to fork process for task: $taskName");
        }

        if ($pid) {
            // Parent
            $pipe->closeWriter(); // Parent reads
            $this->runningProcesses[$pid] = [
                'pipe' => $pipe,
                'taskName' => $taskName,
                'startTime' => time(),
            ];
        } else {
            // Child
            // Pass execution to ChildProcess handler
            // This will exit script, so no return
            $this->childRunner->run($task, $pipe);
        }
    }

    private function checkChildren(): void
    {
        // Check for exited children
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            if (isset($this->runningProcesses[$pid])) {
                $this->handleCompletedProcess($pid, $status);
            }
        }
    }

    private function handleCompletedProcess(int $pid, int $status): void
    {
        $info = $this->runningProcesses[$pid];
        $pipe = $info['pipe'];
        $taskName = $info['taskName'];

        unset($this->runningProcesses[$pid]);

        // Check exit code
        $exitCode = pcntl_wexitstatus($status);

        try {
            $rawPayload = $pipe->read();
            $data = $this->serializer->unserialize($rawPayload);
            $pipe->close();
        } catch (Exception $e) {
            $data = $e; // Failure reading pipe
            $exitCode = 1; // Force failure
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
                posix_kill($pid, SIGKILL);

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

                // Reap it explicitly to avoid zombie
                pcntl_waitpid($pid, $status, WNOHANG);

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
            posix_kill($pid, SIGKILL);
            pcntl_waitpid($pid, $status, WNOHANG); // Reap
        }
        $this->runningProcesses = [];
    }
}
