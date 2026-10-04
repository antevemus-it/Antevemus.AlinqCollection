<?php

namespace Antevemus\ALinq\Interfaces;

use Traversable;

/**
 * IALinqBaseCollection
 *
 * Core interface for LINQ-style collection operations
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqBaseCollection extends \Countable, \IteratorAggregate
{
    /**
     * Create an empty collection
     */
    public static function empty(): self;

    /**
     * Create a collection with repeated elements
     */
    public static function repeat($element, int $count): self;

    /**
     * Create a new collection from an array
     */
    public static function from(array $items): self;

    /**
     * Create a collection from a range of numbers
     */
    public static function range(int $start, int $end, int $step = 1): self;

    /**
     * Get all items as array (ToArray in LINQ)
     */
    public function toArray(): array;

    /**
     * Implement IteratorAggregate interface
     */
    public function getIterator(): Traversable;

    /**
     * Implement Countable interface
     */
    public function count(): int;
}