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
        // The same overflow contract as plus()/fromUnit(): the product would
        // silently become a float past PHP_INT_MAX, and the strict-typed
        // constructor would throw an unrelated TypeError instead.
        if ($millis > intdiv(\PHP_INT_MAX, self::MICROS_PER_MILLI)) {
            throw new \InvalidArgumentException('Duration overflow: value exceeds the maximum representable duration');
        }

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
     *
     * Integer arithmetic, not `ceil()` over a float division: above 2^53 µs
     * (~285 years) float division loses integer precision and the "exact
     * ceil" this method promises would be off by ±1 ms.
     */
    public function toMillis(): int
    {
        return intdiv($this->micros, self::MICROS_PER_MILLI)
            + ($this->micros % self::MICROS_PER_MILLI !== 0 ? 1 : 0);
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
     * Native `\Time\Duration` naming for {@see plus()}: the RFC adopted full
     * verb names (`add`, `sub`, `multiplyBy`, `divideBy`). Only the names
     * match. The two classes still differ in sign, precision and factories
     * (see README, "PHP 8.6"), so moving to the native class takes more than
     * a type-hint change.
     */
    public function add(self $other): self
    {
        return $this->plus(other: $other);
    }

    /**
     * Native `\Time\Duration` naming for {@see minus()} and, like it,
     * saturating: a longer `$other` yields zero, while the native `sub()`
     * returns a negative duration.
     */
    public function sub(self $other): self
    {
        return $this->minus(other: $other);
    }

    /**
     * The duration scaled by a non-negative integer factor, matching
     * `\Time\Duration::multiplyBy()`. Zero is allowed: scaling a timeout to
     * zero is a valid "disable" in many configs.
     */
    public function multiplyBy(int $factor): self
    {
        if ($factor < 0) {
            throw new \InvalidArgumentException('Factor must not be negative');
        }

        $scaled = $this->micros * $factor;

        if (!is_int($scaled)) {
            throw new \InvalidArgumentException('Duration overflow: product exceeds the maximum representable duration');
        }

        return new self(micros: $scaled);
    }

    /**
     * The duration divided by a positive integer divisor, matching
     * `\Time\Duration::divideBy()`. Sub-microsecond remainders are truncated
     * (intdiv), mirroring the native class truncating fractional nanoseconds.
     */
    public function divideBy(int $divisor): self
    {
        if ($divisor <= 0) {
            throw new \InvalidArgumentException('Divisor must be positive');
        }

        return new self(micros: intdiv($this->micros, $divisor));
    }

    /**
     * Parse an ISO-8601 duration string with a time component only, matching
     * the native `\Time\Duration::fromIso8601DurationString()` contract: a
     * date component is rejected and the biggest allowed component is `H`.
     *
     * Fractional seconds are accepted with up to six digits (microsecond
     * resolution — the package's storage precision); finer precision is
     * rejected instead of being silently truncated. `"PT0S"` is valid and
     * yields the zero duration.
     */
    public static function fromIso8601DurationString(string $specification): self
    {
        $regex = '/^PT(?:(?<hours>\d+)H)?(?:(?<minutes>\d+)M)?(?:(?<seconds>\d+(?:\.\d{1,6})?)S)?\z/';

        if (preg_match($regex, $specification, $matches) !== 1) {
            throw new \InvalidArgumentException('Invalid ISO-8601 duration: time-only "PT…H/M/S" expected, date components are not supported');
        }

        if (!isset($matches['hours']) && !isset($matches['minutes']) && !isset($matches['seconds'])) {
            throw new \InvalidArgumentException('Invalid ISO-8601 duration: at least one of H, M, S components is required');
        }

        $duration = new self(micros: 0);

        if (isset($matches['hours'])) {
            $duration = $duration->plus(self::fromUnit(value: (int) $matches['hours'], microsPerUnit: self::MICROS_PER_HOUR));
        }

        if (isset($matches['minutes'])) {
            $duration = $duration->plus(self::fromUnit(value: (int) $matches['minutes'], microsPerUnit: self::MICROS_PER_MINUTE));
        }

        if (isset($matches['seconds'])) {
            // Integer arithmetic end to end: a float cast would lose
            // microsecond precision for large values with a fractional part
            // (floats past 2^53 cannot represent every integer). The whole
            // part goes through the overflow-guarded factories; the fraction
            // is at most 6 digits (microsecond resolution) and pads right.
            $parts = explode('.', $matches['seconds']);
            $whole = ltrim($parts[0], '0');
            $fraction = $parts[1] ?? '';

            if (\strlen($whole) > 19 || (\strlen($whole) === 19 && \strcmp($whole, (string) \PHP_INT_MAX) > 0)) {
                throw new \InvalidArgumentException('Duration overflow: seconds component exceeds the maximum representable duration');
            }

            $duration = $duration->plus(self::seconds((int) $whole));

            if ($fraction !== '') {
                $duration = $duration->plus(new self(micros: (int) \str_pad($fraction, 6, '0')));
            }
        }

        return $duration;
    }

    /**
     * Static comparison form for `usort($durations, Duration::compare(...))`,
     * matching `\Time\Duration::compare()` — the instance method
     * {@see compareTo()} stays for fluent comparisons. Delegates to
     * `compareTo()` so there is a single comparison path.
     */
    public static function compare(self $a, self $b): int
    {
        return $a->compareTo(other: $b);
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
     * Human-readable representation: `"2.5s"`, `"250ms"`, `"1µs"`, `"90min"`,
     * `"2h"`, `"1.5d"`; `"0"` for the zero duration. Microseconds and
     * milliseconds are integers (milliseconds follow `toMillis()`, i.e.
     * rounded up); larger units use the `%g` general format (trailing zeros
     * trimmed).
     *
     * The unit is chosen by checking each tier's *rounded* value against the
     * next unit's boundary — not by a "displayed value ≥ 1" rule. A duration
     * that rounds up to a whole unit of the next tier (e.g. 999.5ms, which
     * `toMillis()` rounds up to `1000`) is displayed in that next unit
     * instead, so the printed value never reads as a different order of
     * magnitude than the one actually chosen. The flip side: such fall-through
     * values print as slightly-below-1 amounts of the next unit — `"0.9995s"`,
     * `"0.999999min"`, `"0.999999d"` — by design.
     *
     * The unit set, the rounding, and the suffix spelling are an observable
     * contract — changing them is a major version bump. (One deliberate
     * asymmetry: `%g` rounds half-to-even, while the plain-integer day
     * rendering past `%g`'s precision rounds half-away-from-zero — both ties
     * are astronomically large and the difference is not worth a branch.)
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

        return $this->formatDays($this->toDays());
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
    private function formatDays(float $days): string
    {
        $formatted = \sprintf('%g', $days);

        if (\stripos($formatted, 'e') !== false) {
            return \number_format($days, 0, '.', '') . 'd';
        }

        return $formatted . 'd';
    }

    private static function fromUnit(int|float $value, int $microsPerUnit): self
    {
        // Integer inputs stay in integer arithmetic end to end: routing them
        // through float would lose precision once the product exceeds 2^53
        // (e.g. seconds(9_223_372_036_853) came out 192 µs off), even though
        // the exact result exists and is representable. Overflow detection is
        // the same as plus(): int * int silently widens to float past
        // PHP_INT_MAX.
        if (is_int($value)) {
            $micros = $value * $microsPerUnit;

            if (!is_int($micros)) {
                throw new \InvalidArgumentException('Duration overflow: value exceeds the maximum representable duration');
            }

            return new self(micros: $micros);
        }

        if (!is_finite($value)) {
            throw new \InvalidArgumentException('Duration must be finite');
        }

        $micros = $value * (float) $microsPerUnit;

        // PHP_INT_MAX (2^63 - 1) has no exact double representation and casts
        // to (float) PHP_INT_MAX === 2^63 — one past the real maximum — so a
        // strict `>` here would let exactly 2^63 slip through and silently
        // wrap to PHP_INT_MIN on the `(int)` cast below. `>=` closes that gap.
        // PHP_INT_MIN (-2^63) *is* exactly representable, so `<` is correct
        // as-is on the lower bound.
        if ($micros >= (float) \PHP_INT_MAX || $micros < (float) \PHP_INT_MIN) {
            throw new \InvalidArgumentException('Duration overflow: value exceeds the maximum representable duration');
        }

        // Every double at magnitude >= 2^52 is already integral (the spacing
        // between representable values there is >= 1.0), so round() is a
        // mathematical no-op — and PHP >= 8.4 has a round() regression that
        // returns n+1 for even integer-valued floats in [2^52, 2^53)
        // (round(4503599627370496.0) === 4503599627370497.0), which corrupted
        // e.g. days(86165) by +1 µs. Skip it where it cannot matter. One-sided
        // on purpose: every negative product either rounds within the window
        // or is integral past it, and ends in the constructor's negative-value
        // rejection either way.
        if ($micros < 2 ** 52) {
            $micros = round($micros);
        }

        return new self(micros: (int) $micros);
    }
}
