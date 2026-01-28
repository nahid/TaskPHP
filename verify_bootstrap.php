<?php

require __DIR__ . '/vendor/autoload.php';

use Nahid\TaskPHP\Task;
use Nahid\TaskPHP\Bootstrap\AbstractBootstrap;
use Nahid\TaskPHP\Contracts\TaskInterface;

class VerificationBootstrap extends AbstractBootstrap
{
    public $initialized = false;
    public $beforeHookCalled = false;
    public $configValue;

    public function __construct($val)
    {
        $this->configValue = $val;
    }

    public function bootstrap(): void
    {
        define('BOOTSTRAP_SUCCESS', true);
        define('CONFIG_VALUE', $this->configValue);
    }

    public function beforeTask(\Nahid\TaskPHP\Contracts\TaskInterface $task): void
    {
        // In a real worker, this would happen in a separate process
        // We will return this state in the task result to verify
    }
}

echo "--- Testing Bootstrap Serialization & Execution ---\n";

$bootstrap = new VerificationBootstrap('secret_key');

$results = Task::bootstrap($bootstrap)
    ->async([
        'check' => function () {
            return [
                'bootstrapped' => defined('BOOTSTRAP_SUCCESS'),
                'config_passed' => (defined('CONFIG_VALUE') ? CONFIG_VALUE : null),
                'instance_type' => 'verified'
            ];
        }
    ])
    ->await();

print_r($results);

if ($results['check']['bootstrapped'] && $results['check']['config_passed'] === 'secret_key') {
    echo "\nVerification: SUCCESS! Interface and Serialization are working perfectly.\n";
} else {
    echo "\nVerification: FAILED!\n";
    exit(1);
}
