<?php

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Interfaces\IALinqCollection;

/**
 * JoiningOperations Trait
 *
 * Provides LINQ-style joining operations for collections
 *
 * @version    0.1
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
     */
    public function join(array $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector): IALinqCollection
    {
        $result = [];
        foreach ($this->items as $outer) {
            $outerKey = $outerKeySelector($outer);
            foreach ($inner as $innerItem) {
                if ($outerKey === $innerKeySelector($innerItem)) {
                    $result[] = $resultSelector($outer, $innerItem);
                }
            }
        }
        return new self($result);
    }

    /**
     * Left join with another collection (GroupJoin in LINQ)
     */
    public function groupJoin(array $inner, callable $outerKeySelector, callable $innerKeySelector, callable $resultSelector): IALinqCollection
    {
        $result = [];
        $innerGrouped = [];

        // Group inner elements by key
        foreach ($inner as $innerItem) {
            $key = $innerKeySelector($innerItem);
            if (!isset($innerGrouped[$key])) {
                $innerGrouped[$key] = [];
            }
            $innerGrouped[$key][] = $innerItem;
        }

        // Join outer with grouped inner
        foreach ($this->items as $outer) {
            $key = $outerKeySelector($outer);
            $matchingInner = $innerGrouped[$key] ?? [];
            $result[] = $resultSelector($outer, new self($matchingInner));
        }

        return new self($result);
    }

    /**
     * Concatenate with another collection (Concat in LINQ)
     */
    public function concat(array $second): IALinqCollection
    {
        return new self(array_merge($this->items, $second));
    }

    /**
     * Get elements that exist in both collections (Intersect in LINQ)
     */
    public function intersect(array $second): IALinqCollection
    {
        // Remove duplicados para comportamento de conjunto real
        return new self(array_values(array_unique(array_intersect($this->items, $second))));
    }

    /**
     * Get elements from this collection that don't exist in second (Except in LINQ)
     */
    public function except(array $second): IALinqCollection
    {
        return new self(array_values(array_diff($this->items, $second)));
    }

    /**
     * Intersect with another collection using a custom comparer
     */
    public function intersectWith(array $second, callable $comparer): IALinqCollection
    {
        return new self(array_values(array_uintersect($this->items, $second, $comparer)));
    }

    /**
     * Difference with another collection using a custom comparer
     */
    public function exceptWith(array $second, callable $comparer): IALinqCollection
    {
        return new self(array_values(array_udiff($this->items, $second, $comparer)));
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
        // Extract keys from the second sequence for comparison
        $secondKeys = [];
        foreach ($second as $item) {
            $key = $keySelector($item);
            $keyString = is_object($key) ? spl_object_hash($key) : (string)$key;
            $secondKeys[$keyString] = true;
        }

        // Select elements from the first sequence whose keys are not in the second
        $result = [];
        foreach ($this->items as $item) {
            $key = $keySelector($item);
            $keyString = is_object($key) ? spl_object_hash($key) : (string)$key;

            if (!isset($secondKeys[$keyString])) {
                $result[] = $item;
            }
        }
        return new self($result);
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
        // Extract keys from the second sequence for comparison
        $secondKeys = [];
        foreach ($second as $item) {
            $key = $keySelector($item);
            $keyString = is_object($key) ? spl_object_hash($key) : (string)$key;
            $secondKeys[$keyString] = true;
        }

        // Select elements from the first sequence whose keys are in the second
        $result = [];
        foreach ($this->items as $item) {
            $key = $keySelector($item);
            $keyString = is_object($key) ? spl_object_hash($key) : (string)$key;

            if (isset($secondKeys[$keyString])) {
                $result[] = $item;
            }
        }
        return new self($result);
    }

    /**
     * Produces the set union of two sequences based on a key selector function
     *
     * @param array $second The sequence to combine with the first sequence
     * @param callable $keySelector A function to extract the key for each element
     * @return self A collection containing unique elements from both sequences
     */
    public function unionBy(array $second, callable $keySelector): IALinqCollection
    {
        $result = [];
        $keys = [];

        // Add elements from the first sequence
        foreach ($this->items as $item) {
            $key = $keySelector($item);
            $keyString = is_object($key) ? spl_object_hash($key) : (string)$key;

            if (!isset($keys[$keyString])) {
                $keys[$keyString] = true;
                $result[] = $item;
            }
        }

        // Add elements from the second sequence
        foreach ($second as $item) {
            $key = $keySelector($item);
            $keyString = is_object($key) ? spl_object_hash($key) : (string)$key;

            if (!isset($keys[$keyString])) {
                $keys[$keyString] = true;
                $result[] = $item;
            }
        }
        return new self($result);
    }
}
