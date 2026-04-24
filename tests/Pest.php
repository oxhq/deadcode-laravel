<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Tests\TestCase;
use Symfony\Component\Process\Process;

uses(TestCase::class)->in('Feature', 'Unit');

function resolveOxinferSourceRoot(): string
{
    $candidates = array_filter([
        trim((string) getenv('OXINFER_SOURCE_ROOT')),
        dirname(__DIR__).'/../oxinfer',
        dirname(__DIR__, 4).'/go/oxinfer',
    ]);

    foreach ($candidates as $candidate) {
        if (is_dir($candidate) && is_file($candidate.'/Cargo.toml')) {
            return $candidate;
        }
    }

    test()->markTestSkipped(
        'Oxinfer source root is not available. Set OXINFER_SOURCE_ROOT to run oxcribe end-to-end fixture tests.'
    );

    throw new RuntimeException('markTestSkipped() should interrupt execution.');
}

function configureFixtureOxinfer(string $fixtureRoot): void
{
    static $builtBinary = null;

    if (! is_string($builtBinary)) {
        $oxinferRoot = resolveOxinferSourceRoot();
        $oxinferBinary = $oxinferRoot.'/target/release/oxinfer'.(DIRECTORY_SEPARATOR === '\\' ? '.exe' : '');
        if (! is_file($oxinferBinary)) {
            $command = ['cargo', 'build', '--release'];

            if (is_file($oxinferRoot.'/Cargo.lock')) {
                $command[] = '--locked';
            }

            $build = new Process($command, $oxinferRoot, null, null, 300);
            $build->mustRun();
        }

        $builtBinary = $oxinferBinary;
    }

    config()->set('oxcribe.oxinfer.binary', $builtBinary);
    config()->set('oxcribe.oxinfer.working_directory', $fixtureRoot);
    config()->set('oxcribe.analysis.cache.enabled', false);
}

function makePortablePhpCommand(string $directory, string $name, string $phpBody): string
{
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $implementationPath = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name.'-impl.php';
    file_put_contents($implementationPath, "<?php\n".$phpBody);

    if (DIRECTORY_SEPARATOR === '\\') {
        $wrapperPath = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name.'.cmd';
        $phpBinary = str_replace('"', '""', PHP_BINARY);
        file_put_contents(
            $wrapperPath,
            "@echo off\r\n\"{$phpBinary}\" \"%~dp0{$name}-impl.php\" %*\r\n"
        );

        return $wrapperPath;
    }

    $wrapperPath = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name;
    $command = sprintf(
        "#!/bin/sh\nexec %s \"$(dirname \"$0\")/%s\" \"$@\"\n",
        escapeshellarg(PHP_BINARY),
        basename($implementationPath),
    );
    file_put_contents($wrapperPath, $command);
    chmod($wrapperPath, 0755);

    return $wrapperPath;
}

function deadcoreControllerReachabilityPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-controller-reachability',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 17,
            'cache_hits' => 2,
            'cache_misses' => 1,
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
                'startLine' => 10,
                'endLine' => 18,
            ],
            [
                'kind' => 'controller_method',
                'symbol' => 'App\\Http\\Controllers\\UserController::unused',
                'file' => 'app/Http/Controllers/UserController.php',
                'reachableFromRuntime' => false,
                'startLine' => 20,
                'endLine' => 24,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Http\\Controllers\\UserController::unused',
                'category' => 'unused_controller_method',
                'confidence' => 'high',
                'file' => 'app/Http/Controllers/UserController.php',
                'startLine' => 20,
                'endLine' => 24,
            ],
        ],
        'removalPlan' => [
            'changeSets' => [
                [
                    'file' => 'app/Http/Controllers/UserController.php',
                    'symbol' => 'App\\Http\\Controllers\\UserController::unused',
                    'start_line' => 20,
                    'end_line' => 24,
                ],
            ],
        ],
    ];
}

function deadcorePhaseTwoHttpAdjacencyPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-phase-two-http-adjacency',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 23,
            'cache_hits' => 3,
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
                'kind' => 'controller_class',
                'symbol' => 'App\\Http\\Controllers\\UnusedWebhookController',
                'file' => 'app/Http/Controllers/UnusedWebhookController.php',
                'reachableFromRuntime' => false,
                'startLine' => 7,
                'endLine' => 29,
            ],
            [
                'kind' => 'form_request_class',
                'symbol' => 'App\\Http\\Requests\\UnusedOrderRequest',
                'file' => 'app/Http/Requests/UnusedOrderRequest.php',
                'reachableFromRuntime' => false,
                'startLine' => 9,
                'endLine' => 18,
            ],
            [
                'kind' => 'resource_class',
                'symbol' => 'App\\Http\\Resources\\UnusedOrderResource',
                'file' => 'app/Http/Resources/UnusedOrderResource.php',
                'reachableFromRuntime' => false,
                'startLine' => 11,
                'endLine' => 24,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Http\\Controllers\\UnusedWebhookController',
                'category' => 'unused_controller_class',
                'confidence' => 'high',
                'file' => 'app/Http/Controllers/UnusedWebhookController.php',
                'startLine' => 7,
                'endLine' => 29,
            ],
            [
                'symbol' => 'App\\Http\\Requests\\UnusedOrderRequest',
                'category' => 'unused_form_request',
                'confidence' => 'medium',
                'file' => 'app/Http/Requests/UnusedOrderRequest.php',
                'startLine' => 9,
                'endLine' => 18,
            ],
            [
                'symbol' => 'App\\Http\\Resources\\UnusedOrderResource',
                'category' => 'unused_resource_class',
                'confidence' => 'medium',
                'file' => 'app/Http/Resources/UnusedOrderResource.php',
                'startLine' => 11,
                'endLine' => 24,
            ],
        ],
        'removalPlan' => [
            'changeSets' => [],
        ],
    ];
}

function deadcoreCommandReachabilityPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-command-reachability',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 29,
            'cache_hits' => 2,
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
                'symbol' => 'App\\Console\\Commands\\ReachableMaintenanceCommand',
                'file' => 'app/Console/Commands/ReachableMaintenanceCommand.php',
                'reachableFromRuntime' => true,
                'startLine' => 9,
                'endLine' => 32,
            ],
            [
                'kind' => 'command_class',
                'symbol' => 'App\\Console\\Commands\\UnusedAuditCommand',
                'file' => 'app/Console/Commands/UnusedAuditCommand.php',
                'reachableFromRuntime' => false,
                'startLine' => 10,
                'endLine' => 28,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Console\\Commands\\UnusedAuditCommand',
                'category' => 'unused_command_class',
                'confidence' => 'high',
                'file' => 'app/Console/Commands/UnusedAuditCommand.php',
                'startLine' => 10,
                'endLine' => 28,
            ],
        ],
        'removalPlan' => [
            'changeSets' => [
                [
                    'file' => 'app/Console/Commands/UnusedAuditCommand.php',
                    'symbol' => 'App\\Console\\Commands\\UnusedAuditCommand',
                    'start_line' => 10,
                    'end_line' => 28,
                ],
            ],
        ],
    ];
}
