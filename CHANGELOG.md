# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.2] - 2026-10-08

### Fixed
- **Lazy materializers no longer lose items.** `toArray()`, `toCollection()` and `toObject()` on an `ALinqLazyCollection` stored each item under its source key, so a source whose keys repeat (two `yield from`, a `where()`/`skip()` pipeline over a plain list, any generator restarting its counter) reported `count()` = 4 and returned two items. The materializers now follow the key policy of the eager collection: a stream whose keys are all integers is a list and comes out reindexed (`where()` over `[1..6]` gives `[2, 4, 6]`, not `[1 => 2, 3 => 4, 5 => 6]`); a stream with string keys is a dictionary and keeps its keys, appending an item whose key collides instead of overwriting it. **Breaking for** code that relied on the gaps left by the lazy `where()`/`skip()`/`take()` over a list.
- **`chunk()` on a lazy stream** keyed each chunk by the source key: repeated keys overwrote items and the chunk never reached its size (six items with key 0 came out as one chunk of one). Chunks are now reindexed lists, as `array_chunk()` does on the eager side.
- **`pad()` on a lazy stream** keyed the padding by its counter, which collided with the keys that `where()`/`skip()` leave behind and overwrote real items (`['a','b','c']->where(v != 'a')->pad(5, 'p')` gave `b, p, p, p`). Padding is now appended after the last integer key, as `array_pad()` does.
- **`remember()` with repeated keys** collapsed the cache on the key, so the second pass saw two items where the first saw four.
- **`last()` / `lastOrDefault()` with a key-aware predicate** reversed the list without preserving keys, so the predicate saw the keys backwards (`fn($v, $k) => $k !== 0` on `[1..5]` answered 4). The real keys are now passed.

### Changed
- **`remember()` is resumable.** A short-circuited pass (`first()`, `any()`, `take(n)`) now caches what it consumed and the next pass replays the cache and keeps pulling from where the upstream stopped: the upstream runs once whatever the shape of the passes (1.1.1 discarded the partial buffer and re-ran it), and a single-pass PDO cursor wrapped in `remember()` can be followed by `first()` and then `toArray()` instead of throwing.

### Added
- Regression tests for the above (suite now **342 tests, 614 assertions**); two pre-existing tests that encoded the old key gaps and the 1.1.1 re-run of the upstream were updated to the new contract.

### Documentation
- Class DocBlock `@version` tags now state the package version in which each file was last changed (derived from the repository history; 19 files `0.1.0`, `IALinqLazyCollection` `1.1.1`, `ALinqCollection` `1.1.0`, the two files changed here `1.1.2`). This one-off correction of the tags does not itself count as a change to the files. CHANGELOG footer links rewritten for every version against the GitHub repository, and an `[Unreleased]` section added.

## [1.1.1] - 2026-10-07

### Fixed
- **`remember()` cache poisoning on partial traversal**: the cache is now promoted only after a complete pass. A short-circuited first traversal (`first()`, `any()`, `take(n)`, `zip()`) no longer leaves a truncated cache that later passes would serve as the whole stream.
- **`fromFile()` splitting long lines**: lines longer than `bufferSize - 1` bytes (4095 by default) were emitted as several items. Lines are now read to their end regardless of length; `$bufferSize` is kept as the stream read-chunk hint and must be greater than zero.

### Changed
- **Single-pass PDO cursors fail loudly**: a second traversal of `fromCursor()` or `from(PDOStatement)` used to yield an empty stream silently. It now throws a `RuntimeException` pointing to `->remember()` (with a complete first pass) as the way to re-traverse the stream.
- **DocBlock headers of `ALinqLazyCollection` and `IALinqLazyCollection`** aligned with the header standard used by every other class and interface of the library.

### Added
- Reproduction and regression tests for the fixes above in `ALinqLazyCollectionTest` (suite now **338 tests, 581 assertions**).

## [1.1.0] - 2026-10-05

