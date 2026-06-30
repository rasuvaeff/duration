# AGENTS.md — duration

Guidance for AI agents working on this package. Read before changing code.

## What this is

`rasuvaeff/duration` is a single immutable value object,
`Rasuvaeff\Duration\Duration` (namespace `Rasuvaeff\Duration`), implementing
`\Stringable`. It models a non-negative time span, stored internally as
microseconds, with named factories (`zero`/`micros`/`millis`/`seconds`/`minutes`/`hours`/`days`),
conversions (`toMicros`/`toMillis`/`toSeconds`/`toMinutes`), saturating arithmetic
(`plus`/`minus`), binary static `min`/`max`, and comparison
(`isZero`/`isPositive`/`equals`/`compareTo`/`isGreaterThan`/`isLessThan`). It is
the foundation for timeout/wait/lease parameters across the resilience packages
and has no runtime dependencies.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **The value contract is the public API: immutable, non-negative, microsecond
   storage.** Construction stays factory-only (private constructor); a negative
   input throws `Duration cannot be negative`; a non-finite float throws
   `Duration must be finite`. Changing the storage unit, the sign rule, or
   `toMillis`'s round-up semantics is a **major** version bump — these are
   observable contracts, not implementation details. The `__toString()` format
   (unit set `µs`/`ms`/`s`/`min`/`h`/`d`, `%g` rounding, `"0"` for zero) is
   likewise an observable contract — changing it is a **major** bump.
4. **Preserve the public contract.** Update README + llms.txt + tests with any
   API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make:

```bash
make build
make cs-fix
make psalm
make test
make test-coverage
make mutation
make release-check
```

`composer.lock` is gitignored (library).
`make test-coverage` and `make mutation` bootstrap `pcov` inside the
`composer:2` container because the base image has no coverage driver.

## Invariants & gotchas

- Storage unit is **microseconds** (`int`). `toMicros()` is exact; `toMillis()`
  uses `ceil` so sub-millisecond spans never floor to `0`; `toSeconds()` and
  `toMinutes()` are `float`. Factories cover `micros` → `days`; `seconds()`/
  `minutes()`/`hours()`/`days()` accept `int|float` and round to the nearest
  microsecond.
- `__toString()` chooses the largest unit with value ≥ 1 (`"0"`, `"1µs"`,
  `"250ms"`, `"2.5s"`, `"1.5min"`, `"2h"`, `"1.5d"`). Milliseconds reuse
  `toMillis()` (rounded up); larger units use `%g`. Keep the branch order
  strictly increasing by unit boundary — boundary cases (exactly 1000µs, 1s, …)
  are what kill mutation survivors.
- All comparisons funnel through `compareTo()` (single `<=>` on micros) — keep
  it that way so `equals`/`isGreaterThan`/`isLessThan` cannot drift. The
  equal-boundary case is what kills mutation survivors; keep three-case tests
  (`<`, `==`, `>`) for every comparison method.
- Mutation runs in CI (`composer mutation`, `minMsi` in `infection.json5`), not
  in the local `composer build` gate — a green local build is not proof MSI holds.
- `minus()` is **saturating** (clamps to zero, never negative) — the correct
  policy for "time remaining" math. Changing it to throw/allow-negative is a
  **major** bump.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment
  (e.g. `actions/checkout@<sha> # v4`). Never revert to floating `@vN` tags.
  Updates go through Dependabot, which bumps the SHA and preserves the comment.
  Workflows also carry `permissions: { contents: read }` at workflow level and
  `persist-credentials: false` on every `actions/checkout` step. Verify with
  `zizmor --persona=auditor .github/` — must report no `unpinned-uses`,
  `excessive-permissions`, or `artipacked` findings.

## When you finish

- Update `README.md` and `llms.txt` (and `examples/` if usage changed); update
  `CHANGELOG.md` when releasing.
- Re-run `composer build`; if the change affects the public API or release
  process, also run `make release-check`. Paste the output.
