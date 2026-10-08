<?php

declare(strict_types=1);

namespace Antevemus\ALinq;

use Antevemus\ALinq\Helpers\ALinqPropertyAccess;
use Closure;
use InvalidArgumentException;

/**
 * ALinqQueryBuilder
 *
 * A query builder for more complex filtering operations.
 *
 * Supported operators (case-insensitive; spaces and underscores are ignored, so
 * `notIn`, `NOT_IN` and `not in` are the same operator):
 *
 *  =, ==        loose equality            !=, <>       loose inequality
 *  ===          strict equality           !==          strict inequality
 *  >, >=, <, <= relational comparison
 *  in           value is one of the given list (loose)
 *  notIn        value is none of the given list (loose)
 *  between      min <= value <= max, given as [min, max] (inclusive)
 *  notBetween   value < min or value > max
 *  isNull       value is null (the comparison value is ignored)
 *  isNotNull    value is not null (the comparison value is ignored)
 *  contains     string contains the given substring
 *  startsWith   string starts with the given prefix
 *  endsWith     string ends with the given suffix
 *
 * The same evaluator backs `ALinqCollection::createPredicate()`, so both entry points
 * accept the same operators with the same semantics.
 *
 * @version    1.2.0
 * @package    antevemus
 * @subpackage alinq
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
class ALinqQueryBuilder
{
    /**
     * Canonical operator names, in documentation order.
     */
    private const OPERATORS = [
        '=', '==', '===', '!=', '<>', '!==', '>', '>=', '<', '<=',
        'in', 'notIn', 'between', 'notBetween', 'isNull', 'isNotNull',
        'contains', 'startsWith', 'endsWith',
    ];

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
     *
     * @param string $property Property name or dot-notation path (e.g. "user.address.city")
     * @param string $operator One of the supported operators (see class docblock)
     * @param mixed $value Comparison value; a [min, max] pair for between/notBetween, a list for in/notIn,
     *                     ignored for isNull/isNotNull
     */
    public function where(string $property, string $operator, mixed $value = null): self
    {
        $accessor = ALinqPropertyAccess::getPropertyAccessor($property);
        $test = self::operatorPredicate($operator, $value);

        $this->conditions[] = fn(mixed $item): bool => $test($accessor($item));
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

    /**
     * List the supported operators (canonical spelling).
     *
     * @return string[]
     */
    public static function supportedOperators(): array
    {
        return self::OPERATORS;
    }

    /**
     * Build a predicate `fn(mixed $actual): bool` that evaluates `$actual <operator> $value`.
     *
     * This is the single operator evaluator shared by where() and by
     * ALinqCollection::createPredicate().
     *
     * @throws InvalidArgumentException for an unknown operator or a malformed between/notBetween range
     */
    public static function operatorPredicate(string $operator, mixed $value = null): Closure
    {
        return match (self::normalizeOperator($operator)) {
            '=', '==' => fn(mixed $actual): bool => $actual == $value,
            '===' => fn(mixed $actual): bool => $actual === $value,
            '!=', '<>' => fn(mixed $actual): bool => $actual != $value,
            '!==' => fn(mixed $actual): bool => $actual !== $value,
            '>' => fn(mixed $actual): bool => $actual > $value,
            '>=' => fn(mixed $actual): bool => $actual >= $value,
            '<' => fn(mixed $actual): bool => $actual < $value,
            '<=' => fn(mixed $actual): bool => $actual <= $value,
            'in' => fn(mixed $actual): bool => in_array($actual, (array)$value),
            'notin' => fn(mixed $actual): bool => !in_array($actual, (array)$value),
            'between' => self::betweenPredicate($operator, $value, false),
            'notbetween' => self::betweenPredicate($operator, $value, true),
            'isnull' => fn(mixed $actual): bool => $actual === null,
            'isnotnull' => fn(mixed $actual): bool => $actual !== null,
            'contains' => fn(mixed $actual): bool => is_string($actual) && str_contains($actual, (string)$value),
            'startswith' => fn(mixed $actual): bool => is_string($actual) && str_starts_with($actual, (string)$value),
            'endswith' => fn(mixed $actual): bool => is_string($actual) && str_ends_with($actual, (string)$value),
            default => throw new InvalidArgumentException(
                sprintf('Unknown operator: %s. Supported operators: %s', $operator, implode(', ', self::OPERATORS))
            ),
        };
    }

    /**
     * Lower-case the operator and drop spaces/underscores ("NOT IN", "not_in", "notIn" -> "notin").
     */
    private static function normalizeOperator(string $operator): string
    {
        return strtolower(str_replace([' ', '_'], '', trim($operator)));
    }

    /**
     * Build the inclusive range predicate for between/notBetween.
     *
     * @throws InvalidArgumentException when $range is not a two-element [min, max] array
     */
    private static function betweenPredicate(string $operator, mixed $range, bool $negate): Closure
    {
        if (!is_array($range) || count($range) !== 2) {
            throw new InvalidArgumentException(
                sprintf('Operator %s expects a [min, max] array with exactly two elements', $operator)
            );
        }

        [$min, $max] = array_values($range);

        if ($negate) {
            return fn(mixed $actual): bool => $actual < $min || $actual > $max;
        }

        return fn(mixed $actual): bool => $actual >= $min && $actual <= $max;
    }
}
