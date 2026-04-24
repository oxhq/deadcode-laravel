<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use Oxhq\Oxcribe\Apply\RemovalPlanner;
use Oxhq\Oxcribe\Apply\RollbackStore;
use Oxhq\Oxcribe\Data\DeadCodeAnalysisResponse;
use Oxhq\Oxcribe\Data\DeadCodeRemovalChangeSet;

final class ApplyCommand extends Command
{
    use WritesJsonOutput;

    protected $signature = 'deadcode:apply {--input= : Raw deadcode.analysis.v1 file to remediate} {--dry-run : Emit planned changes without editing files} {--stage : Apply planned changes locally and persist rollback metadata} {--write=} {--pretty}';

    protected $description = 'Plan and conservatively apply local dead code remediation for removable controller methods';

    public function handle(RemovalPlanner $planner, RollbackStore $rollbackStore): int
    {
        $input = trim((string) $this->option('input'));
        $dryRun = (bool) $this->option('dry-run');
        $stage = (bool) $this->option('stage');

        if ($input === '') {
            $this->error('The --input option is required for local deadcode apply.');

            return self::FAILURE;
        }

        if ($dryRun && $stage) {
            $this->error('Choose either --dry-run or --stage, not both.');

            return self::FAILURE;
        }

        $response = $this->loadResponseFromInput($input);
        $planning = $planner->planWithDecisions($response);
        $plannedChanges = $planning['changes'];
        $payload = [
            'contractVersion' => 'deadcode.apply.v1',
            'input' => $input,
            'status' => $stage ? 'staged' : 'dry_run',
            'changesApplied' => 0,
            'plannedChanges' => count($plannedChanges),
            'changes' => array_map(
                static fn (DeadCodeRemovalChangeSet $changeSet): array => [
                    'file' => $changeSet->file,
                    'symbol' => $changeSet->symbol,
                    'startLine' => $changeSet->startLine,
                    'endLine' => $changeSet->endLine,
                ],
                $plannedChanges,
            ),
        ];

        if (! $stage) {
            $payload['skippedFindingCount'] = count($planning['skippedFindings']);
            $payload['skippedFindings'] = $planning['skippedFindings'];
        }

        if (! $stage) {
            return $this->writeJsonPayload(
                $payload,
                (string) $this->option('write'),
                (bool) $this->option('pretty'),
            );
        }

        try {
            $rollbackChanges = $this->collectRollbackChanges($plannedChanges);
            $rollbackPath = $rollbackStore->store($rollbackChanges);
            $this->applyChanges($plannedChanges);
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $payload['changesApplied'] = count($plannedChanges);
        $payload['rollbackFile'] = $rollbackPath;

        return $this->writeJsonPayload($payload, (string) $this->option('write'), (bool) $this->option('pretty'));
    }

    private function loadResponseFromInput(string $input): DeadCodeAnalysisResponse
    {
        if (! is_file($input)) {
            throw new \InvalidArgumentException(sprintf('Deadcode analysis input file [%s] does not exist.', $input));
        }

        return DeadCodeAnalysisResponse::fromJson((string) file_get_contents($input));
    }

    /**
     * @param  list<DeadCodeRemovalChangeSet>  $plannedChanges
     * @return list<array{file:string,symbol:string,original:string}>
     */
    private function collectRollbackChanges(array $plannedChanges): array
    {
        $groupedChanges = [];
        foreach ($plannedChanges as $changeSet) {
            $groupedChanges[$changeSet->file][] = $changeSet;
        }

        $rollbackChanges = [];
        foreach ($groupedChanges as $relativePath => $changeSets) {
            $absolutePath = base_path($relativePath);
            if (! is_file($absolutePath)) {
                throw new \RuntimeException(sprintf('Planned deadcode change target [%s] does not exist.', $absolutePath));
            }

            $originalContents = @file_get_contents($absolutePath);
            if ($originalContents === false) {
                throw new \RuntimeException(sprintf('Unable to read [%s] for deadcode apply.', $absolutePath));
            }

            foreach ($changeSets as $changeSet) {
                $rollbackChanges[] = [
                    'file' => $relativePath,
                    'symbol' => $changeSet->symbol,
                    'original' => $originalContents,
                ];
            }
        }

        return $rollbackChanges;
    }

    /**
     * @param  list<DeadCodeRemovalChangeSet>  $plannedChanges
     */
    private function applyChanges(array $plannedChanges): void
    {
        $groupedChanges = [];

        foreach ($plannedChanges as $changeSet) {
            $groupedChanges[$changeSet->file][] = $changeSet;
        }

        foreach ($groupedChanges as $relativePath => $changeSets) {
            $absolutePath = base_path($relativePath);
            if (! is_file($absolutePath)) {
                throw new \RuntimeException(sprintf('Planned deadcode change target [%s] does not exist.', $absolutePath));
            }

            $lines = file($absolutePath);
            if ($lines === false) {
                throw new \RuntimeException(sprintf('Unable to read [%s] for deadcode apply.', $absolutePath));
            }

            usort(
                $changeSets,
                static fn (DeadCodeRemovalChangeSet $left, DeadCodeRemovalChangeSet $right): int => $right->startLine <=> $left->startLine,
            );

            foreach ($changeSets as $changeSet) {
                $length = $changeSet->endLine - $changeSet->startLine + 1;
                if ($changeSet->startLine < 1 || $length < 1 || $changeSet->endLine > count($lines)) {
                    throw new \RuntimeException(sprintf(
                        'Invalid deadcode removal range [%d-%d] for [%s].',
                        $changeSet->startLine,
                        $changeSet->endLine,
                        $relativePath,
                    ));
                }

                array_splice($lines, $changeSet->startLine - 1, $length);
            }

            if (@file_put_contents($absolutePath, implode('', $lines)) === false) {
                throw new \RuntimeException(sprintf('Unable to write staged deadcode changes to [%s].', $absolutePath));
            }
        }
    }
}
