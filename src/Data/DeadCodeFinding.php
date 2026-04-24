<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Data;

use JsonSerializable;

final readonly class DeadCodeFinding implements JsonSerializable
{
    public function __construct(
        public string $symbol,
        public string $category,
        public string $confidence,
        public string $file,
        public ?string $reasonSummary = null,
        public array $evidence = [],
        public ?int $startLine = null,
        public ?int $endLine = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            symbol: (string) ($payload['symbol'] ?? ''),
            category: (string) ($payload['category'] ?? ''),
            confidence: (string) ($payload['confidence'] ?? ''),
            file: (string) ($payload['file'] ?? ''),
            reasonSummary: isset($payload['reasonSummary']) ? (string) $payload['reasonSummary'] : null,
            evidence: array_map(
                static fn (array $reason): DeadCodeReason => DeadCodeReason::fromArray($reason),
                array_values((array) ($payload['evidence'] ?? [])),
            ),
            startLine: isset($payload['startLine']) ? (int) $payload['startLine'] : null,
            endLine: isset($payload['endLine']) ? (int) $payload['endLine'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'symbol' => $this->symbol,
            'category' => $this->category,
            'confidence' => $this->confidence,
            'file' => $this->file,
            'reasonSummary' => $this->reasonSummary,
            'evidence' => array_map(
                static fn (DeadCodeReason $reason): array => $reason->jsonSerialize(),
                $this->evidence,
            ),
            'startLine' => $this->startLine,
            'endLine' => $this->endLine,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
