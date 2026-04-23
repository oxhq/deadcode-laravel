<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Console;

use Illuminate\Console\Command;
use JsonException;
use Oxhq\Oxcribe\Bridge\DeadCodeAnalysisRequestFactory;
use Oxhq\Oxcribe\Bridge\ProcessDeadCodeClient;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;

final class AnalyzeCommand extends Command
{
    protected $signature = 'deadcode:analyze {--write=} {--pretty}';

    protected $description = 'Capture the Laravel runtime graph and enrich it with deadcore analysis';

    public function handle(
        RuntimeSnapshotFactory $runtimeSnapshotFactory,
        DeadCodeAnalysisRequestFactory $analysisRequestFactory,
        ProcessDeadCodeClient $deadCodeClient,
    ): int
    {
        $runtime = $runtimeSnapshotFactory->make();
        $request = $analysisRequestFactory->make($runtime);
        $response = $deadCodeClient->analyze($request);

        try {
            $json = json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | ($this->option('pretty') ? JSON_PRETTY_PRINT : 0));
        } catch (JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return $this->writeOrOutput($json);
    }

    private function writeOrOutput(string $json): int
    {
        $target = $this->option('write');

        if (is_string($target) && $target !== '') {
            file_put_contents($target, $json.PHP_EOL);
            $this->info(sprintf('AnalysisResponse written to %s', $target));

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
