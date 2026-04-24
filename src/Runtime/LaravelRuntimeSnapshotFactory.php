<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Runtime;

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\CommandSnapshot;
use Oxhq\Oxcribe\Data\RuntimeSnapshot;
use Oxhq\Oxcribe\Support\RouteSnapshotExtractor;
use Symfony\Component\Console\Command\Command;

final class LaravelRuntimeSnapshotFactory implements RuntimeSnapshotFactory
{
    public function __construct(
        private readonly Application $app,
        private readonly Router $router,
        private readonly RouteSnapshotExtractor $routeSnapshotExtractor,
        private readonly PackageInventoryDetector $packageInventoryDetector,
    ) {}

    public function make(): RuntimeSnapshot
    {
        $routes = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $routes[] = $this->routeSnapshotExtractor->extractSnapshot($route);
        }

        return new RuntimeSnapshot(
            app: new AppSnapshot(
                basePath: $this->app->basePath(),
                laravelVersion: $this->app->version(),
                phpVersion: PHP_VERSION,
                appEnv: $this->app->environment(),
            ),
            routes: $routes,
            packages: $this->packageInventoryDetector->detect($this->app->basePath()),
            commands: $this->registeredCommands(),
        );
    }

    /**
     * @return list<CommandSnapshot>
     */
    private function registeredCommands(): array
    {
        $commands = [];
        $seenObjectIds = [];

        foreach (Artisan::all() as $command) {
            if ($command instanceof ClosureCommand) {
                continue;
            }

            $objectId = spl_object_id($command);
            if (isset($seenObjectIds[$objectId])) {
                continue;
            }
            $seenObjectIds[$objectId] = true;

            $snapshot = $this->commandSnapshot($command);
            if ($snapshot === null) {
                continue;
            }

            $commands[] = $snapshot;
        }

        usort(
            $commands,
            static fn (CommandSnapshot $left, CommandSnapshot $right): int => $left->signature <=> $right->signature,
        );

        return $commands;
    }

    private function commandSnapshot(Command $command): ?CommandSnapshot
    {
        $signature = $command->getName();
        if (! is_string($signature) || $signature === '') {
            return null;
        }

        $fqcn = $command::class;
        if ($fqcn === '') {
            return null;
        }

        $description = trim($command->getDescription());

        return new CommandSnapshot(
            signature: $signature,
            fqcn: $fqcn,
            description: $description !== '' ? $description : null,
        );
    }
}
