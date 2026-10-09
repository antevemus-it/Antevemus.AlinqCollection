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
 * `null` rule (1.3.0 contract, RN-20): a `null` value (including a missing property, which
 * resolves to `null`) is never `>`, `>=`, `<`, `<=`, `between`, `notBetween`, nor does it
 * `contain`/`startWith`/`endWith` anything; `=`/`==`/`!=`/`<>` compare strictly when either
 * side is `null` (`= null` means `isNull`, `= 0` does not match `null`); `in`/`notIn` are
 * strict when the value read is `null`.
 *
 * Mode (RN-22): `create()` accepts `and`/`or`, case-insensitive and trimmed; anything else
 * throws. `toPredicate()` (RN-21) is a snapshot: conditions added afterwards do not alter a
 * predicate already handed out.
 *
 * @version    1.3.1
 * @package    antevemus
 * @subpackage alinq
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
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
     * Create a new query with the specified mode (`and` or `or`, case-insensitive, trimmed)
     *
     * @throws InvalidArgumentException for any other mode (before 1.3.0 it silently became OR)
     */
    public static function create(string $mode = 'and'): self
    {
        $normalized = strtolower(trim($mode));
        if ($normalized !== 'and' && $normalized !== 'or') {
            throw new InvalidArgumentException(sprintf(
                'Unknown query mode: %s. Supported modes: and, or',
                var_export($mode, true)
            ));
        }

        $instance = new self();
        $instance->mode = $normalized;
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
     *
     * The predicate is a snapshot of the conditions and the mode at the moment of the call:
     * a where() added afterwards does not change it, and an empty builder yields an
     * always-true predicate only for that call (review 2026-10-08, 3.11).
     */
    public function toPredicate(): Closure
    {
        $conditions = $this->conditions;

        if ($conditions === []) {
            return static fn($item) => true;
        }

        if ($this->mode === 'and') {
            return static fn($item): bool => array_all($conditions, fn($condition) => $condition($item));
        }

        return static fn($item): bool => array_any($conditions, fn($condition) => $condition($item));
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
     * The `null` rule (RN-20): a `null` actual value never satisfies a relational or string
     * operator; loose equality becomes strict when either side is `null`; `in`/`notIn` are
     * strict for a `null` actual. Before 1.3.0 `'<' 18` accepted an item without the
     * property and `'=' null` matched `0`, `''`, `false` and `[]` (review 2026-10-08, 3.13).
     *
     * @throws InvalidArgumentException for an unknown operator or a malformed between/notBetween range
     */
    public static function operatorPredicate(string $operator, mixed $value = null): Closure
    {
        return match (self::normalizeOperator($operator)) {
            '=', '==' => static fn(mixed $actual): bool => ($actual === null || $value === null)
                ? $actual === $value
                : $actual == $value,
            '===' => static fn(mixed $actual): bool => $actual === $value,
            '!=', '<>' => static fn(mixed $actual): bool => ($actual === null || $value === null)
                ? $actual !== $value
                : $actual != $value,
            '!==' => static fn(mixed $actual): bool => $actual !== $value,
            '>' => static fn(mixed $actual): bool => $actual !== null && $actual > $value,
            '>=' => static fn(mixed $actual): bool => $actual !== null && $actual >= $value,
            '<' => static fn(mixed $actual): bool => $actual !== null && $actual < $value,
            '<=' => static fn(mixed $actual): bool => $actual !== null && $actual <= $value,
            'in' => static fn(mixed $actual): bool => in_array($actual, (array)$value, $actual === null),
            'notin' => static fn(mixed $actual): bool => !in_array($actual, (array)$value, $actual === null),
            'between' => self::betweenPredicate($operator, $value, false),
            'notbetween' => self::betweenPredicate($operator, $value, true),
            'isnull' => static fn(mixed $actual): bool => $actual === null,
            'isnotnull' => static fn(mixed $actual): bool => $actual !== null,
            'contains' => static fn(mixed $actual): bool => is_string($actual) && str_contains($actual, (string)$value),
            'startswith' => static fn(mixed $actual): bool => is_string($actual) && str_starts_with($actual, (string)$value),
            'endswith' => static fn(mixed $actual): bool => is_string($actual) && str_ends_with($actual, (string)$value),
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

        // null is neither inside nor outside a range (RN-20): both forms answer false
        if ($negate) {
            return static fn(mixed $actual): bool => $actual !== null && ($actual < $min || $actual > $max);
        }

        return static fn(mixed $actual): bool => $actual !== null && $actual >= $min && $actual <= $max;
    }
}
