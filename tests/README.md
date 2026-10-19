# Antevemus ALinq Collection — Test Suite

Comprehensive PHPUnit 11 test suite certifying the **Antevemus ALinq Collection** framework.

## 📊 Overview & Metrics

- **Test Framework:** PHPUnit 11.5+ (configured for PHP 8.4+)
- **Test Files:** 26 unit test files (`tests/Unit/`, two of them in `tests/Unit/Http/`) and 4 contract test files (`tests/Contract/`)
- **Total Tests:** 731 tests (2.2.0)
- **Total Assertions:** 2968 assertions without ext-intl (10 tests that use `StringComparer::culture()` skipped); 3051 with ext-intl (1 skipped: the test of the missing extension)
- **Lines of Test Code:** 13,000+ lines
- **Success Rate:** 100% Passing (0 failures, 0 errors, 0 deprecations)
- **Runtime Dependencies:** One, from the same author, with none of its own: `antevemus/adeep-property-access` (the property accessor shared with ASpecification, since 2.0.0)

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

### 9. `UtilityOperationsTest.php` (47 tests)
Validates utility and helper operations:
- `isList()` — Verifies sequential numeric 0-indexed list structure
- `each()`, `eachRecursive()` — Side-effect iteration
- `random()` — Random element sampling
- `extract()` — removed in 2.0.0 (deprecated since 1.3.0): one test proves that no class or interface declares it
- `createPredicate()`, `createPropertySelector()` — Dynamic predicate helpers

### 10. `ALinqQueryBuilderTest.php` (55 tests)
Validates dynamic query composition:
- `create('and'|'or')` — Builder instantiation
- `where()` & `whereCustom()` — Criteria addition
- `whereIn()`, `whereNotIn()`, `whereBetween()` — Shortcuts over `in`/`notIn`/`between` (1.4.0)
- `toPredicate()` — Compilation into high-speed callable closures
- Supported operators: `=`, `==`, `===`, `!=`, `<>`, `!==`, `>`, `>=`, `<`, `<=`, `in`, `notIn`, `between`, `notBetween`, `isNull`, `isNotNull`, `contains`, `startsWith`, `endsWith`

### 11. `ALinqPropertyAccessTest.php` (53 tests)
Validates universal property resolution through the 1.x API, `ALinqPropertyAccess`, which since 2.0.0 is a deprecated facade over `antevemus/adeep-property-access` (kept as the regression of that API until 3.0):
- Dot-notation traversal (`user.address.city`)
- Arrays, stdClass objects, public properties, and getter methods
- Non-public properties of the class and its ancestors as the last resort, static and uninitialized ones included (1.4.0), only when marked `#[Specifiable]` on the property or on the declaring class (1.4.1)
- Unmarked non-public properties resolve to `null`/`false`; a class-level mark covers only the properties declared by that class; ASpecification's `#[Specifiable]` is accepted by name, a look-alike attribute is not; `whereBetween`/`orderBy` over an unmarked private field reveal nothing, eager and lazy (1.4.1)

### 12. `ALinqLazyCollectionTest.php` (64 tests)
Validates the streaming, generator-based pipeline engine:
- Constant $O(1)$ memory usage with 500,000+ element streams (< 100 KB RAM)
- Re-traversable streams via closure factory encapsulation
- `fromFile()` with automatic file handle closure in `finally` blocks
- `fromCsv()` with associative header mapping
- `fromCursor()` with unbuffered PDO statement streams
- `fromHttpPages()` against a local server, see §16 (1.5.0)
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
Enforces the eager × lazy contract (README §11); since 2.2.0 the matrix holds the grouping operators, `toLookup` (an `ALinqLookup` compares by its `toArray()`), `toDictionary` under the three policies, `exceptByKeys`/`intersectByKeys`, `implode` and every comparer parameter, and the row matrix runs 17 operations × 4 inputs (68 comparisons):
- Every operation both collections share runs over the same seven inputs on both sides; results are compared strictly, keys included, and when an operation throws the exception class and message (1.4.0) must match
- A second matrix runs the field- and key-based operators (`whereIn`, `whereBetween`, outer joins, `thenBy`, `single`) over rows given as arrays and as entities with private fields (marked `#[Specifiable]` since 1.4.1)
- A guard fails when a public method shared by both classes is missing from the matrix

