<?php

declare(strict_types=1);

namespace Rasuvaeff\Duration\Tests;

use Rasuvaeff\Duration\Duration;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(Duration::class)]
final class DurationTest
{
    private const string TO_STRING_PATTERN = '/^(0|\d+µs|\d+ms|\d+(\.\d+)?(s|min|h|d))$/';

    #[DataProvider('factoryMicrosProvider')]
    public function factoriesStoreExpectedMicros(Duration $duration, int $expectedMicros): void
    {
        Assert::same($duration->toMicros(), $expectedMicros);
    }

    public static function factoryMicrosProvider(): iterable
    {
        yield 'zero' => [Duration::zero(), 0];
        yield 'micros' => [Duration::micros(123), 123];
        yield 'millis' => [Duration::millis(2), 2_000];
        yield 'seconds int' => [Duration::seconds(3), 3_000_000];
        yield 'seconds float' => [Duration::seconds(1.5), 1_500_000];
        yield 'minutes int' => [Duration::minutes(2), 120_000_000];
        yield 'minutes float' => [Duration::minutes(0.5), 30_000_000];
        yield 'hours int' => [Duration::hours(1), 3_600_000_000];
        yield 'hours float' => [Duration::hours(1.5), 5_400_000_000];
        yield 'days int' => [Duration::days(1), 86_400_000_000];
        yield 'days float' => [Duration::days(0.5), 43_200_000_000];
    }

    public function zeroEqualsMicrosZero(): void
    {
        Assert::true(Duration::zero()->equals(Duration::micros(0)));
    }

    #[DataProvider('toMillisProvider')]
    public function toMillisRoundsUp(Duration $duration, int $expectedMillis): void
    {
        Assert::same($duration->toMillis(), $expectedMillis);
    }

    public static function toMillisProvider(): iterable
    {
        yield 'exact' => [Duration::millis(250), 250];
        yield 'sub-millisecond rounds up' => [Duration::micros(500), 1];
        yield 'fraction below half still rounds up' => [Duration::micros(1_200), 2];
        yield 'fraction above half rounds up' => [Duration::micros(1_800), 2];
        yield 'zero stays zero' => [Duration::zero(), 0];
        // Above 2^53 µs float division loses integer precision: the old
        // (int) ceil($micros / 1000) returned 9007199254741 here - a
        // round-DOWN, violating the exact-ceil contract.
        yield 'first value past 2^53 that float division got wrong' => [Duration::micros(9_007_199_254_741_001), 9_007_199_254_742];
        yield 'PHP_INT_MAX' => [Duration::micros(\PHP_INT_MAX), intdiv(\PHP_INT_MAX, 1_000) + 1];
        yield 'exact multiple of 1000 above 2^53 must not round up' => [Duration::micros(9_196_105_871_194_011_000), 9_196_105_871_194_011];
    }

