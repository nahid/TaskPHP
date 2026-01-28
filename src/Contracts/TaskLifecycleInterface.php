<?php

namespace Nahid\PHPTask\Contracts;

/**
 * Optional lifecycle hooks for task execution.
 * 
 * Implement this interface to add custom logic at different stages
 * of the task lifecycle. All methods have default empty implementations
 * in AbstractBootstrap, so you only need to override what you need.
 */
interface TaskLifecycleInterface
{
    /**
     * Called before each task execution in the worker process.
     * 
     * Use this for:
     * - Starting database transactions
     * - Setting up request context
     * - Initializing per-task resources
     * 
     * @param TaskInterface $task The task about to be executed
     * @return void
     */
    public function beforeTask(TaskInterface $task): void;

    /**
     * Called after successful task execution.
     * 
     * Use this for:
     * - Committing database transactions
     * - Cleaning up resources
     * - Logging success
     * 
     * @param TaskInterface $task The task that was executed
     * @param mixed $result The result returned by the task
     * @return void
     */
    public function afterTask(TaskInterface $task, $result): void;

    /**
     * Called when a task throws an exception.
     * 
     * Use this for:
     * - Rolling back database transactions
     * - Logging errors
     * - Cleanup on failure
     * 
     * @param TaskInterface $task The task that failed
     * @param \Throwable $error The exception that was thrown
     * @return void
     */
    public function onError(TaskInterface $task, \Throwable $error): void;

    /**
     * Called when the worker process is shutting down.
     * 
     * Use this for:
     * - Closing connections
     * - Final cleanup
     * - Restoring state (e.g., WordPress multisite blog)
     * 
     * @return void
     */
    public function shutdown(): void;
}
