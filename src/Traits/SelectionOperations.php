<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\ALinqLookup;
use Antevemus\ALinq\DuplicateKeyPolicy;
use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;
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
 * Since 2.2.0 (forward 047): toDictionary() takes a DuplicateKeyPolicy and toLookup() returns
 * an ALinqLookup, both on the implementation shared with the lazy side
 * (ALinqContract::dictionary(), ALinqContract::lookup()). Since 2.4.0 both take an
 * IALinqEqualityComparer for the keys.
 *
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
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
     * The key must be an int, a string or a BackedEnum (RN-07). By default it must be unique:
     * a key produced twice throws instead of silently overwriting the first element (RN-08,
     * as ToDictionary does in LINQ). Since 2.2.0 `$onDuplicate` may choose
     * DuplicateKeyPolicy::KeepFirst (the element selector does not run for the repeats) or
     * DuplicateKeyPolicy::KeepLast (the key stays at the position of its first occurrence).
     * A key is a PHP array key: `1` and `'1'` are the same key. Since 2.4.0 a comparer may
     * decide which keys are equal: the first key seen is the one kept (under KeepLast it holds
     * the element of the last item equal to it).
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @param callable|null $elementSelector `fn($item)` or `fn($item, $key)`; the item when null
     * @param DuplicateKeyPolicy $onDuplicate What a repeated key does (since 2.2.0)
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0)
     * @throws InvalidArgumentException when a key is not int/string/BackedEnum or, under Throw, is produced twice
     */
    public function toDictionary(callable $keySelector, ?callable $elementSelector = null, DuplicateKeyPolicy $onDuplicate = DuplicateKeyPolicy::Throw, ?IALinqEqualityComparer $comparer = null): array
    {
        return ALinqContract::dictionary(
            $this->items,
            ALinqCallable::withKey($keySelector),
            $elementSelector === null ? null : ALinqCallable::withKey($elementSelector),
            $onDuplicate,
            $comparer
        );
    }

    /**
     * Convert to a lookup, a one-to-many dictionary (ToLookup in LINQ, since 2.2.0)
     *
     * A groupBy() already materialized: `get($key)` gives the elements of a key, or an empty
     * collection for a missing key. The key follows groupBy(): an int, a string or a
     * BackedEnum; null and every other type throw (unlike .NET, which accepts a null key: a
     * lookup answers as groupBy() does). Since 2.4.0 a comparer may decide which keys are equal:
     * the first key seen names the group, and get()/contains() of the lookup use the comparer.
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @param callable|null $elementSelector `fn($item)` or `fn($item, $key)`; the item when null
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0)
     * @throws InvalidArgumentException when a key is not int, string or BackedEnum
     */
    public function toLookup(callable $keySelector, ?callable $elementSelector = null, ?IALinqEqualityComparer $comparer = null): ALinqLookup
    {
        return ALinqContract::lookup(
            $this->items,
            ALinqCallable::withKey($keySelector),
            $elementSelector === null ? null : ALinqCallable::withKey($elementSelector),
            $comparer
        );
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
