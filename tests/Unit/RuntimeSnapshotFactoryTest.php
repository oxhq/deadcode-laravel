<?php

declare(strict_types=1);

use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Support\Facades\Route;
use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\PackageInventorySnapshot;
use Oxhq\Oxcribe\Data\PackageSnapshot;
use Oxhq\Oxcribe\Data\SpatiePackageSnapshot;

it('builds a runtime snapshot with application metadata and routes', function () {
    app()->instance(PackageInventoryDetector::class, new class implements PackageInventoryDetector
    {
        public function detect(string $projectRoot): PackageInventorySnapshot
        {
            return new PackageInventorySnapshot(
                spatie: new SpatiePackageSnapshot(
                    laravelData: PackageSnapshot::installed('spatie/laravel-data', '4.0.0', 'composer.lock'),
                    laravelQueryBuilder: PackageSnapshot::missing('spatie/laravel-query-builder'),
                    laravelPermission: PackageSnapshot::missing('spatie/laravel-permission'),
                    laravelMedialibrary: PackageSnapshot::missing('spatie/laravel-medialibrary'),
                    laravelTranslatable: PackageSnapshot::missing('spatie/laravel-translatable'),
                ),
            );
        }
    });

    Route::get('/oxcribe/runtime', static fn () => 'ok')
        ->name('oxcribe.runtime')
        ->middleware(['api']);

    $snapshot = app(RuntimeSnapshotFactory::class)->make();

    expect($snapshot->app->basePath)->toBe(base_path())
        ->and($snapshot->app->laravelVersion)->toBe(app()->version())
        ->and($snapshot->app->phpVersion)->toBe(PHP_VERSION)
        ->and($snapshot->routes)->not->toBeEmpty();

    $runtimeRoute = collect($snapshot->routes)->first(
        fn ($route) => $route->name === 'oxcribe.runtime'
    );

    expect($runtimeRoute)->not->toBeNull()
        ->and($runtimeRoute->uri)->toBe('oxcribe/runtime')
        ->and($runtimeRoute->methods)->toContain('GET')
        ->and($runtimeRoute->middleware)->toContain('api')
        ->and($snapshot->commands)->not->toBeEmpty();

    $reportCommand = collect($snapshot->commands)->first(
        fn ($command) => $command->signature === 'deadcode:report'
    );

    expect($reportCommand)->not->toBeNull()
        ->and($reportCommand->fqcn)->toBe(\Oxhq\Oxcribe\Console\ReportCommand::class)
        ->and($reportCommand->description)->toBe('Render a local dead code report from an existing analysis payload')
        ->and($snapshot->packages->spatie->laravelData->installed)->toBeTrue()
        ->and($snapshot->packages->spatie->laravelData->version)->toBe('4.0.0');
});

it('captures registered listener mappings without widening to subscribers', function () {
    app()->register(RuntimeSnapshotFactoryTestEventServiceProvider::class);

    $snapshot = app(RuntimeSnapshotFactory::class)->make();

    expect($snapshot->listeners)->toHaveCount(1)
        ->and($snapshot->listeners[0]->eventFqcn)->toBe(RuntimeSnapshotFactoryTestOrderShipped::class)
        ->and($snapshot->listeners[0]->listenerFqcn)->toBe(RuntimeSnapshotFactoryTestSendShipmentNotification::class)
        ->and(collect($snapshot->listeners)->contains(
            fn ($listener) => $listener->listenerFqcn === RuntimeSnapshotFactoryTestInventorySubscriber::class
        ))->toBeFalse();
});

final class RuntimeSnapshotFactoryTestOrderShipped {}

final class RuntimeSnapshotFactoryTestSendShipmentNotification
{
    public function handle(RuntimeSnapshotFactoryTestOrderShipped $event): void {}
}

final class RuntimeSnapshotFactoryTestInventorySubscriber
{
    public function subscribe($events): array
    {
        return [
            RuntimeSnapshotFactoryTestOrderShipped::class => 'onOrderShipped',
        ];
    }

    public function onOrderShipped(RuntimeSnapshotFactoryTestOrderShipped $event): void {}
}

final class RuntimeSnapshotFactoryTestEventServiceProvider extends EventServiceProvider
{
    protected $listen = [
        RuntimeSnapshotFactoryTestOrderShipped::class => [
            RuntimeSnapshotFactoryTestSendShipmentNotification::class,
        ],
    ];

    protected $subscribe = [
        RuntimeSnapshotFactoryTestInventorySubscriber::class,
    ];
}
