<?php

namespace Nahid\TaskPHP\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Nahid\TaskPHP\Task;
use Nahid\TaskPHP\Exceptions\TaskFailedException;
use Nahid\TaskPHP\Exceptions\TimeoutException;

class TaskTest extends TestCase
{
    public function testAsyncExecution()
    {
        $start = microtime(true);
        $results = Task::async([
            'task1' => function () {
                usleep(500000); // 500ms
                return 1;
            },
            'task2' => function () {
                usleep(500000); // 500ms
                return 2;
            }
        ])->await();

        $duration = microtime(true) - $start;

        $this->assertArrayHasKey('task1', $results);
        $this->assertArrayHasKey('task2', $results);
        $this->assertEquals(1, $results['task1']);
        $this->assertEquals(2, $results['task2']);

        // Sequential would be > 1.0s
        $this->assertLessThan(0.9, $duration);
    }

    public function testOutputLimit()
    {
        try {
            Task::outputLimit(100)->async([
                'big_output' => function () {
                    // Returning more than 100 bytes will be caught by the limit
                    return str_repeat('A', 200);
                }
            ])->await();
            $this->fail("Should have thrown exception due to output limit exceeded");
        } catch (TaskFailedException $e) {
            $this->assertEquals('big_output', $e->getTaskName());
        }
    }

    public function testOutputLimitChaining()
    {
        try {
            (new Task())->outputLimit(100)->async([
                'big_output_chain' => function () {
                    return str_repeat('A', 200);
                }
            ])->await();
            $this->fail("Should have thrown exception due to output limit exceeded (chained)");
        } catch (TaskFailedException $e) {
            $this->assertEquals('big_output_chain', $e->getTaskName());
        }
    }

    public function testTimeout()
    {
        try {
            Task::timeout(1)->async([
                'slow' => function () {
                    sleep(2);
                    return 'slow';
                }
            ])->await();
            $this->fail("Should have timed out");
        } catch (TimeoutException $e) {
            $this->assertEquals('slow', $e->getTaskName());
        }
    }

    public function testAwaitWithCallback()
    {
        $sum = Task::async([
            'a' => fn() => 10,
            'b' => fn() => 20
        ])->await(fn($res) => array_sum($res));

        $this->assertEquals(30, $sum);
    }

    public function testForgetExecution()
    {
        $file = sys_get_temp_dir() . '/task_test_forget.txt';
        if (file_exists($file))
            unlink($file);

        Task::async([
            'forget_test' => function () use ($file) {
                sleep(1);
                file_put_contents($file, 'done');
            }
        ])->forget();

        // The file should NOT exist yet as we didn't await and it sleeps for 1s
        $this->assertFileDoesNotExist($file);

        // Wait a bit for the background process to finish
        sleep(2);
        $this->assertFileExists($file);
        unlink($file);
    }
}
