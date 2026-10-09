<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Ordering Operations
 * Tests all 6 ordering methods in OrderingOperations trait
 */
class OrderingOperationsTest extends TestCase
{
    // ===== ORDER BY TESTS =====

    /**
     * Test orderBy sorts elements in ascending order
     */
    public function testOrderBySortsInAscendingOrder(): void
    {
        $collection = ALinqCollection::from([5, 2, 8, 1, 9, 3]);
        $result = $collection->orderBy(fn($x) => $x);

        $this->assertEquals([1, 2, 3, 5, 8, 9], $result->toArray());
    }

    /**
     * Test orderBy with key selector
     */
    public function testOrderByWithKeySelector(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Charlie', 'age' => 25],
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 20]
        ]);

        $result = $collection->orderBy(fn($item) => $item['age']);

        $resultArray = $result->toArray();
        $this->assertEquals('Bob', $resultArray[0]['name']);
        $this->assertEquals('Charlie', $resultArray[1]['name']);
        $this->assertEquals('Alice', $resultArray[2]['name']);
    }

    /**
     * Test orderBy with string values
     */
    public function testOrderByWithStringValues(): void
    {
        $collection = ALinqCollection::from(['banana', 'apple', 'cherry', 'date']);
        $result = $collection->orderBy(fn($x) => $x);

        $this->assertEquals(['apple', 'banana', 'cherry', 'date'], $result->toArray());
    }

    /**
     * Test orderBy on empty collection
     */
    public function testOrderByOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();
        $result = $collection->orderBy(fn($x) => $x);

        $this->assertEmpty($result->toArray());
    }

    /**
     * Test orderBy reindexes keys
     */
    public function testOrderByKeepsDictionaryKeysAndReindexesLists(): void
    {
        // Key policy (review 2026-10-08, decision 1a): a dictionary keeps its keys, a list is
        // reindexed. Before 1.2.0 orderBy() dropped the string keys.
        $collection = ALinqCollection::from(['z' => 3, 'y' => 1, 'x' => 2]);
        $this->assertSame(['y' => 1, 'x' => 2, 'z' => 3], $collection->orderBy(fn($x) => $x)->toArray());

        $list = ALinqCollection::from([3, 1, 2]);
        $this->assertSame([1, 2, 3], $list->orderBy(fn($x) => $x)->toArray());
        $this->assertSame([3, 2, 1], $list->orderByDescending(fn($x) => $x)->toArray());
    }

    // ===== ORDER BY DESCENDING TESTS =====

    /**
     * Test orderByDescending sorts in descending order
     */
    public function testOrderByDescendingSortsInDescendingOrder(): void
    {
        $collection = ALinqCollection::from([5, 2, 8, 1, 9, 3]);
        $result = $collection->orderByDescending(fn($x) => $x);

        $this->assertEquals([9, 8, 5, 3, 2, 1], $result->toArray());
    }

    /**
     * Test orderByDescending with key selector
     */
    public function testOrderByDescendingWithKeySelector(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Alice', 'score' => 85],
            ['name' => 'Bob', 'score' => 92],
            ['name' => 'Charlie', 'score' => 78]
        ]);

        $result = $collection->orderByDescending(fn($item) => $item['score']);

        $resultArray = $result->toArray();
        $this->assertEquals('Bob', $resultArray[0]['name']);
        $this->assertEquals('Alice', $resultArray[1]['name']);
        $this->assertEquals('Charlie', $resultArray[2]['name']);
    }

    /**
     * Test orderByDescending with strings
     */
    public function testOrderByDescendingWithStrings(): void
    {
        $collection = ALinqCollection::from(['apple', 'banana', 'cherry']);
        $result = $collection->orderByDescending(fn($x) => $x);

        $this->assertEquals(['cherry', 'banana', 'apple'], $result->toArray());
    }

    // ===== ORDER BY NATURAL TESTS =====

    /**
     * Test orderByNatural uses natural sorting
     */
    public function testOrderByNaturalUsesNaturalSorting(): void
    {
        $collection = ALinqCollection::from(['item10', 'item2', 'item1', 'item20']);
        $result = $collection->orderByNatural();

        // RN-02: a list comes out reindexed (natsort() alone would keep the old indexes)
        $this->assertSame(['item1', 'item2', 'item10', 'item20'], $result->toArray());
    }

    /**
     * RN-02 (forward 015, decision 1a): every ordering reindexes a list and keeps the keys of
     * a dictionary. Before 1.3.0 orderByNatural()/orderByKey() kept the old indexes of a list
     * and orderByCustom() lost the keys of a dictionary (review 2026-10-08, 2.8).
     */
    public function testOrderingsReindexListsAndKeepDictionaryKeys(): void
    {
        $dict = ALinqCollection::from(['b' => 'item10', 'a' => 'item2', 'c' => 'item1']);
        $this->assertSame(['c' => 'item1', 'a' => 'item2', 'b' => 'item10'], $dict->orderByNatural()->toArray());
        $this->assertSame(['c' => 'item1', 'a' => 'item2', 'b' => 'item10'], $dict->orderByCustom(fn($x, $y) => strnatcmp($x, $y))->toArray());
        $this->assertSame(['a' => 'item2', 'b' => 'item10', 'c' => 'item1'], $dict->orderByKey()->toArray());
        $this->assertSame(['c' => 'item1', 'b' => 'item10', 'a' => 'item2'], $dict->orderByKey(true)->toArray());
        $this->assertSame(['c' => 'item1', 'a' => 'item2', 'b' => 'item10'], $dict->reverse()->toArray());

        $intKeyed = ALinqCollection::from([10 => 'b', 20 => 'a']);
        $this->assertSame([20 => 'a', 10 => 'b'], $intKeyed->orderBy(fn($v) => $v)->toArray());
        $this->assertSame([20 => 'a', 10 => 'b'], $intKeyed->orderByCustom(fn($x, $y) => $x <=> $y)->toArray());
        $this->assertSame([20 => 'a', 10 => 'b'], $intKeyed->reverse()->toArray());

        $list = ALinqCollection::from(['item10', 'item2', 'item1']);
        $this->assertSame(['item1', 'item2', 'item10'], $list->orderByNatural()->toArray());
        $this->assertSame(['item1', 'item10', 'item2'], $list->orderByCustom(fn($x, $y) => strcmp($x, $y))->toArray());
        $this->assertSame(['item1', 'item2', 'item10'], $list->orderByKey(true)->toArray());
        $this->assertSame(['item1', 'item2', 'item10'], $list->reverse()->toArray());
    }

    /**
     * Test orderByNatural case sensitive
     */
    public function testOrderByNaturalCaseSensitive(): void
    {
        $collection = ALinqCollection::from(['Item2', 'item1', 'Item10']);
        $result = $collection->orderByNatural(true);

        $this->assertSame(['Item2', 'Item10', 'item1'], $result->toArray());
    }

    /**
     * Test orderByNatural case insensitive
     */
    public function testOrderByNaturalCaseInsensitive(): void
    {
        $collection = ALinqCollection::from(['ITEM10', 'item2', 'Item1']);
        $result = $collection->orderByNatural(false);

        $this->assertSame(['Item1', 'item2', 'ITEM10'], $result->toArray());
    }

    /**
     * Test orderByNatural preserves keys
     */
    public function testOrderByNaturalPreservesKeys(): void
    {
        $collection = ALinqCollection::from([
            'a' => 'item10',
            'b' => 'item2',
            'c' => 'item1'
        ]);
        $result = $collection->orderByNatural();

        $keys = array_keys($result->toArray());
        $this->assertContains('a', $keys);
        $this->assertContains('b', $keys);
        $this->assertContains('c', $keys);
    }

    // ===== ORDER BY CUSTOM TESTS =====

    /**
     * Test orderByCustom uses custom comparison function
     */
    public function testOrderByCustomUsesCustomComparisonFunction(): void
    {
        $collection = ALinqCollection::from([5, 2, 8, 1, 9, 3]);

        $result = $collection->orderByCustom(fn($a, $b) => $b <=> $a);

        $this->assertEquals([9, 8, 5, 3, 2, 1], $result->toArray());
    }

    /**
     * Test orderByCustom with complex comparison
     */
    public function testOrderByCustomWithComplexComparison(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Alice', 'age' => 30, 'priority' => 2],
            ['name' => 'Bob', 'age' => 25, 'priority' => 1],
            ['name' => 'Charlie', 'age' => 30, 'priority' => 1]
        ]);

        $result = $collection->orderByCustom(function($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            return $a['age'] <=> $b['age'];
        });

        $resultArray = $result->toArray();
        $this->assertEquals('Bob', $resultArray[0]['name']);
        $this->assertEquals('Charlie', $resultArray[1]['name']);
        $this->assertEquals('Alice', $resultArray[2]['name']);
    }

    /**
     * Test orderByCustom with string length comparison
     */
    public function testOrderByCustomWithStringLength(): void
    {
        $collection = ALinqCollection::from(['aaa', 'b', 'cc', 'dddd']);

        $result = $collection->orderByCustom(fn($a, $b) => strlen($a) <=> strlen($b));

        $this->assertEquals(['b', 'cc', 'aaa', 'dddd'], $result->toArray());
    }

    // ===== ORDER BY KEY TESTS =====

    /**
     * Test orderByKey sorts by keys in ascending order
     */
    public function testOrderByKeySortsByKeysAscending(): void
    {
        $collection = ALinqCollection::from([
            'c' => 3,
            'a' => 1,
            'b' => 2
        ]);

        $result = $collection->orderByKey();

        $expected = ['a' => 1, 'b' => 2, 'c' => 3];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test orderByKey descending
     */
    public function testOrderByKeyDescending(): void
    {
        $collection = ALinqCollection::from([
            'a' => 1,
            'b' => 2,
            'c' => 3
        ]);

        $result = $collection->orderByKey(true);

        $expected = ['c' => 3, 'b' => 2, 'a' => 1];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test orderByKey with numeric keys
     */
    public function testOrderByKeyWithNumericKeys(): void
    {
        $collection = ALinqCollection::from([
            30 => 'c',
            10 => 'a',
            20 => 'b'
        ]);

        $result = $collection->orderByKey();

        $expected = [10 => 'a', 20 => 'b', 30 => 'c'];
        $this->assertEquals($expected, $result->toArray());
    }

    // ===== REVERSE TESTS =====

    /**
     * Test reverse reverses element order
     */
    public function testReverseReversesElementOrder(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->reverse();

        $this->assertEquals([5, 4, 3, 2, 1], $result->toArray());
    }

    /**
     * Test reverse preserves keys
     */
    public function testReversePreservesKeys(): void
    {
        $collection = ALinqCollection::from([
            'a' => 1,
            'b' => 2,
            'c' => 3
        ]);

        $result = $collection->reverse();

        $expected = ['c' => 3, 'b' => 2, 'a' => 1];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test reverse on empty collection
     */
    public function testReverseOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();
        $result = $collection->reverse();

        $this->assertEmpty($result->toArray());
    }

    /**
     * Test reverse twice returns original order
     */
    public function testReverseTwiceReturnsOriginalOrder(): void
    {
        $original = [1, 2, 3, 4, 5];
        $collection = ALinqCollection::from($original);

        $result = $collection->reverse()->reverse();

        $this->assertEquals($original, $result->toArray());
    }

    /**
     * Test reverse with single element
     */
    public function testReverseWithSingleElement(): void
    {
        $collection = ALinqCollection::from([42]);
        $result = $collection->reverse();

        $this->assertEquals([42], $result->toArray());
    }

    /**
     * Test fluent chaining with ordering operations
     */
    public function testFluentChainingWithOrderingOperations(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Charlie', 'age' => 25],
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 20],
            ['name' => 'David', 'age' => 35]
        ]);

        $result = $collection
            ->where(fn($item) => $item['age'] >= 25)
            ->orderBy(fn($item) => $item['age'])
            ->select(fn($item) => $item['name']);

        $expected = ['Charlie', 'Alice', 'David'];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test combining ascending and descending sorts
     */
    public function testCombiningAscendingAndDescendingSorts(): void
    {
        $collection = ALinqCollection::from([5, 2, 8, 1, 9, 3]);

        $ascending = $collection->orderBy(fn($x) => $x);
        $descending = $ascending->reverse();

        $this->assertEquals([1, 2, 3, 5, 8, 9], $ascending->toArray());
        $this->assertEquals([9, 8, 5, 3, 2, 1], $descending->toArray());
    }

    /**
     * Test ordering with null values
     */
    public function testOrderingWithNullValues(): void
    {
        $collection = ALinqCollection::from([
            ['value' => 3],
            ['value' => null],
            ['value' => 1],
            ['value' => 2]
        ]);

        $result = $collection->orderBy(fn($item) => $item['value']);

        $this->assertCount(4, $result->toArray());
    }

    /**
     * Test ordering returns new collection instance
     */
    public function testOrderingReturnsNewCollectionInstance(): void
    {
        $original = ALinqCollection::from([3, 1, 2]);
        $sorted = $original->orderBy(fn($x) => $x);

        $this->assertNotSame($original, $sorted);
        $this->assertEquals([3, 1, 2], $original->toArray());
        $this->assertEquals([1, 2, 3], $sorted->toArray());
    }


    /**
     * Review 2026-10-08, 1.15 / decision 4a: the key selector runs once per item (not twice
     * per comparison) and may receive the item key.
     */
    public function testOrderByCallsTheSelectorOncePerItem(): void
    {
        $calls = 0;
        $sorted = ALinqCollection::from([5, 3, 9, 1, 7, 2, 8, 4, 6])->orderBy(function ($v) use (&$calls) {
            $calls++;
            return $v;
        })->toArray();

        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8, 9], $sorted);
        $this->assertSame(9, $calls);

        $this->assertSame(['c' => 3, 'b' => 2, 'a' => 1], ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3])->orderByDescending(fn($v, $k) => $k)->toArray());
        $this->assertSame(['b', 'a'], ALinqCollection::from(['a', 'b'])->orderBy('strrev')->reverse()->toArray());
    }

    // ===== thenBy() / thenByDescending() (1.4.0, forward 021, RN-06, D4 a) =====

    private static function staff(): array
    {
        return [
            ['name' => 'Ana', 'dept' => 'IT', 'salary' => 5000],
            ['name' => 'Bia', 'dept' => 'HR', 'salary' => 4000],
            ['name' => 'Caio', 'dept' => 'IT', 'salary' => 7000],
            ['name' => 'Dani', 'dept' => 'HR', 'salary' => 4000],
            ['name' => 'Edu', 'dept' => 'IT', 'salary' => 5000],
            ['name' => 'Fabi', 'dept' => 'HR', 'salary' => 6000],
        ];
    }

    public function testOrderByThenByDescendingOrdersByDeptThenSalaryStableOnBothSides(): void
    {
        $expected = ['Fabi', 'Bia', 'Dani', 'Caio', 'Ana', 'Edu'];

        foreach ([ALinqCollection::from(self::staff()), ALinqLazyCollection::from(self::staff())] as $side) {
            $names = $side->orderBy(fn($e) => $e['dept'])
                ->thenByDescending(fn($e) => $e['salary'])
                ->select(fn($e) => $e['name'])
                ->toArray();
            $this->assertSame($expected, $names);
        }
    }

    public function testThenByChainsAnyNumberOfCriteria(): void
    {
        $names = ALinqCollection::from(self::staff())
            ->orderByDescending(fn($e) => $e['salary'])
            ->thenBy(fn($e) => $e['dept'])
            ->thenByDescending(fn($e) => $e['name'])
            ->select(fn($e) => $e['name'])
            ->toArray();

        $this->assertSame(['Caio', 'Fabi', 'Edu', 'Ana', 'Dani', 'Bia'], $names);
    }

    public function testThenByAcceptsAKeyComparer(): void
    {
        $byLength = fn(string $a, string $b): int => strlen($a) <=> strlen($b);
        $names = ALinqCollection::from(self::staff())
            ->orderBy(fn($e) => $e['dept'])
            ->thenBy(fn($e) => $e['name'], $byLength)
            ->select(fn($e) => $e['name'])
            ->toArray();

        $this->assertSame(['Bia', 'Dani', 'Fabi', 'Ana', 'Edu', 'Caio'], $names);
    }

    public function testThenByWithoutAPrecedingOrderByThrowsLogicException(): void
    {
        $collection = ALinqCollection::from(self::staff());
        $calls = [
            'fresh collection' => fn() => $collection->thenBy(fn($e) => $e['name']),
            'after where()' => fn() => $collection->orderBy(fn($e) => $e['dept'])->where(fn() => true)->thenBy(fn($e) => $e['name']),
            'after orderByCustom()' => fn() => $collection->orderByCustom(fn($a, $b) => $a['dept'] <=> $b['dept'])->thenBy(fn($e) => $e['name']),
            'after reverse()' => fn() => $collection->orderBy(fn($e) => $e['dept'])->reverse()->thenBy(fn($e) => $e['name']),
        ];
        foreach ($calls as $case => $call) {
            try {
                $call();
                $this->fail("$case: expected LogicException");
            } catch (\LogicException $e) {
                $this->assertSame('thenBy() requires a preceding orderBy().', $e->getMessage(), $case);
            }
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('thenByDescending() requires a preceding orderBy().');
        $collection->thenByDescending(fn($e) => $e['name']);
    }

    public function testThenByLeavesThePrecedingOrderingUntouched(): void
    {
        $byDept = ALinqCollection::from(self::staff())->orderBy(fn($e) => $e['dept']);
        $before = $byDept->toArray();

        $up = $byDept->thenBy(fn($e) => $e['salary'])->select(fn($e) => $e['name'])->toArray();
        $down = $byDept->thenByDescending(fn($e) => $e['salary'])->select(fn($e) => $e['name'])->toArray();

        $this->assertSame($before, $byDept->toArray());
        $this->assertSame(['Bia', 'Dani', 'Fabi', 'Ana', 'Edu', 'Caio'], $up);
        $this->assertSame(['Fabi', 'Bia', 'Dani', 'Caio', 'Ana', 'Edu'], $down);
    }

    public function testThenByFollowsTheKeyRuleAndSeesTheSourceKeys(): void
    {
        $dictionary = ['k1' => 3, 'k2' => 1, 'k3' => 2, 'k4' => 4];
        $this->assertSame(['k4' => 4, 'k3' => 2, 'k1' => 3, 'k2' => 1], ALinqCollection::from($dictionary)->orderBy(fn($v) => $v % 2)->thenByDescending(fn($v) => $v)->toArray());

        // a list is reindexed, but the two-parameter selector of thenBy sees the source key
        $seen = [];
        $sorted = ALinqCollection::from(['c', 'a', 'b'])->orderBy(fn($v) => 0)->thenByDescending(function ($v, $k) use (&$seen) {
            $seen[] = $k;
            return $k;
        });
        $this->assertSame([0, 1, 2], $seen);
        $this->assertSame(['b', 'a', 'c'], $sorted->toArray());
    }

    public function testEveryKeySelectorRunsOncePerItem(): void
    {
        $calls = ['first' => 0, 'second' => 0, 'third' => 0];
        ALinqCollection::from(self::staff())
            ->orderBy(function ($e) use (&$calls) { $calls['first']++; return $e['dept']; })
            ->thenBy(function ($e) use (&$calls) { $calls['second']++; return $e['salary']; })
            ->thenBy(function ($e) use (&$calls) { $calls['third']++; return $e['name']; });

        $this->assertSame(['first' => 6, 'second' => 6, 'third' => 6], $calls);
    }

    public function testOrderByResultsAreUnchangedByThePendingOrdering(): void
    {
        $this->assertSame([1, 2, 3], ALinqCollection::from([3, 1, 2])->orderBy(fn($v) => $v)->toArray());
        $this->assertSame(['b' => 2, 'a' => 1], ALinqCollection::from(['a' => 1, 'b' => 2])->orderByDescending(fn($v) => $v)->toArray());
        $this->assertSame([], ALinqCollection::from([])->orderBy(fn($v) => $v)->thenBy(fn($v) => $v)->toArray());
    }
}
