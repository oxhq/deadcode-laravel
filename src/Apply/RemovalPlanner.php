<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Apply;

use Oxhq\Oxcribe\Data\DeadCodeAnalysisResponse;
use Oxhq\Oxcribe\Data\DeadCodeFinding;
use Oxhq\Oxcribe\Data\DeadCodeRemovalChangeSet;

final class RemovalPlanner
{
    /**
     * @return list<DeadCodeRemovalChangeSet>
     */
    public function plan(DeadCodeAnalysisResponse $response): array
    {
        $eligibleFindings = [];

        foreach ($response->findings as $finding) {
            if (! $this->isEligibleFinding($finding)) {
                continue;
            }

            $eligibleFindings[$this->findingKey($finding)] = true;
        }

        return array_values(array_filter(
            $response->removalPlan->changeSets,
            fn (DeadCodeRemovalChangeSet $changeSet): bool => isset($eligibleFindings[$this->changeSetKey($changeSet)]),
        ));
    }

    private function isEligibleFinding(DeadCodeFinding $finding): bool
    {
        return $finding->category === 'unused_controller_method'
            && $finding->confidence === 'high'
            && $finding->startLine !== null
            && $finding->endLine !== null;
    }

    private function findingKey(DeadCodeFinding $finding): string
    {
        return implode('|', [
            $finding->file,
            $finding->symbol,
            (string) $finding->startLine,
            (string) $finding->endLine,
        ]);
    }

    private function changeSetKey(DeadCodeRemovalChangeSet $changeSet): string
    {
        return implode('|', [
            $changeSet->file,
            $changeSet->symbol,
            (string) $changeSet->startLine,
            (string) $changeSet->endLine,
        ]);
    }
}
