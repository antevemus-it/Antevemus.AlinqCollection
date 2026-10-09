<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use InvalidArgumentException;

/**
 * GroupingOperations Trait
 *
 * Provides LINQ-style grouping operations for collections. Every group is an
 * ALinqCollection, as the README promises, so `->select(fn($group, $key) => $group->count())`
 * and nested pipelines work (review 2026-10-08, 2.1).
 *
 * @version    1.3.1
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait GroupingOperations
{
    /**
     * Group elements by key (GroupBy in LINQ)
     *
     * Returns a collection keyed by the group key whose values are ALinqCollection
     * instances (the items of each group, reindexed). The group key must be an int, a
     * string or a BackedEnum (its value is used); null, bool, float, arrays and other
     * objects throw instead of being coerced by PHP (RN-07, forward 015).
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @return IALinqCollection<array-key, IALinqCollection>
     * @throws InvalidArgumentException when the selector returns a key that is not int, string or BackedEnum
     */
    public function groupBy(callable $keySelector): IALinqCollection
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        $groups = [];
        foreach ($this->items as $itemKey => $item) {
            $key = ALinqContract::groupKey($keySelector($item, $itemKey), $itemKey, 'groupBy');
            $groups[$key][] = $item;
        }

        $result = [];
        foreach ($groups as $key => $items) {
            $result[$key] = new self($items);
        }
        return new self($result);
    }
}
