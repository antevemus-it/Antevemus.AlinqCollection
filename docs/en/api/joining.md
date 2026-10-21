# Joining & Set Theory (`JoiningOperations`)

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

#### Keys instead of elements: `exceptByKeys()`, `intersectByKeys()`; any iterable in `join()` (since 2.2.0)

`exceptByKeys(iterable $keys, callable $keySelector)` and `intersectByKeys(...)` are the `ExceptBy`/`IntersectBy` of .NET 6: the second argument is a sequence of **keys** (an array, a generator, an `ALinqCollection`, an `ALinqLazyCollection`), read once; the selector runs on this collection only. They are set operations: each key comes out once (the first occurrence), keys compare by strict identity (`'2'` is not `2`), a list is reindexed and a dictionary keeps its keys, and a `null` key matches a `null` in `$keys` (a set rule; in a join `null` never matches).

> [!WARNING]
> **Divergence from .NET in `exceptBy()`/`intersectBy()`.** The existing `exceptBy(array $second, callable $keySelector)` and `intersectBy(...)` take a sequence of **elements** of the same shape and apply the selector to **both** sides (the MoreLINQ form, older than .NET 6). They do not change. Called the .NET way (`exceptBy([1, 2], fn($r) => $r['id'])`) they read an offset of an integer, emit a warning and answer wrongly: use `exceptByKeys()`.

`join()` and `groupJoin()` take any iterable as the inner side since 2.2.0 (it was `array`): it is indexed in one read, so a generator or a lazy cursor works, with the same answers as the array form.

<!-- tested: 3-keys -->
```php
use Antevemus\ALinq\ALinqCollection;

$documents = ALinqCollection::from([
    ['id' => 1, 'type' => 'ID card'],
    ['id' => 2, 'type' => 'ID card'],
    ['id' => 3, 'type' => 'Proof of address'],
]);
$links = ALinqCollection::from([['document_id' => 2, 'participant_id' => 10]]);

// exceptByKeys(): the second argument is a sequence of KEYS (the ExceptBy of .NET 6), strict identity
$unlinked = $documents->exceptByKeys($links->select(fn($l) => $l['document_id']), fn($d) => $d['id'])->column('id')->toArray();
$unlinked; // [1, 3]
$documents->intersectByKeys([2, 3], fn($d) => $d['id'])->column('id')->toArray(); // [2, 3]
$documents->exceptByKeys(['2'], fn($d) => $d['id'])->count(); // 3

// exceptBy(): the second argument is a sequence of ELEMENTS, the selector applies to both sides
$documents->exceptBy([['id' => 2]], fn($d) => $d['id'])->column('id')->toArray(); // [1, 3]

// join()/groupJoin(): any iterable as the inner side, read once (a generator, a lazy cursor, a collection)
$participants = (function () {
    yield ['id' => 10, 'document_id' => 2, 'name' => 'Ana'];
})();
$documents->join($participants, fn($d) => $d['id'], fn($p) => $p['document_id'], fn($d, $p) => $p['name'] . ': ' . $d['type'])->toArray(); // ['Ana: ID card']
$documents->groupJoin(ALinqCollection::from([]), fn($d) => $d['id'], fn($p) => $p['document_id'], fn($d, $ps) => $ps->count())->toArray(); // [0, 0, 0]
```

#### Comparers in joins and set operations (since 2.4.0)

Every join (`join()`, `groupJoin()`, `leftJoin()`, `rightJoin()`, `fullJoin()`) and every set operation by element or key (`intersect()`, `except()`, `intersectBy()`, `exceptBy()`, `unionBy()`, `exceptByKeys()`, `intersectByKeys()`) takes an `IALinqEqualityComparer` as its last parameter, so `StringComparer::ordinalIgnoreCase()` matches e-mails whatever their case and `StringComparer::culture('pt_BR', ignoreAccents: true)` matches names whatever their accents. The comparer's `hash()` indexes one side and its `equals()` confirms each candidate, so a join stays linear: over 10 000 × 10 000 rows `equals()` runs once per outer row, never once per pair. A `null` key still never matches in a join, even under a comparer that finds `null` equal to `null`; in a set operation `null` is a key like any other. A set operation keeps the **first item of each key** (the first spelling seen), a list is reindexed and a dictionary keeps its keys, as without a comparer. On the lazy collection the joins, `exceptByKeys()` and `intersectByKeys()` take it too (see [Streaming](lazy-streaming.md)).

<!-- tested: 3-comparer -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Comparison\StringComparer;

$users = ALinqCollection::from([
    ['email' => 'Ana@Example.com', 'name' => 'Ana'],
    ['email' => 'bruno@example.com', 'name' => 'Bruno'],
]);
$orders = [
    ['email' => 'ana@example.com', 'id' => 1],
    ['email' => 'ANA@EXAMPLE.COM', 'id' => 2],
    ['email' => 'carla@example.com', 'id' => 3],
];
$email = fn($row) => $row['email'];
$ignoreCase = StringComparer::ordinalIgnoreCase();

$users->join($orders, $email, $email, fn($u, $o) => $u['name'] . '#' . $o['id'], $ignoreCase)->toArray(); // ['Ana#1', 'Ana#2']
$users->groupJoin($orders, $email, $email, fn($u, $os) => $os->count(), $ignoreCase)->toArray(); // [2, 0]
$users->leftJoin($orders, $email, $email, fn($u, $o) => $u['name'] . '#' . ($o['id'] ?? '-'), $ignoreCase)->toArray(); // ['Ana#1', 'Ana#2', 'Bruno#-']
// Without a comparer the keys match by strict identity, as before
$users->join($orders, $email, $email, fn($u, $o) => $o['id'])->count(); // 0

// Set operations: the first item of each key is the one kept
ALinqCollection::from(['Ana', 'ANA', 'Bruno'])->intersect(['ana', 'carla'], $ignoreCase)->toArray(); // ['Ana']
ALinqCollection::from($orders)->exceptByKeys(['ANA@example.com'], $email, $ignoreCase)->column('id')->toArray(); // [3]
ALinqCollection::from($orders)->unionBy([['email' => 'Carla@Example.com', 'id' => 4], ['email' => 'dora@example.com', 'id' => 5]], $email, $ignoreCase)->column('id')->toArray(); // [1, 3, 5]
```
