<?php

namespace Nahid\PHPTask;

use Nahid\PHPTask\Process\ForkManager;

class TaskGroup
{
    /** @var ForkManager */
    private $manager;

    public function __construct(ForkManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Wait for all deferred tasks to complete.
     *
     * @return array
     */
    public function await(): array
    {
        return $this->manager->wait();
    }
}
