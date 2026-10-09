<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Helpers;

use Antevemus\ALinq\ALinqQueryBuilder;
use BackedEnum;
use Closure;
use DateTimeInterface;
use Generator;
use InvalidArgumentException;
use OverflowException;
use UnderflowException;

/**
 * ALinqContract - The rules every operator of the eager and the lazy collection shares
 *
 * Written once for the 1.3.0 contract (review 2026-10-08, lote 3, forward 015):
 *
 * - shapeLike(): "a list reindexes, a dictionary keeps its keys" (RN-01/RN-02).
 * - groupKey(): a group or dictionary key is an int, a string or a BackedEnum (RN-07).
 * - equality(): a user comparer may answer `bool` (true = equal) or `<=>` style int
 *   (0 = equal); both are accepted everywhere (RN-09).
 * - numericValue() / comparableValue(): `null` is skipped by the numeric aggregations,
 *   anything that is not a number (or not comparable, for min/max) throws (RN-15).
 * - requireAtLeast(): a negative or zero argument that has no meaning throws instead of
 *   slicing from the tail or being ignored (RN-13).
 *
 * Since 1.4.0 (forward 021) the operators added to both collections run on one shared
 * implementation, so the eager and the lazy side cannot drift apart:
 *
 * - single(): the LINQ `Single`, with the same exception classes and messages on both sides.
 * - fieldPredicate(): the predicates of whereIn()/whereNotIn()/whereBetween().
 * - outerJoin(): leftJoin()/rightJoin()/fullJoin(), as `Enumerable.LeftJoin/RightJoin/FullJoin`.
 * - orderPositions(): the stable, composite sort behind orderBy()/thenBy().
 *
 * @version    1.4.0
 * @package    antevemus
 * @subpackage alinq.helpers
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class ALinqContract
{
    /**
     * Applies the key rule of RN-02 to a result derived from $origin: when the origin is a
     * list (array_is_list) the result is reindexed, otherwise the result keeps its keys.
     *
     * @param array $origin The items the operator started from
     * @param array $result The items the operator produced, with their origin keys
     * @return array
     */
    public static function shapeLike(array $origin, array $result): array
    {
        return array_is_list($origin) ? array_values($result) : $result;
    }

    /**
     * Validates a key produced by a key selector for groupBy(), countBy(), aggregateBy(),
     * toDictionary() and groupJoin(): int and string pass through, a BackedEnum becomes its
     * value, everything else throws (PHP would silently truncate a float, turn null into ''
     * and bool into 0/1, or throw a raw TypeError for arrays and objects).
     *
     * @param mixed $key The key produced by the selector
     * @param mixed $sourceKey The key of the item it was produced for (for the message)
     * @param string $operation The operator name (for the message)
     * @return int|string
     * @throws InvalidArgumentException
     */
    public static function groupKey(mixed $key, mixed $sourceKey, string $operation): int|string
    {
        if (is_int($key) || is_string($key)) {
            return $key;
        }
        if ($key instanceof BackedEnum) {
            return $key->value;
        }

        throw new InvalidArgumentException(sprintf(
            '%s() expects the key selector to return an int, a string or a BackedEnum, %s returned for item at key %s',
            $operation,
            get_debug_type($key),
            var_export($sourceKey, true)
        ));
    }

    /**
     * Normalizes a user comparer to `fn($a, $b): bool`: a bool answer is taken as is,
     * an int answer is read in the `<=>` convention (0 means equal).
     *
     * @param callable $comparer `fn($a, $b): bool|int`
     * @return Closure(mixed, mixed): bool
     */
    public static function equality(callable $comparer): Closure
    {
        return static function (mixed $a, mixed $b) use ($comparer): bool {
            $answer = $comparer($a, $b);
            return is_bool($answer) ? $answer : $answer === 0;
        };
    }

    /**
     * The numeric value of an item for sum(), average() and product(): null is returned
     * as null (the caller skips it), int/float pass through, a numeric string is converted,
     * a bool counts as 0/1, anything else throws.
     *
     * @param mixed $value
     * @param mixed $key The item key (for the message)
     * @param string $operation The operator name (for the message)
     * @return int|float|null
     * @throws InvalidArgumentException
     */
    public static function numericValue(mixed $value, mixed $key, string $operation): int|float|null
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return $value + 0;
        }

        throw new InvalidArgumentException(sprintf(
            '%s() expects numeric values, %s found at key %s',
            $operation,
            get_debug_type($value),
            var_export($key, true)
        ));
    }

    /**
     * The comparable value of an item for min() and max(): null is returned as null (the
     * caller skips it), scalars and DateTimeInterface pass through (compared with PHP's
     * `<=>`), arrays and other objects throw.
     *
     * @param mixed $value
     * @param mixed $key The item key (for the message)
     * @param string $operation The operator name (for the message)
     * @return mixed
     * @throws InvalidArgumentException
     */
    public static function comparableValue(mixed $value, mixed $key, string $operation): mixed
    {
        if ($value === null || is_scalar($value) || $value instanceof DateTimeInterface) {
            return $value;
        }

        throw new InvalidArgumentException(sprintf(
            '%s() expects comparable values (scalars or DateTimeInterface), %s found at key %s',
            $operation,
            get_debug_type($value),
            var_export($key, true)
        ));
    }

    /**
     * Guards an integer argument: throws when it is below the minimum that makes sense for
     * the operator (take/skip/pad >= 0, chunk/random/range step >= 1).
     *
     * @throws InvalidArgumentException
     */
    public static function requireAtLeast(int $value, int $minimum, string $argument, string $operation): void
    {
        if ($value < $minimum) {
            throw new InvalidArgumentException(sprintf(
                '%s() expects %s to be at least %d, %d given',
                $operation,
                $argument,
                $minimum,
                $value
            ));
        }
    }

    /**
     * The single element of a sequence, or the single element that satisfies the predicate
     * (Single in LINQ). Stops at the second match.
     *
     * @param iterable $items
     * @param Closure(mixed, mixed): mixed|null $predicate normalized by ALinqCallable::withKey()
     * @return mixed
     * @throws UnderflowException When the sequence is empty or no element matches
     * @throws OverflowException When more than one element (or matching element) exists
     */
    public static function single(iterable $items, ?Closure $predicate): mixed
    {
        $found = false;
        $match = null;
        foreach ($items as $key => $item) {
            if ($predicate !== null && !$predicate($item, $key)) {
                continue;
            }
            if ($found) {
                throw new OverflowException(
                    $predicate === null
                        ? 'Sequence contains more than one element.'
                        : 'Sequence contains more than one matching element.'
                );
            }
            $found = true;
            $match = $item;
        }

        if (!$found) {
            throw new UnderflowException(
                $predicate === null
                    ? 'Sequence contains no elements.'
                    : 'Sequence contains no matching element.'
            );
        }

        return $match;
    }

    /**
     * The predicate behind the collections' whereIn()/whereNotIn()/whereBetween(): the field
     * is read through ALinqPropertyAccess (dot notation allowed); `in`/`notIn` compare with
     * strict identity (`in_array(..., true)`, so `'1'` is not in `[1]`); `between` is
     * inclusive and follows the null rule of the query builder (a null value is never
     * between, RN-20).
     *
     * @param string $operation 'in', 'notIn' or 'between'
     * @param string $field Property name or dot-notation path
     * @param array $arguments [$values] for in/notIn, [$min, $max] for between
     * @return Closure(mixed): bool
     */
    public static function fieldPredicate(string $operation, string $field, array $arguments): Closure
    {
        $accessor = ALinqPropertyAccess::getPropertyAccessor($field);

        if ($operation === 'between') {
            // the builder's evaluator: inclusive, and null is never between (one rule, RN-20)
            $inRange = ALinqQueryBuilder::operatorPredicate('between', [$arguments[0], $arguments[1]]);
            return static fn(mixed $item): bool => $inRange($accessor($item));
        }

        [$values] = $arguments;
        return match ($operation) {
            'in' => static fn(mixed $item): bool => in_array($accessor($item), $values, true),
            'notIn' => static fn(mixed $item): bool => !in_array($accessor($item), $values, true),
            default => throw new InvalidArgumentException(sprintf('Unknown field predicate: %s', $operation)),
        };
    }

    /**
     * The outer joins of .NET 10/11 LINQ (`Enumerable.LeftJoin`, `RightJoin`, `FullJoin`),
     * shared by both collections. The result selector receives `($outer, $inner)` with `null`
     * on the side without a match; without a selector each result is the pair
     * `[$outer, $inner]`. Keys match by the strict identity of ALinqCallable::hashKey() and a
     * null key never matches (the item with a null key comes out without a partner). The
     * output is a sequence (yielded without keys, so it materializes as a list):
     *
     * - left: the outer side is streamed in its order; the inner side is indexed first.
     * - right: the inner side is streamed in its order; the outer side is indexed first.
     * - full: the outer side is streamed (pairs and unmatched outer items, in outer order),
     *   then the unmatched inner items follow in inner order; the inner side is indexed first.
     *
     * @param 'left'|'right'|'full' $kind
     * @param iterable $outer
     * @param iterable $inner
     * @param callable $outerKeySelector `fn($outer)` or `fn($outer, $key)`
     * @param callable $innerKeySelector `fn($inner)` or `fn($inner, $key)`
     * @param callable|null $resultSelector `fn($outer, $inner)`
     * @return Generator<int, mixed>
     */
    public static function outerJoin(
        string $kind,
        iterable $outer,
        iterable $inner,
        callable $outerKeySelector,
        callable $innerKeySelector,
        ?callable $resultSelector
    ): Generator {
        $outerKeySelector = ALinqCallable::withKey($outerKeySelector);
        $innerKeySelector = ALinqCallable::withKey($innerKeySelector);
        $result = $resultSelector ?? static fn(mixed $o, mixed $i): array => [$o, $i];

        if ($kind === 'right') {
            // RightJoin: the outer side becomes the lookup, the inner side drives the order.
            [$outerItems, $outerIndex] = self::indexPositions($outer, $outerKeySelector);
            foreach ($inner as $innerKey => $innerItem) {
                $key = $innerKeySelector($innerItem, $innerKey);
                $positions = $key === null ? [] : ($outerIndex[ALinqCallable::hashKey($key)] ?? []);
                if ($positions === []) {
                    yield $result(null, $innerItem);
                    continue;
                }
                foreach ($positions as $position) {
                    yield $result($outerItems[$position], $innerItem);
                }
            }
            return;
        }

        if ($kind !== 'left' && $kind !== 'full') {
            throw new InvalidArgumentException(sprintf('Unknown outer join kind: %s', $kind));
        }

        [$innerItems, $innerIndex] = self::indexPositions($inner, $innerKeySelector);
        $matched = [];

        foreach ($outer as $outerKey => $outerItem) {
            $key = $outerKeySelector($outerItem, $outerKey);
            $positions = $key === null ? [] : ($innerIndex[ALinqCallable::hashKey($key)] ?? []);
            if ($positions === []) {
                yield $result($outerItem, null);
                continue;
            }
            foreach ($positions as $position) {
                $matched[$position] = true;
                yield $result($outerItem, $innerItems[$position]);
            }
        }

        if ($kind === 'full') {
            foreach ($innerItems as $position => $innerItem) {
                if (!isset($matched[$position])) {
                    yield $result(null, $innerItem);
                }
            }
        }
    }

    /**
     * Buffers a sequence and indexes the position of every item by the identity of its
     * selected key; items whose key is null are buffered but never indexed (they cannot
     * match).
     *
     * @param iterable $items
     * @param Closure(mixed, mixed): mixed $keySelector
     * @return array{0: list<mixed>, 1: array<string, list<int>>}
     */
    private static function indexPositions(iterable $items, Closure $keySelector): array
    {
        $buffer = [];
        $index = [];
        foreach ($items as $key => $item) {
            $position = count($buffer);
            $buffer[] = $item;
            $selected = $keySelector($item, $key);
            if ($selected !== null) {
                $index[ALinqCallable::hashKey($selected)][] = $position;
            }
        }
        return [$buffer, $index];
    }

    /**
     * Stable composite ordering (orderBy()/orderByDescending() followed by any number of
     * thenBy()/thenByDescending()). Every criterion carries its keys already computed, one
     * per position (Schwartzian transform, so each selector runs once per item); the first
     * criterion that tells two items apart decides, and a full tie keeps the original order.
     *
     * @param int $count Number of items
     * @param list<array{0: array<int, mixed>, 1: int, 2: callable|null}> $criteria
     *        [keys by position, direction (1 ascending, -1 descending), comparer `fn($a, $b): int` or null for `<=>`]
     * @return list<int> The positions in sorted order
     */
    public static function orderPositions(int $count, array $criteria): array
    {
        if ($count === 0) {
            return [];
        }

        $positions = range(0, $count - 1);
        usort($positions, static function (int $a, int $b) use ($criteria): int {
            foreach ($criteria as [$keys, $direction, $comparer]) {
                $order = $comparer === null ? $keys[$a] <=> $keys[$b] : ($comparer($keys[$a], $keys[$b]) <=> 0);
                if ($order !== 0) {
                    return $direction * $order;
                }
            }
            return $a <=> $b;
        });

        return $positions;
    }
}
