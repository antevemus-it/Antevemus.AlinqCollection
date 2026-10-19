# Projections & Transformations (`SelectionOperations`)

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

#### Repeated keys and lookups: `toDictionary(onDuplicate:)`, `toLookup()` (since 2.2.0)

`toDictionary($keySelector, $elementSelector = null, DuplicateKeyPolicy $onDuplicate = DuplicateKeyPolicy::Throw)`: `Throw` is the behaviour of every earlier version (the same message); `KeepFirst` keeps the first element and does not run the element selector for the repeats; `KeepLast` keeps the last one, at the position of the first occurrence, like `$map[$row->id] = $row`. The key selector runs for every item and every key is validated in every policy. `toLookup($keySelector, $elementSelector = null)` returns an immutable `ALinqLookup` (§6): `get()` of a missing key is an empty collection, never an undefined index. Both exist on the lazy collection too, as terminal operations that read the stream once.

<!-- tested: 5-dictionary -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\DuplicateKeyPolicy;

$quotes = ALinqCollection::from([
    ['id' => 7, 'unit' => 'North', 'status' => 'draft'],
    ['id' => 8, 'unit' => 'South', 'status' => 'sent'],
    ['id' => 7, 'unit' => 'North', 'status' => 'approved'], // a later version of quote 7
]);

// toDictionary(): a repeated key throws by default; a policy may keep the first or the last
$quotes->toDictionary(fn($q) => $q['id'], fn($q) => $q['status']); // throws InvalidArgumentException: toDictionary() key 7 produced twice (second time for item at key 2)
$quotes->toDictionary(fn($q) => $q['id'], fn($q) => $q['status'], DuplicateKeyPolicy::KeepFirst); // [7 => 'draft', 8 => 'sent']
$quotes->toDictionary(fn($q) => $q['id'], fn($q) => $q['status'], DuplicateKeyPolicy::KeepLast); // [7 => 'approved', 8 => 'sent']

// toLookup(): one key, many elements; a missing key is an empty collection
$byUnit = $quotes->toLookup(fn($q) => $q['unit'], fn($q) => $q['id']);
$byUnit->get('North')->toArray(); // [7, 7]
$byUnit['South']->toArray(); // [8]
$byUnit->get('East')->count(); // 0
$byUnit->keys(); // ['North', 'South']
```
