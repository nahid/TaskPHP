<?php

require __DIR__ . '/../vendor/autoload.php';

use Nahid\TaskPHP\Task;
use Nahid\TaskPHP\Bootstrap\AbstractBootstrap;
use Nahid\TaskPHP\Bootstrap\LaravelBootstrap;
use Nahid\TaskPHP\Bootstrap\WordPressBootstrap;

/**
 * BOOTSTRAP SYSTEM EXAMPLES
 * 
 * The bootstrap system allows you to initialize your framework (Laravel, WordPress, etc.)
 * so that full functionality is available inside background tasks.
 */

// 1. Using built-in Laravel Bootstrap
echo "--- Example: Laravel Bootstrap ---\n";
echo "Note: This is a code demonstration. Paths must be valid for execution.\n\n";

/*
Task::bootstrap(new LaravelBootstrap(
    basePath: '/var/www/html',
    environment: 'production'
))->async([
    'stats' => fn() => \App\Models\User::count() // Eloquent works here!
])->await();
*/


// 2. Creating a Custom Bootstrap
// Custom bootstraps must be autoloadable or required before use.
// We extend AbstractBootstrap to get automatic serialization and lifecycle hooks.

class MyCustomFrameworkBootstrap extends AbstractBootstrap
{
    private $dbConfig;

    public function __construct(array $dbConfig)
    {
        $this->dbConfig = $dbConfig;
    }

    /**
     * This method runs inside every worker process before tasks execute.
     */
    public function bootstrap(): void
    {
        // define('MY_APP_INITIALIZED', true);
        // $connection = connect($this->dbConfig); 
        echo "[Worker] Framework initialized with DB: " . $this->dbConfig['host'] . "\n";
    }

    /**
     * Optional hook: Runs before every single task in this group.
     */
    public function beforeTask(\Nahid\TaskPHP\Contracts\TaskInterface $task): void
    {
        echo "[Worker] Starting task transaction...\n";
    }
}

// 3. Using the custom bootstrap
echo "--- Example: Custom Bootstrap ---\n";

$dbConfig = ['host' => 'localhost', 'user' => 'admin'];

$results = Task::bootstrap(new MyCustomFrameworkBootstrap($dbConfig))
    ->async([
        'process' => function () {
            return "Task executed in initialized environment";
        }
    ])
    ->await();

print_r($results);

echo "\nSummary: The bootstrap object is serialized and sent to the worker,";
echo "where it automatically re-initializes your environment.\n";
