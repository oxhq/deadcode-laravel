<?php

declare(strict_types=1);

use Deadcode\Console\Commands\DeadcodeAnalyzeCommand;
use Deadcode\Runtime\Contracts\Task;
use Deadcode\Runtime\Runtime;
use Deadcode\Runtime\Supervisor\SupervisorTransport;
use Symfony\Component\Console\Tester\CommandTester;

it('streams progress while running deadcode analyze', function (): void {
    $runtime = new Runtime(new class implements SupervisorTransport
    {
        public function run(Task $task, callable $onFrame): array
        {
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
    });

    app()->instance(Runtime::class, $runtime);

    $tester = executeRuntimeAnalyzeCommand();

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())->toContain('Capturing Laravel runtime snapshot')
        ->and($tester->getDisplay())->toContain('Invoking deadcore')
        ->and($tester->getDisplay())->toContain('Findings: 12')
        ->and($tester->getDisplay())->toContain('Report: storage/app/deadcode/report.json');
});

it('renders runtime failures and exits non-zero', function (): void {
    $runtime = new Runtime(new class implements SupervisorTransport
    {
        public function run(Task $task, callable $onFrame): array
        {
            throw new RuntimeException('deadcode supervisor transport failed');
        }
    });

    app()->instance(Runtime::class, $runtime);

    $tester = executeRuntimeAnalyzeCommand();

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('deadcode supervisor transport failed');
});

function executeRuntimeAnalyzeCommand(array $input = []): CommandTester
{
    $command = app()->make(DeadcodeAnalyzeCommand::class);
    $command->setLaravel(app());

    $tester = new CommandTester($command);
    $tester->execute($input);

    return $tester;
}
