# Antevemus ALinq Collection

[![Latest Stable Version](https://img.shields.io/badge/release-v1.4.0-blue.svg)](https://github.com/antevemus-it/Antevemus.AlinqCollection/releases)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests Passing](https://img.shields.io/badge/tests-494%20passed%20%7C%201606%20assertions-success.svg)](tests/)
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
| **🌊 Streaming & Big Data** | `ALinqLazyCollection`, `fromFile`, `fromCsv`, `fromCursor`, `where`, `select`, `takeWhile`, `skipWhile`, `zip`, `tap`, `remember`, constant $O(1)$ RAM for the streaming operators |
| **🔍 Filtering & Slicing** | `where`, `take`, `skip`, `distinct`, `distinctBy`, `first`, `firstOrDefault`, `last`, `lastOrDefault`, `single`, `singleOrDefault`, `whereIn`, `whereNotIn`, `whereBetween`, `chunk`, `pad`, `shuffle`, `contains` |
| **🔗 Joining & Set Theory** | `join` (inner), `leftJoin`, `rightJoin`, `fullJoin` (outer, as .NET 10/11), `groupJoin` (hierarchical), `concat`, `intersect`, `intersectWith`, `intersectBy`, `except`, `exceptWith`, `exceptBy`, `unionBy`, `combine`, `replace`, `replaceRecursive` |
| **📊 Aggregation & Metrics** | `any`, `all`, `sum`, `average`, `min`, `minBy`, `max`, `maxBy`, `product`, `countValues`, `aggregate`, `aggregateBy`, `countBy` |
| **🎯 Selection & Projection** | `select`, `selectMany` (flattening), `column`, `toDictionary`, `toObject`, `flip`, `toArray` |
| **🗂️ Grouping & Bucketing** | `groupBy` (keyed sub-collections with full fluent chaining capabilities) |
| **⚡ Ordering & Sorting** | `orderBy`, `orderByDescending`, `thenBy`, `thenByDescending`, `orderByNatural` (human alphanumeric sort), `orderByCustom`, `orderByKey`, `reverse` |
| **🧭 Iteration & Traversal** | `each`, `eachRecursive`, `current`, `key`, `next`, `prev`, `reset`, `end`, full `IteratorAggregate` and `Countable` parity |
| **🛠️ Dynamic Query Builder** | `ALinqQueryBuilder` (`create('and'\|'or')`, `where('field', '>=', $val)`, `whereIn`, `whereNotIn`, `whereBetween`, `toPredicate()`) |
| **🔎 Deep Property Access** | `ALinqPropertyAccess` with dot-notation (`getValue($item, 'company.address.zip')`), getters and, as the last resort, non-public properties |

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
        "antevemus/alinq-collection": "^1.4"
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
use function Antevemus\ASpecification\DSL\greaterThan;

// 1. Define Business Rules using Evans & Fowler Specification Pattern
$isEligibleCustomer = Spec::allOf(
    Spec::property('status', is('ACTIVE')),
    Spec::property('creditScore', greaterThan(700))
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
$chunks = $collection->chunk(3);                       // 4 collections: [1, 2, 3], [4, 5, 6], [7, 8, 9], [10]
$sums = $chunks->select(fn($chunk) => $chunk->sum());  // [6, 15, 24, 10]
$padded = ALinqCollection::from([1, 2])->pad(5, 0); // [1, 2, 0, 0, 0]
```

#### Field filters: `whereIn()`, `whereNotIn()`, `whereBetween()` (since 1.4.0)

Shortcuts over `where()` that read a field through `ALinqPropertyAccess` (array key, getter, property, dot-notation path such as `'address.city'`). `whereIn`/`whereNotIn` compare by **strict** identity (`in_array(..., true)`: `'18'` is not `18`); `whereBetween` is **inclusive** and a `null` value is never between. Keys follow the contract: a list comes out reindexed, a dictionary keeps its keys.

```php
$staff = ALinqCollection::from([
    ['name' => 'Ana',   'dept' => 'IT',    'age' => 17],
    ['name' => 'Bruno', 'dept' => 'HR',    'age' => 18],
    ['name' => 'Carla', 'dept' => 'Sales', 'age' => 65],
    ['name' => 'Davi',  'dept' => 'IT',    'age' => null],
]);

$staff->whereIn('dept', ['IT', 'HR'])->column('name')->toArray(); // ['Ana', 'Bruno', 'Davi']
$staff->whereNotIn('dept', ['IT'])->column('name')->toArray();    // ['Bruno', 'Carla']
$staff->whereBetween('age', 18, 65)->column('name')->toArray();   // ['Bruno', 'Carla'] (bounds included, null never between)
$staff->whereIn('age', ['18'])->count();                          // 0 (strict: '18' is not 18)
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
    resultSelector: fn($dept, ALinqCollection $matchedEmps) => [
        'dept'  => $dept['name'],
        'staff' => $matchedEmps->column('name')->toArray(),
    ]
)->toArray();

// 3. Set Operations with Key Selectors
$datasetA = ALinqCollection::from([['id' => 1], ['id' => 2], ['id' => 3]]);
$datasetB = [['id' => 2], ['id' => 3], ['id' => 4]];

$intersect = $datasetA->intersectBy($datasetB, fn($item) => $item['id']); // [id: 2, id: 3]
$difference = $datasetA->exceptBy($datasetB, fn($item) => $item['id']);   // [id: 1]
$union = $datasetA->unionBy($datasetB, fn($item) => $item['id']);        // [id: 1, id: 2, id: 3, id: 4]
```

#### Outer joins: `leftJoin()`, `rightJoin()`, `fullJoin()` (since 1.4.0)

The outer joins of .NET 10/11 LINQ (`Enumerable.LeftJoin`, `RightJoin`, `FullJoin`), with the signature `leftJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null)` (same for `rightJoin`/`fullJoin`). They replace the `groupJoin()` + `selectMany()` written by hand. The result selector receives `($outer, $inner)` with `null` on the side without a match; without a selector each result is the pair `[$outer, $inner]`. The result is always a list. Keys match by the strict identity of `join()`/`groupJoin()`, and a `null` key never matches: that item comes out without a partner.

- `leftJoin()`: every outer item, in outer order (once per match, or once with `null`).
- `rightJoin()`: every inner item, in inner order (as in .NET), each with its matching outer items in outer order, or with `null`.
- `fullJoin()`: the pairs and the unmatched outer items in outer order, then the unmatched inner items in inner order.

```php
$orders = ALinqCollection::from([
    ['id' => 1, 'customer' => 10],
    ['id' => 2, 'customer' => null], // a null key never matches
    ['id' => 3, 'customer' => 99],   // no such customer
    ['id' => 4, 'customer' => 10],
]);
$customers = [
    ['id' => 10, 'name' => 'Acme'],
    ['id' => 20, 'name' => 'Globex'], // no orders
];

$orderKey    = fn($o) => $o['customer'];
$customerKey = fn($c) => $c['id'];
$label       = fn(?array $o, ?array $c) => ($o['id'] ?? '-') . ':' . ($c['name'] ?? '-');

$orders->leftJoin($customers, $orderKey, $customerKey, $label)->toArray();
// ['1:Acme', '2:-', '3:-', '4:Acme']
$orders->rightJoin($customers, $orderKey, $customerKey, $label)->toArray();
// ['1:Acme', '4:Acme', '-:Globex']
$orders->fullJoin($customers, $orderKey, $customerKey, $label)->toArray();
// ['1:Acme', '2:-', '3:-', '4:Acme', '-:Globex']

// Without a result selector: pairs [$outer, $inner]
[$order, $customer] = $orders->leftJoin($customers, $orderKey, $customerKey)->toArray()[1];
// $order = ['id' => 2, 'customer' => null], $customer = null
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

// single(): exactly one element, or exactly one that matches (LINQ Single), else it throws (since 1.4.0)
$refund = $orders->single(fn($o) => $o['status'] === 'REFUNDED')['code']; // 'ORD-4'
$orders->single(fn($o) => $o['status'] === 'CANCELLED'); // UnderflowException: Sequence contains no matching element.
$orders->single(fn($o) => $o['status'] === 'COMPLETED'); // OverflowException: Sequence contains more than one matching element.
ALinqCollection::from([])->single();                     // UnderflowException: Sequence contains no elements.
$orders->single();                                       // OverflowException: Sequence contains more than one element.
$orders->singleOrDefault(null, fn($o) => $o['status'] === 'CANCELLED'); // null (the tolerant variant)
```

`single()` throws the same exception classes and messages on the eager and the lazy collection; `singleOrDefault()` returns the default when nothing matches and still throws `OverflowException` on more than one match.

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

$employees = ALinqCollection::from([
    ['name' => 'Ana',   'dept' => 'Sales', 'salary' => 5000],
    ['name' => 'Bruno', 'dept' => 'IT',    'salary' => 7000],
    ['name' => 'Carla', 'dept' => 'IT',    'salary' => 9000],
    ['name' => 'Davi',  'dept' => 'IT',    'salary' => 7000],
]);

// Composite ordering (since 1.4.0): orderBy() followed by any number of thenBy()/thenByDescending()
$sorted = $employees
    ->orderBy(fn($e) => $e['dept'])
    ->thenByDescending(fn($e) => $e['salary'])
    ->column('name')
    ->toArray();
// ['Carla', 'Bruno', 'Davi', 'Ana']: IT 9000, IT 7000, IT 7000, Sales 5000 (Bruno before Davi: stable)

// A comparer receives the two keys: fn($keyA, $keyB): int
$byNameLength = $employees
    ->orderBy(fn($e) => $e['dept'])
    ->thenBy(fn($e) => $e['name'], fn($a, $b) => strlen($a) <=> strlen($b))
    ->column('name')
    ->toArray();
// ['Davi', 'Bruno', 'Carla', 'Ana']

// thenBy() refines only a collection that comes straight from orderBy*()/thenBy*()
$employees->thenBy(fn($e) => $e['name']);
// LogicException: thenBy() requires a preceding orderBy().
$employees->orderBy(fn($e) => $e['dept'])->where(fn($e) => true)->thenByDescending(fn($e) => $e['salary']);
// LogicException: thenByDescending() requires a preceding orderBy().

// orderByCustom() still sorts with one comparison over whole items
$sameOrder = $employees
    ->orderByCustom(fn($a, $b) => [$a['dept'], -$a['salary']] <=> [$b['dept'], -$b['salary']])
    ->column('name')
    ->toArray();
// ['Carla', 'Bruno', 'Davi', 'Ana']
```

`orderBy()`/`orderByDescending()` are stable and call each key selector once per item. `thenBy()`/`thenByDescending()` add a criterion that only decides between items tied on every previous criterion; a full tie keeps the source order. Their key selector receives `($item, $key)` with the key of the source, before ordering. A second `orderBy()` starts a new ordering (its key becomes the primary one), as in LINQ.

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

Operators (case- and separator-insensitive): `=`, `==`, `===`, `!=`, `<>`, `!==`, `>`, `>=`, `<`, `<=`, `in`, `notIn`, `between`, `notBetween`, `isNull`, `isNotNull`, `contains`, `startsWith`, `endsWith`. `between` takes an inclusive `[min, max]` pair. The same evaluator is exposed as `ALinqQueryBuilder::operatorPredicate($operator, $value)` and backs `createPredicate()`.

Shortcuts (since 1.4.0): `whereIn($field, $values)`, `whereNotIn($field, $values)` and `whereBetween($field, $min, $max)` are exactly `where($field, 'in' | 'notIn' | 'between', ...)`:

```php
$applicants = ALinqCollection::from([
    ['name' => 'Ana',   'status' => 'APPROVED', 'country' => 'BR', 'age' => 30],
    ['name' => 'Bruno', 'status' => 'REVIEW',   'country' => 'XX', 'age' => 40],
    ['name' => 'Carla', 'status' => 'REJECTED', 'country' => 'BR', 'age' => 25],
    ['name' => 'Davi',  'status' => 'APPROVED', 'country' => 'PT', 'age' => 70],
]);

$query = ALinqQueryBuilder::create('and')
    ->whereIn('status', ['APPROVED', 'REVIEW'])
    ->whereNotIn('country', ['XX'])
    ->whereBetween('age', 21, 65);

$applicants->where($query->toPredicate())->column('name')->toArray(); // ['Ana']
```

> **Note:** in the builder, `in`/`notIn` compare **loosely** (`'18'` is in `[18]`), strictly only when the value read is `null`. The collection methods of the same names (§2) compare **strictly**: they are sugar over the collection's `where()`, not over the builder.

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

Resolution order (the same as the `PropertyAccessor` of Antevemus.ASpecification 1.4.4):

1. array or `ArrayAccess` by key;
2. a public getter (`getX()`, `x()`, `isX()`, `hasX()`);
3. `__get` guarded by `__isset`;
4. a public initialized property;
5. since 1.4.0, as the last resort, a **non-public property** (private or protected, static too) declared by the class or one of its ancestors, read by reflection without calling any method; an uninitialized one resolves to `null`.

Otherwise `null`, never an `Error`. A dot is always a path. `ALinqPropertyAccess::hasProperty($item, 'a.b')` tells whether the path resolves and follows the same order.

```php
final class Invoice
{
    public function __construct(private string $number, private float $total) {}
    public function getTotal(): float { return $this->total; }
}

$invoice = new Invoice('NF-001', 99.9);

ALinqPropertyAccess::getValue($invoice, 'total');     // 99.9 (public getter)
ALinqPropertyAccess::getValue($invoice, 'number');    // 'NF-001' (private property, last resort; null before 1.4.0)
ALinqPropertyAccess::hasProperty($invoice, 'number'); // true (false before 1.4.0)
ALinqPropertyAccess::getValue($invoice, 'missing');   // null
```

The last step reaches every entry point that reads a property: `ALinqQueryBuilder::where()`, `whereIn()`/`whereNotIn()`/`whereBetween()`, `createPropertySelector()` and `createPropertyComparer()`.

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

#### Ordering and outer joins accumulate data (since 1.4.0)

The lazy collection has `orderBy()`/`orderByDescending()` with `thenBy()`/`thenByDescending()`, and the three outer joins, with the same answers as the eager side. They stay deferred (nothing runs until the first traversal), but they are **not** constant-memory:

- `orderBy*()`/`thenBy*()` buffer the whole upstream when traversed, then sort once with every criterion;
- `leftJoin()`/`fullJoin()` buffer and index the inner side and stream the lazy collection;
- `rightJoin()` buffers and indexes the lazy collection itself and streams the inner side (the result follows the inner order).

```php
$sorted = ALinqLazyCollection::range(1, 6)
    ->orderBy(fn($n) => $n % 3)          // deferred: nothing runs yet
    ->thenByDescending(fn($n) => $n)
    ->toArray();                          // [6, 3, 4, 1, 5, 2]
```

#### Irregular input (since 1.3.2)

Irregular input fails loudly or is an explicit choice, never a silent reshape:

- `fromCsv()` with a header is **strict by default**: a record whose number of fields differs from the header throws `RuntimeException('CSV row N has K fields, header has H')` when it is read (the previous records were already delivered; `N` counts data records from 1, blank lines excluded). `fromCsv($path, strict: false)` delivers that record as a positional list (the 1.3.1 behaviour, for dirty files). Without a header there is no reference width.
- A blank line is not a record: it is skipped in every mode.
- A UTF-8 BOM at the start of the file is removed before parsing in `fromCsv()` (a quoted first field stays correct) and from the first line in `fromFile()`.
- A directory passed to `fromFile()`/`fromCsv()` throws `RuntimeException('Path is a directory, not a file: "<path>"')` on the first traversal.
- One statement, one collection: a second `fromCursor()` (or `from()`) over the same `PDOStatement` throws `RuntimeException('PDOStatement already bound to another lazy collection')` on its first traversal. To iterate again use `remember()`; to run the query again use a new statement, prepared statements included.

```php
ALinqLazyCollection::fromCsv('clients.csv')->toArray();                // width mismatch → RuntimeException("CSV row 2 has 1 fields, header has 2")
ALinqLazyCollection::fromCsv('clients.csv', strict: false)->toArray(); // 1.3.1 behaviour: the irregular record comes as a list
```

### 11. The Contract: Keys, Equality, Empty Collections (since 1.3.0)

Both collections obey one written contract, enforced by a parity test that runs every shared operation over the same inputs on the eager and the lazy side (`tests/Contract/ParityTest.php`, zero divergences allowed). When an operation throws, the exception class and, since 1.4.0, its message must be the same on both sides; a second matrix runs the field- and key-based operators (`whereIn`, `whereBetween`, the outer joins, `thenBy`, `single`) over rows given as arrays and as entities with private fields.

| Rule | Behaviour |
|---|---|
| **Keys** | A **list** (`array_is_list()`) comes out **reindexed** from every filtering, slicing, set or ordering operation; a **dictionary** (any other key shape, including `[10 => 'a', 20 => 'b']`) **keeps its keys**. `select()` always preserves keys. `concat()`, `selectMany()`, `join()`, `groupJoin()`, `leftJoin()`, `rightJoin()`, `fullJoin()`, `zip()`, `unionBy()` always produce a list. `chunk()` is a list of collections (each chunk follows the rule of its source). The lazy collection knows whether its source is a list (`from(array)`, `fromFile()`, `fromCsv()`, `fromCursor()`, `range()`, `repeat()`); a raw generator is "unknown" and keeps its keys while streaming, and `toArray()` reindexes it only when every key is an integer. Materialization never loses an item: a repeated key is appended. |
| **Equality** | Elements and keys are compared by **strict, type-aware identity** everywhere: `distinct`, `distinctBy`, `intersect`, `except`, `intersectBy`, `exceptBy`, `unionBy`, `join`, `groupJoin`, `leftJoin`, `rightJoin`, `fullJoin`, `contains`, and the collection `whereIn`/`whereNotIn`. `1`, `'1'`, `1.0` and `true` are four different values; arrays compare by value; objects by identity. A `null` key never matches in `join`/`groupJoin` or in the outer joins (LINQ and SQL semantics; in an outer join that item comes out without a partner). A database column that arrives as a string does not match an integer key: cast on your side. A custom comparer (`contains($v, $cmp)`, `intersectWith`, `exceptWith`) may return `bool` (`true` = equal) or a `<=>` style integer (`0` = equal). |
| **Group keys** | `groupBy`, `countBy`, `aggregateBy` and `toDictionary` accept `int`, `string` and `BackedEnum` (its value) keys; `null`, `bool`, `float`, arrays and objects throw `InvalidArgumentException` instead of being silently coerced. `toDictionary()` throws on a repeated key. |
| **Empty collections** | As in LINQ: `first()`, `last()`, `min()`, `max()`, `minBy()`, `maxBy()`, `average()` throw `UnderflowException` on an empty collection or when no element matches; `firstOrDefault()`, `lastOrDefault()`, `singleOrDefault()` return the default; `singleOrDefault()` throws `OverflowException` on more than one match; `single()` (1.4.0) throws `UnderflowException('Sequence contains no elements.')` / `('Sequence contains no matching element.')` and `OverflowException('Sequence contains more than one element.')` / `('Sequence contains more than one matching element.')`; `random()` throws `UnderflowException` like `first()` and `random(n > 1)` returns an empty collection like `take(n)`; `sum()` of nothing is `0`, `product()` of nothing is `1`; `any()` means "has at least one item"; `all($p)` of nothing is `true`; `all()` without predicate means "every item is truthy". |
| **Ordering** (1.4.0) | `orderBy()`/`orderByDescending()` are stable; `thenBy()`/`thenByDescending()` refine a collection that comes straight from `orderBy*()`/`thenBy*()` and throw `LogicException('thenBy() requires a preceding orderBy().')` (or `thenByDescending() ...`) after any other operation; a full tie keeps the source order; key selectors receive `($item, $key)` with the source key; a comparer receives the two keys, `fn($keyA, $keyB): int`. |
| **Arguments** | `take`/`skip`/`pad` with a negative count, `chunk(0)`, `random(0)`, `range()` with a non-positive step throw `InvalidArgumentException` (nothing slices from the tail or is silently ignored). |
| **Numbers** | `sum`, `average`, `min`, `max`, `product` **ignore `null`** (`average([1, null, 2])` is `1.5`); when only `null` remains the collection counts as empty. A non-numeric value (string, array, object) throws `InvalidArgumentException` in `sum`/`average`/`product`; `min`/`max` compare any scalar or `DateTimeInterface`. |
| **Callbacks** | A callback receives `($item, $key)` when it accepts two parameters and the item alone otherwise, so `where('is_int')`, `select('trim')` and `fn($v, $k) => ...` all work in every operator of both collections. |
| **Immutability** | No query operation mutates the collection it was called on. `each()` iterates over a copy: a by-reference callback does not change the collection (use `select()` to transform). |
| **Query builder** | `toPredicate()` is a snapshot: later `where()` calls do not change a predicate already returned. `create($mode)` accepts `and`/`or` (any case) and throws otherwise. When the property resolves to `null`, `>`, `>=`, `<`, `<=`, `between`, `notBetween`, `contains`, `startsWith`, `endsWith` are `false`; `=`/`==` and `!=`/`<>` compare strictly against `null` (`where('x', '=', null)` means "is null"); a missing property resolves to `null`. |
| **JSON** | Both collections implement `JsonSerializable`: a list encodes as a JSON array, a dictionary as a JSON object. |

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

...............................................................  63 / 494 ( 12%)
............................................................... 126 / 494 ( 25%)
............................................................... 189 / 494 ( 38%)
............................................................... 252 / 494 ( 51%)
............................................................... 315 / 494 ( 63%)
............................................................... 378 / 494 ( 76%)
............................................................... 441 / 494 ( 89%)
.....................................................           494 / 494 (100%)

Time: 00:00.136, Memory: 8.00 MB

OK (494 tests, 1606 assertions)
====================================================================
 RESULT: 100% SUITE PASS | 0 REGRESSIONS | 0 DEPRECATIONS
====================================================================
```

- **494 Tests & 1606 Assertions** certifying all 8 functional traits, the generator streaming engine and the eager × lazy contract (`tests/Contract`).
- **Measured coverage** (pcov, 1.4.0): 199 of 203 methods and 1159 of 1165 lines of `src/`; the six uncovered lines are defensive guards (four in the property accessor: `catch` blocks around reflection and the empty-name check of the non-public lookup; one in the shared outer join: the unknown-kind check).
- **PHP 8.4 Native Compatibility**: Verified with native `array_any`, `array_all`, `array_find`, `array_find_key`.
- **Zero External Runtime Dependencies**: Pure PHP 8.4 library with zero third-party requirements.

---

## 🗺️ Roadmap & Ecosystem

- [x] **v1.0.0**: Stable production release with 8 core traits, 305 tests, and PHP 8.4 native engine.
- [x] **ASpecification Synergy**: Integration with `Antevemus.ASpecification` via `ALinqBridge`.
- [x] **v1.1.0**: Generator-based lazy evaluation pipeline (`ALinqLazyCollection`) for handling multi-gigabyte streams without in-memory buffering.
- [x] **v1.1.1**: Streaming engine correctness: `remember()` caches only complete passes, `fromFile()` keeps long lines whole, single-pass cursors fail loudly on re-traversal.
- [x] **v1.1.2**: Lazy materializers never lose items (list reindexed, dictionary preserved), resumable `remember()`, `last()` with the real key.
- [x] **v1.2.0**: README promises I: `groupBy()` groups are collections, `select()` receives the key, every operator accepts native callables, `distinct()` is strict and safe for arrays/objects, `selectMany()` flattens any iterable, `between`/`notIn`/`isNull` in the query builder, dot-notation and getter resolution in `ALinqPropertyAccess`.
- [x] **v1.3.0**: The contract: one key rule, strict identity, LINQ semantics on empty collections, validated arguments and group keys, `null`-aware aggregations and query builder, `count($predicate)`, `JsonSerializable`; eager × lazy parity enforced by test (§11).
- [x] **v1.3.2**: Lazy sources hardening: irregular CSV rows, BOM, directories in `fromFile()`, one statement per `fromCursor()` collection.
- [x] **v1.4.0**: `whereIn()` / `whereNotIn()` / `whereBetween()` / `single()`, announced in the 0.1.0 release notes and shipped at last (see the CHANGELOG erratum).
- [x] **v1.4.0**: `leftJoin()` / `rightJoin()` / `fullJoin()`: outer joins returning `[$outer, $inner]` pairs with `null` on the side without a match, as `Enumerable.LeftJoin`/`RightJoin`/`FullJoin` in .NET 10/11 (they replace `groupJoin()` + `selectMany()` by hand).
- [x] **v1.4.0**: `thenBy()` / `thenByDescending()`: composite, stable multi-key ordering, on the lazy collection too.
- [ ] **Shared property accessor** with `Antevemus.ASpecification` (today both ship the same resolution order, including the non-public last resort since 1.4.0; extracting a common package is a 2.0 topic).
- [ ] **Future**: Parallel collection processing leveraging PHP Fibers and concurrent workers.

The milestone view of this list, with target releases, lives in [ROADMAP.md](ROADMAP.md).

---

## 📄 License & Authors

This software is open-source, licensed under the **[MIT License](LICENSE)**.

- **Author:** Heliton Junior (CTO) — [`contato@antevemus.com.br`](mailto:contato@antevemus.com.br)
- **Organization:** [Antevemus Soluções Inovadoras em TI Ltda.](https://antevemus.com.br)
- **Copyright:** Copyright (c) 2025–2026 Antevemus Soluções Inovadoras em TI Ltda.
