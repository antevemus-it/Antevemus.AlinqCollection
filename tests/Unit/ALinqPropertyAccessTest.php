<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqQueryBuilder;
use Antevemus\ALinq\Helpers\ALinqPropertyAccess;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ALinqPropertyAccess Helper
 */
class ALinqPropertyAccessTest extends TestCase
{
    // ===== getValue() TESTS =====

    public function testGetValueFromArray(): void
    {
        $array = ['name' => 'Alice', 'age' => 30];

        $this->assertEquals('Alice', ALinqPropertyAccess::getValue($array, 'name'));
        $this->assertEquals(30, ALinqPropertyAccess::getValue($array, 'age'));
    }

    public function testGetValueFromArrayReturnsNullForMissingKey(): void
    {
        $array = ['name' => 'Alice'];

        $this->assertNull(ALinqPropertyAccess::getValue($array, 'missing'));
    }

    public function testGetValueFromObjectWithPublicProperty(): void
    {
        $obj = new class {
            public $name = 'Bob';
            public $age = 25;
        };

        $this->assertEquals('Bob', ALinqPropertyAccess::getValue($obj, 'name'));
        $this->assertEquals(25, ALinqPropertyAccess::getValue($obj, 'age'));
    }

    public function testGetValueFromObjectWithGetterMethod(): void
    {
        $obj = new class {
            // Sem propriedade pública, apenas getter
            public function getValue() {
                return 'secret';
            }
        };

        // Como não tem propriedade pública 'value', vai tentar getValue()
        $this->assertEquals('secret', ALinqPropertyAccess::getValue($obj, 'value'));
    }

    public function testGetValueFromObjectWithIsMethod(): void
    {
        $obj = new class {
            public function isActive() {
                return true;
            }
        };

        $this->assertTrue(ALinqPropertyAccess::getValue($obj, 'active'));
    }

    public function testGetValueFromObjectWithHasMethod(): void
    {
        $obj = new class {
            public function hasPermission() {
                return false;
            }
        };

        $this->assertFalse(ALinqPropertyAccess::getValue($obj, 'permission'));
    }

    public function testGetValueReturnsNullForMissingProperty(): void
    {
        $obj = new class {
            public $name = 'Test';
        };

        $this->assertNull(ALinqPropertyAccess::getValue($obj, 'nonexistent'));
    }

    public function testGetValueReturnsNullForNonObjectNonArray(): void
    {
        $this->assertNull(ALinqPropertyAccess::getValue('string', 'property'));
        $this->assertNull(ALinqPropertyAccess::getValue(123, 'property'));
        $this->assertNull(ALinqPropertyAccess::getValue(null, 'property'));
    }

    // ===== getPropertyAccessor() TESTS =====

    public function testGetPropertyAccessorReturnsClosureThatAccessesProperty(): void
    {
        $accessor = ALinqPropertyAccess::getPropertyAccessor('name');

        $this->assertInstanceOf(\Closure::class, $accessor);

        $obj = new class {
            public $name = 'Charlie';
        };

        $this->assertEquals('Charlie', $accessor($obj));
    }

    public function testGetPropertyAccessorWorksWithArrays(): void
    {
        $accessor = ALinqPropertyAccess::getPropertyAccessor('city');

        $array = ['city' => 'New York', 'country' => 'USA'];

        $this->assertEquals('New York', $accessor($array));
    }

    public function testGetPropertyAccessorWithGetterMethod(): void
    {
        $accessor = ALinqPropertyAccess::getPropertyAccessor('status');

        $obj = new class {
            public function getStatus() {
                return 'active';
            }
        };

        $this->assertEquals('active', $accessor($obj));
    }

    // ===== getNestedPropertyAccessor() TESTS =====

    public function testGetNestedPropertyAccessorWithSimplePath(): void
    {
        $accessor = ALinqPropertyAccess::getNestedPropertyAccessor('name');

        $obj = new class {
            public $name = 'David';
        };

        $this->assertEquals('David', $accessor($obj));
    }

    public function testGetNestedPropertyAccessorWithTwoLevels(): void
    {
        $accessor = ALinqPropertyAccess::getNestedPropertyAccessor('user.name');

        $data = [
            'user' => ['name' => 'Eve', 'age' => 28]
        ];

        $this->assertEquals('Eve', $accessor($data));
    }

