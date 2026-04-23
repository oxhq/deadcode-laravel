<?php

declare(strict_types=1);

namespace Tests\Fixtures\Runtime;

use Deadcode\Runtime\Contracts\Task;
use Deadcode\Runtime\Contracts\TaskContext;
use Deadcode\Runtime\Contracts\TaskHandler;
use Deadcode\Runtime\TaskResult;

final class FixtureTaskHandler implements TaskHandler
{
    public function __construct(private readonly string $greeting) {}

    public function handle(Task $task, TaskContext $context): TaskResult
    {
        assert($task instanceof FixtureTask);

        $message = $this->greeting.' '.$task->name;

        $context->emitProgress($message, 100);

        return new TaskResult(
            status: 'ok',
            data: ['message' => $message],
            meta: [],
        );
    }
}
