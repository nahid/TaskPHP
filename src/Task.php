<?php

namespace Nahid\PHPTask;

use Nahid\PHPTask\Process\ForkManager;

class Task
{
    /** @var int */
    private $concurrencyLimit = -1;

    /** @var int|null */
    private $timeout = null;

    /** @var bool */
    private $failFast = true;

    /**
     * Set concurrency limit.
     *
     * @param int $limit
     * @return self
     */
    public static function limit(int $limit): self
    {
        $instance = new self();
        $instance->concurrencyLimit = $limit;
        return $instance;
    }

    /**
     * Set timeout in seconds.
     * 
     * @param int $seconds
     * @return self
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set fail-fast behavior.
     * 
     * @param bool $failFast
     * @return self
     */
    public function failFast(bool $failFast = true): self
    {
        $this->failFast = $failFast;
        return $this;
    }

    /**
     * Magic static call handler to support Task::async, Task::defer, and Task::concurrent.
     */
    public static function __callStatic($method, $args)
    {
        if ($method === 'async') {
            return (new self())->runAsync(...$args);
        }
        if ($method === 'defer') {
            return (new self())->runDefer(...$args);
        }
        if ($method === 'concurrent') {
            (new self())->runConcurrent(...$args);
            return;
        }

        throw new \BadMethodCallException("Static method {$method} does not exist");
    }

    /**
     * Magic call handler to support $task->async, $task->defer, and $task->concurrent.
     */
    public function __call($method, $args)
    {
        if ($method === 'async') {
            return $this->runAsync(...$args);
        }
        if ($method === 'defer') {
            return $this->runDefer(...$args);
        }
        if ($method === 'concurrent') {
            $this->runConcurrent(...$args);
            return;
        }

        throw new \BadMethodCallException("Method {$method} does not exist");
    }

    /**
     * Run tasks asynchronously.
     *
     * @param array $tasks
     * @return array
     */
    protected function runAsync(array $tasks): array
    {
        $manager = $this->createManager($tasks);
        $manager->start();
        return $manager->wait();
    }

    /**
     * Run tasks concurrently without returning results.
     *
     * @param array $tasks
     * @return void
     */
    protected function runConcurrent(array $tasks): void
    {
        $manager = $this->createManager($tasks);
        $manager->start();
        $manager->wait();
    }

    /**
     * Defer tasks (fire and return handle).
     *
     * @param array $tasks
     * @return TaskGroup
     */
    protected function runDefer(array $tasks): TaskGroup
    {
        $manager = $this->createManager($tasks);
        $manager->start();
        return new TaskGroup($manager);
    }

    private function createManager(array $tasks): ForkManager
    {
        $normalizedTasks = [];
        foreach ($tasks as $key => $task) {
            if ($task instanceof \Closure || is_callable($task)) {
                $normalizedTasks[$key] = new CallbackTask($task);
            } else {
                $normalizedTasks[$key] = $task;
            }
        }

        $manager = new ForkManager($normalizedTasks, $this->concurrencyLimit);
        $manager->setTimeout($this->timeout);
        $manager->setFailFast($this->failFast);
        return $manager;
    }
}
