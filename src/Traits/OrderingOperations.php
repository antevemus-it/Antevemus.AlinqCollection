<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use LogicException;

/**
 * OrderingOperations Trait
 *
 * Provides LINQ-style ordering and sorting operations for collections.
 * Key policy (RN-02, forward 015): every ordering reindexes a list and keeps the keys of
 * a dictionary.
 *
 * Composite ordering (1.4.0, forward 021, D4 a): orderBy()/orderByDescending() return the
 * sorted collection with the ordering still pending on that instance (the source items and
 * the precomputed keys of every criterion, never global state); thenBy()/thenByDescending()
 * append a criterion and sort the same source again with the composite, stable comparison
 * (ALinqContract::orderPositions()), so every key selector runs once per item. Any other
 * operation returns a collection without a pending ordering, and thenBy*() on it throws
 * LogicException.
 *
 * @version    1.4.0
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait OrderingOperations
{
    /**
     * Sort elements by the selected key, stable (OrderBy in LINQ); thenBy()/thenByDescending()
     * may follow
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     */
    public function orderBy(callable $keySelector): IALinqCollection
    {
        return self::ordered($this->items, [self::orderingCriterion($this->items, $keySelector, 1, null)]);
    }

    /**
     * Sort elements in descending order, stable (OrderByDescending in LINQ);
     * thenBy()/thenByDescending() may follow
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     */
    public function orderByDescending(callable $keySelector): IALinqCollection
    {
        return self::ordered($this->items, [self::orderingCriterion($this->items, $keySelector, -1, null)]);
    }

    /**
     * Subsequent ascending ordering (ThenBy in LINQ): items that tie on every previous
     * criterion are ordered by this key; a full tie keeps the original order
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)` (the key of the source, before ordering)
     * @param callable|null $comparer `fn($keyA, $keyB): int` in the `<=>` convention; `<=>` when null
     * @throws LogicException when the collection does not come straight from orderBy*()/thenBy*()
     */
    public function thenBy(callable $keySelector, ?callable $comparer = null): IALinqCollection
    {
        return $this->appendOrdering('thenBy', $keySelector, $comparer, 1);
    }

    /**
     * Subsequent descending ordering (ThenByDescending in LINQ)
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)` (the key of the source, before ordering)
     * @param callable|null $comparer `fn($keyA, $keyB): int` in the `<=>` convention; `<=>` when null
     * @throws LogicException when the collection does not come straight from orderBy*()/thenBy*()
     */
    public function thenByDescending(callable $keySelector, ?callable $comparer = null): IALinqCollection
    {
        return $this->appendOrdering('thenByDescending', $keySelector, $comparer, -1);
    }

    /**
     * Appends a criterion to the ordering pending on this instance and sorts its source again.
     *
     * @throws LogicException when no ordering is pending
     */
    private function appendOrdering(string $operation, callable $keySelector, ?callable $comparer, int $direction): IALinqCollection
    {
        if ($this->pendingOrdering === null) {
            throw new LogicException(sprintf('%s() requires a preceding orderBy().', $operation));
        }

        [$source, $criteria] = $this->pendingOrdering;
        $criteria[] = self::orderingCriterion($source, $keySelector, $direction, $comparer);

        return self::ordered($source, $criteria);
    }

    /**
     * One ordering criterion with its keys computed once per item, by position
     * (Schwartzian transform, review 2026-10-08, 1.15).
     *
     * @return array{0: list<mixed>, 1: int, 2: callable|null}
     */
    private static function orderingCriterion(array $source, callable $keySelector, int $direction, ?callable $comparer): array
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        $keys = [];
        foreach ($source as $key => $item) {
            $keys[] = $keySelector($item, $key);
        }
        return [$keys, $direction, $comparer];
    }

    /**
     * Sorts the source by the criteria (stable) and returns the result with the ordering
     * pending on it. Key policy: a list comes out reindexed, a dictionary keeps its keys
     * (decision 1a).
     */
    private static function ordered(array $source, array $criteria): self
    {
        $sourceKeys = array_keys($source);
        $values = array_values($source);
        $sorted = [];
        foreach (ALinqContract::orderPositions(count($values), $criteria) as $position) {
            $sorted[$sourceKeys[$position]] = $values[$position];
        }

        $result = new self(ALinqContract::shapeLike($source, $sorted));
        $result->pendingOrdering = [$source, $criteria];
        return $result;
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
