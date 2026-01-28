# Framework Integration Guide

## Using with WordPress, Laravel, or Other PHP Frameworks

The PHP Task library supports framework bootstrapping to ensure all framework services and functions are available in background worker processes.

There are two ways to bootstrap:

1.  **Object-oriented (Recommended)**: Use `TaskBootstrapInterface` and specialized classes like `LaravelBootstrap` or `WordPressBootstrap`. This provides automatic serialization and lifecycle hooks.
2.  **File-based (Legacy)**: Provide a path to a PHP file that initializes your framework.

---

## Object-Oriented Bootstrap (Recommended)

This is the most robust approach. It uses serialized objects to pass configuration to workers and provides optional lifecycle hooks (`beforeTask`, `afterTask`, `onError`, `shutdown`).

### Laravel Integration

Use the built-in `LaravelBootstrap` class:

```php
use Nahid\TaskPHP\Task;
use Nahid\TaskPHP\Bootstrap\LaravelBootstrap;

// Register Laravel bootstrap
$results = Task::registerBootstrap(new LaravelBootstrap(base_path()))
    ->async([
        'users' => fn() => \App\Models\User::count(),
    ])
    ->await();
```

**Features:**
- Automatically initializes Laravel Kernel
- Supports custom environments: `new LaravelBootstrap(base_path(), 'testing')`
- **Automatic Transactions**: If you enable database management in lifecycle hooks, it can handle transactions per task.

### WordPress Integration

Use the built-in `WordPressBootstrap` class:

```php
use Nahid\TaskPHP\Task;
use Nahid\TaskPHP\Bootstrap\WordPressBootstrap;

// Register WordPress bootstrap
$results = Task::registerBootstrap(new WordPressBootstrap(ABSPATH . 'wp-load.php'))
    ->async([
        'posts' => fn() => wp_count_posts()->publish,
    ])
    ->await();
```

**Features:**
- Supports Multisite: `new WordPressBootstrap($path, $blogId)`
- Supports `SHORTINIT`: `new WordPressBootstrap($path, null, true)`

### Custom Bootstraps

You can create your own bootstrap by extending `AbstractBootstrap`:

```php
use Nahid\TaskPHP\Bootstrap\AbstractBootstrap;
use Nahid\TaskPHP\Contracts\TaskInterface;

class MyCustomBootstrap extends AbstractBootstrap
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function bootstrap(): void
    {
        // Your initialization logic here
        // $this->config is automatically available here!
    }

    public function beforeTask(TaskInterface $task): void
    {
        // Optional hook before each task
    }
}
```

---

## WordPress Integration

### 1. Basic Setup

```php
use Nahid\TaskPHP\Task;

$wpBootstrap = '/path/to/wordpress/wp-load.php';

$results = Task::bootstrap($wpBootstrap)
    ->async([
        'posts' => function() {
            return wp_count_posts('post')->publish;
        }
    ]);
```

### 2. WordPress Plugin Example

```php
// In your WordPress plugin
add_action('admin_init', function() {
    $wpBootstrap = ABSPATH . 'wp-load.php';
    
    Task::bootstrap($wpBootstrap)
        ->concurrent([
            'send_emails' => function() {
                $users = get_users(['role' => 'subscriber']);
                foreach ($users as $user) {
                    wp_mail($user->user_email, 'Newsletter', 'Content...');
                }
            }
        ]);
});
```

### 3. WooCommerce Example

```php
Task::bootstrap($wpBootstrap)
    ->limit(5) // Process 5 orders at a time
    ->concurrent([
        'order_1' => function() {
            $order = wc_get_order(1);
            $order->update_status('processing');
        },
        'order_2' => function() {
            $order = wc_get_order(2);
            $order->update_status('processing');
        }
    ]);
```

---

## Laravel Integration

### 1. Create Bootstrap File

Create `laravel-bootstrap.php` in your Laravel project root:

```php
<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

// Bootstrap the application kernel (loads config, providers, etc.)
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
```

### 2. Use in Laravel

```php
use Nahid\TaskPHP\Task;

$laravelBootstrap = base_path('laravel-bootstrap.php');

$results = Task::bootstrap($laravelBootstrap)
    ->async([
        'users' => function() {
            return \App\Models\User::count();
        },
        
        'orders' => function() {
            return \App\Models\Order::today()->count();
        }
    ]);
```

### 3. Artisan Command Example

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nahid\TaskPHP\Task;

class ProcessReportsCommand extends Command
{
    protected $signature = 'reports:process';
    
