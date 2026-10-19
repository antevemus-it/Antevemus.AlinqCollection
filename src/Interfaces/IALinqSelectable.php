<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

use Antevemus\ALinq\ALinqLookup;
use Antevemus\ALinq\DuplicateKeyPolicy;
use stdClass;

/**
 * IALinqSelectable
 *
 * Interface for selection operations on collections.
 *
 * Contract (1.3.0): `select` keeps the keys (one-to-one projection); `selectMany` flattens
 * any iterable and throws for anything else; dictionary keys must be int, string or
 * BackedEnum and a repeated key is an error.
 *
 * Since 2.2.0 (forward 047): `toDictionary()` takes a `DuplicateKeyPolicy` (Throw by default,
 * KeepFirst, KeepLast) and `toLookup()` returns an immutable `ALinqLookup`. Classes that
 * implement this interface on their own must add them.
 *
 * @version    2.2.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqSelectable extends IALinqBaseCollection
{
    /**
     * Project elements into a new form, keeping the keys (Select in LINQ)
     *
     * @param callable $selector `fn($item)` or `fn($item, $key)`
     */
    public function select(callable $selector): IALinqCollection;

    /**
     * Project elements and flatten results into a list (SelectMany in LINQ)
     *
     * @throws \UnexpectedValueException when the selector returns a non-iterable value
     */
    public function selectMany(callable $selector): IALinqCollection;

    /**
     * Extract a column from a collection of arrays or objects
     */
    public function column(string|int $columnKey, string|int|null $indexKey = null): IALinqCollection;

    /**
     * Convert to dictionary (ToDictionary in LINQ); `1` and `'1'` are the same key
     *
     * @param DuplicateKeyPolicy $onDuplicate What a repeated key does (since 2.2.0; Throw by default)
     * @throws \InvalidArgumentException when the key selector returns something other than int, string or BackedEnum,
     *         or, under DuplicateKeyPolicy::Throw, produces the same key twice
     */
    public function toDictionary(callable $keySelector, ?callable $elementSelector = null, DuplicateKeyPolicy $onDuplicate = DuplicateKeyPolicy::Throw): array;

    /**
     * Convert to an immutable one-to-many lookup (ToLookup in LINQ, since 2.2.0): get() of a
     * missing key is an empty collection; keys follow groupBy() (a null key throws)
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`
     * @param callable|null $elementSelector `fn($item)` or `fn($item, $key)`
     * @throws \InvalidArgumentException when the key selector returns something other than int, string or BackedEnum
     */
    public function toLookup(callable $keySelector, ?callable $elementSelector = null): ALinqLookup;

    /**
     * Convert collection to an object
     */
    public function toObject(): stdClass;

    /**
     * Flip keys and values in the collection
     */
    public function flip(): IALinqCollection;
}
