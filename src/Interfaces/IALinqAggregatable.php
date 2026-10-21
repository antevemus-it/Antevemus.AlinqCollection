<?php

declare(strict_types=1);

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
 * be int, string or BackedEnum. `single()` (1.4.0) throws where LINQ `Single` throws.
 *
 * Since 2.1.0 (forward 036): `minOrDefault`, `maxOrDefault`, `averageOrDefault`,
 * `minByOrDefault` and `maxByOrDefault` answer as their siblings, with `$default` instead of
 * the `UnderflowException` of "nothing to aggregate"; every other exception is the same.
 * Classes that implement this interface on their own must add them.
 *
 * Since 2.2.0 (forward 047): `min`, `max`, `minBy`, `maxBy` and their `*OrDefault` variants
 * take an optional comparer `fn($a, $b): int` as their last parameter (null values are still
 * skipped by `min`/`max`; the comparer decides what can be compared); `implode()` joins the
 * text of the items; `countBy`/`aggregateBy` answer as before without building the groups
 * (`aggregateBy` hands `$func` the position of the item inside its group). Classes that
 * implement this interface on their own must add the parameters and `implode()`.
 *
 * Since 2.4.0: `countBy` and `aggregateBy` take an optional `IALinqEqualityComparer` (the last
 * parameter of `countBy`, the fourth of `aggregateBy`; the keys equal under it are one group,
 * named by the first seen), and
 * `aggregateBy` takes a last optional `$seedSelector` `fn($key)` that returns the seed of each
 * group. Classes that implement this interface on their own must add the parameters.
 *
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqAggregatable extends IALinqBaseCollection
{
    /**
     * Check if any element satisfies predicate (Any in LINQ); without a predicate, whether there is at least one element
     */
    public function any(?callable $predicate = null): bool;

    /**
     * Get the single element, or the single element that satisfies the predicate (Single in LINQ)
     *
     * Messages (the same on the lazy side): `Sequence contains no elements.`,
     * `Sequence contains no matching element.`, `Sequence contains more than one element.`,
     * `Sequence contains more than one matching element.`
     *
     * @param callable|null $predicate `fn($item)` or `fn($item, $key)`
     * @throws \UnderflowException when the collection is empty or no element matches
     * @throws \OverflowException when more than one element (or matching element) exists
     */
    public function single(?callable $predicate = null): mixed;

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
     * Get average of elements, or `$default` when there is no non-null value to average
     * (LINQ's nullable Average, with a chosen default instead of null)
     *
     * @param mixed $default Returned instead of the UnderflowException of average()
     * @param callable|null $selector `fn($item)` or `fn($item, $key)`
     * @throws \InvalidArgumentException when a value is not numeric
     */
    public function averageOrDefault(mixed $default = null, ?callable $selector = null);

    /**
     * Get minimum value (Min in LINQ); `null` values are skipped; scalars and DateTimeInterface are comparable
     *
     * @param callable|null $comparer `fn($a, $b): int` (since 2.2.0); with it, the comparer decides what is comparable
     * @throws \UnderflowException when there is no non-null value
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function min(?callable $selector = null, ?callable $comparer = null);

    /**
     * Get minimum value, or `$default` when there is no non-null value (LINQ's nullable Min,
     * with a chosen default instead of null); a `null` default is indistinguishable from
     * "nothing to compare", as in firstOrDefault()
     *
     * @param mixed $default Returned instead of the UnderflowException of min()
     * @param callable|null $selector `fn($item)` or `fn($item, $key)`
     * @param callable|null $comparer `fn($a, $b): int` (since 2.2.0)
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function minOrDefault(mixed $default = null, ?callable $selector = null, ?callable $comparer = null);

    /**
     * Get maximum value (Max in LINQ); `null` values are skipped; scalars and DateTimeInterface are comparable
     *
     * @param callable|null $comparer `fn($a, $b): int` (since 2.2.0); with it, the comparer decides what is comparable
     * @throws \UnderflowException when there is no non-null value
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function max(?callable $selector = null, ?callable $comparer = null);

    /**
     * Get maximum value, or `$default` when there is no non-null value (LINQ's nullable Max,
     * with a chosen default instead of null)
     *
     * @param mixed $default Returned instead of the UnderflowException of max()
     * @param callable|null $selector `fn($item)` or `fn($item, $key)`
     * @param callable|null $comparer `fn($a, $b): int` (since 2.2.0)
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function maxOrDefault(mixed $default = null, ?callable $selector = null, ?callable $comparer = null);

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
     * Applies an accumulator function over each group of elements in a sequence (AggregateBy in .NET 9)
     *
     * @param mixed $seed The seed of every group, whatever its type (ignored when $seedSelector is given)
     * @param callable $func `fn($carry, $item, $position)`: the position of the item inside its group, not its source key
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); the first key seen names the group
     * @param callable|null $seedSelector `fn($key)`, the seed of each group, called once per group (since 2.4.0)
     * @throws \InvalidArgumentException when the key selector returns something other than int, string or BackedEnum
     */
    public function aggregateBy(callable $keySelector, $seed, callable $func, ?IALinqEqualityComparer $comparer = null, ?callable $seedSelector = null): IALinqCollection;

    /**
     * Groups elements by a specified key and counts the elements in each group
     *
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); the first key seen names the group
     * @throws \InvalidArgumentException when the key selector returns something other than int, string or BackedEnum
     */
    public function countBy(callable $keySelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Returns the element whose selected key is the maximum
     *
     * @param callable|null $comparer `fn($keyA, $keyB): int` (since 2.2.0)
     * @throws \UnderflowException when the collection is empty
     */
    public function maxBy(callable $keySelector, ?callable $comparer = null);

    /**
     * Returns the element whose selected key is the maximum (the first one on a tie), or
     * `$default` when the collection is empty (LINQ's MaxBy over a nullable source)
     *
     * @param mixed $default Returned instead of the UnderflowException of maxBy()
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @param callable|null $comparer `fn($keyA, $keyB): int` (since 2.2.0)
     */
    public function maxByOrDefault(mixed $default, callable $keySelector, ?callable $comparer = null);

    /**
     * Returns the element whose selected key is the minimum
     *
     * @param callable|null $comparer `fn($keyA, $keyB): int` (since 2.2.0)
     * @throws \UnderflowException when the collection is empty
     */
    public function minBy(callable $keySelector, ?callable $comparer = null);

    /**
     * Returns the element whose selected key is the minimum (the first one on a tie), or
     * `$default` when the collection is empty (LINQ's MinBy over a nullable source)
     *
     * @param mixed $default Returned instead of the UnderflowException of minBy()
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @param callable|null $comparer `fn($keyA, $keyB): int` (since 2.2.0)
     */
    public function minByOrDefault(mixed $default, callable $keySelector, ?callable $comparer = null);

    /**
     * Join the text of the items (or of the selected values) with a separator (since 2.2.0):
     * strings, ints, floats and Stringable by their text, null as '', '' when empty
     *
     * @param callable|null $selector `fn($item)` or `fn($item, $key)`
     * @throws \InvalidArgumentException for a bool, an array or an object that is not Stringable
     */
    public function implode(string $separator = '', ?callable $selector = null): string;
}
