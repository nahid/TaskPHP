<?php

require __DIR__ . '/../../vendor/autoload.php';

use Nahid\PHPTask\IPC\Serializer;
use Nahid\PHPTask\Contracts\TaskBootstrapInterface;
use Nahid\PHPTask\Contracts\TaskLifecycleInterface;

$serializer = new Serializer();

// Start output buffering to prevent any accidental output from corrupting the IPC
ob_start();

// Read serialized payload from STDIN
$input = stream_get_contents(STDIN);

if ($input === false || $input === '') {
    if (ob_get_level() > 0) {
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

    // Clean any accidental output from the buffer
    if (ob_get_level() > 0) {
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
    // If we're here, something failed. Clean the buffer.
    if (ob_get_level() > 0) {
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
