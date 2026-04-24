<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('renders a deadcode report from an input file as json', function () {
    [$projectRoot, $analysisPath] = createDeadcodeRemediationFixture();

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'json',
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.report.v1',
        'projectRoot' => $projectRoot,
        'requestId' => 'req-controller-remediation',
        'status' => 'ok',
        'summary' => [
            'entrypointCount' => 1,
            'symbolCount' => 2,
            'reachableSymbolCount' => 1,
            'unreachableSymbolCount' => 1,
            'findingCount' => 1,
            'removalChangeCount' => 1,
        ],
    ])->and($payload['symbols'][0]['reasonSummary'])->toBe('Reachable through Laravel runtime routing or supported controller call expansion.')
        ->and($payload['symbols'][0]['reachabilityReasons'][0]['code'])->toBe('supported_controller_reachability')
        ->and($payload['findings'][0]['reasonSummary'])->toBe('No runtime route or supported controller call keeps this method alive.')
        ->and($payload['findings'][0]['evidence'][0]['code'])->toBe('no_supported_controller_reachability');

    File::deleteDirectory($projectRoot);
});

it('renders a deadcode report from an input file as a table', function () {
    [$projectRoot, $analysisPath] = createDeadcodeRemediationFixture();

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'table',
    ]))->toBe(0);

    $output = Artisan::output();

    expect($output)->toContain('Dead Code Report')
        ->and($output)->toContain('App\\Http\\Controllers\\UserController::unused')
        ->and($output)->toContain('unused_controller_method')
        ->and($output)->toContain('high')
        ->and($output)->toContain('No runtime route or supported controller call keeps this method alive.')
        ->and($output)->toContain('app/Http/Controllers/UserController.php');

    File::deleteDirectory($projectRoot);
});

it('renders phase 2 http-adjacent categories from an input file', function () {
    [$projectRoot, $analysisPath] = createDeadcodeRemediationFixture(deadcorePhaseTwoHttpAdjacencyPayload());

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'json',
    ]))->toBe(0);

    $jsonPayload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($jsonPayload)->toMatchArray([
        'contractVersion' => 'deadcode.report.v1',
        'projectRoot' => $projectRoot,
        'requestId' => 'req-phase-two-http-adjacency',
        'status' => 'ok',
        'summary' => [
            'entrypointCount' => 1,
            'symbolCount' => 3,
            'reachableSymbolCount' => 0,
            'unreachableSymbolCount' => 3,
            'findingCount' => 3,
            'removalChangeCount' => 0,
        ],
    ])->and($jsonPayload['symbols'])->toHaveCount(3)
        ->and($jsonPayload['symbols'][0]['kind'])->toBe('controller_class')
        ->and($jsonPayload['symbols'][1]['kind'])->toBe('form_request_class')
        ->and($jsonPayload['symbols'][2]['kind'])->toBe('resource_class')
        ->and(array_column($jsonPayload['findings'], 'category'))->toBe([
            'unused_controller_class',
            'unused_form_request',
            'unused_resource_class',
        ]);

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'table',
    ]))->toBe(0);

    $tableOutput = Artisan::output();

    expect($tableOutput)->toContain('App\\Http\\Controllers\\UnusedWebhookController')
        ->and($tableOutput)->toContain('unused_controller_class')
        ->and($tableOutput)->toContain('App\\Http\\Requests\\UnusedOrderRequest')
        ->and($tableOutput)->toContain('unused_form_request')
        ->and($tableOutput)->toContain('App\\Http\\Resources\\UnusedOrderResource')
        ->and($tableOutput)->toContain('unused_resource_class');

    File::deleteDirectory($projectRoot);
});

it('renders policy reachability categories from an input file', function () {
    [$projectRoot, $analysisPath] = createDeadcodeRemediationFixture(deadcorePolicyReachabilityPayload());

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'json',
    ]))->toBe(0);

    $jsonPayload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($jsonPayload)->toMatchArray([
        'contractVersion' => 'deadcode.report.v1',
        'projectRoot' => $projectRoot,
        'requestId' => 'req-policy-reachability',
        'status' => 'ok',
        'summary' => [
            'entrypointCount' => 1,
            'symbolCount' => 2,
            'reachableSymbolCount' => 1,
            'unreachableSymbolCount' => 1,
            'findingCount' => 1,
            'removalChangeCount' => 1,
        ],
    ])->and(array_column($jsonPayload['entrypoints'], 'kind'))->toBe(['runtime_policy'])
        ->and(array_column($jsonPayload['symbols'], 'kind'))->toBe([
            'policy_class',
            'policy_class',
        ])
        ->and(array_column($jsonPayload['findings'], 'category'))->toBe([
            'unused_policy_class',
        ]);

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'table',
    ]))->toBe(0);

    $tableOutput = Artisan::output();

    expect($tableOutput)->toContain('App\\Policies\\UnusedInvoicePolicy')
        ->and($tableOutput)->toContain('unused_policy_class')
        ->and($tableOutput)->toContain('app/Policies/UnusedInvoicePolicy.php');

    File::deleteDirectory($projectRoot);
});

