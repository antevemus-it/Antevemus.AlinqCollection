<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

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
 * @version    1.3.1
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
     * Convert to dictionary (ToDictionary in LINQ)
     *
     * @throws \InvalidArgumentException when the key selector returns something other than int, string or BackedEnum,
     *         or produces the same key twice
     */
    public function toDictionary(callable $keySelector, ?callable $elementSelector = null): array;

    /**
     * Convert collection to an object
     */
    public function toObject(): stdClass;

    /**
     * Flip keys and values in the collection
     */
    public function flip(): IALinqCollection;
}
