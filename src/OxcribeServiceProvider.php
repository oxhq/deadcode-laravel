<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe;

use Deadcode\Console\Commands\DeadcodeAnalyzeCommand;
use Deadcode\Providers\DeadcodeServiceProvider;
use Illuminate\Support\ServiceProvider;
use Oxhq\Oxcribe\Bridge\AnalysisRequestFactory;
use Oxhq\Oxcribe\Bridge\DeadCodeAnalysisRequestFactory;
use Oxhq\Oxcribe\Bridge\ProcessDeadCodeClient;
use Oxhq\Oxcribe\Bridge\ProcessOxinferClient;
use Oxhq\Oxcribe\Console\ApplyCommand;
use Oxhq\Oxcribe\Console\DoctorCommand;
use Oxhq\Oxcribe\Console\InstallBinaryCommand;
use Oxhq\Oxcribe\Console\ReportCommand;
use Oxhq\Oxcribe\Console\RollbackCommand;
use Oxhq\Oxcribe\Contracts\OxinferClient;
use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Runtime\LaravelRuntimeSnapshotFactory;
use Oxhq\Oxcribe\Support\FormRequestFieldResolver;
use Oxhq\Oxcribe\Support\InstalledPackageDetector;
use Oxhq\Oxcribe\Support\ManifestFactory;
use Oxhq\Oxcribe\Support\RequestSerializer;
use Oxhq\Oxcribe\Support\RouteIdFactory;
use Oxhq\Oxcribe\Support\RouteSnapshotExtractor;

final class OxcribeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/oxcribe.php', 'oxcribe');
        $this->app->register(DeadcodeServiceProvider::class);

        $this->app->singleton(RouteIdFactory::class);
        $this->app->singleton(ManifestFactory::class);
        $this->app->singleton(RouteSnapshotExtractor::class);
        $this->app->singleton(RequestSerializer::class);
        $this->app->singleton(FormRequestFieldResolver::class);
        $this->app->singleton(PackageInventoryDetector::class, function ($app): PackageInventoryDetector {
            return new InstalledPackageDetector((array) $app['config']->get('oxcribe', []));
        });
        $this->app->singleton(RuntimeSnapshotFactory::class, LaravelRuntimeSnapshotFactory::class);
        $this->app->singleton(AnalysisRequestFactory::class, function ($app): AnalysisRequestFactory {
            return new AnalysisRequestFactory(
                manifestFactory: $app->make(ManifestFactory::class),
                config: (array) $app['config']->get('oxcribe', []),
            );
        });
        $this->app->singleton(DeadCodeAnalysisRequestFactory::class, function ($app): DeadCodeAnalysisRequestFactory {
            return new DeadCodeAnalysisRequestFactory(
                manifestFactory: $app->make(ManifestFactory::class),
                config: (array) $app['config']->get('oxcribe', []),
            );
        });
        $this->app->singleton(ProcessDeadCodeClient::class, function ($app): ProcessDeadCodeClient {
            return new ProcessDeadCodeClient((array) $app['config']->get('oxcribe.deadcore', []));
        });
        $this->app->singleton(OxinferClient::class, function ($app): OxinferClient {
            return new ProcessOxinferClient((array) $app['config']->get('oxcribe.deadcore', []));
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/oxcribe.php' => config_path('oxcribe.php'),
        ], 'oxcribe-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ApplyCommand::class,
                DeadcodeAnalyzeCommand::class,
                DoctorCommand::class,
                InstallBinaryCommand::class,
                ReportCommand::class,
                RollbackCommand::class,
            ]);
        }
    }
}

if (! class_exists(\Garaekz\Oxcribe\OxcribeServiceProvider::class, false)) {
    class_alias(OxcribeServiceProvider::class, \Garaekz\Oxcribe\OxcribeServiceProvider::class);
}
