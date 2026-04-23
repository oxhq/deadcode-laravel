<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Events;

final readonly class TaskProgress
{
    public function __construct(
        public string $taskId,
        public string $message,
        public ?int $percent = null,
        public array $meta = [],
    ) {}
}
