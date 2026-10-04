<?php

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Aggregation Operations
 * Tests all 13 aggregation methods in AggregationOperations trait
 */
class AggregationOperationsTest extends TestCase
{
    // ===== ANY TESTS =====

    /**
     * Test any returns true when collection has elements
     */
    public function testAnyReturnsTrueWhenCollectionHasElements(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);

        $this->assertTrue($collection->any());
    }

    /**
     * Test any returns false on empty collection
     */
    public function testAnyReturnsFalseOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertFalse($collection->any());
    }

    /**
     * Test any with predicate returns true when at least one matches
     */
    public function testAnyWithPredicateReturnsTrueWhenMatches(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        $this->assertTrue($collection->any(fn($x) => $x > 3));
    }

    /**
     * Test any with predicate returns false when none match
     */
    public function testAnyWithPredicateReturnsFalseWhenNoneMatch(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);

        $this->assertFalse($collection->any(fn($x) => $x > 10));
    }

    // ===== ALL TESTS =====

    /**
     * Test all returns true when all elements satisfy predicate
     */
    public function testAllReturnsTrueWhenAllSatisfyPredicate(): void
    {
        $collection = ALinqCollection::from([2, 4, 6, 8]);

        $this->assertTrue($collection->all(fn($x) => $x % 2 === 0));
    }

    /**
     * Test all returns false when at least one doesn't satisfy predicate
     */
    public function testAllReturnsFalseWhenOneFails(): void
    {
        $collection = ALinqCollection::from([2, 4, 5, 8]);

        $this->assertFalse($collection->all(fn($x) => $x % 2 === 0));
    }

    /**
     * Test all on empty collection returns true (vacuous truth)
     */
    public function testAllOnEmptyCollectionReturnsTrue(): void
    {
        $collection = ALinqCollection::empty();

        // Logicamente, "todos os elementos satisfazem" é verdade quando não há elementos
        $this->assertTrue($collection->all(fn($x) => true));
        $this->assertTrue($collection->all(fn($x) => false)); // Também verdade em coleção vazia
    }

    // ===== SUM TESTS =====

    /**
     * Test sum calculates total of numeric values
     */
    public function testSumCalculatesTotalOfNumericValues(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        $this->assertEquals(15, $collection->sum());
    }

    /**
     * Test sum with selector function
     */
    public function testSumWithSelectorFunction(): void
    {
        $collection = ALinqCollection::from([
            ['price' => 10],
            ['price' => 20],
            ['price' => 30]
        ]);

        $result = $collection->sum(fn($item) => $item['price']);

        $this->assertEquals(60, $result);
    }

    /**
     * Test sum on empty collection returns zero
     */
    public function testSumOnEmptyCollectionReturnsZero(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertEquals(0, $collection->sum());
    }

    /**
     * Test sum with floating point numbers
     */
    public function testSumWithFloatingPointNumbers(): void
    {
        $collection = ALinqCollection::from([1.5, 2.5, 3.0]);

        $this->assertEquals(7.0, $collection->sum());
    }

    // ===== AVERAGE TESTS =====

    /**
     * Test average calculates mean of numeric values
     */
    public function testAverageCalculatesMeanOfNumericValues(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        $this->assertEquals(3, $collection->average());
    }

    /**
     * Test average with selector function
     */
    public function testAverageWithSelectorFunction(): void
    {
        $collection = ALinqCollection::from([
            ['value' => 10],
            ['value' => 20],
            ['value' => 30]
        ]);

        $result = $collection->average(fn($item) => $item['value']);

        $this->assertEquals(20, $result);
    }

    /**
     * Test average on empty collection returns zero
     */
    public function testAverageOnEmptyCollectionReturnsZero(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertEquals(0, $collection->average());
    }

    /**
     * Test average with floating point result
     */
    public function testAverageWithFloatingPointResult(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);

        $this->assertEquals(2, $collection->average());
    }

    // ===== MIN TESTS =====

    /**
     * Test min returns minimum value
     */
    public function testMinReturnsMinimumValue(): void
    {
        $collection = ALinqCollection::from([5, 2, 8, 1, 9]);

        $this->assertEquals(1, $collection->min());
    }

    /**
     * Test min with selector function
     */
    public function testMinWithSelectorFunction(): void
    {
        $collection = ALinqCollection::from([
            ['age' => 25],
            ['age' => 30],
            ['age' => 20]
        ]);

        $result = $collection->min(fn($item) => $item['age']);

        $this->assertEquals(20, $result);
    }

    /**
     * Test min on empty collection returns null
     */
    public function testMinOnEmptyCollectionReturnsNull(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertNull($collection->min());
    }

    /**
     * Test min with negative numbers
     */
    public function testMinWithNegativeNumbers(): void
    {
        $collection = ALinqCollection::from([5, -2, 8, -10, 3]);

        $this->assertEquals(-10, $collection->min());
    }

    // ===== MAX TESTS =====

    /**
     * Test max returns maximum value
     */
    public function testMaxReturnsMaximumValue(): void
    {
        $collection = ALinqCollection::from([5, 2, 8, 1, 9]);

        $this->assertEquals(9, $collection->max());
    }

    /**
     * Test max with selector function
     */
    public function testMaxWithSelectorFunction(): void
    {
        $collection = ALinqCollection::from([
            ['score' => 85],
            ['score' => 92],
            ['score' => 78]
        ]);

        $result = $collection->max(fn($item) => $item['score']);

        $this->assertEquals(92, $result);
    }

    /**
     * Test max on empty collection returns null
     */
    public function testMaxOnEmptyCollectionReturnsNull(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertNull($collection->max());
    }

    // ===== PRODUCT TESTS =====

    /**
     * Test product multiplies all values
     */
    public function testProductMultipliesAllValues(): void
    {
        $collection = ALinqCollection::from([2, 3, 4]);

        $this->assertEquals(24, $collection->product());
    }

    /**
     * Test product with selector function
     */
    public function testProductWithSelectorFunction(): void
    {
        $collection = ALinqCollection::from([
            ['factor' => 2],
            ['factor' => 3],
            ['factor' => 4]
        ]);

        $result = $collection->product(fn($item) => $item['factor']);

        $this->assertEquals(24, $result);
    }

    /**
     * Test product on empty collection returns zero
     */
    public function testProductOnEmptyCollectionReturnsZero(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertEquals(0, $collection->product());
    }

    /**
     * Test product with zero in collection
     */
    public function testProductWithZeroInCollection(): void
    {
        $collection = ALinqCollection::from([2, 0, 4]);

        $this->assertEquals(0, $collection->product());
    }

    // ===== COUNT VALUES TESTS =====

    /**
     * Test countValues counts occurrences of each value
     */
    public function testCountValuesCountsOccurrences(): void
    {
        $collection = ALinqCollection::from([1, 2, 2, 3, 3, 3]);

        $result = $collection->countValues();

        $expected = [1 => 1, 2 => 2, 3 => 3];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test countValues with string values
     */
    public function testCountValuesWithStringValues(): void
    {
        $collection = ALinqCollection::from(['a', 'b', 'a', 'c', 'a', 'b']);

        $result = $collection->countValues();

        $expected = ['a' => 3, 'b' => 2, 'c' => 1];
        $this->assertEquals($expected, $result);
    }

    // ===== AGGREGATE TESTS =====

    /**
     * Test aggregate performs custom reduction
     */
    public function testAggregatePerformsCustomReduction(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        $result = $collection->aggregate(0, fn($carry, $value, $key) => $carry + $value);

        $this->assertEquals(15, $result);
    }

    /**
     * Test aggregate with string concatenation
     */
    public function testAggregateWithStringConcatenation(): void
    {
        $collection = ALinqCollection::from(['a', 'b', 'c']);

        $result = $collection->aggregate('', fn($carry, $value, $key) => $carry . $value);

        $this->assertEquals('abc', $result);
    }

    /**
     * Test aggregate with initial seed value
     */
    public function testAggregateWithInitialSeedValue(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);

        $result = $collection->aggregate(10, fn($carry, $value, $key) => $carry + $value);

        $this->assertEquals(16, $result);
    }

    /**
     * Test aggregate uses key in reduction
     */
    public function testAggregateUsesKeyInReduction(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);

        $result = $collection->aggregate([], fn($carry, $value, $key) => array_merge($carry, [$key => $value * 2]));

        $expected = ['a' => 2, 'b' => 4, 'c' => 6];
        $this->assertEquals($expected, $result);
    }

    // ===== AGGREGATE BY TESTS =====

    /**
     * Test aggregateBy groups and aggregates
     */
    public function testAggregateByGroupsAndAggregates(): void
    {
        $collection = ALinqCollection::from([
            ['category' => 'A', 'value' => 10],
            ['category' => 'B', 'value' => 20],
            ['category' => 'A', 'value' => 15],
            ['category' => 'B', 'value' => 25]
        ]);

        $result = $collection->aggregateBy(
            fn($item) => $item['category'],
            0,
            fn($carry, $item) => $carry + $item['value']
        );

        $resultArray = $result->toArray();
        $this->assertEquals(25, $resultArray['A']);
        $this->assertEquals(45, $resultArray['B']);
    }

    // ===== COUNT BY TESTS =====

    /**
     * Test countBy counts elements by key
     */
    public function testCountByCountsElementsByKey(): void
    {
        $collection = ALinqCollection::from([
            ['type' => 'A'],
            ['type' => 'B'],
            ['type' => 'A'],
            ['type' => 'A'],
            ['type' => 'C']
        ]);

        $result = $collection->countBy(fn($item) => $item['type']);

        $resultArray = $result->toArray();
        $this->assertEquals(3, $resultArray['A']);
        $this->assertEquals(1, $resultArray['B']);
        $this->assertEquals(1, $resultArray['C']);
    }

    /**
     * Test countBy with numeric keys
     */
    public function testCountByWithNumericKeys(): void
    {
        $collection = ALinqCollection::from([
            ['priority' => 1],
            ['priority' => 2],
            ['priority' => 1],
            ['priority' => 1]
        ]);

        $result = $collection->countBy(fn($item) => $item['priority']);

        $resultArray = $result->toArray();
        $this->assertEquals(3, $resultArray[1]);
        $this->assertEquals(1, $resultArray[2]);
    }

    // ===== MAX BY TESTS =====

    /**
     * Test maxBy returns element with maximum key value
     */
    public function testMaxByReturnsElementWithMaximumKeyValue(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30],
            ['name' => 'Charlie', 'age' => 28]
        ]);

        $result = $collection->maxBy(fn($item) => $item['age']);

        $this->assertEquals('Bob', $result['name']);
        $this->assertEquals(30, $result['age']);
    }

    /**
     * Test maxBy on empty collection returns null
     */
    public function testMaxByOnEmptyCollectionReturnsNull(): void
    {
        $collection = ALinqCollection::empty();

        $result = $collection->maxBy(fn($item) => $item['value']);

        $this->assertNull($result);
    }

    /**
     * Test maxBy with string comparison
     */
    public function testMaxByWithStringComparison(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Alice'],
            ['name' => 'Charlie'],
            ['name' => 'Bob']
        ]);

        $result = $collection->maxBy(fn($item) => $item['name']);

        $this->assertEquals('Charlie', $result['name']);
    }

    // ===== MIN BY TESTS =====

    /**
     * Test minBy returns element with minimum key value
     */
    public function testMinByReturnsElementWithMinimumKeyValue(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Alice', 'score' => 85],
            ['name' => 'Bob', 'score' => 92],
            ['name' => 'Charlie', 'score' => 78]
        ]);

        $result = $collection->minBy(fn($item) => $item['score']);

        $this->assertEquals('Charlie', $result['name']);
        $this->assertEquals(78, $result['score']);
    }

    /**
     * Test minBy on empty collection returns null
     */
    public function testMinByOnEmptyCollectionReturnsNull(): void
    {
        $collection = ALinqCollection::empty();

        $result = $collection->minBy(fn($item) => $item['value']);

        $this->assertNull($result);
    }

    /**
     * Test minBy with negative values
     */
    public function testMinByWithNegativeValues(): void
    {
        $collection = ALinqCollection::from([
            ['value' => 5],
            ['value' => -10],
            ['value' => 3]
        ]);

        $result = $collection->minBy(fn($item) => $item['value']);

        $this->assertEquals(-10, $result['value']);
    }

    /**
     * Test fluent API chaining with aggregation operations
     */
    public function testFluentApiChainingWithAggregationOperations(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

        $hasEven = $collection->any(fn($x) => $x % 2 === 0);
        $allPositive = $collection->all(fn($x) => $x > 0);
        $total = $collection->sum();

        $this->assertTrue($hasEven);
        $this->assertTrue($allPositive);
        $this->assertEquals(55, $total);
    }

    /**
     * Test all() with null predicate returns false
     * This tests the edge case where predicate is null (lines 39-40)
     */
    public function testAllWithNullPredicateReturnsFalse(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);

        // Call with null predicate should return false
        $result = $collection->all(null);

        // Should return false when predicate is null
        $this->assertFalse($result);
    }
}
