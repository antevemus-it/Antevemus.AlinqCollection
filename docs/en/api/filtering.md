# Filtering & Slicing (`FilteringOperations`)

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

Shortcuts over `where()` that read a field through the shared `PropertyAccess` (§9: array key, getter, property, dot-notation path such as `'address.city'`). `whereIn`/`whereNotIn` compare by **strict** identity (`in_array(..., true)`: `'18'` is not `18`); `whereBetween` is **inclusive** and a `null` value is never between. Keys follow the contract: a list comes out reindexed, a dictionary keeps its keys.

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
