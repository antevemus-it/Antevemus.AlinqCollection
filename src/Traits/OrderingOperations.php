<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqCollection;

/**
 * OrderingOperations Trait
 *
 * Provides LINQ-style ordering and sorting operations for collections.
 * Key policy (RN-02, forward 015): every ordering reindexes a list and keeps the keys of
 * a dictionary.
 *
 * @version    1.3.0
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
        return new self($this->sortByKey($keySelector, 1));
    }

    /**
     * Sort elements in descending order (OrderByDescending in LINQ)
     */
    public function orderByDescending(callable $keySelector): IALinqCollection
    {
        return new self($this->sortByKey($keySelector, -1));
    }

    /**
     * Stable sort by a precomputed key (each selector called once per item instead of twice
     * per comparison, review 2026-10-08, 1.15). Key policy: a list comes out reindexed, a
     * dictionary keeps its keys (decision 1a).
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @param int $direction 1 ascending, -1 descending
     * @return array
     */
    private function sortByKey(callable $keySelector, int $direction): array
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        $sortKeys = [];
        foreach ($this->items as $key => $item) {
            $sortKeys[$key] = $keySelector($item, $key);
        }

        $items = $this->items;
        uksort($items, static fn($a, $b) => $direction * ($sortKeys[$a] <=> $sortKeys[$b]));

        return ALinqContract::shapeLike($this->items, $items);
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
        return new self(ALinqContract::shapeLike($this->items, $items));
    }

    /**
     * Sort using a custom comparison function `fn($a, $b): int`
     */
    public function orderByCustom(callable $comparer): IALinqCollection
    {
        $items = $this->items;
        uasort($items, $comparer);
        return new self(ALinqContract::shapeLike($this->items, $items));
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
        return new self(ALinqContract::shapeLike($this->items, $items));
    }

    /**
     * Reverse the order of elements (Reverse in LINQ)
     */
    public function reverse(): IALinqCollection
    {
        return new self(ALinqContract::shapeLike($this->items, array_reverse($this->items, true)));
    }
}
