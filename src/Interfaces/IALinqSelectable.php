<?php

namespace Antevemus\ALinq\Interfaces;

use stdClass;

/**
 * IALinqSelectable
 *
 * Interface for selection operations on collections
 *
 * @version    0.1.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqSelectable extends IALinqBaseCollection
{
    /**
     * Project elements into a new form (Select in LINQ)
     */
    public function select(callable $selector): IALinqCollection;

    /**
     * Project elements and flatten results (SelectMany in LINQ)
     */
    public function selectMany(callable $selector): IALinqCollection;

    /**
     * Extract a column from a collection of arrays or objects
     */
    public function column(string|int $columnKey, string|int|null $indexKey = null): IALinqCollection;

    /**
     * Convert to dictionary (ToDictionary in LINQ)
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