<?php

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqGroupable
 *
 * Interface for grouping operations on collections
 *
 * @version    0.1.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqGroupable extends IALinqBaseCollection
{
    /**
     * Group elements by key (GroupBy in LINQ)
     */
    public function groupBy(callable $keySelector): IALinqCollection;
}
