<?php

namespace Antevemus\ALinq\Tests\Unit;

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
}
