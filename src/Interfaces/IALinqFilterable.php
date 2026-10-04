<?php

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqFilterable
 *
 * Interface for filtering operations on collections
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqFilterable extends IALinqBaseCollection
{
    /**
     * Filter elements (Where in LINQ)
     */
    public function where(callable $predicate): IALinqCollection;

    /**
     * Take first n elements (Take in LINQ)
     */
    public function take(int $count): IALinqCollection;

    /**
     * Skip first n elements (Skip in LINQ)
     */
    public function skip(int $count): IALinqCollection;

    /**
     * Get distinct elements (Distinct in LINQ)
     */
    public function distinct(?callable $keySelector = null): IALinqCollection;

    /**
     * Get first element matching predicate (FirstOrDefault in LINQ)
     */
    public function firstOrDefault($default = null, ?callable $predicate = null);

    /**
     * Get last element matching predicate (LastOrDefault in LINQ)
     */
    public function lastOrDefault($default = null, ?callable $predicate = null);

    /**
     * Get first element matching predicate (First in LINQ)
     */
    public function first(?callable $predicate = null);

    /**
     * Get last element matching predicate (Last in LINQ)
     */
    public function last(?callable $predicate = null);

    /**
     * Get single element matching predicate or null if none (SingleOrDefault in LINQ)
     */
    public function singleOrDefault($default = null, ?callable $predicate = null);

    /**
     * Find the first key that matches the predicate
     */
    public function findKey(callable $predicate);

    /**
     * Check if the given key exists in the collection
     */
    public function keyExists(int|string $key): bool;

    /**
     * Chunk the collection into smaller collections
     */
    public function chunk(int $size): IALinqCollection;

    /**
     * Pad the collection to the specified length
     */
    public function pad(int $size, $value): IALinqCollection;

    /**
     * Get a random element from the collection
     */
    public function random(int $num = 1);

    /**
     * Determines whether the collection contains a specified element
     */
    public function contains($value, ?callable $comparer = null): bool;

    /**
     * Returns distinct elements from a sequence based on a key selector function
     */
    public function distinctBy(callable $keySelector): IALinqCollection;
}