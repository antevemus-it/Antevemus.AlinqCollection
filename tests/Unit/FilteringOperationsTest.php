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

        // RN-02: a list comes out reindexed
        $this->assertSame([3, 4, 5], $result->toArray());
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
        $this->expectExceptionMessage("Collection contains more than one matching element.");

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
     * D10 (forward 015): chunk() splits the collection into ALinqCollection chunks that stay
     * queryable (before 1.3.0: native arrays, which lose the collection API).
     */
    public function testChunkSplitsCollectionIntoCollections(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5, 6, 7]);
        $result = $collection->chunk(3);

        $this->assertContainsOnlyInstancesOf(ALinqCollection::class, $result->toArray());
        $this->assertSame([[1, 2, 3], [4, 5, 6], [7]], self::chunks($result));
        $this->assertSame([6, 15, 7], $result->select(fn(ALinqCollection $chunk) => $chunk->sum())->toArray());
        $this->assertSame('[[1,2,3],[4,5,6],[7]]', json_encode($result));
    }

    /**
     * RN-02: chunk() is a list of collections; inside each chunk a dictionary keeps its keys
     * and a list is reindexed. Before 1.3.0 this test only counted the chunks and chunk()
     * never preserved keys (review 2026-10-08, 2.8).
     */
    public function testChunkPreservesKeys(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);
        $result = $collection->chunk(2);

        $this->assertSame([['a' => 1, 'b' => 2], ['c' => 3]], self::chunks($result));
        $this->assertSame([[1, 2], [3]], self::chunks(ALinqCollection::from([1, 2, 3])->chunk(2)));
        $this->assertSame([[10 => 'x', 20 => 'y']], self::chunks(ALinqCollection::from([10 => 'x', 20 => 'y'])->chunk(5)));
    }

    /**
     * @return array<int, array> the chunks as native arrays
     */
    private static function chunks(ALinqCollection $chunked): array
    {
        return array_map(static fn(ALinqCollection $chunk) => $chunk->toArray(), $chunked->toArray());
    }

    /**
     * RN-13 (forward 015, D4): chunk(0) throws the library's own InvalidArgumentException
     * instead of a native ValueError.
     */
    public function testChunkWithSizeBelowOneThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('chunk() expects size to be at least 1, 0 given');

        ALinqCollection::from([1, 2])->chunk(0);
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
     * RN-13 (forward 015, D4): a negative pad size throws. Before 1.3.0 array_pad() padded
     * at the beginning, which the lazy side cannot do without buffering.
     */
    public function testPadWithNegativeSizeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('pad() expects size to be at least 0, -5 given');

        ALinqCollection::from([1, 2, 3])->pad(-5, 0);
    }

    /**
     * RN-02: pad() on a dictionary keeps the keys and appends the padding.
     */
    public function testPadKeepsDictionaryKeysAndAppendsPadding(): void
    {
        $this->assertSame(['a' => 1, 'b' => 2, 0 => 'p', 1 => 'p'], ALinqCollection::from(['a' => 1, 'b' => 2])->pad(4, 'p')->toArray());
        $this->assertSame([5 => 'x', 6 => 'p'], ALinqCollection::from([5 => 'x'])->pad(2, 'p')->toArray());
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
     * RN-09 (forward 015): a comparer may answer bool (true = equal) or `<=>` (0 = equal).
     * Before 1.3.0 only `=== 0` was accepted, so `fn($a, $b) => $a == $b` never found anything
     * (review 2026-10-08, 2.9) while the lazy side accepted only truthy (4.6).
     */
    public function testContainsAcceptsBooleanOrSpaceshipComparer(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);

        $this->assertTrue($collection->contains(2, fn($a, $b) => $a == $b));
        $this->assertFalse($collection->contains(9, fn($a, $b) => $a == $b));
        $this->assertTrue($collection->contains(3, fn($a, $b) => $a <=> $b));
        $this->assertFalse($collection->contains(99, fn($a, $b) => $a <=> $b));
        $this->assertTrue($collection->contains('3', fn($a, $b) => $a === (int) $b));
    }

    /**
     * RN-02 (forward 015, decision 1a): subset operations reindex a list and keep the keys of
     * a dictionary, including a dictionary with non-sequential integer keys. Before 1.3.0
     * distinct() always reindexed and take()/skip() reindexed integer keys (review 2026-10-08, 2.8).
     */
    public function testSubsetOperationsReindexListsAndKeepDictionaryKeys(): void
    {
        $dict = ALinqCollection::from(['x' => 10, 'y' => 20, 'z' => 30, 'w' => 20]);
        $this->assertSame(['x' => 10, 'y' => 20, 'z' => 30], $dict->distinct()->toArray());
        $this->assertSame(['x' => 10, 'y' => 20], $dict->distinctBy(fn($v) => $v > 15)->toArray());
        $this->assertSame(['x' => 10, 'y' => 20], $dict->take(2)->toArray());
        $this->assertSame(['z' => 30, 'w' => 20], $dict->skip(2)->toArray());
        $this->assertSame(['z' => 30], $dict->where(fn($v) => $v > 25)->toArray());
        $this->assertSame(['y' => 20, 'z' => 30, 'w' => 20], $dict->where(fn($v) => $v > 15)->skip(0)->toArray());

        $intKeyed = ALinqCollection::from([10 => 'a', 20 => 'b', 30 => 'a']);
        $this->assertSame([10 => 'a', 20 => 'b'], $intKeyed->distinct()->toArray());
        $this->assertSame([20 => 'b', 30 => 'a'], $intKeyed->skip(1)->toArray());
        $this->assertSame([10 => 'a'], $intKeyed->take(1)->toArray());

        $list = ALinqCollection::from([10, 20, 30, 20]);
        $this->assertSame([10, 20, 30], $list->distinct()->toArray());
        $this->assertSame([30, 20], $list->skip(2)->toArray());
        $this->assertSame([10, 20], $list->take(2)->toArray());
        $this->assertSame([20, 30, 20], $list->where(fn($v) => $v > 15)->toArray());
    }

    /**
     * RN-02: shuffle() of a dictionary keeps every item under its key.
     */
    public function testShuffleKeepsDictionaryKeys(): void
    {
        $dict = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
        $shuffled = ALinqCollection::from($dict)->shuffle()->toArray();

        $this->assertCount(4, $shuffled);
        foreach ($dict as $key => $value) {
            $this->assertSame($value, $shuffled[$key]);
        }

        $list = ALinqCollection::from([1, 2, 3, 4])->shuffle()->toArray();
        $this->assertTrue(array_is_list($list));
        sort($list);
        $this->assertSame([1, 2, 3, 4], $list);
    }

    /**
     * RN-13 (forward 015, D4): negative take()/skip() throw. Before 1.3.0 array_slice()
     * counted from the end (take(-2) on [1..5] returned [1, 2, 3], review 2026-10-08, 2.7).
     */
    public function testNegativeTakeAndSkipThrow(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        try {
            $collection->take(-2);
            $this->fail('take(-2) must throw');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('take() expects count to be at least 0, -2 given', $e->getMessage());
        }
        try {
            $collection->skip(-2);
            $this->fail('skip(-2) must throw');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('skip() expects count to be at least 0, -2 given', $e->getMessage());
        }

        $this->assertSame([], $collection->take(0)->toArray());
        $this->assertSame([1, 2, 3, 4, 5], $collection->skip(0)->toArray());
    }

    /**
     * RN-11 (forward 015, decision 2a): first()/last() throw UnderflowException when the
     * collection is empty or nothing matches; the *OrDefault variants return the default.
     * Before 1.3.0 first()/last() returned null.
     */
    public function testFirstAndLastThrowOnEmptyOrNoMatch(): void
    {
        $empty = ALinqCollection::empty();
        $items = ALinqCollection::from([1, 2, 3]);

        $calls = [
            'Cannot take first() of an empty collection.' => fn() => $empty->first(),
            'Cannot take last() of an empty collection.' => fn() => $empty->last(),
            'first(): no element matches the predicate.' => fn() => $items->first(fn($v) => $v > 5),
            'last(): no element matches the predicate.' => fn() => $items->last(fn($v) => $v > 5),
        ];
        foreach ($calls as $message => $call) {
            try {
                $call();
                $this->fail('first()/last() must throw');
            } catch (\UnderflowException $e) {
                // Same text as the lazy side: the contract is the class and the message.
                $this->assertSame($message, $e->getMessage());
            }
        }

        $this->assertSame('d', $empty->firstOrDefault('d'));
        $this->assertSame('d', $empty->lastOrDefault('d'));
        $this->assertSame('d', $items->firstOrDefault('d', fn($v) => $v > 5));
        $this->assertSame('d', $items->lastOrDefault('d', fn($v) => $v > 5));
    }

    /**
     * RN-05: a stored null is an element; only the absence of a match returns the default.
     * first()/last() no longer move the internal pointer (review 2026-10-08, 2.13 and 2.14).
     */
    public function testNullElementIsDistinguishedFromAbsence(): void
    {
        $withNull = ALinqCollection::from([null, 1]);

        $this->assertNull($withNull->first());
        $this->assertNull($withNull->firstOrDefault('DEF'));
        $this->assertNull($withNull->firstOrDefault('DEF', fn($v) => $v === null));
        $this->assertNull($withNull->lastOrDefault('DEF', fn($v) => $v === null));
        $this->assertSame('DEF', $withNull->firstOrDefault('DEF', fn($v) => $v === 2));

        $pointer = ALinqCollection::from([1, 2, 3]);
        $pointer->next();
        $this->assertSame(2, $pointer->current());
        $pointer->first();
        $pointer->last();
        $this->assertSame(2, $pointer->current());
    }

    /**
     * RN-12: singleOrDefault() with more than one match throws OverflowException, which
     * still extends RuntimeException (the exception type before 1.3.0).
     */
    public function testSingleOrDefaultThrowsOverflowException(): void
    {
        try {
            ALinqCollection::from([1, 2])->singleOrDefault();
            $this->fail('singleOrDefault() with two items must throw');
        } catch (\OverflowException $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e);
        }

        $this->assertSame(2, ALinqCollection::from(['a' => 1, 'b' => 2])->singleOrDefault(null, fn($v) => $v === 2));
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

        // RN-02: every step reindexes the list
        $this->assertSame([5, 6, 7, 8], $result->toArray());
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


    /**
     * Review 2026-10-08, 2.2 / decision 4a: a native one-argument callable works in every
     * predicate operator (before: ArgumentCountError from array_filter/array_find/array_any
     * passing the key), and a two-parameter closure still receives the key.
     */
    public function testNativeCallablesWorkInEveryPredicateOperator(): void
    {
        $mixed = ALinqCollection::from([1, 'a', 2, 'b']);

        $this->assertSame([1, 2], $mixed->where('is_int')->toArray());
        $this->assertSame([1, 2], $mixed->where(is_int(...))->toArray());
        $this->assertSame(1, $mixed->first('is_int'));
        $this->assertSame(2, $mixed->last('is_int'));
        $this->assertSame('a', $mixed->firstOrDefault(null, 'is_string'));
        $this->assertSame('b', $mixed->lastOrDefault(null, 'is_string'));
        $this->assertSame(1, $mixed->findKey('is_string'));
        $this->assertSame('b', ALinqCollection::from([1, 'b'])->singleOrDefault(null, 'is_string'));

        // Two-parameter predicates keep receiving the key
        $this->assertSame([2 => 2, 3 => 'b'], ALinqCollection::from([1, 'a', 2, 'b'])->where(fn($v, $k) => $k >= 2)->toArray() === [2, 'b']
            ? [2 => 2, 3 => 'b'] : ALinqCollection::from([1, 'a', 2, 'b'])->where(fn($v, $k) => $k >= 2)->toArray(),
            'sanity: list is reindexed by where()');
        $this->assertSame([2, 'b'], ALinqCollection::from([1, 'a', 2, 'b'])->where(fn($v, $k) => $k >= 2)->toArray());
        $this->assertSame(2, ALinqCollection::from(['x' => 1, 'y' => 2])->first(fn($v, $k) => $k === 'y'));
    }

    /**
     * Review 2026-10-08, 2.3 / 2.11: distinct() is strict and type-aware, safe for arrays
     * (by value) and objects (by identity), O(1) per item in both forms.
     */
    public function testDistinctIsStrictAndSafeForArraysAndObjects(): void
    {
        $this->assertSame([[1, 2], [3, 4]], ALinqCollection::from([[1, 2], [3, 4], [1, 2]])->distinct()->toArray());

        $o1 = new \stdClass();
        $o2 = new \stdClass();
        $this->assertCount(2, ALinqCollection::from([$o1, $o2, $o1])->distinct()->toArray());

        $this->assertSame([1, '1', true, 1.0], ALinqCollection::from([1, '1', true, 1.0, 1])->distinct()->toArray());
        $this->assertSame([null, '', false, 0, '0'], ALinqCollection::from([null, '', false, 0, '0', null])->distinct()->toArray());
        $this->assertCount(1, ALinqCollection::from([NAN, NAN])->distinct()->toArray());

        // With a selector, same identity rule; native selector accepted
        $this->assertSame(['a', 'bb'], ALinqCollection::from(['a', 'bb', 'cc', 'd'])->distinct('strlen')->toArray());
        $this->assertSame([['k' => [1]], ['k' => [2]]], ALinqCollection::from([['k' => [1]], ['k' => [2]], ['k' => [1]]])->distinct(fn($v) => $v['k'])->toArray());
    }
}
