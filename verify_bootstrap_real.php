<?php

require __DIR__ . '/vendor/autoload.php';

use Nahid\TaskPHP\Task;
use Nahid\TaskPHP\Bootstrap\LaravelBootstrap;

echo "--- Verifying Bootstrap with Autoloadable Class (LaravelBootstrap) ---\n";

// We point to a non-existent path. 
// If it works, the worker WILL try to run it and fail with a "file not found" error.
// If it skips, it will return 'skipped'.

try {
    $results = Task::bootstrap(new LaravelBootstrap(__DIR__ . '/missing-laravel-path'))
        ->async([
            'test' => fn() => 'success'
        ])
        ->await();

    echo "Result: " . print_r($results, true);
} catch (\Nahid\TaskPHP\Exceptions\TaskFailedException $e) {
    echo "Caught expected error: " . $e->getMessage() . "\n";
    if (strpos($e->getMessage(), 'missing-laravel-path') !== false) {
        echo "\nVerification: SUCCESS! The worker correctly identified and attempted to run the Bootstrap object.\n";
    }
} catch (\Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}
