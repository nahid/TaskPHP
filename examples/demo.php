<?php

require __DIR__ . '/../vendor/autoload.php';

use Nahid\PHPTask\Task;
use Nahid\PHPTask\Contracts\TaskInterface;
use Nahid\PHPTask\Exceptions\TaskFailedException;
use Nahid\PHPTask\Exceptions\TimeoutException;

class SleepTask implements TaskInterface
{
    private $seconds;
    private $id;

    public function __construct($id, $seconds)
    {
        $this->id = $id;
        $this->seconds = $seconds;
    }

    public function handle()
    {
        sleep($this->seconds);
        return "Task {$this->id} slept {$this->seconds}s";
    }
}

class FailTask implements TaskInterface
{
    public function handle()
    {
        throw new \Exception("Boom!");
    }
}

class TimeoutTask implements TaskInterface
{
    public function handle()
    {
        sleep(5);
        return "Should not finish";
    }
}

echo "1. Testing simple async (Parallel Sleep)...\n";
$start = microtime(true);
$results = Task::async([
    't1' => new SleepTask(1, 2),
    't2' => new SleepTask(2, 2),
]);
$end = microtime(true);
$duration = $end - $start;
echo "Duration: " . number_format($duration, 2) . "s (Expected ~2.0s)\n";
print_r($results);

echo "\n2. Testing Concurrency Limit (5 tasks, limit 2)...\n";
$start = microtime(true);
$results = Task::limit(2)->async([
    new SleepTask(1, 1),
    new SleepTask(2, 1),
    new SleepTask(3, 1),
    new SleepTask(4, 1),
    new SleepTask(5, 1),
]);
$end = microtime(true);
$duration = $end - $start;
echo "Duration: " . number_format($duration, 2) . "s (Expected ~3.0s)\n";
// 2 run (1s), 2 run (1s), 1 run (1s) -> total 3s

echo "\n3. Testing Fail Fast...\n";
try {
    Task::async([
        new SleepTask('good', 1),
        new FailTask(),
    ]);
} catch (TaskFailedException $e) {
    echo "Caught expected TaskFailedException: " . $e->getMessage() . "\n";
}

echo "\n4. Testing Collect Errors...\n";
$results = Task::limit(2)->failFast(false)->async([
    'good' => new SleepTask('good', 1),
    'bad' => new FailTask(),
]);
print_r($results);

echo "\n5. Testing Timeout...\n";
try {
    Task::limit(2)->timeout(1)->async([
        'timeout' => new TimeoutTask(),
    ]);
} catch (TimeoutException $e) {
    echo "Caught expected TimeoutException for task: " . $e->getTaskName() . "\n";
}

echo "\n6. Testing Concurrent (void return)...\n";
$start = microtime(true);
$result = Task::concurrent([
    new SleepTask('c1', 1),
    new SleepTask('c2', 1),
]);
$end = microtime(true);
$duration = $end - $start;
echo "Duration: " . number_format($duration, 2) . "s (Expected ~1.0s)\n";
echo "Result is: " . var_export($result, true) . "\n";

echo "\n7. Testing Closures...\n";
$start = microtime(true);
$results = Task::async([
    'closure1' => function () {
        sleep(1);
        return "Closure 1 executed";
    },
    'closure2' => function () {
        sleep(1);
        return "Closure 2 executed";
    }
]);
$end = microtime(true);
$duration = $end - $start;
echo "Duration: " . number_format($duration, 2) . "s (Expected ~1.0s)\n";
print_r($results);

echo "\nDone.\n";
