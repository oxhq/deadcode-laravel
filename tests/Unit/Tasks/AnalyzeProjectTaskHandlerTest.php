<?php

declare(strict_types=1);

use Deadcode\Runtime\Worker\InMemoryTaskContext;
use Deadcode\Tasks\AnalyzeProjectTask;
use Deadcode\Tasks\AnalyzeProjectTaskHandler;
use Oxhq\Oxcribe\Bridge\DeadCodeAnalysisRequestFactory;
use Oxhq\Oxcribe\Bridge\ProcessDeadCodeClient;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\CommandSnapshot;
use Oxhq\Oxcribe\Data\ListenerSnapshot;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Support\ManifestFactory;

it('fails when the target project path is not an existing directory', function (): void {
    $handler = new AnalyzeProjectTaskHandler(
        new AnalyzeProjectTaskHandlerTestRuntimeSnapshotFactory(base_path()),
        new DeadCodeAnalysisRequestFactory(new ManifestFactory, []),
        new ProcessDeadCodeClient(['binary' => base_path('missing-deadcore')]),
    );

    $handler->handle(
        new AnalyzeProjectTask(sys_get_temp_dir().'/deadcode-missing-project'),
        new InMemoryTaskContext('task-invalid-project'),
    );
})->throws(InvalidArgumentException::class, 'Analyze project path must be an existing directory');

it('captures runtime, invokes deadcore, writes an analysis payload, and returns the response finding count', function (): void {
    $workspace = sys_get_temp_dir().'/deadcode-task-handler-'.bin2hex(random_bytes(4));
    $projectRoot = $workspace.'/target-project';
    $binaryRoot = $workspace.'/bin';
    mkdir($projectRoot, 0777, true);
    $projectRoot = (string) realpath($projectRoot);

    $requestCapturePath = $workspace.'/request.json';
    $deadcoreBinary = makePortablePhpCommand($binaryRoot, 'deadcore', sprintf(
        <<<'PHP'
$request = stream_get_contents(STDIN);
file_put_contents(%s, $request);

fwrite(STDOUT, json_encode([
    'contractVersion' => 'deadcode.analysis.v1',
    'requestId' => 'req-task-handler',
    'status' => 'ok',
    'meta' => [
        'duration_ms' => 31,
        'cache_hits' => 4,
        'cache_misses' => 2,
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
        ],
        [
            'symbol' => 'App\\Http\\Requests\\UnusedOrderRequest',
            'category' => 'unused_form_request',
            'confidence' => 'medium',
            'file' => 'app/Http/Requests/UnusedOrderRequest.php',
        ],
        [
            'symbol' => 'App\\Http\\Resources\\UnusedOrderResource',
            'category' => 'unused_resource_class',
            'confidence' => 'medium',
            'file' => 'app/Http/Resources/UnusedOrderResource.php',
        ],
    ],
    'removalPlan' => [
        'changeSets' => [],
    ],
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
PHP,
        var_export($requestCapturePath, true),
    ));

    $handler = new AnalyzeProjectTaskHandler(
        new AnalyzeProjectTaskHandlerTestRuntimeSnapshotFactory($projectRoot),
        new DeadCodeAnalysisRequestFactory(new ManifestFactory, []),
        new ProcessDeadCodeClient([
            'binary' => $deadcoreBinary,
            'working_directory' => $projectRoot,
        ]),
    );
    $context = new InMemoryTaskContext('task-success');

    $result = $handler->handle(new AnalyzeProjectTask($projectRoot), $context);

    $expectedAnalysisPath = $projectRoot.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'deadcode'.DIRECTORY_SEPARATOR.'analysis.json';
    expect($result->status)->toBe('ok')
        ->and($result->data['findingCount'])->toBe(3)
        ->and($result->data['analysisPath'])->toBe($expectedAnalysisPath)
        ->and($result->meta['durationMs'])->toBe(31)
        ->and($expectedAnalysisPath)->toBeFile();

    $requestPayload = json_decode((string) file_get_contents($requestCapturePath), true, 512, JSON_THROW_ON_ERROR);
    expect($requestPayload['runtime']['app']['basePath'])->toBe($projectRoot)
        ->and($requestPayload['manifest']['project']['root'])->toBe($projectRoot)
        ->and($requestPayload['runtime']['listeners'])->toBe([
            [
                'eventFqcn' => 'App\\Events\\OrderShipped',
                'listenerFqcn' => 'App\\Listeners\\SendReachableShipmentNotification',
            ],
        ])
        ->and($requestPayload['runtime']['commands'])->toBe([
            [
                'signature' => 'maintenance:reachable',
                'fqcn' => 'App\\Console\\Commands\\ReachableMaintenanceCommand',
                'description' => 'Run the reachable maintenance workflow.',
            ],
        ]);

    $analysisPayload = json_decode((string) file_get_contents($expectedAnalysisPath), true, 512, JSON_THROW_ON_ERROR);
    expect($analysisPayload['contractVersion'])->toBe('deadcode.analysis.v1')
        ->and($analysisPayload['requestId'])->toBe('req-task-handler')
        ->and($analysisPayload['findings'])->toHaveCount(3)
        ->and(array_column($analysisPayload['symbols'], 'kind'))->toBe([
            'controller_class',
            'form_request_class',
            'resource_class',
        ])
        ->and(array_column($analysisPayload['findings'], 'category'))->toBe([
            'unused_controller_class',
            'unused_form_request',
            'unused_resource_class',
        ]);

    $progressMessages = array_column($context->events(), 'message');
    expect($progressMessages)->toContain(
        'Validating target project',
        'Capturing Laravel runtime snapshot',
        'Building deadcore request',
        'Invoking deadcore',
        'Writing report',
    );
});

it('fails when the target project does not match the bootstrapped worker app', function (): void {
    $workspace = sys_get_temp_dir().'/deadcode-task-handler-mismatch-'.bin2hex(random_bytes(4));
    $projectRoot = $workspace.'/target-project';
    $workerRoot = $workspace.'/worker-app';
    mkdir($projectRoot, 0777, true);
    mkdir($workerRoot, 0777, true);

    $handler = new AnalyzeProjectTaskHandler(
        new AnalyzeProjectTaskHandlerTestRuntimeSnapshotFactory((string) realpath($workerRoot)),
        new DeadCodeAnalysisRequestFactory(new ManifestFactory, []),
        new ProcessDeadCodeClient(['binary' => base_path('missing-deadcore')]),
    );

    $handler->handle(
        new AnalyzeProjectTask((string) realpath($projectRoot)),
        new InMemoryTaskContext('task-mismatch-project'),
    );
})->throws(RuntimeException::class, 'The worker is bootstrapped for');

final class AnalyzeProjectTaskHandlerTestRuntimeSnapshotFactory implements RuntimeSnapshotFactory
{
    public function __construct(private readonly string $basePath) {}

    public function make(): RuntimeSnapshot
    {
        return new RuntimeSnapshot(
            app: new AppSnapshot(
                basePath: $this->basePath,
                laravelVersion: '12.0.0',
                phpVersion: PHP_VERSION,
                appEnv: 'testing',
            ),
            routes: [],
            commands: [
                new CommandSnapshot(
                    signature: 'maintenance:reachable',
                    fqcn: 'App\\Console\\Commands\\ReachableMaintenanceCommand',
                    description: 'Run the reachable maintenance workflow.',
                ),
            ],
            listeners: [
                new ListenerSnapshot(
                    eventFqcn: 'App\\Events\\OrderShipped',
                    listenerFqcn: 'App\\Listeners\\SendReachableShipmentNotification',
                ),
            ],
        );
    }
}
