<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Events;

final readonly class TaskFailed
{
    public function __construct(
        public string $taskId,
        public string $exceptionClass,
        public string $message,
        public ?string $code = null,
        public bool $retryable = false,
    ) {}
}
