<?php

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Test class for Selection Operations
 * Tests all 6 selection methods in SelectionOperations trait
 */
class SelectionOperationsTest extends TestCase
{
    // ===== SELECT TESTS =====

    /**
     * Test select projects elements to new form
     */
    public function testSelectProjectsElementsToNewForm(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->select(fn($x) => $x * 2);

        $this->assertEquals([2, 4, 6, 8, 10], $result->toArray());
    }

    /**
     * Test select with complex transformation
     */
    public function testSelectWithComplexTransformation(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30]
        ]);

        $result = $collection->select(fn($item) => $item['name'] . ' is ' . $item['age']);

        $expected = ['Alice is 25', 'Bob is 30'];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test select on empty collection
     */
    public function testSelectOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();
        $result = $collection->select(fn($x) => $x * 2);

        $this->assertEmpty($result->toArray());
    }

    /**
     * Test select preserves keys
     */
    public function testSelectPreservesKeys(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);
        $result = $collection->select(fn($x) => $x * 10);

        $expected = ['a' => 10, 'b' => 20, 'c' => 30];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test select to create objects
     */
    public function testSelectToCreateObjects(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->select(fn($x) => (object)['value' => $x]);

        $resultArray = $result->toArray();
        $this->assertCount(3, $resultArray);
        $this->assertInstanceOf(stdClass::class, $resultArray[0]);
        $this->assertEquals(1, $resultArray[0]->value);
    }

    // ===== SELECT MANY TESTS =====

    /**
     * Test selectMany flattens nested arrays
     */
    public function testSelectManyFlattensNestedArrays(): void
    {
        $collection = ALinqCollection::from([
            [1, 2, 3],
            [4, 5, 6],
            [7, 8, 9]
        ]);

        $result = $collection->selectMany(fn($x) => $x);

        $expected = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test selectMany with transformation
     */
    public function testSelectManyWithTransformation(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Alice', 'tags' => ['developer', 'manager']],
            ['name' => 'Bob', 'tags' => ['designer']]
        ]);

        $result = $collection->selectMany(fn($item) => $item['tags']);

        $expected = ['developer', 'manager', 'designer'];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test selectMany on empty collection
     */
    public function testSelectManyOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();
        $result = $collection->selectMany(fn($x) => $x);

        $this->assertEmpty($result->toArray());
    }

    /**
     * Test selectMany with some empty arrays
     */
    public function testSelectManyWithSomeEmptyArrays(): void
    {
        $collection = ALinqCollection::from([
            [1, 2],
            [],
            [3, 4]
        ]);

        $result = $collection->selectMany(fn($x) => $x);

        $expected = [1, 2, 3, 4];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test selectMany projects and flattens
     */
    public function testSelectManyProjectsAndFlattens(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->selectMany(fn($x) => [$x, $x * 10]);

        $expected = [1, 10, 2, 20, 3, 30];
        $this->assertEquals($expected, $result->toArray());
    }

    // ===== COLUMN TESTS =====

    /**
     * Test column extracts single column from array of arrays
     */
    public function testColumnExtractsSingleColumn(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice', 'age' => 25],
            ['id' => 2, 'name' => 'Bob', 'age' => 30],
            ['id' => 3, 'name' => 'Charlie', 'age' => 35]
        ]);

        $result = $collection->column('name');

        $expected = ['Alice', 'Bob', 'Charlie'];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test column with index key
     */
    public function testColumnWithIndexKey(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie']
        ]);

        $result = $collection->column('name', 'id');

        $expected = [1 => 'Alice', 2 => 'Bob', 3 => 'Charlie'];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test column with numeric key
     */
    public function testColumnWithNumericKey(): void
    {
        $collection = ALinqCollection::from([
            [10, 'value1'],
            [20, 'value2'],
            [30, 'value3']
        ]);

        $result = $collection->column(1, 0);

        $expected = [10 => 'value1', 20 => 'value2', 30 => 'value3'];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test column from objects
     */
    public function testColumnFromObjects(): void
    {
        $obj1 = (object)['id' => 1, 'name' => 'Alice'];
        $obj2 = (object)['id' => 2, 'name' => 'Bob'];

        $collection = ALinqCollection::from([$obj1, $obj2]);
        $result = $collection->column('name');

        $expected = ['Alice', 'Bob'];
        $this->assertEquals($expected, $result->toArray());
    }

    // ===== TO DICTIONARY TESTS =====

    /**
     * Test toDictionary creates dictionary with key selector
     */
    public function testToDictionaryCreatesDict(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie']
        ]);

        $result = $collection->toDictionary(fn($item) => $item['id']);

        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertArrayHasKey(3, $result);
        $this->assertEquals('Alice', $result[1]['name']);
    }

    /**
     * Test toDictionary with element selector
     */
    public function testToDictionaryWithElementSelector(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice', 'age' => 25],
            ['id' => 2, 'name' => 'Bob', 'age' => 30]
        ]);

        $result = $collection->toDictionary(
            fn($item) => $item['id'],
            fn($item) => $item['name']
        );

        $expected = [1 => 'Alice', 2 => 'Bob'];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test toDictionary with string keys
     */
    public function testToDictionaryWithStringKeys(): void
    {
        $collection = ALinqCollection::from([
            ['code' => 'US', 'country' => 'United States'],
            ['code' => 'UK', 'country' => 'United Kingdom']
        ]);

        $result = $collection->toDictionary(
            fn($item) => $item['code'],
            fn($item) => $item['country']
        );

        $expected = ['US' => 'United States', 'UK' => 'United Kingdom'];
        $this->assertEquals($expected, $result);
    }

    /**
     * RN-08 (forward 015, D3): a key produced twice throws, as ToDictionary does in LINQ.
     * Before 1.3.0 the second element silently overwrote the first.
     */
    public function testToDictionaryThrowsOnDuplicateKeys(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1, 'name' => 'First'],
            ['id' => 1, 'name' => 'Second']
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('toDictionary() key 1 produced twice (second time for item at key 1)');

        $collection->toDictionary(
            fn($item) => $item['id'],
            fn($item) => $item['name']
        );
    }

    /**
     * RN-07: toDictionary() validates the key like groupBy(): BackedEnum becomes its value,
     * null/bool/float/array/object throw.
     */
    public function testToDictionaryValidatesKeys(): void
    {
        $collection = ALinqCollection::from([['k' => SelectionStatus::On, 'v' => 1], ['k' => SelectionStatus::Off, 'v' => 2]]);
        $this->assertSame(['on' => 1, 'off' => 2], $collection->toDictionary(fn($i) => $i['k'], fn($i) => $i['v']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('toDictionary() expects the key selector to return an int, a string or a BackedEnum, float returned for item at key 0');
        $collection->toDictionary(fn($i) => 1.5);
    }

    // ===== TO OBJECT TESTS =====

    /**
     * Test toObject converts collection to stdClass
     */
    public function testToObjectConvertsToStdClass(): void
    {
        $collection = ALinqCollection::from([
            'name' => 'Alice',
            'age' => 25,
            'city' => 'New York'
        ]);

        $result = $collection->toObject();

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertEquals('Alice', $result->name);
        $this->assertEquals(25, $result->age);
        $this->assertEquals('New York', $result->city);
    }

    /**
     * Test toObject with numeric keys
     */
    public function testToObjectWithNumericKeys(): void
    {
        $collection = ALinqCollection::from([
            10 => 'value1',
            20 => 'value2'
        ]);

        $result = $collection->toObject();

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertEquals('value1', $result->{10});
        $this->assertEquals('value2', $result->{20});
    }

    /**
     * Test toObject preserves nested arrays
     */
    public function testToObjectPreservesNestedArrays(): void
    {
        $collection = ALinqCollection::from([
            'user' => ['name' => 'Alice', 'age' => 25],
            'status' => 'active'
        ]);

        $result = $collection->toObject();

        $this->assertIsArray($result->user);
        $this->assertEquals('Alice', $result->user['name']);
    }

    // ===== FLIP TESTS =====

    /**
     * Test flip exchanges keys and values
     */
    public function testFlipExchangesKeysAndValues(): void
    {
        $collection = ALinqCollection::from([
            'a' => 'apple',
            'b' => 'banana',
            'c' => 'cherry'
        ]);

        $result = $collection->flip();

        $expected = [
            'apple' => 'a',
            'banana' => 'b',
            'cherry' => 'c'
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test flip with numeric values becomes string keys
     */
    public function testFlipWithNumericValuesBecomesStringKeys(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);

        $result = $collection->flip();

        $expected = [1 => 'a', 2 => 'b', 3 => 'c'];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test flip handles duplicate values by keeping last key
     */
    public function testFlipHandlesDuplicateValuesByKeepingLast(): void
    {
        $collection = ALinqCollection::from([
            'first' => 'value',
            'second' => 'value'
        ]);

        $result = $collection->flip();

        $this->assertEquals(['value' => 'second'], $result->toArray());
    }

    /**
     * Test fluent API chaining with selection operations
     */
    public function testFluentApiChainingWithSelectionOperations(): void
    {
        $collection = ALinqCollection::from([
            ['category' => 'A', 'values' => [1, 2, 3]],
            ['category' => 'B', 'values' => [4, 5, 6]]
        ]);

        $result = $collection
            ->selectMany(fn($item) => $item['values'])
            ->select(fn($x) => $x * 2);

        $expected = [2, 4, 6, 8, 10, 12];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test selection operations on empty collection
     */
    public function testSelectionOperationsOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertEmpty($collection->select(fn($x) => $x)->toArray());
        $this->assertEmpty($collection->selectMany(fn($x) => $x)->toArray());
        $this->assertEquals([], $collection->toDictionary(fn($x) => $x));

        $obj = $collection->toObject();
        $this->assertInstanceOf(stdClass::class, $obj);
    }


    /**
     * Review 2026-10-08, decision 4a: the selector receives the key only when it accepts
     * two parameters; native one-argument functions keep working.
     */
    public function testSelectPassesTheKeyOnlyWhenTheSelectorAcceptsIt(): void
    {
        $this->assertSame(['A', 'B'], ALinqCollection::from(['a', 'b'])->select('strtoupper')->toArray());
        $this->assertSame(['x' => 'x=1', 'y' => 'y=2'], ALinqCollection::from(['x' => 1, 'y' => 2])->select(fn($v, $k) => "$k=$v")->toArray());
        $this->assertSame([1, 2], ALinqCollection::from(['a', 'ab'])->select(strlen(...))->toArray());
    }

    /**
     * Review 2026-10-08, 2.4: selectMany() flattens any iterable and refuses a scalar instead
     * of silently dropping it.
     */
    public function testSelectManyFlattensAnyIterableAndRefusesScalars(): void
    {
        $source = ALinqCollection::from([[1, 2], [3]]);
        $this->assertSame([1, 2, 3], $source->selectMany(fn($x) => ALinqCollection::from($x))->toArray());
        $this->assertSame([1, 2, 3], $source->selectMany(fn($x) => new \ArrayIterator($x))->toArray());
        $this->assertSame([1, 2, 3], $source->selectMany(fn($x) => (function () use ($x) { yield from $x; })())->toArray());
        $this->assertSame([1, 2, 3], $source->selectMany(fn($x) => $x)->toArray());

        $this->expectException(\UnexpectedValueException::class);
        ALinqCollection::from([1, 2])->selectMany(fn($x) => $x * 10);
    }
}

/**
 * Fixture for RN-07: a BackedEnum is accepted as dictionary key (its value is used).
 */
enum SelectionStatus: string
{
    case On = "on";
    case Off = "off";
}
