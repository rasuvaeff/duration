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
        $sum = $this->micros + $other->micros;

        if (!is_int($sum)) {
            throw new \InvalidArgumentException('Duration overflow: sum exceeds the maximum representable duration');
        }

        return new self(micros: $sum);
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
     * Human-readable representation, choosing the largest unit with a
     * *displayed* value of at least 1: `"2.5s"`, `"250ms"`, `"1µs"`,
     * `"90min"`, `"2h"`, `"1.5d"`; `"0"` for the zero duration. Microseconds
     * and milliseconds are integers (milliseconds follow `toMillis()`, i.e.
     * rounded up); larger units use the `%g` general format (trailing zeros
     * trimmed).
     *
     * Each unit's rounded value is checked against the next unit's boundary:
     * a duration that rounds up to a whole unit of the next tier (e.g.
     * 999.5ms, which `toMillis()` rounds up to `1000`) is displayed in that
     * next unit instead, so the printed value never reads as a different
     * order of magnitude than the one actually chosen.
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

        $millis = $this->toMillis();

        if ($millis < 1_000) {
            return $millis . 'ms';
        }

        $seconds = \sprintf('%g', $this->toSeconds());

        if ((float) $seconds < 60.0) {
            return $seconds . 's';
        }

        $minutes = \sprintf('%g', $this->toMinutes());

        if ((float) $minutes < 60.0) {
            return $minutes . 'min';
        }

        $hours = \sprintf('%g', $this->toHours());

        if ((float) $hours < 24.0) {
            return $hours . 'h';
        }

        return self::formatDays($this->toDays());
    }

    private function toHours(): float
    {
        return $this->micros / self::MICROS_PER_HOUR;
    }

    private function toDays(): float
    {
        return $this->micros / self::MICROS_PER_DAY;
    }

    /**
     * `%g` falls back to scientific notation once the exponent reaches its
     * default precision (six significant digits). At that magnitude the
     * fractional part carries no meaningful information anyway, so the exact
     * whole number of days is rendered instead of a lossy `"1.23457e+6"`.
     */
    private static function formatDays(float $days): string
    {
        $formatted = \sprintf('%g', $days);

        if (\stripos($formatted, 'e') !== false) {
            return \number_format($days, 0, '.', '') . 'd';
        }

        return $formatted . 'd';
    }

    private static function fromUnit(int|float $value, int $microsPerUnit): self
    {
        if (is_float($value) && !is_finite($value)) {
            throw new \InvalidArgumentException('Duration must be finite');
        }

        $micros = round((float) $value * (float) $microsPerUnit);

        // PHP_INT_MAX (2^63 - 1) has no exact double representation and casts
        // to (float) PHP_INT_MAX === 2^63 — one past the real maximum — so a
        // strict `>` here would let exactly 2^63 slip through and silently
        // wrap to PHP_INT_MIN on the `(int)` cast below. `>=` closes that gap.
        // PHP_INT_MIN (-2^63) *is* exactly representable, so `<` is correct
        // as-is on the lower bound.
        if ($micros >= (float) \PHP_INT_MAX || $micros < (float) \PHP_INT_MIN) {
            throw new \InvalidArgumentException('Duration overflow: value exceeds the maximum representable duration');
        }

        return new self(micros: (int) $micros);
    }
}
