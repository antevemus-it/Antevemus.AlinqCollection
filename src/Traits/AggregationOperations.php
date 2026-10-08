<?php

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Interfaces\IALinqCollection;

/**
 * AggregationOperations Trait
 *
 * Provides LINQ-style aggregation operations for collections
 *
 * @version    0.1.0
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait AggregationOperations
{
    /**
     * Check if any element satisfies predicate (Any in LINQ)
     * Leverages PHP 8.4's array_any function
     */
    public function any(?callable $predicate = NULL): bool
    {
        if ($predicate === null) {
            return count($this->items) > 0;
        }
        return array_any($this->items, $predicate);
    }

    /**
     * Check if all elements satisfy predicate (All in LINQ)
     * Leverages PHP 8.4's array_all function
     */
    public function all(?callable $predicate = null): bool
    {
        if ($predicate === null) {
            return false;
        }
        return array_all($this->items, $predicate);
    }

    /**
     * Get sum of elements (Sum in LINQ)
     */
    public function sum(?callable $selector = null): int|float
    {
        if ($selector !== null) {
            return array_sum(array_map($selector, $this->items));
        }
        return array_sum($this->items);
    }

    /**
     * Get average of elements (Average in LINQ)
     */
    public function average(?callable $selector = null): int|float
    {
        if (empty($this->items)) {
            return 0;
        }

        if ($selector !== null) {
            $values = array_map($selector, $this->items);
            return array_sum($values) / count($values);
        }
        return array_sum($this->items) / count($this->items);
    }

    /**
     * Get minimum value (Min in LINQ)
     */
    public function min(?callable $selector = null)
    {
        if (empty($this->items)) {
            return null;
        }

        if ($selector !== null) {
            $values = array_map($selector, $this->items);
            return min($values);
        }
        return min($this->items);
    }

    /**
     * Get maximum value (Max in LINQ)
     */
    public function max(?callable $selector = null)
    {
        if (empty($this->items)) {
            return null;
        }

        if ($selector !== null) {
            $values = array_map($selector, $this->items);
            return max($values);
        }
        return max($this->items);
    }

    /**
     * Get product of elements
     */
    public function product(?callable $selector = null): int|float
    {
        if (empty($this->items)) {
            return 0;
        }

        if ($selector !== null) {
            $values = array_map($selector, $this->items);
            return array_product($values);
        }
        return array_product($this->items);
    }

    /**
     * Count occurrences of values in the collection
     */
    public function countValues(): array
    {
        return array_count_values($this->items);
    }

    /**
     * Perform a custom aggregation operation (Aggregate in LINQ)
     */
    public function aggregate($seed, callable $func)
    {
        return array_reduce(
            array_keys($this->items),
            fn($carry, $key) => $func($carry, $this->items[$key], $key),
            $seed
        );
    }
    /*
    public function aggregate($seed, callable $func)
    {
        return array_reduce($this->items, $func, $seed);
    }*/

    /**
     * Applies an accumulator function over each group of elements in a sequence
     *
     * @param callable $keySelector A function to extract the key for each element
     * @param mixed $seed The initial accumulator value
     * @param callable $func An accumulator function to invoke on each group
     * @return self A collection of results from the aggregation
     */
    public function aggregateBy(callable $keySelector, $seed, callable $func): IALinqCollection
    {
        $groups = $this->groupBy($keySelector)->toArray();

        $result = array_map(function ($group) use ($func, $seed) {
            return array_reduce($group, $func, $seed);
        }, $groups);

        return new self($result);
    }

    /**
     * Groups elements by a specified key and counts the elements in each group
     *
     * @param callable $keySelector A function to extract the key for each element
     * @return self A collection containing counts for each group
     */
    public function countBy(callable $keySelector): IALinqCollection
    {
        $groups = $this->groupBy($keySelector)->toArray();

        $result = array_map(function ($group) {
            return count($group);
        }, $groups);

        return new self($result);
    }

    /**
     * Returns the maximum value in a sequence based on a key selector function
     *
     * @param callable $keySelector A function to extract the comparison key from each element
     * @return mixed The maximum value element based on the key selector
     */
    public function maxBy(callable $keySelector): mixed
    {
        if (empty($this->items)) {
            return null;
        }

        $maxItem = reset($this->items);
        $maxKey = $keySelector($maxItem);

        foreach ($this->items as $item) {
            $key = $keySelector($item);
            if ($key > $maxKey) {
                $maxItem = $item;
                $maxKey = $key;
            }
        }

        return $maxItem;
    }

    /**
     * Returns the minimum value in a sequence based on a key selector function
     *
     * @param callable $keySelector A function to extract the comparison key from each element
     * @return mixed The minimum value element based on the key selector
     */
    public function minBy(callable $keySelector): mixed
    {
        if (empty($this->items)) {
            return null;
        }

        $minItem = reset($this->items);
        $minKey = $keySelector($minItem);

        foreach ($this->items as $item) {
            $key = $keySelector($item);
            if ($key < $minKey) {
                $minItem = $item;
                $minKey = $key;
            }
        }
        return $minItem;
    }
}
