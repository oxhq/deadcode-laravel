<?php

declare(strict_types=1);

use Deadcode\Runtime\Contracts\Task;
use Deadcode\Runtime\TaskResult;

it('serializes a task payload and task result with stable keys', function (): void {
    $task = new class ('C:/repo', 'json') implements Task {
        public function __construct(
            private readonly string $projectPath,
            private readonly string $format,
        ) {}

        public function name(): string
        {
            return 'deadcode.analyze_project';
        }

        public function payload(): array
        {
            return [
                'projectPath' => $this->projectPath,
                'format' => $this->format,
            ];
        }
    };

    $result = new TaskResult(
        status: 'ok',
        data: ['reportPath' => 'storage/app/deadcode/report.json'],
        meta: ['durationMs' => 42],
    );

    expect($task->name())->toBe('deadcode.analyze_project');
    expect($task->payload())->toBe([
        'projectPath' => 'C:/repo',
        'format' => 'json',
    ]);
    expect($result->status)->toBe('ok');
    expect($result->data)->toBe(['reportPath' => 'storage/app/deadcode/report.json']);
    expect($result->meta)->toBe(['durationMs' => 42]);
});
