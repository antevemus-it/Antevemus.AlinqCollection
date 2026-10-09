<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqCollection
 *
 * Comprehensive interface that combines all LINQ-style collection operations
 *
 * @version    1.3.1
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqCollection extends
    IALinqBaseCollection,
    IALinqFilterable,
    IALinqJoinable,
    IALinqAggregatable,
    IALinqSelectable,
    IALinqGroupable,
    IALinqOrderable,
    IALinqIterator,
    IALinqUtility
{
}