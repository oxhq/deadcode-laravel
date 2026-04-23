<?php

declare(strict_types=1);

use Deadcode\Runtime\Contracts\Task;
use Deadcode\Runtime\Supervisor\GoSupervisorProcessTransport;

it('streams supervisor frames before process completion and uses a unique task id per run', function (): void {
    $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'deadcode-transport-'.bin2hex(random_bytes(8));
    mkdir($tempDir, 0777, true);

    $signalPath = $tempDir.DIRECTORY_SEPARATOR.'callback.signal';
    $binary = makePortablePhpCommand($tempDir, 'fake-supervisor', <<<'PHP'
$input = trim((string) stream_get_contents(STDIN));
$frame = json_decode($input, true, flags: JSON_THROW_ON_ERROR);
$taskId = $frame['taskId'] ?? null;

fwrite(STDOUT, json_encode([
    'type' => 'task.started',
    'taskId' => $taskId,
    'name' => $frame['name'] ?? null,
], JSON_THROW_ON_ERROR) . PHP_EOL);
fflush(STDOUT);

$signalPath = $frame['payload']['signalPath'] ?? null;
$deadline = microtime(true) + 1.5;
while (! is_file($signalPath) && microtime(true) < $deadline) {
    usleep(10_000);
}

if (! is_file($signalPath)) {
    fwrite(STDERR, "callback signal not observed\n");
    exit(2);
}

fwrite(STDOUT, json_encode([
    'type' => 'task.completed',
    'taskId' => $taskId,
    'result' => [
        'status' => 'ok',
        'data' => ['taskId' => $taskId],
        'meta' => [],
    ],
], JSON_THROW_ON_ERROR) . PHP_EOL);
fflush(STDOUT);
PHP);

    $transport = new GoSupervisorProcessTransport($binary, 5);
    $task = new class($signalPath) implements Task
    {
        public function __construct(private readonly string $signalPath) {}

        public function name(): string
        {
            return 'deadcode.analyze_project';
        }

        public function payload(): array
        {
            return [
                'projectPath' => 'C:/repo',
                'signalPath' => $this->signalPath,
            ];
        }
    };

    $run = function () use ($signalPath, $task, $transport): array {
        @unlink($signalPath);

        $frames = [];
        $result = $transport->run($task, function (array $frame) use (&$frames, $signalPath): void {
            $frames[] = $frame;

            if (($frame['type'] ?? null) === 'task.started') {
                file_put_contents($signalPath, 'seen');
            }
        });

        return [$frames, $result];
    };

    [$firstFrames, $firstResult] = $run();
    [$secondFrames, $secondResult] = $run();

    expect($firstFrames)->toHaveCount(2);
    expect($secondFrames)->toHaveCount(2);
    expect($firstFrames[0]['type'])->toBe('task.started');
    expect($firstFrames[1]['type'])->toBe('task.completed');
    expect($firstFrames[0]['taskId'])->toBe($firstFrames[1]['taskId']);
    expect($secondFrames[0]['taskId'])->toBe($secondFrames[1]['taskId']);
    expect($firstResult['data']['taskId'])->toBe($firstFrames[0]['taskId']);
    expect($secondResult['data']['taskId'])->toBe($secondFrames[0]['taskId']);
    expect($firstFrames[0]['taskId'])->not->toBe('task-1');
    expect($secondFrames[0]['taskId'])->not->toBe('task-1');
    expect($firstFrames[0]['taskId'])->not->toBe($secondFrames[0]['taskId']);
});
