<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqGroupable
 *
 * Interface for grouping operations on collections.
 *
 * Since 2.4.0 groupBy() takes an optional IALinqEqualityComparer for the keys. Classes that
 * implement this interface on their own must add the parameter.
 *
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqGroupable extends IALinqBaseCollection
{
    /**
     * Group elements by key (GroupBy in LINQ): a collection keyed by the group key whose
     * values are ALinqCollection instances
     *
     * @param callable $keySelector `fn($item)` or `fn($item, $key)`, returning int, string or BackedEnum
     * @param IALinqEqualityComparer|null $comparer The identity of the keys (since 2.4.0); the first key seen names the group
     * @throws \InvalidArgumentException when the key selector returns any other type
     */
    public function groupBy(callable $keySelector, ?IALinqEqualityComparer $comparer = null): IALinqCollection;
}
