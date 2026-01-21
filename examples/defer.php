<?php

require __DIR__ . '/../vendor/autoload.php';

use Nahid\PHPTask\Task;
use Nahid\PHPTask\Contracts\TaskInterface;
use Nahid\PHPTask\Exceptions\TaskFailedException;
use Nahid\PHPTask\Exceptions\TimeoutException;

Task::defer([
    function () {
        sleep(5);
        file_put_contents('defer.txt', 'I survived ' . time() . "\n", FILE_APPEND);
    }
]);

echo "Finished";
