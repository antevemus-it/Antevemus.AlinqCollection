<?php

namespace Antevemus\ALinq\Interfaces;

use Closure;

/**
 * IALinqUtility
 *
 * Interface for utility operations on collections
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqUtility extends IALinqBaseCollection
{
    /**
     * Check if the collection is a list (sequential integer keys starting from 0)
     */
    public function isList(): bool;

    /**
     * Apply a callback to each element in the collection
     */
    public function each(callable $callback): IALinqCollection;

    /**
     * Apply a callback recursively to every element in the collection
     */
    public function eachRecursive(callable $callback): IALinqCollection;

    /**
     * Extract variables from collection into the current symbol table
     */
    public function extract(int $flags = EXTR_OVERWRITE): int;

    /**
     * Create a closure that can be used for query composition
     */
    public function createPredicate(string $operator, $value): Closure;

    /**
     * Create a property selector closure
     */
    public function createPropertySelector(string $property): Closure;
}