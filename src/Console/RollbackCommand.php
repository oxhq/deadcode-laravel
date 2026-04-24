<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use Oxhq\Oxcribe\Apply\RollbackStore;

final class RollbackCommand extends Command
{
    use WritesJsonOutput;

    protected $signature = 'deadcode:rollback {--write=} {--pretty}';

    protected $description = 'Restore the latest locally staged deadcode remediation change set';

    public function handle(RollbackStore $rollbackStore): int
    {
        $payload = $rollbackStore->latest();

        if ($payload === null) {
            return $this->writeJsonPayload(
                [
                    'contractVersion' => 'deadcode.rollback.v1',
                    'status' => 'noop',
                    'changesRolledBack' => 0,
                    'message' => 'No locally staged deadcode change set is available to roll back.',
                ],
                (string) $this->option('write'),
                (bool) $this->option('pretty'),
            );
        }

        try {
            $restoredFiles = [];
            foreach ((array) ($payload['changes'] ?? []) as $change) {
                $file = (string) ($change['file'] ?? '');
                $original = (string) ($change['original'] ?? '');

                if ($file === '') {
                    continue;
                }

                $absolutePath = base_path($file);
                $directory = dirname($absolutePath);
                if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
                    throw new \RuntimeException(sprintf('Unable to create restore directory [%s].', $directory));
                }

                if (is_dir($absolutePath)) {
                    throw new \RuntimeException(sprintf('Unable to restore [%s]: target path is a directory.', $file));
                }

                if (@file_put_contents($absolutePath, $original) === false) {
                    throw new \RuntimeException(sprintf('Unable to restore [%s].', $file));
                }

                $restoredFiles[$file] = true;
            }

            $rollbackStore->deleteLatest();
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->writeJsonPayload(
            [
                'contractVersion' => 'deadcode.rollback.v1',
                'status' => 'rolled_back',
                'changesRolledBack' => count((array) ($payload['changes'] ?? [])),
                'filesRestored' => array_keys($restoredFiles),
            ],
            (string) $this->option('write'),
            (bool) $this->option('pretty'),
        );
    }
}
