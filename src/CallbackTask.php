<?php

namespace Nahid\PHPTask;

use Nahid\PHPTask\Contracts\TaskInterface;

class CallbackTask implements TaskInterface
{
    /** @var callable */
    private $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function handle()
    {
        return call_user_func($this->callback);
    }
}