### 15. `tests/Contract/AccessorContractTest.php` (7 tests)
Proves that ALinq delegates the property resolution to `antevemus/adeep-property-access` (2.0.0; the contract battery with the written answers lives in that package):
- The deprecated facade (`getValue`, `hasProperty`, `getPropertyAccessor`, `getNestedPropertyAccessor`) answers exactly as `PropertyAccess` on 53 cases (the 14 of the ALinq contract plus 39 edge cases of the package battery), without raising any notice
- The five internal entry points (`createPropertySelector`, `createPropertyComparer`, `ALinqQueryBuilder::where`, eager and lazy `whereIn`) agree with the package on every case; the 14 ALinq cases keep their written answers
- The answers that changed from 1.5.1 (getter with a required parameter, `__call()`, `__construct`, empty name, `ArrayObject` key holding `null`, the package's `#[Specifiable]`) reach the internal readers
- The four facade methods are `@deprecated 2.0.0`, `createPropertyComparer()` is not, and the public API is the one of 1.x
- No file of `src/` keeps a copy of the resolution (`method_exists`, `property_exists`, `ReflectionProperty`, `get_object_vars`, a getter name built with `ucfirst`) or calls the facade

### 16. `tests/Unit/Http/FromHttpPagesTest.php` (21 tests) and `HttpPageValueObjectsTest.php` (38 tests)
Validate `ALinqLazyCollection::fromHttpPages()` (1.5.0, forward 027). `FromHttpPagesTest` starts a `php -S` server on a free port of 127.0.0.1 (`router.php`, four workers, a per-run request log as the call counter) in `setUpBeforeClass()` and stops it in `tearDownAfterClass()`; no traffic leaves the loopback interface:
- Prefetch overlap measured: 3 pages of 300 ms with a consumer spending 300 ms per page finish under 1.5 s (≈ 1.2 s; serially 1.8 s)
- Cursor-in-body, page-number and `Link`-header pagination (absolute and relative targets); a `null` request or cursor ends the stream; the page limit throws after the last allowed page and never requests the next one
- A 500 on page 2 throws after page 1 was delivered; neither the message, the URL, the string form nor the arguments recorded in the stack trace hold the body or the query string; transport errors, the timeout (only the time spent waiting counts, a TLS handshake that never ends included) and redirects (off by default)
- TLS verification cannot be turned off: a self-signed server (`tls-server.php`) is refused and no constructor exposes a TLS switch
- `take(3)`, `first()`, `any()` and an exception in the consumer make at most two requests and the server sees the prefetch aborted
- Every traversal fetches again from the first page; `remember()` fetches once
- `HttpPageValueObjectsTest` covers the request and option validation (schemes, header injection, timeouts), `header()`, `json()`, the RFC 8288 `link()` parser and URL redaction without a network

Exclude them with `--exclude-group http` (they take about 2.5 s and need `ext-curl`).

### 17. `tests/Unit/ReadmePropertyAccessTest.php` (2 tests)
Runs the two code blocks of README §9 (EN and pt-BR) as written, read from the README files, and checks every commented answer (`// 99.9 (public getter)`, `// Returns: ...`) against what the code returns.

### 18. `tests/Unit/AggregationOrDefaultTest.php` (33 tests)
The aggregates with a fallback (2.1.0, `minOrDefault`, `maxOrDefault`, `averageOrDefault`, `minByOrDefault`, `maxByOrDefault`):
- Single pass: on a source that throws when opened a second time, each method reads it once (with values, empty, only nulls; directly and after `where()`), while the guard `any() ? min() : null` fails on the same source
- The consumer's code that motivated the methods, rewritten, on both collections and over `fromCursor()` (SQLite), including why the selector needs `strtotime(...) ?: null`
- Both interfaces declare the five methods with `$default` first

### 19. `tests/Contract/AggregateOrDefaultContractTest.php` (2 tests)
Uses the sibling as the oracle: 17 pairs (sibling, `OrDefault` variant) × 13 inputs × 2 sides. Where the sibling throws `UnderflowException` the variant returns the default; everywhere else (values, ties, non-comparable or non-numeric values, a throwing selector) the answer, or the exception class and message, is the same; eager and lazy agree. A tie returns the first item, by identity.

### 20. `tests/Unit/StringComparerTest.php` (17 tests)
`StringComparer` (2.2.0): `ordinal()` is bytes and not PHP's `<=>` (`'10'`/`'9'`, `'1e1'`/`'10'`); -1/0/1; `null` first and equal only to `null`, its hash never shared with a text; `Stringable` by its text; any other type throws; `ordinalIgnoreCase()` folds Unicode case and refuses invalid UTF-8; immutable; accepted by `contains()`, `orderByCustom()`, `thenBy()`, `intersectWith()`, `exceptWith()`. With ext-intl: `culture('pt_BR')` orders accented names, the strength of each option, `numeric`, locale resolution (`pt_XX` accepted, unknown and empty locales refused), invalid UTF-8 refused with the ICU error and not the text. Without ext-intl: the `LogicException` of the factory.

### 21. `tests/Unit/ComparerOperationsTest.php` (13 tests)
The comparer of 2.2.0 on both collections: `orderBy()`/`orderByDescending()` with a comparer, `distinct()`/`distinctBy()` with an `IALinqEqualityComparer` (and a comparer whose hash puts everything in one bucket, so `equals()` decides), `min`/`max`/`minBy`/`maxBy` and the `*OrDefault` variants with a comparer (null skipped, the comparer decides what is comparable), and one extreme comparison for both sides (NAN, arrays with different keys).

### 22. `tests/Unit/LookupAndDictionaryTest.php` (21 tests)
`toLookup()` + `ALinqLookup` (missing key = empty collection, element selector with the source key, the answer of `groupBy()`, `BackedEnum` and `1`/`'1'`/`'01'` keys, a null key throws, immutable, empty source) and `toDictionary(onDuplicate:)` (Throw keeps the message of 2.1.0, KeepFirst does not run the element selector for repeats, KeepLast keeps the position of the first occurrence, every key validated in every policy, an enum and not a string, the interface signatures), on both collections.

### 23. `tests/Unit/LazyGroupingTest.php` (9 tests)
The lazy grouping of 2.2.0: `groupBy`/`countBy`/`aggregateBy`/`exceptByKeys` are deferred and open a single-pass source once (a second traversal re-reads it; `remember()` replays), the terminals read it once, `aggregateBy()` hands the position inside the group on both sides, one seed for every group, the invalid-key message of the eager side, the stages are dictionaries downstream, `countBy()` over 200 000 items stays under a 1 MiB peak growth while `groupBy()` crosses it, a SQLite cursor, and the eager answers of `countBy`/`aggregateBy` unchanged.

### 24. `tests/Unit/KeyedSetAndJoinTest.php` (13 tests)
`exceptByKeys()`/`intersectByKeys()` with keys as an array, a generator, an eager and a lazy collection; the selector runs on the items only; strict identity and `null` matching `null`; dictionary keys kept; `exceptBy()` unchanged next to the .NET form; the lazy stage reads the keys on every traversal; `join()`/`groupJoin()` over a generator, an eager and a lazy collection answer as over an array and read the inner side once; the interface signatures.

### 25. `tests/Unit/CsvSeparatorDetectionTest.php` (27 tests)
`fromCsv(separator: null)`: each candidate, a separator inside quotes, a quoted record over several lines, the most frequent wins, one field reads with `,`, blank lines before the first record, BOM, CRLF, escaped and doubled quotes, empty files; with a header; ties (two and four candidates); first records over 64 KiB (one line, or several shorter lines in quotes) and of exactly 64 KiB; detection on every traversal and deferred; a stream that cannot seek; the default is still `,`.

### 26. `tests/Unit/ImplodeTest.php` (8 tests)
`implode()` on both collections: strings, numbers, `Stringable`, null as empty text, empty collection, PHP's text of a float, the selector with the key, the exception for a bool, an array or an object, the lazy side streams, both interfaces.

### 27. `tests/Unit/ConsumerExamplesTest.php` (4 tests)
The consumer's code that motivated 2.2.0, transcribed and run next to its rewrite: three orderings by an accented name (with ext-intl), the hand-written index (`KeepLast`) and grouping (`toLookup`), the documents without a participant (`exceptByKeys`) and the CSV reader of the economic indexes (`separator: null`), including where the two separator rules part.

### 28. `tests/Unit/ReadmeConsumerGapsTest.php` (15 tests)
Runs the seven README blocks of 2.2.0 (marked `<!-- tested: id -->`, EN and pt-BR) as written and checks every commented answer (`// [1, 3]`, `// throws Class: message`); the culture block runs only with ext-intl. A guard checks that both READMEs carry the same blocks, with the same code (comments aside) and the same answers.

### 29. `tests/Contract/ConsumerGapsPropertyTest.php` (6 tests)
Properties with an independent oracle (2.2.0, RN-03): 300 seeded inputs per property (lists and dictionaries of 0-60 items; int, string, `1`/`'1'`/`'01'`, `BackedEnum` and occasional invalid keys; repeated values; null), on both collections: `toLookup` against `$m[$k][] = $x` and against `groupBy`; `toDictionary` `KeepFirst`/`KeepLast` against the hand-written index; `countBy`/`aggregateBy` against hand-written counters (the position inside the group); `exceptByKeys`/`intersectByKeys` against `where(in_array(..., true))->distinctBy()`; the laws of every comparer (antisymmetry, transitivity on every triple of a sample, `equals` ⇔ `compare === 0` ⇒ equal hashes; the culture ones with ext-intl).

---

## 🚀 Running the Tests

### Execute Entire Test Suite
```bash
vendor/bin/phpunit

# With Xdebug installed, turn it off (a debugger session would make the suite wait)
XDEBUG_MODE=off vendor/bin/phpunit --no-coverage
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
