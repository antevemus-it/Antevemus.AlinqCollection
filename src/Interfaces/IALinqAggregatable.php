<?php

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqAggregatable
 *
 * Interface for aggregation operations on collections
 *
 * @version    0.1.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqAggregatable extends IALinqBaseCollection
{
    /**
     * Check if any element satisfies predicate (Any in LINQ)
     */
    public function any(?callable $predicate = null): bool;

    /**
     * Check if all elements satisfy predicate (All in LINQ)
     */
    public function all(?callable $predicate = null): bool;

    /**
     * Get sum of elements (Sum in LINQ)
     */
    public function sum(?callable $selector = null): int|float;

    /**
     * Get average of elements (Average in LINQ)
     */
    public function average(?callable $selector = null): int|float;

    /**
     * Get minimum value (Min in LINQ)
     */
    public function min(?callable $selector = null);

    /**
     * Get maximum value (Max in LINQ)
     */
    public function max(?callable $selector = null);

    /**
     * Get product of elements
     */
    public function product(?callable $selector = null): int|float;

    /**
     * Count occurrences of values in the collection
     */
    public function countValues(): array;

    /**
     * Perform a custom aggregation operation (Aggregate in LINQ)
     */
    public function aggregate($seed, callable $func);

    /**
     * Applies an accumulator function over each group of elements in a sequence
     */
    public function aggregateBy(callable $keySelector, $seed, callable $func): IALinqCollection;

    /**
     * Groups elements by a specified key and counts the elements in each group
     */
    public function countBy(callable $keySelector): IALinqCollection;

    /**
     * Returns the maximum value in a sequence based on a key selector function
     */
    public function maxBy(callable $keySelector);

    /**
     * Returns the minimum value in a sequence based on a key selector function
     */
    public function minBy(callable $keySelector);
}