### Added
- **Generator-Based Streaming Engine (`ALinqLazyCollection`)**: Implementation of deferred, pull-based streaming pipeline enabling processing of multi-gigabyte files, datasets, and unbuffered SQL cursors with constant $O(1)$ memory consumption (< 100 KB RAM).
- **Contract `IALinqLazyCollection`**: Formal interface contract segregating 35+ lazy transformation and terminal reduction operations.
- **Rewindable Multi-Pass Traversals**: Solved PHP's native `"Cannot traverse an already closed generator"` limitation by wrapping generator factories (`Closure(): iterable`), allowing infinite re-iteration of pipelines.
- **Deterministic Resource Disposal**: `fromFile()` and `fromCsv()` guarantee instant file handle closure via `try ... finally` blocks even during short-circuited termination (`take()`, `first()`).
- **Dynamic CSV Ingestion**: `ALinqLazyCollection::fromCsv()` automatically maps the first header line to associative row arrays with configurable delimiter, enclosure, and escape characters.
- **Unbuffered Database Cursor Streaming**: `ALinqLazyCollection::fromCursor()` streams PDO statements row-by-row with optional row projection mapper.
- **Intermediate Stream Caching**: `remember()` operator caches evaluated items during first traversal for lightning-fast subsequent passes without re-executing expensive upstream I/O.
- **Dual Pipeline Interoperability**: Seamless transitions between eager in-memory collections and lazy streams via `$collection->lazy()` and `$lazyCollection->toCollection()`.
- **Expanded Test Suite**: Added 30 new unit tests (76 assertions) in `ALinqLazyCollectionTest`, bringing total suite to **335 tests, 561 assertions** with 100% pass rate.

## [1.0.0] - 2026-10-04

