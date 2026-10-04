<?php

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Grouping Operations
 * Tests groupBy method in GroupingOperations trait
 */
class GroupingOperationsTest extends TestCase
{
    // ===== GROUP BY TESTS =====

    /**
     * Test groupBy groups elements by key selector
     */
    public function testGroupByGroupsElementsByKeySelector(): void
    {
        $collection = ALinqCollection::from([
            ['category' => 'A', 'value' => 1],
            ['category' => 'B', 'value' => 2],
            ['category' => 'A', 'value' => 3],
            ['category' => 'C', 'value' => 4],
            ['category' => 'B', 'value' => 5]
        ]);

        $result = $collection->groupBy(fn($item) => $item['category']);

        $resultArray = $result->toArray();

        $this->assertArrayHasKey('A', $resultArray);
        $this->assertArrayHasKey('B', $resultArray);
        $this->assertArrayHasKey('C', $resultArray);
        $this->assertCount(2, $resultArray['A']);
        $this->assertCount(2, $resultArray['B']);
        $this->assertCount(1, $resultArray['C']);
    }

    /**
     * Test groupBy with numeric keys
     */
    public function testGroupByWithNumericKeys(): void
    {
        $collection = ALinqCollection::from([
            ['priority' => 1, 'task' => 'Task1'],
            ['priority' => 2, 'task' => 'Task2'],
            ['priority' => 1, 'task' => 'Task3'],
            ['priority' => 3, 'task' => 'Task4']
        ]);

        $result = $collection->groupBy(fn($item) => $item['priority']);

        $resultArray = $result->toArray();

        $this->assertCount(2, $resultArray[1]);
        $this->assertCount(1, $resultArray[2]);
        $this->assertCount(1, $resultArray[3]);
    }

    /**
     * Test groupBy on empty collection
     */
    public function testGroupByOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();
        $result = $collection->groupBy(fn($item) => $item['key']);

