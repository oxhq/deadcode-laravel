<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Oxhq\Oxcribe\OxcribeServiceProvider;

it('registers the package service provider in the test application', function () {
    if (! class_exists(OxcribeServiceProvider::class)) {
        $this->markTestSkipped('OxcribeServiceProvider has not been created yet.');
    }

    expect(app()->getProvider(OxcribeServiceProvider::class))
        ->toBeInstanceOf(OxcribeServiceProvider::class);
});

it('registers the local deadcode command surface', function () {
    $commands = array_keys(Artisan::all());

    expect($commands)
        ->toContain(
            'deadcode:doctor',
            'deadcode:install-binary',
            'deadcode:analyze',
            'deadcode:report',
            'deadcode:apply',
            'deadcode:rollback',
        )
        ->not->toContain(
            'oxcribe:doctor',
            'oxcribe:install-binary',
            'oxcribe:analyze',
            'oxcribe:export-openapi',
            'oxcribe:publish',
        );
});

it('keeps the deadcode command surface honest about unsupported project switching', function () {
    $analyze = Artisan::all()['deadcode:analyze'];
    $report = Artisan::all()['deadcode:report'];

    expect($analyze->getDefinition()->hasOption('project-root'))->toBeFalse()
        ->and($report->getDefinition()->hasOption('project-root'))->toBeFalse()
        ->and($analyze->getDefinition()->hasArgument('projectPath'))->toBeTrue()
        ->and($analyze->getDefinition()->hasOption('write'))->toBeFalse()
        ->and($analyze->getDefinition()->hasOption('pretty'))->toBeFalse()
        ->and($report->getDefinition()->hasOption('write'))->toBeTrue()
        ->and($report->getDefinition()->hasOption('projectPath'))->toBeFalse();
});

it('does not register obsolete visibility middleware aliases', function () {
    $aliases = app('router')->getMiddleware();

    expect(config('oxcribe.visibility'))->toBeNull()
        ->and($aliases)->not->toHaveKeys([
            'oxcribe.publish',
            'ox.publish',
            'oxcribe.private',
            'ox.private',
        ]);
});
