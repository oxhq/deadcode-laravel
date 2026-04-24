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
                'reasonSummary' => 'Reachable through Laravel runtime routing or supported controller call expansion.',
                'reachabilityReasons' => [
                    [
                        'code' => 'supported_controller_reachability',
                        'summary' => 'Laravel runtime routes or supported controller call expansion keep this controller method alive.',
                    ],
                ],
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
                'reasonSummary' => 'No runtime route or supported controller call keeps this method alive.',
                'evidence' => [
                    [
                        'code' => 'no_supported_controller_reachability',
                        'summary' => 'No Laravel runtime route or supported controller call expansion reaches this controller method.',
                    ],
                ],
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

function deadcorePolicyReachabilityPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-policy-reachability',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 27,
            'cache_hits' => 2,
            'cache_misses' => 1,
        ],
        'entrypoints' => [
            [
                'kind' => 'runtime_policy',
                'symbol' => 'App\\Policies\\OrderPolicy',
                'source' => 'App\\Models\\Order',
            ],
        ],
        'symbols' => [
            [
                'kind' => 'policy_class',
                'symbol' => 'App\\Policies\\OrderPolicy',
                'file' => 'app/Policies/OrderPolicy.php',
                'reachableFromRuntime' => true,
                'startLine' => 9,
                'endLine' => 22,
            ],
            [
                'kind' => 'policy_class',
                'symbol' => 'App\\Policies\\UnusedInvoicePolicy',
                'file' => 'app/Policies/UnusedInvoicePolicy.php',
                'reachableFromRuntime' => false,
                'startLine' => 9,
                'endLine' => 22,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Policies\\UnusedInvoicePolicy',
                'category' => 'unused_policy_class',
                'confidence' => 'high',
                'file' => 'app/Policies/UnusedInvoicePolicy.php',
                'startLine' => 9,
                'endLine' => 22,
            ],
        ],
        'removalPlan' => [
            'changeSets' => [
                [
                    'file' => 'app/Policies/UnusedInvoicePolicy.php',
                    'symbol' => 'App\\Policies\\UnusedInvoicePolicy',
                    'start_line' => 9,
                    'end_line' => 22,
                ],
            ],
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

function deadcoreListenerReachabilityPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-listener-reachability',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 31,
            'cache_hits' => 2,
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
                'symbol' => 'App\\Listeners\\SendReachableShipmentNotification',
                'file' => 'app/Listeners/SendReachableShipmentNotification.php',
                'reachableFromRuntime' => true,
                'startLine' => 9,
                'endLine' => 18,
            ],
            [
                'kind' => 'listener_class',
                'symbol' => 'App\\Listeners\\UnusedInventoryListener',
                'file' => 'app/Listeners/UnusedInventoryListener.php',
                'reachableFromRuntime' => false,
                'startLine' => 9,
                'endLine' => 18,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Listeners\\UnusedInventoryListener',
                'category' => 'unused_listener_class',
                'confidence' => 'high',
                'file' => 'app/Listeners/UnusedInventoryListener.php',
                'startLine' => 9,
                'endLine' => 18,
            ],
        ],
        'removalPlan' => [
            'changeSets' => [
                [
                    'file' => 'app/Listeners/UnusedInventoryListener.php',
                    'symbol' => 'App\\Listeners\\UnusedInventoryListener',
                    'start_line' => 9,
                    'end_line' => 18,
                ],
            ],
        ],
    ];
}

function deadcoreSubscriberReachabilityPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-subscriber-reachability',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 33,
            'cache_hits' => 2,
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
                'symbol' => 'App\\Subscribers\\ReachableOrderSubscriber',
                'file' => 'app/Subscribers/ReachableOrderSubscriber.php',
                'reachableFromRuntime' => true,
                'startLine' => 10,
                'endLine' => 20,
            ],
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
            'changeSets' => [
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

function deadcoreJobReachabilityPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-job-reachability',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 35,
            'cache_hits' => 2,
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
                'symbol' => 'App\\Jobs\\ReachableShipmentReminder',
                'file' => 'app/Jobs/ReachableShipmentReminder.php',
                'reachableFromRuntime' => true,
                'startLine' => 9,
                'endLine' => 16,
            ],
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
            'changeSets' => [
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

function deadcorePhaseFourModelPayload(): array
{
    return [
        'contractVersion' => 'deadcode.analysis.v1',
        'requestId' => 'req-phase4-models',
        'status' => 'ok',
        'meta' => [
            'duration_ms' => 41,
            'cache_hits' => 3,
            'cache_misses' => 2,
        ],
        'entrypoints' => [
            [
                'kind' => 'runtime_route',
                'symbol' => 'App\\Http\\Controllers\\InvoiceController::show',
                'source' => 'invoices.show',
            ],
        ],
        'symbols' => [
            [
                'kind' => 'model_method',
                'symbol' => 'App\\Models\\Invoice::summary',
                'file' => 'app/Models/Invoice.php',
                'reachableFromRuntime' => false,
                'startLine' => 12,
                'endLine' => 16,
            ],
            [
                'kind' => 'model_scope',
                'symbol' => 'App\\Models\\Invoice::published',
                'file' => 'app/Models/Invoice.php',
                'reachableFromRuntime' => false,
                'startLine' => 18,
                'endLine' => 22,
            ],
            [
                'kind' => 'model_relationship',
                'symbol' => 'App\\Models\\Invoice::customer',
                'file' => 'app/Models/Invoice.php',
                'reachableFromRuntime' => false,
                'startLine' => 24,
                'endLine' => 28,
            ],
            [
                'kind' => 'model_accessor',
                'symbol' => 'App\\Models\\User::display_name',
                'file' => 'app/Models/User.php',
                'reachableFromRuntime' => false,
                'startLine' => 10,
                'endLine' => 13,
            ],
            [
                'kind' => 'model_mutator',
                'symbol' => 'App\\Models\\User::display_name',
                'file' => 'app/Models/User.php',
                'reachableFromRuntime' => false,
                'startLine' => 15,
                'endLine' => 18,
            ],
        ],
        'findings' => [
            [
                'symbol' => 'App\\Models\\Invoice::summary',
                'category' => 'unused_model_method',
                'confidence' => 'high',
                'file' => 'app/Models/Invoice.php',
                'reasonSummary' => 'No supported explicit model call from already-reachable code reaches this method.',
                'evidence' => [
                    [
                        'code' => 'no_supported_model_call',
                        'summary' => 'No supported explicit model helper call from already-reachable code reaches this method.',
                    ],
                ],
                'startLine' => 12,
                'endLine' => 16,
            ],
            [
                'symbol' => 'App\\Models\\Invoice::published',
                'category' => 'unused_model_scope',
                'confidence' => 'high',
                'file' => 'app/Models/Invoice.php',
                'reasonSummary' => 'No supported explicit scope-call pattern reaches this local scope.',
                'evidence' => [
                    [
                        'code' => 'no_supported_scope_call',
                        'summary' => 'No supported explicit scope-call pattern reaches this local scope.',
                    ],
                ],
                'startLine' => 18,
                'endLine' => 22,
            ],
            [
                'symbol' => 'App\\Models\\Invoice::customer',
                'category' => 'unused_model_relationship',
                'confidence' => 'high',
                'file' => 'app/Models/Invoice.php',
                'reasonSummary' => 'No supported explicit relationship access or eager loading reaches this relationship.',
                'evidence' => [
                    [
                        'code' => 'no_supported_relationship_usage',
                        'summary' => 'No supported explicit relationship access or eager-loading pattern reaches this relationship.',
                    ],
                ],
                'startLine' => 24,
                'endLine' => 28,
            ],
            [
                'symbol' => 'App\\Models\\User::display_name',
                'category' => 'unused_model_accessor',
                'confidence' => 'high',
                'file' => 'app/Models/User.php',
                'reasonSummary' => 'No supported explicit attribute read or append metadata reaches this accessor.',
                'evidence' => [
                    [
                        'code' => 'no_supported_attribute_read',
                        'summary' => 'No supported explicit attribute read or append metadata reaches this accessor.',
                    ],
                ],
                'startLine' => 10,
                'endLine' => 13,
            ],
            [
                'symbol' => 'App\\Models\\User::display_name',
                'category' => 'unused_model_mutator',
                'confidence' => 'high',
                'file' => 'app/Models/User.php',
                'reasonSummary' => 'No supported explicit attribute write reaches this mutator.',
                'evidence' => [
                    [
                        'code' => 'no_supported_attribute_write',
                        'summary' => 'No supported explicit attribute write reaches this mutator.',
                    ],
                ],
                'startLine' => 15,
                'endLine' => 18,
            ],
        ],
        'removalPlan' => [
            'changeSets' => [
                [
                    'file' => 'app/Models/Invoice.php',
                    'symbol' => 'App\\Models\\Invoice::summary',
                    'start_line' => 12,
                    'end_line' => 16,
                ],
                [
                    'file' => 'app/Models/Invoice.php',
                    'symbol' => 'App\\Models\\Invoice::published',
                    'start_line' => 18,
                    'end_line' => 22,
                ],
                [
                    'file' => 'app/Models/Invoice.php',
                    'symbol' => 'App\\Models\\Invoice::customer',
                    'start_line' => 24,
                    'end_line' => 28,
                ],
                [
                    'file' => 'app/Models/User.php',
                    'symbol' => 'App\\Models\\User::display_name',
                    'start_line' => 10,
                    'end_line' => 13,
                ],
                [
                    'file' => 'app/Models/User.php',
                    'symbol' => 'App\\Models\\User::display_name',
                    'start_line' => 15,
                    'end_line' => 18,
                ],
            ],
        ],
    ];
}

function createDeadcodePolicyClassRemovalFixture(): array
{
    $fileContents = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

final class UnusedInvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }
}
PHP;

    $projectRoot = sys_get_temp_dir().'/deadcode-policy-removal-'.bin2hex(random_bytes(6));
    $targetPath = $projectRoot.'/app/Policies/UnusedInvoicePolicy.php';
    $analysisPath = $projectRoot.'/storage/app/deadcode/analysis.json';

    File::ensureDirectoryExists(dirname($targetPath));
    File::ensureDirectoryExists(dirname($analysisPath));

    file_put_contents($targetPath, $fileContents);
    file_put_contents(
        $analysisPath,
        json_encode(deadcorePolicyReachabilityPayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
    );

    app()->setBasePath($projectRoot);
    app()->useStoragePath($projectRoot.'/storage');

    return [$projectRoot, $analysisPath, $targetPath, $fileContents];
}
