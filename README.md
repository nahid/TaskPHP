# TaskPHP

A framework-agnostic PHP concurrency library designed for high-performance task execution using child processes. It provides a modern, clean `async/await` pattern to manage parallel jobs without complex message brokers.

## Installation

```bash
composer require nahid/task-php
```

## Features

- **Async/Await Pattern**: Start tasks and await results when needed.
- **Concurrency Control**: Limit the number of parallel workers.
- **Timeouts**: Automatic process termination for slow tasks.
- **Fail-Fast**: Optional ability to cancel all tasks if one fails.
- **Framework Agnostic**: Works standalone or with Laravel, WordPress, etc.

## Basic Usage

### 1. Simple Async Execution
```php
use Nahid\TaskPHP\Task;

$results = Task::async([
    'task1' => fn() => 10,
    'task2' => fn() => 20,
])->await();

// $results = ['task1' => 10, 'task2' => 20]
```

### 2. Await with Result Processing
```php
$sum = Task::async([
    'a' => fn() => 10,
    'b' => fn() => 20
])->await(fn($res) => array_sum($res));

echo $sum; // 30
```

### 3. Fire and Forget (Background)
```php
Task::async([
    'send_email' => function() {
        // This runs in the background
    }
])->forget();
```

## Configuration

```php
$results = Task::limit(5)          // Concurrent workers
    ->timeout(30)                  // Max execution time
    ->bootstrap('init.php')        // Bootstrap framework
    ->failFast(true)               // Stop all on first error
    ->async($tasks)
    ->await();
```

## License
MIT (c) Nahid
