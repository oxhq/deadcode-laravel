<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class ListenerSnapshot
{
    public function __construct(
        public string $eventFqcn,
        public string $listenerFqcn,
    ) {}

    /**
     * @return array{eventFqcn: string, listenerFqcn: string}
     */
    public function toArray(): array
    {
        return [
            'eventFqcn' => $this->eventFqcn,
            'listenerFqcn' => $this->listenerFqcn,
        ];
    }

    /**
     * @return array{eventFqcn: string, listenerFqcn: string}
     */
    public function toWireArray(): array
    {
        return $this->toArray();
    }
}
