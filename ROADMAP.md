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
- **Description:** One written contract ([docs/en/api/contract.md](docs/en/api/contract.md)) for both collections, enforced by `tests/Contract/ParityTest.php`: a list is reindexed and a dictionary keeps its keys on both sides; set operations and joins use strict typed identity and a `null` key never matches; empty collections throw as in LINQ; invalid arguments and group keys throw `InvalidArgumentException`; numeric aggregations ignore `null`; `concat()` is a sequence; `chunk()` returns collections; `count($predicate)` and `JsonSerializable` on both collections; query-builder `null` semantics and `toPredicate()` snapshot; `each()` never mutates; `extract()` deprecated. Specified before code (Reversa forward 015, decisions D1 to D12).
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
- **Description:** Stable multi-key ordering chained after `orderBy()`/`orderByDescending()` shipped on both collections (the lazy collection also gained `orderBy()`/`orderByDescending()`), with an optional comparer over the keys and a `LogicException` when no ordering precedes. The example of [docs/en/api/ordering.md](docs/en/api/ordering.md) now reads as it does in LINQ instead of relying on `orderByCustom()`.
- **Goal:** `orderBy(dept)->thenByDescending(salary)` reads as it does in LINQ.

### 12a. Prefetching HTTP Page Sources: `fromHttpPages()` and `fromHttpPagesByIndex()` ⚡ (Shipped in v1.5.0 and v2.4.0)
- **Description:** The core part of milestone 12. `ALinqLazyCollection::fromHttpPages($requestFor, $parsePage, ?HttpPageOptions)` walks a paginated HTTP API (cursor in the body, page number or `Link` header) as one lazy sequence of items; as soon as page *n* is parsed, the request of page *n+1* goes on the wire through `curl_multi` and only then are the items of page *n* handed over. No event loop, no Fiber, no package dependency (`ext-curl` in `suggest`). Errors throw `HttpPageException` at the point of delivery, with the status and the URL without its query string, never the body; stopping early cancels the request in flight; every traversal starts again from the first page; TLS verification cannot be turned off. Measured: 3 pages of 300 ms with a consumer spending 300 ms per page take 1.2 s instead of 1.8 s. Specified before code (Reversa forward 027).
- **Also shipped (v2.4.0):** `fromHttpPagesByIndex()` covers the APIs whose pages are addressable by index without the previous response: the page being consumed and up to `$prefetch` pages after it (2 by default) are in flight on the same `curl_multi` handle, the stream ends on an empty page or on an `$until` callback, and stopping early cancels every request in flight (4 pages of 600 ms with a consumer of 200 ms per page: about 1.4 s with `prefetch: 3`, against 2.6 s one page ahead).
- **Goal:** The `IAsyncEnumerable<T>`-with-prefetch effect in plain PHP-FPM.

