<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Oxhq\Oxcribe\Console\AnalyzeCommand;

it('keeps deadcode analyze owned by the current oxcribe analyze command', function (): void {
    $command = Artisan::all()['deadcode:analyze'];

    expect($command)->toBeInstanceOf(AnalyzeCommand::class)
        ->and($command->getDescription())->toBe('Capture the Laravel runtime graph and enrich it with deadcore analysis')
        ->and($command->getDefinition()->hasArgument('projectPath'))->toBeFalse()
        ->and($command->getDefinition()->hasOption('write'))->toBeTrue()
        ->and($command->getDefinition()->hasOption('pretty'))->toBeTrue();
});
