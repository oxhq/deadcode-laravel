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
    ]);

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
        ->and($output)->toContain('app/Http/Controllers/UserController.php');

    File::deleteDirectory($projectRoot);
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

function createDeadcodeRemediationFixture(): array
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
    file_put_contents($analysisPath, json_encode(deadcodeControllerMethodRemovalPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

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
