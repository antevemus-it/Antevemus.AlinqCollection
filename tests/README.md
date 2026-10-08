# Antevemus ALinq Collection — Test Suite

Comprehensive PHPUnit 11 test suite certifying the **Antevemus ALinq Collection** framework.

## 📊 Overview & Metrics

- **Test Framework:** PHPUnit 11.5+ (configured for PHP 8.4+)
- **Test Files:** 12 Unit Test Suites (`tests/Unit/`)
- **Total Tests:** 387 tests
- **Total Assertions:** 1050 assertions
- **Lines of Test Code:** 5,100+ lines
- **Success Rate:** 100% Passing (0 failures, 0 errors, 0 deprecations)
- **Runtime Dependencies:** Zero (pure PHP 8.4 native engine)

---

## 🏛️ Test Suite Structure

The test suite is structured around the modular trait architecture of the collection engine:

### 1. `ALinqCollectionTest.php` (19 tests)
Validates core collection lifecycle, factory methods, and standard PHP interfaces:
- `from()` — Collection instantiation from native PHP arrays
- `range()` — Arithmetic sequence generation
- `repeat()` — Element repetition
- `empty()` — Empty collection creation
- `getIterator()` — `IteratorAggregate` compliance
- `count()` — `Countable` interface compliance
- `toArray()` & `toObject()` — Export transformations

### 2. `FilteringOperationsTest.php` (53 tests)
Validates all 17 filtering and slicing operations:
- `where()` — Predicate-based filtering and automatic numeric array reindexing
- `take()` & `skip()` — Pagination and slicing
- `distinct()` & `distinctBy()` — Deduplication with key selectors
- `first()`, `firstOrDefault()` — Head element extraction with fallback defaults
- `last()`, `lastOrDefault()` — Tail element extraction with fallback defaults
- `singleOrDefault()` — Cardinality validation (single element or default)
- `findKey()`, `keyExists()` — Key lookup and presence checks
- `chunk()`, `pad()`, `shuffle()`, `contains()` — Utility partitioning

### 3. `JoiningOperationsTest.php` (33 tests)
Validates all 13 relational joins and set-theoretic operations:
- `join()` — SQL-style inner join with key selectors and result projection
- `groupJoin()` — Hierarchical left outer join
- `concat()` — Sequence concatenation
- `intersect()`, `intersectWith()`, `intersectBy()` — Set intersection with custom comparers
- `except()`, `exceptWith()`, `exceptBy()` — Set difference with custom comparers
- `unionBy()` — Set union with key selectors
- `combine()`, `replace()`, `replaceRecursive()` — Key/value merging and replacement

### 4. `AggregationOperationsTest.php` (48 tests)
Validates all 13 aggregation and metric operations leveraging PHP 8.4 C-level functions:
- `any()` — Quantifier leveraging native `array_any()`
- `all()` — Quantifier leveraging native `array_all()`
- `sum()`, `average()`, `product()` — Numeric calculations
- `min()`, `minBy()`, `max()`, `maxBy()` — Extremum extraction
- `countValues()` — Element frequency distribution
- `aggregate()`, `aggregateBy()`, `countBy()` — Custom seed-accumulator folds and group counting

### 5. `SelectionOperationsTest.php` (26 tests)
Validates 6 projection and transformation operations:
- `select()` — 1-to-1 element mapping
- `selectMany()` — 1-to-N flattening of nested collections
- `column()` — Column extraction
- `toDictionary()` — Associative key-value map projection
- `toObject()` — `stdClass` conversion
- `flip()` — Key-value inversion

### 6. `GroupingOperationsTest.php` (16 tests)
Validates collection partitioning:
- `groupBy()` — Grouping elements by key selector, yielding nested `ALinqCollection` instances

### 7. `OrderingOperationsTest.php` (43 tests)
Validates 6 sorting and ordering operations:
- `orderBy()` & `orderByDescending()` — Ascending/descending sorting
- `orderByNatural()` — Human-friendly alphanumeric natural sorting
- `orderByCustom()` — Sorting via custom comparison closures
- `orderByKey()` — Associative key sorting
- `reverse()` — Sequence order inversion

### 8. `IteratorOperationsTest.php` (16 tests)
Validates low-level array pointer traversal:
- `current()`, `key()`, `next()`, `prev()`, `reset()`, `end()`

### 9. `UtilityOperationsTest.php` (37 tests)
Validates utility and helper operations:
- `isList()` — Verifies sequential numeric 0-indexed list structure
- `each()`, `eachRecursive()` — Side-effect iteration
- `random()` — Random element sampling
- `extract()` — Variable extraction
- `createPredicate()`, `createPropertySelector()` — Dynamic predicate helpers

### 10. `ALinqQueryBuilderTest.php` (31 tests)
Validates dynamic query composition:
- `create('and'|'or')` — Builder instantiation
- `where()` & `whereCustom()` — Criteria addition
- `toPredicate()` — Compilation into high-speed callable closures
- Supported operators: `=`, `==`, `===`, `!=`, `<>`, `!==`, `>`, `>=`, `<`, `<=`, `in`, `contains`, `startsWith`, `endsWith`, `between`

### 11. `ALinqPropertyAccessTest.php` (14 tests)
Validates universal property resolution:
- Dot-notation traversal (`user.address.city`)
- Arrays, stdClass objects, public properties, and getter methods

### 12. `ALinqLazyCollectionTest.php` (30 tests)
Validates the streaming, generator-based pipeline engine:
- Constant $O(1)$ memory usage with 500,000+ element streams (< 100 KB RAM)
- Re-traversable streams via closure factory encapsulation
- `fromFile()` with automatic file handle closure in `finally` blocks
- `fromCsv()` with associative header mapping
- `fromCursor()` with unbuffered PDO statement streams
- Lazy operators (`where`, `whereNot`, `select`, `selectMany`, `take`, `skip`, `takeWhile`, `skipWhile`, `distinct`, `chunk`, `pad`, `concat`, `zip`, `tap`, `remember`)
- Short-circuiting terminal operations (`first`, `firstOrDefault`, `last`, `lastOrDefault`, `singleOrDefault`, `any`, `all`, `contains`)
- Arithmetic streaming aggregations (`sum`, `average`, `min`, `max`, `minBy`, `maxBy`, `aggregate`)
- Interoperability bridges (`ALinqCollection::lazy()` and `ALinqLazyCollection::toCollection()`)

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
