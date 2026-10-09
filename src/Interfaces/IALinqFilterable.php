<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqFilterable
 *
 * Interface for filtering operations on collections.
 *
 * Contract (1.3.0): every operator that returns a subset reindexes a list and keeps the
 * keys of a dictionary; `distinct`/`distinctBy` use the strict, type-aware identity of
 * `ALinqCallable::hashKey()` (`1`, `'1'`, `1.0` and `true` are four values, arrays compare
 * by value, objects by identity); callbacks receive `($item, $key)` only when they accept
 * two parameters; an empty result throws where LINQ throws.
 *
 * @version    1.3.1
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqFilterable extends IALinqBaseCollection
{
    /**
     * Filter elements (Where in LINQ)
     *
     * @param callable $predicate `fn($item)` or `fn($item, $key)`
     */
    public function where(callable $predicate): IALinqCollection;

    /**
     * Take first n elements (Take in LINQ)
     *
     * @throws \InvalidArgumentException when $count is negative
     */
    public function take(int $count): IALinqCollection;

    /**
     * Skip first n elements (Skip in LINQ)
     *
     * @throws \InvalidArgumentException when $count is negative
     */
    public function skip(int $count): IALinqCollection;

    /**
     * Get distinct elements by strict identity, or by the key the selector produces (Distinct in LINQ)
     */
    public function distinct(?callable $keySelector = null): IALinqCollection;

    /**
     * Get first element matching predicate, or $default when there is none (FirstOrDefault in LINQ)
     */
    public function firstOrDefault($default = null, ?callable $predicate = null);

    /**
     * Get last element matching predicate, or $default when there is none (LastOrDefault in LINQ)
     */
    public function lastOrDefault($default = null, ?callable $predicate = null);

    /**
     * Get first element matching predicate (First in LINQ)
     *
     * @throws \UnderflowException when the collection is empty or no element matches
     */
    public function first(?callable $predicate = null);

    /**
     * Get last element matching predicate (Last in LINQ)
     *
     * @throws \UnderflowException when the collection is empty or no element matches
     */
    public function last(?callable $predicate = null);

    /**
     * Get the single element matching predicate, or $default when there is none (SingleOrDefault in LINQ)
     *
     * @throws \OverflowException when more than one element matches
     */
    public function singleOrDefault($default = null, ?callable $predicate = null);

    /**
     * Find the first key that matches the predicate
     */
    public function findKey(callable $predicate);

    /**
     * Check if the given key exists in the collection
     */
    public function keyExists(int|string $key): bool;

    /**
     * Chunk the collection into collections of $size items (the last one may be shorter);
     * inside each chunk a list is reindexed and a dictionary keeps its keys
     *
     * @throws \InvalidArgumentException when $size is lower than 1
     */
    public function chunk(int $size): IALinqCollection;

    /**
     * Pad the collection to the specified length, appending $value
     *
     * @throws \InvalidArgumentException when $size is negative
     */
    public function pad(int $size, $value): IALinqCollection;

    /**
     * Get one random element ($num = 1) or a collection of $num random elements;
     * random() of an empty collection throws like first(), random($num > 1) of an empty
     * collection is an empty collection like take()
     *
     * @throws \InvalidArgumentException when $num is lower than 1
     * @throws \UnderflowException when $num is 1 and the collection is empty
     */
    public function random(int $num = 1);

    /**
     * Determines whether the collection contains a specified element
     *
     * Without a comparer the comparison is strict (`===`). A comparer may answer `bool`
     * (`true` = equal) or an int in the `<=>` convention (`0` = equal).
     */
    public function contains($value, ?callable $comparer = null): bool;

    /**
     * Returns distinct elements from a sequence based on a key selector function
     */
    public function distinctBy(callable $keySelector): IALinqCollection;
}
