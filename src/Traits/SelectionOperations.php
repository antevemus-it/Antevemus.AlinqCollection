<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use InvalidArgumentException;
use stdClass;
use UnexpectedValueException;

/**
 * SelectionOperations Trait
 *
 * Provides LINQ-style selection and projection operations for collections.
 * Callbacks follow ALinqCallable::withKey(): `($item, $key)` when they accept two
 * parameters, the item alone otherwise (review 2026-10-08, decision 4a).
 *
 * @version    1.3.0
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait SelectionOperations
{
    /**
     * Project elements into a new form (Select in LINQ)
     */
    public function select(callable $selector): IALinqCollection
    {
        // Keys are preserved and handed to a two-parameter selector, so the README's
        // `groupBy()->select(fn(ALinqCollection $group, string $role) => ...)` works; before,
        // array_map() passed the item alone (review 2026-10-08, 2.1).
        $selector = ALinqCallable::withKey($selector);
        $result = [];
        foreach ($this->items as $key => $item) {
            $result[$key] = $selector($item, $key);
        }
        return new self($result);
    }

    /**
     * Project elements and flatten results (SelectMany in LINQ)
     *
     * The selector may return any iterable (array, ALinqCollection, Traversable, generator);
     * a non-iterable result is an error, not a silently dropped item (review 2026-10-08, 2.4).
     *
     * @throws UnexpectedValueException When the selector returns a non-iterable value
     */
    public function selectMany(callable $selector): IALinqCollection
    {
        $selector = ALinqCallable::withKey($selector);
        $result = [];
        foreach ($this->items as $key => $item) {
            $selected = $selector($item, $key);
            if (!is_iterable($selected)) {
                throw new UnexpectedValueException(sprintf(
                    'selectMany() expects the selector to return an iterable, %s returned for key %s',
                    get_debug_type($selected),
                    var_export($key, true)
                ));
            }
            foreach ($selected as $subItem) {
                $result[] = $subItem;
            }
        }
        return new self($result);
    }

    /**
     * Extract a column from a collection of arrays or objects
     */
    public function column(string|int $columnKey, string|int|null $indexKey = null): IALinqCollection
    {
        return new self(array_column($this->items, $columnKey, $indexKey));
    }

    /**
     * Convert to dictionary (ToDictionary in LINQ)
     *
     * The key must be an int, a string or a BackedEnum (RN-07) and must be unique: a key
     * produced twice throws instead of silently overwriting the first element (RN-08,
     * as ToDictionary does in LINQ).
     *
     * @throws InvalidArgumentException when a key is not int/string/BackedEnum or is produced twice
     */
    public function toDictionary(callable $keySelector, ?callable $elementSelector = null): array
    {
        $result = [];
        $keySelector = ALinqCallable::withKey($keySelector);
        $elementSelector = ALinqCallable::withKey($elementSelector ?? fn($item) => $item);

        foreach ($this->items as $itemKey => $item) {
            $key = ALinqContract::groupKey($keySelector($item, $itemKey), $itemKey, 'toDictionary');
            if (array_key_exists($key, $result)) {
                throw new InvalidArgumentException(sprintf(
                    'toDictionary() key %s produced twice (second time for item at key %s)',
                    var_export($key, true),
                    var_export($itemKey, true)
                ));
            }
            $result[$key] = $elementSelector($item, $itemKey);
        }

        return $result;
    }

    /**
     * Convert collection to an object
     */
    public function toObject(): stdClass
    {
        $obj = new stdClass();
        foreach ($this->items as $key => $value) {
            $obj->$key = $value;
        }
        return $obj;
    }

    /**
     * Flip keys and values in the collection
     */
    public function flip(): IALinqCollection
    {
        return new self(array_flip($this->items));
    }
}
