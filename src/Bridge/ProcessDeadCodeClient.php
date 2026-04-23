<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Bridge;

use Oxhq\Oxcribe\Data\DeadCodeAnalysisRequest;
use Oxhq\Oxcribe\Data\DeadCodeAnalysisResponse;
use Oxhq\Oxcribe\Support\DeadcoreBinaryResolver;
use RuntimeException;
use Symfony\Component\Process\Process;

final class ProcessDeadCodeClient
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function analyze(DeadCodeAnalysisRequest $request): DeadCodeAnalysisResponse
    {
        $workingDirectory = (string) ($this->config['working_directory'] ?? $request->runtime->app->basePath);
        $timeout = (float) ($this->config['timeout'] ?? 120);
        $binary = (new DeadcoreBinaryResolver)->resolve($this->config, $workingDirectory);

        $process = new Process([$binary, '--request', '-'], $workingDirectory, null, null, $timeout);
        $process->setInput($request->toWireJson());
        $process->run();

        if (! $process->isSuccessful()) {
            $stderr = trim($process->getErrorOutput());

            throw new RuntimeException(
                sprintf(
                    'deadcore failed with exit code %d%s',
                    $process->getExitCode() ?? 1,
                    $stderr !== '' ? ': '.$stderr : '',
                ),
            );
        }

        return DeadCodeAnalysisResponse::fromJson($process->getOutput());
    }
}
