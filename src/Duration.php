<?php

declare(strict_types=1);

namespace Rasuvaeff\Duration;

/**
 * Type-safe, immutable, non-negative duration.
 *
 * Stored internally as microseconds — the natural sub-second unit in PHP
 * (`usleep`, `microtime`, stream timeouts). Construct through the named
 * factories; never instantiate directly.
 *
 * @api
 */
final readonly class Duration implements \Stringable
{
    private const int MICROS_PER_MILLI = 1_000;
    private const int MICROS_PER_SECOND = 1_000_000;
    private const int MICROS_PER_MINUTE = 60_000_000;
    private const int MICROS_PER_HOUR = 3_600_000_000;
    private const int MICROS_PER_DAY = 86_400_000_000;

    private function __construct(
        private int $micros,
    ) {
        if ($micros < 0) {
            throw new \InvalidArgumentException('Duration cannot be negative');
        }
    }

    public static function zero(): self
    {
        return new self(micros: 0);
    }

    public static function micros(int $micros): self
    {
        return new self(micros: $micros);
    }

    public static function millis(int $millis): self
    {
        return new self(micros: $millis * self::MICROS_PER_MILLI);
    }

    public static function seconds(int|float $seconds): self
    {
        return self::fromUnit(value: $seconds, microsPerUnit: self::MICROS_PER_SECOND);
    }

    public static function minutes(int|float $minutes): self
    {
        return self::fromUnit(value: $minutes, microsPerUnit: self::MICROS_PER_MINUTE);
    }

    public static function hours(int|float $hours): self
    {
        return self::fromUnit(value: $hours, microsPerUnit: self::MICROS_PER_HOUR);
    }

    public static function days(int|float $days): self
    {
        return self::fromUnit(value: $days, microsPerUnit: self::MICROS_PER_DAY);
    }

    public function toMicros(): int
    {
        return $this->micros;
    }

    /**
     * Whole milliseconds, rounded up: a non-zero sub-millisecond duration never
     * collapses to 0 (0ms means "no timeout" to most clients).
     */
    public function toMillis(): int
    {
        return (int) ceil($this->micros / self::MICROS_PER_MILLI);
    }

    public function toSeconds(): float
    {
        return $this->micros / self::MICROS_PER_SECOND;
    }

    public function toMinutes(): float
    {
        return $this->micros / self::MICROS_PER_MINUTE;
    }

    public function plus(self $other): self
    {
        return new self(micros: $this->micros + $other->micros);
    }

    /**
     * Saturating subtraction: subtracting a longer duration yields zero, never a
     * negative value. Correct for "time remaining" math (a passed deadline is 0).
     */
    public function minus(self $other): self
    {
        return new self(micros: max(0, $this->micros - $other->micros));
    }

    /**
     * The smaller of two durations.
     */
    public static function min(self $a, self $b): self
    {
        return $a->micros <= $b->micros ? $a : $b;
    }

    /**
     * The larger of two durations.
     */
    public static function max(self $a, self $b): self
    {
        return $a->micros >= $b->micros ? $a : $b;
    }

    public function isZero(): bool
    {
        return $this->micros === 0;
    }

    public function isPositive(): bool
    {
        return $this->micros > 0;
    }

    public function equals(self $other): bool
    {
        return $this->compareTo(other: $other) === 0;
    }

    /**
     * @return int<-1, 1> Negative when shorter, zero when equal, positive when longer.
     */
    public function compareTo(self $other): int
    {
        return $this->micros <=> $other->micros;
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->compareTo(other: $other) > 0;
    }

    public function isLessThan(self $other): bool
    {
        return $this->compareTo(other: $other) < 0;
    }

    /**
     * Human-readable representation, choosing the largest unit with a value of
     * at least 1: `"2.5s"`, `"250ms"`, `"1µs"`, `"90min"`, `"2h"`, `"1.5d"`;
     * `"0"` for the zero duration. Microseconds and milliseconds are integers
     * (milliseconds follow `toMillis()`, i.e. rounded up); larger units use the
     * `%g` general format (trailing zeros trimmed).
     *
     * The unit set, the rounding, and the suffix spelling are an observable
     * contract — changing them is a major version bump.
     */
    #[\Override]
    public function __toString(): string
    {
        if ($this->micros === 0) {
            return '0';
        }

        if ($this->micros < self::MICROS_PER_MILLI) {
            return $this->micros . 'µs';
        }

        if ($this->micros < self::MICROS_PER_SECOND) {
            return $this->toMillis() . 'ms';
        }

        if ($this->micros < self::MICROS_PER_MINUTE) {
            return \sprintf('%g', $this->toSeconds()) . 's';
        }

        if ($this->micros < self::MICROS_PER_HOUR) {
            return \sprintf('%g', $this->toMinutes()) . 'min';
        }

        if ($this->micros < self::MICROS_PER_DAY) {
            return \sprintf('%g', $this->toHours()) . 'h';
        }

        return \sprintf('%g', $this->toDays()) . 'd';
    }

    private function toHours(): float
    {
        return $this->micros / self::MICROS_PER_HOUR;
    }

    private function toDays(): float
    {
        return $this->micros / self::MICROS_PER_DAY;
    }

    private static function fromUnit(int|float $value, int $microsPerUnit): self
    {
        if (is_float($value) && !is_finite($value)) {
            throw new \InvalidArgumentException('Duration must be finite');
        }

        return new self(micros: (int) round((float) $value * (float) $microsPerUnit));
    }
}
