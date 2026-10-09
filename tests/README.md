# Antevemus ALinq Collection — Test Suite

Comprehensive PHPUnit 11 test suite certifying the **Antevemus ALinq Collection** framework.

## 📊 Overview & Metrics

- **Test Framework:** PHPUnit 11.5+ (configured for PHP 8.4+)
- **Test Files:** 13 unit test files (`tests/Unit/`) and 2 contract test files (`tests/Contract/`)
- **Total Tests:** 494 tests (1.4.0)
- **Total Assertions:** 1606 assertions
- **Lines of Test Code:** 8,500+ lines
- **Success Rate:** 100% Passing (0 failures, 0 errors, 0 deprecations)
- **Runtime Dependencies:** Zero (pure PHP 8.4 native engine)

---

## 🏛️ Test Suite Structure

The test suite is structured around the modular trait architecture of the collection engine:

### 1. `ALinqCollectionTest.php` (23 tests)
Validates core collection lifecycle, factory methods, and standard PHP interfaces:
- `from()` — Collection instantiation from native PHP arrays
- `range()` — Arithmetic sequence generation
- `repeat()` — Element repetition
- `empty()` — Empty collection creation
- `getIterator()` — `IteratorAggregate` compliance
- `count()` — `Countable` interface compliance
- `toArray()` & `toObject()` — Export transformations

### 2. `FilteringOperationsTest.php` (60 tests)
Validates the filtering and slicing operations:
- `where()` — Predicate-based filtering and automatic numeric array reindexing
- `whereIn()`, `whereNotIn()`, `whereBetween()` — Field filters (strict `in`/`notIn`, inclusive `between`, `null` never between; 1.4.0)
- `take()` & `skip()` — Pagination and slicing
- `distinct()` & `distinctBy()` — Deduplication with key selectors
- `first()`, `firstOrDefault()` — Head element extraction with fallback defaults
- `last()`, `lastOrDefault()` — Tail element extraction with fallback defaults
- `singleOrDefault()` — Cardinality validation (single element or default)
- `findKey()`, `keyExists()` — Key lookup and presence checks
- `chunk()`, `pad()`, `shuffle()`, `contains()` — Utility partitioning

### 3. `JoiningOperationsTest.php` (41 tests)
Validates the relational joins and set-theoretic operations:
- `join()` — SQL-style inner join with key selectors and result projection
- `leftJoin()`, `rightJoin()`, `fullJoin()` — Outer joins as in .NET 10/11 (pairs or result selector, `null` key never matches, order of each join; 1.4.0)
- `groupJoin()` — Hierarchical left outer join
- `concat()` — Sequence concatenation
- `intersect()`, `intersectWith()`, `intersectBy()` — Set intersection with custom comparers
- `except()`, `exceptWith()`, `exceptBy()` — Set difference with custom comparers
- `unionBy()` — Set union with key selectors
- `combine()`, `replace()`, `replaceRecursive()` — Key/value merging and replacement

### 4. `AggregationOperationsTest.php` (54 tests)
Validates the aggregation and metric operations leveraging PHP 8.4 C-level functions:
- `single()` — Exactly one element, `UnderflowException`/`OverflowException` with LINQ messages (1.4.0)
- `any()` — Quantifier leveraging native `array_any()`
- `all()` — Quantifier leveraging native `array_all()`
- `sum()`, `average()`, `product()` — Numeric calculations
- `min()`, `minBy()`, `max()`, `maxBy()` — Extremum extraction
- `countValues()` — Element frequency distribution
- `aggregate()`, `aggregateBy()`, `countBy()` — Custom seed-accumulator folds and group counting

### 5. `SelectionOperationsTest.php` (29 tests)
Validates 6 projection and transformation operations:
- `select()` — 1-to-1 element mapping
- `selectMany()` — 1-to-N flattening of nested collections
- `column()` — Column extraction
- `toDictionary()` — Associative key-value map projection
- `toObject()` — `stdClass` conversion
- `flip()` — Key-value inversion

### 6. `GroupingOperationsTest.php` (17 tests)
Validates collection partitioning:
- `groupBy()` — Grouping elements by key selector, yielding nested `ALinqCollection` instances

### 7. `OrderingOperationsTest.php` (37 tests)
Validates the sorting and ordering operations:
- `orderBy()` & `orderByDescending()` — Stable ascending/descending sorting
- `thenBy()` & `thenByDescending()` — Composite ordering, comparer over keys, `LogicException` without a preceding `orderBy()` (1.4.0)
- `orderByNatural()` — Human-friendly alphanumeric natural sorting
- `orderByCustom()` — Sorting via custom comparison closures
- `orderByKey()` — Associative key sorting
- `reverse()` — Sequence order inversion

### 8. `IteratorOperationsTest.php` (10 tests)
Validates low-level array pointer traversal:
- `current()`, `key()`, `next()`, `prev()`, `reset()`, `end()`

