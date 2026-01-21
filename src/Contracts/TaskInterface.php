<?php

namespace Nahid\PHPTask\Contracts;

interface TaskInterface
{
    /**
     * Handle the task execution.
     *
     * @return mixed
     */
    public function handle();
}
