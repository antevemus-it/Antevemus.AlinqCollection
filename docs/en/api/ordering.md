# Sorting & Ordering (`OrderingOperations`)

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

#### Comparing text: `StringComparer` (since 2.2.0)

`orderBy()`/`orderByDescending()` now take the comparer `thenBy()` already took (`fn($keyA, $keyB): int`), and so do `min()`, `max()`, `minBy()`, `maxBy()` and their `*OrDefault()` variants; `distinct()`/`distinctBy()` take an `IALinqEqualityComparer` (an `equals()` and a compatible `hash()`, the `IEqualityComparer<T>` of .NET). `Antevemus\ALinq\Comparison\StringComparer` is both, so one object serves every operator, including the `contains()`, `orderByCustom()`, `intersectWith()` and `exceptWith()` that already took a callable:

- `StringComparer::ordinal()`: byte by byte. It is **not** the default: without a comparer the keys go through PHP's `<=>`, which reads numeric strings as numbers (`'10' <=> '9'` is `1`, `'1e1' <=> '10'` is `0`).
- `StringComparer::ordinalIgnoreCase()`: simple Unicode case folding, then bytes (needs `ext-mbstring`). .NET upper-cases with the invariant culture instead; equality agrees in the usual cases, the order may differ in rare ones.
- `StringComparer::culture($locale, ignoreCase: false, ignoreAccents: false, numeric: false)`: the collation of a language, through ICU (needs `ext-intl`, listed in `suggest`; without it the factory throws `LogicException`). A locale ICU does not know throws `InvalidArgumentException`; `pt_XX` falls back to `pt`.

`null` comes before every text and equals only `null`; a `Stringable` is compared by its text; any other value throws `InvalidArgumentException`. With a comparer, `min()`/`max()` still skip `null`, and the comparer, not the library, decides what can be compared.

<!-- tested: 7-ordinal -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Comparison\StringComparer;

$names = ALinqCollection::from(['Zeca', 'Álvaro', 'ana', 'Bruno', 'Ética']);

// Without a comparer: PHP's <=>, byte by byte (upper case first, accented letters last)
$names->orderBy(fn($n) => $n)->toArray(); // ['Bruno', 'Zeca', 'ana', 'Álvaro', 'Ética']

// ordinal(): bytes, without the numeric reading of numeric strings that <=> does
ALinqCollection::from(['10', '9'])->orderBy(fn($s) => $s)->toArray(); // ['9', '10']
ALinqCollection::from(['10', '9'])->orderBy(fn($s) => $s, StringComparer::ordinal())->toArray(); // ['10', '9']

// ordinalIgnoreCase(): Unicode case folding; accents still count
ALinqCollection::from(['Ana', 'ANA', 'ana', 'Ána'])->distinct(null, StringComparer::ordinalIgnoreCase())->toArray(); // ['Ana', 'Ána']
StringComparer::ordinalIgnoreCase()->equals('ÉTICA', 'ética'); // true
```

With `ext-intl`:

<!-- tested: 7-culture -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Comparison\StringComparer;

$people = ALinqCollection::from([
    ['name' => 'Zeca', 'team' => 'B'],
    ['name' => 'Álvaro', 'team' => 'A'],
    ['name' => 'ana', 'team' => 'B'],
    ['name' => 'Ética', 'team' => 'A'],
    ['name' => 'Bruno', 'team' => 'A'],
]);
$ptBr = StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true);

$people->orderBy(fn($p) => $p['name'], $ptBr)->column('name')->toArray(); // ['Álvaro', 'ana', 'Bruno', 'Ética', 'Zeca']
$sorted = $people
    ->orderBy(fn($p) => $p['team'])
    ->thenBy(fn($p) => $p['name'], $ptBr)
    ->column('name')
    ->toArray();
$sorted; // ['Álvaro', 'Bruno', 'Ética', 'ana', 'Zeca']
$ptBr->equals('Ética', 'ETICA'); // true
$people->minBy(fn($p) => $p['name'], $ptBr)['name']; // 'Álvaro'

// Grouping without accents: the hash is an opaque key (not for display)
$people->countBy(fn($p) => $ptBr->hash(mb_substr($p['name'], 0, 1)))->count(); // 4

// numeric: digit runs compare by value
ALinqCollection::from(['item10', 'item2'])->orderBy(fn($s) => $s, StringComparer::culture('en_US', numeric: true))->toArray(); // ['item2', 'item10']
```
