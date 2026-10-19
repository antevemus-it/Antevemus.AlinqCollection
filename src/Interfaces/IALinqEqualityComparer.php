<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

/**
 * IALinqEqualityComparer - Equality with a compatible hash (the `IEqualityComparer<T>` of .NET)
 *
 * Since 2.2.0 (forward 047, D3). distinct() and distinctBy() of both collections accept one
 * as their identity: values are bucketed by hash() and, inside a bucket, compared with
 * equals(), so the operation stays O(n) and the first occurrence wins.
 *
 * Contract every implementation must honour: equal values have the same hash
 * (`equals($a, $b)` implies `hash($a) === hash($b)`). Two values with the same hash need not
 * be equal (equals() decides). `Antevemus\ALinq\Comparison\StringComparer` implements it.
 *
 * @version    2.2.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqEqualityComparer
{
    /**
     * Whether the two values are equal under this comparer.
     */
    public function equals(mixed $a, mixed $b): bool;

    /**
     * A hash compatible with equals(): equal values have the same hash.
     */
    public function hash(mixed $value): string;
}
