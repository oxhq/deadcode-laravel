<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Supervisor;

use Deadcode\Runtime\Contracts\Task;

interface SupervisorTransport
{
    public function run(Task $task, callable $onFrame): array;
}
