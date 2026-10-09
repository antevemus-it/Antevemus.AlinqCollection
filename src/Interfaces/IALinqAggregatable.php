<?php

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqAggregatable
 *
 * Interface for aggregation operations on collections.
 *
 * Contract (1.3.0): an empty collection throws `UnderflowException` where LINQ throws
 * (`min`, `max`, `minBy`, `maxBy`, `average`); `sum` of nothing is `0`, `product` of nothing
 * is `1`. The numeric aggregations skip `null` (nullable semantics), accept numeric strings
 * and count `bool` as 0/1; anything else throws `InvalidArgumentException`. Group keys must
 * be int, string or BackedEnum.
 *
 * @version    1.3.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqAggregatable extends IALinqBaseCollection
{
    /**
     * Check if any element satisfies predicate (Any in LINQ); without a predicate, whether there is at least one element
     */
    public function any(?callable $predicate = null): bool;

    /**
     * Check if all elements satisfy predicate (All in LINQ); vacuously true on an empty collection.
     * Without a predicate, whether every element is truthy.
     */
    public function all(?callable $predicate = null): bool;

    /**
     * Get sum of elements (Sum in LINQ); `null` values are skipped, an empty collection sums to 0
     *
     * @throws \InvalidArgumentException when a value is not numeric
     */
    public function sum(?callable $selector = null): int|float;

    /**
     * Get average of elements (Average in LINQ); `null` values are skipped
     *
     * @throws \UnderflowException when there is no non-null value to average
     * @throws \InvalidArgumentException when a value is not numeric
     */
    public function average(?callable $selector = null): int|float;

    /**
     * Get minimum value (Min in LINQ); `null` values are skipped; scalars and DateTimeInterface are comparable
     *
     * @throws \UnderflowException when there is no non-null value
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function min(?callable $selector = null);

    /**
     * Get maximum value (Max in LINQ); `null` values are skipped; scalars and DateTimeInterface are comparable
     *
     * @throws \UnderflowException when there is no non-null value
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function max(?callable $selector = null);

    /**
     * Get product of elements; `null` values are skipped, an empty product is 1
     *
     * @throws \InvalidArgumentException when a value is not numeric
     */
    public function product(?callable $selector = null): int|float;

    /**
     * Count occurrences of values in the collection (int and string values only, as array_count_values)
     */
    public function countValues(): array;

    /**
     * Perform a custom aggregation operation (Aggregate in LINQ)
     *
     * @param callable $func fn($carry, $item, $key): mixed
     */
    public function aggregate($seed, callable $func);

    /**
     * Applies an accumulator function over each group of elements in a sequence
     *
     * @throws \InvalidArgumentException when the key selector returns something other than int, string or BackedEnum
     */
    public function aggregateBy(callable $keySelector, $seed, callable $func): IALinqCollection;

    /**
     * Groups elements by a specified key and counts the elements in each group
     *
     * @throws \InvalidArgumentException when the key selector returns something other than int, string or BackedEnum
     */
    public function countBy(callable $keySelector): IALinqCollection;

    /**
     * Returns the element whose selected key is the maximum
     *
     * @throws \UnderflowException when the collection is empty
     */
    public function maxBy(callable $keySelector);

    /**
     * Returns the element whose selected key is the minimum
     *
     * @throws \UnderflowException when the collection is empty
     */
    public function minBy(callable $keySelector);
}
