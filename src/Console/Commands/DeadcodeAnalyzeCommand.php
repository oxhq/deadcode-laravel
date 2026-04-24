<?php

declare(strict_types=1);

namespace Deadcode\Console\Commands;

use Deadcode\Runtime\Runtime;
use Deadcode\Tasks\AnalyzeProjectTask;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class DeadcodeAnalyzeCommand extends Command
{
    protected $signature = 'deadcode:analyze {projectPath?}';

    protected $description = 'Analyze a Laravel project for dead code candidates.';

    public function handle(Runtime $runtime): int
    {
        try {
            $result = $runtime->run(
                new AnalyzeProjectTask($this->argument('projectPath') ?? base_path()),
                function (array $frame): void {
                    if (($frame['type'] ?? null) === 'task.progress') {
                        $this->line((string) $frame['message']);
                    }
                },
            );

            $findingCount = $this->requireIntResultValue($result->data, 'findingCount');
            $reportPath = $this->requireStringResultValue($result->data, 'reportPath');

            $this->components->info('Findings: '.$findingCount);
            $this->components->info('Report: '.$reportPath);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireIntResultValue(array $data, string $key): int
    {
        if (! array_key_exists($key, $data)) {
            throw new RuntimeException(sprintf('Runtime result missing required key [%s].', $key));
        }

        $value = $data[$key];

        if (! is_int($value)) {
            throw new RuntimeException(sprintf('Runtime result key [%s] must be of type [int].', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requireStringResultValue(array $data, string $key): string
    {
        if (! array_key_exists($key, $data)) {
            throw new RuntimeException(sprintf('Runtime result missing required key [%s].', $key));
        }

        $value = $data[$key];

        if (! is_string($value)) {
            throw new RuntimeException(sprintf('Runtime result key [%s] must be of type [string].', $key));
        }

        return $value;
    }
}
