<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;
use InvalidArgumentException;

/**
 * GroupingOperations Trait
 *
 * Provides LINQ-style grouping operations for collections. Every group is an
 * ALinqCollection, as the README promises, so `->select(fn($group, $key) => $group->count())`
 * and nested pipelines work (review 2026-10-08, 2.1).
 *
 * Since 2.2.0 (forward 047) the grouping runs on ALinqContract::groupItems(), shared with the
 * lazy groupBy() stage and with toLookup(). A group key is a PHP array key: `1` and `'1'` are
 * the same key (unlike the strict identity of joins and set operations).
 *
 * Since 2.4.0 groupBy() takes an IALinqEqualityComparer for the keys: the keys equal under it
 * form one group, named by the first of them seen.
 *
 * @version    2.4.0
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
     * Since 2.4.0 a comparer may decide which keys are equal (for instance
     * `StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true)`): it receives the
     * keys after the rule above (a BackedEnum as its value), hash() buckets them and equals()
     * decides; the group is named by the first key seen (`'São Paulo'` before `'sao paulo'` gives
     * the group `'São Paulo'`). Two keys the comparer tells apart that PHP would turn into the
     * same array key (`1` and `'1'`) throw.
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); PHP array keys when null
     * @return IALinqCollection<array-key, IALinqCollection>
     * @throws InvalidArgumentException when the selector returns a key that is not int, string or BackedEnum
     */
    public function groupBy(callable $keySelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection
    {
        $result = [];
        foreach (ALinqContract::groupItems($this->items, ALinqCallable::withKey($keySelector), null, 'groupBy', $comparer) as $key => $items) {
            $result[$key] = new self($items);
        }
        return new self($result);
    }
}
