<?php

namespace Nahid\TaskPHP\Contracts;

interface TaskInterface
{
    /**
     * Handle the task execution.
     *
     * @return mixed
     */
    public function handle();
}
