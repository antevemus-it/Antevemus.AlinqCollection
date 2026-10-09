<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqCollection;

/**
 * JoiningOperations Trait
 *
 * Provides LINQ-style joining and set operations for collections.
 *
 * Contract since 1.3.0 (forward 015): every key and element comparison uses
 * ALinqCallable::hashKey(), strict and type-aware (`1`, `'1'`, `1.0` and `true` are four
 * different values; arrays compare by value, objects by identity), so `join` on an int key
 * does not match a string key coming from PDO: cast on the caller side (RN-06).
 * `intersect`/`except` are set operations (deduplicated) that keep the keys of a
 * dictionary and reindex a list (RN-02); `concat` is a sequence operation and always
 * returns a reindexed list (RN-17); a comparer may answer bool or `<=>` (RN-09).
 *
 * @version    1.3.0
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait JoiningOperations
{
    /**
     * Join with another collection (Join in LINQ)
     *
     * The inner side is indexed once by key identity (O(n + m) instead of the nested loop
     * of 1.2.0, review 2026-10-08, 3.23). A null key on either side never matches, as in
     * LINQ and SQL.
     */
    public function join(array $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector): IALinqCollection
    {
        $outerKeySelector = ALinqCallable::withKey($outerKeySelector);
        $innerGrouped = self::indexByKey($inner, $innerKeySelector);

        $result = [];
        foreach ($this->items as $outerKeyIndex => $outer) {
            $outerKey = $outerKeySelector($outer, $outerKeyIndex);
            if ($outerKey === null) {
                continue;
            }
            foreach ($innerGrouped[ALinqCallable::hashKey($outerKey)] ?? [] as $innerItem) {
                $result[] = $resultSelector($outer, $innerItem);
            }
        }
        return new self($result);
    }

    /**
     * Left join with another collection (GroupJoin in LINQ)
     *
     * The matched group handed to the result selector is an ALinqCollection (README §3),
     * empty when nothing matches. A null key on either side never matches, as in LINQ and
     * SQL: an outer item with a null key gets an empty group.
     */
    public function groupJoin(array $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector): IALinqCollection
    {
        $outerKeySelector = ALinqCallable::withKey($outerKeySelector);
        $innerGrouped = self::indexByKey($inner, $innerKeySelector);

        $result = [];
        foreach ($this->items as $outerKeyIndex => $outer) {
            $outerKey = $outerKeySelector($outer, $outerKeyIndex);
            $matches = $outerKey === null ? [] : ($innerGrouped[ALinqCallable::hashKey($outerKey)] ?? []);
            $result[] = $resultSelector($outer, new self($matches));
        }

        return new self($result);
    }

    /**
     * Groups the items of $inner by the identity (hashKey) of their selected key; items
     * whose key is null are left out (they can never match).
     *
     * @return array<string, array<int, mixed>>
     */
    private static function indexByKey(array $inner, callable $keySelector): array
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        $grouped = [];
        foreach ($inner as $innerKeyIndex => $innerItem) {
            $innerKey = $keySelector($innerItem, $innerKeyIndex);
            if ($innerKey !== null) {
                $grouped[ALinqCallable::hashKey($innerKey)][] = $innerItem;
            }
        }
        return $grouped;
    }

    /**
     * Concatenate with another collection (Concat in LINQ)
     *
     * A sequence operation: the result is always a reindexed list and nothing is
     * overwritten. Use replace() or unionBy() for a merge by key.
     */
    public function concat(array $second): IALinqCollection
    {
        return new self(array_merge(array_values($this->items), array_values($second)));
    }

    /**
     * Get elements that exist in both collections (Intersect in LINQ)
     *
     * A set operation: deduplicated, strict identity, keys of a dictionary preserved.
     */
    public function intersect(array $second): IALinqCollection
    {
        return $this->setOperation($second, fn($item) => $item, true);
    }

    /**
     * Get elements from this collection that don't exist in second (Except in LINQ)
     *
     * A set operation: deduplicated, strict identity, keys of a dictionary preserved.
     */
    public function except(array $second): IALinqCollection
    {
        return $this->setOperation($second, fn($item) => $item, false);
    }

    /**
     * Intersect with another collection using a custom comparer
     *
     * @param callable $comparer `fn($a, $b): bool|int` (bool: true = equal; int: `<=>`, 0 = equal)
     */
    public function intersectWith(array $second, callable $comparer): IALinqCollection
    {
        $equals = ALinqContract::equality($comparer);
        $result = [];
        foreach ($this->items as $key => $item) {
            foreach ($second as $other) {
                if ($equals($item, $other)) {
                    $result[$key] = $item;
                    break;
                }
            }
        }
        return new self(ALinqContract::shapeLike($this->items, $result));
    }

    /**
     * Difference with another collection using a custom comparer
     *
     * @param callable $comparer `fn($a, $b): bool|int` (bool: true = equal; int: `<=>`, 0 = equal)
     */
    public function exceptWith(array $second, callable $comparer): IALinqCollection
    {
        $equals = ALinqContract::equality($comparer);
        $result = [];
        foreach ($this->items as $key => $item) {
            $found = false;
            foreach ($second as $other) {
                if ($equals($item, $other)) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $result[$key] = $item;
            }
        }
        return new self(ALinqContract::shapeLike($this->items, $result));
    }

    /**
     * Combine collections using keys from one and values from another
     */
    public function combine(array $values): IALinqCollection
    {
        return new self(array_combine($this->items, $values));
    }

    /**
     * Replace elements in the collection with elements from another collection
     */
    public function replace(array $replacements): IALinqCollection
    {
        return new self(array_replace($this->items, $replacements));
    }

    /**
     * Replace elements in the collection recursively with elements from another collection
     */
    public function replaceRecursive(array $replacements): IALinqCollection
    {
        return new self(array_replace_recursive($this->items, $replacements));
    }

    /**
     * Produces the set difference of two sequences based on a key selector function
     *
     * @param array $second The sequence to compare to the first sequence
     * @param callable $keySelector A function to extract the key for each element
     * @return self A collection containing elements from the first sequence not in the second
     */
    public function exceptBy(array $second, callable $keySelector): IALinqCollection
    {
        return $this->setOperation($second, $keySelector, false);
    }

    /**
     * Produces the set intersection of two sequences based on a key selector function
     *
     * @param array $second The sequence to compare to the first sequence
     * @param callable $keySelector A function to extract the key for each element
     * @return self A collection containing shared elements based on the key selector
     */
    public function intersectBy(array $second, callable $keySelector): IALinqCollection
    {
        return $this->setOperation($second, $keySelector, true);
    }

    /**
     * Keeps the items of this collection whose key identity is (intersect) or is not
     * (except) present in $second, each identity at most once, keys of a dictionary
     * preserved.
     */
    private function setOperation(array $second, callable $keySelector, bool $keepWhenPresent): IALinqCollection
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        $secondKeys = [];
        foreach ($second as $key => $item) {
            $secondKeys[ALinqCallable::hashKey($keySelector($item, $key))] = true;
        }

        $result = [];
        $seen = [];
        foreach ($this->items as $key => $item) {
            $identity = ALinqCallable::hashKey($keySelector($item, $key));
            if (isset($seen[$identity]) || isset($secondKeys[$identity]) !== $keepWhenPresent) {
                continue;
            }
            $seen[$identity] = true;
            $result[$key] = $item;
        }
        return new self(ALinqContract::shapeLike($this->items, $result));
    }

    /**
     * Produces the set union of two sequences based on a key selector function
     *
     * The result is a list: first the distinct items of this collection, then the items
     * of $second whose key was not seen yet.
     *
     * @param array $second The sequence to combine with the first sequence
     * @param callable $keySelector A function to extract the key for each element
     * @return self A collection containing unique elements from both sequences
     */
    public function unionBy(array $second, callable $keySelector): IALinqCollection
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        $result = [];
        $keys = [];

        foreach ([$this->items, $second] as $sequence) {
            foreach ($sequence as $key => $item) {
                $identity = ALinqCallable::hashKey($keySelector($item, $key));
                if (!isset($keys[$identity])) {
                    $keys[$identity] = true;
                    $result[] = $item;
                }
            }
        }
        return new self($result);
    }
}
