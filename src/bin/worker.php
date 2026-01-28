<?php

// Start output buffering as early as possible to prevent any accidental output from corrupting the IPC
ob_start();

$autoloadPath = null;
$currentDir = __DIR__;
while ($currentDir !== '/' && $currentDir !== '.') {
    $path = $currentDir . '/vendor/autoload.php';
    if (file_exists($path)) {
        $autoloadPath = $path;
        break;
    }

    // Check one level higher
    $parentDir = dirname($currentDir);
    if ($parentDir === $currentDir)
        break;
    $currentDir = $parentDir;
}

if (!$autoloadPath && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
}

if (!$autoloadPath && file_exists(__DIR__ . '/../../../../autoload.php')) {
    $autoloadPath = __DIR__ . '/../../../../autoload.php';
}

if (!$autoloadPath) {
    fwrite(STDERR, "Could not find autoloader. Check your vendor directory.\n");
    exit(1);
}

require $autoloadPath;

use Nahid\TaskPHP\IPC\Serializer;
use Nahid\TaskPHP\Contracts\TaskBootstrapInterface;
use Nahid\TaskPHP\Contracts\TaskLifecycleInterface;

$serializer = new Serializer();

// Read serialized payload from STDIN
$input = stream_get_contents(STDIN);

if ($input === false || $input === '') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    exit(1);
}

$bootstrap = null;
$task = null;

try {
    // Unserialize the payload (contains bootstrap and task)
    $payload = $serializer->unserialize($input);

    // Handle bootstrap (object or file path)
    if (isset($payload['bootstrap']) && $payload['bootstrap']) {
        $bootstrapData = $payload['bootstrap'];

        if ($bootstrapData instanceof TaskBootstrapInterface) {
            // Object-based bootstrap (new approach)
            $bootstrap = $bootstrapData;
            $bootstrap->bootstrap();
        } elseif (is_string($bootstrapData)) {
            // File-based bootstrap (legacy compatibility)
            if (file_exists($bootstrapData)) {
                require $bootstrapData;
            } else {
                throw new RuntimeException("Bootstrap file not found: {$bootstrapData}");
            }
        }
    }

    // Extract the task
    $task = isset($payload['task']) ? $payload['task'] : $payload;

    // Call beforeTask hook if available
    if ($bootstrap instanceof TaskLifecycleInterface) {
        $bootstrap->beforeTask($task);
    }

    // Execute the task
    if (is_callable($task)) {
        $result = $task();
    } elseif (is_object($task) && method_exists($task, 'handle')) {
        $result = $task->handle();
    } else {
        throw new RuntimeException("Invalid task format");
    }

    // Call afterTask hook if available
    if ($bootstrap instanceof TaskLifecycleInterface) {
        $bootstrap->afterTask($task, $result);
    }

    $output = $serializer->serialize($result);

    // Clean all accidental output from the buffer
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Write result to STDOUT
    fwrite(STDOUT, $output);

    // Call shutdown hook if available
    if ($bootstrap instanceof TaskLifecycleInterface) {
        $bootstrap->shutdown();
    }

    exit(0);

} catch (Throwable $e) {
    // If we're here, something failed. Clean all buffers.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Call onError hook if available
    if ($bootstrap instanceof TaskLifecycleInterface) {
        try {
            $bootstrap->onError($task ?? null, $e);
        } catch (Throwable $hookError) {
            // Ignore errors in error hook
        }
    }

    // Write error to STDOUT (serialized exception)
    $output = $serializer->serialize($e);
    fwrite(STDOUT, $output);

    // Call shutdown hook even on error
    if ($bootstrap instanceof TaskLifecycleInterface) {
        try {
            $bootstrap->shutdown();
        } catch (Throwable $shutdownError) {
            // Ignore errors in shutdown hook
        }
    }

    exit(1);
}
