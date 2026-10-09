<?php

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
 *
 * @version    1.3.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqJoinable extends IALinqBaseCollection
{
    /**
     * Join with another collection (Join in LINQ); keys match by strict identity and a
     * null key never matches (LINQ and SQL semantics); result is a list
     */
    public function join(array $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector): IALinqCollection;

    /**
     * Left join with another collection (GroupJoin in LINQ); the matched group is an
     * ALinqCollection (empty when nothing matches); keys match by strict identity and a
     * null key never matches (LINQ and SQL semantics)
     */
    public function groupJoin(array $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector): IALinqCollection;

    /**
     * Concatenate with another collection (Concat in LINQ): a sequence operation, the result is a reindexed list
     */
    public function concat(array $second): IALinqCollection;

    /**
     * Get the distinct elements that exist in both collections, by strict identity (Intersect in LINQ)
     */
    public function intersect(array $second): IALinqCollection;

    /**
     * Get the distinct elements of this collection that don't exist in second, by strict identity (Except in LINQ)
     */
    public function except(array $second): IALinqCollection;

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
     * Produces the set difference of two sequences based on a key selector function
     */
    public function exceptBy(array $second, callable $keySelector): IALinqCollection;

    /**
     * Produces the set intersection of two sequences based on a key selector function
     */
    public function intersectBy(array $second, callable $keySelector): IALinqCollection;

    /**
     * Produces the set union of two sequences based on a key selector function
     */
    public function unionBy(array $second, callable $keySelector): IALinqCollection;
}
