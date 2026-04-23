<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use Oxhq\Oxcribe\Bridge\DeadCodeAnalysisRequestFactory;
use Oxhq\Oxcribe\Bridge\ProcessDeadCodeClient;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\DeadCodeAnalysisResponse;
use Oxhq\Oxcribe\Data\Diagnostic;
use Oxhq\Oxcribe\Data\RouteMatch;

final class ReportCommand extends Command
{
    use WritesJsonOutput;

    protected $signature = 'deadcode:report {--write=} {--pretty}';

    protected $description = 'Produce a local dead code report from the current Laravel runtime and deadcore analysis';

    public function handle(
        RuntimeSnapshotFactory $runtimeSnapshotFactory,
        DeadCodeAnalysisRequestFactory $analysisRequestFactory,
        ProcessDeadCodeClient $deadCodeClient,
    ): int {
        $runtime = $runtimeSnapshotFactory->make();
        $request = $analysisRequestFactory->make($runtime);
        $response = $deadCodeClient->analyze($request);

        return $this->writeJsonPayload(
            $this->reportPayload($runtime->app->basePath, $response),
            (string) $this->option('write'),
            (bool) $this->option('pretty'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(string $projectRoot, DeadCodeAnalysisResponse $response): array
    {
        return [
            'contractVersion' => 'deadcode.report.v1',
            'projectRoot' => $projectRoot,
            'requestId' => $response->requestId,
            'runtimeFingerprint' => $response->runtimeFingerprint,
            'status' => $response->status,
            'summary' => [
                'routesInspected' => count($response->routeMatches),
                'diagnosticCount' => count($response->diagnostics),
                'partial' => (bool) ($response->meta['partial'] ?? false),
            ],
            'diagnostics' => array_map(
                static fn (Diagnostic $diagnostic): array => [
                    'code' => $diagnostic->code,
                    'severity' => $diagnostic->severity,
                    'scope' => $diagnostic->scope,
                    'message' => $diagnostic->message,
                    'routeId' => $diagnostic->routeId,
                    'actionKey' => $diagnostic->actionKey,
                    'file' => $diagnostic->file,
                    'line' => $diagnostic->line,
                ],
                $response->diagnostics,
            ),
            'routeMatches' => array_map(
                static fn (RouteMatch $routeMatch): array => [
                    'routeId' => $routeMatch->routeId,
                    'actionKind' => $routeMatch->actionKind,
                    'matchStatus' => $routeMatch->matchStatus,
                    'actionKey' => $routeMatch->actionKey,
                    'reasonCode' => $routeMatch->reasonCode,
                ],
                $response->routeMatches,
            ),
        ];
    }
}
