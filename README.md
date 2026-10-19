# Antevemus ALinq Collection

[![CI](https://github.com/antevemus-it/Antevemus.AlinqCollection/actions/workflows/tests.yml/badge.svg)](https://github.com/antevemus-it/Antevemus.AlinqCollection/actions/workflows/tests.yml)
[![Latest Stable Version](https://img.shields.io/badge/release-v2.2.1-blue.svg)](https://github.com/antevemus-it/Antevemus.AlinqCollection/releases)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests Passing](https://img.shields.io/badge/tests-743%20passed%20%7C%203971%20assertions-success.svg)](docs/en/quality/tests.md)
[![Architecture](https://img.shields.io/badge/Architecture-LINQ%20%7C%20Functional%20Collections-orange)](https://learn.microsoft.com/en-us/dotnet/csharp/linq/)
[![Synergy: ASpecification](https://img.shields.io/badge/Synergy-Antevemus.ASpecification-purple)](https://github.com/antevemus-it/Antevemus.ASpecification)

> **Enterprise LINQ-Style Collection Framework for PHP 8.4+**  
> A fluent, expressive, and type-safe collection manipulation engine bringing the elegance of .NET Language Integrated Query (LINQ) to PHP. Powered by PHP 8.4 native array primitives (`array_any`, `array_all`, `array_find`, `array_find_key`), generator-based streaming pipelines (`ALinqLazyCollection`), advanced set theory, dynamic predicate query builders, dot-notation deep property resolution, and seamless bidirectional synergy with [`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification).

---

[🇺🇸 English](README.md) • [🇧🇷 Português (Brasil)](README.pt-BR.md)

---

The documentation lives in [`docs/en`](docs/en/index.md) (English) and [`docs/pt-BR`](docs/pt-BR/index.md) (Portuguese, Brazil), one page per topic, in the Docs Kit format (a `docs-nav.json` per language). The API Reference keeps the section numbers it had in this README (§9 is still `### 9.`, now on its own page), and every PHP block of the pages is checked by the test suite.

---

## 🚀 Quick Start

The same block opens [Getting Started](docs/en/getting-started/index.md), and the test suite runs it with the answers written in its comments:

<!-- tested: quick-start -->
```php
<?php

declare(strict_types=1);

use Antevemus\ALinq\ALinqCollection;

$users = ALinqCollection::from([
    ['id' => 1, 'name' => 'Alice', 'role' => 'admin', 'score' => 95, 'active' => true],
    ['id' => 2, 'name' => 'Bob', 'role' => 'editor', 'score' => 82, 'active' => true],
    ['id' => 3, 'name' => 'Charlie', 'role' => 'viewer', 'score' => 60, 'active' => false],
    ['id' => 4, 'name' => 'Diana', 'role' => 'admin', 'score' => 91, 'active' => true],
    ['id' => 5, 'name' => 'Evan', 'role' => 'editor', 'score' => 74, 'active' => true],
]);

// 1. Fluent Query Pipeline
$topActiveAdmins = $users
    ->where(fn($u) => $u['active'] && $u['role'] === 'admin')
    ->orderByDescending(fn($u) => $u['score'])
    ->select(fn($u) => sprintf('%s (%d pts)', $u['name'], $u['score']))
    ->toArray();

// Result: ['Alice (95 pts)', 'Diana (91 pts)']

// 2. High-Performance Aggregations (Native PHP 8.4)
$hasSuperUser = $users->any(fn($u) => $u['score'] > 90);      // true (via array_any)
$allActive    = $users->all(fn($u) => $u['active']);          // false (via array_all)
$avgScore     = $users->average(fn($u) => $u['score']);       // 80.4
$bestUser     = $users->maxBy(fn($u) => $u['score']);         // ['id' => 1, 'name' => 'Alice'...]

// 3. Grouping & Sub-Collection Aggregations
$roleStats = $users
    ->groupBy(fn($u) => $u['role'])
    ->select(fn(ALinqCollection $group, string $role) => [
        'role'        => $role,
        'count'       => $group->count(),
        'avg_score'   => $group->average(fn($u) => $u['score']),
        'top_performer' => $group->maxBy(fn($u) => $u['score'])['name'],
    ])
    ->toArray();
```

---

## 📦 Installation

Install via Composer: `composer require antevemus/alinq-collection`. Requirements: PHP 8.4 or later, and one dependency, installed with it: [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (from the same author, with no dependencies of its own), the property accessor shared with Antevemus.ASpecification. Upgrading from 1.x: see [UPGRADE-2.0.md](UPGRADE-2.0.md). Installing from the Git VCS repository: [Getting Started](docs/en/getting-started/index.md).

---

## 📚 Documentation

One line per page, grouped as in the navigation; §N is the section of the API Reference that the page hosts, numbered as in the former README.

**Getting started**

- [Installation and quick start](docs/en/getting-started/index.md) · [pt-BR](docs/pt-BR/getting-started/index.md): Composer, the Git VCS repository and the Quick Start.

**Concepts**

- [Origins and philosophy](docs/en/concepts/origins.md) · [pt-BR](docs/pt-BR/concepts/origins.md): Why LINQ in PHP, and the six design principles of the library.
- [Core features](docs/en/concepts/core-features.md) · [pt-BR](docs/pt-BR/concepts/core-features.md): Every operator of the library in one table, by domain.
- [Architecture](docs/en/concepts/architecture.md) · [pt-BR](docs/pt-BR/concepts/architecture.md): The source tree: the main classes, the 11 interfaces and the 8 traits.
- [Synergy with ASpecification](docs/en/concepts/aspecification-synergy.md) · [pt-BR](docs/pt-BR/concepts/aspecification-synergy.md): ASpecification rules evaluated over ALinq collections through `ALinqBridge`.

**API reference**

- [Factories](docs/en/api/factories.md) (§1) · [pt-BR](docs/pt-BR/api/factories.md): `from()`, `range()`, `repeat()`, `empty()`, `toArray()` and `toObject()`.
- [Filtering and slicing](docs/en/api/filtering.md) (§2) · [pt-BR](docs/pt-BR/api/filtering.md): `where()`, `take()`/`skip()`, `distinct()`, `first()`, `chunk()` and the field filters `whereIn()`, `whereNotIn()`, `whereBetween()`.
- [Joining and set theory](docs/en/api/joining.md) (§3) · [pt-BR](docs/pt-BR/api/joining.md): Inner and outer joins, `groupJoin()`, the set operations and `exceptByKeys()`/`intersectByKeys()`.
- [Aggregations and metrics](docs/en/api/aggregations.md) (§4) · [pt-BR](docs/pt-BR/api/aggregations.md): `any()`, `all()`, `sum()`, `average()`, `min()`/`max()`, `single()` and the `*OrDefault()` aggregates.
- [Projections and transformations](docs/en/api/projections.md) (§5) · [pt-BR](docs/pt-BR/api/projections.md): `select()`, `selectMany()`, `column()`, `toDictionary(onDuplicate:)` and `toLookup()`.
- [Grouping and partitioning](docs/en/api/grouping.md) (§6) · [pt-BR](docs/pt-BR/api/grouping.md): `groupBy()` into sub-collections, and `ALinqLookup`, a materialized `groupBy()`.
- [Sorting and ordering](docs/en/api/ordering.md) (§7) · [pt-BR](docs/pt-BR/api/ordering.md): Stable `orderBy()`/`thenBy()`, natural and custom orderings, and `StringComparer`.
- [Dynamic query builder](docs/en/api/query-builder.md) (§8) · [pt-BR](docs/pt-BR/api/query-builder.md): `ALinqQueryBuilder`: conditions in `and`/`or` mode, its operators and `toPredicate()`.
- [Deep property access](docs/en/api/property-access.md) (§9) · [pt-BR](docs/pt-BR/api/property-access.md): Dot-notation paths, the resolution order and the `#[Specifiable]` opt-in for non-public properties.
- [Streaming and big data](docs/en/api/lazy-streaming.md) (§10) · [pt-BR](docs/pt-BR/api/lazy-streaming.md): `ALinqLazyCollection`: files, CSV, database cursors, paginated HTTP, `remember()` and the stages that accumulate data.
- [The contract](docs/en/api/contract.md) (§11) · [pt-BR](docs/pt-BR/api/contract.md): The rules both collections obey: keys, equality, group keys, empty collections, ordering, comparers, arguments and more.

**Quality**

- [Tests](docs/en/quality/tests.md) · [pt-BR](docs/pt-BR/quality/tests.md): The PHPUnit report, the measured coverage and the mutation testing.

**Project**

- [Roadmap](docs/en/project/roadmap.md) · [pt-BR](docs/pt-BR/project/roadmap.md): Every release shipped and what comes next; the milestone view is in ROADMAP.md.
- [License and authors](docs/en/project/license-and-authors.md) · [pt-BR](docs/pt-BR/project/license-and-authors.md): The MIT license, the author and the organization.

---

## 🧪 Quality

- **Tests:** 743 PHPUnit tests (`vendor/bin/phpunit`) over the 8 traits, the streaming engine, the eager × lazy contract and the code blocks of the documentation, 100% passing on PHP 8.4.
- **Assertions:** 3971 without `ext-intl` (the 10 tests of `StringComparer::culture()` skipped) and 4054 with it (1 skipped, the test of the missing extension); the CI runs both legs.
- **Coverage** (pcov, 2.2.0, with `ext-intl`): 289 of 299 methods and 1619 of 1637 lines of `src/`; the 18 uncovered lines are defensive guards or depend on the environment.
- **Mutation testing:** Infection on the lines changed since 2.1.0, MSI 97.59% (10 escaped mutants, all equivalent).
- **Contract parity:** every shared operation runs over the same inputs on the eager and the lazy collection, with zero divergences allowed (`tests/Contract/ParityTest.php`; the rules: [The contract](docs/en/api/contract.md)).
- **The report**, the coverage details and the mutation run: [Tests](docs/en/quality/tests.md).

---

## 🗺️ Roadmap & Ecosystem

Every release shipped and what comes next: [Roadmap](docs/en/project/roadmap.md). The milestone view, with target releases: 👉 **[ROADMAP.md](ROADMAP.md)**.

---

## 📄 License & Authors

This software is open-source, licensed under the **[MIT License](LICENSE)**.

- **Author:** Heliton Junior (CTO) — [`contato@antevemus.com.br`](mailto:contato@antevemus.com.br)
- **Organization:** [Antevemus Soluções Inovadoras em TI Ltda.](https://antevemus.com.br)
- **Copyright:** Copyright (c) 2025–2026 Antevemus Soluções Inovadoras em TI Ltda.
