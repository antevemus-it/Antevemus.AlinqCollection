<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Helpers;

use Antevemus\ADeepPropertyAccess\PropertyAccess;
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLookup;
use Antevemus\ALinq\ALinqQueryBuilder;
use Antevemus\ALinq\DuplicateKeyPolicy;
use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;
use BackedEnum;
use Closure;
use DateTimeInterface;
use Generator;
use InvalidArgumentException;
use OverflowException;
use Stringable;
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
 * Since 2.2.0 (forward 047, D26) the same holds for every operator 2.2.0 added or extended:
 *
 * - isMoreExtreme(): the one comparison behind min()/max()/minBy()/maxBy() and their
 *   *OrDefault() variants, with or without a comparer (before, `<=>` on the eager side and
 *   `<`/`>` on the lazy side, which disagreed on values that do not compare both ways: NAN,
 *   arrays with different keys).
 * - distinct(): identity by ALinqCallable::hashKey() or by an IALinqEqualityComparer.
 * - groupItems(), countByKey(), aggregateByKey(): groupBy(), toLookup(), countBy() and
 *   aggregateBy() (the last two keep one counter or accumulator per key, not the items).
 * - dictionary(): toDictionary() with a DuplicateKeyPolicy; lookup(): toLookup().
 * - setByKeys(): exceptByKeys()/intersectByKeys(), the `ExceptBy`/`IntersectBy` of .NET 6.
 * - implode(): the text of the items joined by a separator.
 *
 * Since 2.4.0 an IALinqEqualityComparer may decide the key identity of every operator that
 * groups, deduplicates or matches by key: groupItems(), countByKey(), aggregateByKey(),
 * dictionary(), lookup(), setByKeys(), setByElements() and union() (the set operations of the
 * eager side, written there until 2.3.1), outerJoin(), and the inner joins innerJoin() and
 * groupJoin() (shared since 2.4.0, when the lazy side gained join() and groupJoin()). The
 * identity is hash-based (ALinqKeySet: hash() is the bucket, equals() decides inside it), and
 * the first key seen represents its group or set. aggregateByKey() takes the seed of each
 * group from a seed selector `fn($key)` when one is given.
 *
 * @version    2.4.0
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
     * toDictionary() and toLookup() (2.2.0): int and string pass through, a BackedEnum becomes
     * its value, everything else throws (PHP would silently truncate a float, turn null into ''
     * and bool into 0/1, or throw a raw TypeError for arrays and objects). The key is then a
     * PHP array key: `1` and `'1'` are the same key, `'01'` is another one (unlike the strict
     * identity of joins and sets, where `1` and `'1'` differ).
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
     * is read through the shared accessor (`PropertyAccess` of antevemus/adeep-property-access,
     * since 2.0.0; dot notation allowed); `in`/`notIn` compare with strict identity
     * (`in_array(..., true)`, so `'1'` is not in `[1]`); `between` is inclusive and follows
     * the null rule of the query builder (a null value is never between, RN-20).
     *
     * @param string $operation 'in', 'notIn' or 'between'
     * @param string $field Property name or dot-notation path
     * @param array $arguments [$values] for in/notIn, [$min, $max] for between
     * @return Closure(mixed): bool
     */
    public static function fieldPredicate(string $operation, string $field, array $arguments): Closure
    {
        $accessor = PropertyAccess::accessor($field);

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
     * `[$outer, $inner]`. Keys match by the strict identity of ALinqCallable::hashKey() (or,
     * since 2.4.0, by a comparer: its hash() indexes, its equals() confirms) and a
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
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (2.4.0), hash-based
     * @return Generator<int, mixed>
     */
    public static function outerJoin(
        string $kind,
        iterable $outer,
        iterable $inner,
        callable $outerKeySelector,
        callable $innerKeySelector,
        ?callable $resultSelector,
        ?IALinqEqualityComparer $comparer = null
    ): Generator {
        $outerKeySelector = ALinqCallable::withKey($outerKeySelector);
        $innerKeySelector = ALinqCallable::withKey($innerKeySelector);
        $result = $resultSelector ?? static fn(mixed $o, mixed $i): array => [$o, $i];

        if ($kind === 'right') {
            // RightJoin: the outer side becomes the lookup, the inner side drives the order.
            [$outerItems, $outerIndex] = self::indexPositions($outer, $outerKeySelector, $comparer);
            foreach ($inner as $innerKey => $innerItem) {
                $key = $innerKeySelector($innerItem, $innerKey);
                $positions = $comparer === null
                    ? ($key === null ? [] : ($outerIndex[ALinqCallable::hashKey($key)] ?? []))
                    : self::matchingPositions($outerIndex, $key, $comparer);
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

        [$innerItems, $innerIndex] = self::indexPositions($inner, $innerKeySelector, $comparer);
        $matched = [];

        foreach ($outer as $outerKey => $outerItem) {
            $key = $outerKeySelector($outerItem, $outerKey);
            $positions = $comparer === null
                ? ($key === null ? [] : ($innerIndex[ALinqCallable::hashKey($key)] ?? []))
                : self::matchingPositions($innerIndex, $key, $comparer);
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
     * The inner join (Join in LINQ), shared since 2.4.0 by join() of both collections: the inner
     * side is buffered and indexed first (read once), then the outer side is streamed; for each
     * outer item, one result per matching inner item, in inner order. A null key never matches.
     *
     * @param callable $outerKeySelector `fn($outer)` or `fn($outer, $key)`
     * @param callable $innerKeySelector `fn($inner)` or `fn($inner, $key)`
     * @param callable $resultSelector `fn($outer, $inner)`
     * @param IALinqEqualityComparer|null $comparer The identity of the keys, hash-based
     * @return Generator<int, mixed>
     */
    public static function innerJoin(
        iterable $outer,
        iterable $inner,
        callable $outerKeySelector,
        callable $innerKeySelector,
        callable $resultSelector,
        ?IALinqEqualityComparer $comparer
    ): Generator {
        $outerKeySelector = ALinqCallable::withKey($outerKeySelector);
        [$innerItems, $innerIndex] = self::indexPositions($inner, ALinqCallable::withKey($innerKeySelector), $comparer);

        foreach ($outer as $outerKey => $outerItem) {
            $key = $outerKeySelector($outerItem, $outerKey);
            $positions = $comparer === null
                ? ($key === null ? [] : ($innerIndex[ALinqCallable::hashKey($key)] ?? []))
                : self::matchingPositions($innerIndex, $key, $comparer);
            foreach ($positions as $position) {
                yield $resultSelector($outerItem, $innerItems[$position]);
            }
        }
    }

    /**
     * The group join (GroupJoin in LINQ), shared since 2.4.0 by groupJoin() of both collections:
     * as innerJoin(), but one result per outer item, the matching inner items handed over as an
     * ALinqCollection list (empty when nothing matches; a null key never matches).
     *
     * @param callable $outerKeySelector `fn($outer)` or `fn($outer, $key)`
     * @param callable $innerKeySelector `fn($inner)` or `fn($inner, $key)`
     * @param callable $resultSelector `fn($outer, ALinqCollection $matches)`
     * @param IALinqEqualityComparer|null $comparer The identity of the keys, hash-based
     * @return Generator<int, mixed>
     */
    public static function groupJoin(
        iterable $outer,
        iterable $inner,
        callable $outerKeySelector,
        callable $innerKeySelector,
        callable $resultSelector,
        ?IALinqEqualityComparer $comparer
    ): Generator {
        $outerKeySelector = ALinqCallable::withKey($outerKeySelector);
        [$innerItems, $innerIndex] = self::indexPositions($inner, ALinqCallable::withKey($innerKeySelector), $comparer);

        foreach ($outer as $outerKey => $outerItem) {
            $key = $outerKeySelector($outerItem, $outerKey);
            $positions = $comparer === null
                ? ($key === null ? [] : ($innerIndex[ALinqCallable::hashKey($key)] ?? []))
                : self::matchingPositions($innerIndex, $key, $comparer);
            $matches = [];
            foreach ($positions as $position) {
                $matches[] = $innerItems[$position];
            }
            yield $resultSelector($outerItem, new ALinqCollection($matches));
        }
    }

    /**
     * Buffers a sequence and indexes the position of every item by its selected key; items
     * whose key is null are buffered but never indexed (they cannot match). Without a comparer
     * the index maps ALinqCallable::hashKey() to the positions (the shape of 2.3.1); with one
     * (2.4.0) it maps the comparer's hash() to the distinct keys that have it, each with its
     * positions, so equals() runs once per distinct key of a bucket, never once per item.
     *
     * @param iterable $items
     * @param Closure(mixed, mixed): mixed $keySelector
     * @return array{0: list<mixed>, 1: array<string, list<int>|list<array{0: mixed, 1: list<int>}>>}
     */
    private static function indexPositions(iterable $items, Closure $keySelector, ?IALinqEqualityComparer $comparer): array
    {
        $buffer = [];
        $index = [];
        foreach ($items as $key => $item) {
            $position = count($buffer);
            $buffer[] = $item;
            $selected = $keySelector($item, $key);
            if ($selected === null) {
                continue;
            }
            if ($comparer === null) {
                $index[ALinqCallable::hashKey($selected)][] = $position;
                continue;
            }
            $hash = $comparer->hash($selected);
            $entry = self::bucketEntry($index[$hash] ?? [], $selected, $comparer);
            if ($entry === null) {
                $index[$hash][] = [$selected, [$position]];
            } else {
                $index[$hash][$entry][1][] = $position;
            }
        }
        return [$buffer, $index];
    }

    /**
     * Under a comparer, the positions indexed under the key equal to $key, in source order; none
     * for a null key, which never reaches the comparer. (Without a comparer the callers read the
     * index directly, as in 2.3.1.)
     *
     * @param array<string, list<array{0: mixed, 1: list<int>}>> $index
     * @return list<int>
     */
    private static function matchingPositions(array $index, mixed $key, IALinqEqualityComparer $comparer): array
    {
        if ($key === null) {
            return [];
        }
        $bucket = $index[$comparer->hash($key)] ?? [];
        $entry = self::bucketEntry($bucket, $key, $comparer);

        return $entry === null ? [] : $bucket[$entry][1];
    }

    /**
     * The entry of a comparer's bucket whose key is equal to $key, by equals().
     *
     * @param list<array{0: mixed, 1: list<int>}> $bucket
     */
    private static function bucketEntry(array $bucket, mixed $key, IALinqEqualityComparer $comparer): ?int
    {
        foreach ($bucket as $entry => [$kept]) {
            if ($comparer->equals($kept, $key)) {
                return $entry;
            }
        }

        return null;
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

    /**
     * Whether $candidate replaces $current as the extreme of min()/max() ($direction -1/1) or
     * minBy()/maxBy(): `<=>` without a comparer, otherwise the comparer's answer read in the
     * `<=>` convention. The one comparison of both collections (2.2.0).
     *
     * @param callable|null $comparer `fn($a, $b): int`
     */
    public static function isMoreExtreme(mixed $candidate, mixed $current, int $direction, ?callable $comparer): bool
    {
        $order = $comparer === null ? $candidate <=> $current : $comparer($candidate, $current) <=> 0;

        return $order === $direction;
    }

    /**
     * The first occurrence of each identity, with its source key: identity by
     * ALinqCallable::hashKey() of the item (or of the selected key), or, with a comparer, its
     * hash() as the bucket and equals() inside the bucket (2.2.0, D5). O(n) expected.
     *
     * @param Closure(mixed, mixed): mixed|null $keySelector normalized by ALinqCallable::withKey()
     * @return Generator<array-key, mixed>
     */
    public static function distinct(iterable $items, ?Closure $keySelector, ?IALinqEqualityComparer $comparer): Generator
    {
        $seen = [];
        foreach ($items as $key => $item) {
            $identifier = $keySelector === null ? $item : $keySelector($item, $key);
            if ($comparer === null) {
                $hash = ALinqCallable::hashKey($identifier);
                if (isset($seen[$hash])) {
                    continue;
                }
                $seen[$hash] = true;
            } else {
                $hash = $comparer->hash($identifier);
                foreach ($seen[$hash] ?? [] as $kept) {
                    if ($comparer->equals($kept, $identifier)) {
                        continue 2;
                    }
                }
                $seen[$hash][] = $identifier;
            }
            yield $key => $item;
        }
    }

    /**
     * The items (or the selected elements) grouped by key, keys in the order of their first
     * appearance, each group a list in source order (groupBy(), toLookup()). A key follows
     * groupKey(): `1` and `'1'` are one key, as PHP array keys.
     *
     * @param Closure(mixed, mixed): mixed $keySelector normalized by ALinqCallable::withKey()
     * @param Closure(mixed, mixed): mixed|null $elementSelector normalized by ALinqCallable::withKey()
     * @param string $operation The operator name (for the message of an invalid key)
     * With a comparer (2.4.0) the keys that are equal under it form one group, whose key is
     * the first of them seen (see representatives()).
     *
     * @return array<array-key, list<mixed>>
     * @throws InvalidArgumentException When a key is not an int, a string or a BackedEnum
     */
    public static function groupItems(iterable $items, Closure $keySelector, ?Closure $elementSelector, string $operation, ?IALinqEqualityComparer $comparer = null): array
    {
        return self::groupItemsWithKeys($items, $keySelector, $elementSelector, $operation, $comparer)[0];
    }

    /**
     * groupItems() and the key of every group as the selector produced it (after groupKey()),
     * in the order of the groups: a numeric string such as `'10'` stays a string here, where
     * the array key of its group is the int 10 (what a comparer of texts must receive).
     *
     * @return array{0: array<array-key, list<mixed>>, 1: list<int|string>}
     * @throws InvalidArgumentException When a key is not an int, a string or a BackedEnum
     */
    private static function groupItemsWithKeys(iterable $items, Closure $keySelector, ?Closure $elementSelector, string $operation, ?IALinqEqualityComparer $comparer): array
    {
        $representative = self::representatives($comparer, $operation);
        $groups = [];
        $keys = [];
        foreach ($items as $itemKey => $item) {
            $key = self::groupKey($keySelector($item, $itemKey), $itemKey, $operation);
            if ($representative !== null) {
                $key = $representative($key);
            }
            if (!array_key_exists($key, $groups)) {
                $keys[] = $key;
            }
            $groups[$key][] = $elementSelector === null ? $item : $elementSelector($item, $itemKey);
        }

        return [$groups, $keys];
    }

    /**
     * The group of every key under a comparer (2.4.0): a Closure that takes a key already
     * validated by groupKey() and answers the representative of its group, the first key seen
     * that is equal to it (an ALinqKeySet, so hash-based); null without a comparer, where a key
     * is its own group. Two keys that the comparer tells apart but that are one PHP array key
     * (`1` and `'1'`, for a comparer that separates them) cannot both name a group: that throws.
     *
     * @return (Closure(int|string): int|string)|null
     * @throws InvalidArgumentException (from the Closure) When two different keys are one array key
     */
    private static function representatives(?IALinqEqualityComparer $comparer, string $operation): ?Closure
    {
        if ($comparer === null) {
            return null;
        }

        $kept = new ALinqKeySet($comparer);
        $arrayKeys = [];

        return static function (int|string $key) use ($kept, &$arrayKeys, $operation): int|string {
            [$representative, $added] = $kept->intern($key);
            if (!$added) {
                return $representative;
            }
            if (array_key_exists($key, $arrayKeys)) {
                throw new InvalidArgumentException(sprintf(
                    '%s() keys %s and %s are different under the comparer but are the same array key',
                    $operation,
                    var_export($arrayKeys[$key], true),
                    var_export($key, true)
                ));
            }
            $arrayKeys[$key] = $key;

            return $key;
        };
    }

    /**
     * The number of items per key, keys in first-appearance order (countBy()): one integer per
     * key, the items are not kept.
     *
     * @param Closure(mixed, mixed): mixed $keySelector normalized by ALinqCallable::withKey()
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (2.4.0); the first key seen names the group
     * @return array<array-key, int>
     * @throws InvalidArgumentException When a key is not an int, a string or a BackedEnum
     */
    public static function countByKey(iterable $items, Closure $keySelector, string $operation, ?IALinqEqualityComparer $comparer = null): array
    {
        $representative = self::representatives($comparer, $operation);
        $counts = [];
        foreach ($items as $itemKey => $item) {
            $key = self::groupKey($keySelector($item, $itemKey), $itemKey, $operation);
            if ($representative !== null) {
                $key = $representative($key);
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * One accumulator per key (aggregateBy()): `$func($carry, $item, $position)`, where the
     * third argument is the position of the item inside its group (0, 1, ...), as the eager
     * aggregateBy() has always passed (each group was a reindexed list); every group starts
     * from the same $seed, whatever its type (a Closure seed is the accumulator itself), or,
     * since 2.4.0, from `$seedSelector($key)` when a seed selector is given (the `seedSelector`
     * of .NET 9), called once per group with the key of the group; $seed is then ignored. Keys
     * in first-appearance order; the items are not kept.
     *
     * @param Closure(mixed, mixed): mixed $keySelector normalized by ALinqCallable::withKey()
     * @param mixed $seed The initial accumulator of every group (ignored when $seedSelector is given)
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (2.4.0); the first key seen names the group
     * @param callable|null $seedSelector `fn($key)`, the seed of each group (2.4.0)
     * @return array<array-key, mixed>
     * @throws InvalidArgumentException When a key is not an int, a string or a BackedEnum
     */
    public static function aggregateByKey(iterable $items, Closure $keySelector, mixed $seed, callable $func, string $operation, ?IALinqEqualityComparer $comparer = null, ?callable $seedSelector = null): array
    {
        $representative = self::representatives($comparer, $operation);
        $carries = [];
        $positions = [];
        foreach ($items as $itemKey => $item) {
            $key = self::groupKey($keySelector($item, $itemKey), $itemKey, $operation);
            if ($representative !== null) {
                $key = $representative($key);
            }
            $position = $positions[$key] ?? 0;
            $carries[$key] = $func($position === 0 ? ($seedSelector === null ? $seed : $seedSelector($key)) : $carries[$key], $item, $position);
            $positions[$key] = $position + 1;
        }

        return $carries;
    }

    /**
     * toDictionary(): `key => element`, with the policy for a key produced twice. The key
     * selector runs for every item and every key is validated; under KeepFirst the element
     * selector does not run for a repeated key; under KeepLast the key keeps the position of
     * its first occurrence. With a comparer (2.4.0) the keys equal under it are one key, the
     * first one seen, and the message of a repeat names the key as produced the second time.
     *
     * @param Closure(mixed, mixed): mixed $keySelector normalized by ALinqCallable::withKey()
     * @param Closure(mixed, mixed): mixed|null $elementSelector normalized by ALinqCallable::withKey()
     * @return array<array-key, mixed>
     * @throws InvalidArgumentException When a key is not valid, or (Throw) is produced twice
     */
    public static function dictionary(iterable $items, Closure $keySelector, ?Closure $elementSelector, DuplicateKeyPolicy $onDuplicate, ?IALinqEqualityComparer $comparer = null): array
    {
        $representative = self::representatives($comparer, 'toDictionary');
        $result = [];
        foreach ($items as $itemKey => $item) {
            $produced = self::groupKey($keySelector($item, $itemKey), $itemKey, 'toDictionary');
            $key = $representative === null ? $produced : $representative($produced);
            if (array_key_exists($key, $result)) {
                if ($onDuplicate === DuplicateKeyPolicy::KeepFirst) {
                    continue;
                }
                if ($onDuplicate === DuplicateKeyPolicy::Throw) {
                    throw new InvalidArgumentException(sprintf(
                        'toDictionary() key %s produced twice (second time %sfor item at key %s)',
                        var_export($key, true),
                        $produced === $key ? '' : 'as ' . var_export($produced, true) . ', ',
                        var_export($itemKey, true)
                    ));
                }
            }
            $result[$key] = $elementSelector === null ? $item : $elementSelector($item, $itemKey);
        }

        return $result;
    }

    /**
     * toLookup(): the groups of groupItems() as an immutable ALinqLookup.
     *
     * @param Closure(mixed, mixed): mixed $keySelector normalized by ALinqCallable::withKey()
     * @param Closure(mixed, mixed): mixed|null $elementSelector normalized by ALinqCallable::withKey()
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (2.4.0), kept by the lookup for get()/contains()
     * @throws InvalidArgumentException When a key is not an int, a string or a BackedEnum
     */
    public static function lookup(iterable $items, Closure $keySelector, ?Closure $elementSelector, ?IALinqEqualityComparer $comparer = null): ALinqLookup
    {
        [$groups, $keys] = self::groupItemsWithKeys($items, $keySelector, $elementSelector, 'toLookup', $comparer);

        return new ALinqLookup($groups, $comparer, $keys);
    }

    /**
     * exceptByKeys()/intersectByKeys(), the `ExceptBy`/`IntersectBy` of .NET 6: $keys is read
     * once, first, into a set by the strict identity of ALinqCallable::hashKey(); the selector
     * runs on the items only; an item is kept when its key is absent ($keepWhenPresent false)
     * or present (true) in the set, each key at most once (the first occurrence), with its
     * source key. A null key matches a null in $keys (a set rule; null never matches in a join).
     * With a comparer (2.4.0) the keys are compared by it (ALinqKeySet), the first item of each
     * key still the one kept.
     *
     * @param Closure(mixed, mixed): mixed $keySelector normalized by ALinqCallable::withKey()
     * @return Generator<array-key, mixed>
     */
    public static function setByKeys(iterable $items, iterable $keys, Closure $keySelector, bool $keepWhenPresent, ?IALinqEqualityComparer $comparer = null): Generator
    {
        if ($comparer !== null) {
            yield from self::setByKeysUnder($comparer, $items, $keys, $keySelector, $keepWhenPresent);
            return;
        }

        $present = [];
        foreach ($keys as $key) {
            $present[ALinqCallable::hashKey($key)] = true;
        }

        $seen = [];
        foreach ($items as $itemKey => $item) {
            $identity = ALinqCallable::hashKey($keySelector($item, $itemKey));
            if (isset($seen[$identity]) || isset($present[$identity]) !== $keepWhenPresent) {
                continue;
            }
            $seen[$identity] = true;
            yield $itemKey => $item;
        }
    }

    /**
     * setByKeys() under a comparer (2.4.0): the same rules, the keys compared by the comparer
     * through two ALinqKeySet (the keys given, the keys already emitted).
     *
     * @param Closure(mixed, mixed): mixed $keySelector normalized by ALinqCallable::withKey()
     * @return Generator<array-key, mixed>
     */
    private static function setByKeysUnder(IALinqEqualityComparer $comparer, iterable $items, iterable $keys, Closure $keySelector, bool $keepWhenPresent): Generator
    {
        $present = new ALinqKeySet($comparer);
        foreach ($keys as $key) {
            $present->add($key);
        }

        $seen = new ALinqKeySet($comparer);
        foreach ($items as $itemKey => $item) {
            $identity = $keySelector($item, $itemKey);
            if ($present->contains($identity) !== $keepWhenPresent || !$seen->add($identity)) {
                continue;
            }
            yield $itemKey => $item;
        }
    }

    /**
     * The set operations of the eager collection under a comparer (2.4.0: intersect(), except(),
     * intersectBy(), exceptBy()): the items whose key is (intersect) or is not (except) the key
     * of an item of $second, the selector applied to both sides, each key at most once (the
     * first occurrence), with its source key.
     *
     * @param Closure(mixed, mixed): mixed $keySelector normalized by ALinqCallable::withKey()
     * @return Generator<array-key, mixed>
     */
    public static function setByElements(iterable $items, iterable $second, Closure $keySelector, bool $keepWhenPresent, IALinqEqualityComparer $comparer): Generator
    {
        $keys = static function () use ($second, $keySelector): Generator {
            foreach ($second as $key => $item) {
                yield $keySelector($item, $key);
            }
        };

        return self::setByKeysUnder($comparer, $items, $keys(), $keySelector, $keepWhenPresent);
    }

    /**
     * unionBy() under a comparer (2.4.0): the items of every sequence, in order, whose key was not
     * seen yet (the first occurrence of each key), without their source keys.
     *
     * @param list<iterable> $sequences
     * @param Closure(mixed, mixed): mixed $keySelector normalized by ALinqCallable::withKey()
     * @return Generator<int, mixed>
     */
    public static function union(array $sequences, Closure $keySelector, IALinqEqualityComparer $comparer): Generator
    {
        $seen = new ALinqKeySet($comparer);
        foreach ($sequences as $sequence) {
            foreach ($sequence as $key => $item) {
                if ($seen->add($keySelector($item, $key))) {
                    yield $item;
                }
            }
        }
    }

    /**
     * implode(): the text of each item (or of the selected value) joined by $separator. A
     * string, an int, a float (PHP's own text) or a Stringable is accepted; null is the empty
     * text (as `string.Join`); anything else throws instead of PHP's `"1"`/`""` for a bool or
     * its "Array to string conversion" warning. Nothing to join is ''.
     *
     * @param Closure(mixed, mixed): mixed|null $selector normalized by ALinqCallable::withKey()
     * @throws InvalidArgumentException For a bool, an array or an object that is not Stringable
     */
    public static function implode(iterable $items, string $separator, ?Closure $selector): string
    {
        $text = '';
        $first = true;
        foreach ($items as $key => $item) {
            $value = $selector === null ? $item : $selector($item, $key);
            if ($value === null) {
                $piece = '';
            } elseif (is_string($value) || is_int($value) || is_float($value) || $value instanceof Stringable) {
                $piece = (string) $value;
            } else {
                throw new InvalidArgumentException(sprintf(
                    'implode() expects strings, numbers, Stringable or null, %s found at key %s',
                    get_debug_type($value),
                    var_export($key, true)
                ));
            }
            $text .= $first ? $piece : $separator . $piece;
            $first = false;
        }

        return $text;
    }
}
