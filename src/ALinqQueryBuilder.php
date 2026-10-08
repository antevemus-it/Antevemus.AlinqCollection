<?php

namespace Antevemus\ALinq;

use Antevemus\ALinq\Helpers\ALinqPropertyAccess;
use Closure;

/**
 * ALinqQueryBuilder
 *
 * A query builder for more complex filtering operations
 *
 * @version    0.1.0
 * @package    antevemus
 * @subpackage alinq
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
class ALinqQueryBuilder
{
    private array $conditions = [];
    private string $mode = 'and';

    /**
     * Create a new query with the specified mode
     */
    public static function create(string $mode = 'and'): self
    {
        $instance = new self();
        $instance->mode = strtolower($mode);
        return $instance;
    }

    /**
     * Add a condition using property and operator
     */
    public function where(string $property, string $operator, $value): self
    {
        $accessor = ALinqPropertyAccess::getPropertyAccessor($property);

        $condition = match($operator) {
            '=', '==' => fn($item) => $accessor($item) == $value,
            '===' => fn($item) => $accessor($item) === $value,
            '!=', '<>' => fn($item) => $accessor($item) != $value,
            '!==' => fn($item) => $accessor($item) !== $value,
            '>' => fn($item) => $accessor($item) > $value,
            '>=' => fn($item) => $accessor($item) >= $value,
            '<' => fn($item) => $accessor($item) < $value,
            '<=' => fn($item) => $accessor($item) <= $value,
            'in' => fn($item) => in_array($accessor($item), (array)$value),
            'contains' => fn($item) => is_string($accessor($item)) && str_contains($accessor($item), (string)$value),
            'startsWith' => fn($item) => is_string($accessor($item)) && str_starts_with($accessor($item), (string)$value),
            'endsWith' => fn($item) => is_string($accessor($item)) && str_ends_with($accessor($item), (string)$value),
            default => throw new \InvalidArgumentException("Unknown operator: $operator")
        };

        $this->conditions[] = $condition;
        return $this;
    }

    /**
     * Add a custom condition
     */
    public function whereCustom(Closure $predicate): self
    {
        $this->conditions[] = $predicate;
        return $this;
    }

    /**
     * Create a compound predicate from all conditions
     */
    public function toPredicate(): Closure
    {
        if (empty($this->conditions)) {
            return fn($item) => true;
        }

        if ($this->mode === 'and') {
            return function($item) {
                return array_all($this->conditions, fn($condition) => $condition($item));
            };
        }

        return function($item) {
            return array_any($this->conditions, fn($condition) => $condition($item));
        };
    }
}
