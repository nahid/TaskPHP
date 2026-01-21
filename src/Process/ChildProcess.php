<?php

namespace Nahid\PHPTask\Process;

use Exception;
use Throwable;
use Nahid\PHPTask\Contracts\TaskInterface;
use Nahid\PHPTask\IPC\Pipe;
use Nahid\PHPTask\IPC\Serializer;

class ChildProcess
{
    /** @var Serializer */
    private $serializer;

    public function __construct()
    {
        $this->serializer = new Serializer();
    }

    /**
     * Execute the task in the child process.
     * This method SHOULD NOT return. It exits the process.
     */
    public function run(TaskInterface $task, Pipe $pipe): void
    {
        // Close the reader end, we only write.
        $pipe->closeReader();

        try {
            // Execute the task
            $result = $task->handle();

            // Serialize success result
            $payload = $this->serializer->serialize($result);

            // Write to pipe
            // Suppress broken pipe errors (if parent exited)
            @$pipe->write($payload);

            // Clean up
            $pipe->closeWriter();

            exit(0);
        } catch (Throwable $e) {
            // Serialize exception
            // We rely on Serializer to handle exception formatting
            $payload = $this->serializer->serialize($e);

            try {
                // If the parent is gone, this might fail (Broken pipe).
                // We suppress errors here to support detached background execution.
                @$pipe->write($payload);
            } catch (Throwable $writeError) {
                // Ignore write errors (parent likely exited)
            }

            $pipe->closeWriter();

            exit(1);
        }
    }
}