it('renders command reachability categories from an input file', function () {
    [$projectRoot, $analysisPath] = createDeadcodeRemediationFixture(deadcoreCommandReachabilityPayload());

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'json',
    ]))->toBe(0);

    $jsonPayload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($jsonPayload)->toMatchArray([
        'contractVersion' => 'deadcode.report.v1',
        'projectRoot' => $projectRoot,
        'requestId' => 'req-command-reachability',
        'status' => 'ok',
        'summary' => [
            'entrypointCount' => 1,
            'symbolCount' => 2,
            'reachableSymbolCount' => 1,
            'unreachableSymbolCount' => 1,
            'findingCount' => 1,
            'removalChangeCount' => 1,
        ],
    ])->and(array_column($jsonPayload['entrypoints'], 'kind'))->toBe(['runtime_command'])
        ->and(array_column($jsonPayload['symbols'], 'kind'))->toBe([
            'command_class',
            'command_class',
        ])
        ->and(array_column($jsonPayload['findings'], 'category'))->toBe([
            'unused_command_class',
        ]);

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'table',
    ]))->toBe(0);

    $tableOutput = Artisan::output();

    expect($tableOutput)->toContain('App\\Console\\Commands\\UnusedAuditCommand')
        ->and($tableOutput)->toContain('unused_command_class')
        ->and($tableOutput)->toContain('app/Console/Commands/UnusedAuditCommand.php');

    File::deleteDirectory($projectRoot);
});

it('renders listener reachability categories from an input file', function () {
    [$projectRoot, $analysisPath] = createDeadcodeRemediationFixture(deadcoreListenerReachabilityPayload());

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'json',
    ]))->toBe(0);

    $jsonPayload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($jsonPayload)->toMatchArray([
        'contractVersion' => 'deadcode.report.v1',
        'projectRoot' => $projectRoot,
        'requestId' => 'req-listener-reachability',
        'status' => 'ok',
        'summary' => [
            'entrypointCount' => 1,
            'symbolCount' => 2,
            'reachableSymbolCount' => 1,
            'unreachableSymbolCount' => 1,
            'findingCount' => 1,
            'removalChangeCount' => 1,
        ],
    ])->and(array_column($jsonPayload['entrypoints'], 'kind'))->toBe(['runtime_listener'])
        ->and(array_column($jsonPayload['symbols'], 'kind'))->toBe([
            'listener_class',
            'listener_class',
        ])
        ->and(array_column($jsonPayload['findings'], 'category'))->toBe([
            'unused_listener_class',
        ]);

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'table',
    ]))->toBe(0);

    $tableOutput = Artisan::output();

    expect($tableOutput)->toContain('App\\Listeners\\UnusedInventoryListener')
        ->and($tableOutput)->toContain('unused_listener_class')
        ->and($tableOutput)->toContain('app/Listeners/UnusedInventoryListener.php');

    File::deleteDirectory($projectRoot);
});

it('renders subscriber reachability categories from an input file', function () {
    [$projectRoot, $analysisPath] = createDeadcodeRemediationFixture(deadcoreSubscriberReachabilityPayload());

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'json',
    ]))->toBe(0);

    $jsonPayload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($jsonPayload)->toMatchArray([
        'contractVersion' => 'deadcode.report.v1',
        'projectRoot' => $projectRoot,
        'requestId' => 'req-subscriber-reachability',
        'status' => 'ok',
        'summary' => [
            'entrypointCount' => 1,
            'symbolCount' => 2,
            'reachableSymbolCount' => 1,
            'unreachableSymbolCount' => 1,
            'findingCount' => 1,
            'removalChangeCount' => 1,
        ],
    ])->and(array_column($jsonPayload['entrypoints'], 'kind'))->toBe(['runtime_subscriber'])
        ->and(array_column($jsonPayload['symbols'], 'kind'))->toBe([
            'subscriber_class',
            'subscriber_class',
        ])
        ->and(array_column($jsonPayload['findings'], 'category'))->toBe([
            'unused_subscriber_class',
        ]);

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'table',
    ]))->toBe(0);

    $tableOutput = Artisan::output();

    expect($tableOutput)->toContain('App\\Subscribers\\UnusedInventorySubscriber')
        ->and($tableOutput)->toContain('unused_subscriber_class')
        ->and($tableOutput)->toContain('app/Subscribers/UnusedInventorySubscriber.php');

    File::deleteDirectory($projectRoot);
});

it('renders job reachability categories from an input file', function () {
    [$projectRoot, $analysisPath] = createDeadcodeRemediationFixture(deadcoreJobReachabilityPayload());

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'json',
    ]))->toBe(0);

    $jsonPayload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($jsonPayload)->toMatchArray([
        'contractVersion' => 'deadcode.report.v1',
        'projectRoot' => $projectRoot,
        'requestId' => 'req-job-reachability',
        'status' => 'ok',
        'summary' => [
            'entrypointCount' => 1,
            'symbolCount' => 2,
            'reachableSymbolCount' => 1,
            'unreachableSymbolCount' => 1,
            'findingCount' => 1,
            'removalChangeCount' => 1,
        ],
    ])->and(array_column($jsonPayload['entrypoints'], 'kind'))->toBe(['runtime_job'])
        ->and(array_column($jsonPayload['symbols'], 'kind'))->toBe([
            'job_class',
            'job_class',
        ])
        ->and(array_column($jsonPayload['findings'], 'category'))->toBe([
            'unused_job_class',
        ]);

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'table',
    ]))->toBe(0);

    $tableOutput = Artisan::output();

    expect($tableOutput)->toContain('App\\Jobs\\UnusedShipmentReminder')
        ->and($tableOutput)->toContain('unused_job_class')
        ->and($tableOutput)->toContain('app/Jobs/UnusedShipmentReminder.php');

    File::deleteDirectory($projectRoot);
});

