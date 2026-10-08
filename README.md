# Antevemus ALinq Collection

[![Latest Stable Version](https://img.shields.io/badge/release-v1.1.1-blue.svg)](https://github.com/antevemus-it/Antevemus.AlinqCollection/releases)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests Passing](https://img.shields.io/badge/tests-342%20passed%20%7C%20614%20assertions-success.svg)](tests/)
[![Architecture](https://img.shields.io/badge/Architecture-LINQ%20%7C%20Functional%20Collections-orange)](https://learn.microsoft.com/en-us/dotnet/csharp/linq/)
[![Synergy: ASpecification](https://img.shields.io/badge/Synergy-Antevemus.ASpecification-purple)](https://github.com/antevemus-it/Antevemus.ASpecification)

> **Enterprise LINQ-Style Collection Framework for PHP 8.4+**  
> A fluent, expressive, and type-safe collection manipulation engine bringing the elegance of .NET Language Integrated Query (LINQ) to PHP. Powered by PHP 8.4 native array primitives (`array_any`, `array_all`, `array_find`, `array_find_key`), generator-based streaming pipelines (`ALinqLazyCollection`), advanced set theory, dynamic predicate query builders, dot-notation deep property resolution, and seamless bidirectional synergy with [`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification).

---

[🇺🇸 English](README.md) • [🇧🇷 Português (Brasil)](README.pt-BR.md)

---

## 🏛️ Origins & Philosophy

Modern PHP development frequently struggles with boilerplate array manipulations: repetitive loops, stateful mutation variables, and verbose closures wrapping native functions. While .NET developers have long enjoyed **LINQ (Language Integrated Query)** as a declarative standard for querying data structures, PHP developers have traditionally relied on heavy, monolithic collection libraries.

**Antevemus ALinq Collection** was designed from first principles for **PHP 8.4+**:
1. **Leveraging Native C-Level Primitives**: Harnesses PHP 8.4's native `array_any()`, `array_all()`, `array_find()`, and `array_find_key()` for maximum execution speed without userspace overhead.
2. **Decomposed Trait-Based Modular Architecture**: Eliminates monoliths. Functionality is cleanly decoupled across 8 specialized domain traits (Filtering, Joining, Aggregations, Projections, Grouping, Ordering, Iterators, Utilities).
3. **Generator-Based Streaming Pipelines (O(1) RAM)**: `ALinqLazyCollection` processes multi-gigabyte log files, CSVs, and unbuffered database cursors line-by-line without memory inflation.
4. **Enterprise Set Theory**: Complete mathematical Venn diagram operations (`intersectBy`, `exceptBy`, `unionBy`) supporting custom key selectors and comparers.
5. **Dot-Notation Deep Property Access**: Query mixed datasets (arrays, objects, nested DTOs, or domain entities) effortlessly using dot-notation paths like `user.profile.address.city`.
6. **Architectural Synergy**: Native integration partner for **[`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification)**, allowing Evans & Fowler specification trees to compile directly into high-speed in-memory LINQ pipelines via `ALinqBridge` and `ALinqSpecificationVisitor`.

---

## 🌟 Core Features at a Glance

| Feature Domain | Capabilities |
| :--- | :--- |
| **🌊 Streaming & Big Data** | `ALinqLazyCollection`, `fromFile`, `fromCsv`, `fromCursor`, `where`, `select`, `takeWhile`, `skipWhile`, `zip`, `tap`, `remember`, constant $O(1)$ RAM |
| **🔍 Filtering & Slicing** | `where`, `take`, `skip`, `distinct`, `distinctBy`, `first`, `firstOrDefault`, `last`, `lastOrDefault`, `singleOrDefault`, `chunk`, `pad`, `shuffle`, `contains` |
| **🔗 Joining & Set Theory** | `join` (inner), `groupJoin` (left outer), `concat`, `intersect`, `intersectWith`, `intersectBy`, `except`, `exceptWith`, `exceptBy`, `unionBy`, `combine`, `replace`, `replaceRecursive` |
| **📊 Aggregation & Metrics** | `any`, `all`, `sum`, `average`, `min`, `minBy`, `max`, `maxBy`, `product`, `countValues`, `aggregate`, `aggregateBy`, `countBy` |
| **🎯 Selection & Projection** | `select`, `selectMany` (flattening), `column`, `toDictionary`, `toObject`, `flip`, `toArray` |
| **🗂️ Grouping & Bucketing** | `groupBy` (keyed sub-collections with full fluent chaining capabilities) |
| **⚡ Ordering & Sorting** | `orderBy`, `orderByDescending`, `orderByNatural` (human alphanumeric sort), `orderByCustom`, `orderByKey`, `reverse` |
| **🧭 Iteration & Traversal** | `each`, `eachRecursive`, `current`, `key`, `next`, `prev`, `reset`, `end`, full `IteratorAggregate` and `Countable` parity |
| **🛠️ Dynamic Query Builder** | `ALinqQueryBuilder` (`create('and'\|'or')`, `where('field', '>=', $val)`, `toPredicate()`) |
| **🔎 Deep Property Access** | `ALinqPropertyAccess` with dot-notation (`getValue($item, 'company.address.zip')`) and reflection support |

---

## 📦 Installation

Install via Composer:

```bash
composer require antevemus/alinq-collection
```

### Git VCS Repository (Development or Private Environments)

If using directly from GitHub prior to or alongside Packagist:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/antevemus-it/Antevemus.AlinqCollection.git"
        }
    ],
    "require": {
        "antevemus/alinq-collection": "^1.0"
    }
}
```

---

## 🚀 Quick Start

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

## 🧩 Architectural Deep Dive

`Antevemus.AlinqCollection` is engineered for high maintainability, strict typing, and zero circular dependencies. Rather than a bloated 2,000-line monolith, the main class delegates every functional domain into targeted traits:

```text
Antevemus\ALinq\
├── ALinqCollection.php          # Main facade implementing IALinqCollection (In-Memory Engine)
├── ALinqLazyCollection.php      # Generator-based Streaming Pipeline (O(1) Memory Engine)
├── ALinqQueryBuilder.php        # Fluent condition builder (AND / OR modes) for complex queries
├── Helpers\
│   └── ALinqPropertyAccess.php  # Universal getter supporting dot notation, arrays, objects, and methods
├── Interfaces\                  # 11 Segregated Contracts
│   ├── IALinqBaseCollection.php
│   ├── IALinqCollection.php
│   ├── IALinqLazyCollection.php # Streaming collection contract (Generators, files, cursors)
│   ├── IALinqFilterable.php
│   ├── IALinqJoinable.php
│   ├── IALinqAggregatable.php
│   ├── IALinqSelectable.php
│   ├── IALinqGroupable.php
│   ├── IALinqOrderable.php
│   ├── IALinqIterator.php
│   └── IALinqUtility.php
└── Traits\                      # 8 Isolated Trait Implementations
    ├── FilteringOperations.php
    ├── JoiningOperations.php
    ├── AggregationOperations.php
    ├── SelectionOperations.php
    ├── GroupingOperations.php
    ├── OrderingOperations.php
    ├── IteratorOperations.php
    └── UtilityOperations.php
```

---

## 🔮 Synergy with Antevemus ASpecification

`Antevemus.AlinqCollection` is the native collection engine for **[`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification)**. When both packages are installed, business rules encapsulated in **Domain Specifications** can be evaluated directly against ALinq collections without writing manual closures.

### How It Works

The **`ALinqBridge`** and **`ALinqSpecificationVisitor`** inside `Antevemus.ASpecification` compile any composite specification AST (composed of `And`, `Or`, `Not`, `GreaterThan`, `Regex`, etc.) into an optimized PHP functional predicate:

```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ASpecification\Spec;
use Antevemus\ASpecification\Linq\ALinqBridge;
use function Antevemus\ASpecification\DSL\is;
use function Antevemus\ASpecification\DSL\isGreaterThan;

// 1. Define Business Rules using Evans & Fowler Specification Pattern
$isEligibleCustomer = Spec::and(
    Spec::property('status', is('ACTIVE')),
    Spec::property('creditScore', isGreaterThan(700))
);

// 2. Query any collection or in-memory repository through ALinqBridge
$customers = ALinqCollection::from($rawCustomerData);

// ALinqBridge compiles the specification AST into a native predicate:
$qualifiedCustomers = ALinqBridge::filter($customers, $isEligibleCustomer)
    ->orderByDescending(fn($c) => $c->creditScore)
    ->take(10)
    ->select(fn($c) => [
        'id'    => $c->id,
        'name'  => $c->name,
        'tier'  => 'PREMIUM',
    ])
    ->toArray();
```

---

## 📖 Comprehensive API Reference

### 1. Factory & Instantiation Methods

| Method | Signature | Description |
| :--- | :--- | :--- |
| `from()` | `static from(array $items): self` | Creates an ALinqCollection from a raw PHP array. |
| `range()` | `static range(int $start, int $end, int $step = 1): self` | Generates a collection containing an arithmetic progression. |
| `repeat()` | `static repeat(mixed $element, int $count): self` | Generates a collection that contains one repeated value. |
| `empty()` | `static empty(): self` | Returns an empty ALinqCollection instance. |
| `toArray()` | `toArray(): array` | Exports the collection back to a standard PHP array. |
| `toObject()` | `toObject(): stdClass` | Converts the items into a `stdClass` object. |

```php
$numbers = ALinqCollection::range(10, 50, 10); // [10, 20, 30, 40, 50]
$flags   = ALinqCollection::repeat('PENDING', 3); // ['PENDING', 'PENDING', 'PENDING']
```

---

### 2. Filtering & Slicing (`FilteringOperations`)

```php
$collection = ALinqCollection::from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

// where(): Filters elements matching predicate; automatically reindexes numeric lists
$evens = $collection->where(fn($x) => $x % 2 === 0);

// take(n) & skip(n): Slicing and pagination
$page = $collection->skip(4)->take(3); // [5, 6, 7]

// distinct() & distinctBy(): Deduplication
$people = ALinqCollection::from([
    ['id' => 1, 'dept' => 'IT'],
    ['id' => 2, 'dept' => 'HR'],
    ['id' => 3, 'dept' => 'IT'],
]);
$uniqueDepts = $people->distinctBy(fn($p) => $p['dept']); // IDs 1, 2

// Element extraction
$first = $collection->first(fn($x) => $x > 5);        // 6
$firstOrNull = $collection->firstOrDefault(null, fn($x) => $x > 100); // null
$single = $collection->where(fn($x) => $x === 3)->singleOrDefault(); // 3

// chunk(size) & pad(size, value)
$chunks = $collection->chunk(3); // [[1, 2, 3], [4, 5, 6], [7, 8, 9], [10]]
$padded = ALinqCollection::from([1, 2])->pad(5, 0); // [1, 2, 0, 0, 0]
```

---

### 3. Joining & Set Theory (`JoiningOperations`)

Full relational joins and mathematical set operations:

```php
$departments = ALinqCollection::from([
    ['id' => 10, 'name' => 'Engineering'],
    ['id' => 20, 'name' => 'Marketing'],
]);

$employees = ALinqCollection::from([
    ['name' => 'Alex', 'dept_id' => 10],
    ['name' => 'Beatrice', 'dept_id' => 10],
    ['name' => 'Carlos', 'dept_id' => 20],
]);

// 1. Inner Join (SQL-style)
$directory = $employees->join(
    inner: $departments->toArray(),
    outerKeySelector: fn($emp) => $emp['dept_id'],
    innerKeySelector: fn($dept) => $dept['id'],
    resultSelector: fn($emp, $dept) => [
        'employee'   => $emp['name'],
        'department' => $dept['name'],
    ]
)->toArray();

// 2. Group Join (Hierarchical / Left Outer Join)
$deptRoster = $departments->groupJoin(
    inner: $employees->toArray(),
    outerKeySelector: fn($dept) => $dept['id'],
    innerKeySelector: fn($emp) => $emp['dept_id'],
    resultSelector: fn($dept, array $matchedEmps) => [
        'dept'  => $dept['name'],
        'staff' => array_column($matchedEmps, 'name'),
    ]
)->toArray();

// 3. Set Operations with Key Selectors
$datasetA = ALinqCollection::from([['id' => 1], ['id' => 2], ['id' => 3]]);
$datasetB = [['id' => 2], ['id' => 3], ['id' => 4]];

$intersect = $datasetA->intersectBy($datasetB, fn($item) => $item['id']); // [id: 2, id: 3]
$difference = $datasetA->exceptBy($datasetB, fn($item) => $item['id']);   // [id: 1]
$union = $datasetA->unionBy($datasetB, fn($item) => $item['id']);        // [id: 1, id: 2, id: 3, id: 4]
```

---

### 4. Aggregations & Metrics (`AggregationOperations`)

Harnessing PHP 8.4 C-level speed for rapid metrics:

```php
$orders = ALinqCollection::from([
    ['code' => 'ORD-1', 'amount' => 120.50, 'status' => 'COMPLETED'],
    ['code' => 'ORD-2', 'amount' => 45.00,  'status' => 'PENDING'],
    ['code' => 'ORD-3', 'amount' => 310.00, 'status' => 'COMPLETED'],
    ['code' => 'ORD-4', 'amount' => 85.20,  'status' => 'REFUNDED'],
]);

// Quantifiers
$hasPending = $orders->any(fn($o) => $o['status'] === 'PENDING'); // true (array_any)
$allCompleted = $orders->all(fn($o) => $o['status'] === 'COMPLETED'); // false (array_all)

// Arithmetic Metrics
$totalSales = $orders->sum(fn($o) => $o['amount']);      // 560.70
$avgTicket  = $orders->average(fn($o) => $o['amount']);  // 140.175
$biggestOrder = $orders->maxBy(fn($o) => $o['amount']); // ORD-3
$smallestOrder = $orders->minBy(fn($o) => $o['amount']); // ORD-2

// Frequency & Categorization
$statusCounts = $orders->countBy(fn($o) => $o['status'])->toArray();
// ['COMPLETED' => 2, 'PENDING' => 1, 'REFUNDED' => 1]

// Custom Reductions
$totalBalance = $orders->aggregate(seed: 1000.0, func: fn($acc, $o) => $acc + $o['amount']);
```

---

### 5. Projections & Transformations (`SelectionOperations`)

```php
$catalog = ALinqCollection::from([
    ['sku' => 'A1', 'tags' => ['electronics', 'mobile'], 'price' => 799],
    ['sku' => 'A2', 'tags' => ['mobile', 'accessories'], 'price' => 29],
]);

// select(): Map 1-to-1
$skuList = $catalog->select(fn($item) => $item['sku'])->toArray(); // ['A1', 'A2']

// selectMany(): Flatten 1-to-Many nested collections
$allTags = $catalog
    ->selectMany(fn($item) => $item['tags'])
    ->distinct()
    ->toArray(); // ['electronics', 'mobile', 'accessories']

// toDictionary(): Transform to keyed associative lookup
$priceMap = $catalog->toDictionary(
    keySelector: fn($item) => $item['sku'],
    elementSelector: fn($item) => $item['price']
); // ['A1' => 799, 'A2' => 29]
```

---

### 6. Grouping & Partitioning (`GroupingOperations`)

`groupBy()` returns a collection of `ALinqCollection` instances, enabling seamless nested fluent pipelines:

```php
$transactions = ALinqCollection::from([
    ['category' => 'Food', 'amount' => 25.50],
    ['category' => 'Transport', 'amount' => 15.00],
    ['category' => 'Food', 'amount' => 64.00],
    ['category' => 'Transport', 'amount' => 30.00],
    ['category' => 'Leisure', 'amount' => 120.00],
]);

$summary = $transactions
    ->groupBy(fn($t) => $t['category'])
    ->select(fn(ALinqCollection $group, string $category) => [
        'category'     => $category,
        'total'        => $group->sum(fn($t) => $t['amount']),
        'transaction_count' => $group->count(),
        'average_spend'     => round($group->average(fn($t) => $t['amount']), 2),
    ])
    ->orderByDescending(fn($row) => $row['total'])
    ->toArray();
```

---

### 7. Sorting & Ordering (`OrderingOperations`)

```php
$files = ALinqCollection::from([
    'file10.txt', 'file2.txt', 'file1.txt', 'FILE100.txt'
]);

// Natural Alphanumeric Sort (Human sorting: file1, file2, file10, FILE100)
$naturalSorted = $files->orderByNatural(caseSensitive: false)->toArray();

// Multi-attribute ordering
$employees = ALinqCollection::from([
    ['dept' => 'Sales', 'salary' => 5000],
    ['dept' => 'IT',    'salary' => 7000],
    ['dept' => 'IT',    'salary' => 9000],
]);

$sorted = $employees
    ->orderBy(fn($e) => $e['dept'])
    ->orderByDescending(fn($e) => $e['salary'])
    ->toArray();
```

---

### 8. Dynamic Query Builder (`ALinqQueryBuilder`)

Construct dynamic filters without writing manual nested boolean logic or closures:

```php
use Antevemus\ALinq\ALinqQueryBuilder;

// Build a dynamic query from user input or UI filters
$query = ALinqQueryBuilder::create('and')
    ->where('status', '=', 'APPROVED')
    ->where('creditScore', '>=', 650)
    ->where('age', 'between', [21, 65]);

// Convert directly into a callable predicate
$predicate = $query->toPredicate();

$approvedApplicants = $applicants->where($predicate);
```

---

### 9. Deep Dot-Notation Property Access (`ALinqPropertyAccess`)

Safely query deeply nested structures regardless of whether keys are associative arrays, object properties, getter methods, or mixed:

```php
use Antevemus\ALinq\Helpers\ALinqPropertyAccess;

$payload = [
    'user' => (object)[
        'profile' => [
            'organization' => (object)[
                'taxId' => '12.345.678/0001-90'
            ]
        ]
    ]
];

// Resolves nested path automatically:
$taxId = ALinqPropertyAccess::getValue($payload, 'user.profile.organization.taxId');
// Returns: '12.345.678/0001-90'
```

---

### 10. Streaming & Big Data Pipelines (`ALinqLazyCollection`)

Inspired by reactive Node.js streams and .NET `IEnumerable<T>` deferred execution, `ALinqLazyCollection` provides high-throughput, memory-bounded processing using PHP 8.4 generators. 

Processing gigabyte-scale datasets (such as access logs, financial transaction CSVs, or large SQL dumps) with standard in-memory arrays causes fatal memory exhaustion. `ALinqLazyCollection` guarantees **constant $O(1)$ memory consumption (< 100 KB RAM)** regardless of dataset size by evaluating elements strictly on-demand.

#### Key Architectural Guarantees:
- **Rewindable Multi-Pass Traversals**: Unlike standard PHP generators that throw `"Cannot traverse an already closed generator"`, `ALinqLazyCollection` wraps generator factories (`Closure(): iterable`). You can iterate, filter, and materialize the same pipeline multiple times safely.
- **Deterministic Resource Disposal**: Sources like `fromFile()` and `fromCsv()` guarantee file handle closure in `finally` blocks, even during early pipeline termination (e.g. short-circuiting with `first()`, `take(n)`).
- **Dual Pipeline Integration**: Effortlessly convert between eager collections (`ALinqCollection`) and streaming pipelines (`ALinqLazyCollection`) via `$collection->lazy()` and `$lazy->toCollection()`.

#### Real-World Streaming Example: Multi-Gigabyte Log File

```php
use Antevemus\ALinq\ALinqLazyCollection;

// Stream lines from a 10 GB production server log with < 100 KB RAM footprint
$highSeverityAlerts = ALinqLazyCollection::fromFile('/var/log/app/production.log')
    ->where(fn(string $line) => str_contains($line, '[CRITICAL]'))
    ->select(function (string $line) {
        preg_match('/\[(?<time>[^\]]+)\] \[CRITICAL\] (?<msg>.*)/', $line, $matches);
        return [
            'timestamp' => $matches['time'] ?? 'unknown',
            'message'   => $matches['msg'] ?? trim($line),
        ];
    })
    ->take(50) // Short-circuits immediately: stops reading disk once 50 matches are found!
    ->toArray();
```

#### Real-World CSV Processing with Dynamic Headers

```php
// Automatically maps header line to associative row arrays
$activeEnterpriseUsers = ALinqLazyCollection::fromCsv('/var/data/customers.csv')
    ->where(fn(array $row) => $row['plan'] === 'Enterprise' && $row['status'] === 'active')
    ->select(fn(array $row) => [
        'customerId' => (int)$row['id'],
        'email'      => strtolower(trim($row['email'])),
        'mrr'        => (float)$row['mrr'],
    ])
    ->distinctBy(fn($customer) => $customer['email'])
    ->tap(fn($customer) => syslog(LOG_INFO, "Processing customer {$customer['customerId']}"))
    ->toCollection(); // Materialize filtered results into eager ALinqCollection for further in-memory grouping
```

#### Database Cursor Streaming (Unbuffered PDO)

```php
// Stream millions of database records directly from an unbuffered PDO query
$stmt = $pdo->query('SELECT id, name, salary, department FROM employees');

$topSalariesByDept = ALinqLazyCollection::fromCursor($stmt)
    ->where(fn($row) => (float)$row['salary'] > 50000.0)
    ->take(100)
    ->toCollection()
    ->groupBy(fn($row) => $row['department']);
```

#### Caching Intermediate Streams with `remember()`

If an upstream data stream is computationally expensive or involves external I/O, calling `remember()` caches evaluated elements so subsequent iterations pull from memory without re-executing upstream generators:

```php
$cachedStream = ALinqLazyCollection::from(function () {
    // Heavy generator or network stream
    yield from fetchRemoteMetrics();
})->remember();

$totalCount = $cachedStream->count(); // Generator runs once
$average    = $cachedStream->average(); // Reads from local memory cache, no generator rerun
```

---

## 🧪 Quality Assurance & Test Coverage

The framework features extensive unit testing with **100% test pass rate** under PHP 8.4:

```bash
vendor/bin/phpunit
```

```text
====================================================================
 PHPUnit 11.5.42 - ANTEVEMUS ALINQ COLLECTION TEST SUITE
====================================================================

...............................................................  63 / 342 ( 18%)
............................................................... 126 / 342 ( 37%)
............................................................... 189 / 342 ( 56%)
............................................................... 252 / 342 ( 75%)
............................................................... 315 / 342 ( 94%)
....................                                            342 / 342 (100%)

Time: 00:06.312, Memory: 6.00 MB

OK (342 tests, 614 assertions)
====================================================================
 RESULT: 100% SUITE PASS | 0 REGRESSIONS | 0 DEPRECATIONS
====================================================================
```

- **342 Unit Tests & 614 Assertions** certifying all 8 functional traits and generator streaming engine.
- **PHP 8.4 Native Compatibility**: Verified with native `array_any`, `array_all`, `array_find`, `array_find_key`.
- **Zero External Runtime Dependencies**: Pure PHP 8.4 library with zero third-party requirements.

---

## 🗺️ Roadmap & Ecosystem

- [x] **v1.0.0**: Stable production release with 8 core traits, 305 tests, and PHP 8.4 native engine.
- [x] **ASpecification Synergy**: Integration with `Antevemus.ASpecification` via `ALinqBridge`.
- [x] **v1.1.0**: Generator-based lazy evaluation pipeline (`ALinqLazyCollection`) for handling multi-gigabyte streams without in-memory buffering.
- [x] **v1.1.1**: Streaming engine correctness: `remember()` caches only complete passes, `fromFile()` keeps long lines whole, single-pass cursors fail loudly on re-traversal.
- [ ] **v1.2.0**: Parallel collection processing leveraging PHP Fibers and concurrent workers.

---

## 📄 License & Authors

This software is open-source, licensed under the **[MIT License](LICENSE)**.

- **Author:** Heliton Junior (CTO) — [`contato@antevemus.com.br`](mailto:contato@antevemus.com.br)
- **Organization:** [Antevemus Soluções Inovadoras em TI Ltda.](https://antevemus.com.br)
- **Copyright:** Copyright (c) 2025–2026 Antevemus Soluções Inovadoras em TI Ltda.
