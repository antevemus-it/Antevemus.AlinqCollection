# Aggregations & Metrics (`AggregationOperations`)

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

// minOrDefault() & co.: the sibling's answer, or a fallback when there is nothing to aggregate (since 2.1.0)
$smallest = $orders->minOrDefault(null, fn($o) => $o['amount']);                                    // 45.0
$cancelled = $orders->where(fn($o) => $o['status'] === 'CANCELLED')->maxOrDefault(0.0, fn($o) => $o['amount']); // 0.0 (max() would throw)
ALinqCollection::from([])->averageOrDefault();                                                       // null
```

`single()` throws the same exception classes and messages on the eager and the lazy collection; `singleOrDefault()` returns the default when nothing matches and still throws `OverflowException` on more than one match.

`minOrDefault($default = null, $selector)`, `maxOrDefault(...)`, `averageOrDefault(...)`, `minByOrDefault($default, $keySelector)` and `maxByOrDefault(...)` (since 2.1.0, both collections) give the same answer as `min()`/`max()`/`average()`/`minBy()`/`maxBy()`, but when there is nothing to aggregate (empty, or only nulls where the sibling skips them) they return `$default` instead of throwing `UnderflowException`; every other error throws as before, with the same class and message. They read the source once, so they are safe on single-pass sources such as `fromCursor()`, where the guard `any() ? min() : null` reads it twice (and fails on a cursor). `$default` comes first, as in `firstOrDefault()`.
