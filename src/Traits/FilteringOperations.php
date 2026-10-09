<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use InvalidArgumentException;
use OverflowException;
use UnderflowException;

/**
 * FilteringOperations Trait
 *
 * Provides filtering and element selection operations for collections.
 * Callbacks follow ALinqCallable::withKey(): `($item, $key)` when they accept two
 * parameters, the item alone otherwise (review 2026-10-08, decision 4a).
 *
 * Contract since 1.3.0 (forward 015): a list comes out reindexed and a dictionary keeps
 * its keys (RN-02); first()/last() throw UnderflowException when nothing matches and the
 * *OrDefault() variants return the default (RN-11); negative counts throw
 * InvalidArgumentException instead of slicing from the tail (RN-13); element identity is
 * ALinqCallable::hashKey() (RN-06); a comparer may answer bool or `<=>` (RN-09).
 *
 * @version    1.3.0
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

        return new self(ALinqContract::shapeLike($this->items, $filtered));
    }

    /**
     * Take first n elements (Take in LINQ)
     *
     * @throws InvalidArgumentException when $count is negative
     */
    public function take(int $count): IALinqCollection
    {
        ALinqContract::requireAtLeast($count, 0, 'count', 'take');

        return new self(ALinqContract::shapeLike($this->items, array_slice($this->items, 0, $count, true)));
    }

    /**
     * Skip first n elements (Skip in LINQ)
     *
     * @throws InvalidArgumentException when $count is negative
     */
    public function skip(int $count): IALinqCollection
    {
        ALinqContract::requireAtLeast($count, 0, 'count', 'skip');

        return new self(ALinqContract::shapeLike($this->items, array_slice($this->items, $count, null, true)));
    }

    /**
     * Get distinct elements (Distinct in LINQ)
     *
     * Identity is ALinqCallable::hashKey(): strict and type-aware for scalars, by value for
     * arrays, by identity for objects. The first occurrence wins; a dictionary keeps the key
     * of that occurrence.
     */
    public function distinct(?callable $keySelector = null): IALinqCollection
    {
        $selector = $keySelector === null ? null : ALinqCallable::withKey($keySelector);
        $result = [];
        $seen = [];
        foreach ($this->items as $key => $item) {
            $identity = ALinqCallable::hashKey($selector === null ? $item : $selector($item, $key));
            if (!isset($seen[$identity])) {
                $seen[$identity] = true;
                $result[$key] = $item;
            }
        }
        return new self(ALinqContract::shapeLike($this->items, $result));
    }

    /**
     * Get first element matching predicate or the default (FirstOrDefault in LINQ)
     *
     * A stored null is an element like any other: only the absence of a match returns
     * the default (RN-05).
     */
    public function firstOrDefault($default = null, ?callable $predicate = null)
    {
        $key = $this->firstKey($predicate);
        return $key === null ? $default : $this->items[$key];
    }

    /**
     * Get last element matching predicate or the default (LastOrDefault in LINQ)
     */
    public function lastOrDefault($default = null, ?callable $predicate = null)
    {
        $key = $this->lastKey($predicate);
        return $key === null ? $default : $this->items[$key];
    }

    /**
     * Get first element matching predicate (First in LINQ)
     *
     * @throws UnderflowException when the collection is empty or no element matches
     */
    public function first(?callable $predicate = null)
    {
        $key = $this->firstKey($predicate);
        if ($key === null) {
            throw new UnderflowException(
                $predicate === null
                    ? 'Cannot take first() of an empty collection.'
                    : 'first(): no element matches the predicate.'
            );
        }
        return $this->items[$key];
    }

    /**
     * Get last element matching predicate (Last in LINQ)
     *
     * @throws UnderflowException when the collection is empty or no element matches
     */
    public function last(?callable $predicate = null)
    {
        $key = $this->lastKey($predicate);
        if ($key === null) {
            throw new UnderflowException(
                $predicate === null
                    ? 'Cannot take last() of an empty collection.'
                    : 'last(): no element matches the predicate.'
            );
        }
        return $this->items[$key];
    }

    /**
     * Key of the first matching element, null when there is none.
     */
    private function firstKey(?callable $predicate): int|string|null
    {
        if ($predicate === null) {
            return array_key_first($this->items);
        }

        return array_find_key($this->items, ALinqCallable::withKey($predicate));
    }

    /**
     * Key of the last matching element, null when there is none. The predicate sees the
     * real key (review 2026-10-08, 2.5).
     */
    private function lastKey(?callable $predicate): int|string|null
    {
        if ($predicate === null) {
            return array_key_last($this->items);
        }

        return array_find_key(array_reverse($this->items, true), ALinqCallable::withKey($predicate));
    }

    /**
     * Get single element matching predicate or the default if none (SingleOrDefault in LINQ)
     *
     * @throws OverflowException when more than one element matches
     */
    public function singleOrDefault($default = null, ?callable $predicate = null)
    {
        $filtered = $predicate === null
            ? $this->items
            : array_filter($this->items, ALinqCallable::withKey($predicate), ARRAY_FILTER_USE_BOTH);

        if (count($filtered) > 1) {
            throw new OverflowException('Collection contains more than one matching element.');
        }

        return count($filtered) === 0 ? $default : $filtered[array_key_first($filtered)];
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
     * Chunk the collection into collections of at most $size items (Chunk in LINQ)
     *
     * The result is a list of ALinqCollection chunks, so each chunk stays queryable
     * (`chunk(100)->select(fn($c) => $c->sum())`); use `$chunk->toArray()` when a native
     * array is needed (no copy). Inside each chunk a list is reindexed and a dictionary
     * keeps its keys.
     *
     * @throws InvalidArgumentException when $size is below 1
     */
    public function chunk(int $size): IALinqCollection
    {
        ALinqContract::requireAtLeast($size, 1, 'size', 'chunk');

        $chunks = [];
        foreach (array_chunk($this->items, $size, !array_is_list($this->items)) as $chunk) {
            $chunks[] = new self($chunk);
        }
        return new self($chunks);
    }

    /**
     * Pad the collection to the specified length
     *
     * A dictionary keeps its keys and the padding is appended.
     *
     * @throws InvalidArgumentException when $size is negative
     */
    public function pad(int $size, $value): IALinqCollection
    {
        ALinqContract::requireAtLeast($size, 0, 'size', 'pad');

        $items = $this->items;
        for ($count = count($items); $count < $size; $count++) {
            $items[] = $value;
        }
        return new self($items);
    }

    /**
     * Randomize the order of items in the collection
     *
     * A dictionary keeps each item under its key; only the order changes.
     */
    public function shuffle(): IALinqCollection
    {
        if (array_is_list($this->items)) {
            $items = $this->items;
            shuffle($items);
            return new self($items);
        }

        $keys = array_keys($this->items);
        shuffle($keys);
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->items[$key];
        }
        return new self($items);
    }

    /**
     * Determines whether the collection contains a specified element
     *
     * @param mixed $value The value to locate in the collection
     * @param callable|null $comparer Optional `fn($item, $value): bool|int`; a bool answer is
     *                                taken as is, an int answer follows `<=>` (0 = equal)
     * @return bool True if the collection contains the specified element, otherwise false
     */
    public function contains($value, ?callable $comparer = null): bool
    {
        if ($comparer !== null) {
            $equals = ALinqContract::equality($comparer);
            return array_any($this->items, fn($item) => $equals($item, $value));
        }
        return in_array($value, $this->items, true);
    }

    /**
     * Returns distinct elements from a sequence based on a key selector function
     *
     * Same identity rule as distinct() (ALinqCallable::hashKey()).
     *
     * @param callable $keySelector A function to extract the key for each element
     * @return self A collection of distinct elements based on the key selector
     */
    public function distinctBy(callable $keySelector): IALinqCollection
    {
        return $this->distinct($keySelector);
    }
}
