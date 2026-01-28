<?php

namespace Nahid\PHPTask;

use Nahid\PHPTask\Process\ProcessManager;
use Nahid\PHPTask\Contracts\TaskBootstrapInterface;

class Task
{
    /** @var int */
    private $concurrencyLimit = -1;

    /** @var int|null */
    private $outputLimit = null;

    /** @var int|null */
    private $timeout = null;

    /** @var bool */
    private $failFast = true;

    /** @var TaskBootstrapInterface|string|null */
    private $bootstrap = null;

    /**
     * Set concurrency limit.
     */
    protected function setLimit(int $limit): self
    {
        $this->concurrencyLimit = $limit;
        return $this;
    }

    /**
     * Set bootstrap (object or path).
     *
     * @param TaskBootstrapInterface|string|null $bootstrap
     */
    protected function setBootstrap($bootstrap): self
    {
        $this->bootstrap = $bootstrap;
        return $this;
    }

    /**
     * Set task execution timeout in seconds.
     */
    protected function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set fail fast mode.
     */
    protected function setFailFast(bool $failFast = true): self
    {
        $this->failFast = $failFast;
        return $this;
    }

    /**
     * Set maximum output size in bytes for worker processes.
     */
    protected function setOutputLimit(int $bytes): self
    {
        $this->outputLimit = $bytes;
        return $this;
    }

    /**
     * Entry point for running tasks asynchronously.
     * Retruns a TaskGroup handle for awaiting or forgetting results.
     *
     * @param array $tasks
     * @return TaskGroup
     */
    protected function executeAsync(array $tasks): TaskGroup
    {
        $manager = $this->createManager($tasks);
        $manager->start();
        return new TaskGroup($manager);
    }

    /**
     * Register a bootstrap object (static helper).
     */
    public static function registerBootstrap(TaskBootstrapInterface $bootstrap): self
    {
        return (new self())->setBootstrap($bootstrap);
    }

    /**
     * Static entry point proxy.
     */
    public static function __callStatic($method, $args)
    {
        $instance = new self();
        return $instance->__call($method, $args);
    }

    /**
     * Dynamic method proxy to support fluent chaining after static calls.
     */
    public function __call($method, $args)
    {
        $map = [
            'async' => 'executeAsync',
            'limit' => 'setLimit',
            'timeout' => 'setTimeout',
            'bootstrap' => 'setBootstrap',
            'failFast' => 'setFailFast',
            'outputLimit' => 'setOutputLimit'
        ];

        if (isset($map[$method])) {
            return $this->{$map[$method]}(...$args);
        }

        throw new \BadMethodCallException("Method {$method} does not exist");
    }

    /**
     * Create and configure a ProcessManager instance.
     */
    private function createManager(array $tasks): ProcessManager
    {
        $normalizedTasks = [];
        foreach ($tasks as $key => $task) {
            if ($task instanceof \Closure || is_callable($task)) {
                $normalizedTasks[$key] = new CallbackTask($task);
            } else {
                $normalizedTasks[$key] = $task;
            }
        }

        $manager = new ProcessManager($normalizedTasks, $this->concurrencyLimit);

        if ($this->timeout !== null) {
            $manager->setTimeout($this->timeout);
        }

        if ($this->outputLimit !== null) {
            $manager->setOutputLimit($this->outputLimit);
        }

        $manager->setFailFast($this->failFast);
        $manager->setBootstrap($this->bootstrap);

        return $manager;
    }
}
