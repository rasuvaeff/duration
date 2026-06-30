# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 — 2026-06-30

- Initial release: type-safe, immutable, non-negative duration value object (`Stringable`).
- Stored internally as microseconds; factories `zero`/`micros`/`millis`/`seconds`/`minutes`/`hours`/`days`.
- Conversions: `toMicros` (exact), `toMillis` (rounded up via `ceil`), `toSeconds` (`float`), `toMinutes` (`float`).
- Saturating arithmetic `plus`/`minus`; static binary `min`/`max`.
- Comparisons `compareTo`/`equals`/`isGreaterThan`/`isLessThan`/`isZero`/`isPositive`.
- `__toString()` with fixed human-readable format (`µs`/`ms`/`s`/`min`/`h`/`d`, largest fitting unit).
- Property-based tests for algebraic laws (commutativity, associativity, antisymmetry, round-trip, absorption).
