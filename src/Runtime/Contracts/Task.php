<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Contracts;

interface Task
{
    public function name(): string;

    public function payload(): array;
}