### 9. `UtilityOperationsTest.php` (48 tests)
Validates utility and helper operations:
- `isList()` — Verifies sequential numeric 0-indexed list structure
- `each()`, `eachRecursive()` — Side-effect iteration
- `random()` — Random element sampling
- `extract()` — Variable extraction
- `createPredicate()`, `createPropertySelector()` — Dynamic predicate helpers

### 10. `ALinqQueryBuilderTest.php` (55 tests)
Validates dynamic query composition:
- `create('and'|'or')` — Builder instantiation
- `where()` & `whereCustom()` — Criteria addition
- `whereIn()`, `whereNotIn()`, `whereBetween()` — Shortcuts over `in`/`notIn`/`between` (1.4.0)
- `toPredicate()` — Compilation into high-speed callable closures
- Supported operators: `=`, `==`, `===`, `!=`, `<>`, `!==`, `>`, `>=`, `<`, `<=`, `in`, `notIn`, `between`, `notBetween`, `isNull`, `isNotNull`, `contains`, `startsWith`, `endsWith`

### 11. `ALinqPropertyAccessTest.php` (48 tests)
Validates universal property resolution:
- Dot-notation traversal (`user.address.city`)
- Arrays, stdClass objects, public properties, and getter methods
- Non-public properties of the class and its ancestors as the last resort, static and uninitialized ones included (1.4.0)

### 12. `ALinqLazyCollectionTest.php` (64 tests)
Validates the streaming, generator-based pipeline engine:
- Constant $O(1)$ memory usage with 500,000+ element streams (< 100 KB RAM)
- Re-traversable streams via closure factory encapsulation
- `fromFile()` with automatic file handle closure in `finally` blocks
- `fromCsv()` with associative header mapping
- `fromCursor()` with unbuffered PDO statement streams
- Lazy operators (`where`, `whereNot`, `whereIn`, `whereNotIn`, `whereBetween`, `select`, `selectMany`, `take`, `skip`, `takeWhile`, `skipWhile`, `distinct`, `chunk`, `pad`, `concat`, `zip`, `tap`, `remember`)
- Accumulating operators (`orderBy`, `orderByDescending`, `thenBy`, `thenByDescending`, `leftJoin`, `rightJoin`, `fullJoin`; 1.4.0)
- Short-circuiting terminal operations (`first`, `firstOrDefault`, `last`, `lastOrDefault`, `single`, `singleOrDefault`, `any`, `all`, `contains`)
- Arithmetic streaming aggregations (`sum`, `average`, `min`, `max`, `minBy`, `maxBy`, `aggregate`)
- Interoperability bridges (`ALinqCollection::lazy()` and `ALinqLazyCollection::toCollection()`)

### 13. `ALinqCallableTest.php` (2 tests)
Validates the shared callback helper:
- `withKey()` — The arity rule (`($item, $key)` or the item alone), variadic callbacks included
- `hashKey()` — Strict, type-aware identity buckets

### 14. `tests/Contract/ParityTest.php` (3 tests)
Enforces the eager × lazy contract (README §11):
- Every operation both collections share runs over the same seven inputs on both sides; results are compared strictly, keys included, and when an operation throws the exception class and message (1.4.0) must match
- A second matrix runs the field- and key-based operators (`whereIn`, `whereBetween`, outer joins, `thenBy`, `single`) over rows given as arrays and as entities with private fields
- A guard fails when a public method shared by both classes is missing from the matrix

### 15. `tests/Contract/AccessorContractTest.php` (3 tests)
Keeps `ALinqPropertyAccess` the single property resolver:
- The five public entry points that read a property (`getPropertyAccessor`, `getNestedPropertyAccessor`, `createPropertyComparer`, `createPropertySelector`, `ALinqQueryBuilder::where`) agree with `getValue()` on the same 13 cases, the same resolution order as the ASpecification 1.4.4 `PropertyAccessor`

---

## 🚀 Running the Tests

### Execute Entire Test Suite
```bash
vendor/bin/phpunit
```

### Detailed Test Execution (Testdox)
```bash
vendor/bin/phpunit --testdox
```

### Run a Specific Test Suite
```bash
vendor/bin/phpunit tests/Unit/ALinqLazyCollectionTest.php
```

### Filter by Test Name
```bash
vendor/bin/phpunit --filter testLargeDatasetMaintainsConstantMemoryUsage
```

### Code Coverage Report (Requires Xdebug or PCOV)
```bash
# HTML Coverage Report
vendor/bin/phpunit --coverage-html coverage/html

# Text Coverage Summary
vendor/bin/phpunit --coverage-text
```

---

## 📄 License & Copyright

- **License:** MIT License — see [LICENSE](../LICENSE)
- **Copyright:** Copyright (c) 2025–2026 Antevemus Soluções Inovadoras em TI Ltda.
