<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Interfaces\IALinqCollection;

/**
 * FilteringOperations Trait
 *
 * Provides filtering and element selection operations for collections.
 * Callbacks follow ALinqCallable::withKey(): `($item, $key)` when they accept two
 * parameters, the item alone otherwise (review 2026-10-08, decision 4a).
 *
 * @version    1.2.0
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait FilteringOperations
{
    /**
     * Filter elements (Where in LINQ)
     * Reindexes numeric arrays (lists), preserves keys for associative arrays
     */
    public function where(callable $predicate): IALinqCollection
    {
        $filtered = array_filter($this->items, ALinqCallable::withKey($predicate), ARRAY_FILTER_USE_BOTH);

        // Reindex only if original array was a list (sequential numeric keys)
        if (array_is_list($this->items)) {
            return new self(array_values($filtered));
        }

        return new self($filtered);
    }

    /**
     * Take first n elements (Take in LINQ)
     */
    public function take(int $count): IALinqCollection
    {
        return new self(array_slice($this->items, 0, $count));
    }

    /**
     * Skip first n elements (Skip in LINQ)
     */
    public function skip(int $count): IALinqCollection
    {
        return new self(array_slice($this->items, $count));
    }

    /**
     * Get distinct elements (Distinct in LINQ)
     */
    public function distinct(?callable $keySelector = null): IALinqCollection
    {
        // Identity by ALinqCallable::hashKey(): strict and type-aware for scalars, by value
        // for arrays, by identity for objects, O(1) per item. Before, the no-selector form
        // used array_unique() (string comparison: arrays collapsed with a warning, objects
        // threw, 1/'1'/true/1.0 became one) and the selector form was an O(n²) in_array()
        // (review 2026-10-08, 2.3 and 2.11).
        $selector = $keySelector === null ? null : ALinqCallable::withKey($keySelector);
        $result = [];
        $seen = [];
        foreach ($this->items as $key => $item) {
            $identity = ALinqCallable::hashKey($selector === null ? $item : $selector($item, $key));
            if (!isset($seen[$identity])) {
                $seen[$identity] = true;
                $result[] = $item;
            }
        }
        return new self($result);
    }

    /**
     * Get first element matching predicate (FirstOrDefault in LINQ)
     * Leverages PHP 8.4's array_find function
     */
    public function firstOrDefault($default = null, ?callable $predicate = null)
    {
        if ($predicate === null) {
            return empty($this->items) ? $default : reset($this->items);
        }

        $result = array_find($this->items, ALinqCallable::withKey($predicate));
        return $result !== null ? $result : $default;
    }

    /**
     * Get last element matching predicate (LastOrDefault in LINQ)
     */
    public function lastOrDefault($default = null, ?callable $predicate = null)
    {
        if ($predicate === null) {
            return empty($this->items) ? $default : end($this->items);
        }

        // preserve_keys: the predicate receives the real key. Without it a list was
        // reindexed backwards and `fn($v, $k) => $k !== 0` on [1..5] answered 4 instead of 5
        // (review 2026-10-08, 2.5).
        $reversedItems = array_reverse($this->items, true);
        $result = array_find($reversedItems, ALinqCallable::withKey($predicate));
        return $result !== null ? $result : $default;
    }

    /**
     * Get first element matching predicate (First in LINQ)
     */
    public function first(?callable $predicate = null)
    {
        return $this->firstOrDefault(null, $predicate);
    }

    /**
     * Get last element matching predicate (Last in LINQ)
     */
    public function last(?callable $predicate = null)
    {
        return $this->lastOrDefault(null, $predicate);
    }

    /**
     * Get single element matching predicate or null if none (SingleOrDefault in LINQ)
     */
    public function singleOrDefault($default = null, ?callable $predicate = null)
    {
        $filtered = $predicate === null
            ? $this->items
            : array_filter($this->items, ALinqCallable::withKey($predicate), ARRAY_FILTER_USE_BOTH);

        if (count($filtered) > 1) {
            throw new \RuntimeException("Sequence contains more than one matching element");
        }

        return count($filtered) === 0 ? $default : reset($filtered);
    }

    /**
     * Find the first key that matches the predicate
     * Leverages PHP 8.4's array_find_key function
     */
    public function findKey(callable $predicate)
    {
        return array_find_key($this->items, ALinqCallable::withKey($predicate));
    }

    /**
     * Check if the given key exists in the collection.
     *
     * @param int|string $key The key to check for existence.
     * @return bool Returns true if the key exists, false otherwise.
     */
    public function keyExists(int|string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Chunk the collection into smaller collections
     */
    public function chunk(int $size): IALinqCollection
    {
        return new self(array_chunk($this->items, $size));
    }

    /**
     * Pad the collection to the specified length
     */
    public function pad(int $size, $value): IALinqCollection
    {
        return new self(array_pad($this->items, $size, $value));
    }

    /**
     * Randomize the order of items in the collection
     */
    public function shuffle(): IALinqCollection
    {
        $items = $this->items;
        shuffle($items);
        return new self($items);
    }

    /**
     * Determines whether the collection contains a specified element
     *
     * @param mixed $value The value to locate in the collection
     * @param callable|null $comparer Optional custom comparison function
     * @return bool True if the collection contains the specified element, otherwise false
     */
    public function contains($value, ?callable $comparer = null): bool
    {
        if ($comparer !== null) {
            return array_any($this->items, fn($item) => $comparer($item, $value) === 0);
        }
        return in_array($value, $this->items, true);
    }

    /**
     * Returns distinct elements from a sequence based on a key selector function
     *
     * @param callable $keySelector A function to extract the key for each element
     * @return self A collection of distinct elements based on the key selector
     */
    public function distinctBy(callable $keySelector): IALinqCollection
    {
        $result = [];
        $keys = [];
        $selector = ALinqCallable::withKey($keySelector);

        foreach ($this->items as $itemKey => $item) {
            $key = $selector($item, $itemKey);
            $keyString = is_object($key) ? spl_object_hash($key) : (string)$key;

            if (!isset($keys[$keyString])) {
                $keys[$keyString] = true;
                $result[] = $item;
            }
        }
        return new self($result);
    }
}