it('renders phase 4 model-heavy categories from an input file', function () {
    [$projectRoot, $analysisPath] = createDeadcodeRemediationFixture(deadcorePhaseFourModelPayload());

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'json',
    ]))->toBe(0);

    $jsonPayload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($jsonPayload)->toMatchArray([
        'contractVersion' => 'deadcode.report.v1',
        'projectRoot' => $projectRoot,
        'requestId' => 'req-phase4-models',
        'status' => 'ok',
        'summary' => [
            'entrypointCount' => 1,
            'symbolCount' => 5,
            'reachableSymbolCount' => 0,
            'unreachableSymbolCount' => 5,
            'findingCount' => 5,
            'removalChangeCount' => 5,
        ],
    ])->and(array_column($jsonPayload['symbols'], 'kind'))->toBe([
        'model_method',
        'model_scope',
        'model_relationship',
        'model_accessor',
        'model_mutator',
    ])->and(array_column($jsonPayload['findings'], 'category'))->toBe([
        'unused_model_method',
        'unused_model_scope',
        'unused_model_relationship',
        'unused_model_accessor',
        'unused_model_mutator',
    ])->and($jsonPayload['findings'][0]['reasonSummary'])->toBe('No supported explicit model call from already-reachable code reaches this method.')
        ->and($jsonPayload['findings'][0]['evidence'][0]['code'])->toBe('no_supported_model_call');

    expect(Artisan::call('deadcode:report', [
        '--input' => $analysisPath,
        '--format' => 'table',
    ]))->toBe(0);

    $tableOutput = Artisan::output();

    expect($tableOutput)->toContain('App\\Models\\Invoice::summary')
        ->and($tableOutput)->toContain('unused_model_method')
        ->and($tableOutput)->toContain('No supported explicit model call from already-reachable code reaches this method.')
        ->and($tableOutput)->toContain('App\\Models\\Invoice::published')
        ->and($tableOutput)->toContain('unused_model_scope')
        ->and($tableOutput)->toContain('App\\Models\\Invoice::customer')
        ->and($tableOutput)->toContain('unused_model_relationship')
        ->and($tableOutput)->toContain('App\\Models\\User::display_name')
        ->and($tableOutput)->toContain('unused_model_accessor')
        ->and($tableOutput)->toContain('unused_model_mutator');

    File::deleteDirectory($projectRoot);
});

it('requires an existing analysis input before rendering a deadcode report', function () {
    expect(Artisan::call('deadcode:report', [
        '--format' => 'json',
    ]))->toBe(1);

    expect(Artisan::output())->toContain('Run `php artisan deadcode:analyze` first or pass `--input=` with an existing deadcode.analysis.v1 payload.');
});

it('stages a high-confidence unused controller method removal from an input file', function () {
    [$projectRoot, $analysisPath, $controllerPath, $originalContents] = createDeadcodeRemediationFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);
    $rollbackFiles = glob($projectRoot.'/storage/app/deadcode/rollback/*.json') ?: [];

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'staged',
        'changesApplied' => 1,
    ])->and(file_get_contents($controllerPath))->not->toContain("return 'unused';")
        ->and(file_get_contents($controllerPath))->toContain("return 'index';")
        ->and($rollbackFiles)->not->toBeEmpty();

    $storedRollback = json_decode((string) file_get_contents($rollbackFiles[0]), true, 512, JSON_THROW_ON_ERROR);

    expect($storedRollback)->toMatchArray([
        'contractVersion' => 'deadcode.rollback.v1',
        'changes' => [
            [
                'file' => 'app/Http/Controllers/UserController.php',
                'symbol' => 'App\\Http\\Controllers\\UserController::unused',
                'original' => $originalContents,
            ],
        ],
    ]);

    File::deleteDirectory($projectRoot);
});

it('stages a high-confidence phase 2 class removal from an input file', function (
    string $symbol,
    string $category,
    string $relativePath,
    string $fileContents,
    int $startLine,
    int $endLine,
    string $expectedStagedContents,
) {
    [$projectRoot, $analysisPath, $targetPath] = createDeadcodeClassRemovalFixture(
        symbol: $symbol,
        category: $category,
        relativePath: $relativePath,
        fileContents: $fileContents,
        startLine: $startLine,
        endLine: $endLine,
    );

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'staged',
        'changesApplied' => 1,
        'plannedChanges' => 1,
    ])->and(file_get_contents($targetPath))->toBe($expectedStagedContents)
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeTrue();

    File::deleteDirectory($projectRoot);
})->with('phaseTwoRemovableClasses');

it('rolls back the latest staged phase 2 class removal', function (
    string $symbol,
    string $category,
    string $relativePath,
    string $fileContents,
    int $startLine,
    int $endLine,
) {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeClassRemovalFixture(
        symbol: $symbol,
        category: $category,
        relativePath: $relativePath,
        fileContents: $fileContents,
        startLine: $startLine,
        endLine: $endLine,
    );

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    expect(Artisan::call('deadcode:rollback'))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.rollback.v1',
        'status' => 'rolled_back',
        'changesRolledBack' => 1,
    ])->and(file_get_contents($targetPath))->toBe($originalContents);

    File::deleteDirectory($projectRoot);
})->with('phaseTwoRemovableClasses');

it('stages a high-confidence unused command class removal from an input file', function () {
    [$projectRoot, $analysisPath, $targetPath] = createDeadcodeCommandClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'staged',
        'changesApplied' => 1,
        'plannedChanges' => 1,
    ])->and(file_get_contents($targetPath))->toBe(deadcodeCommandClassStagedContents())
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeTrue();

    File::deleteDirectory($projectRoot);
});

it('plans a high-confidence unused listener class removal in dry run when the removal plan is explicit and isolated', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeListenerClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--dry-run' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'dry_run',
        'changesApplied' => 0,
        'plannedChanges' => 1,
        'changes' => [
            [
                'file' => 'app/Listeners/UnusedInventoryListener.php',
                'symbol' => 'App\\Listeners\\UnusedInventoryListener',
                'startLine' => 9,
                'endLine' => 14,
            ],
        ],
    ])->and(file_get_contents($targetPath))->toBe($originalContents)
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeFalse();

    File::deleteDirectory($projectRoot);
});

