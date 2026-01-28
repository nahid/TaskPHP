<?php

namespace Nahid\TaskPHP\Bootstrap;

use Nahid\TaskPHP\Contracts\TaskInterface;

/**
 * Laravel framework bootstrap for background tasks.
 * 
 * This bootstrap loads the Laravel application kernel and initializes
 * all service providers, making Eloquent, facades, and the service
 * container available in worker processes.
 * 
 * Example usage:
 * 
 *     Task::registerBootstrap(new LaravelBootstrap(
 *         basePath: base_path(),
 *         environment: 'production'
 *     ))->async([...]);
 */
class LaravelBootstrap extends AbstractBootstrap
{
    protected $app = null;
    protected $basePath;
    protected $environment;

    public function __construct(
        string $basePath,
        string $environment = 'production'
    ) {
        $this->basePath = $basePath;
        $this->environment = $environment;
    }

    /**
     * Bootstrap Laravel application.
     */
    public function bootstrap(): void
    {
        // Load Composer autoloader
        require_once $this->basePath . '/vendor/autoload.php';

        // Create Laravel application instance
        $this->app = require_once $this->basePath . '/bootstrap/app.php';

        // Set environment
        $this->app->detectEnvironment(fn() => $this->environment);

        // Bootstrap the application kernel
        // This loads configuration, service providers, facades, etc.
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        // Now all Laravel features are available:
        // - Eloquent models (User::find())
        // - Facades (DB::, Cache::, etc.)
        // - Helper functions (config(), app(), etc.)
        // - Service container
        // - Queue system
    }

    /**
     * Start a database transaction before each task.
     */
    public function beforeTask(TaskInterface $task): void
    {
        if ($this->app && $this->app->bound('db')) {
            \DB::beginTransaction();
        }
    }

    /**
     * Commit the database transaction after successful task execution.
     */
    public function afterTask(TaskInterface $task, $result): void
    {
        if ($this->app && $this->app->bound('db')) {
            \DB::commit();
        }
    }

    /**
     * Rollback the database transaction on task failure.
     */
    public function onError(TaskInterface $task, \Throwable $error): void
    {
        if ($this->app && $this->app->bound('db')) {
            \DB::rollBack();
        }
    }

    /**
     * Cleanup on worker shutdown.
     */
    public function shutdown(): void
    {
        if ($this->app) {
            $this->app->flush();
        }
    }
}
