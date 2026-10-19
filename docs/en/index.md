# Antevemus ALinq Collection

The official documentation of the library, one page per topic. The same pages exist in Portuguese (Brazil), in `docs/pt-BR`; the API Reference keeps the section numbers it had in the README (`### 9.` is still §9, now on its own page), and every PHP block of these pages is checked by the test suite.

> **Enterprise LINQ-Style Collection Framework for PHP 8.4+**  
> A fluent, expressive, and type-safe collection manipulation engine bringing the elegance of .NET Language Integrated Query (LINQ) to PHP. Powered by PHP 8.4 native array primitives (`array_any`, `array_all`, `array_find`, `array_find_key`), generator-based streaming pipelines (`ALinqLazyCollection`), advanced set theory, dynamic predicate query builders, dot-notation deep property resolution, and seamless bidirectional synergy with [`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification).

## Documentation Map

### Getting started

- [Installation and quick start](getting-started/index.md): Composer, the Git VCS repository and the Quick Start.

### Concepts

- [Origins and philosophy](concepts/origins.md): Why LINQ in PHP, and the six design principles of the library.
- [Core features](concepts/core-features.md): Every operator of the library in one table, by domain.
- [Architecture](concepts/architecture.md): The source tree: the main classes, the 11 interfaces and the 8 traits.
- [Synergy with ASpecification](concepts/aspecification-synergy.md): ASpecification rules evaluated over ALinq collections through `ALinqBridge`.

### API reference

- [Factories](api/factories.md) (§1): `from()`, `range()`, `repeat()`, `empty()`, `toArray()` and `toObject()`.
- [Filtering and slicing](api/filtering.md) (§2): `where()`, `take()`/`skip()`, `distinct()`, `first()`, `chunk()` and the field filters `whereIn()`, `whereNotIn()`, `whereBetween()`.
- [Joining and set theory](api/joining.md) (§3): Inner and outer joins, `groupJoin()`, the set operations and `exceptByKeys()`/`intersectByKeys()`.
- [Aggregations and metrics](api/aggregations.md) (§4): `any()`, `all()`, `sum()`, `average()`, `min()`/`max()`, `single()` and the `*OrDefault()` aggregates.
- [Projections and transformations](api/projections.md) (§5): `select()`, `selectMany()`, `column()`, `toDictionary(onDuplicate:)` and `toLookup()`.
- [Grouping and partitioning](api/grouping.md) (§6): `groupBy()` into sub-collections, and `ALinqLookup`, a materialized `groupBy()`.
- [Sorting and ordering](api/ordering.md) (§7): Stable `orderBy()`/`thenBy()`, natural and custom orderings, and `StringComparer`.
- [Dynamic query builder](api/query-builder.md) (§8): `ALinqQueryBuilder`: conditions in `and`/`or` mode, its operators and `toPredicate()`.
- [Deep property access](api/property-access.md) (§9): Dot-notation paths, the resolution order and the `#[Specifiable]` opt-in for non-public properties.
- [Streaming and big data](api/lazy-streaming.md) (§10): `ALinqLazyCollection`: files, CSV, database cursors, paginated HTTP, `remember()` and the stages that accumulate data.
- [The contract](api/contract.md) (§11): The rules both collections obey: keys, equality, group keys, empty collections, ordering, comparers, arguments and more.

### Quality

- [Tests](quality/tests.md): The PHPUnit report, the measured coverage and the mutation testing.

### Project

- [Roadmap](project/roadmap.md): Every release shipped and what comes next; the milestone view is in ROADMAP.md.
- [License and authors](project/license-and-authors.md): The MIT license, the author and the organization.

## Where Is Each Thing

| What | Where |
| :--- | :--- |
| Overview, badges and the Quick Start | [README.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/README.md) |
| What changed in each version | [CHANGELOG.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/CHANGELOG.md) |
| Milestones shipped and planned | [ROADMAP.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/ROADMAP.md) |
| Upgrading from 1.x | [UPGRADE-2.0.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/UPGRADE-2.0.md) |
| Provenance and third-party licenses | [NOTICE.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/NOTICE.md), [THIRD_PARTY_NOTICES.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/THIRD_PARTY_NOTICES.md) |
| License | [LICENSE](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/LICENSE) |