it('does not plan unused listener class removal when the removal plan is not isolated', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeListenerClassRemovalFixture(
        changeSets: [
            [
                'file' => 'app/Listeners/UnusedInventoryListener.php',
                'symbol' => 'App\\Listeners\\UnusedInventoryListener',
                'start_line' => 9,
                'end_line' => 14,
            ],
            [
                'file' => 'app/Listeners/UnusedInventoryListener.php',
                'symbol' => 'App\\Listeners\\UnusedInventoryListener::handle',
                'start_line' => 11,
                'end_line' => 13,
            ],
        ],
    );

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--dry-run' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'dry_run',
        'changesApplied' => 0,
        'plannedChanges' => 0,
        'changes' => [],
        'skippedFindingCount' => 1,
        'skippedFindings' => [
            [
                'symbol' => 'App\\Listeners\\UnusedInventoryListener',
                'category' => 'unused_listener_class',
                'decision' => 'non_isolated_removal_plan',
                'reasonSummary' => 'The removal plan is not isolated to this finding, so the planner will not stage it.',
            ],
        ],
    ])->and($payload['skippedFindings'][0]['reasonSummary'])->toContain('not isolated')
        ->and(file_get_contents($targetPath))->toBe($originalContents);

    File::deleteDirectory($projectRoot);
});

it('does not plan unused policy class removal even when the removal plan is explicit', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodePolicyClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--dry-run' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'dry_run',
        'changesApplied' => 0,
        'plannedChanges' => 0,
        'changes' => [],
        'skippedFindingCount' => 1,
        'skippedFindings' => [
            [
                'symbol' => 'App\\Policies\\UnusedInvoicePolicy',
                'category' => 'unused_policy_class',
                'decision' => 'report_only_category',
                'reasonSummary' => 'Category [unused_policy_class] is currently report-only and will not be staged.',
            ],
        ],
    ])->and($payload['skippedFindings'][0]['reasonSummary'])->toContain('report-only')
        ->and(file_get_contents($targetPath))->toBe($originalContents)
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeFalse();

    File::deleteDirectory($projectRoot);
});

it('does not plan phase 4 model-heavy removals even when the removal plan is explicit', function () {
    [$projectRoot, $analysisPath, $controllerPath, $originalContents] = createDeadcodeRemediationFixture(
        deadcorePhaseFourModelPayload(),
    );

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--dry-run' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'dry_run',
        'changesApplied' => 0,
        'plannedChanges' => 0,
        'changes' => [],
        'skippedFindingCount' => 5,
    ])->and($payload['skippedFindings'][0]['decision'])->toBe('report_only_category')
        ->and($payload['skippedFindings'][0]['reasonSummary'])->toContain('report-only')
        ->and(file_get_contents($controllerPath))->toBe($originalContents)
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeFalse();

    File::deleteDirectory($projectRoot);
});

it('stages a high-confidence unused listener class removal from an input file', function () {
    [$projectRoot, $analysisPath, $targetPath] = createDeadcodeListenerClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'staged',
        'changesApplied' => 1,
        'plannedChanges' => 1,
    ])->and(file_get_contents($targetPath))->toBe(deadcodeListenerClassStagedContents())
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeTrue();

    File::deleteDirectory($projectRoot);
});

it('rolls back the latest staged unused listener class removal', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeListenerClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    expect(Artisan::call('deadcode:rollback'))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.rollback.v1',
        'status' => 'rolled_back',
        'changesRolledBack' => 1,
    ])->and(file_get_contents($targetPath))->toBe($originalContents);

    File::deleteDirectory($projectRoot);
});

it('plans a high-confidence unused subscriber class removal in dry run when the removal plan is explicit and isolated', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeSubscriberClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--dry-run' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'dry_run',
        'changesApplied' => 0,
        'plannedChanges' => 1,
        'changes' => [
            [
                'file' => 'app/Subscribers/UnusedInventorySubscriber.php',
                'symbol' => 'App\\Subscribers\\UnusedInventorySubscriber',
                'startLine' => 10,
                'endLine' => 20,
            ],
        ],
    ])->and(file_get_contents($targetPath))->toBe($originalContents)
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeFalse();

    File::deleteDirectory($projectRoot);
});

it('does not plan unused subscriber class removal when the removal plan is not isolated', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeSubscriberClassRemovalFixture(
        changeSets: [
            [
                'file' => 'app/Subscribers/UnusedInventorySubscriber.php',
                'symbol' => 'App\\Subscribers\\UnusedInventorySubscriber',
                'start_line' => 10,
                'end_line' => 20,
            ],
            [
                'file' => 'app/Subscribers/UnusedInventorySubscriber.php',
                'symbol' => 'App\\Subscribers\\UnusedInventorySubscriber::subscribe',
                'start_line' => 12,
                'end_line' => 18,
            ],
        ],
    );

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--dry-run' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'dry_run',
        'changesApplied' => 0,
        'plannedChanges' => 0,
        'changes' => [],
        'skippedFindingCount' => 1,
        'skippedFindings' => [
            [
                'symbol' => 'App\\Subscribers\\UnusedInventorySubscriber',
                'category' => 'unused_subscriber_class',
                'decision' => 'non_isolated_removal_plan',
                'reasonSummary' => 'The removal plan is not isolated to this finding, so the planner will not stage it.',
            ],
        ],
    ])->and($payload['skippedFindings'][0]['reasonSummary'])->toContain('not isolated')
        ->and(file_get_contents($targetPath))->toBe($originalContents);

    File::deleteDirectory($projectRoot);
});

