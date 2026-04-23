<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Contracts;

use Deadcode\Runtime\TaskResult;

interface TaskHandler
{
    public function handle(Task $task, TaskContext $context): TaskResult;
}
