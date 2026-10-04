<?php

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqOrderable
 *
 * Interface for ordering operations on collections
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqOrderable extends IALinqBaseCollection
{
    /**
     * Sort elements (OrderBy in LINQ)
     */
    public function orderBy(callable $keySelector): IALinqCollection;

    /**
     * Sort elements in descending order (OrderByDescending in LINQ)
     */
    public function orderByDescending(callable $keySelector): IALinqCollection;

    /**
     * Sort by natural order (similar to human sorting)
     */
    public function orderByNatural(bool $caseSensitive = true): IALinqCollection;

    /**
     * Sort using a custom comparison function
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
