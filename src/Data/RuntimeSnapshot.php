<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class RuntimeSnapshot
{
    public PackageInventorySnapshot $packages;
    /**
     * @var list<CommandSnapshot>
     */
    public array $commands;
    /**
     * @var list<ListenerSnapshot>
     */
    public array $listeners;

    /**
     * @param  list<RouteSnapshot>  $routes
     * @param  list<CommandSnapshot>  $commands
     * @param  list<ListenerSnapshot>  $listeners
     */
    public function __construct(
        public AppSnapshot $app,
        public array $routes,
        ?PackageInventorySnapshot $packages = null,
        array $commands = [],
        array $listeners = [],
    ) {
        $this->packages = $packages ?? PackageInventorySnapshot::empty();
        $this->commands = array_values($commands);
        $this->listeners = array_values($listeners);
    }

    public function toArray(): array
    {
        return [
            'app' => $this->app->toArray(),
            'routes' => array_map(static fn (RouteSnapshot $route): array => $route->toArray(), $this->routes),
            'commands' => array_map(static fn (CommandSnapshot $command): array => $command->toArray(), $this->commands),
            'listeners' => array_map(static fn (ListenerSnapshot $listener): array => $listener->toArray(), $this->listeners),
            'packages' => $this->packages->toArray(),
        ];
    }

    public function toWireArray(): array
    {
        return [
            'app' => $this->app->toArray(),
            'routes' => array_map(static fn (RouteSnapshot $route): array => $route->toWireArray(), $this->routes),
            'commands' => array_map(static fn (CommandSnapshot $command): array => $command->toWireArray(), $this->commands),
            'listeners' => array_map(static fn (ListenerSnapshot $listener): array => $listener->toWireArray(), $this->listeners),
            'packages' => $this->packages->toWireArray(),
        ];
    }
}
