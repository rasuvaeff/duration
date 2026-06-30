<?php

declare(strict_types=1);

namespace Rasuvaeff\Duration\Tests;

use Rasuvaeff\Duration\Duration;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
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

    #[Property(runs: 200)]
    public function microsRoundTrip(int $micros): void
    {
        Assert::same(Duration::micros($micros)->toMicros(), $micros);
    }

    /** @return array<string, ArbitraryInterface> */
    private function microsRoundTripGenerators(): array
    {
        return ['micros' => Gen::intBetween(0, 1_000_000_000)];
    }

    #[Property(runs: 200)]
    public function millisRoundTrip(int $millis): void
    {
        Assert::same(Duration::millis($millis)->toMillis(), $millis);
    }

    /** @return array<string, ArbitraryInterface> */
    private function millisRoundTripGenerators(): array
    {
        return ['millis' => Gen::intBetween(0, 1_000_000)];
    }

    #[Property]
    public function plusIsCommutative(int $a, int $b): void
    {
        $left = Duration::micros($a)->plus(Duration::micros($b));
        $right = Duration::micros($b)->plus(Duration::micros($a));

        Assert::true($left->equals($right));
    }

    /** @return array<string, ArbitraryInterface> */
    private function plusIsCommutativeGenerators(): array
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
    private function plusIsAssociativeGenerators(): array
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

    /** @return array<string, ArbitraryInterface> */
    private function plusThenMinusRoundTripsGenerators(): array
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
    private function compareToIsAntisymmetricGenerators(): array
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
    private function minIsCommutativeGenerators(): array
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
    private function maxIsCommutativeGenerators(): array
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
    private function minNeverExceedsEitherGenerators(): array
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
    private function maxNeverUndershootsEitherGenerators(): array
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
    private function minMaxAbsorptionGenerators(): array
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
    private function toStringMatchesFormatGenerators(): array
    {
        return ['micros' => Gen::intBetween(0, 100_000_000_000)];
    }
}
