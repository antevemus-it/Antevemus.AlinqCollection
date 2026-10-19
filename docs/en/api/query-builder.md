# Dynamic Query Builder (`ALinqQueryBuilder`)

### 8. Dynamic Query Builder (`ALinqQueryBuilder`)

Construct dynamic filters without writing manual nested boolean logic or closures:

```php
use Antevemus\ALinq\ALinqQueryBuilder;

// Build a dynamic query from user input or UI filters
$query = ALinqQueryBuilder::create('and')
    ->where('status', '=', 'APPROVED')
    ->where('creditScore', '>=', 650)
    ->where('age', 'between', [21, 65]);

// Convert directly into a callable predicate
$predicate = $query->toPredicate();

$approvedApplicants = $applicants->where($predicate);
```

Operators (case- and separator-insensitive): `=`, `==`, `===`, `!=`, `<>`, `!==`, `>`, `>=`, `<`, `<=`, `in`, `notIn`, `between`, `notBetween`, `isNull`, `isNotNull`, `contains`, `startsWith`, `endsWith`. `between` takes an inclusive `[min, max]` pair. The same evaluator is exposed as `ALinqQueryBuilder::operatorPredicate($operator, $value)` and backs `createPredicate()`.

Shortcuts (since 1.4.0): `whereIn($field, $values)`, `whereNotIn($field, $values)` and `whereBetween($field, $min, $max)` are exactly `where($field, 'in' | 'notIn' | 'between', ...)`:

```php
$applicants = ALinqCollection::from([
    ['name' => 'Ana',   'status' => 'APPROVED', 'country' => 'BR', 'age' => 30],
    ['name' => 'Bruno', 'status' => 'REVIEW',   'country' => 'XX', 'age' => 40],
    ['name' => 'Carla', 'status' => 'REJECTED', 'country' => 'BR', 'age' => 25],
    ['name' => 'Davi',  'status' => 'APPROVED', 'country' => 'PT', 'age' => 70],
]);

$query = ALinqQueryBuilder::create('and')
    ->whereIn('status', ['APPROVED', 'REVIEW'])
    ->whereNotIn('country', ['XX'])
    ->whereBetween('age', 21, 65);

$applicants->where($query->toPredicate())->column('name')->toArray(); // ['Ana']
```

> [!NOTE]
> **Note:** in the builder, `in`/`notIn` compare **loosely** (`'18'` is in `[18]`), strictly only when the value read is `null`. The collection methods of the same names (§2) compare **strictly**: they are sugar over the collection's `where()`, not over the builder.
