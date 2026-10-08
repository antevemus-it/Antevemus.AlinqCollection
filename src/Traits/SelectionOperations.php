<?php

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Interfaces\IALinqCollection;
use stdClass;

/**
 * SelectionOperations Trait
 *
 * Provides LINQ-style selection and projection operations for collections
 *
 * @version    0.1.0
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
        return new self(array_map($selector, $this->items));
    }

    /**
     * Project elements and flatten results (SelectMany in LINQ)
     */
    public function selectMany(callable $selector): IALinqCollection
    {
        $result = [];
        foreach ($this->items as $item) {
            $selected = $selector($item);
            if (is_array($selected)) {
                foreach ($selected as $subItem) {
                    $result[] = $subItem;
                }
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
     */
    public function toDictionary(callable $keySelector, ?callable $elementSelector = null): array
    {
        $result = [];
        $elementSelector = $elementSelector ?? fn($item) => $item;

        foreach ($this->items as $item) {
            $key = $keySelector($item);
            $result[$key] = $elementSelector($item);
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
