<?php

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqPropertyAccess;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use Closure;

/**
 * UtilityOperations Trait
 *
 * Provides utility operations for collections
 *
 * @version    0.1.0
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
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
     * Apply a callback to each element in the collection
     */
    public function each(callable $callback): IALinqCollection
    {
        array_walk($this->items, $callback);
        return $this;
    }

    /**
     * Apply a callback recursively to every element in the collection
     */
    public function eachRecursive(callable $callback): IALinqCollection
    {
        array_walk_recursive($this->items, $callback);
        return $this;
    }

    /**
     * Get a random element from the collection
     */
    public function random(int $num = 1)
    {
        if (empty($this->items)) {
            return null;
        }

        $keys = array_rand($this->items, min($num, count($this->items)));

        if ($num === 1) {
            return $this->items[$keys];
        }

        $results = [];
        foreach ((array)$keys as $key) {
            $results[] = $this->items[$key];
        }

        return new self($results);
    }

    /**
     * Extract variables from collection into the current symbol table
     */
    public function extract(int $flags = EXTR_OVERWRITE): int
    {
        return extract($this->items, $flags);
    }

    /**
     * Create a closure that can be used for query composition
     */
    public function createPredicate(string $operator, $value): Closure
    {
        return match($operator) {
            '=' => fn($item) => $item == $value,
            '===' => fn($item) => $item === $value,
            '!=' => fn($item) => $item != $value,
            '!==' => fn($item) => $item !== $value,
            '>' => fn($item) => $item > $value,
            '>=' => fn($item) => $item >= $value,
            '<' => fn($item) => $item < $value,
            '<=' => fn($item) => $item <= $value,
            'in' => fn($item) => in_array($item, (array)$value),
            'contains' => fn($item) => str_contains((string)$item, (string)$value),
            'startsWith' => fn($item) => str_starts_with((string)$item, (string)$value),
            'endsWith' => fn($item) => str_ends_with((string)$item, (string)$value),
            default => throw new \InvalidArgumentException("Unknown operator: $operator")
        };
    }

    /**
     * Create a property selector closure
     */
    public function createPropertySelector(string $property): Closure
    {
        return ALinqPropertyAccess::getPropertyAccessor($property);
    }
}
