<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

use Closure;

/**
 * IALinqUtility
 *
 * Interface for utility operations on collections.
 *
 * Contract (1.3.0): `each`/`eachRecursive` iterate over a copy and never mutate the
 * collection (transformation is `select`). `extract()`, deprecated in 1.3.0, was removed in
 * 2.0.0: a method cannot export variables into the scope of its caller.
 *
 * @version    2.0.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqUtility extends IALinqBaseCollection
{
    /**
     * Check if the collection is a list (sequential integer keys starting from 0)
     */
    public function isList(): bool;

    /**
     * Apply a callback to each element in the collection, without mutating it
     *
     * @param callable $callback `fn($item)` or `fn($item, $key)`
     */
    public function each(callable $callback): IALinqCollection;

    /**
     * Apply a callback recursively to every leaf element in the collection, without mutating it
     *
     * @param callable $callback `fn($item)` or `fn($item, $key)`
     */
    public function eachRecursive(callable $callback): IALinqCollection;

    /**
     * Create a closure that can be used for query composition
     *
     * Same operators and semantics as ALinqQueryBuilder::where(), including the `null` rule.
     *
     * @throws \InvalidArgumentException for an unknown operator
     */
    public function createPredicate(string $operator, $value): Closure;

    /**
     * Create a property selector closure (the accessor shared with ALinqQueryBuilder and
     * ASpecification: `Antevemus\ADeepPropertyAccess\PropertyAccess`, since 2.0.0)
     */
    public function createPropertySelector(string $property): Closure;
}
