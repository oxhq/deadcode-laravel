<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Apply;

use JsonException;
use RuntimeException;

final class RollbackStore
{
    public const CONTRACT_VERSION = 'deadcode.rollback.v1';

    /**
     * @param  list<array{file:string,symbol:string,original:string}>  $changes
     */
    public function store(array $changes): string
    {
        $directory = $this->directory();
        if (! is_dir($directory) && ! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create rollback directory [%s].', $directory));
        }

        $path = $this->latestPath();
        $payload = $this->encodePayload([
            'contractVersion' => self::CONTRACT_VERSION,
            'storedAt' => gmdate(DATE_ATOM),
            'changes' => $changes,
        ]);
        $bytesWritten = @file_put_contents($path, $payload);
        if ($bytesWritten === false || ! is_file($path)) {
            throw new RuntimeException(sprintf('Unable to persist rollback payload [%s].', $path));
        }

        $storedPayload = @file_get_contents($path);
        if ($storedPayload === false || $storedPayload !== $payload) {
            throw new RuntimeException(sprintf('Unable to verify rollback payload [%s].', $path));
        }

        return $path;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latest(): ?array
    {
        $path = $this->latestPath();
        if (! is_file($path)) {
            return null;
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    public function deleteLatest(): void
    {
        $path = $this->latestPath();
        if (is_file($path) && ! @unlink($path) && is_file($path)) {
            throw new RuntimeException(sprintf('Unable to delete rollback payload [%s].', $path));
        }
    }

    public function latestPath(): string
    {
        return $this->directory().DIRECTORY_SEPARATOR.'latest.json';
    }

    private function directory(): string
    {
        return storage_path('app/deadcode/rollback');
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }
}
