<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Events;

final readonly class TaskCompleted
{
    public function __construct(
        public string $taskId,
        public array $result,
    ) {}
}