    public function testGetNestedPropertyAccessorWithThreeLevels(): void
    {
        $accessor = ALinqPropertyAccess::getNestedPropertyAccessor('user.address.city');

        $data = [
            'user' => [
                'name' => 'Frank',
                'address' => [
                    'city' => 'Paris',
                    'country' => 'France'
                ]
            ]
        ];

        $this->assertEquals('Paris', $accessor($data));
    }

    public function testGetNestedPropertyAccessorWithObjects(): void
    {
        $accessor = ALinqPropertyAccess::getNestedPropertyAccessor('user.name');

        $user = new class {
            public $name = 'Grace';
        };

        $obj = new class($user) {
            public $user;
            public function __construct($user) {
                $this->user = $user;
            }
        };

        $this->assertEquals('Grace', $accessor($obj));
    }

    public function testGetNestedPropertyAccessorReturnsNullForMissingPath(): void
    {
        $accessor = ALinqPropertyAccess::getNestedPropertyAccessor('user.address.city');

        $data = ['user' => ['name' => 'Henry']];

        $this->assertNull($accessor($data));
    }

    public function testGetNestedPropertyAccessorReturnsNullWhenIntermediateIsNull(): void
    {
        $accessor = ALinqPropertyAccess::getNestedPropertyAccessor('user.address.city');

        $data = ['user' => null];

        $this->assertNull($accessor($data));
    }

    public function testGetNestedPropertyAccessorWithMixedArraysAndObjects(): void
    {
        $accessor = ALinqPropertyAccess::getNestedPropertyAccessor('data.user.name');

        $user = new class {
            public $name = 'Ivy';
        };

        $data = [
            'data' => (object)['user' => $user]
        ];

        $this->assertEquals('Ivy', $accessor($data));
    }

    // ===== createPropertyComparer() TESTS =====

    public function testCreatePropertyComparerReturnsClosure(): void
    {
        $comparer = ALinqPropertyAccess::createPropertyComparer('age', 'minAge');

        $this->assertInstanceOf(\Closure::class, $comparer);
    }

    public function testCreatePropertyComparerComparesEqualValues(): void
    {
        $comparer = ALinqPropertyAccess::createPropertyComparer('age', 'targetAge');

        $obj = new class {
            public $age = 30;
            public $targetAge = 30;
        };

        $this->assertTrue($comparer($obj));
    }

    public function testCreatePropertyComparerComparesUnequalValues(): void
    {
        $comparer = ALinqPropertyAccess::createPropertyComparer('current', 'expected');

        $obj = new class {
            public $current = 10;
            public $expected = 20;
        };

        $this->assertFalse($comparer($obj));
    }

    public function testCreatePropertyComparerWithArrays(): void
    {
        $comparer = ALinqPropertyAccess::createPropertyComparer('price', 'cost');

        $data = ['price' => 100, 'cost' => 100];

        $this->assertTrue($comparer($data));
    }

    public function testCreatePropertyComparerWithGetterMethods(): void
    {
        $comparer = ALinqPropertyAccess::createPropertyComparer('value1', 'value2');

        $obj = new class {
            public function getValue1() {
                return 'test';
            }
            public function getValue2() {
                return 'test';
            }
        };

        $this->assertTrue($comparer($obj));
    }

    public function testCreatePropertyComparerWithNullValues(): void
    {
        $comparer = ALinqPropertyAccess::createPropertyComparer('missing1', 'missing2');

        $obj = new class {
            public $name = 'Test';
        };

        // Ambas propriedades faltando retornam null, então são iguais
        $this->assertTrue($comparer($obj));
    }

    public function testCreatePropertyComparerWithOneNullValue(): void
    {
        $comparer = ALinqPropertyAccess::createPropertyComparer('exists', 'missing');

        $obj = new class {
            public $exists = 'value';
        };

        $this->assertFalse($comparer($obj));
    }

    // ===== RESOLUTION ORDER / VISIBILITY (review 2026-10-08 §3.1, §3.15; decision 3a) =====

    public function testGetValueResolvesPrivatePropertyThroughItsGetter(): void
    {
        $this->assertSame('priv', ALinqPropertyAccess::getValue(new PrivateWithGetter(), 'name'));
        $this->assertSame('prot', ALinqPropertyAccess::getValue(new ProtectedWithGetter(), 'name'));
    }

    public function testGetValueReadsAPrivatePropertyWithoutGetterAsTheLastResort(): void
    {
        // 1.4.0 (forward 021, RN-07): before, null
        $this->assertSame('priv-no-getter', ALinqPropertyAccess::getValue(new PrivateWithoutGetter(), 'name'));
    }

