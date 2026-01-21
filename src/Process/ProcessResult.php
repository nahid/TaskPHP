<?php

namespace Nahid\PHPTask\Process;

class ProcessResult
{
    /** @var int */
    public $pid;

    /** @var mixed */
    public $result;

    /** @var mixed|null */
    public $error;

    /** @var string|int|null */
    public $taskName;

    /** @var bool */
    public $success;

    public function __construct(int $pid, $taskName, bool $success, $result = null, $error = null)
    {
        $this->pid = $pid;
        $this->taskName = $taskName;
        $this->success = $success;
        $this->result = $result;
        $this->error = $error;
    }
}
