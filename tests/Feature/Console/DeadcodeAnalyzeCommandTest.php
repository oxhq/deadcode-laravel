<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;

it('emits the raw deadcore response while running deadcode analyze', function (): void {
    [$projectRoot] = configureFakeDeadcoreCommand(deadcoreControllerReachabilityPayload());
    bindFakeRuntimeSnapshotFactory($projectRoot);

    expect(Artisan::call('deadcode:analyze'))->toBe(0);

    $payload = json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR);

    expect($payload)->toBe(deadcoreControllerReachabilityPayload());

    File::deleteDirectory($projectRoot);
});

it('writes the raw deadcore response to the requested file', function (): void {
    [$projectRoot] = configureFakeDeadcoreCommand(deadcoreControllerReachabilityPayload());
    bindFakeRuntimeSnapshotFactory($projectRoot);
    $target = $projectRoot.'/analysis.json';

    expect(Artisan::call('deadcode:analyze', ['--write' => $target]))->toBe(0)
        ->and(File::exists($target))->toBeTrue()
        ->and(json_decode((string) file_get_contents($target), true, 512, JSON_THROW_ON_ERROR))
        ->toBe(deadcoreControllerReachabilityPayload());

    File::deleteDirectory($projectRoot);
});

function configureFakeDeadcoreCommand(array $payload): array
{
    $projectRoot = sys_get_temp_dir().'/deadcode-analyze-'.bin2hex(random_bytes(6));
    $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $script = sprintf(
        <<<'PHP'
$payload = %s;
fwrite(STDOUT, $payload);
PHP,
        var_export($encodedPayload, true),
    );

    $binaryPath = makePortablePhpCommand($projectRoot.'/bin', 'deadcore', $script);

    config()->set('oxcribe.deadcore.binary', $binaryPath);

    return [$projectRoot, $binaryPath];
}

function bindFakeRuntimeSnapshotFactory(string $projectRoot): void
{
    app()->instance(RuntimeSnapshotFactory::class, new class($projectRoot) implements RuntimeSnapshotFactory
    {
        public function __construct(
            private readonly string $projectRoot,
        ) {}

        public function make(): RuntimeSnapshot
        {
            return new RuntimeSnapshot(
                app: new AppSnapshot(
                    basePath: $this->projectRoot,
                    laravelVersion: '12.0.0',
                    phpVersion: PHP_VERSION,
                    appEnv: 'testing',
                ),
                routes: [],
            );
        }
    });
}
