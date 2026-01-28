<?php

namespace Nahid\TaskPHP;

use Nahid\TaskPHP\Process\ProcessManager;

/**
 * Represents a group of deferred tasks.
 */
class TaskGroup
{
    /** @var ProcessManager */
    private $manager;

    public function __construct(ProcessManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Terminate the tracking of these tasks. 
     * The parent process will not wait for these tasks to finish when it exits.
     * 
     * @return void
     */
    public function forget(): void
    {
        $this->manager->unregister();
    }



    /**
     * Wait for all deferred tasks to complete.
     *
     * @param callable|null $callback Optional closure to process results
     * @return mixed
     */
    public function await(?callable $callback = null)
    {
        $results = $this->manager->wait();

        if (is_callable($callback)) {
            return $callback($results);
        }

        return $results;
    }
}
