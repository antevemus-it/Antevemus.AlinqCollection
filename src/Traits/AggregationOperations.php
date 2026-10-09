<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use InvalidArgumentException;
use UnderflowException;

/**
 * AggregationOperations Trait
 *
 * Provides LINQ-style aggregation operations for collections.
 *
 * Contract since 1.3.0 (forward 015): `null` values are ignored by sum(), average(),
 * min(), max() and product() (nullable semantics of LINQ); a value that is not numeric
 * (sum/average/product) or not comparable (min/max) throws InvalidArgumentException;
 * average(), min(), max(), minBy() and maxBy() throw UnderflowException on an empty
 * collection (or one with only nulls); sum() of nothing is 0 and product() of nothing is 1
 * (RN-11, RN-15). all() without a predicate asks whether every item is truthy and is
 * vacuously true on an empty collection; any() without a predicate still means "has at
 * least one item" (RN-14).
 *
 * @version    1.3.0
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
     * Without a predicate: whether the collection has at least one element.
     */
    public function any(?callable $predicate = NULL): bool
    {
        if ($predicate === null) {
            return count($this->items) > 0;
        }
        return array_any($this->items, ALinqCallable::withKey($predicate));
    }

    /**
     * Check if all elements satisfy predicate (All in LINQ)
     * Without a predicate: whether every element is truthy. Vacuously true when empty.
     */
    public function all(?callable $predicate = null): bool
    {
        $predicate = $predicate === null ? static fn($item) => (bool) $item : ALinqCallable::withKey($predicate);
        return array_all($this->items, $predicate);
    }

    /**
     * Values produced by a selector, one per item, in order (the selector receives
     * `($item, $key)` when it accepts two parameters, review 2026-10-08, decision 4a).
     *
     * @param callable|null $selector
     * @return array<array-key, mixed> keyed by the item key
     */
    private function selectValues(?callable $selector): array
    {
        if ($selector === null) {
            return $this->items;
        }

        $selector = ALinqCallable::withKey($selector);
        $values = [];
        foreach ($this->items as $key => $item) {
            $values[$key] = $selector($item, $key);
        }
        return $values;
    }

    /**
     * Numeric values of the collection with nulls dropped (RN-15).
     *
     * @return list<int|float>
     * @throws InvalidArgumentException for a value that is not numeric
     */
    private function numericValues(?callable $selector, string $operation): array
    {
        $values = [];
        foreach ($this->selectValues($selector) as $key => $value) {
            $number = ALinqContract::numericValue($value, $key, $operation);
            if ($number !== null) {
                $values[] = $number;
            }
        }
        return $values;
    }

    /**
     * Get sum of elements (Sum in LINQ); 0 when there is nothing to add.
     *
     * @throws InvalidArgumentException for a value that is not numeric
     */
    public function sum(?callable $selector = null): int|float
    {
        return array_sum($this->numericValues($selector, 'sum'));
    }

    /**
     * Get average of elements (Average in LINQ)
     *
     * @throws UnderflowException when the collection is empty or holds only nulls
     * @throws InvalidArgumentException for a value that is not numeric
     */
    public function average(?callable $selector = null): int|float
    {
        $values = $this->numericValues($selector, 'average');
        if ($values === []) {
            throw new UnderflowException('Cannot compute average() of an empty collection (null values are skipped).');
        }
        return array_sum($values) / count($values);
    }

    /**
     * Get minimum value (Min in LINQ)
     *
     * @throws UnderflowException when the collection is empty or holds only nulls
     * @throws InvalidArgumentException for a value that is not comparable
     */
    public function min(?callable $selector = null)
    {
        return $this->extreme($selector, 'min', -1);
    }

    /**
     * Get maximum value (Max in LINQ)
     *
     * @throws UnderflowException when the collection is empty or holds only nulls
     * @throws InvalidArgumentException for a value that is not comparable
     */
    public function max(?callable $selector = null)
    {
        return $this->extreme($selector, 'max', 1);
    }

    /**
     * Smallest ($direction -1) or largest (1) comparable value, nulls skipped.
     */
    private function extreme(?callable $selector, string $operation, int $direction): mixed
    {
        $found = false;
        $extreme = null;
        foreach ($this->selectValues($selector) as $key => $value) {
            $value = ALinqContract::comparableValue($value, $key, $operation);
            if ($value === null) {
                continue;
            }
            if (!$found || ($value <=> $extreme) === $direction) {
                $extreme = $value;
                $found = true;
            }
        }

        if (!$found) {
            throw new UnderflowException(sprintf('Cannot compute %s() of an empty collection (null values are skipped).', $operation));
        }
        return $extreme;
    }

    /**
     * Get product of elements; 1 when there is nothing to multiply (empty product).
     *
     * @throws InvalidArgumentException for a value that is not numeric
     */
    public function product(?callable $selector = null): int|float
    {
        return array_product($this->numericValues($selector, 'product'));
    }

    /**
     * Count occurrences of values in the collection
     */
    public function countValues(): array
    {
        return array_count_values($this->items);
    }

    /**
     * Perform a custom aggregation operation (Aggregate in LINQ); `$func($carry, $item, $key)`
     */
    public function aggregate($seed, callable $func)
    {
        return array_reduce(
            array_keys($this->items),
            fn($carry, $key) => $func($carry, $this->items[$key], $key),
            $seed
        );
    }

    /**
     * Applies an accumulator function over each group of elements in a sequence
     *
     * @param callable $keySelector A function to extract the key for each element
     * @param mixed $seed The initial accumulator value
     * @param callable $func An accumulator function to invoke on each group
     * @return self A collection of results from the aggregation
     * @throws InvalidArgumentException when the selector returns a key that is not int, string or BackedEnum
     */
    public function aggregateBy(callable $keySelector, $seed, callable $func): IALinqCollection
    {
        // groupBy() returns ALinqCollection groups since 1.2.0; each is reduced with its own
        // aggregate(), so $func receives (carry, item, key) as everywhere else.
        $result = [];
        foreach ($this->groupBy($keySelector)->toArray() as $key => $group) {
            $result[$key] = $group->aggregate($seed, $func);
        }

        return new self($result);
    }

    /**
     * Groups elements by a specified key and counts the elements in each group
     *
     * @param callable $keySelector A function to extract the key for each element
     * @return self A collection containing counts for each group
     * @throws InvalidArgumentException when the selector returns a key that is not int, string or BackedEnum
     */
    public function countBy(callable $keySelector): IALinqCollection
    {
        $result = [];
        foreach ($this->groupBy($keySelector)->toArray() as $key => $group) {
            $result[$key] = $group->count();
        }

        return new self($result);
    }

    /**
     * Returns the element with the largest key (MaxBy in LINQ)
     *
     * @param callable $keySelector A function to extract the comparison key from each element
     * @return mixed The first element whose key is the largest
     * @throws UnderflowException when the collection is empty
     */
    public function maxBy(callable $keySelector): mixed
    {
        return $this->extremeBy($keySelector, 'maxBy', 1);
    }

    /**
     * Returns the element with the smallest key (MinBy in LINQ)
     *
     * @param callable $keySelector A function to extract the comparison key from each element
     * @return mixed The first element whose key is the smallest
     * @throws UnderflowException when the collection is empty
     */
    public function minBy(callable $keySelector): mixed
    {
        return $this->extremeBy($keySelector, 'minBy', -1);
    }

    /**
     * First element whose key is the smallest ($direction -1) or the largest (1).
     */
    private function extremeBy(callable $keySelector, string $operation, int $direction): mixed
    {
        if ($this->items === []) {
            throw new UnderflowException(sprintf('Cannot compute %s() of an empty collection.', $operation));
        }

        $keySelector = ALinqCallable::withKey($keySelector);
        $extremeItem = null;
        $extremeKey = null;
        $first = true;
        foreach ($this->items as $itemKey => $item) {
            $key = $keySelector($item, $itemKey);
            if ($first || ($key <=> $extremeKey) === $direction) {
                $extremeItem = $item;
                $extremeKey = $key;
                $first = false;
            }
        }

        return $extremeItem;
    }
}