it('stages a high-confidence unused subscriber class removal from an input file', function () {
    [$projectRoot, $analysisPath, $targetPath] = createDeadcodeSubscriberClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'staged',
        'changesApplied' => 1,
        'plannedChanges' => 1,
    ])->and(file_get_contents($targetPath))->toBe(deadcodeSubscriberClassStagedContents())
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeTrue();

    File::deleteDirectory($projectRoot);
});

it('rolls back the latest staged unused subscriber class removal', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeSubscriberClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    expect(Artisan::call('deadcode:rollback'))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.rollback.v1',
        'status' => 'rolled_back',
        'changesRolledBack' => 1,
    ])->and(file_get_contents($targetPath))->toBe($originalContents);

    File::deleteDirectory($projectRoot);
});

it('plans a high-confidence unused job class removal in dry run when the removal plan is explicit and isolated', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeJobClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--dry-run' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'dry_run',
        'changesApplied' => 0,
        'plannedChanges' => 1,
        'changes' => [
            [
                'file' => 'app/Jobs/UnusedShipmentReminder.php',
                'symbol' => 'App\\Jobs\\UnusedShipmentReminder',
                'startLine' => 9,
                'endLine' => 16,
            ],
        ],
    ])->and(file_get_contents($targetPath))->toBe($originalContents)
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeFalse();

    File::deleteDirectory($projectRoot);
});

it('does not plan unused job class removal when the removal plan is not isolated', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeJobClassRemovalFixture(
        changeSets: [
            [
                'file' => 'app/Jobs/UnusedShipmentReminder.php',
                'symbol' => 'App\\Jobs\\UnusedShipmentReminder',
                'start_line' => 9,
                'end_line' => 16,
            ],
            [
                'file' => 'app/Jobs/UnusedShipmentReminder.php',
                'symbol' => 'App\\Jobs\\UnusedShipmentReminder::handle',
                'start_line' => 11,
                'end_line' => 15,
            ],
        ],
    );

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--dry-run' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'dry_run',
        'changesApplied' => 0,
        'plannedChanges' => 0,
        'changes' => [],
    ])->and(file_get_contents($targetPath))->toBe($originalContents);

    File::deleteDirectory($projectRoot);
});

it('stages a high-confidence unused job class removal from an input file', function () {
    [$projectRoot, $analysisPath, $targetPath] = createDeadcodeJobClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.apply.v1',
        'status' => 'staged',
        'changesApplied' => 1,
        'plannedChanges' => 1,
    ])->and(file_get_contents($targetPath))->toBe(deadcodeJobClassStagedContents())
        ->and(is_file($projectRoot.'/storage/app/deadcode/rollback/latest.json'))->toBeTrue();

    File::deleteDirectory($projectRoot);
});

it('rolls back the latest staged unused job class removal', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeJobClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    expect(Artisan::call('deadcode:rollback'))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.rollback.v1',
        'status' => 'rolled_back',
        'changesRolledBack' => 1,
    ])->and(file_get_contents($targetPath))->toBe($originalContents);

    File::deleteDirectory($projectRoot);
});

it('rolls back the latest staged unused command class removal', function () {
    [$projectRoot, $analysisPath, $targetPath, $originalContents] = createDeadcodeCommandClassRemovalFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    expect(Artisan::call('deadcode:rollback'))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.rollback.v1',
        'status' => 'rolled_back',
        'changesRolledBack' => 1,
    ])->and(file_get_contents($targetPath))->toBe($originalContents);

    File::deleteDirectory($projectRoot);
});

it('rolls back the latest staged controller method removal', function () {
    [$projectRoot, $analysisPath, $controllerPath, $originalContents] = createDeadcodeRemediationFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    expect(Artisan::call('deadcode:rollback'))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'contractVersion' => 'deadcode.rollback.v1',
        'status' => 'rolled_back',
        'changesRolledBack' => 1,
    ])->and(file_get_contents($controllerPath))->toBe($originalContents);

    File::deleteDirectory($projectRoot);
});

it('does not stage edits when rollback persistence cannot be written', function () {
    [$projectRoot, $analysisPath, $controllerPath, $originalContents] = createDeadcodeRemediationFixture();
    $rollbackPath = $projectRoot.'/storage/app/deadcode/rollback';

    file_put_contents($rollbackPath, 'blocked');

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(1);

    expect(file_get_contents($controllerPath))->toBe($originalContents)
        ->and(is_file($rollbackPath.'/latest.json'))->toBeFalse()
        ->and(Artisan::output())->toContain('Unable to create rollback directory');

    File::deleteDirectory($projectRoot);
});

it('keeps the rollback payload when restore cannot write the original file', function () {
    [$projectRoot, $analysisPath, $controllerPath] = createDeadcodeRemediationFixture();

    expect(Artisan::call('deadcode:apply', [
        '--input' => $analysisPath,
        '--stage' => true,
    ]))->toBe(0);

    $rollbackPayloadPath = $projectRoot.'/storage/app/deadcode/rollback/latest.json';

    unlink($controllerPath);
    mkdir($controllerPath);

    expect(Artisan::call('deadcode:rollback'))->toBe(1);

    expect(is_file($rollbackPayloadPath))->toBeTrue()
        ->and(is_dir($controllerPath))->toBeTrue()
        ->and(Artisan::output())->toContain('Unable to restore');

    File::deleteDirectory($projectRoot);
});