        $this->assertEmpty($result->toArray());
    }

    /**
     * Test groupBy with all elements in same group
     */
    public function testGroupByWithAllElementsInSameGroup(): void
    {
        $collection = ALinqCollection::from([
            ['type' => 'A', 'value' => 1],
            ['type' => 'A', 'value' => 2],
            ['type' => 'A', 'value' => 3]
        ]);

        $result = $collection->groupBy(fn($item) => $item['type']);

        $resultArray = $result->toArray();

        $this->assertCount(1, $resultArray);
        $this->assertCount(3, $resultArray['A']);
    }

    /**
     * Test groupBy with all elements in different groups
     */
    public function testGroupByWithAllElementsInDifferentGroups(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3]
        ]);

        $result = $collection->groupBy(fn($item) => $item['id']);

        $resultArray = $result->toArray();

        $this->assertCount(3, $resultArray);
        $this->assertCount(1, $resultArray[1]);
        $this->assertCount(1, $resultArray[2]);
        $this->assertCount(1, $resultArray[3]);
    }

    /**
     * Test groupBy preserves order within groups
     */
    public function testGroupByPreservesOrderWithinGroups(): void
    {
        $collection = ALinqCollection::from([
            ['type' => 'A', 'seq' => 1],
            ['type' => 'B', 'seq' => 2],
            ['type' => 'A', 'seq' => 3],
            ['type' => 'A', 'seq' => 4]
        ]);

        $result = $collection->groupBy(fn($item) => $item['type']);

        $resultArray = $result->toArray();

        $this->assertEquals(1, $resultArray['A'][0]['seq']);
        $this->assertEquals(3, $resultArray['A'][1]['seq']);
        $this->assertEquals(4, $resultArray['A'][2]['seq']);
    }

    /**
     * Test groupBy with string keys
     */
    public function testGroupByWithStringKeys(): void
    {
        $collection = ALinqCollection::from([
            ['status' => 'pending', 'item' => 'Item1'],
            ['status' => 'completed', 'item' => 'Item2'],
            ['status' => 'pending', 'item' => 'Item3'],
            ['status' => 'failed', 'item' => 'Item4']
        ]);

        $result = $collection->groupBy(fn($item) => $item['status']);

        $resultArray = $result->toArray();

        $this->assertArrayHasKey('pending', $resultArray);
        $this->assertArrayHasKey('completed', $resultArray);
        $this->assertArrayHasKey('failed', $resultArray);
        $this->assertCount(2, $resultArray['pending']);
    }

    /**
     * Test groupBy with boolean keys
     */
    public function testGroupByWithBooleanKeys(): void
    {
        $collection = ALinqCollection::from([
            ['active' => true, 'name' => 'User1'],
            ['active' => false, 'name' => 'User2'],
            ['active' => true, 'name' => 'User3'],
            ['active' => false, 'name' => 'User4']
        ]);

        $result = $collection->groupBy(fn($item) => $item['active']);

        $resultArray = $result->toArray();

        $this->assertArrayHasKey(1, $resultArray);
        $this->assertArrayHasKey(0, $resultArray);
        $this->assertCount(2, $resultArray[1]);
        $this->assertCount(2, $resultArray[0]);
    }

    /**
     * Test groupBy with computed key
     */
    public function testGroupByWithComputedKey(): void
    {
        $collection = ALinqCollection::from([
            ['value' => 10],
            ['value' => 15],
            ['value' => 20],
            ['value' => 25],
            ['value' => 30]
        ]);

        $result = $collection->groupBy(fn($item) => floor($item['value'] / 10));

        $resultArray = $result->toArray();

        $this->assertArrayHasKey(1, $resultArray);
        $this->assertArrayHasKey(2, $resultArray);
        $this->assertArrayHasKey(3, $resultArray);
    }

    /**
     * Test groupBy with object property access
     */
    public function testGroupByWithObjectPropertyAccess(): void
    {
        $obj1 = (object)['category' => 'A', 'value' => 1];
        $obj2 = (object)['category' => 'B', 'value' => 2];
        $obj3 = (object)['category' => 'A', 'value' => 3];

        $collection = ALinqCollection::from([$obj1, $obj2, $obj3]);
        $result = $collection->groupBy(fn($item) => $item->category);

        $resultArray = $result->toArray();

        $this->assertCount(2, $resultArray['A']);
        $this->assertCount(1, $resultArray['B']);
    }

    /**
     * Test groupBy combined with aggregation
     */
    public function testGroupByCombinedWithAggregation(): void
    {
        $collection = ALinqCollection::from([
            ['department' => 'Sales', 'amount' => 100],
            ['department' => 'IT', 'amount' => 150],
            ['department' => 'Sales', 'amount' => 200],
            ['department' => 'IT', 'amount' => 250]
        ]);

        $grouped = $collection->groupBy(fn($item) => $item['department']);

        $groupedArray = $grouped->toArray();

        $salesTotal = array_sum(array_column($groupedArray['Sales'], 'amount'));
        $itTotal = array_sum(array_column($groupedArray['IT'], 'amount'));

        $this->assertEquals(300, $salesTotal);
        $this->assertEquals(400, $itTotal);
    }

    /**
     * Test groupBy with fluent chaining
     */
    public function testGroupByWithFluentChaining(): void
    {
        $collection = ALinqCollection::from([
            ['type' => 'A', 'value' => 10],
            ['type' => 'B', 'value' => 20],
            ['type' => 'A', 'value' => 30],
            ['type' => 'B', 'value' => 40],
            ['type' => 'C', 'value' => 50]
        ]);

        $result = $collection
            ->where(fn($item) => $item['value'] >= 20)
            ->groupBy(fn($item) => $item['type']);

        $resultArray = $result->toArray();

        // Após filtrar value >= 20, temos: B(20), A(30), B(40), C(50)
        $this->assertArrayHasKey('A', $resultArray);
        $this->assertArrayHasKey('B', $resultArray);
        $this->assertArrayHasKey('C', $resultArray);
        $this->assertCount(1, $resultArray['A']); // Apenas A com value=30
        $this->assertCount(2, $resultArray['B']); // B com value=20 e value=40
    }

    /**
     * Test groupBy returns ALinqCollection instance
     */
    public function testGroupByReturnsALinqCollectionInstance(): void
    {
        $collection = ALinqCollection::from([
            ['key' => 1],
            ['key' => 2]
        ]);

        $result = $collection->groupBy(fn($item) => $item['key']);

        $this->assertInstanceOf(ALinqCollection::class, $result);
    }

    /**
     * Test groupBy with mixed type keys
     */
    public function testGroupByWithMixedTypeKeys(): void
    {
        $collection = ALinqCollection::from([
            ['id' => '1', 'name' => 'First'],
            ['id' => 1, 'name' => 'Second'],
            ['id' => '2', 'name' => 'Third']
        ]);

        $result = $collection->groupBy(fn($item) => $item['id']);

        $resultArray = $result->toArray();

        $this->assertArrayHasKey('1', $resultArray);
        $this->assertArrayHasKey(1, $resultArray);
        $this->assertArrayHasKey('2', $resultArray);
    }

    /**
     * Test groupBy with null keys
     */
    public function testGroupByWithNullKeys(): void
    {
        $collection = ALinqCollection::from([
            ['category' => null, 'value' => 1],
            ['category' => 'A', 'value' => 2],
            ['category' => null, 'value' => 3]
        ]);

        $result = $collection->groupBy(fn($item) => $item['category']);

        $resultArray = $result->toArray();

        $this->assertArrayHasKey('', $resultArray);
        $this->assertCount(2, $resultArray['']);
    }
}
