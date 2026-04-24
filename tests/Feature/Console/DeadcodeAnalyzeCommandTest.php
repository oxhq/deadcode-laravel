<?php

declare(strict_types=1);

use Deadcode\Runtime\Runtime;
use Deadcode\Runtime\Supervisor\SupervisorTransport;
use Deadcode\Tasks\AnalyzeProjectTask;

it('streams progress while running deadcode analyze', function (): void {
    $transport = new class implements SupervisorTransport
    {
        public function run($task, callable $onFrame): array
        {
            expect($task)->toBeInstanceOf(AnalyzeProjectTask::class);

            $onFrame([
                'type' => 'task.progress',
                'taskId' => 'task-1',
                'message' => 'Capturing Laravel runtime snapshot',
                'percent' => 20,
            ]);
            $onFrame([
                'type' => 'task.progress',
                'taskId' => 'task-1',
                'message' => 'Invoking deadcore',
                'percent' => 70,
            ]);

            return [
                'status' => 'ok',
                'data' => [
                    'findingCount' => 12,
                    'reportPath' => 'storage/app/deadcode/report.json',
                ],
                'meta' => ['durationMs' => 321],
            ];
        }
    };

    app()->instance(Runtime::class, new Runtime($transport));

    $this->artisan('deadcode:analyze')
        ->expectsOutput('Capturing Laravel runtime snapshot')
        ->expectsOutput('Invoking deadcore')
        ->expectsOutputToContain('Findings: 12')
        ->expectsOutputToContain('Report: storage/app/deadcode/report.json')
        ->assertExitCode(0);
});

it('fails with a stable message when the runtime result is missing summary fields', function (): void {
    $transport = new class implements SupervisorTransport
    {
        public function run($task, callable $onFrame): array
        {
            expect($task)->toBeInstanceOf(AnalyzeProjectTask::class);

            return [
                'status' => 'ok',
                'data' => [],
                'meta' => ['durationMs' => 321],
            ];
        }
    };

    app()->instance(Runtime::class, new Runtime($transport));

    $this->artisan('deadcode:analyze')
        ->expectsOutputToContain('Runtime result missing required key [findingCount].')
        ->assertExitCode(1);
});

it('fails with a stable message when the runtime result has wrong summary field types', function (): void {
    $transport = new class implements SupervisorTransport
    {
        public function run($task, callable $onFrame): array
        {
            expect($task)->toBeInstanceOf(AnalyzeProjectTask::class);

            return [
                'status' => 'ok',
                'data' => [
                    'findingCount' => '12',
                    'reportPath' => 404,
                ],
                'meta' => ['durationMs' => 321],
            ];
        }
    };

    app()->instance(Runtime::class, new Runtime($transport));

    $this->artisan('deadcode:analyze')
        ->expectsOutputToContain('Runtime result key [findingCount] must be of type [int].')
        ->assertExitCode(1);
});
