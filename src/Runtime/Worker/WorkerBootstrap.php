<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Worker;

use Deadcode\Runtime\Contracts\Task;
use Deadcode\Runtime\Contracts\TaskHandler;
use Deadcode\Runtime\Protocol\FrameCodec;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

final readonly class WorkerBootstrap
{
    public function __construct(private Container $container) {}

    public function run(string $inputLine): string
    {
        $frame = FrameCodec::decode($inputLine);
        $task = $this->makeTask($frame);
        $handler = $this->makeHandler($task);

        $context = new InMemoryTaskContext($frame['taskId'] ?? 'task-1');
        $result = $handler->handle($task, $context);

        return FrameCodec::encode([
            'type' => 'task.completed',
            'taskId' => $frame['taskId'] ?? 'task-1',
            'result' => [
                'status' => $result->status,
                'data' => $result->data,
                'meta' => $result->meta,
                'events' => $context->events(),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $frame
     */
    private function makeTask(array $frame): Task
    {
        $taskClass = $frame['taskClass'] ?? null;
        $payload = $frame['payload'] ?? null;

        if (! is_string($taskClass) || $taskClass === '') {
            throw new RuntimeException('Worker frame must include a non-empty string [taskClass].');
        }

        if (! is_array($payload)) {
            throw new RuntimeException('Worker frame must include an array [payload].');
        }

        if (! class_exists($taskClass)) {
            throw new RuntimeException(sprintf('Worker task class [%s] could not be autoloaded.', $taskClass));
        }

        $task = new $taskClass(...$payload);

        if (! $task instanceof Task) {
            throw new RuntimeException(sprintf(
                'Worker task class [%s] must implement [%s].',
                $taskClass,
                Task::class,
            ));
        }

        return $task;
    }

    private function makeHandler(Task $task): TaskHandler
    {
        $handler = $this->container->make($task::class.'Handler');

        if (! $handler instanceof TaskHandler) {
            throw new RuntimeException(sprintf(
                'Worker handler [%s] must implement [%s].',
                $task::class.'Handler',
                TaskHandler::class,
            ));
        }

        return $handler;
    }
}
