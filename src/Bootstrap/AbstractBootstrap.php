<?php

namespace Nahid\TaskPHP\Bootstrap;

use Nahid\TaskPHP\Contracts\TaskBootstrapInterface;
use Nahid\TaskPHP\Contracts\TaskLifecycleInterface;
use Nahid\TaskPHP\Contracts\TaskInterface;

/**
 * Abstract base class for bootstrap implementations.
 * 
 * Provides:
 * - Automatic serialization via __serialize/__unserialize
 * - Default empty implementations for all lifecycle hooks
 * - Type-safe property access
 * 
 * Extend this class and only override what you need:
 * - bootstrap() is REQUIRED
 * - Lifecycle methods are OPTIONAL
 */
abstract class AbstractBootstrap implements TaskBootstrapInterface, TaskLifecycleInterface
{
    /**
     * Bootstrap the framework (required override).
     */
    abstract public function bootstrap(): void;

    /**
     * Optional: Called before each task execution.
     */
    public function beforeTask(TaskInterface $task): void
    {
        // Default: do nothing
    }

    /**
     * Optional: Called after successful task execution.
     */
    public function afterTask(TaskInterface $task, $result): void
    {
        // Default: do nothing
    }

    /**
     * Optional: Called when a task throws an exception.
     */
    public function onError(TaskInterface $task, \Throwable $error): void
    {
        // Default: do nothing
    }

    /**
     * Optional: Called when worker process shuts down.
     */
    public function shutdown(): void
    {
        // Default: do nothing
    }

    /**
     * Serialize the bootstrap instance.
     * 
     * This method uses reflection to get all constructor parameters
     * and serialize them. This works automatically with readonly
     * properties and constructor property promotion.
     * 
     * @return array
     */
    public function __serialize(): array
    {
        $reflection = new \ReflectionClass($this);
        $data = [];

        // Get all properties (including readonly)
        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $data[$property->getName()] = $property->getValue($this);
        }

        return $data;
    }

    /**
     * Unserialize the bootstrap instance.
     * 
     * This method reconstructs the object from serialized data.
     * 
     * @param array $data
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $reflection = new \ReflectionClass($this);

        foreach ($data as $name => $value) {
            if ($reflection->hasProperty($name)) {
                $property = $reflection->getProperty($name);
                $property->setAccessible(true);
                $property->setValue($this, $value);
            }
        }
    }
}
