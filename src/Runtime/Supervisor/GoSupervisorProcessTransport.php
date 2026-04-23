<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Supervisor;

use Deadcode\Runtime\Contracts\Task;
use Deadcode\Runtime\Protocol\FrameCodec;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final readonly class GoSupervisorProcessTransport implements SupervisorTransport
{
    public function __construct(
        private string $binary,
        private int $timeout,
    ) {}

    public function run(Task $task, callable $onFrame): array
    {
        $taskId = bin2hex(random_bytes(16));
        $process = new Process([$this->binary], timeout: $this->timeout);

        $process->setInput(FrameCodec::encode([
            'type' => 'task.run',
            'taskId' => $taskId,
            'name' => $task->name(),
            'payload' => $task->payload(),
        ]));

        $result = null;
        $stdoutBuffer = '';

        $process->start();

        foreach ($process as $type => $output) {
            if ($type !== Process::OUT) {
                continue;
            }

            $stdoutBuffer .= $output;
            $this->drainFrames($stdoutBuffer, $onFrame, $result);
        }

        $this->drainFrames($stdoutBuffer, $onFrame, $result, flush: true);

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        if (! is_array($result)) {
            throw new RuntimeException('Supervisor did not return a completed task result.');
        }

        return $result;
    }

    private function drainFrames(string &$stdoutBuffer, callable $onFrame, mixed &$result, bool $flush = false): void
    {
        while (($newlineOffset = strpos($stdoutBuffer, "\n")) !== false) {
            $line = substr($stdoutBuffer, 0, $newlineOffset);
            $stdoutBuffer = substr($stdoutBuffer, $newlineOffset + 1);

            $this->handleFrameLine($line, $onFrame, $result);
        }

        if ($flush && $stdoutBuffer !== '') {
            $line = $stdoutBuffer;
            $stdoutBuffer = '';

            $this->handleFrameLine($line, $onFrame, $result);
        }
    }

    private function handleFrameLine(string $line, callable $onFrame, mixed &$result): void
    {
        $line = trim($line);

        if ($line === '') {
            return;
        }

        $frame = FrameCodec::decode($line);
        $onFrame($frame);

        if (($frame['type'] ?? null) === 'task.completed') {
            $result = $frame['result'] ?? null;
        }
    }
}
