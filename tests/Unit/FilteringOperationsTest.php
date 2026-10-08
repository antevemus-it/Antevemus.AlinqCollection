<?php

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Filtering Operations
 * Tests all 17 filtering methods in FilteringOperations trait
 */
class FilteringOperationsTest extends TestCase
{
    // ===== WHERE TESTS =====

    /**
     * Test where filters elements based on predicate
     * Lists are reindexed starting from 0
     */
    public function testWhereFiltersElements(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->where(fn($x) => $x > 3)->toArray();

        $this->assertEquals([4, 5], $result);
    }

    /**
     * Test where with empty result
     */
    public function testWhereReturnsEmptyWhenNoMatch(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->where(fn($x) => $x > 10);

        $this->assertEmpty($result->toArray());
    }

    /**
     * Test where with key and value
     */
    public function testWhereWithKeyAndValue(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);
        $result = $collection->where(fn($value, $key) => $key === 'b')->toArray();

        $this->assertEquals(['b' => 2], $result);
    }

    // ===== TAKE TESTS =====

    /**
     * Test take returns first n elements
     */
    public function testTakeReturnsFirstNElements(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->take(3);

        $this->assertEquals([1, 2, 3], $result->toArray());
    }

    /**
     * Test take with count larger than collection size
     */
    public function testTakeBeyondCollectionSize(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->take(10);

        $this->assertEquals([1, 2, 3], $result->toArray());
    }

    /**
     * Test take with zero
     */
    public function testTakeWithZero(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->take(0);

        $this->assertEmpty($result->toArray());
    }

    // ===== SKIP TESTS =====

    /**
     * Test skip removes first n elements
     */
    public function testSkipRemovesFirstNElements(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->skip(2);

        // array_slice reindexa arrays numéricos por padrão
        $this->assertEquals([3, 4, 5], array_values($result->toArray()));
    }

    /**
     * Test skip beyond collection size
     */
    public function testSkipBeyondCollectionSize(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->skip(10);

        $this->assertEmpty($result->toArray());
    }

    // ===== DISTINCT TESTS =====

    /**
     * Test distinct removes duplicate values
     */
    public function testDistinctRemovesDuplicates(): void
    {
        $collection = ALinqCollection::from([1, 2, 2, 3, 3, 3, 4]);
        $result = $collection->distinct();

        $this->assertEquals([1, 2, 3, 4], $result->toArray());
    }

    /**
     * Test distinct with key selector
     */
    public function testDistinctWithKeySelector(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 1, 'name' => 'Charlie']
        ]);
        $result = $collection->distinct(fn($item) => $item['id']);

        $this->assertCount(2, $result->toArray());
    }

    /**
     * Test distinct on empty collection
     */
    public function testDistinctOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();
        $result = $collection->distinct();

        $this->assertEmpty($result->toArray());
    }

    // ===== DISTINCT BY TESTS =====

    /**
     * Test distinctBy with key selector
     */
    public function testDistinctByWithKeySelector(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1, 'category' => 'A'],
            ['id' => 2, 'category' => 'B'],
            ['id' => 3, 'category' => 'A'],
            ['id' => 4, 'category' => 'C']
        ]);
        $result = $collection->distinctBy(fn($item) => $item['category']);

        $this->assertCount(3, $result->toArray());
    }

    /**
     * Test distinctBy with object key
     */
    public function testDistinctByWithObjectKey(): void
    {
        $obj1 = (object)['id' => 1];
        $obj2 = (object)['id' => 2];

        $collection = ALinqCollection::from([
            ['ref' => $obj1],
            ['ref' => $obj2],
            ['ref' => $obj1]
        ]);
        $result = $collection->distinctBy(fn($item) => $item['ref']);

        $this->assertCount(2, $result->toArray());
    }

    // ===== FIRST / FIRST OR DEFAULT TESTS =====

    /**
     * Test first returns first element
     */
    public function testFirstReturnsFirstElement(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->first();

        $this->assertEquals(1, $result);
    }

    /**
     * Test first with predicate
     */
    public function testFirstWithPredicate(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->first(fn($x) => $x > 3);

        $this->assertEquals(4, $result);
    }

    /**
     * Test firstOrDefault returns default when empty
     */
    public function testFirstOrDefaultReturnsDefault(): void
    {
        $collection = ALinqCollection::empty();
        $result = $collection->firstOrDefault('default');

        $this->assertEquals('default', $result);
    }

    /**
     * Test firstOrDefault with predicate no match
     */
    public function testFirstOrDefaultWithPredicateNoMatch(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->firstOrDefault('default', fn($x) => $x > 10);

        $this->assertEquals('default', $result);
    }

    // ===== LAST / LAST OR DEFAULT TESTS =====

    /**
     * Test last returns last element
     */
    public function testLastReturnsLastElement(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->last();

        $this->assertEquals(5, $result);
    }

    /**
     * Test last with predicate
     */
    public function testLastWithPredicate(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->last(fn($x) => $x < 4);

        $this->assertEquals(3, $result);
    }

    /**
     * Test lastOrDefault returns default when empty
     */
    public function testLastOrDefaultReturnsDefault(): void
    {
        $collection = ALinqCollection::empty();
        $result = $collection->lastOrDefault('default');

        $this->assertEquals('default', $result);
    }

    /**
     * Review 2026-10-08, 2.5: the predicate of last()/lastOrDefault() received a reversed,
     * reindexed key, so `fn($v, $k) => $k !== 0` on [1..5] answered 4 and `$k === 0` answered 5.
     */
    public function testLastWithKeyPredicateSeesTheRealKeys(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        $this->assertSame(5, $collection->last(fn($v, $k) => $k !== 0));
        $this->assertSame(1, $collection->lastOrDefault('d', fn($v, $k) => $k === 0));
        $this->assertSame('d', $collection->lastOrDefault('d', fn($v, $k) => $k > 10));

        // Same answer as first() for a predicate that matches a single key.
        $this->assertSame($collection->first(fn($v, $k) => $k === 2), $collection->last(fn($v, $k) => $k === 2));

        // Dictionary keys were never affected; they must keep working.
        $this->assertSame(2, ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3])->lastOrDefault(null, fn($v, $k) => $k === 'b'));
    }

    // ===== SINGLE OR DEFAULT TESTS =====

    /**
     * Test singleOrDefault returns single element
     */
    public function testSingleOrDefaultReturnsSingleElement(): void
    {
        $collection = ALinqCollection::from([42]);
        $result = $collection->singleOrDefault();

        $this->assertEquals(42, $result);
    }

    /**
     * Test singleOrDefault with predicate
     */
    public function testSingleOrDefaultWithPredicate(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->singleOrDefault(null, fn($x) => $x === 3);

        $this->assertEquals(3, $result);
    }

    /**
     * Test singleOrDefault returns default when empty
     */
    public function testSingleOrDefaultReturnsDefaultWhenEmpty(): void
    {
        $collection = ALinqCollection::empty();
        $result = $collection->singleOrDefault('default');

        $this->assertEquals('default', $result);
    }

    /**
     * Test singleOrDefault throws exception with multiple elements
     */
    public function testSingleOrDefaultThrowsExceptionWithMultiple(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Sequence contains more than one matching element");

        $collection = ALinqCollection::from([1, 2, 3]);
        $collection->singleOrDefault();
    }

    // ===== FIND KEY TESTS =====

    /**
     * Test findKey returns first matching key
     */
    public function testFindKeyReturnsFirstMatchingKey(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);
        $result = $collection->findKey(fn($value) => $value > 1);

        $this->assertEquals('b', $result);
    }

    /**
     * Test findKey returns null when no match
     */
    public function testFindKeyReturnsNullWhenNoMatch(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->findKey(fn($value) => $value > 10);

        $this->assertNull($result);
    }

    // ===== KEY EXISTS TESTS =====

    /**
     * Test keyExists returns true for existing key
     */
    public function testKeyExistsReturnsTrueForExistingKey(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2]);

        $this->assertTrue($collection->keyExists('a'));
        $this->assertTrue($collection->keyExists('b'));
    }

    /**
     * Test keyExists returns false for non-existing key
     */
    public function testKeyExistsReturnsFalseForNonExistingKey(): void
    {
        $collection = ALinqCollection::from(['a' => 1]);

        $this->assertFalse($collection->keyExists('z'));
    }

    /**
     * Test keyExists with numeric keys
     */
    public function testKeyExistsWithNumericKeys(): void
    {
        $collection = ALinqCollection::from([10 => 'a', 20 => 'b']);

        $this->assertTrue($collection->keyExists(10));
        $this->assertFalse($collection->keyExists(0));
    }

    // ===== CHUNK TESTS =====

    /**
     * Test chunk splits collection into smaller collections
     */
    public function testChunkSplitsCollection(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5, 6, 7]);
        $result = $collection->chunk(3);

        $expected = [[1, 2, 3], [4, 5, 6], [7]];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test chunk preserves keys
     */
    public function testChunkPreservesKeys(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);
        $result = $collection->chunk(2);

        $this->assertCount(2, $result->toArray());
    }

    // ===== PAD TESTS =====

    /**
     * Test pad extends collection to specified size
     */
    public function testPadExtendsCollection(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->pad(5, 0);

        $this->assertEquals([1, 2, 3, 0, 0], $result->toArray());
    }

    /**
     * Test pad with negative size pads at beginning
     */
    public function testPadWithNegativeSizePadsAtBeginning(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->pad(-5, 0);

        $this->assertEquals([0, 0, 1, 2, 3], $result->toArray());
    }

    /**
     * Test pad does nothing when size is smaller than collection
     */
    public function testPadDoesNothingWhenSmallerThanCollection(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->pad(3, 0);

        $this->assertEquals([1, 2, 3, 4, 5], $result->toArray());
    }

    // ===== SHUFFLE TESTS =====

    /**
     * Test shuffle randomizes collection order
     */
    public function testShuffleRandomizesOrder(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->shuffle();

        $this->assertCount(5, $result->toArray());
        $this->assertContains(1, $result->toArray());
        $this->assertContains(5, $result->toArray());
    }

    /**
     * Test shuffle returns new collection
     */
    public function testShuffleReturnsNewCollection(): void
    {
        $original = ALinqCollection::from([1, 2, 3]);
        $shuffled = $original->shuffle();

        $this->assertNotSame($original, $shuffled);
    }

    // ===== CONTAINS TESTS =====

    /**
     * Test contains returns true for existing value
     */
    public function testContainsReturnsTrueForExistingValue(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        $this->assertTrue($collection->contains(3));
    }

    /**
     * Test contains returns false for non-existing value
     */
    public function testContainsReturnsFalseForNonExistingValue(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);

        $this->assertFalse($collection->contains(10));
    }

    /**
     * Test contains with strict comparison
     */
    public function testContainsWithStrictComparison(): void
    {
        $collection = ALinqCollection::from([1, 2, '3']);

        $this->assertFalse($collection->contains(3));
        $this->assertTrue($collection->contains('3'));
    }

    /**
     * Test contains with custom comparer
     */
    public function testContainsWithCustomComparer(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob']
        ]);

        $comparer = fn($item, $value) => $item['id'] === $value['id'] ? 0 : 1;

        $this->assertTrue($collection->contains(['id' => 1, 'name' => 'Different'], $comparer));
        $this->assertFalse($collection->contains(['id' => 99, 'name' => 'NotFound'], $comparer));
    }

    /**
     * Test fluent API chaining with filtering operations
     */
    public function testFluentApiChaining(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        $result = $collection
            ->where(fn($x) => $x > 3)
            ->take(5)
            ->skip(1)
            ->distinct();

        // skip() reindexa o array, então usamos array_values para comparar valores
        $this->assertEquals([5, 6, 7, 8], array_values($result->toArray()));
    }

    /**
     * Test filtering on empty collection
     */
    public function testFilteringOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertEmpty($collection->where(fn($x) => true)->toArray());
        $this->assertEmpty($collection->take(5)->toArray());
        $this->assertEmpty($collection->skip(2)->toArray());
        $this->assertEmpty($collection->distinct()->toArray());
    }
}
