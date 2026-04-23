<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Events;

final readonly class TaskCancelled
{
    public function __construct(public string $taskId) {}
}
