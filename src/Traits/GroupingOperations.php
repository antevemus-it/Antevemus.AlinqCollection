<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Interfaces\IALinqCollection;

/**
 * GroupingOperations Trait
 *
 * Provides LINQ-style grouping operations for collections. Every group is an
 * ALinqCollection, as the README promises, so `->select(fn($group, $key) => $group->count())`
 * and nested pipelines work (review 2026-10-08, 2.1).
 *
 * @version    1.2.0
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait GroupingOperations
{
    /**
     * Group elements by key (GroupBy in LINQ)
     *
     * Returns a collection keyed by the group key whose values are ALinqCollection
     * instances (the items of each group, reindexed). Before 1.2.0 the groups were plain
     * arrays and the documented `->select(fn(ALinqCollection $group, string $key) => ...)`
     * threw a TypeError.
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @return IALinqCollection<array-key, IALinqCollection>
     */
    public function groupBy(callable $keySelector): IALinqCollection
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        $groups = [];
        foreach ($this->items as $itemKey => $item) {
            $key = $keySelector($item, $itemKey);
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $item;
        }

        $result = [];
        foreach ($groups as $key => $items) {
            $result[$key] = new self($items);
        }
        return new self($result);
    }
}
