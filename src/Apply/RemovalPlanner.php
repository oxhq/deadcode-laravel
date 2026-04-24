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
            if (! $this->isEligibleFinding($finding, $response->removalPlan->changeSets)) {
                continue;
            }

            $eligibleFindings[$this->findingKey($finding)] = true;
        }

        return array_values(array_filter(
            $response->removalPlan->changeSets,
            fn (DeadCodeRemovalChangeSet $changeSet): bool => isset($eligibleFindings[$this->changeSetKey($changeSet)]),
        ));
    }

    /**
     * @param  list<DeadCodeRemovalChangeSet>  $changeSets
     */
    private function isEligibleFinding(DeadCodeFinding $finding, array $changeSets): bool
    {
        if ($finding->confidence !== 'high'
            || $finding->startLine === null
            || $finding->endLine === null) {
            return false;
        }

        if (in_array($finding->category, [
            'unused_controller_method',
            'unused_form_request',
            'unused_resource_class',
            'unused_controller_class',
        ], true)) {
            return true;
        }

        if ($finding->category !== 'unused_command_class') {
            return false;
        }

        return $this->hasExplicitIsolatedCommandRemoval($finding, $changeSets);
    }

    /**
     * @param  list<DeadCodeRemovalChangeSet>  $changeSets
     */
    private function hasExplicitIsolatedCommandRemoval(DeadCodeFinding $finding, array $changeSets): bool
    {
        $matchingChangeSetCount = 0;
        $sameFileChangeSetCount = 0;

        foreach ($changeSets as $changeSet) {
            if ($changeSet->file !== $finding->file) {
                continue;
            }

            $sameFileChangeSetCount++;

            if ($this->changeSetKey($changeSet) === $this->findingKey($finding)) {
                $matchingChangeSetCount++;
            }
        }

        return $matchingChangeSetCount === 1 && $sameFileChangeSetCount === 1;
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
