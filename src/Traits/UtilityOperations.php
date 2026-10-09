<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\ALinqQueryBuilder;
use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Helpers\ALinqPropertyAccess;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use Closure;
use InvalidArgumentException;

/**
 * UtilityOperations Trait
 *
 * Provides utility operations for collections.
 *
 * Contract since 1.3.0 (forward 015): each()/eachRecursive() never mutate the collection,
 * even with a by-reference callback (RN-25); extract() is deprecated because a method
 * cannot export into its caller's scope (RN-26); random() keeps the keys of a dictionary
 * and rejects a count below 1 (RN-02, RN-13).
 *
 * @version    1.3.1
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait UtilityOperations
{
    /**
     * Check if the collection is a list (sequential integer keys starting from 0)
     */
    public function isList(): bool
    {
        return array_is_list($this->items);
    }

    /**
     * Apply a callback to each element in the collection, for its side effects
     *
     * The callback receives `($item, $key)` when it accepts two parameters. The collection
     * is never mutated: a by-reference parameter changes a copy. Use select() to transform.
     */
    public function each(callable $callback): IALinqCollection
    {
        $callback = ALinqCallable::withKey($callback);
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }
        return $this;
    }

    /**
     * Apply a callback recursively to every leaf of the collection, for its side effects
     *
     * Same rules as each(): `($item, $key)` by arity, never mutates the collection.
     */
    public function eachRecursive(callable $callback): IALinqCollection
    {
        $callback = ALinqCallable::withKey($callback);
        $copy = $this->items;
        array_walk_recursive($copy, static function ($item, $key) use ($callback): void {
            $callback($item, $key);
        });
        return $this;
    }

    /**
     * Get one random element, or a collection of $num random elements (keys of a
     * dictionary preserved)
     *
     * random() of an empty collection throws, like first() of a shuffled sequence;
     * random($num > 1) of an empty collection is an empty collection, like take($num).
     *
     * @throws InvalidArgumentException when $num is below 1
     * @throws \UnderflowException when $num is 1 and the collection is empty
     */
    public function random(int $num = 1)
    {
        ALinqContract::requireAtLeast($num, 1, 'num', 'random');

        if ($this->items === []) {
            if ($num === 1) {
                throw new \UnderflowException('Cannot take random() of an empty collection.');
            }
            return new self([]);
        }

        $keys = array_rand($this->items, min($num, count($this->items)));

        if ($num === 1) {
            return $this->items[$keys];
        }

        $results = [];
        foreach ((array)$keys as $key) {
            $results[$key] = $this->items[$key];
        }

        return new self(ALinqContract::shapeLike($this->items, $results));
    }

    /**
     * Extract variables from collection into the current symbol table
     *
     * @deprecated since 1.3.0, removed in 2.0: extract() acts on the symbol table of this
     *             method, never on the caller's scope, so nothing is exported. The count of
     *             variables that would have been extracted is still returned.
     */
    public function extract(int $flags = EXTR_OVERWRITE): int
    {
        trigger_error(
            'ALinqCollection::extract() is deprecated since 1.3.0 and will be removed in 2.0: a method cannot export variables into the scope of its caller.',
            E_USER_DEPRECATED
        );
        return extract($this->items, $flags);
    }

    /**
     * Create a closure that can be used for query composition
     *
     * Delegates to ALinqQueryBuilder::operatorPredicate(), so the operator set and the
     * semantics are the same as in ALinqQueryBuilder::where() (including between, notIn,
     * isNull and case-insensitive operator names).
     *
     * @param string $operator One of ALinqQueryBuilder::supportedOperators()
     * @param mixed $value Comparison value (ignored for isNull/isNotNull)
     * @throws \InvalidArgumentException for an unknown operator
     */
    public function createPredicate(string $operator, mixed $value = null): Closure
    {
        return ALinqQueryBuilder::operatorPredicate($operator, $value);
    }

    /**
     * Create a property selector closure
     */
    public function createPropertySelector(string $property): Closure
    {
        return ALinqPropertyAccess::getPropertyAccessor($property);
    }
}