    public function handle()
    {
        $bootstrap = base_path('laravel-bootstrap.php');
        
        $results = Task::bootstrap($bootstrap)
            ->limit(3)
            ->async([
                'sales' => fn() => \App\Services\Reports\SalesReport::generate(),
                'users' => fn() => \App\Services\Reports\UserReport::generate(),
                'inventory' => fn() => \App\Services\Reports\InventoryReport::generate(),
            ]);
        
        $this->info('All reports generated!');
    }
}
```

### 4. Queue Job Processing

```php
use Nahid\TaskPHP\Task;
use Nahid\TaskPHP\Contracts\TaskInterface;

class ProcessJobTask implements TaskInterface
{
    protected $jobId;
    
    public function __construct($jobId)
    {
        $this->jobId = $jobId;
    }
    
    public function handle()
    {
        $job = \App\Models\Job::find($this->jobId);
        $job->process();
        $job->save();
        
        event(new \App\Events\JobProcessed($job));
        
        return "Job {$this->jobId} complete";
    }
}

// Process multiple jobs in parallel
$bootstrap = base_path('laravel-bootstrap.php');
$jobIds = [1, 2, 3, 4, 5];

$tasks = array_map(
    fn($id) => new ProcessJobTask($id),
    $jobIds
);

Task::bootstrap($bootstrap)
    ->limit(3)
    ->concurrent($tasks);
```

---

## Other Frameworks

### Symfony

```php
// symfony-bootstrap.php
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
```

### CodeIgniter 4

```php
// ci4-bootstrap.php
require_once __DIR__ . '/vendor/autoload.php';

$pathsConfig = APPPATH . 'Config/Paths.php';
require realpath($pathsConfig) ?: $pathsConfig;

$paths = new Config\Paths();
$bootstrap = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

$app = Config\Services::codeigniter();
$app->initialize();
```

---

## Important Notes

### 1. Database Connections
Each worker process will create its own database connection. For MySQL:
- Ensure `max_connections` is sufficient
- Consider using a connection pool
- Close connections when tasks complete

### 2. File Paths
Always use **absolute paths** for bootstrap files:
```php
// ✅ Good
$bootstrap = '/var/www/html/wp-load.php';

// ❌ Bad (relative paths won't work in worker process)
$bootstrap = '../wp-load.php';
```

### 3. Memory Usage
Each worker is a separate PHP process:
- Monitor memory with `ps aux | grep php`
- Set appropriate `memory_limit` in php.ini
- Use concurrency limits to control resource usage

### 4. Shared Hosting Considerations
- Most shared hosts allow `proc_open` (check with `function_exists('proc_open')`)
- Set reasonable concurrency limits (2-3 concurrent processes)
- Avoid long-running tasks on shared hosting

---

## Troubleshooting

### "Bootstrap file not found"
```php
// Check if file exists before using
$bootstrap = '/path/to/framework/bootstrap.php';
if (!file_exists($bootstrap)) {
    throw new Exception("Bootstrap file not found: $bootstrap");
}

Task::bootstrap($bootstrap)->async([...]);
```

### "Class not found" in worker
Your bootstrap file might not be loading all autoloaders:
```php
// Ensure Composer autoloader is included
require_once __DIR__ . '/vendor/autoload.php';
```

### Database connection errors
Each process creates new connections. Check:
1. Connection limits (`SHOW VARIABLES LIKE 'max_connections'`)
2. Firewall rules
3. Database credentials in environment variables

---

## API Reference

```php
// Recommended: Register bootstrap object
Task::registerBootstrap(TaskBootstrapInterface $bootstrap): Task

// Legacy: Set bootstrap path
Task::bootstrap(string $path): Task

// Chain with other methods
Task::registerBootstrap($laravelBootstrap)
    ->limit(5)
    ->timeout(30)
    ->failFast(true)
    ->async([...]);
```

---

## Performance Tips

1. **Reuse bootstrap instances**:
```php
$task = Task::bootstrap($path)->limit(5);

$results1 = $task->async([...]); // Uses bootstrap
$results2 = $task->async([...]); // Reuses configuration
```

2. **Minimize bootstrap overhead**:
   - Only load essential services
   - Defer heavy initialization
   - Use lazy loading where possible

3. **Connection pooling**:
```php
// In your bootstrap file
// Use persistent connections
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_PERSISTENT => true
]);
```

---

For more examples, see:
- [`examples/wordpress_example.php`](../examples/wordpress_example.php)
- [`examples/laravel_example.php`](../examples/laravel_example.php)
