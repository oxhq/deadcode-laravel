<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Runtime;

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Support\Providers\EventServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Artisan;
use Oxhq\Oxcribe\Contracts\PackageInventoryDetector;
use Oxhq\Oxcribe\Contracts\RuntimeSnapshotFactory;
use Oxhq\Oxcribe\Data\AppSnapshot;
use Oxhq\Oxcribe\Data\CommandSnapshot;
use Oxhq\Oxcribe\Data\ListenerSnapshot;
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
            listeners: $this->registeredListeners(),
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

    /**
     * @return list<ListenerSnapshot>
     */
    private function registeredListeners(): array
    {
        $listeners = [];

        foreach ($this->app->getProviders(EventServiceProvider::class) as $provider) {
            foreach ($provider->getEvents() as $event => $registeredListeners) {
                if (! is_string($event) || $event === '' || str_contains($event, '*')) {
                    continue;
                }

                foreach ($this->normalizeListeners($registeredListeners) as $listenerFqcn) {
                    $listeners[$event.'|'.$listenerFqcn] = new ListenerSnapshot(
                        eventFqcn: $event,
                        listenerFqcn: $listenerFqcn,
                    );
                }
            }
        }

        $listeners = array_values($listeners);

        usort(
            $listeners,
            static fn (ListenerSnapshot $left, ListenerSnapshot $right): int => [$left->eventFqcn, $left->listenerFqcn]
                <=> [$right->eventFqcn, $right->listenerFqcn],
        );

        return $listeners;
    }

    /**
     * @return list<string>
     */
    private function normalizeListeners(mixed $registeredListeners): array
    {
        if (is_string($registeredListeners)) {
            $listenerFqcn = $this->listenerClassFromString($registeredListeners);

            return $listenerFqcn !== null ? [$listenerFqcn] : [];
        }

        if (is_array($registeredListeners)) {
            if (isset($registeredListeners[0]) && is_string($registeredListeners[0])) {
                return method_exists($registeredListeners[0], 'subscribe')
                    ? []
                    : [$registeredListeners[0]];
            }

            $listeners = [];

            foreach ($registeredListeners as $listener) {
                array_push($listeners, ...$this->normalizeListeners($listener));
            }

            return $listeners;
        }

        if (is_object($registeredListeners) && ! $registeredListeners instanceof \Closure) {
            $listenerFqcn = $registeredListeners::class;

            return method_exists($listenerFqcn, 'subscribe') ? [] : [$listenerFqcn];
        }

        return [];
    }

    private function listenerClassFromString(string $listener): ?string
    {
        $listener = trim($listener);

        if ($listener === '') {
            return null;
        }

        $listenerFqcn = $listener;

        foreach (['@', '::'] as $separator) {
            if (str_contains($listenerFqcn, $separator)) {
                $listenerFqcn = strstr($listenerFqcn, $separator, true);
            }
        }

        $listenerFqcn = trim((string) $listenerFqcn);
        if ($listenerFqcn === '' || method_exists($listenerFqcn, 'subscribe')) {
            return null;
        }

        return $listenerFqcn;
    }
}
