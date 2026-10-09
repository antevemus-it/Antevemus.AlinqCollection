<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Helpers;

use BackedEnum;
use Closure;
use DateTimeInterface;
use InvalidArgumentException;

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
 * @version    1.3.0
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
}
