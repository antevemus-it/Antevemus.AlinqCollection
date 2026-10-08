<?php

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqJoinable
 *
 * Interface for joining operations on collections
 *
 * @version    0.1.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqJoinable extends IALinqBaseCollection
{
    /**
     * Join with another collection (Join in LINQ)
     */
    public function join(array $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector): IALinqCollection;

    /**
     * Left join with another collection (GroupJoin in LINQ)
     */
    public function groupJoin(array $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector): IALinqCollection;

    /**
     * Concatenate with another collection (Concat in LINQ)
     */
    public function concat(array $second): IALinqCollection;

    /**
     * Get elements that exist in both collections (Intersect in LINQ)
     */
    public function intersect(array $second): IALinqCollection;

    /**
     * Get elements from this collection that don't exist in second (Except in LINQ)
     */
    public function except(array $second): IALinqCollection;

    /**
     * Intersect with another collection using a custom comparer
     */
    public function intersectWith(array $second, callable $comparer): IALinqCollection;

    /**
     * Difference with another collection using a custom comparer
     */
    public function exceptWith(array $second, callable $comparer): IALinqCollection;

    /**
     * Combine collections using keys from one and values from another
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