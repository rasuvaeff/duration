# rasuvaeff/duration

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/duration/v)](https://packagist.org/packages/rasuvaeff/duration)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/duration/downloads)](https://packagist.org/packages/rasuvaeff/duration)
[![Build](https://github.com/rasuvaeff/duration/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/duration/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/duration/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/duration/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/duration/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/duration/php)](https://packagist.org/packages/rasuvaeff/duration)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)

Type-safe, immutable, non-negative duration value object for PHP. Replaces bare
`int` parameters (seconds? milliseconds?) with an explicit unit, removing a whole
class of "seconds vs milliseconds" bugs. Designed as the foundation for timeout,
wait and lease parameters across the resilience packages.

> Using an AI coding assistant? [llms.txt](llms.txt) contains a compact API reference you can share with the model.

## Requirements

- PHP 8.3+
- No runtime dependencies

## Installation

```bash
composer require rasuvaeff/duration
```

## Usage

```php
use Rasuvaeff\Duration\Duration;

$timeout = Duration::seconds(2.5);

$timeout->toMillis();  // 2500
$timeout->toMicros();  // 2500000
$timeout->toSeconds(); // 2.5

$total = Duration::millis(500)->plus(Duration::seconds(1)); // 1500ms

Duration::seconds(1)->isGreaterThan(Duration::millis(500)); // true

echo Duration::minutes(1.5); // "1.5min"
```

### Factories

| Method | Description |
|---|---|
| `Duration::zero()` | Zero-length duration |
| `Duration::micros(int $micros)` | From microseconds |
| `Duration::millis(int $millis)` | From milliseconds |
| `Duration::seconds(int\|float $seconds)` | From seconds (fractional allowed) |
| `Duration::minutes(int\|float $minutes)` | From minutes (fractional allowed) |
| `Duration::hours(int\|float $hours)` | From hours (fractional allowed) |
| `Duration::days(int\|float $days)` | From days (fractional allowed) |

### Conversions

| Method | Returns | Notes |
|---|---|---|
| `toMicros()` | `int` | Exact — microseconds is the storage unit |
| `toMillis()` | `int` | Rounded **up** (`ceil`) |
| `toSeconds()` | `float` | |
| `toMinutes()` | `float` | |

`toMillis()` rounds up on purpose: a non-zero sub-millisecond duration must
never collapse to `0`, because `0ms` means "no timeout / infinite" to cURL and
most HTTP clients — the exact failure a timeout value is meant to prevent.

### Arithmetic & comparison

| Method | Returns | Description |
|---|---|---|
| `plus(Duration $other)` | `Duration` | Sum of two durations |
| `minus(Duration $other)` | `Duration` | Saturating difference — never negative (a passed deadline is `0`) |
| `Duration::min($a, $b)` | `Duration` | Static — the smaller of two durations |
| `Duration::max($a, $b)` | `Duration` | Static — the larger of two durations |
| `isZero()` | `bool` | True when zero-length |
| `isPositive()` | `bool` | True when non-zero |
| `equals(Duration $other)` | `bool` | Equal length |
| `compareTo(Duration $other)` | `int` | `-1` / `0` / `1` |
| `isGreaterThan(Duration $other)` | `bool` | Strictly longer |
| `isLessThan(Duration $other)` | `bool` | Strictly shorter |

### String representation

`Duration` implements `Stringable`. Casting to string yields a human-readable
form, choosing the largest unit with a value of at least 1:

```
"0"        zero
"1µs"      microseconds (integer)
"250ms"    milliseconds (integer, rounded up — matches toMillis())
"2.5s"     seconds (%g, trailing zeros trimmed)
"1.5min"   minutes
"2h"       hours
"1.5d"     days
```

The unit set, rounding and suffix spelling are an observable contract: changing
them is a **major** version bump. For machine-readable values use `toMicros()` /
`toMillis()` / `toSeconds()` / `toMinutes()` directly.

## Security

Not security-sensitive: a pure, side-effect-free value object. It performs no
I/O and holds no secrets. The only enforced contract is the input domain:

- Negative durations throw `InvalidArgumentException` (`Duration cannot be negative`).
- Non-finite floats (`INF` / `NAN`) throw `InvalidArgumentException` (`Duration must be finite`).

## Examples

See [examples/](examples/) for runnable scripts.
Examples are expected to execute without fatal errors and stay aligned with the
documented public API.

| Script | Shows | Needs server? |
|---|---|---|
| `basic.php` | Factories, conversions, arithmetic, comparison | no |

## Development

No PHP/Composer on the host — run in Docker via the `composer:2` image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make install
make build
make cs-fix
make test
make test-coverage
make mutation
make release-check
```

`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## License

[BSD-3-Clause](LICENSE.md)
