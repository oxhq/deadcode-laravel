<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Events;

final readonly class TaskStdout
{
    public function __construct(
        public string $taskId,
        public string $chunk,
    ) {}
}
