<?php

declare(strict_types=1);

namespace Oxhq\Oxcribe\Support;

final class PackageVersion
{
    public const TAG = 'v0.1.5';

    public static function label(): string
    {
        return 'deadcode-laravel '.self::TAG;
    }
}
