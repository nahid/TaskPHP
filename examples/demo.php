<?php

require __DIR__ . '/../vendor/autoload.php';

use Nahid\TaskPHP\Task;

// 1. Basic Async/Await
echo "--- Running tasks concurrently and awaiting results ---\n";
$vals = Task::async([
    'val' => function () {
        sleep(2);
        return "5";
    }
])->await();

print_r($vals);

// 2. Await with callback processing
echo "\n--- Processing results with a callback ---\n";
$sum = Task::async([
    'a' => fn() => 10,
    'b' => fn() => 20
])->await(fn($res) => array_sum($res));

echo "Sum: $sum\n";

// 3. Fire and Forget (Background)
echo "\n--- Dispatched background task. Check background.log in 2 seconds ---\n";
Task::async([
    'bg' => function () {
        sleep(2);
        file_put_contents(__DIR__ . '/background.log', "Background job finished at " . date('H:i:s') . "\n");
    }
])->forget();

echo "Main script exiting. Worker is still running in background!\n";
