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
        // 1. Find and load autoloader
        $autoloadPath = $this->basePath . '/vendor/autoload.php';
        if (!file_exists($autoloadPath)) {
            throw new \RuntimeException("Laravel vendor directory not found at: {$this->basePath}/vendor");
        }
        require_once $autoloadPath;

        // 2. Create Application instance
        // Laravel's bootstrap/app.php can return the app OR a configuration object (Laravel 11+)
        $app = require $this->basePath . '/bootstrap/app.php';

        // Laravel 11+ compatibility: if it returns a builder/configurator, we need to create the app
        if (is_object($app) && method_exists($app, 'create')) {
            $app = $app->create();
        }

        if (!$app instanceof \Illuminate\Contracts\Foundation\Application) {
            // Fallback: try to find it in the Facade application or container
            if (class_exists(\Illuminate\Support\Facades\Facade::class)) {
                $app = \Illuminate\Support\Facades\Facade::getFacadeApplication() ?: \Illuminate\Container\Container::getInstance();
            }
        }

        if (!$app instanceof \Illuminate\Contracts\Foundation\Application) {
            throw new \RuntimeException("Could not obtain a valid Laravel Application instance.");
        }

        $this->app = $app;

        // 3. Set Instance and Environment
        \Illuminate\Support\Facades\Facade::setFacadeApplication($this->app);

        if (method_exists($this->app, 'detectEnvironment')) {
            $this->app->detectEnvironment(fn() => $this->environment);
        }

        // 4. Bootstrap Kernel (Console)
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        // 5. Wire up Eloquent
        if (class_exists(\Illuminate\Database\Eloquent\Model::class) && $this->app->bound('db')) {
            $db = $this->app->make('db');
            \Illuminate\Database\Eloquent\Model::setConnectionResolver($db);

            // Fallback for some Laravel versions: set it on the Facade as well
            if (class_exists(\Illuminate\Support\Facades\DB::class)) {
                \Illuminate\Support\Facades\DB::setFacadeApplication($this->app);
            }
        }
    }

    /**
     * Start a database transaction before each task.
     */
    public function beforeTask(TaskInterface $task): void
    {
        if ($this->app && $this->app->bound('db')) {
            try {
                $this->app->make('db')->beginTransaction();
            } catch (\Throwable $e) {
                // Ignore if DB is not configured or connection fails
            }
        }
    }

    /**
     * Commit the database transaction after successful task execution.
     */
    public function afterTask(TaskInterface $task, $result): void
    {
        if ($this->app && $this->app->bound('db')) {
            try {
                $this->app->make('db')->commit();
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }

    /**
     * Rollback the database transaction on task failure.
     */
    public function onError(TaskInterface $task, \Throwable $error): void
    {
        if ($this->app && $this->app->bound('db')) {
            try {
                $this->app->make('db')->rollBack();
            } catch (\Throwable $e) {
                // Ignore
            }
        }
    }

    /**
     * Cleanup on worker shutdown.
     */
    public function shutdown(): void
    {
        if ($this->app) {
            if (method_exists($this->app, 'flush')) {
                $this->app->flush();
            }
        }
    }
}
