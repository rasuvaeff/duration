# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

- Check the README examples on every build with `rasuvaeff/doc-exec`: the usage
  block in `README.md` and `README.ru.md` is executed against the real
  `Duration` API, and `composer build` fails if an example stops matching it.
- Migrate the property-based test suite from the frozen `rasuvaeff/property-testing` 2.x to the new `rasuvaeff/property-testing-testo` adapter (drop-in, no PHP code changes; same `#[Property]` attribute and `Gen` API).
- Persist the property regression corpus across coverage CI runs (restore before tests, env `PROPERTY_DB`, save on completion of any non-cancelled run, including failed ones).
- Adopt `rasuvaeff/rector-named-literals` and apply the named-argument rule to literal calls.
- Raise `rasuvaeff/property-testing-testo` to `^0.6`.
- Fix `__toString()`: a value that rounds up into the next unit (e.g. 999.5ms, which `toMillis()` rounds up to 1000) now falls through to that next unit (`"0.9995s"`) instead of printing a misleading whole-number value in the wrong unit (`"1000ms"`). The same class of boundary artifact is now also closed at the s/min and min/h boundaries.
- Fix `__toString()`: day counts of 1,000,000 or more no longer render in scientific notation (`"1.0e+6d"`); they render as a plain integer (`"1000000d"`).
- Fix `plus()`: overflowing `PHP_INT_MAX` microseconds now throws `InvalidArgumentException('Duration overflow: ...')` instead of silently promoting to `float` and letting the constructor throw an unrelated `TypeError`.
- Fix `seconds()`/`minutes()`/`hours()`/`days()`: overflowing `PHP_INT_MAX` microseconds now throws `InvalidArgumentException('Duration overflow: ...')` instead of silently producing a wrong (possibly zero) duration via an out-of-range float-to-int cast.

## 1.0.0 — 2026-06-30

- Initial release: type-safe, immutable, non-negative duration value object (`Stringable`).
- Stored internally as microseconds; factories `zero`/`micros`/`millis`/`seconds`/`minutes`/`hours`/`days`.
- Conversions: `toMicros` (exact), `toMillis` (rounded up via `ceil`), `toSeconds` (`float`), `toMinutes` (`float`).
- Saturating arithmetic `plus`/`minus`; static binary `min`/`max`.
- Comparisons `compareTo`/`equals`/`isGreaterThan`/`isLessThan`/`isZero`/`isPositive`.
- `__toString()` with fixed human-readable format (`µs`/`ms`/`s`/`min`/`h`/`d`, largest fitting unit).
- Property-based tests for algebraic laws (commutativity, associativity, antisymmetry, round-trip, absorption).
