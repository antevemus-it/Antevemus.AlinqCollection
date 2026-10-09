<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqOrderable
 *
 * Interface for ordering operations on collections.
 *
 * Contract (1.3.0): every ordering operator reindexes a list and keeps the keys of a
 * dictionary; sorts are stable; each `orderBy*` call orders the whole collection.
 *
 * Composite ordering (1.4.0): the collection returned by `orderBy()`/`orderByDescending()`
 * keeps the ordering pending, and `thenBy()`/`thenByDescending()` refine it with one more
 * key (stable, each key selector called once per item). `thenBy*()` on any other
 * collection, including the result of `orderByNatural`/`orderByCustom`/`orderByKey`,
 * throws `LogicException`.
 *
 * @version    1.4.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqOrderable extends IALinqBaseCollection
{
    /**
     * Sort elements by the selected key (OrderBy in LINQ)
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     */
    public function orderBy(callable $keySelector): IALinqCollection;

    /**
     * Sort elements in descending order (OrderByDescending in LINQ)
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     */
    public function orderByDescending(callable $keySelector): IALinqCollection;

    /**
     * Subsequent ascending ordering (ThenBy in LINQ)
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @param callable|null $comparer `fn($keyA, $keyB): int` (`<=>` convention); `<=>` when null
     * @throws \LogicException when the collection does not come straight from orderBy*()/thenBy*()
     */
    public function thenBy(callable $keySelector, ?callable $comparer = null): IALinqCollection;

    /**
     * Subsequent descending ordering (ThenByDescending in LINQ)
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @param callable|null $comparer `fn($keyA, $keyB): int` (`<=>` convention); `<=>` when null
     * @throws \LogicException when the collection does not come straight from orderBy*()/thenBy*()
     */
    public function thenByDescending(callable $keySelector, ?callable $comparer = null): IALinqCollection;

    /**
     * Sort by natural order (similar to human sorting)
     */
    public function orderByNatural(bool $caseSensitive = true): IALinqCollection;

    /**
     * Sort using a custom comparison function (`<=>` convention)
     */
    public function orderByCustom(callable $comparer): IALinqCollection;

    /**
     * Sort by key
     */
    public function orderByKey(bool $descending = false): IALinqCollection;

    /**
     * Reverse the order of elements (Reverse in LINQ)
     */
    public function reverse(): IALinqCollection;

    /**
     * Randomize the order of items in the collection
     */
    public function shuffle(): IALinqCollection;
}
