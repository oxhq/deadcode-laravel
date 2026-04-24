<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

final readonly class CommandSnapshot
{
    public function __construct(
        public string $signature,
        public string $fqcn,
        public ?string $description = null,
    ) {}

    /**
     * @return array{signature: string, fqcn: string, description: ?string}
     */
    public function toArray(): array
    {
        return [
            'signature' => $this->signature,
            'fqcn' => $this->fqcn,
            'description' => $this->description,
        ];
    }

    /**
     * @return array{signature: string, fqcn: string, description?: string}
     */
    public function toWireArray(): array
    {
        return array_filter(
            $this->toArray(),
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