function createDeadcodeRemediationFixture(?array $analysisPayload = null): array
{
    $projectRoot = sys_get_temp_dir().'/deadcode-task8-'.bin2hex(random_bytes(6));
    $controllerPath = $projectRoot.'/app/Http/Controllers/UserController.php';
    $analysisPath = $projectRoot.'/storage/app/deadcode/analysis.json';

    File::ensureDirectoryExists(dirname($controllerPath));
    File::ensureDirectoryExists(dirname($analysisPath));

    $controllerContents = <<<'PHP'
<?php

namespace App\Http\Controllers;

final class UserController
{
    public function index(): string
    {
        return 'index';
    }

    public function unused(): string
    {
        return 'unused';
    }
}
PHP;

    file_put_contents($controllerPath, $controllerContents);
    file_put_contents($analysisPath, json_encode($analysisPayload ?? deadcodeControllerMethodRemovalPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

    app()->setBasePath($projectRoot);
    app()->useStoragePath($projectRoot.'/storage');

    return [$projectRoot, $analysisPath, $controllerPath, $controllerContents];
}

function deadcodeControllerMethodRemovalPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-controller-remediation',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 11,
            'cache_hits' => 1,
            'cache_misses' => 0,
        ],
        'entrypoints' => [
            [
                'kind' => 'runtime_route',
                'symbol' => 'App\\Http\\Controllers\\UserController::index',
                'source' => 'users.index',
            ],
        ],
        'symbols' => [
            [
                'kind' => 'controller_method',
                'symbol' => 'App\\Http\\Controllers\\UserController::index',
                'file' => 'app/Http/Controllers/UserController.php',
                'reachableFromRuntime' => true,
                'reasonSummary' => 'Reachable through Laravel runtime routing or supported controller call expansion.',
                'reachabilityReasons' => [
                    [
                        'code' => 'supported_controller_reachability',
                        'summary' => 'Laravel runtime routes or supported controller call expansion keep this controller method alive.',
                    ],
                ],
                'startLine' => 6,
                'endLine' => 9,
            ],
            [
                'kind' => 'controller_method',
                'symbol' => 'App\\Http\\Controllers\\UserController::unused',
                'file' => 'app/Http/Controllers/UserController.php',
                'reachableFromRuntime' => false,
                'startLine' => 11,
                'endLine' => 14,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Http\\Controllers\\UserController::unused',
                'category' => 'unused_controller_method',
                'confidence' => 'high',
                'file' => 'app/Http/Controllers/UserController.php',
                'reasonSummary' => 'No runtime route or supported controller call keeps this method alive.',
                'evidence' => [
                    [
                        'code' => 'no_supported_controller_reachability',
                        'summary' => 'No Laravel runtime route or supported controller call expansion reaches this controller method.',
                    ],
                ],
                'startLine' => 11,
                'endLine' => 14,
            ],
        ],
        'removalPlan' => [
            'changeSets' => [
                [
                    'file' => 'app/Http/Controllers/UserController.php',
                    'symbol' => 'App\\Http\\Controllers\\UserController::unused',
                    'start_line' => 11,
                    'end_line' => 14,
                ],
            ],
        ],
    ];
}

dataset('phaseTwoRemovableClasses', [
    'form request class' => [
        'App\\Http\\Requests\\UnusedAuditRequest',
        'unused_form_request',
        'app/Http/Requests/UnusedAuditRequest.php',
        <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UnusedAuditRequest extends FormRequest
{
    public function rules(): array
    {
        return [];
    }
}
PHP,
        9,
        15,
        <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


PHP,
    ],
    'resource class' => [
        'App\\Http\\Resources\\UnusedAuditResource',
        'unused_resource_class',
        'app/Http/Resources/UnusedAuditResource.php',
        <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class UnusedAuditResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['status' => 'unused'];
    }
}
PHP,
        9,
        15,
        <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


PHP,
    ],
    'controller class' => [
        'App\\Http\\Controllers\\UnusedWebhookController',
        'unused_controller_class',
        'app/Http/Controllers/UnusedWebhookController.php',
        <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class UnusedWebhookController
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['status' => 'unused']);
    }
}
PHP,
        9,
        15,
        <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;


PHP,
    ],
]);

function createDeadcodeClassRemovalFixture(
    string $symbol,
    string $category,
    string $relativePath,
    string $fileContents,
    int $startLine,
    int $endLine,
): array {
    $projectRoot = sys_get_temp_dir().'/deadcode-phase2-removal-'.bin2hex(random_bytes(6));
    $targetPath = $projectRoot.'/'.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $analysisPath = $projectRoot.'/storage/app/deadcode/analysis.json';

    File::ensureDirectoryExists(dirname($targetPath));
    File::ensureDirectoryExists(dirname($analysisPath));

    file_put_contents($targetPath, $fileContents);
    file_put_contents(
        $analysisPath,
        json_encode(
            deadcodePhaseTwoClassRemovalPayload(
                symbol: $symbol,
                category: $category,
                relativePath: $relativePath,
                startLine: $startLine,
                endLine: $endLine,
            ),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ),
    );

    app()->setBasePath($projectRoot);
    app()->useStoragePath($projectRoot.'/storage');

    return [$projectRoot, $analysisPath, $targetPath, $fileContents];
}

