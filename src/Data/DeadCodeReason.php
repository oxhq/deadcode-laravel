<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

use JsonSerializable;

final readonly class DeadCodeReason implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $summary,
        public ?string $source = null,
        public ?string $relatedSymbol = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            code: (string) ($payload['code'] ?? ''),
            summary: (string) ($payload['summary'] ?? ''),
            source: isset($payload['source']) ? (string) $payload['source'] : null,
            relatedSymbol: isset($payload['relatedSymbol']) ? (string) $payload['relatedSymbol'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'code' => $this->code,
            'summary' => $this->summary,
            'source' => $this->source,
            'relatedSymbol' => $this->relatedSymbol,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
