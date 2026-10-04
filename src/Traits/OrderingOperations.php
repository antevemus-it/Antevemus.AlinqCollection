<?php

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Interfaces\IALinqCollection;

/**
 * OrderingOperations Trait
 *
 * Provides LINQ-style ordering and sorting operations for collections
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait OrderingOperations
{
    /**
     * Sort elements (OrderBy in LINQ)
     */
    public function orderBy(callable $keySelector): IALinqCollection
    {
        $items = $this->items;
        usort($items, fn($a, $b) => $keySelector($a) <=> $keySelector($b));
        return new self($items);
    }

    /**
     * Sort elements in descending order (OrderByDescending in LINQ)
     */
    public function orderByDescending(callable $keySelector): IALinqCollection
    {
        $items = $this->items;
        usort($items, fn($a, $b) => $keySelector($b) <=> $keySelector($a));
        return new self($items);
    }

    /**
     * Sort by natural order (similar to human sorting)
     */
    public function orderByNatural(bool $caseSensitive = true): IALinqCollection
    {
        $items = $this->items;
        if ($caseSensitive) {
            natsort($items);
        } else {
            natcasesort($items);
        }
        return new self($items);
    }

    /**
     * Sort using a custom comparison function
     */
    public function orderByCustom(callable $comparer): IALinqCollection
    {
        $items = $this->items;
        usort($items, $comparer);
        return new self($items);
    }

    /**
     * Sort by key
     */
    public function orderByKey(bool $descending = false): IALinqCollection
    {
        $items = $this->items;
        if ($descending) {
            krsort($items);
        } else {
            ksort($items);
        }
        return new self($items);
    }

    /**
     * Reverse the order of elements (Reverse in LINQ)
     */
    public function reverse(): IALinqCollection
    {
        return new self(array_reverse($this->items));
    }
}
