# Product Roadmap & Future Milestones — Antevemus ALinq Collection

This document outlines the engineering roadmap of the **Antevemus ALinq Collection** library: what shipped, what is scheduled, and what is only promised. The checklist at the end of the [README](README.md#-roadmap--ecosystem) is the short form of this page.

---

## ✅ Completed Milestones

### 1. Stable Core: 8 Functional Traits on PHP 8.4 🧱 (Shipped in v1.0.0)
- **Description:** `ALinqCollection` with the eight operation families (filtering, selection, ordering, grouping, joining & set theory, aggregation, iteration, utility), the query builder and the property accessor, on native PHP 8.4 array primitives with zero runtime dependencies.
- **Goal:** LINQ ergonomics over plain PHP arrays, with the ASpecification synergy (`ALinqBridge`) from day one.

### 2. Lazy Streaming Pipeline & O(1) RAM Evaluation 🌊 (Shipped in v1.1.0)
- **Description:** `ALinqLazyCollection`, a generator-based deferred pipeline with `fromFile()`, `fromCsv()`, `fromCursor()`, `remember()` and the streaming operators.
- **Goal:** Multi-gigabyte files, CSV streams and unbuffered database cursors with a constant memory footprint for the streaming operators (measured: 632 bytes of growth over a 73 MB file).

### 3. Streaming Engine Correctness 🔧 (Shipped in v1.1.1 and v1.1.2)
- **Description:** `remember()` caches only complete passes and is resumable, `fromFile()` keeps long lines whole, single-pass cursors fail loudly on re-traversal, lazy materializers never lose items (list reindexed, dictionary preserved), `last()` answers with the real key.
- **Goal:** No silent data loss anywhere in the lazy path.

### 4. README Promises I: Every Documented Example Runs 📘 (Shipped in v1.2.0)
- **Description:** The 2026-10 line-by-line README audit executed all fifteen code blocks and found five that did not run. `groupBy()` groups became `ALinqCollection` instances, `select()` receives the key, every operator accepts native callables through one arity rule (`ALinqCallable::withKey()`), `distinct()` compares by strict typed identity, `selectMany()` flattens any iterable, the query builder gained `between`/`notBetween`/`notIn`/`isNull`/`isNotNull`, and `ALinqPropertyAccess` resolves getters and dot-notation in the same order as the ASpecification `PropertyAccessor`.
- **Goal:** No README promise without code behind it; the README block is the test.

### 5. The Contract: Eager × Lazy Parity 📜 (Shipped in v1.3.0)
- **Description:** One written contract (README §11) for both collections, enforced by `tests/Contract/ParityTest.php`: a list is reindexed and a dictionary keeps its keys on both sides; set operations and joins use strict typed identity and a `null` key never matches; empty collections throw as in LINQ; invalid arguments and group keys throw `InvalidArgumentException`; numeric aggregations ignore `null`; `concat()` is a sequence; `chunk()` returns collections; `count($predicate)` and `JsonSerializable` on both collections; query-builder `null` semantics and `toPredicate()` snapshot; `each()` never mutates; `extract()` deprecated. Specified before code (Reversa forward 015, decisions D1 to D12).
- **Goal:** The same question gets the same answer, with the same keys and the same exception, on the eager and on the lazy path. The review measured 114 divergences in 350 comparisons on 1.1.1; the parity test allows zero.

### 6. Process Hygiene 🧹 (Shipped in v1.3.1)
- **Description:** `declare(strict_types=1)` in every file of `src/`, test coverage of the facades and branches the 2026-10 review found untested, a `phpunit.xml.dist` without a phantom suite or logging that writes files on every run, the reciprocal `suggest` of `antevemus/aspecification`, no residual Portuguese, copyright years, and the release contract (`docs/RELEASING.md`) for the two-repository model where every public tag lives on the `release` line.
- **Goal:** The repository states what it does (coverage, counts, versions) and the release rite is written down.

### 7. Lazy Sources Hardening 🛡️ (Shipped in v1.3.2)
- **Description:** `fromCsv()` records whose width differs from the header throw (`strict: false` keeps the 1.3.1 list), blank lines are skipped, the UTF-8 BOM is consumed before parsing in `fromCsv()` and `fromFile()`, a directory passed to `fromFile()`/`fromCsv()` throws, and one `PDOStatement` is bound to one lazy collection so two collections never share a cursor. Specified before code (Reversa forward 016).
- **Goal:** Irregular input fails loudly or is an explicit choice, never silently reshaped.

### 8. Announced-but-Unshipped Backlog (CHANGELOG erratum, 2026-10) 📋 (Shipped in v1.4.0)
- **Description:** `whereIn()`, `whereNotIn()`, `whereBetween()` and `single()` had been announced in the 0.1.0 release notes and never existed (see the erratum in the CHANGELOG). All four shipped on both collections: `single()` is the LINQ `Single` (exactly one element, or `UnderflowException`/`OverflowException` with the same messages on both sides); the `where*` trio became shortcuts in the query builder over `in`/`notIn`/`between` and, on the collections, sugar over `where()` with strict `in`/`notIn` and an inclusive, `null`-never-between `between`. Specified before code (Reversa forward 021).
- **Goal:** Every item of the erratum is either shipped or explicitly declined; none is left as a promise.

### 9. Outer Joins Returning Pairs: `leftJoin()`, `rightJoin()`, `fullJoin()` 🔗 (Shipped in v1.4.0)
- **Description:** The three outer joins of .NET 10/11 LINQ (`Enumerable.LeftJoin`, `RightJoin`, `FullJoin`) shipped on both collections: the result selector receives `($outer, $inner)` with `null` on the side without a match, and without a selector each result is the pair `[$outer, $inner]`; always a list, with the same strict identity and `null`-key rule as `join()`/`groupJoin()`. `rightJoin()` follows the inner order, as in .NET; `fullJoin()` yields the outer side first, then the unmatched inner items. They replaced the `groupJoin()` plus `selectMany()` written by hand.
- **Goal:** Relational completeness of the joining family with pattern-matching-friendly results.

### 10. Composite Ordering: `thenBy()` / `thenByDescending()` 🪜 (Shipped in v1.4.0)
- **Description:** Stable multi-key ordering chained after `orderBy()`/`orderByDescending()` shipped on both collections (the lazy collection also gained `orderBy()`/`orderByDescending()`), with an optional comparer over the keys and a `LogicException` when no ordering precedes. The README §7 example now reads as it does in LINQ instead of relying on `orderByCustom()`.
- **Goal:** `orderBy(dept)->thenByDescending(salary)` reads as it does in LINQ.

---

## 🔮 Long-Term Vision (v2.0+)

### 11. Shared Property Accessor with ASpecification 🧩
- **Description:** `ALinqPropertyAccess` and the ASpecification `PropertyAccessor` ship the same resolution order (array/`ArrayAccess`, public getter, `__get` guarded by `__isset`, public initialized property, since ALinq 1.4.0 and ASpecification 1.4.4 a non-public property as the last resort, opt-in with `#[Specifiable]` since ALinq 1.4.1 and ASpecification 1.6.1, else `null`) as two copies. Extracting one package both depend on changes the dependency graph of both libraries.
- **Goal:** One accessor, one set of tests (`tests/Contract/AccessorContractTest.php` is the seed).
- **Target Release:** v2.0.0

### 12. Parallel Collection Processing with PHP Fibers ⚡
- **Description:** Optional parallel evaluation of independent pipeline branches on PHP Fibers and concurrent workers, keeping the core synchronous and dependency-free.
- **Target Release:** v2.0.0 (or a companion package)

---

## 🤝 Community & Contributions
Suggestions, feedback and pull requests are welcome. To propose a feature or contribute to any item on this roadmap, open an issue or discussion on [GitHub Issues](https://github.com/antevemus-it/Antevemus.AlinqCollection/issues).
