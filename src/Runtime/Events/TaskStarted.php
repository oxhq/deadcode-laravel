<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Events;

final readonly class TaskStarted
{
    public function __construct(
        public string $taskId,
        public string $name,
    ) {}
}