    public function testTheNonPublicPropertyComesAfterEveryOtherStep(): void
    {
        // a public getter wins over the private property it shadows
        $this->assertSame('from-getter', ALinqPropertyAccess::getValue(new PrivateShadowedByGetter(), 'name'));
        // __get guarded by __isset wins over the private property
        $this->assertSame('magic:name', ALinqPropertyAccess::getValue(new PrivateShadowedByMagicGet(), 'name'));
        // a private getter is still ignored, the private property behind it is read
        $this->assertSame('field', ALinqPropertyAccess::getValue(new PrivateGetterOverPrivateProperty(), 'name'));
    }

    public function testGetValueReadsNonPublicPropertiesUpTheHierarchy(): void
    {
        $child = new ChildOfPrivateParent();

        $this->assertSame('parent-private', ALinqPropertyAccess::getValue($child, 'secret'));
        $this->assertSame('parent-protected', ALinqPropertyAccess::getValue($child, 'level'));
        $this->assertSame('child-private', ALinqPropertyAccess::getValue($child, 'own'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty($child, 'secret'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty($child, 'level'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty($child, 'missing'));
        $this->assertNull(ALinqPropertyAccess::getValue($child, 'missing'));
    }

    public function testGetValueReadsPrivateStaticAndUninitializedPrivateProperties(): void
    {
        $this->assertSame('private-static', ALinqPropertyAccess::getValue(new PrivateStaticAndUninitialized(), 'shared'));
        $this->assertNull(ALinqPropertyAccess::getValue(new PrivateStaticAndUninitialized(), 'pending'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty(new PrivateStaticAndUninitialized(), 'pending'), 'declared, even if uninitialized');
    }

    public function testDotPathsAndTheQueryBuilderReachPrivateProperties(): void
    {
        $order = ['customer' => new PrivateWithoutGetter()];

        $this->assertSame('priv-no-getter', ALinqPropertyAccess::getValue($order, 'customer.name'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty($order, 'customer.name'));

        $matches = ALinqCollection::from([$order, ['customer' => null]])
            ->where(ALinqQueryBuilder::create()->where('customer.name', '=', 'priv-no-getter')->toPredicate())
            ->count();
        $this->assertSame(1, $matches);
    }

    public function testGetValueLetsMethodsWinOverPublicProperties(): void
    {
        // public $active = true, isActive() = false: the method wins (ASpec order)
        $this->assertFalse(ALinqPropertyAccess::getValue(new PublicActiveWithIsMethod(), 'active'));
        // public $status = 'prop', getStatus() = 'getter'
        $this->assertSame('getter', ALinqPropertyAccess::getValue(new PublicStatusWithGetter(), 'status'));
        // public $value = 'v', hasValue() = 'has'
        $this->assertSame('has', ALinqPropertyAccess::getValue(new PublicValueWithHasMethod(), 'value'));
    }

    public function testGetValueResolvesMagicGetWhenIssetIsDeclared(): void
    {
        $this->assertSame('magic:name', ALinqPropertyAccess::getValue(new MagicGetWithIsset(), 'name'));
        $this->assertNull(ALinqPropertyAccess::getValue(new MagicGetWithoutIsset(), 'name'));
    }

    public function testGetValueResolvesArrayAccessOffsets(): void
    {
        $this->assertSame('aa', ALinqPropertyAccess::getValue(new ArrayAccessFixture(['name' => 'aa']), 'name'));
        $this->assertNull(ALinqPropertyAccess::getValue(new ArrayAccessFixture([]), 'name'));
    }

    public function testGetValueCallsAMethodNamedLikeTheProperty(): void
    {
        $this->assertSame('method-name', ALinqPropertyAccess::getValue(new MethodNamedLikeProperty(), 'name'));
    }

    public function testGetValueReturnsNullForUninitializedTypedProperty(): void
    {
        $this->assertNull(ALinqPropertyAccess::getValue(new UninitializedTyped(), 'age'));
    }

    public function testGetValueReadsPublicStaticPropertyWithoutWarnings(): void
    {
        $this->assertSame('static', ALinqPropertyAccess::getValue(new PublicStaticProperty(), 'name'));
    }

    public function testGetValueIgnoresPrivateGetters(): void
    {
        $this->assertNull(ALinqPropertyAccess::getValue(new PrivateGetterOnly(), 'name'));
        // private getter + public property: the property is still readable
        $this->assertSame('pub', ALinqPropertyAccess::getValue(new PrivateGetterWithPublicProperty(), 'name'));
    }

    public function testGetValueKeepsWorkingForEnumsReadonlyAndDateTime(): void
    {
        $this->assertSame('A', ALinqPropertyAccess::getValue(StatusFixture::Active, 'value'));
        $this->assertSame('Active', ALinqPropertyAccess::getValue(StatusFixture::Active, 'name'));
        $this->assertSame('ro', ALinqPropertyAccess::getValue(new ReadonlyFixture('ro'), 'name'));
        $this->assertSame(
            1767225600,
            ALinqPropertyAccess::getValue(new \DateTime('2026-01-01 00:00:00 UTC'), 'timestamp')
        );
    }

    // ===== DOT NOTATION IN getValue() (review 2026-10-08 §3.2; README §9) =====

    public function testGetValueResolvesDotNotationPaths(): void
    {
        $target = new class {
            public object $a;
            public function __construct()
            {
                $this->a = (object)['b' => ['c' => 'deep']];
            }
        };

        $this->assertSame('deep', ALinqPropertyAccess::getValue($target, 'a.b.c'));
        $this->assertNull(ALinqPropertyAccess::getValue($target, 'a.b.missing'));
        $this->assertNull(ALinqPropertyAccess::getValue($target, 'a.missing.c'));
    }

    public function testGetValueRunsTheReadmeSection9ExampleAsWritten(): void
    {
        $payload = [
            'user' => (object)[
                'profile' => [
                    'organization' => (object)[
                        'taxId' => '12.345.678/0001-90'
                    ]
                ]
            ]
        ];

        $this->assertSame(
            '12.345.678/0001-90',
            ALinqPropertyAccess::getValue($payload, 'user.profile.organization.taxId')
        );
    }

    public function testGetValueTreatsADottedNameAsNestedEvenWhenALiteralKeyExists(): void
    {
        // Documented choice (decision 3a): a dotted name is always a path, as in ASpecification.
        $data = ['a.b' => 'literal', 'a' => ['b' => 'nested']];

        $this->assertSame('nested', ALinqPropertyAccess::getValue($data, 'a.b'));
    }

    public function testNestedAccessorResolvesPrivatePropertiesWithGettersAlongThePath(): void
    {
        $accessor = ALinqPropertyAccess::getNestedPropertyAccessor('p.name');

        $this->assertSame('priv', $accessor(['p' => new PrivateWithGetter()]));
        $this->assertSame('priv', ALinqPropertyAccess::getPropertyAccessor('p.name')(['p' => new PrivateWithGetter()]));
    }

    public function testPropertyAccessorAndComparerUseTheSameResolutionOrder(): void
    {
        $accessor = ALinqPropertyAccess::getPropertyAccessor('name');
        $this->assertSame('priv', $accessor(new PrivateWithGetter()));

        $comparer = ALinqPropertyAccess::createPropertyComparer('name', 'alias');
        $this->assertTrue($comparer(new class {
            private string $name = 'same';
            public string $alias = 'same';
            public function getName(): string { return $this->name; }
        }));
    }

    // ===== hasProperty() (new; same semantics as ASpecification PropertyAccessor::hasProperty) =====

    public function testHasPropertyOnArraysAndArrayAccess(): void
    {
        $this->assertTrue(ALinqPropertyAccess::hasProperty(['name' => 'x'], 'name'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty(['name' => null], 'name'), 'a null value still exists');
        $this->assertFalse(ALinqPropertyAccess::hasProperty(['name' => 'x'], 'other'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty(new ArrayAccessFixture(['name' => 'aa']), 'name'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty(new ArrayAccessFixture([]), 'name'));
    }

    public function testHasPropertyOnObjectsFollowsTheResolutionOrder(): void
    {
        $this->assertTrue(ALinqPropertyAccess::hasProperty(new PrivateWithGetter(), 'name'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty(new PrivateWithoutGetter(), 'name'), 'non-public property, since 1.4.0');
        $this->assertFalse(ALinqPropertyAccess::hasProperty(new PrivateGetterOnly(), 'name'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty(new MethodNamedLikeProperty(), 'name'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty(new MagicGetWithIsset(), 'name'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty(new MagicGetWithoutIsset(), 'name'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty(new UninitializedTyped(), 'age'), 'declared public, even if uninitialized');
        $this->assertTrue(ALinqPropertyAccess::hasProperty(new PublicStaticProperty(), 'name'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty((object)['name' => 'dyn'], 'name'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty(new \stdClass(), 'name'));
    }

    public function testHasPropertyWithDotNotationAndNonInspectableTargets(): void
    {
        $data = ['user' => ['address' => ['city' => 'Paris']], 'p' => new PrivateWithGetter()];

        $this->assertTrue(ALinqPropertyAccess::hasProperty($data, 'user.address.city'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty($data, 'user.address.zip'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty($data, 'user.phone.number'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty($data, 'p.name'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty(null, 'name'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty('string', 'name'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty(42, 'name'));
    }

    /**
     * L4, revised by 1.4.0 (forward 021, RN-07): hasProperty() on a declared non-public
     * property without getter or __isset answers true and getValue() reads it, consistently
     * (before 1.4.0 both answered "missing": false and null).
     */
    public function testHasPropertyIsTrueForANonPublicPropertyWithoutGetter(): void
    {
        $entity = new class {
            private string $secret = 's';
            protected int $level = 1;
            public string $name = 'n';
        };

        $this->assertTrue(ALinqPropertyAccess::hasProperty($entity, 'secret'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty($entity, 'level'));
        $this->assertTrue(ALinqPropertyAccess::hasProperty($entity, 'name'));
        $this->assertSame('s', ALinqPropertyAccess::getValue($entity, 'secret'));
        $this->assertSame(1, ALinqPropertyAccess::getValue($entity, 'level'));
        $this->assertSame('n', ALinqPropertyAccess::getValue($entity, 'name'));
    }
}

// ===== Fixtures for the resolution-order tests (one per row of REVISAO-2026-10-08 §3.15) =====

final class PrivateWithGetter
{
    private string $name = 'priv';
    public function getName(): string { return $this->name; }
}

final class ProtectedWithGetter
{
    protected string $name = 'prot';
    public function getName(): string { return $this->name; }
}

final class PrivateWithoutGetter
{
    private string $name = 'priv-no-getter';
    public function describe(): string { return $this->name; }
}

final class PublicActiveWithIsMethod
{
    public bool $active = true;
    public function isActive(): bool { return false; }
}

final class PublicStatusWithGetter
{
    public string $status = 'prop';
    public function getStatus(): string { return 'getter'; }
}

final class PublicValueWithHasMethod
{
    public string $value = 'v';
    public function hasValue(): string { return 'has'; }
}

final class MagicGetWithIsset
{
    public function __get(string $name): string { return "magic:$name"; }
    public function __isset(string $name): bool { return true; }
}

final class MagicGetWithoutIsset
{
    public function __get(string $name): string { return "magic:$name"; }
}

final class ArrayAccessFixture implements \ArrayAccess
{
    public function __construct(private array $data) {}
    public function offsetExists(mixed $offset): bool { return isset($this->data[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->data[$offset]; }
    public function offsetSet(mixed $offset, mixed $value): void { $this->data[$offset] = $value; }
    public function offsetUnset(mixed $offset): void { unset($this->data[$offset]); }
}

final class MethodNamedLikeProperty
{
    public function name(): string { return 'method-name'; }
}

final class UninitializedTyped
{
    public int $age;
}

final class PublicStaticProperty
{
    public static string $name = 'static';
}

final class PrivateGetterOnly
{
    private function getName(): string { return 'private-getter'; }
}

final class PrivateGetterWithPublicProperty
{
    public string $name = 'pub';
    private function getName(): string { return 'private-getter'; }
}

final class ReadonlyFixture
{
    public function __construct(public readonly string $name) {}
}

enum StatusFixture: string
{
    case Active = 'A';
}

// ===== Fixtures for the non-public last resort (1.4.0, forward 021, RN-07) =====

final class PrivateShadowedByGetter
{
    private string $name = 'from-field';
    public function getName(): string { return 'from-getter'; }
}

final class PrivateShadowedByMagicGet
{
    private string $name = 'from-field';
    public function __get(string $name): string { return "magic:$name"; }
    public function __isset(string $name): bool { return true; }
}

final class PrivateGetterOverPrivateProperty
{
    private string $name = 'field';
    private function getName(): string { return 'private-getter'; }
}

class PrivateParent
{
    private string $secret = 'parent-private';
    protected string $level = 'parent-protected';
}

final class ChildOfPrivateParent extends PrivateParent
{
    private string $own = 'child-private';
}

final class PrivateStaticAndUninitialized
{
    private static string $shared = 'private-static';
    private int $pending;
}
