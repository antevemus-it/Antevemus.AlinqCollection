<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqJoinable
 *
 * Interface for joining operations on collections.
 *
 * Contract (1.3.0): joins and set operations compare keys and elements by the strict,
 * type-aware identity of `ALinqCallable::hashKey()` (`1` and `'1'` are different keys:
 * cast on the caller side for PDO rows); `intersect`/`except` are set operations and
 * deduplicate; `concat` is a sequence operation and always yields a reindexed list;
 * a custom comparer may answer `bool` (`true` = equal) or an int in the `<=>` convention.
 * The outer joins (1.4.0) follow `Enumerable.LeftJoin/RightJoin/FullJoin` of .NET 10/11:
 * the result selector receives `($outer, $inner)` with `null` on the side without a match,
 * the default result is the pair `[$outer, $inner]`, the output is always a list and a
 * `null` key never matches (that side comes out without a partner).
 *
 * Since 2.2.0 (forward 047): `join()`/`groupJoin()` take any iterable as the inner side (read
 * once); `exceptByKeys()`/`intersectByKeys()` are the `ExceptBy`/`IntersectBy` of .NET 6 (a
 * sequence of keys; a `null` key matches a `null` in it, a set rule). `exceptBy()`/
 * `intersectBy()` keep the MoreLINQ form (a sequence of elements, the selector applied to
 * both sides). Classes that implement this interface on their own must widen the two
 * parameters and add the two methods.
 *
 * Since 2.4.0: every join and every set operation by key or element (`intersect`, `except`,
 * `intersectBy`, `exceptBy`, `exceptByKeys`, `intersectByKeys`, `unionBy`) takes an optional
 * `IALinqEqualityComparer` as its last parameter: hash-based (its `hash()` indexes, its
 * `equals()` confirms), the first item of each key kept by a set operation. Classes that
 * implement this interface on their own must add the parameter.
 *
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqJoinable extends IALinqBaseCollection
{
    /**
     * Join with another collection (Join in LINQ); keys match by strict identity and a
     * null key never matches (LINQ and SQL semantics); result is a list
     */
    public function join(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Left join with another collection (GroupJoin in LINQ); the matched group is an
     * ALinqCollection (empty when nothing matches); keys match by strict identity and a
     * null key never matches (LINQ and SQL semantics)
     */
    public function groupJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Left outer join (LeftJoin in .NET 10/11 LINQ): every outer item, paired with each match
     * or with `null`; outer order
     *
     * @param callable|null $resultSelector `fn($outer, $inner|null)`; the pair `[$outer, $inner]` when null
     */
    public function leftJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Right outer join (RightJoin in .NET 10/11 LINQ): every inner item, paired with each
     * matching outer item (outer order) or with `null`; inner order
     *
     * @param callable|null $resultSelector `fn($outer|null, $inner)`; the pair `[$outer, $inner]` when null
     */
    public function rightJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Full outer join (FullJoin in .NET 10/11 LINQ): the outer items in outer order (paired
     * with each match or with `null`), then the unmatched inner items in inner order
     *
     * @param callable|null $resultSelector `fn($outer|null, $inner|null)`; the pair `[$outer, $inner]` when null
     */
    public function fullJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Concatenate with another collection (Concat in LINQ): a sequence operation, the result is a reindexed list
     */
    public function concat(array $second): IALinqCollection;

    /**
     * Get the distinct elements that exist in both collections, by strict identity (Intersect in LINQ)
     */
    public function intersect(array $second, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Get the distinct elements of this collection that don't exist in second, by strict identity (Except in LINQ)
     */
    public function except(array $second, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Intersect with another collection using a custom comparer (`bool` or `<=>` answer)
     */
    public function intersectWith(array $second, callable $comparer): IALinqCollection;

    /**
     * Difference with another collection using a custom comparer (`bool` or `<=>` answer)
     */
    public function exceptWith(array $second, callable $comparer): IALinqCollection;

    /**
     * Combine collections using keys from one and values from another
     *
     * @throws \ValueError when the two arrays have different sizes
     */
    public function combine(array $values): IALinqCollection;

    /**
     * Replace elements in the collection with elements from another collection
     */
    public function replace(array $replacements): IALinqCollection;

    /**
     * Replace elements in the collection recursively with elements from another collection
     */
    public function replaceRecursive(array $replacements): IALinqCollection;

    /**
     * Produces the set difference of two sequences based on a key selector function; $second
     * holds ELEMENTS and the selector applies to both sides (MoreLINQ), unlike the `ExceptBy`
     * of .NET 6: for a sequence of keys use exceptByKeys()
     */
    public function exceptBy(array $second, callable $keySelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Produces the set intersection of two sequences based on a key selector function; $second
     * holds ELEMENTS and the selector applies to both sides (MoreLINQ), unlike the `IntersectBy`
     * of .NET 6: for a sequence of keys use intersectByKeys()
     */
    public function intersectBy(array $second, callable $keySelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * The distinct items whose selected key is not in $keys (ExceptBy of .NET 6, since 2.2.0):
     * $keys is read once, the selector runs on this collection only, strict identity
     */
    public function exceptByKeys(iterable $keys, callable $keySelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * The distinct items whose selected key is in $keys (IntersectBy of .NET 6, since 2.2.0):
     * $keys is read once, the selector runs on this collection only, strict identity
     */
    public function intersectByKeys(iterable $keys, callable $keySelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection;

    /**
     * Produces the set union of two sequences based on a key selector function
     */
    public function unionBy(array $second, callable $keySelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection;
}
