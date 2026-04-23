<?php

declare(strict_types=1);

use Deadcode\Runtime\Protocol\FrameCodec;

it('encodes and decodes a progress frame as json lines', function (): void {
    $line = FrameCodec::encode([
        'type' => 'task.progress',
        'taskId' => 'task-1',
        'message' => 'Capturing Laravel runtime snapshot',
        'percent' => 20,
    ]);

    expect($line)->toEndWith("\n");

    $decoded = FrameCodec::decode($line);

    expect($decoded)->toBe([
        'type' => 'task.progress',
        'taskId' => 'task-1',
        'message' => 'Capturing Laravel runtime snapshot',
        'percent' => 20,
    ]);
});

it('wraps malformed json input in a codec runtime exception', function (): void {
    expect(fn (): array => FrameCodec::decode("{bad json}\n"))
        ->toThrow(\RuntimeException::class, 'Failed to decode runtime frame.');
});

it('rejects scalar json input with a codec runtime exception', function (): void {
    expect(fn (): array => FrameCodec::decode("123\n"))
        ->toThrow(\RuntimeException::class, 'Failed to decode runtime frame.');
});
