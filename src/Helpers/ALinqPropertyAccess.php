<?php

namespace Antevemus\ALinq\Helpers;

use Closure;

/**
 * ALinqPropertyAccess
 *
 * Helper class for property/method access on objects
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq.helpers
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
class ALinqPropertyAccess
{
    /**
     * Get a property value from an object or array
     *
     * @param object|array $item The item to access
     * @param string $property The property name
     * @return mixed The property value
     */
    public static function getValue($item, string $property)
    {
        if (is_array($item)) {
            return $item[$property] ?? null;
        }

        if (is_object($item)) {
            // Try direct property access
            if (property_exists($item, $property)) {
                return $item->$property;
            }

            // Try getter method
            $getter = 'get' . ucfirst($property);
            if (method_exists($item, $getter)) {
                return $item->$getter();
            }

            // Try has/is method for boolean properties
            $isMethod = 'is' . ucfirst($property);
            if (method_exists($item, $isMethod)) {
                return $item->$isMethod();
            }

            $hasMethod = 'has' . ucfirst($property);
            if (method_exists($item, $hasMethod)) {
                return $item->$hasMethod();
            }
        }

        return null;
    }

    /**
     * Create a property accessor function
     *
     * @param string $property The property to access
     * @return callable A function that extracts the property
     */
    public static function getPropertyAccessor(string $property): Closure
    {
        return fn($item) => self::getValue($item, $property);
    }

    /**
     * Create a nested property accessor function
     *
     * @param string $path The property path (e.g. "user.address.city")
     * @return callable A function that extracts the nested property
     */
    public static function getNestedPropertyAccessor(string $path): Closure
    {
        $segments = explode('.', $path);

        return function($item) use ($segments) {
            $current = $item;

            foreach ($segments as $segment) {
                if ($current === null) {
                    return null;
                }

                $current = self::getValue($current, $segment);
            }

            return $current;
        };
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

        return fn($item) => $accessor1($item) == $accessor2($item);
    }
}
