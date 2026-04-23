<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Supervisor;

use Deadcode\Runtime\Contracts\Task;
use Deadcode\Runtime\Protocol\FrameCodec;
use RuntimeException;
use Symfony\Component\Process\Process;

final readonly class GoSupervisorProcessTransport implements SupervisorTransport
{
    public function __construct(
        private string $binary,
        private int $timeout,
    ) {}

    public function run(Task $task, callable $onFrame): array
    {
        $process = new Process([$this->binary], timeout: $this->timeout);

        $process->setInput(FrameCodec::encode([
            'type' => 'task.run',
            'taskId' => 'task-1',
            'name' => $task->name(),
            'payload' => $task->payload(),
        ]));

        $process->mustRun();

        $result = null;
        $output = trim($process->getOutput());

        foreach (preg_split("/\r\n|\n|\r/", $output) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            $frame = FrameCodec::decode($line);
            $onFrame($frame);

            if (($frame['type'] ?? null) === 'task.completed') {
                $result = $frame['result'] ?? null;
            }
        }

        if (! is_array($result)) {
            throw new RuntimeException('Supervisor did not return a completed task result.');
        }

        return $result;
    }
}
