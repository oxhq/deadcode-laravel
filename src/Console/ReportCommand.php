<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use Oxhq\Oxcribe\Bridge\DeadCodeAnalysisRequestFactory;
use Oxhq\Oxcribe\Bridge\ProcessDeadCodeClient;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\DeadCodeAnalysisResponse;
use Oxhq\Oxcribe\Data\DeadCodeEntrypoint;
use Oxhq\Oxcribe\Data\DeadCodeFinding;
use Oxhq\Oxcribe\Data\DeadCodeRemovalChangeSet;
use Oxhq\Oxcribe\Data\DeadCodeSymbol;

final class ReportCommand extends Command
{
    use WritesJsonOutput;

    protected $signature = 'deadcode:report {--input= : Existing deadcode.analysis.v1 payload to render} {--format=json : Output format [json|table]} {--write=} {--pretty}';

    protected $description = 'Produce a local dead code report from the current Laravel runtime and deadcore analysis';

    public function handle(
        RuntimeSnapshotFactory $runtimeSnapshotFactory,
        DeadCodeAnalysisRequestFactory $analysisRequestFactory,
        ProcessDeadCodeClient $deadCodeClient,
    ): int {
        $input = trim((string) $this->option('input'));
        $format = strtolower(trim((string) $this->option('format')));

        if (! in_array($format, ['json', 'table'], true)) {
            $this->error(sprintf('Unsupported report format [%s]. Expected [json] or [table].', $format));

            return self::FAILURE;
        }

        if ($input !== '') {
            $response = $this->loadResponseFromInput($input);
            $projectRoot = app()->basePath();
        } else {
            $runtime = $runtimeSnapshotFactory->make();
            $request = $analysisRequestFactory->make($runtime);
            $response = $deadCodeClient->analyze($request);
            $projectRoot = $runtime->app->basePath;
        }

        $payload = $this->reportPayload($projectRoot, $response);

        if ($format === 'table') {
            return $this->writeTablePayload($payload, (string) $this->option('write'));
        }

        return $this->writeJsonPayload(
            $payload,
            (string) $this->option('write'),
            (bool) $this->option('pretty'),
        );
    }

    private function loadResponseFromInput(string $input): DeadCodeAnalysisResponse
    {
        if (! is_file($input)) {
            throw new \InvalidArgumentException(sprintf('Deadcode analysis input file [%s] does not exist.', $input));
        }

        return DeadCodeAnalysisResponse::fromJson((string) file_get_contents($input));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeTablePayload(array $payload, string $target = ''): int
    {
        $table = $this->renderTablePayload($payload);

        if ($target !== '') {
            file_put_contents($target, $table.PHP_EOL);
            $this->info(sprintf('Payload written to %s', $target));

            return self::SUCCESS;
        }

        $this->output->writeln($table);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderTablePayload(array $payload): string
    {
        /** @var array<string, int> $summary */
        $summary = $payload['summary'];
        /** @var list<array<string, mixed>> $findings */
        $findings = $payload['findings'];

        $rows = [
            ['Symbol', 'Category', 'Confidence', 'File', 'Lines'],
        ];

        foreach ($findings as $finding) {
            $rows[] = [
                (string) ($finding['symbol'] ?? ''),
                (string) ($finding['category'] ?? ''),
                (string) ($finding['confidence'] ?? ''),
                (string) ($finding['file'] ?? ''),
                sprintf('%s-%s', $finding['startLine'] ?? '?', $finding['endLine'] ?? '?'),
            ];
        }

        $widths = array_fill(0, count($rows[0]), 0);
        foreach ($rows as $row) {
            foreach ($row as $index => $column) {
                $widths[$index] = max($widths[$index], strlen($column));
            }
        }

        $lines = [
            'Dead Code Report',
            sprintf('Project Root: %s', (string) ($payload['projectRoot'] ?? '')),
            sprintf('Request ID: %s', (string) ($payload['requestId'] ?? '')),
            sprintf(
                'Summary: %d findings, %d removable change sets, %d reachable symbols, %d unreachable symbols',
                $summary['findingCount'] ?? 0,
                $summary['removalChangeCount'] ?? 0,
                $summary['reachableSymbolCount'] ?? 0,
                $summary['unreachableSymbolCount'] ?? 0,
            ),
            '',
        ];

        foreach ($rows as $index => $row) {
            $formatted = [];

            foreach ($row as $columnIndex => $column) {
                $formatted[] = str_pad($column, $widths[$columnIndex]);
            }

            $lines[] = implode(' | ', $formatted);

            if ($index === 0) {
                $lines[] = implode('-+-', array_map(
                    static fn (int $width): string => str_repeat('-', $width),
                    $widths,
                ));
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function reportPayload(string $projectRoot, DeadCodeAnalysisResponse $response): array
    {
        $reachableSymbolCount = count(array_filter(
            $response->symbols,
            static fn (DeadCodeSymbol $symbol): bool => $symbol->reachableFromRuntime,
        ));

        return [
            'contractVersion' => 'deadcode.report.v1',
            'projectRoot' => $projectRoot,
            'requestId' => $response->requestId,
            'status' => $response->status,
            'meta' => [
                'durationMs' => $response->meta->durationMs,
                'cacheHits' => $response->meta->cacheHits,
                'cacheMisses' => $response->meta->cacheMisses,
            ],
            'summary' => [
                'entrypointCount' => count($response->entrypoints),
                'symbolCount' => count($response->symbols),
                'reachableSymbolCount' => $reachableSymbolCount,
                'unreachableSymbolCount' => count($response->symbols) - $reachableSymbolCount,
                'findingCount' => count($response->findings),
                'removalChangeCount' => count($response->removalPlan->changeSets),
            ],
            'entrypoints' => array_map(
                static fn (DeadCodeEntrypoint $entrypoint): array => [
                    'kind' => $entrypoint->kind,
                    'symbol' => $entrypoint->symbol,
                    'source' => $entrypoint->source,
                ],
                $response->entrypoints,
            ),
            'symbols' => array_map(
                static fn (DeadCodeSymbol $symbol): array => array_filter([
                    'kind' => $symbol->kind,
                    'symbol' => $symbol->symbol,
                    'file' => $symbol->file,
                    'reachableFromRuntime' => $symbol->reachableFromRuntime,
                    'startLine' => $symbol->startLine,
                    'endLine' => $symbol->endLine,
                ], static fn (mixed $value): bool => $value !== null),
                $response->symbols,
            ),
            'findings' => array_map(
                static fn (DeadCodeFinding $finding): array => array_filter([
                    'symbol' => $finding->symbol,
                    'category' => $finding->category,
                    'confidence' => $finding->confidence,
                    'file' => $finding->file,
                    'startLine' => $finding->startLine,
                    'endLine' => $finding->endLine,
                ], static fn (mixed $value): bool => $value !== null),
                $response->findings,
            ),
            'removalPlan' => [
                'changeSets' => array_map(
                    static fn (DeadCodeRemovalChangeSet $changeSet): array => [
                        'file' => $changeSet->file,
                        'symbol' => $changeSet->symbol,
                        'startLine' => $changeSet->startLine,
                        'endLine' => $changeSet->endLine,
                    ],
                    $response->removalPlan->changeSets,
                ),
            ],
        ];
    }
}
