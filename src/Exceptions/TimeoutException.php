<?php

namespace Nahid\TaskPHP\Exceptions;

class TimeoutException extends TaskException
{
    /** @var string|int|null */
    protected $taskName;

    public function __construct($taskName = null, string $message = "Task timed out")
    {
        parent::__construct($message);
        $this->taskName = $taskName;
    }

    public function getTaskName()
    {
        return $this->taskName;
    }
}
