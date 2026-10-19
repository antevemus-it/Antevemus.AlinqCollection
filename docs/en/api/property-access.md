# Deep Dot-Notation Property Access (`PropertyAccess`)

### 9. Deep Dot-Notation Property Access (`PropertyAccess`)

Every name ALinq reads (in `where*()`, the query builder, `createPropertySelector()`, `createPropertyComparer()`) is resolved by **[`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess)**, the accessor shared with Antevemus.ASpecification since 2.0.0: both libraries read the same value from the same candidate by construction. It is installed with ALinq, and you can call it directly. Safely query deeply nested structures regardless of whether keys are associative arrays, object properties, getter methods, or mixed:

```php
use Antevemus\ADeepPropertyAccess\PropertyAccess;

$payload = [
    'user' => (object)[
        'profile' => [
            'organization' => (object)[
                'taxId' => '12.345.678/0001-90'
            ]
        ]
    ]
];

// Resolves nested path automatically:
$taxId = PropertyAccess::getValue($payload, 'user.profile.organization.taxId');
// Returns: '12.345.678/0001-90'
```

Resolution order of each segment, first match wins:

1. array key, or `ArrayAccess` offset (`hasProperty()` follows `offsetExists()`: a key holding `null` exists, as in an array);
2. a public getter (`getX()`, `x()`, `isX()`, `hasX()`) **without required parameters** (a method with a required parameter is not a getter and is skipped; `__call()` is never used, and a name starting with `__` is never called);
3. `__get` guarded by `__isset`;
4. a public property (an uninitialized typed one resolves to `null`);
5. as the last resort, a **non-public property** (private or protected, static too) declared by the class or one of its ancestors, read by reflection without calling any method, **only when the author of the class marked it `#[Specifiable]`** (since 1.4.1): on the property itself, or on the class that declares it. A class-level mark covers the properties declared by that class only, not those of its parents or subclasses. An uninitialized marked property resolves to `null`; an unmarked one resolves to `null` and `hasProperty()` answers `false`.

Otherwise `null`, never an `Error`. A dot is always a path. `PropertyAccess::hasProperty($item, 'a.b')` tells whether the path resolves and follows the same order; `PropertyAccess::accessor('a.b')` is the same read as a `Closure`.

```php
use Antevemus\ADeepPropertyAccess\PropertyAccess;
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Attributes\Specifiable;

final class Invoice
{
    public function __construct(
        #[Specifiable] private string $number,
        private float $total,
        private string $approvalCode = 'X9-SECRET',
    ) {}
    public function getTotal(): float { return $this->total; }
}

$invoice = new Invoice('NF-001', 99.9);

PropertyAccess::getValue($invoice, 'total');           // 99.9 (public getter)
PropertyAccess::getValue($invoice, 'number');          // 'NF-001' (private, marked #[Specifiable]: last resort)
PropertyAccess::hasProperty($invoice, 'number');       // true
PropertyAccess::getValue($invoice, 'approvalCode');    // null (private, not marked)
PropertyAccess::hasProperty($invoice, 'approvalCode'); // false
PropertyAccess::getValue($invoice, 'missing');         // null
ALinqCollection::from([$invoice])->whereBetween('approvalCode', 'A', 'Z')->count(); // 0
```

Why an opt-in: the library only receives a name as text (in `where*()`, the `orderBy()` selectors, `select()`, the query builder, `createPropertySelector()`, `createPropertyComparer()`) and cannot tell code written by the author of the entity from a name that came from a request. Without the mark, filtering or ordering over a private field would reveal it: `whereBetween('password_hash', 'a', 'b')` answers, range after range, what the value starts with. 1.4.0 read every non-public property; 1.4.1 reads only the marked ones. `#[Antevemus\ALinq\Attributes\Specifiable]` is the one to use in ALinq code; `#[Antevemus\ASpecification\Attributes\Specifiable]` and `#[Antevemus\ADeepPropertyAccess\Attributes\Specifiable]` count as the same mark (recognized by name, no dependency between the packages). Public getters, `__get` guarded by `__isset` and public properties need no mark.

**`ALinqPropertyAccess` (1.x) is a deprecated facade since 2.0.0**, removed in 3.0.0: `getValue()` and `hasProperty()` delegate to the methods of the same name of `PropertyAccess`, `getPropertyAccessor()` and `getNestedPropertyAccessor()` to `PropertyAccess::accessor()`. It raises no runtime notice. `ALinqPropertyAccess::createPropertyComparer($a, $b)` belongs to ALinq and stays. See [UPGRADE-2.0.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/UPGRADE-2.0.md) for the answers that changed at the edges.
