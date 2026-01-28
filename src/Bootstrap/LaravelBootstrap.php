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
        // Load Composer autoloader if not already loaded
        if (!file_exists($this->basePath . '/vendor/autoload.php')) {
            throw new \RuntimeException("Laravel vendor directory not found at: {$this->basePath}/vendor");
        }

        require_once $this->basePath . '/vendor/autoload.php';

        // Create Laravel application instance
        // We use require instead of require_once because app.php returns the instance
        $this->app = require $this->basePath . '/bootstrap/app.php';

        if (!$this->app instanceof \Illuminate\Foundation\Application) {
            // If it returns true (already required), we might have a problem or need to find it
            if (interface_exists(\Illuminate\Contracts\Foundation\Application::class)) {
                $this->app = \Illuminate\Support\Facades\Facade::getFacadeApplication() ?: app();
            }
        }

        if (!$this->app) {
            throw new \RuntimeException("Failed to initialize Laravel application instance.");
        }

        // Set environment
        if (method_exists($this->app, 'detectEnvironment')) {
            $this->app->detectEnvironment(fn() => $this->environment);
        }

        // Ensure Facades work
        if (class_exists(\Illuminate\Support\Facades\Facade::class)) {
            \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);
        }

        // Bootstrap the application kernel
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        // Ensure Eloquent has a connection resolver
        if ($this->app->bound('db') && class_exists(\Illuminate\Database\Eloquent\Model::class)) {
            \Illuminate\Database\Eloquent\Model::setConnectionResolver($this->app['db']);
        }
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