function deadcodePhaseTwoClassRemovalPayload(
    string $symbol,
    string $category,
    string $relativePath,
    int $startLine,
    int $endLine,
): array {
    $kind = match ($category) {
        'unused_form_request' => 'form_request_class',
        'unused_resource_class' => 'resource_class',
        'unused_controller_class' => 'controller_class',
        default => throw new InvalidArgumentException(sprintf('Unsupported phase 2 removal category [%s].', $category)),
    };

    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-phase2-removal',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 19,
            'cache_hits' => 2,
            'cache_misses' => 1,
        ],
        'entrypoints' => [
            [
                'kind' => 'runtime_route',
                'symbol' => 'App\\Http\\Controllers\\OrdersController::index',
                'source' => 'orders.index',
            ],
        ],
        'symbols' => [
            [
                'kind' => $kind,
                'symbol' => $symbol,
                'file' => $relativePath,
                'reachableFromRuntime' => false,
                'startLine' => $startLine,
                'endLine' => $endLine,
            ],
        ],
        'findings' => [
            [
                'symbol' => $symbol,
                'category' => $category,
                'confidence' => 'high',
                'file' => $relativePath,
                'startLine' => $startLine,
                'endLine' => $endLine,
            ],
        ],
        'removalPlan' => [
            'changeSets' => [
                [
                    'file' => $relativePath,
                    'symbol' => $symbol,
                    'start_line' => $startLine,
                    'end_line' => $endLine,
                ],
            ],
        ],
    ];
}

function createDeadcodeCommandClassRemovalFixture(): array
{
    $fileContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class UnusedAuditCommand extends Command
{
    protected $signature = 'audit:unused';

    protected $description = 'Unused audit command';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
PHP;

    $projectRoot = sys_get_temp_dir().'/deadcode-command-removal-'.bin2hex(random_bytes(6));
    $targetPath = $projectRoot.'/app/Console/Commands/UnusedAuditCommand.php';
    $analysisPath = $projectRoot.'/storage/app/deadcode/analysis.json';

    File::ensureDirectoryExists(dirname($targetPath));
    File::ensureDirectoryExists(dirname($analysisPath));

    file_put_contents($targetPath, $fileContents);
    file_put_contents(
        $analysisPath,
        json_encode(deadcodeCommandClassRemovalPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    );

    app()->setBasePath($projectRoot);
    app()->useStoragePath($projectRoot.'/storage');

    return [$projectRoot, $analysisPath, $targetPath, $fileContents];
}

function deadcodeCommandClassRemovalPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-command-removal',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 17,
            'cache_hits' => 1,
            'cache_misses' => 1,
        ],
        'entrypoints' => [
            [
                'kind' => 'runtime_command',
                'symbol' => 'App\\Console\\Commands\\ReachableMaintenanceCommand',
                'source' => 'maintenance:reachable',
            ],
        ],
        'symbols' => [
            [
                'kind' => 'command_class',
                'symbol' => 'App\\Console\\Commands\\UnusedAuditCommand',
                'file' => 'app/Console/Commands/UnusedAuditCommand.php',
                'reachableFromRuntime' => false,
                'startLine' => 9,
                'endLine' => 19,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Console\\Commands\\UnusedAuditCommand',
                'category' => 'unused_command_class',
                'confidence' => 'high',
                'file' => 'app/Console/Commands/UnusedAuditCommand.php',
                'startLine' => 9,
                'endLine' => 19,
            ],
        ],
        'removalPlan' => [
            'changeSets' => [
                [
                    'file' => 'app/Console/Commands/UnusedAuditCommand.php',
                    'symbol' => 'App\\Console\\Commands\\UnusedAuditCommand',
                    'start_line' => 9,
                    'end_line' => 19,
                ],
            ],
        ],
    ];
}

function deadcodeCommandClassStagedContents(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;


PHP;
}

/**
 * @param  list<array{file:string,symbol:string,start_line:int,end_line:int}>|null  $changeSets
 */
function createDeadcodeListenerClassRemovalFixture(?array $changeSets = null): array
{
    $fileContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderShipped;

final class UnusedInventoryListener
{
    public function handle(OrderShipped $event): void
    {
    }
}
PHP;

    $projectRoot = sys_get_temp_dir().'/deadcode-listener-removal-'.bin2hex(random_bytes(6));
    $targetPath = $projectRoot.'/app/Listeners/UnusedInventoryListener.php';
    $analysisPath = $projectRoot.'/storage/app/deadcode/analysis.json';

    File::ensureDirectoryExists(dirname($targetPath));
    File::ensureDirectoryExists(dirname($analysisPath));

    file_put_contents($targetPath, $fileContents);
    file_put_contents(
        $analysisPath,
        json_encode(
            deadcodeListenerClassRemovalPayload($changeSets),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ),
    );

    app()->setBasePath($projectRoot);
    app()->useStoragePath($projectRoot.'/storage');

    return [$projectRoot, $analysisPath, $targetPath, $fileContents];
}

/**
 * @param  list<array{file:string,symbol:string,start_line:int,end_line:int}>|null  $changeSets
 */
function deadcodeListenerClassRemovalPayload(?array $changeSets = null): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-listener-removal',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 21,
            'cache_hits' => 1,
            'cache_misses' => 1,
        ],
        'entrypoints' => [
            [
                'kind' => 'runtime_listener',
                'symbol' => 'App\\Listeners\\SendReachableShipmentNotification',
                'source' => 'App\\Events\\OrderShipped',
            ],
        ],
        'symbols' => [
            [
                'kind' => 'listener_class',
                'symbol' => 'App\\Listeners\\UnusedInventoryListener',
                'file' => 'app/Listeners/UnusedInventoryListener.php',
                'reachableFromRuntime' => false,
                'startLine' => 9,
                'endLine' => 14,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Listeners\\UnusedInventoryListener',
                'category' => 'unused_listener_class',
                'confidence' => 'high',
                'file' => 'app/Listeners/UnusedInventoryListener.php',
                'startLine' => 9,
                'endLine' => 14,
            ],
        ],
        'removalPlan' => [
            'changeSets' => $changeSets ?? [
                [
                    'file' => 'app/Listeners/UnusedInventoryListener.php',
                    'symbol' => 'App\\Listeners\\UnusedInventoryListener',
                    'start_line' => 9,
                    'end_line' => 14,
                ],
            ],
        ],
    ];
}

