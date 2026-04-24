<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class SubscriberSnapshot
{
    public function __construct(
        public string $fqcn,
    ) {}

    /**
     * @return array{fqcn: string}
     */
    public function toArray(): array
    {
        return [
            'fqcn' => $this->fqcn,
        ];
    }

    /**
     * @return array{fqcn: string}
     */
    public function toWireArray(): array
    {
        return $this->toArray();
    }
}
