<?php

namespace Antevemus\ALinq\Traits;

use Antevemus\ALinq\Interfaces\IALinqCollection;

/**
 * GroupingOperations Trait
 *
 * Provides LINQ-style grouping operations for collections
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq.traits
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
trait GroupingOperations
{
    /**
     * Group elements by key (GroupBy in LINQ)
     */
    public function groupBy(callable $keySelector): IALinqCollection
    {
        $result = [];
        foreach ($this->items as $item) {
            $key = $keySelector($item);
            if (!isset($result[$key])) {
                $result[$key] = [];
            }
            $result[$key][] = $item;
        }
        return new self($result);
    }
}
