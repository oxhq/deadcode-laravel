<?php

declare(strict_types=1);

namespace Deadcode\Runtime\Protocol;

use JsonException;
use RuntimeException;

final class FrameCodec
{
    public static function encode(array $frame): string
    {
        try {
            return json_encode($frame, JSON_THROW_ON_ERROR) . "\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode runtime frame.', 0, $exception);
        }
    }

    public static function decode(string $line): array
    {
        try {
            return json_decode(trim($line), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to decode runtime frame.', 0, $exception);
        }
    }
}
