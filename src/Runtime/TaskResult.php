<?php

declare(strict_types=1);

namespace Deadcode\Runtime;

final readonly class TaskResult
{
    public function __construct(
        public string $status,
        public array $data = [],
        public array $meta = [],
    ) {}
}
