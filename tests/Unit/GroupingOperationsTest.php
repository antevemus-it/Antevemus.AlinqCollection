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

        // Groups are ALinqCollection instances since 1.2.0 (review 2026-10-08, 2.1)
        $this->assertEquals(1, $resultArray['A']->toArray()[0]['seq']);
        $this->assertEquals(3, $resultArray['A']->toArray()[1]['seq']);
        $this->assertEquals(4, $resultArray['A']->toArray()[2]['seq']);
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
     * RN-07 (forward 015): a group key is an int, a string or a BackedEnum; bool, null, float,
     * arrays and other objects throw with the type and the item key. Before 1.3.0 PHP coerced
     * them in silence (true -> 1, null -> '', 1.5 -> 1 with E_DEPRECATED) or threw a raw
     * TypeError (review 2026-10-08, 2.10). The bool case of this test now expects the
     * exception; a caller that wants two groups maps the flag to a name or an int.
     */
    public function testGroupByRejectsKeysThatAreNotIntStringOrBackedEnum(): void
    {
        $collection = ALinqCollection::from([
            ['active' => true, 'name' => 'User1'],
            ['active' => false, 'name' => 'User2'],
        ]);

        $cases = [
            'bool' => fn($item) => $item['active'],
            'null' => fn($item) => null,
            'float' => fn($item) => 1.5,
            'array' => fn($item) => [1],
            'stdClass' => fn($item) => new \stdClass(),
        ];
        foreach ($cases as $type => $selector) {
            try {
                $collection->groupBy($selector);
                $this->fail("groupBy() with a $type key must throw");
            } catch (\InvalidArgumentException $e) {
                $this->assertSame(
                    "groupBy() expects the key selector to return an int, a string or a BackedEnum, $type returned for item at key 0",
                    $e->getMessage()
                );
            }
        }

        $byName = $collection->groupBy(fn($item) => $item['active'] ? 'active' : 'inactive')->toArray();
        $this->assertSame(['active', 'inactive'], array_keys($byName));
        $this->assertCount(1, $byName['active']);

        // countBy() and aggregateBy() go through the same validation
        $this->expectException(\InvalidArgumentException::class);
        $collection->countBy(fn($item) => $item['active']);
    }

    /**
     * RN-07: a BackedEnum key is grouped under its backing value.
     */
    public function testGroupByAcceptsBackedEnumKeys(): void
    {
        $collection = ALinqCollection::from([
            ['status' => GroupingStatus::Active, 'id' => 1],
            ['status' => GroupingStatus::Blocked, 'id' => 2],
            ['status' => GroupingStatus::Active, 'id' => 3],
        ]);

        $groups = $collection->groupBy(fn($item) => $item['status'])->toArray();

        $this->assertSame(['active', 'blocked'], array_keys($groups));
        $this->assertSame([1, 3], $groups['active']->column('id')->toArray());
        $this->assertSame(['active' => 2, 'blocked' => 1], $collection->countBy(fn($item) => $item['status'])->toArray());
    }

    /**
     * Test groupBy with computed key (cast to int since 1.3.0: a float key throws, RN-07)
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

        $result = $collection->groupBy(fn($item) => (int) floor($item['value'] / 10));

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

        // Groups are ALinqCollection instances since 1.2.0 (review 2026-10-08, 2.1)
        $salesTotal = $groupedArray['Sales']->sum(fn($item) => $item['amount']);
        $itTotal = $groupedArray['IT']->sum(fn($item) => $item['amount']);

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
     * RN-07: a null key throws (before 1.3.0 it was silently grouped under ''). The caller
     * names the group explicitly.
     */
    public function testGroupByWithNullKeys(): void
    {
        $collection = ALinqCollection::from([
            ['category' => null, 'value' => 1],
            ['category' => 'A', 'value' => 2],
            ['category' => null, 'value' => 3]
        ]);

        $result = $collection->groupBy(fn($item) => $item['category'] ?? 'uncategorized')->toArray();

        $this->assertSame(['uncategorized', 'A'], array_keys($result));
        $this->assertCount(2, $result['uncategorized']);

        $this->expectException(\InvalidArgumentException::class);
        $collection->groupBy(fn($item) => $item['category']);
    }


    /**
     * Review 2026-10-08, 2.1 (README Quick Start and §6): groupBy() returns ALinqCollection
     * groups and select() hands the group key to a two-parameter selector.
     */
    public function testGroupByReturnsCollectionsAndSelectReceivesTheKey(): void
    {
        $users = ALinqCollection::from([
            ['name' => 'Alice', 'role' => 'admin', 'score' => 95],
            ['name' => 'Bob', 'role' => 'editor', 'score' => 70],
            ['name' => 'Carol', 'role' => 'admin', 'score' => 80],
        ]);

        $groups = $users->groupBy(fn($u) => $u['role']);
        $this->assertContainsOnlyInstancesOf(ALinqCollection::class, $groups->toArray());
        $this->assertSame(['admin', 'editor'], array_keys($groups->toArray()));

        $summary = $groups
            ->select(fn(ALinqCollection $group, string $role) => [
                'role' => $role,
                'count' => $group->count(),
                'avg' => $group->average(fn($u) => $u['score']),
            ])
            ->toArray();
        $this->assertSame(['role' => 'admin', 'count' => 2, 'avg' => 87.5], $summary['admin']);
        $this->assertSame(['role' => 'editor', 'count' => 1, 'avg' => 70], $summary['editor']);

        // Nested pipelines inside a group
        $this->assertSame(['Alice', 'Carol'], $groups->toArray()['admin']->column('name')->toArray());

        // The key selector itself may use the item key
        $byIndexParity = ALinqCollection::from(['a', 'b', 'c'])->groupBy(fn($v, $k) => $k % 2);
        $this->assertSame(['a', 'c'], $byIndexParity->toArray()[0]->toArray());
    }
}

/**
 * Fixture for RN-07: a BackedEnum is accepted as group key (its value is used).
 */
enum GroupingStatus: string
{
    case Active = "active";
    case Blocked = "blocked";
}
