# 🔮 Synergy with Antevemus ASpecification

`Antevemus.AlinqCollection` is the native collection engine for **[`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification)**. When both packages are installed, business rules encapsulated in **Domain Specifications** can be evaluated directly against ALinq collections without writing manual closures.

### How It Works

The **`ALinqBridge`** and **`ALinqSpecificationVisitor`** inside `Antevemus.ASpecification` compile any composite specification AST (composed of `And`, `Or`, `Not`, `GreaterThan`, `Regex`, etc.) into an optimized PHP functional predicate:

```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ASpecification\Spec;
use Antevemus\ASpecification\Linq\ALinqBridge;
use function Antevemus\ASpecification\DSL\is;
use function Antevemus\ASpecification\DSL\greaterThan;

// 1. Define Business Rules using Evans & Fowler Specification Pattern
$isEligibleCustomer = Spec::allOf(
    Spec::property('status', is('ACTIVE')),
    Spec::property('creditScore', greaterThan(700))
);

// 2. Query any collection or in-memory repository through ALinqBridge
$customers = ALinqCollection::from($rawCustomerData);

// ALinqBridge compiles the specification AST into a native predicate:
$qualifiedCustomers = ALinqBridge::filter($customers, $isEligibleCustomer)
    ->orderByDescending(fn($c) => $c->creditScore)
    ->take(10)
    ->select(fn($c) => [
        'id'    => $c->id,
        'name'  => $c->name,
        'tier'  => 'PREMIUM',
    ])
    ->toArray();
```
