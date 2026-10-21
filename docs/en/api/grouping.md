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

#### Comparers: one group for every spelling (since 2.4.0)

`groupBy()`, `countBy()`, `aggregateBy()`, `toDictionary()` and `toLookup()` take an `IALinqEqualityComparer` as their last parameter, on both collections: the keys equal under it are **one group, named by the first key seen** (the first element represents the group, as in LINQ: `'São Paulo'` before `'sao paulo'` gives the group `'São Paulo'`; in the other order, `'sao paulo'`). The identity is hash-based (`hash()` buckets, `equals()` decides), so grouping stays linear. The comparer receives the key after the key rule above (a `BackedEnum` as its value; `null` and the other types still throw), and two keys it tells apart that PHP turns into one array key (`1` and `'1'`) throw `InvalidArgumentException`. The lookup of `toLookup()` keeps the comparer, so `get()`/`contains()` find a group by any key equal to its name. `toDictionary()` keeps the first key seen (under `KeepLast` it holds the element of the last item equal to it), and its message for a repeat names the key as produced the second time.

`aggregateBy()` also takes a seed selector, `aggregateBy($keySelector, $seed, $func, $comparer = null, $seedSelector = null)`: a callable `fn($key)` called once per group, when the group starts, with the key that names it, whose answer is the seed of that group (the `seedSelector` of .NET 9): a fresh object per group, or a seed that depends on the key. When it is given, `$seed` is ignored (pass `null`); without it `$seed` is the seed of every group whatever its type, as before, a Closure included (it is the accumulator itself, never called).

<!-- tested: 6-comparer -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Comparison\StringComparer;
use Antevemus\ALinq\DuplicateKeyPolicy;

$visits = ALinqCollection::from([
    ['city' => 'São Paulo', 'visits' => 3],
    ['city' => 'Rio', 'visits' => 1],
    ['city' => 'sao paulo', 'visits' => 2],
    ['city' => 'SAO PAULO', 'visits' => 4],
]);
$ptBr = StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true);
$city = fn($v) => $v['city'];

// The first spelling seen names the group
$visits->countBy($city, $ptBr)->toArray(); // ['São Paulo' => 3, 'Rio' => 1]
$visits->groupBy($city, $ptBr)->select(fn($group) => $group->sum(fn($v) => $v['visits']))->toArray(); // ['São Paulo' => 9, 'Rio' => 1]
$visits->toDictionary($city, fn($v) => $v['visits'], DuplicateKeyPolicy::KeepLast, $ptBr); // ['São Paulo' => 4, 'Rio' => 1]
// The lookup keeps the comparer: any spelling finds the group
$visits->toLookup($city, fn($v) => $v['visits'], $ptBr)->get('SÃO paulo')->toArray(); // [3, 2, 4]
// aggregateBy(): a seed selector gives each group its own seed
$visits->aggregateBy($city, null, fn($trace, $v) => $trace . ' ' . $v['visits'], $ptBr, fn($name) => $name . ':')->toArray(); // ['São Paulo' => 'São Paulo: 3 2 4', 'Rio' => 'Rio: 1']
// Without a comparer the keys are PHP array keys, as before
$visits->countBy($city)->count(); // 4
```