function deadcodeListenerClassStagedContents(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderShipped;


PHP;
}

/**
 * @param  list<array{file:string,symbol:string,start_line:int,end_line:int}>|null  $changeSets
 */
function createDeadcodeSubscriberClassRemovalFixture(?array $changeSets = null): array
{
    $fileContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Subscribers;

use App\Events\OrderShipped;
use Illuminate\Events\Dispatcher;

final class UnusedInventorySubscriber
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(OrderShipped::class, self::class.'@handle');
    }

    public function handle(OrderShipped $event): void
    {
    }
}
PHP;

    $projectRoot = sys_get_temp_dir().'/deadcode-subscriber-removal-'.bin2hex(random_bytes(6));
    $targetPath = $projectRoot.'/app/Subscribers/UnusedInventorySubscriber.php';
    $analysisPath = $projectRoot.'/storage/app/deadcode/analysis.json';

    File::ensureDirectoryExists(dirname($targetPath));
    File::ensureDirectoryExists(dirname($analysisPath));

    file_put_contents($targetPath, $fileContents);
    file_put_contents(
        $analysisPath,
        json_encode(
            deadcodeSubscriberClassRemovalPayload($changeSets),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ),
    );

    app()->setBasePath($projectRoot);
    app()->useStoragePath($projectRoot.'/storage');

    return [$projectRoot, $analysisPath, $targetPath, $fileContents];
}

/**
 * @param  list<array{file:string,symbol:string,start_line:int,end_line:int}>|null  $changeSets
 */
function deadcodeSubscriberClassRemovalPayload(?array $changeSets = null): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-subscriber-removal',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 22,
            'cache_hits' => 1,
            'cache_misses' => 1,
        ],
        'entrypoints' => [
            [
                'kind' => 'runtime_subscriber',
                'symbol' => 'App\\Subscribers\\ReachableOrderSubscriber',
                'source' => 'App\\Events\\OrderShipped',
            ],
        ],
        'symbols' => [
            [
                'kind' => 'subscriber_class',
                'symbol' => 'App\\Subscribers\\UnusedInventorySubscriber',
                'file' => 'app/Subscribers/UnusedInventorySubscriber.php',
                'reachableFromRuntime' => false,
                'startLine' => 10,
                'endLine' => 20,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Subscribers\\UnusedInventorySubscriber',
                'category' => 'unused_subscriber_class',
                'confidence' => 'high',
                'file' => 'app/Subscribers/UnusedInventorySubscriber.php',
                'startLine' => 10,
                'endLine' => 20,
            ],
        ],
        'removalPlan' => [
            'changeSets' => $changeSets ?? [
                [
                    'file' => 'app/Subscribers/UnusedInventorySubscriber.php',
                    'symbol' => 'App\\Subscribers\\UnusedInventorySubscriber',
                    'start_line' => 10,
                    'end_line' => 20,
                ],
            ],
        ],
    ];
}

function deadcodeSubscriberClassStagedContents(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Subscribers;

use App\Events\OrderShipped;
use Illuminate\Events\Dispatcher;


PHP;
}

/**
 * @param  list<array{file:string,symbol:string,start_line:int,end_line:int}>|null  $changeSets
 */
function createDeadcodeJobClassRemovalFixture(?array $changeSets = null): array
{
    $fileContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;

final class UnusedShipmentReminder
{
    use Queueable;

    public function handle(): void
    {
    }
}
PHP;

    $projectRoot = sys_get_temp_dir().'/deadcode-job-removal-'.bin2hex(random_bytes(6));
    $targetPath = $projectRoot.'/app/Jobs/UnusedShipmentReminder.php';
    $analysisPath = $projectRoot.'/storage/app/deadcode/analysis.json';

    File::ensureDirectoryExists(dirname($targetPath));
    File::ensureDirectoryExists(dirname($analysisPath));

    file_put_contents($targetPath, $fileContents);
    file_put_contents(
        $analysisPath,
        json_encode(
            deadcodeJobClassRemovalPayload($changeSets),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        ),
    );

    app()->setBasePath($projectRoot);
    app()->useStoragePath($projectRoot.'/storage');

    return [$projectRoot, $analysisPath, $targetPath, $fileContents];
}

/**
 * @param  list<array{file:string,symbol:string,start_line:int,end_line:int}>|null  $changeSets
 */
function deadcodeJobClassRemovalPayload(?array $changeSets = null): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-job-removal',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 23,
            'cache_hits' => 1,
            'cache_misses' => 1,
        ],
        'entrypoints' => [
            [
                'kind' => 'runtime_job',
                'symbol' => 'App\\Jobs\\ReachableShipmentReminder',
                'source' => 'redis:emails',
            ],
        ],
        'symbols' => [
            [
                'kind' => 'job_class',
                'symbol' => 'App\\Jobs\\UnusedShipmentReminder',
                'file' => 'app/Jobs/UnusedShipmentReminder.php',
                'reachableFromRuntime' => false,
                'startLine' => 9,
                'endLine' => 16,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Jobs\\UnusedShipmentReminder',
                'category' => 'unused_job_class',
                'confidence' => 'high',
                'file' => 'app/Jobs/UnusedShipmentReminder.php',
                'startLine' => 9,
                'endLine' => 16,
            ],
        ],
        'removalPlan' => [
            'changeSets' => $changeSets ?? [
                [
                    'file' => 'app/Jobs/UnusedShipmentReminder.php',
                    'symbol' => 'App\\Jobs\\UnusedShipmentReminder',
                    'start_line' => 9,
                    'end_line' => 16,
                ],
            ],
        ],
    ];
}

function deadcodeJobClassStagedContents(): string
{
    return <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;


PHP;
}
