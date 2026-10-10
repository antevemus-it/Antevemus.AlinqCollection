<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Helpers;

use Antevemus\ALinq\Attributes\Specifiable;
use ArrayAccess;
use Closure;
use ReflectionAttribute;
use ReflectionClass;
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
 *     d. as the last resort, a private or protected property declared by the class or one
 *        of its ancestors (reflection, the PropertyAccessor of ASpecification, Domian
 *        `ReflectionUtils.getFieldByName()`), read without calling any method, ONLY when the
 *        author of the class opted in with `#[Specifiable]` (1.4.1): on the property itself,
 *        or on the class that declares it (a class-level mark covers the properties declared
 *        by that class only, not those of its parents or subclasses).
 *        `#[Antevemus\ASpecification\Attributes\Specifiable]` counts as the same mark,
 *        recognized by name. An uninitialized marked property resolves to `null`; an unmarked
 *        one resolves to `null` too, as in 1.3.x. 1.4.0 read every non-public property; 1.4.1
 *        made the step opt-in because the library only receives a name as text and cannot
 *        tell the entity author's code from a name that came from a request, so filtering or
 *        ordering over a private field (`whereBetween('password_hash', 'a', 'b')`) would be a
 *        read oracle for its value.
 *     Uninitialized typed properties and private getters resolve to `null` instead of
 *     raising an Error.
 *  3. Anything else (scalars, null, missing member) resolves to `null`.
 *
 * Dot notation: a property name containing `.` is always a nested path (`user.address.city`),
 * also inside getValue(). A literal array key such as `'a.b'` is therefore NOT addressable
 * through this helper; it is read as `$target['a']['b']`. This follows the ASpecification
 * accessor on purpose (review 2026-10-08, decision 3a).
 *
 * @version    1.4.1
 * @package    antevemus
 * @subpackage alinq.helpers
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
class ALinqPropertyAccess
{
    /**
     * Attributes that opt a non-public property into the last resolution step (1.4.1),
     * compared by name so that ALinq does not depend on antevemus/aspecification.
     */
    private const SPECIFIABLE_ATTRIBUTES = [
        Specifiable::class,
        'Antevemus\\ASpecification\\Attributes\\Specifiable',
    ];

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
     * an accessible (`isset`) property, a declared public property, a private or protected
     * property declared by the class or an ancestor and marked `#[Specifiable]` (1.4.1), or
     * an array/ArrayAccess key. An array key whose value is null still exists; a missing key,
     * an unmarked non-public property, a private getter without a property or a
     * non-object/non-array target does not.
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

        // Last resort: a non-public property of the class or an ancestor, only when marked
        // #[Specifiable] (opt-in since 1.4.1).
        $field = self::specifiableField($target, $property);
        if ($field !== null) {
            try {
                if ($field->isStatic()) {
                    return $field->getValue();
                }

                return $field->isInitialized($target) ? $field->getValue($target) : null;
            } catch (Throwable) {
                // Unresolvable through reflection: treat as missing
            }
        }

        return null;
    }

    /**
     * The property declared under this name by the class of $target or by the nearest
     * ancestor that declares it, whatever its visibility (a private property of a parent
     * class is found too); null when no class in the hierarchy declares it. Mirrors
     * ReflectionUtils::getFieldByName() of ASpecification.
     */
    private static function declaredField(object $target, string $property): ?ReflectionProperty
    {
        if ($property === '') {
            return null;
        }

        $class = new ReflectionClass($target);
        while ($class !== false) {
            if ($class->hasProperty($property)) {
                $field = $class->getProperty($property);
                if ($field->getDeclaringClass()->getName() === $class->getName()) {
                    return $field;
                }
            }
            $class = $class->getParentClass();
        }

        return null;
    }

    /**
     * The non-public property declared under this name by the class of $target or an
     * ancestor, provided its author opted in with `#[Specifiable]` on the property or on the
     * class that declares it (1.4.1); null otherwise.
     */
    private static function specifiableField(object $target, string $property): ?ReflectionProperty
    {
        $field = self::declaredField($target, $property);
        if ($field === null || $field->isPublic()) {
            return null;
        }

        if (self::hasSpecifiableMark($field->getAttributes())
            || self::hasSpecifiableMark($field->getDeclaringClass()->getAttributes())) {
            return $field;
        }

        return null;
    }

    /**
     * Whether one of the attributes is `#[Specifiable]` (ALinq's or ASpecification's).
     * Attributes are compared by name and never instantiated.
     *
     * @param ReflectionAttribute[] $attributes
     */
    private static function hasSpecifiableMark(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            foreach (self::SPECIFIABLE_ATTRIBUTES as $name) {
                if (strcasecmp(ltrim($attribute->getName(), '\\'), $name) === 0) {
                    return true;
                }
            }
        }

        return false;
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
                if ((new ReflectionProperty($target, $property))->isPublic()) {
                    return true;
                }
            } catch (Throwable) {
                return false;
            }
        }

        // Last resort: a non-public property marked #[Specifiable] (opt-in since 1.4.1).
        return self::specifiableField($target, $property) !== null;
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
