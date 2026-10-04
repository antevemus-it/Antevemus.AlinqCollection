<?php

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Joining Operations
 * Tests all 13 joining methods in JoiningOperations trait
 */
class JoiningOperationsTest extends TestCase
{
    // ===== JOIN TESTS =====

    /**
     * Test join combines two collections
     */
    public function testJoinCombinesTwoCollections(): void
    {
        $outer = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob']
        ]);

        $inner = [
            ['userId' => 1, 'order' => 'Order1'],
            ['userId' => 2, 'order' => 'Order2']
        ];

        $result = $outer->join(
            $inner,
            fn($o) => $o['id'],
            fn($i) => $i['userId'],
            fn($o, $i) => ['name' => $o['name'], 'order' => $i['order']]
        );

        $expected = [
            ['name' => 'Alice', 'order' => 'Order1'],
            ['name' => 'Bob', 'order' => 'Order2']
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test join with multiple matches
     */
    public function testJoinWithMultipleMatches(): void
    {
        $outer = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice']
        ]);

        $inner = [
            ['userId' => 1, 'order' => 'Order1'],
            ['userId' => 1, 'order' => 'Order2']
        ];

        $result = $outer->join(
            $inner,
            fn($o) => $o['id'],
            fn($i) => $i['userId'],
            fn($o, $i) => $i['order']
        );

        $this->assertEquals(['Order1', 'Order2'], $result->toArray());
    }

    /**
     * Test join with no matches
     */
    public function testJoinWithNoMatches(): void
    {
        $outer = ALinqCollection::from([['id' => 1]]);
        $inner = [['userId' => 2]];

        $result = $outer->join(
            $inner,
            fn($o) => $o['id'],
            fn($i) => $i['userId'],
            fn($o, $i) => $o
        );

        $this->assertEmpty($result->toArray());
    }

    // ===== GROUP JOIN TESTS =====

    /**
     * Test groupJoin creates left join with grouped results
     */
    public function testGroupJoinCreatesLeftJoin(): void
    {
        $outer = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob']
        ]);

        $inner = [
            ['userId' => 1, 'order' => 'Order1'],
            ['userId' => 1, 'order' => 'Order2'],
            ['userId' => 2, 'order' => 'Order3']
        ];

        $result = $outer->groupJoin(
            $inner,
            fn($o) => $o['id'],
            fn($i) => $i['userId'],
            fn($o, $orders) => ['name' => $o['name'], 'orderCount' => $orders->count()]
        );

        $expected = [
            ['name' => 'Alice', 'orderCount' => 2],
            ['name' => 'Bob', 'orderCount' => 1]
        ];

        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test groupJoin with no matches returns empty collection
     */
    public function testGroupJoinWithNoMatchesReturnsEmptyCollection(): void
    {
        $outer = ALinqCollection::from([['id' => 1, 'name' => 'Alice']]);
        $inner = [['userId' => 2, 'order' => 'Order1']];

        $result = $outer->groupJoin(
            $inner,
            fn($o) => $o['id'],
            fn($i) => $i['userId'],
            fn($o, $orders) => ['name' => $o['name'], 'orderCount' => $orders->count()]
        );

        $expected = [['name' => 'Alice', 'orderCount' => 0]];
        $this->assertEquals($expected, $result->toArray());
    }

    // ===== CONCAT TESTS =====

    /**
     * Test concat merges two collections
     */
    public function testConcatMergesTwoCollections(): void
    {
        $first = ALinqCollection::from([1, 2, 3]);
        $second = [4, 5, 6];

        $result = $first->concat($second);

        $this->assertEquals([1, 2, 3, 4, 5, 6], $result->toArray());
    }

    /**
     * Test concat preserves duplicates
     */
    public function testConcatPreservesDuplicates(): void
    {
        $first = ALinqCollection::from([1, 2, 3]);
        $second = [2, 3, 4];

        $result = $first->concat($second);

        $this->assertEquals([1, 2, 3, 2, 3, 4], $result->toArray());
    }

    /**
     * Test concat with empty collection
     */
    public function testConcatWithEmptyCollection(): void
    {
        $first = ALinqCollection::from([1, 2, 3]);
        $result = $first->concat([]);

        $this->assertEquals([1, 2, 3], $result->toArray());
    }

    // ===== INTERSECT TESTS =====

    /**
     * Test intersect returns common elements
     */
    public function testIntersectReturnsCommonElements(): void
    {
        $first = ALinqCollection::from([1, 2, 3, 4, 5]);
        $second = [3, 4, 5, 6, 7];

        $result = $first->intersect($second);

        $this->assertEquals([3, 4, 5], $result->toArray());
    }

    /**
     * Test intersect with no common elements
     */
    public function testIntersectWithNoCommonElements(): void
    {
        $first = ALinqCollection::from([1, 2, 3]);
        $second = [4, 5, 6];

        $result = $first->intersect($second);

        $this->assertEmpty($result->toArray());
    }

    /**
     * Test intersect removes duplicates
     */
    public function testIntersectRemovesDuplicates(): void
    {
        $first = ALinqCollection::from([1, 1, 2, 2, 3]);
        $second = [1, 2, 3];

        $result = $first->intersect($second);

        $this->assertEquals([1, 2, 3], $result->toArray());
    }

    // ===== EXCEPT TESTS =====

    /**
     * Test except returns elements not in second collection
     */
    public function testExceptReturnsElementsNotInSecond(): void
    {
        $first = ALinqCollection::from([1, 2, 3, 4, 5]);
        $second = [3, 4, 5];

        $result = $first->except($second);

        $this->assertEquals([1, 2], $result->toArray());
    }

    /**
     * Test except with no overlapping elements
     */
    public function testExceptWithNoOverlappingElements(): void
    {
        $first = ALinqCollection::from([1, 2, 3]);
        $second = [4, 5, 6];

        $result = $first->except($second);

        $this->assertEquals([1, 2, 3], $result->toArray());
    }

    /**
     * Test except with all elements overlapping
     */
    public function testExceptWithAllElementsOverlapping(): void
    {
        $first = ALinqCollection::from([1, 2, 3]);
        $second = [1, 2, 3];

        $result = $first->except($second);

        $this->assertEmpty($result->toArray());
    }

    // ===== INTERSECT WITH TESTS =====

    /**
     * Test intersectWith with custom comparer
     */
    public function testIntersectWithCustomComparer(): void
    {
        $first = ALinqCollection::from([
            ['id' => 1, 'value' => 'A'],
            ['id' => 2, 'value' => 'B'],
            ['id' => 3, 'value' => 'C']
        ]);

        $second = [
            ['id' => 2, 'value' => 'X'],
            ['id' => 3, 'value' => 'Y']
        ];

        $comparer = fn($a, $b) => $a['id'] <=> $b['id'];
        $result = $first->intersectWith($second, $comparer);

        $this->assertCount(2, $result->toArray());
    }

    // ===== EXCEPT WITH TESTS =====

    /**
     * Test exceptWith with custom comparer
     */
    public function testExceptWithCustomComparer(): void
    {
        $first = ALinqCollection::from([
            ['id' => 1, 'value' => 'A'],
            ['id' => 2, 'value' => 'B'],
            ['id' => 3, 'value' => 'C']
        ]);

        $second = [
            ['id' => 2, 'value' => 'X']
        ];

        $comparer = fn($a, $b) => $a['id'] <=> $b['id'];
        $result = $first->exceptWith($second, $comparer);

        $this->assertCount(2, $result->toArray());
    }

    // ===== COMBINE TESTS =====

    /**
     * Test combine creates associative array from keys and values
     */
    public function testCombineCreatesAssociativeArray(): void
    {
        $keys = ALinqCollection::from(['a', 'b', 'c']);
        $values = [1, 2, 3];

        $result = $keys->combine($values);

        $expected = ['a' => 1, 'b' => 2, 'c' => 3];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test combine with numeric keys
     */
    public function testCombineWithNumericKeys(): void
    {
        $keys = ALinqCollection::from([10, 20, 30]);
        $values = ['value1', 'value2', 'value3'];

        $result = $keys->combine($values);

        $expected = [10 => 'value1', 20 => 'value2', 30 => 'value3'];
        $this->assertEquals($expected, $result->toArray());
    }

    // ===== REPLACE TESTS =====

    /**
     * Test replace replaces values by key
     */
    public function testReplaceReplacesValuesByKey(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);
        $replacements = ['b' => 20, 'c' => 30];

        $result = $collection->replace($replacements);

        $expected = ['a' => 1, 'b' => 20, 'c' => 30];
        $this->assertEquals($expected, $result->toArray());
    }

    /**
     * Test replace adds new keys
     */
    public function testReplaceAddsNewKeys(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2]);
        $replacements = ['c' => 3];

        $result = $collection->replace($replacements);

        $expected = ['a' => 1, 'b' => 2, 'c' => 3];
        $this->assertEquals($expected, $result->toArray());
    }

    // ===== REPLACE RECURSIVE TESTS =====

    /**
     * Test replaceRecursive replaces nested values
     */
    public function testReplaceRecursiveReplacesNestedValues(): void
    {
        $collection = ALinqCollection::from([
            'user' => ['name' => 'Alice', 'age' => 25],
            'status' => 'active'
        ]);

        $replacements = [
            'user' => ['age' => 26]
        ];

        $result = $collection->replaceRecursive($replacements);

        $expected = [
            'user' => ['name' => 'Alice', 'age' => 26],
            'status' => 'active'
        ];
        $this->assertEquals($expected, $result->toArray());
    }

    // ===== EXCEPT BY TESTS =====

    /**
     * Test exceptBy with key selector
     */
    public function testExceptByWithKeySelector(): void
    {
        $first = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie']
        ]);

        $second = [
            ['id' => 2, 'name' => 'Different'],
            ['id' => 4, 'name' => 'David']
        ];

        $result = $first->exceptBy($second, fn($item) => $item['id']);

        $this->assertCount(2, $result->toArray());
        $this->assertEquals('Alice', $result->toArray()[0]['name']);
        $this->assertEquals('Charlie', $result->toArray()[1]['name']);
    }

    /**
     * Test exceptBy with object keys
     */
    public function testExceptByWithObjectKeys(): void
    {
        $obj1 = (object)['id' => 1];
        $obj2 = (object)['id' => 2];
        $obj3 = (object)['id' => 3];

        $first = ALinqCollection::from([
            ['ref' => $obj1],
            ['ref' => $obj2],
            ['ref' => $obj3]
        ]);

        $second = [['ref' => $obj2]];

        $result = $first->exceptBy($second, fn($item) => $item['ref']);

        $this->assertCount(2, $result->toArray());
    }

    // ===== INTERSECT BY TESTS =====

    /**
     * Test intersectBy with key selector
     */
    public function testIntersectByWithKeySelector(): void
    {
        $first = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie']
        ]);

        $second = [
            ['id' => 2, 'name' => 'Different'],
            ['id' => 3, 'name' => 'AlsoDifferent']
        ];

        $result = $first->intersectBy($second, fn($item) => $item['id']);

        $this->assertCount(2, $result->toArray());
        $this->assertEquals('Bob', $result->toArray()[0]['name']);
        $this->assertEquals('Charlie', $result->toArray()[1]['name']);
    }

    /**
     * Test intersectBy with no common keys
     */
    public function testIntersectByWithNoCommonKeys(): void
    {
        $first = ALinqCollection::from([
            ['id' => 1],
            ['id' => 2]
        ]);

        $second = [
            ['id' => 3],
            ['id' => 4]
        ];

        $result = $first->intersectBy($second, fn($item) => $item['id']);

        $this->assertEmpty($result->toArray());
    }

    // ===== UNION BY TESTS =====

    /**
     * Test unionBy combines unique elements based on key selector
     */
    public function testUnionByCombinesUniqueElements(): void
    {
        $first = ALinqCollection::from([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob']
        ]);

        $second = [
            ['id' => 2, 'name' => 'BobDuplicate'],
            ['id' => 3, 'name' => 'Charlie']
        ];

        $result = $first->unionBy($second, fn($item) => $item['id']);

        $this->assertCount(3, $result->toArray());
        $resultArray = $result->toArray();
        $this->assertEquals('Alice', $resultArray[0]['name']);
        $this->assertEquals('Bob', $resultArray[1]['name']);
        $this->assertEquals('Charlie', $resultArray[2]['name']);
    }

    /**
     * Test unionBy removes duplicates from both collections
     */
    public function testUnionByRemovesDuplicatesFromBothCollections(): void
    {
        $first = ALinqCollection::from([
            ['id' => 1],
            ['id' => 1],
            ['id' => 2]
        ]);

        $second = [
            ['id' => 2],
            ['id' => 3]
        ];

        $result = $first->unionBy($second, fn($item) => $item['id']);

        $this->assertCount(3, $result->toArray());
    }

    /**
     * Test fluent API chaining with joining operations
     */
    public function testFluentApiChainingWithJoiningOperations(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);

        $result = $collection
            ->concat([4, 5])
            ->except([2, 4])
            ->intersect([1, 3, 5]);

        $this->assertEquals([1, 3, 5], $result->toArray());
    }
}