    /**
     * The exact-ceil contract over the full int range, checked against pure
     * integer arithmetic - float division diverges from it above 2^53 µs.
     */
    #[Property(runs: 300)]
    public function toMillisMatchesIntegerCeilOverTheFullRange(int $micros): void
    {
        $expected = intdiv($micros, 1_000) + ($micros % 1_000 !== 0 ? 1 : 0);

        Assert::same(Duration::micros($micros)->toMillis(), $expected);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function toMillisMatchesIntegerCeilOverTheFullRangeGenerators(): array
    {
        return ['micros' => Gen::intBetween(0, \PHP_INT_MAX)];
    }

    /** @return iterable<string, array{int}> */
    public static function toMillisMatchesIntegerCeilOverTheFullRangeExamples(): iterable
    {
        yield 'first wrong value of the old float implementation' => [9_007_199_254_741_001];
        yield 'PHP_INT_MAX' => [\PHP_INT_MAX];
        yield 'exactly 2^53' => [9_007_199_254_740_992];
    }

    public function millisRejectsOverflowInsteadOfSilentlyWrapping(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Duration overflow');

        Duration::millis(intdiv(\PHP_INT_MAX, 1_000) + 1);
    }

    public function millisAtTheOverflowBoundaryStillSucceeds(): void
    {
        Assert::same(Duration::millis(intdiv(\PHP_INT_MAX, 1_000))->toMicros(), intdiv(\PHP_INT_MAX, 1_000) * 1_000);
    }

    /**
     * Integer inputs must stay in integer arithmetic end to end: the old
     * float path lost precision once the product exceeded 2^53 (e.g.
     * seconds(9_223_372_036_853) came out 192 µs off), and PHP >= 8.4's
     * round() regression additionally corrupted integer-valued floats in
     * [2^52, 2^53) by +1 (days(86165) gained a microsecond on 8.4/8.5).
     */
    #[Property(runs: 200)]
    public function intFactoriesAreExact(int $seconds): void
    {
        Assert::same(Duration::seconds($seconds)->toMicros(), $seconds * 1_000_000);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function intFactoriesAreExactGenerators(): array
    {
        return ['seconds' => Gen::intBetween(0, intdiv(\PHP_INT_MAX, 1_000_000))];
    }

    /** @return iterable<string, array{int}> */
    public static function intFactoriesAreExactExamples(): iterable
    {
        yield 'product 192 micros off on the old float path' => [9_223_372_036_853];
        yield 'round() regression territory' => [4_600_000_000];
    }

    #[DataProvider('intFactoryExactnessProvider')]
    public function intFactoryProductsAreExactInEveryUnit(Duration $duration, int $expectedMicros): void
    {
        Assert::same($duration->toMicros(), $expectedMicros);
    }

    public static function intFactoryExactnessProvider(): iterable
    {
        yield 'days(86165): +1 microsecond on PHP >= 8.4 via the old round()' => [Duration::days(86_165), 7_444_656_000_000_000];
        yield 'hours(1390967)' => [Duration::hours(1_390_967), 5_007_481_200_000_000];
        yield 'minutes at the precision edge' => [Duration::minutes(153_722_867_280), 9_223_372_036_800_000_000];
    }

    /**
     * A float input whose product lands in [2^52, 2^53) is already integral
     * (double spacing there is exactly 1.0) - round() must be skipped there,
     * both as a no-op and to sidestep the PHP >= 8.4 regression that returns
     * n+1 for even integer-valued floats in that range.
     */
    public function floatInputInTheRoundRegressionRangeStaysExact(): void
    {
        Assert::same(Duration::days(52_125.0)->toMicros(), 4_503_600_000_000_000);
    }

    #[DataProvider('halfAwayRoundingProvider')]
    public function floatFactoriesRoundHalfAwayFromZero(float $seconds, int $expectedMicros): void
    {
        Assert::same(Duration::seconds($seconds)->toMicros(), $expectedMicros);
    }

    public static function halfAwayRoundingProvider(): iterable
    {
        yield 'x.5 rounds away from zero' => [0.000_002_5, 3];
        yield 'x.5 below rounds up too' => [0.000_001_5, 2];
    }

    #[DataProvider('subMicroRoundingProvider')]
    public function secondsRoundToNearestMicro(float $seconds, int $expectedMicros): void
    {
        Assert::same(Duration::seconds($seconds)->toMicros(), $expectedMicros);
    }

    public static function subMicroRoundingProvider(): iterable
    {
        yield 'sub-micro fraction below half rounds down' => [0.000_000_2, 0];
        yield 'sub-micro fraction above half rounds up' => [0.000_000_8, 1];
    }

    #[DataProvider('toSecondsProvider')]
    public function toSecondsConverts(Duration $duration, float $expectedSeconds): void
    {
        Assert::same($duration->toSeconds(), $expectedSeconds);
    }

    public static function toSecondsProvider(): iterable
    {
        yield 'whole' => [Duration::seconds(2), 2.0];
        yield 'fractional' => [Duration::micros(500_000), 0.5];
        yield 'zero' => [Duration::zero(), 0.0];
    }

    #[DataProvider('toMinutesProvider')]
    public function toMinutesConverts(Duration $duration, float $expectedMinutes): void
    {
        Assert::same($duration->toMinutes(), $expectedMinutes);
    }

    public static function toMinutesProvider(): iterable
    {
        yield 'whole' => [Duration::minutes(2), 2.0];
        yield 'fractional' => [Duration::seconds(30), 0.5];
        yield 'zero' => [Duration::zero(), 0.0];
    }

    public function plusAddsMicros(): void
    {
        $sum = Duration::millis(100)->plus(Duration::millis(50));

        Assert::same($sum->toMicros(), 150_000);
    }

    public function plusIsCommutativeOnMicros(): void
    {
        $a = Duration::seconds(1)->plus(Duration::millis(250));
        $b = Duration::millis(250)->plus(Duration::seconds(1));

        Assert::true($a->equals($b));
    }

    public function minusSubtractsMicros(): void
    {
        $diff = Duration::seconds(5)->minus(Duration::seconds(2));

        Assert::same($diff->toMicros(), 3_000_000);
    }

    public function minusSaturatesAtZeroWhenLonger(): void
    {
        Assert::true(Duration::seconds(2)->minus(Duration::seconds(5))->isZero());
    }

    public function minusEqualYieldsZero(): void
    {
        Assert::true(Duration::seconds(5)->minus(Duration::seconds(5))->isZero());
    }

    public function minReturnsSmaller(): void
    {
        Assert::true(Duration::min(Duration::seconds(1), Duration::seconds(2))->equals(Duration::seconds(1)));
    }

    public function maxReturnsLarger(): void
    {
        Assert::true(Duration::max(Duration::seconds(1), Duration::seconds(2))->equals(Duration::seconds(2)));
    }

    public function minOnEqualReturnsEqualValue(): void
    {
        Assert::true(Duration::min(Duration::seconds(2), Duration::millis(2_000))->equals(Duration::seconds(2)));
    }

    public function maxOnEqualReturnsEqualValue(): void
    {
        Assert::true(Duration::max(Duration::seconds(2), Duration::millis(2_000))->equals(Duration::seconds(2)));
    }

    public function isZeroTrueForZero(): void
    {
        Assert::true(Duration::zero()->isZero());
    }

    public function isZeroFalseForNonZero(): void
    {
        Assert::false(Duration::micros(1)->isZero());
    }

    public function isPositiveTrueForNonZero(): void
    {
        Assert::true(Duration::micros(1)->isPositive());
    }

    public function isPositiveFalseForZero(): void
    {
        Assert::false(Duration::zero()->isPositive());
    }

    #[DataProvider('compareProvider')]
    public function compareToReturnsSign(Duration $left, Duration $right, int $expectedSign): void
    {
        Assert::same($left->compareTo($right), $expectedSign);
    }

    public static function compareProvider(): iterable
    {
        yield 'less' => [Duration::millis(1), Duration::millis(2), -1];
        yield 'equal' => [Duration::millis(2), Duration::millis(2), 0];
        yield 'greater' => [Duration::millis(3), Duration::millis(2), 1];
    }

    #[DataProvider('equalsProvider')]
    public function equalsComparesMicros(Duration $left, Duration $right, bool $expected): void
    {
        Assert::same($left->equals($right), $expected);
    }

    public static function equalsProvider(): iterable
    {
        yield 'equal' => [Duration::seconds(1), Duration::millis(1_000), true];
        yield 'shorter' => [Duration::millis(999), Duration::seconds(1), false];
        yield 'longer' => [Duration::millis(1_001), Duration::seconds(1), false];
    }

    #[DataProvider('greaterThanProvider')]
    public function isGreaterThanComparesMicros(Duration $left, Duration $right, bool $expected): void
    {
        Assert::same($left->isGreaterThan($right), $expected);
    }

    public static function greaterThanProvider(): iterable
    {
        yield 'greater' => [Duration::millis(3), Duration::millis(2), true];
        yield 'equal' => [Duration::millis(2), Duration::millis(2), false];
        yield 'less' => [Duration::millis(1), Duration::millis(2), false];
    }

    #[DataProvider('lessThanProvider')]
    public function isLessThanComparesMicros(Duration $left, Duration $right, bool $expected): void
    {
        Assert::same($left->isLessThan($right), $expected);
    }

    public static function lessThanProvider(): iterable
    {
        yield 'less' => [Duration::millis(1), Duration::millis(2), true];
        yield 'equal' => [Duration::millis(2), Duration::millis(2), false];
        yield 'greater' => [Duration::millis(3), Duration::millis(2), false];
    }

    #[DataProvider('toStringProvider')]
    public function toStringFormatsUnit(Duration $duration, string $expected): void
    {
        Assert::same((string) $duration, $expected);
    }

    public static function toStringProvider(): iterable
    {
        yield 'zero' => [Duration::zero(), '0'];
        yield 'micros' => [Duration::micros(500), '500µs'];
        yield 'millis exact' => [Duration::millis(250), '250ms'];
        yield 'millis boundary (1000µs)' => [Duration::micros(1_000), '1ms'];
        yield 'seconds' => [Duration::seconds(2.5), '2.5s'];
        yield 'seconds whole drops trailing zero' => [Duration::seconds(2), '2s'];
        yield 'seconds boundary (1s)' => [Duration::micros(1_000_000), '1s'];
        yield 'minutes' => [Duration::minutes(1.5), '1.5min'];
        yield 'minutes boundary (1min)' => [Duration::micros(60_000_000), '1min'];
        yield 'hours' => [Duration::hours(2), '2h'];
        yield 'hours boundary (1h)' => [Duration::micros(3_600_000_000), '1h'];
        yield 'days' => [Duration::days(1.5), '1.5d'];
        yield 'days boundary (1d)' => [Duration::micros(86_400_000_000), '1d'];
        yield 'ms rounding into the next second falls through to seconds' => [Duration::micros(999_500), '0.9995s'];
        yield 'fall-through can print a sub-1 minute value' => [Duration::micros(59_999_953), '0.999999min'];
        yield 'fall-through can print a sub-1 day value' => [Duration::micros(86_399_928_000), '0.999999d'];
        yield 'seconds rounding into the next minute falls through to minutes' => [Duration::micros(59_999_999), '1min'];
        yield 'minutes rounding into the next hour falls through to hours' => [Duration::micros(3_599_999_940), '1h'];
        yield 'hours rounding into the next day falls through to days' => [Duration::micros(86_399_996_400), '1d'];
        yield 'day count beyond %g precision renders as a plain integer, not scientific notation' => [Duration::days(1_000_000), '1000000d'];
        yield 'fractional day count beyond %g precision also avoids scientific notation' => [Duration::days(1_234_567), '1234567d'];
    }

    public function rejectsNegativeMicros(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Duration cannot be negative');

        Duration::micros(-1);
    }

    public function rejectsNegativeSeconds(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Duration cannot be negative');

        Duration::seconds(-2.5);
    }

    public function rejectsInfiniteSeconds(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Duration must be finite');

        Duration::seconds(INF);
    }

    public function rejectsNanMinutes(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Duration must be finite');

        Duration::minutes(NAN);
    }

    public function plusRejectsOverflowInsteadOfSilentlyWrapping(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Duration overflow');

        Duration::micros(\PHP_INT_MAX - 10)->plus(Duration::micros(20));
    }

    public function plusAtTheOverflowBoundaryStillSucceeds(): void
    {
        $sum = Duration::micros(\PHP_INT_MAX - 10)->plus(Duration::micros(10));

        Assert::same($sum->toMicros(), \PHP_INT_MAX);
    }

    public function fromUnitRejectsOverflowInsteadOfSilentlyTruncating(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Duration overflow');

        Duration::days(\PHP_INT_MAX);
    }

    /**
     * `(float) PHP_INT_MAX` has no exact double representation and rounds up
     * to 2^63 — one past the true maximum. A value whose computed micros
     * lands exactly on that rounded boundary must still be rejected (a
     * strict `>` against `(float) PHP_INT_MAX` would let it through, and the
     * subsequent `(int)` cast of 2^63 silently wraps to PHP_INT_MIN).
     */
    public function fromUnitRejectsExactlyAtTheFloatRoundedUpperBoundary(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Duration overflow');

        Duration::seconds(9_223_372_036_854.775390625);
    }

    /**
     * One representable double below the rounded boundary above: this is
     * the largest value fromUnit() can actually produce and still be valid,
     * and it must succeed rather than being caught by an overly eager check.
     */
    public function fromUnitAcceptsTheLargestValueJustBelowTheBoundary(): void
    {
        $duration = Duration::seconds(9_223_372_036_854.7734375);

        Assert::same($duration->toMicros(), 9_223_372_036_854_773_760);
    }

    /**
     * PHP_INT_MIN (-2^63) *is* exactly representable as a double, so a value
     * that computes to exactly PHP_INT_MIN micros must pass the overflow
     * check (it is not less than the boundary) and instead be rejected by
     * the ordinary negative-value check in the constructor — proving the
     * lower-bound comparison is a strict `<`, not `<=`.
     */
    public function fromUnitAtExactlyPhpIntMinFailsOnNegativityNotOverflow(): void
    {
        Expect::exception(\InvalidArgumentException::class)->withMessageContaining('Duration cannot be negative');

        Duration::seconds(\PHP_INT_MIN / 1_000_000);
    }

    #[Property(runs: 200)]
    public function microsRoundTrip(int $micros): void
    {
        Assert::same(Duration::micros($micros)->toMicros(), $micros);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function microsRoundTripGenerators(): array
    {
        return ['micros' => Gen::intBetween(0, \PHP_INT_MAX)];
    }

    #[Property(runs: 200)]
    public function millisRoundTrip(int $millis): void
    {
        Assert::same(Duration::millis($millis)->toMillis(), $millis);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function millisRoundTripGenerators(): array
    {
        // The full valid range: the old cap of 1e6 was 9000x below the zone
        // where the float-based toMillis() lost precision, so the round-trip
        // could never falsify that bug.
        return ['millis' => Gen::intBetween(0, intdiv(\PHP_INT_MAX, 1_000))];
    }

    #[Property]
    public function plusIsCommutative(int $a, int $b): void
    {
        $left = Duration::micros($a)->plus(Duration::micros($b));
        $right = Duration::micros($b)->plus(Duration::micros($a));

        Assert::true($left->equals($right));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function plusIsCommutativeGenerators(): array
    {
        return [
            'a' => Gen::intBetween(0, 100_000_000),
            'b' => Gen::intBetween(0, 100_000_000),
        ];
    }

    #[Property]
    public function plusIsAssociative(int $a, int $b, int $c): void
    {
        $left = Duration::micros($a)->plus(Duration::micros($b))->plus(Duration::micros($c));
        $right = Duration::micros($a)->plus(Duration::micros($b)->plus(Duration::micros($c)));

        Assert::true($left->equals($right));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function plusIsAssociativeGenerators(): array
    {
        return [
            'a' => Gen::intBetween(0, 100_000_000),
            'b' => Gen::intBetween(0, 100_000_000),
            'c' => Gen::intBetween(0, 100_000_000),
        ];
    }

    #[Property]
    public function plusThenMinusRoundTrips(int $a, int $b): void
    {
        $result = Duration::micros($a)->plus(Duration::micros($b))->minus(Duration::micros($b));

        Assert::same($result->toMicros(), $a);
    }

    /** @return iterable<string, array{int, int}> */
    public static function plusThenMinusRoundTripsExamples(): iterable
    {
        yield 'zero and zero' => [0, 0];
        yield 'subtracting nothing' => [1, 0];
        yield 'adding nothing' => [0, 1];
        yield 'a single microsecond each way' => [1, 1];
    }

    #[Property(runs: 300)]
    public function minusSaturatesAtZeroInsteadOfGoingNegative(int $a, int $b): void
    {
        $result = Duration::micros($a)->minus(Duration::micros($b));

        // The saturating branch is the whole reason minus() is not plain
        // subtraction — a Duration cannot be negative, and the constructor
        // throws rather than wrapping. Both sides have to be drawn or the
        // property is about subtraction, not about saturation.
        Classify::cover($b > $a, 'saturates at zero', 30.0);
        Classify::cover($b <= $a, 'ordinary subtraction', 30.0);
        Classify::when($a === $b, 'exactly zero by subtraction');

        Assert::same($result->toMicros(), max(0, $a - $b));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function minusSaturatesAtZeroInsteadOfGoingNegativeGenerators(): array
    {
        // A shared range so both orderings are equally likely; two independent
        // ranges would decide the split by how they happen to overlap.
        return [
            'a' => Gen::intBetween(0, 1_000_000),
            'b' => Gen::intBetween(0, 1_000_000),
        ];
    }

    /** @return iterable<string, array{int, int}> */
    public static function minusSaturatesAtZeroInsteadOfGoingNegativeExamples(): iterable
    {
        yield 'equal operands' => [1_000, 1_000];
        yield 'one microsecond short' => [999, 1_000];
        yield 'one microsecond over' => [1_001, 1_000];
        yield 'everything from nothing' => [0, 1_000_000];
    }

    /** @return array<string, ArbitraryInterface> */
    public static function plusThenMinusRoundTripsGenerators(): array
    {
        return [
            'a' => Gen::intBetween(0, 100_000_000),
            'b' => Gen::intBetween(0, 100_000_000),
        ];
    }

    #[Property]
    public function compareToIsAntisymmetric(int $a, int $b): void
    {
        $ab = Duration::micros($a)->compareTo(Duration::micros($b));
        $ba = Duration::micros($b)->compareTo(Duration::micros($a));

        Assert::same($ab, -$ba);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function compareToIsAntisymmetricGenerators(): array
    {
        return [
            'a' => Gen::intBetween(0, 1_000_000_000),
            'b' => Gen::intBetween(0, 1_000_000_000),
        ];
    }

    #[Property]
    public function minIsCommutative(int $a, int $b): void
    {
        Assert::true(
            Duration::min(Duration::micros($a), Duration::micros($b))
                ->equals(Duration::min(Duration::micros($b), Duration::micros($a))),
        );
    }

    /** @return array<string, ArbitraryInterface> */
    public static function minIsCommutativeGenerators(): array
    {
        return [
            'a' => Gen::intBetween(0, 100_000_000),
            'b' => Gen::intBetween(0, 100_000_000),
        ];
    }

    #[Property]
    public function maxIsCommutative(int $a, int $b): void
    {
        Assert::true(
            Duration::max(Duration::micros($a), Duration::micros($b))
                ->equals(Duration::max(Duration::micros($b), Duration::micros($a))),
        );
    }

    /** @return array<string, ArbitraryInterface> */
    public static function maxIsCommutativeGenerators(): array
    {
        return [
            'a' => Gen::intBetween(0, 100_000_000),
            'b' => Gen::intBetween(0, 100_000_000),
        ];
    }

    #[Property]
    public function minNeverExceedsEither(int $a, int $b): void
    {
        $min = Duration::min(Duration::micros($a), Duration::micros($b));

        Assert::true($min->compareTo(Duration::micros($a)) <= 0);
        Assert::true($min->compareTo(Duration::micros($b)) <= 0);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function minNeverExceedsEitherGenerators(): array
    {
        return [
            'a' => Gen::intBetween(0, 100_000_000),
            'b' => Gen::intBetween(0, 100_000_000),
        ];
    }

    #[Property]
    public function maxNeverUndershootsEither(int $a, int $b): void
    {
        $max = Duration::max(Duration::micros($a), Duration::micros($b));

        Assert::true($max->compareTo(Duration::micros($a)) >= 0);
        Assert::true($max->compareTo(Duration::micros($b)) >= 0);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function maxNeverUndershootsEitherGenerators(): array
    {
        return [
            'a' => Gen::intBetween(0, 100_000_000),
            'b' => Gen::intBetween(0, 100_000_000),
        ];
    }

    #[Property]
    public function minMaxAbsorption(int $a, int $b): void
    {
        $d = Duration::micros($a);

        Assert::true(Duration::min($d, Duration::max($d, Duration::micros($b)))->equals($d));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function minMaxAbsorptionGenerators(): array
    {
        return [
            'a' => Gen::intBetween(0, 100_000_000),
            'b' => Gen::intBetween(0, 100_000_000),
        ];
    }

    #[Property]
    public function toStringMatchesFormat(int $micros): void
    {
        Assert::true(preg_match(self::TO_STRING_PATTERN, (string) Duration::micros($micros)) === 1);
    }

    /** @return array<string, ArbitraryInterface> */
    public static function toStringMatchesFormatGenerators(): array
    {
        // Upper bound reaches past the 1,000,000-day mark, where %g's default
        // six-significant-digit precision would otherwise fall back to
        // scientific notation (see formatDaysAvoidsScientificNotation below).
        return ['micros' => Gen::intBetween(0, 200_000_000_000_000_000)];
    }

    /** @return iterable<string, array{int}> */
    public static function toStringMatchesFormatExamples(): iterable
    {
        yield 'ms rounds up into the next second' => [999_500];
        yield 'seconds round up into the next minute' => [59_999_999];
        yield 'minutes round up into the next hour' => [3_599_999_940];
        yield 'hours round up into the next day' => [86_399_996_400];
        yield 'day count beyond %g precision' => [1_000_000 * 86_400_000_000];
    }

    #[Property(runs: 50)]
    public function formatDaysAvoidsScientificNotation(int $days): void
    {
        Assert::false(str_contains((string) Duration::days($days), 'e'));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function formatDaysAvoidsScientificNotationGenerators(): array
    {
        // Upper bound stays under PHP_INT_MAX microseconds (~106,751,991 days
        // is the largest representable day count) — this property is about
        // formatting, not about the separate overflow-rejection behavior.
        return ['days' => Gen::intBetween(1_000_000, 100_000_000)];
    }

    /** @return iterable<string, array{int}> */
    public static function formatDaysAvoidsScientificNotationExamples(): iterable
    {
        yield 'exactly at the %g scientific-notation threshold' => [1_000_000];
        yield 'fractional-looking large count' => [1_234_567];
    }
}
