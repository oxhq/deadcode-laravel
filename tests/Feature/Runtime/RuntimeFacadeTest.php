<?php

declare(strict_types=1);

use Deadcode\Runtime\Contracts\Task;
use Deadcode\Runtime\Runtime;
use Deadcode\Runtime\Supervisor\SupervisorTransport;

it('streams progress and returns a final task result through the runtime facade', function (): void {
    $transport = new class implements SupervisorTransport
    {
        public function run(Task $task, callable $onFrame): array
        {
            $onFrame(['type' => 'task.started', 'taskId' => 'task-1', 'name' => $task->name()]);
            $onFrame(['type' => 'task.progress', 'taskId' => 'task-1', 'message' => 'Capturing runtime snapshot', 'percent' => 20]);
            $onFrame(['type' => 'task.completed', 'taskId' => 'task-1', 'result' => ['status' => 'ok', 'data' => ['findingCount' => 3], 'meta' => ['durationMs' => 55]]]);

            return ['status' => 'ok', 'data' => ['findingCount' => 3], 'meta' => ['durationMs' => 55]];
        }
    };

    $events = [];
    $runtime = new Runtime($transport);
    $task = new class implements Task
    {
        public function name(): string
        {
            return 'deadcode.analyze_project';
        }

        public function payload(): array
        {
            return ['projectPath' => 'C:/repo'];
        }
    };

    $result = $runtime->run($task, function (array $frame) use (&$events): void {
        $events[] = $frame['type'];
    });

    expect($events)->toBe(['task.started', 'task.progress', 'task.completed']);
    expect($result->data['findingCount'])->toBe(3);
});
