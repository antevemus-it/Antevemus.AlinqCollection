<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Helpers;

use Antevemus\ADeepPropertyAccess\PropertyAccess;
use Closure;

/**
 * ALinqPropertyAccess - Deprecated facade over the shared property accessor, plus the comparer
 *
 * Since 2.0.0 the property resolution of this library lives in `antevemus/adeep-property-access`
 * (`Antevemus\ADeepPropertyAccess\PropertyAccess`), the package shared with ASpecification: both
 * libraries read the same value from the same candidate by construction. Every internal reader of
 * the library (the query builder, `createPropertySelector()`, `whereIn()`/`whereNotIn()`/
 * `whereBetween()` on both collections) calls the package directly. getValue(), hasProperty(),
 * getPropertyAccessor() and getNestedPropertyAccessor() only delegate, so that code calling them
 * keeps working; createPropertyComparer() belongs to this library and is not deprecated.
 *
 * Resolution order of a single segment, first match wins (the package's contract):
 *   1. array key, or `ArrayAccess` offset (existence through `offsetExists()`);
 *   2. public method `getX()`, `x()`, `isX()`, `hasX()` without required parameters (never
 *      `__call()`, never a name starting with `__`);
 *   3. `isset($object->x)`: public initialized property, dynamic property, `__get` guarded by
 *      `__isset`;
 *   4. declared public property, static or instance (an uninitialized typed property yields null);
 *   5. private or protected property marked `#[Specifiable]` (this library's attribute,
 *      ASpecification's or the package's, recognized by name), on the property or on the class
 *      that declares it, declared by the class or an ancestor;
 *   6. null.
 * A path with a dot is always a path (a literal key `'a.b'` is read as `$target['a']['b']`). An
 * unmarked private or protected property is not resolved: the name may come from a request, and
 * without the author's opt-in filtering or ordering over a private field would work as a read
 * oracle for its value.
 *
 * Answers that changed with the package (2.0.0): a public method with a required parameter is not
 * a getter (it threw `ArgumentCountError` from getValue() while hasProperty() answered true); a
 * private getter of a class with `__call()` is never reached through `__call()`; a name starting
 * with `__` is never called (`'__construct'` ran the constructor again); an empty name no longer
 * calls a public `get()`, `is()` or `has()`; a key of an `ArrayObject`/`ArrayIterator` holding null
 * exists for hasProperty(), as in an array; `#[Antevemus\ADeepPropertyAccess\Attributes\Specifiable]`
 * is accepted as the marker too.
 *
 * No runtime notice is raised: the facade sits on the hot path of every query that names a field.
 *
 * @version    2.0.0
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
     * @deprecated 2.0.0 Removed in 3.0.0; use Antevemus\ADeepPropertyAccess\PropertyAccess::getValue()
     */
    public static function getValue(mixed $item, string $property): mixed
    {
        return PropertyAccess::getValue($item, $property);
    }

    /**
     * Check whether a property (or a nested dot-notation path) exists on the target
     *
     * @param mixed $item The item to inspect
     * @param string $property The property name or dot-notation path
     * @return bool
     * @deprecated 2.0.0 Removed in 3.0.0; use Antevemus\ADeepPropertyAccess\PropertyAccess::hasProperty()
     */
    public static function hasProperty(mixed $item, string $property): bool
    {
        return PropertyAccess::hasProperty($item, $property);
    }

    /**
     * Create a property accessor function
     *
     * @param string $property The property to access (dot notation allowed)
     * @return Closure(mixed): mixed A function that reads the property
     * @deprecated 2.0.0 Removed in 3.0.0; use Antevemus\ADeepPropertyAccess\PropertyAccess::accessor()
     */
    public static function getPropertyAccessor(string $property): Closure
    {
        return PropertyAccess::accessor($property);
    }

    /**
     * Create a nested property accessor function (the same as getPropertyAccessor(): a dot is
     * always a path)
     *
     * @param string $path The property path (e.g. "user.address.city")
     * @return Closure(mixed): mixed A function that reads the nested property
     * @deprecated 2.0.0 Removed in 3.0.0; use Antevemus\ADeepPropertyAccess\PropertyAccess::accessor()
     */
    public static function getNestedPropertyAccessor(string $path): Closure
    {
        return PropertyAccess::accessor($path);
    }

    /**
     * Create a comparison function for two properties of the same item (loose equality, `==`),
     * both read through the shared accessor
     *
     * @param string $property1 First property to compare (dot notation allowed)
     * @param string $property2 Second property to compare (dot notation allowed)
     * @return Closure(mixed): bool A function that compares the two properties
     */
    public static function createPropertyComparer(string $property1, string $property2): Closure
    {
        $accessor1 = PropertyAccess::accessor($property1);
        $accessor2 = PropertyAccess::accessor($property2);

        return static fn(mixed $item): bool => $accessor1($item) == $accessor2($item);
    }
}
