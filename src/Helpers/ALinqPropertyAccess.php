<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Helpers;

use ArrayAccess;
use Closure;
use ReflectionProperty;
use Throwable;

/**
 * ALinqPropertyAccess
 *
 * Helper class for property/method access on objects and arrays.
 *
 * Resolution order (mirrors the PropertyAccessor of antevemus/aspecification, so that
 * both libraries read the same value from the same candidate):
 *
 *  1. Arrays and ArrayAccess: `$target[$property] ?? null`.
 *  2. Objects, in this order:
 *     a. a public, callable method `getX()`, `x()`, `isX()` or `hasX()` (methods win over
 *        public properties, so an entity with `private $age` + `getAge()` resolves and a
 *        `public $active` paired with `isActive()` reads the method);
 *     b. an accessible, non-null property (`isset`, which also honours `__get` + `__isset`);
 *     c. a declared PUBLIC property (static or instance) that is initialized (reflection);
 *        private/protected properties, uninitialized typed properties and private getters
 *        resolve to `null` instead of raising an Error.
 *  3. Anything else (scalars, null, missing member) resolves to `null`.
 *
 * Dot notation: a property name containing `.` is always a nested path (`user.address.city`),
 * also inside getValue(). A literal array key such as `'a.b'` is therefore NOT addressable
 * through this helper; it is read as `$target['a']['b']`. This follows the ASpecification
 * accessor on purpose (review 2026-10-08, decision 3a).
 *
 * @version    1.3.1
 * @package    antevemus
 * @subpackage alinq.helpers
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
class ALinqPropertyAccess
{
    /**
     * Get a property value (or a nested dot-notation path) from an object or array
     *
     * @param mixed $item The item to access
     * @param string $property The property name or dot-notation path (e.g. "user.address.city")
     * @return mixed The property value, or null when it cannot be resolved
     */
    public static function getValue(mixed $item, string $property): mixed
    {
        if ($item === null) {
            return null;
        }

        if (str_contains($property, '.')) {
            return self::getNestedValue($item, $property);
        }

        return self::extractSingleProperty($item, $property);
    }

    /**
     * Check whether a property (or a nested dot-notation path) exists on the target.
     *
     * Existence follows the same resolution order as getValue(): a public getter-style method,
     * an accessible (`isset`) property, a declared public property, or an array/ArrayAccess key.
     * An array key whose value is null still exists; a missing key, a private property without
     * getter or a non-object/non-array target does not.
     *
     * @param mixed $item The item to inspect
     * @param string $property The property name or dot-notation path
     * @return bool
     */
    public static function hasProperty(mixed $item, string $property): bool
    {
        if ($item === null) {
            return false;
        }

        if (str_contains($property, '.')) {
            $current = $item;

            foreach (explode('.', $property) as $segment) {
                if ($current === null || !self::hasSingleProperty($current, $segment)) {
                    return false;
                }

                $current = self::extractSingleProperty($current, $segment);
            }

            return true;
        }

        return self::hasSingleProperty($item, $property);
    }

    /**
     * Create a property accessor function
     *
     * @param string $property The property to access (dot notation allowed)
     * @return callable A function that extracts the property
     */
    public static function getPropertyAccessor(string $property): Closure
    {
        return fn(mixed $item): mixed => self::getValue($item, $property);
    }

    /**
     * Create a nested property accessor function
     *
     * @param string $path The property path (e.g. "user.address.city")
     * @return callable A function that extracts the nested property
     */
    public static function getNestedPropertyAccessor(string $path): Closure
    {
        return fn(mixed $item): mixed => $item === null ? null : self::getNestedValue($item, $path);
    }

    /**
     * Create a comparison function for two properties
     *
     * @param string $property1 First property to compare
     * @param string $property2 Second property to compare
     * @return callable A function that compares the two properties
     */
    public static function createPropertyComparer(string $property1, string $property2): Closure
    {
        $accessor1 = self::getPropertyAccessor($property1);
        $accessor2 = self::getPropertyAccessor($property2);

        return fn(mixed $item): bool => $accessor1($item) == $accessor2($item);
    }

    /**
     * Candidate method names for a property, in resolution order.
     *
     * @return string[]
     */
    private static function candidateMethods(string $property): array
    {
        $suffix = ucfirst($property);

        return ['get' . $suffix, $property, 'is' . $suffix, 'has' . $suffix];
    }

    /**
     * Extract a single (non-dotted) property from an object or array.
     */
    private static function extractSingleProperty(mixed $target, string $property): mixed
    {
        if (is_array($target) || $target instanceof ArrayAccess) {
            return $target[$property] ?? null;
        }

        if (!is_object($target)) {
            return null;
        }

        foreach (self::candidateMethods($property) as $method) {
            if (method_exists($target, $method) && is_callable([$target, $method])) {
                return $target->$method();
            }
        }

        if (isset($target->{$property})) {
            return $target->{$property};
        }

        if (property_exists($target, $property)) {
            try {
                $reflection = new ReflectionProperty($target, $property);

                if ($reflection->isPublic() && $reflection->isInitialized($target)) {
                    return $reflection->getValue($target);
                }
            } catch (Throwable) {
                // Unresolvable through reflection: treat as missing
            }
        }

        return null;
    }

    /**
     * Check whether a single (non-dotted) property exists on an object or array.
     */
    private static function hasSingleProperty(mixed $target, string $property): bool
    {
        if (is_array($target)) {
            return array_key_exists($property, $target);
        }

        if ($target instanceof ArrayAccess) {
            return isset($target[$property]);
        }

        if (!is_object($target)) {
            return false;
        }

        foreach (self::candidateMethods($property) as $method) {
            if (method_exists($target, $method) && is_callable([$target, $method])) {
                return true;
            }
        }

        if (isset($target->{$property})) {
            return true;
        }

        if (property_exists($target, $property)) {
            try {
                $reflection = new ReflectionProperty($target, $property);

                return $reflection->isPublic();
            } catch (Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Walk a dot-separated path segment by segment.
     */
    private static function getNestedValue(mixed $target, string $path): mixed
    {
        $current = $target;

        foreach (explode('.', $path) as $segment) {
            if ($current === null) {
                return null;
            }

            $current = self::extractSingleProperty($current, $segment);
        }

        return $current;
    }
}
