# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://dev.azure.com/antevemus/A-Flow%20Engine/_git/Antevemus.AlinqCollection/branchCompare?baseVersion=GTv0.1.1&targetVersion=GBmain
[0.1.1]: https://dev.azure.com/antevemus/A-Flow%20Engine/_git/Antevemus.AlinqCollection/branchCompare?baseVersion=GTv0.1.0&targetVersion=GTv0.1.1
[0.1.0]: https://dev.azure.com/antevemus/A-Flow%20Engine/_git/Antevemus.AlinqCollection?version=GTv0.1.0
