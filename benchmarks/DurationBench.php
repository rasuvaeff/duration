<?php

declare(strict_types=1);

namespace Rasuvaeff\Duration\Benchmarks;

use Rasuvaeff\Duration\Duration;
use Testo\Bench;

final class DurationBench
{
    #[Bench(
        callables: [
            'millis' => [self::class, 'viaMillis'],
        ],
        calls: 1_000_000,
        iterations: 10,
    )]
    public static function viaSeconds(): Duration
    {
        return Duration::seconds(2.5);
    }

    public static function viaMillis(): Duration
    {
        return Duration::millis(2_500);
    }

    #[Bench(
        callables: [
            'toMicros' => [self::class, 'convertToMicros'],
        ],
        calls: 1_000_000,
        iterations: 10,
    )]
    public static function convertToMillis(): int
    {
        return self::sample()->toMillis();
    }

    public static function convertToMicros(): int
    {
        return self::sample()->toMicros();
    }

    private static function sample(): Duration
    {
        return Duration::seconds(1.5);
    }
}
