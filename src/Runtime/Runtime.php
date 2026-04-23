<?php

declare(strict_types=1);

namespace Deadcode\Runtime;

use Deadcode\Runtime\Contracts\Task;
use Deadcode\Runtime\Supervisor\SupervisorTransport;

final readonly class Runtime
{
    public function __construct(private SupervisorTransport $transport) {}

    public function run(Task $task, ?callable $onFrame = null): TaskResult
    {
        $payload = $this->transport->run($task, $onFrame ?? static fn (): null => null);

        return new TaskResult(
            status: $payload['status'],
            data: $payload['data'] ?? [],
            meta: $payload['meta'] ?? [],
        );
    }
}
