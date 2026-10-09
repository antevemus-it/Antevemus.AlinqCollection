<?php

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use PHPUnit\Framework\TestCase;
use ArrayIterator;

/**
 * Test class for ALinqCollection base functionality
 */
class ALinqCollectionTest extends TestCase
{
    /**
     * Test creating a collection from an array using from() method
     */
    public function testFromCreatesCollectionFromArray(): void
    {
        $data = [1, 2, 3, 4, 5];
        $collection = ALinqCollection::from($data);

        $this->assertInstanceOf(ALinqCollection::class, $collection);
        $this->assertEquals($data, $collection->toArray());
    }

    /**
     * Test creating a collection from an empty array
     */
    public function testFromCreatesCollectionFromEmptyArray(): void
    {
        $collection = ALinqCollection::from([]);

        $this->assertInstanceOf(ALinqCollection::class, $collection);
        $this->assertEmpty($collection->toArray());
        $this->assertEquals(0, $collection->count());
    }

    /**
     * Test creating an empty collection using empty() method
     */
    public function testEmptyCreatesEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertInstanceOf(ALinqCollection::class, $collection);
        $this->assertEmpty($collection->toArray());
        $this->assertEquals(0, $collection->count());
    }

    /**
     * Test creating a range of numbers
     */
    public function testRangeCreatesSequenceOfNumbers(): void
    {
        $collection = ALinqCollection::range(1, 5);

        $this->assertEquals([1, 2, 3, 4, 5], $collection->toArray());
    }

    /**
     * Test creating a range with custom step
     */
    public function testRangeWithCustomStep(): void
    {
        $collection = ALinqCollection::range(0, 10, 2);

        $this->assertEquals([0, 2, 4, 6, 8, 10], $collection->toArray());
    }

    /**
     * Test creating a descending range
     */
    public function testRangeDescending(): void
    {
        $collection = ALinqCollection::range(5, 1);

        $this->assertEquals([5, 4, 3, 2, 1], $collection->toArray());
    }

    /**
     * Test repeat method creates collection with repeated elements
     */
    public function testRepeatCreatesCollectionWithRepeatedElements(): void
    {
        $collection = ALinqCollection::repeat('test', 3);

        $this->assertEquals(['test', 'test', 'test'], $collection->toArray());
    }

    /**
     * Test repeat with zero count
     */
    public function testRepeatWithZeroCount(): void
    {
        $collection = ALinqCollection::repeat('value', 0);

        $this->assertEmpty($collection->toArray());
    }

    /**
     * Test repeat with negative count throws exception
     */
    public function testRepeatWithNegativeCountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Count cannot be negative");

        ALinqCollection::repeat('value', -1);
    }

    /**
     * Test repeat with complex object
     */
    public function testRepeatWithComplexObject(): void
    {
        $object = (object)['key' => 'value'];
        $collection = ALinqCollection::repeat($object, 2);

        $result = $collection->toArray();
        $this->assertCount(2, $result);
        $this->assertSame($object, $result[0]);
        $this->assertSame($object, $result[1]);
    }

    /**
     * Test toArray returns internal array
     */
    public function testToArrayReturnsInternalArray(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $collection = ALinqCollection::from($data);

        $this->assertEquals($data, $collection->toArray());
    }

    /**
     * Test count returns correct number of elements
     */
    public function testCountReturnsCorrectNumberOfElements(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        $this->assertEquals(5, $collection->count());
        $this->assertCount(5, $collection);
    }

    /**
     * Test count on empty collection
     */
    public function testCountOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertEquals(0, $collection->count());
    }

    /**
     * Test getIterator returns Traversable
     */
    public function testGetIteratorReturnsTraversable(): void
    {
        $data = [1, 2, 3];
        $collection = ALinqCollection::from($data);

        $iterator = $collection->getIterator();

        $this->assertInstanceOf(\Traversable::class, $iterator);
        $this->assertInstanceOf(ArrayIterator::class, $iterator);
    }

    /**
     * Test collection can be iterated with foreach
     */
    public function testCollectionCanBeIteratedWithForeach(): void
    {
        $data = ['a' => 1, 'b' => 2, 'c' => 3];
        $collection = ALinqCollection::from($data);

        $result = [];
        foreach ($collection as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertEquals($data, $result);
    }

    /**
     * Test collection preserves keys
     */
    public function testCollectionPreservesKeys(): void
    {
        $data = [10 => 'a', 20 => 'b', 30 => 'c'];
        $collection = ALinqCollection::from($data);

        $this->assertEquals($data, $collection->toArray());
    }

    /**
     * Test collection with associative array
     */
    public function testCollectionWithAssociativeArray(): void
    {
        $data = ['name' => 'John', 'age' => 30, 'city' => 'New York'];
        $collection = ALinqCollection::from($data);

        $this->assertEquals($data, $collection->toArray());
        $this->assertEquals(3, $collection->count());
    }

    /**
     * Test collection with mixed types
     */
    public function testCollectionWithMixedTypes(): void
    {
        $data = [1, 'string', 3.14, true, null, ['nested']];
        $collection = ALinqCollection::from($data);

        $this->assertEquals($data, $collection->toArray());
        $this->assertEquals(6, $collection->count());
    }

    /**
     * Test collection constructor requires array parameter (type safety)
     */
    public function testConstructorRequiresArrayParameter(): void
    {
        // O construtor tem type hint array, então null não é aceito
        $this->expectException(\TypeError::class);
        new ALinqCollection(null);
    }

    // ===== CONTRACT 1.3.0: count($predicate) (RN-23), JsonSerializable (RN-24), interfaces (RN-27) =====

    public function testCountWithPredicate(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4]);

        // Before 1.3.0 the predicate was silently discarded and count() answered 4
        $this->assertSame(2, $collection->count(fn($v) => $v > 2));
        $this->assertSame(2, $collection->count(fn($v, $k) => $k % 2 === 0), 'two-parameter predicates receive the key');
        $this->assertSame(4, $collection->count('is_int'), 'native callables receive the item only');
        $this->assertSame(0, ALinqCollection::empty()->count(fn($v) => true));
        $this->assertSame(4, $collection->count());
        $this->assertCount(4, $collection);
    }

    public function testJsonSerializeListAndDictionary(): void
    {
        // Before 1.3.0 json_encode() of a collection returned "{}"
        $this->assertSame('[1,2,3]', json_encode(ALinqCollection::from([1, 2, 3])));
        $this->assertSame('{"a":1,"b":2}', json_encode(ALinqCollection::from(['a' => 1, 'b' => 2])));
        $this->assertSame('[]', json_encode(ALinqCollection::empty()));
        $this->assertSame(
            '{"items":[{"id":1}]}',
            json_encode(['items' => ALinqCollection::from([['id' => 1]])]),
            'nested inside another structure'
        );
        $this->assertInstanceOf(\JsonSerializable::class, ALinqCollection::empty());
        $this->assertSame([10 => 'a'], ALinqCollection::from([10 => 'a'])->jsonSerialize());
    }

    public function testLazyFacadesAreDeclaredByTheInterface(): void
    {
        $interface = new \ReflectionClass(\Antevemus\ALinq\Interfaces\IALinqBaseCollection::class);

        foreach (['lazy', 'fromFile', 'fromCsv', 'fromCursor', 'count', 'jsonSerialize'] as $method) {
            $this->assertTrue($interface->hasMethod($method), "IALinqBaseCollection must declare $method()");
        }
        $this->assertTrue($interface->implementsInterface(\JsonSerializable::class));

        $lazy = new \ReflectionClass(\Antevemus\ALinq\Interfaces\IALinqLazyCollection::class);
        foreach (['fromCursor', 'count', 'jsonSerialize'] as $method) {
            $this->assertTrue($lazy->hasMethod($method), "IALinqLazyCollection must declare $method()");
        }
        $this->assertTrue($lazy->implementsInterface(\JsonSerializable::class));
    }

    /**
     * L4 (review 2026-10-08, process): the eager fromCsv()/fromCursor() facades had no test.
     * They hand the work to ALinqLazyCollection and return it.
     */
    public function testFromCsvAndFromCursorFacadesDelegateToTheLazyCollection(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'alinq_facade_csv_');
        file_put_contents($csv, "id,name\n1,Ana\n2,Bia\n");
        try {
            $rows = ALinqCollection::fromCsv($csv);
            $this->assertInstanceOf(ALinqLazyCollection::class, $rows);
            $this->assertSame([['id' => '1', 'name' => 'Ana'], ['id' => '2', 'name' => 'Bia']], $rows->toArray());
            $this->assertSame(['1;Ana', '2;Bia'], ALinqCollection::fromCsv($csv, ',', '"', '\\', false)->skip(1)->select(fn($r) => implode(';', $r))->toArray());
        } finally {
            unlink($csv);
        }

        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, n TEXT)');
        $pdo->exec("INSERT INTO t (n) VALUES ('a'), ('b')");
        $cursor = ALinqCollection::fromCursor($pdo->query('SELECT id, n FROM t ORDER BY id'), fn(array $row) => $row['n']);
        $this->assertInstanceOf(ALinqLazyCollection::class, $cursor);
        $this->assertSame(['a', 'b'], $cursor->toArray());
    }
}
