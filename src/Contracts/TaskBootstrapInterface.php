<?php

namespace Nahid\TaskPHP\Contracts;

/**
 * Minimal bootstrap interface for framework initialization.
 * 
 * Implementers only need to provide the bootstrap logic.
 * Serialization and lifecycle hooks are optional via AbstractBootstrap.
 */
interface TaskBootstrapInterface
{
    /**
     * Bootstrap the framework/application.
     * 
     * This method is called once per worker process before any tasks are executed.
     * Use this to load your framework, initialize services, set up database connections, etc.
     * 
     * Examples:
     * - Laravel: Load app kernel and bootstrap service providers
     * - WordPress: Load wp-load.php and initialize WordPress environment
     * - Symfony: Boot kernel and container
     * 
     * @return void
     */
    public function bootstrap(): void;
}
