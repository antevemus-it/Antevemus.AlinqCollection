# Grouping & Partitioning (`GroupingOperations`)

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

#### `ALinqLookup`: a materialized `groupBy()` (since 2.2.0)

`toLookup()` returns an `ALinqLookup` (the `ILookup<TKey, TElement>` of .NET): keys in the order of their first appearance, the elements of each key in source order, `get($key)` (an `ALinqCollection`, empty for a missing key), `contains($key)`, `keys()`, `count()` (the number of keys), iteration as `key => ALinqCollection` (the shape of `groupBy()`), `toArray()` and `json_encode()`. Read-only `ArrayAccess`: `$lookup['x']` is `get('x')`, `isset()` is `contains()`, and writing throws. A key follows `groupBy()`: an `int`, a `string` or a `BackedEnum` (its value); `null` throws, unlike .NET, so that a lookup answers as `groupBy()` does.

<!-- tested: 6-lookup -->
```php
use Antevemus\ALinq\ALinqCollection;

$lookup = ALinqCollection::from([3, 1, 4, 1, 5, 9, 2, 6])->toLookup(fn($n) => $n % 3);
$lookup->toArray(); // [0 => [3, 9, 6], 1 => [1, 4, 1], 2 => [5, 2]]
count($lookup); // 3
$lookup->contains(1); // true
$lookup[7]->toArray(); // []
json_encode($lookup); // '[[3,9,6],[1,4,1],[5,2]]'
$lookup[7] = ALinqCollection::from([]); // throws LogicException: ALinqLookup is immutable.

// Group keys are PHP array keys: 1 and '1' are one key, '01' is another
ALinqCollection::from([['k' => 1], ['k' => '1'], ['k' => '01']])->toLookup(fn($r) => $r['k'])->keys(); // [1, '01']
```
