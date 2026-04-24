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
     * @var list<SubscriberSnapshot>
     */
    public array $subscribers;

    /**
     * @param  list<RouteSnapshot>  $routes
     * @param  list<CommandSnapshot>  $commands
     * @param  list<ListenerSnapshot>  $listeners
     * @param  list<SubscriberSnapshot>  $subscribers
     */
    public function __construct(
        public AppSnapshot $app,
        public array $routes,
        ?PackageInventorySnapshot $packages = null,
        array $commands = [],
        array $listeners = [],
        array $subscribers = [],
    ) {
        $this->packages = $packages ?? PackageInventorySnapshot::empty();
        $this->commands = array_values($commands);
        $this->listeners = array_values($listeners);
        $this->subscribers = array_values($subscribers);
    }

    public function toArray(): array
    {
        return [
            'app' => $this->app->toArray(),
            'routes' => array_map(static fn (RouteSnapshot $route): array => $route->toArray(), $this->routes),
            'commands' => array_map(static fn (CommandSnapshot $command): array => $command->toArray(), $this->commands),
            'listeners' => array_map(static fn (ListenerSnapshot $listener): array => $listener->toArray(), $this->listeners),
            'subscribers' => array_map(static fn (SubscriberSnapshot $subscriber): array => $subscriber->toArray(), $this->subscribers),
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
            'subscribers' => array_map(static fn (SubscriberSnapshot $subscriber): array => $subscriber->toWireArray(), $this->subscribers),
            'packages' => $this->packages->toWireArray(),
        ];
    }
}
