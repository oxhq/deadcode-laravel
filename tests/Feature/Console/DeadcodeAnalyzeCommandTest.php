<?php

declare(strict_types=1);

use Deadcode\Runtime\Runtime;
use Deadcode\Runtime\TaskResult;
use RuntimeException;

it('streams progress while running deadcode analyze', function (): void {
    $runtime = Mockery::mock(Runtime::class);
    $runtime->shouldReceive('run')
        ->once()
        ->andReturnUsing(function ($task, $onFrame) {
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

            return new TaskResult(
                status: 'ok',
                data: [
                    'findingCount' => 12,
                    'reportPath' => 'storage/app/deadcode/report.json',
                ],
                meta: ['durationMs' => 321],
            );
        });

    app()->instance(Runtime::class, $runtime);

    $this->artisan('deadcode:analyze')
        ->expectsOutput('Capturing Laravel runtime snapshot')
        ->expectsOutput('Invoking deadcore')
        ->expectsOutput('Findings: 12')
        ->expectsOutput('Report: storage/app/deadcode/report.json')
        ->assertExitCode(0);
});

it('renders runtime failures and exits non-zero', function (): void {
    $runtime = Mockery::mock(Runtime::class);
    $runtime->shouldReceive('run')
        ->once()
        ->andThrow(new RuntimeException('deadcode supervisor transport failed'));

    app()->instance(Runtime::class, $runtime);

    $this->artisan('deadcode:analyze')
        ->expectsOutputToContain('deadcode supervisor transport failed')
        ->assertExitCode(1);
});
