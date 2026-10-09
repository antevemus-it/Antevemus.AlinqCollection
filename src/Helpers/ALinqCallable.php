<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Helpers;

use Closure;
use ReflectionFunction;

/**
 * ALinqCallable - Shared rules for user callbacks and element identity
 *
 * Two rules every operator of the eager and the lazy collection follows since the
 * 2026-10-08 review (decision 4a, bugs #37 and #38):
 *
 * - withKey(): a callback receives `($item, $key)` only when it accepts two parameters;
 *   a one-parameter callback (`fn($v) => ...`, `'is_int'`, `'strtoupper'`, `strlen(...)`)
 *   receives the item alone. Before, `array_filter(..., ARRAY_FILTER_USE_BOTH)`,
 *   `array_find()` and `array_any()` pushed the key into native functions
 *   (`ArgumentCountError: is_int() expects exactly 1 argument, 2 given`), while
 *   `array_map()` and the eager selectors never passed it, so the same callable worked
 *   in one operator and crashed in the next, and the lazy side always passed both.
 *
 * - hashKey(): a type-aware identity string for distinct(), so `1`, `'1'`, `true` and
 *   `1.0` stay distinct, arrays are compared by value, objects by identity, and no
 *   "Array to string conversion" warning is emitted (bug #39).
 *
 * @version    1.3.1
 * @package    antevemus
 * @subpackage alinq.helpers
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class ALinqCallable
{
    /**
     * Normalizes a callback to the `($item, $key)` shape: the key is forwarded only when the
     * callback accepts a second parameter (declared parameters for user code, required
     * parameters for internal functions such as `intval()`, whose optional second argument
     * is not a key; variadics accept it).
     *
     * @param callable $callback
     * @return Closure(mixed, mixed): mixed
     */
    public static function withKey(callable $callback): Closure
    {
        $closure = $callback instanceof Closure ? $callback : Closure::fromCallable($callback);

        if (self::acceptsKey($closure)) {
            return $closure;
        }

        return static fn(mixed $item, mixed $key = null): mixed => $closure($item);
    }

    /**
     * Whether the callback accepts a second positional argument.
     *
     * @param Closure $closure
     * @return bool
     */
    public static function acceptsKey(Closure $closure): bool
    {
        $reflection = new ReflectionFunction($closure);
        if ($reflection->isVariadic()) {
            return true;
        }

        $parameters = $reflection->isInternal()
            ? $reflection->getNumberOfRequiredParameters()
            : $reflection->getNumberOfParameters();

        return $parameters >= 2;
    }

    /**
     * Type-aware identity of a value, for de-duplication by strict equality.
     *
     * Scalars carry their type; floats are formatted to survive `0.1 + 0.2`; NAN is its own
     * bucket (every NAN is "equal" here, unlike `===`, so distinct() does not keep them all);
     * arrays are compared by value through serialization; objects by identity.
     *
     * @param mixed $value
     * @return string
     */
    public static function hashKey(mixed $value): string
    {
        return match (true) {
            $value === null => 'n',
            is_bool($value) => $value ? 'b1' : 'b0',
            is_int($value) => 'i' . $value,
            is_float($value) => is_nan($value) ? 'fNAN' : 'f' . var_export($value, true),
            is_string($value) => 's' . $value,
            is_object($value) => 'o' . spl_object_id($value),
            is_array($value) => 'a' . serialize($value),
            default => 'r' . gettype($value) . ':' . (string) @(int) $value,
        };
    }
}