### 11. Shared Property Accessor with ASpecification 🧩 (Shipped in v2.0.0)
- **Description:** `ALinqPropertyAccess` and the ASpecification `PropertyAccessor` shipped the same resolution order as two copies (array/`ArrayAccess`, public getter, `__get` guarded by `__isset`, public initialized property, a non-public property as the last resort opt-in with `#[Specifiable]`, else `null`). Both were extracted into one package, [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (zero dependencies, PHP 8.2+), which ALinq 2.0 and ASpecification 2.0 require and call directly. The extraction fixed what both copies got wrong at the edges (a getter with a required parameter threw `ArgumentCountError`; `__call()` and magic names could be reached by name) and settled the cases where they disagreed. `ALinqPropertyAccess` stays until 3.0 as a deprecated facade; `createPropertyComparer()` and `#[Specifiable]` stay in ALinq. Released together with the removal of `extract()` (deprecated since 1.3.0), see [UPGRADE-2.0.md](UPGRADE-2.0.md).
- **Goal:** One accessor, one set of tests: the contract battery lives in the package; `tests/Contract/AccessorContractTest.php` proves that ALinq delegates and keeps no copy.

### 13. Aggregates with a Fallback: `minOrDefault()` and Family 🛟 (Shipped in v2.1.0)
- **Description:** Since 1.3.0 `min()`, `max()`, `average()`, `minBy()` and `maxBy()` throw `UnderflowException` when there is nothing to aggregate, as in LINQ, and callers guarded them with `any() ? min() : null`, which reads the source twice: wasteful on an array, wrong on a single-pass source (`fromCursor()`, `fromHttpPages()`, a generator). `defaultIfEmpty(null)->min()` would not help, because `min()` skips nulls. `minOrDefault()`, `maxOrDefault()`, `averageOrDefault()`, `minByOrDefault()` and `maxByOrDefault()` answer as their sibling and return `$default` only where the sibling throws for want of something to aggregate, in one traversal, on both collections. Asked for by the first production consumer of the library. Specified before code (Reversa forward 036).
- **Goal:** The guard written once, in the library, correct on every source.

### 14. Consumer Gaps: Culture-Aware Comparison, Lookups, Grouping on the Lazy Side 🧭 (Shipped in v2.2.0)
- **Description:** The audit of the first production consumer (2026-10-18) measured eight gaps, all closed on both collections, on implementations shared by the eager and the lazy side (`ALinqContract`): `StringComparer` (`ordinal()`, `ordinalIgnoreCase()`, `culture()` through ICU, with `ext-intl` as a `suggest`) accepted by `orderBy*()`, `distinct*()` (as an `IALinqEqualityComparer`), `min*()`, `max*()`, `minBy*()`, `maxBy*()`, so a list of names with accents sorts as a language does; `toLookup()` returning an immutable `ALinqLookup` (a missing key is an empty collection); `toDictionary(onDuplicate: DuplicateKeyPolicy::KeepFirst|KeepLast)`; `groupBy()`, `countBy()`, `aggregateBy()` as deferred stages on the lazy collection (`countBy()`/`aggregateBy()` keep one value per key, not the items) and `toDictionary()`/`toLookup()` as one-pass terminals; `exceptByKeys()`/`intersectByKeys()`, the `ExceptBy`/`IntersectBy` of .NET 6 (the existing `exceptBy()`/`intersectBy()` keep their MoreLINQ answer, with a written warning); `join()`/`groupJoin()` over any iterable; `fromCsv(separator: null)` detecting `,` `;` tab `|` by count outside quotes; `implode()`. The answers of 2.1.0 do not change; the group-key rule (PHP array keys: `1` and `'1'` are one key) and the position that `aggregateBy()` hands to `$func` are now written in [docs/en/api/contract.md](docs/en/api/contract.md). Mutation testing (Infection) entered the library, scoped to the changed lines. Specified before code (Reversa forward 047).
- **Goal:** The loops a consumer writes by hand (`$m[$k][] = $x`, `$m[$x->id] = $x`, `uasort` with `<=>` on accented names, nested `any()`) have a library call with a written contract, on both collections.

### 12. Concurrent I/O Sources and Parallel Workers ⚡ (Shipped: core in v1.5.0 and v2.4.0; companions `antevemus/alinq-revolt` 1.0.0 and `antevemus/alinq-parallel` 1.0.0)
- **Description:** Corrected in 1.4.3: a PHP Fiber is a cooperative coroutine on a single thread, so it never runs pipeline branches in parallel, and it only saves time when the code inside it waits on non-blocking I/O. The milestone was split by what each tool can actually deliver, and every part shipped:
  - **Prefetching HTTP sources (core, zero dependency):** `fromHttpPages()` in v1.5.0 (prefetch depth 1, because the next request needs the cursor of the previous response) and `fromHttpPagesByIndex()` in v2.4.0 (several pages in flight, for APIs whose pages are addressable by index without the previous response); milestone 12a above.
  - **Async collection for async SDKs (companion package):** [`antevemus/alinq-revolt`](https://github.com/antevemus-it/Antevemus.AlinqRevolt) 1.0.0, `ALinqAsyncCollection` on Fibers and the Revolt event loop (Amp futures), for clients that are already non-blocking (the Amp HTTP client, async SDKs): `selectAsync()`, `whereAsync()`, `selectManyAsync()` and `eachAsync()` with an ordered, bounded window of items in flight, returning core lazy collections. It gains time only while the closures wait on non-blocking I/O: measured there, 16 requests of 200 ms take 214 ms with the Amp client at concurrency 16 and still about 3.2 s with a blocking client. Listed in `suggest` since 2.3.0, which also added the parameter it builds its stages with: `ALinqLazyCollection::from($factory, isList:)` and `isList(): ?bool`.
  - **CPU parallelism (companion package):** [`antevemus/alinq-parallel`](https://github.com/antevemus-it/Antevemus.AlinqParallel) 1.0.0, `ALinqParallelCollection` over a pool of PHP worker processes (`proc_open`, no extension): `selectParallel()`, `whereParallel()`, `selectManyParallel()` and `eachParallel()` with the same ordered, bounded window, for independent CPU-bound work per item given as a static method or a serializable invokable (closures do not serialize). Measured there on 12 CPUs: 16 items of about 200 ms of CPU take 3.1 s in the core `select()` and 0.9 s with 8 workers; starting a worker costs about 100 ms, so light work stays in the core. Listed in `suggest` since 2.4.0.
- **Goal:** Each kind of concurrency where it actually gains time: prefetch in the core, Fibers for non-blocking I/O, processes for CPU, and nothing promised that a Fiber cannot deliver.

### 15. Comparers in Grouping, Set Operations and Joins; Joins on the Lazy Side 🔭 (Shipped in v2.4.0)
- **Description:** An equality comparer (`IALinqEqualityComparer`, such as `StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true)`) as the last parameter of `groupBy()`, `toLookup()`, `toDictionary()`, `countBy()`, `aggregateBy()`, of the set operations (`intersect()`, `except()`, `intersectBy()`, `exceptBy()`, `unionBy()`, `exceptByKeys()`, `intersectByKeys()`) and of the joins (`join()`, `groupJoin()`, `leftJoin()`, `rightJoin()`, `fullJoin()`), on both collections where the operator exists. The question left open in v2.2.0, which text is "the" key of a group, is answered as LINQ answers it: the first key seen (`'São Paulo'` before `'sao paulo'` names the group `'São Paulo'`), and a set keeps the first item of each key. Hash-based, so a join stays linear (10 000 × 10 000 rows: one `equals()` per outer row); the lookup of `toLookup()` keeps the comparer for `get()`/`contains()`. `join()`/`groupJoin()` on the lazy collection (the inner side read once into a hash table, the lazy side streamed, the answers of the eager side), and a seed per group in `aggregateBy()` (`seedSelector: fn($key)`, the `seedSelector` of .NET 9).

---

## 🗓️ Scheduled

Nothing. Every milestone above shipped; new work enters this file with a consumer and a written case first.

---

## 🤝 Community & Contributions
Suggestions, feedback and pull requests are welcome. To propose a feature or contribute to any item on this roadmap, open an issue or discussion on [GitHub Issues](https://github.com/antevemus-it/Antevemus.AlinqCollection/issues).
