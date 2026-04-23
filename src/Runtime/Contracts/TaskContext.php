<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Contracts;

interface TaskContext
{
    public function emitProgress(string $message, ?int $percent = null, array $meta = []): void;

    public function writeStdout(string $chunk): void;

    public function writeStderr(string $chunk): void;

    public function isCancellationRequested(): bool;
}
