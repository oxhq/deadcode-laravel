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
        /** @var list<DeadCodeRemovalChangeSet> $changes */
        $changes = $this->planWithDecisions($response)['changes'];

        return $changes;
    }

    /**
     * @return array{changes:list<DeadCodeRemovalChangeSet>, skippedFindings:list<array<string, mixed>>}
     */
    public function planWithDecisions(DeadCodeAnalysisResponse $response): array
    {
        $eligibleFindings = [];
        $skippedFindings = [];

        foreach ($response->findings as $finding) {
            $decision = $this->decisionForFinding($finding, $response->removalPlan->changeSets);
            if ($decision['eligible']) {
                $eligibleFindings[$this->findingKey($finding)] = true;

                continue;
            }

            $skippedFindings[] = [
                'symbol' => $finding->symbol,
                'category' => $finding->category,
                'decision' => $decision['code'],
                'reasonSummary' => $decision['summary'],
            ];
        }

        return [
            'changes' => array_values(array_filter(
                $response->removalPlan->changeSets,
                fn (DeadCodeRemovalChangeSet $changeSet): bool => isset($eligibleFindings[$this->changeSetKey($changeSet)]),
            )),
            'skippedFindings' => $skippedFindings,
        ];
    }

    /**
     * @param  list<DeadCodeRemovalChangeSet>  $changeSets
     */
    private function decisionForFinding(DeadCodeFinding $finding, array $changeSets): array
    {
        if ($this->isReportOnlyCategory($finding->category)) {
            return [
                'eligible' => false,
                'code' => 'report_only_category',
                'summary' => sprintf('Category [%s] is currently report-only and will not be staged.', $finding->category),
            ];
        }

        if ($finding->confidence !== 'high') {
            return [
                'eligible' => false,
                'code' => 'insufficient_confidence',
                'summary' => sprintf('Finding confidence [%s] is below the current staging threshold [high].', $finding->confidence),
            ];
        }

        if ($finding->startLine === null || $finding->endLine === null) {
            return [
                'eligible' => false,
                'code' => 'missing_range',
                'summary' => 'Finding does not carry a complete source range, so the planner cannot stage it safely.',
            ];
        }

        if (in_array($finding->category, [
            'unused_controller_method',
            'unused_form_request',
            'unused_resource_class',
            'unused_controller_class',
        ], true)) {
            return [
                'eligible' => true,
                'code' => 'eligible',
                'summary' => 'Finding is eligible for conservative staging under the current policy.',
            ];
        }

        if (! in_array($finding->category, [
            'unused_command_class',
            'unused_job_class',
            'unused_listener_class',
            'unused_subscriber_class',
        ], true)) {
            return [
                'eligible' => false,
                'code' => 'report_only_category',
                'summary' => sprintf('Category [%s] is not enabled for staging under the current policy.', $finding->category),
            ];
        }

        return $this->classRemovalDecision($finding, $changeSets);
    }

    /**
     * @param  list<DeadCodeRemovalChangeSet>  $changeSets
     */
    private function classRemovalDecision(DeadCodeFinding $finding, array $changeSets): array
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

        if ($matchingChangeSetCount === 1 && $sameFileChangeSetCount === 1) {
            return [
                'eligible' => true,
                'code' => 'eligible',
                'summary' => 'Finding has one explicit isolated class-removal plan and is eligible for conservative staging.',
            ];
        }

        if ($matchingChangeSetCount === 0) {
            return [
                'eligible' => false,
                'code' => 'missing_removal_plan',
                'summary' => 'No explicit removal plan matches this finding, so the planner will not stage it.',
            ];
        }

        return [
            'eligible' => false,
            'code' => 'non_isolated_removal_plan',
            'summary' => 'The removal plan is not isolated to this finding, so the planner will not stage it.',
        ];
    }

    private function isReportOnlyCategory(string $category): bool
    {
        return in_array($category, [
            'unused_policy_class',
            'unused_model_method',
            'unused_model_scope',
            'unused_model_relationship',
            'unused_model_accessor',
            'unused_model_mutator',
        ], true);
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