### Added
- **Official Enterprise Release (v1.0.0)**: Production-ready certification for PHP 8.4+.
- **ASpecification Synergy**: Full compatibility and interoperability with [`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification) via `ALinqBridge` and `ALinqSpecificationVisitor`.
- **Bilingual Documentation**: Comprehensive technical documentation with English default ([`README.md`](README.md)) and Brazilian Portuguese ([`README.pt-BR.md`](README.pt-BR.md)).
- **Composer Corporate Alignment**: Standardized corporate metadata, support links (`source`, `issues`, `docs`), and author credentials for Packagist.org distribution.

### Verified
- **PHP 8.4 Runtime Certification**: 100% test pass rate across 305 tests and 485 assertions with zero deprecations and zero regressions.

## [0.1.1] - 2025-11-20

### Fixed
- **`where()` array reindexing**: Lists (sequential numeric arrays) are now reindexed starting from 0 after filtering, while associative arrays preserve their keys. This fixes issues when using `first()` or array index access after `where()` operations.
- **`firstOrDefault()` key independence**: Now uses `reset()` instead of `$this->items[0]` to get the first element, making it work correctly with any array key structure.

### Changed
- Simplified `firstOrDefault()` implementation removing redundant conditions
- Updated tests to reflect the new reindexing behavior for lists

## [0.1.0] - 2025-10-23

### Added
- Initial release of ALinq Collection library
- Core `ALinqCollection` class implementing fluent LINQ-style API
- `ALinqQueryBuilder` for building complex predicates
- `ALinqPropertyAccess` helper for property access patterns
- Comprehensive trait-based architecture:
  - **FilteringOperations** (17 methods): where, take, skip, distinct, first, last, chunk, pad, shuffle, contains
  - **JoiningOperations** (13 methods): join, groupJoin, concat, intersect, except, combine, replace, exceptBy, intersectBy, unionBy
  - **AggregationOperations** (13 methods): any, all, sum, average, min, max, product, aggregate, aggregateBy, countBy, maxBy, minBy, countValues
  - **SelectionOperations** (6 methods): select, selectMany, column, toDictionary, toObject, flip
  - **GroupingOperations** (1 method): groupBy
  - **OrderingOperations** (6 methods): orderBy, orderByDescending, orderByNatural, orderByCustom, orderByKey, reverse
  - **IteratorOperations** (6 methods): current, key, next, prev, reset, end
  - **UtilityOperations** (7 methods): isList, each, eachRecursive, random, extract, createPredicate, createPropertySelector
- Factory methods: `from()`, `range()`, `empty()`, `repeat()`
- Full PHP 8.4 compatibility using native array functions (array_any, array_all, array_find, array_find_key)
- Comprehensive test suite with 305 tests and 485 assertions
- 100% code coverage (338/338 lines, 84/84 methods, 11/11 classes)
- PSR-4 autoloading with `Antevemus\ALinq` namespace
- Complete PHPDoc documentation
- MIT License

### Fixed
- Callable parameter validation using `$predicate === null` instead of `empty($predicate)` in `any()` and `all()` methods
- `intersect()` now properly removes duplicates using `array_unique()` for correct set theory behavior

### Changed
- Refactored monolithic 1,020-line class into modular trait-based architecture (89% size reduction to 112 lines)
- Method `all()` signature changed from `all(callable $predicate)` to `all(?callable $predicate = null)` for consistency

### Security
- Type-safe constructor rejecting null parameters
- Strict type hints throughout the codebase
- No external runtime dependencies (requires only PHP 8.4+)

## Release Notes

### v0.1.0 - Initial Release

This is the first public release of ALinq Collection, a comprehensive LINQ-style collection library for PHP 8.4+.

**Highlights:**
- 🎯 **69 LINQ-style methods** covering filtering, joining, aggregation, selection, grouping, ordering, and utilities
- 🧪 **100% test coverage** with 305 comprehensive tests
- 🏗️ **Trait-based architecture** for clean separation of concerns
- ⚡ **PHP 8.4 native functions** for optimal performance
- 📦 **Zero runtime dependencies**
- 📚 **Complete documentation** with examples

**What's Included:**

**Filtering & Querying:**
- where, whereIn, whereNotIn, whereBetween
- take, skip, distinct, distinctBy
- first, firstOrDefault, last, lastOrDefault
- single, singleOrDefault
- chunk, pad, shuffle, contains

**Joining & Set Operations:**
- join, groupJoin
- concat, intersect, except
- intersectBy, exceptBy, unionBy
- combine, replace, replaceRecursive

**Aggregation & Statistics:**
- any, all, count, countValues
- sum, average, min, max, product
- aggregate, aggregateBy, countBy
- minBy, maxBy

**Selection & Projection:**
- select, selectMany
- column, toDictionary, toObject
- flip

**Grouping & Ordering:**
- groupBy
- orderBy, orderByDescending
- orderByNatural, orderByCustom
- orderByKey, reverse

**Utilities:**
- each, eachRecursive
- random, extract
- isList
- Fluent query builder

**PHP Compatibility:**
Requires PHP 8.4.0 or higher for native array functions support.

**Installation:**
```bash
composer require antevemus/alinq-collection
```

**Quick Example:**
```php
use Antevemus\ALinq\ALinqCollection;

$users = ALinqCollection::from([
    ['name' => 'Alice', 'age' => 25, 'city' => 'NYC'],
    ['name' => 'Bob', 'age' => 30, 'city' => 'LA'],
    ['name' => 'Charlie', 'age' => 25, 'city' => 'NYC']
]);

$result = $users
    ->where(fn($u) => $u['age'] === 25)
    ->orderBy(fn($u) => $u['name'])
    ->select(fn($u) => $u['name'])
    ->toArray();

// Result: ['Alice', 'Charlie']
```

For complete documentation, see [README.md](README.md).

---

[Unreleased]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.1.2...HEAD
[1.1.2]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v0.1.1...v1.0.0
[0.1.1]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/releases/tag/v0.1.0
