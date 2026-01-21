<?php

namespace Nahid\PHPTask\Exceptions;

class TaskFailedException extends TaskException
{
    /** @var int */
    protected $pid;

    /** @var string|int|null */
    protected $taskName;

    /** @var string */
    protected $originalMessage;

    /** @var string */
    protected $originalTrace;

    public function __construct(string $message, int $pid, $taskName = null, string $originalTrace = '')
    {
        parent::__construct($message);
        $this->pid = $pid;
        $this->taskName = $taskName;
        $this->originalMessage = $message;
        $this->originalTrace = $originalTrace;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getTaskName()
    {
        return $this->taskName;
    }

    public function getOriginalTrace(): string
    {
        return $this->originalTrace;
    }
}
