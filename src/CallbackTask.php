<?php

namespace Nahid\TaskPHP;

use Laravel\SerializableClosure\SerializableClosure;
use Nahid\TaskPHP\Contracts\TaskInterface;

class CallbackTask implements TaskInterface
{
    /**
     * @var callable|SerializableClosure
     */
    protected $callback;

    public function __construct($callback)
    {
        if ($callback instanceof \Closure) {
            $callback = new SerializableClosure($callback);
        }

        $this->callback = $callback;
    }

    public function handle()
    {
        // SerializableClosure is invokable
        return call_user_func($this->callback);
    }
}
