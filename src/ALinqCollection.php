<?php

namespace Antevemus\ALinq;

use Antevemus\ALinq\Interfaces\IALinqCollection;
use Antevemus\ALinq\Traits\FilteringOperations;
use Antevemus\ALinq\Traits\JoiningOperations;
use Antevemus\ALinq\Traits\AggregationOperations;
use Antevemus\ALinq\Traits\SelectionOperations;
use Antevemus\ALinq\Traits\GroupingOperations;
use Antevemus\ALinq\Traits\OrderingOperations;
use Antevemus\ALinq\Traits\IteratorOperations;
use Antevemus\ALinq\Traits\UtilityOperations;
use ArrayIterator;
use Traversable;

/**
 * ALinqCollection
 *
 * A comprehensive LINQ-style collection class leveraging PHP 8.4 array functions
 *
 * @version    0.1
 * @package    antevemus
 * @subpackage alinq
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class ALinqCollection implements IALinqCollection
{
    use FilteringOperations;
    use JoiningOperations;
    use AggregationOperations;
    use SelectionOperations;
    use GroupingOperations;
    use OrderingOperations;
    use IteratorOperations;
    use UtilityOperations;

    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = $items ?? [];
    }

    /**
     * Implement IteratorAggregate interface
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * Implement Countable interface
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Create a new collection from an array
     */
    public static function from(array $items): self
    {
        return new self($items);
    }

    /**
     * Create a collection from a range of numbers
     */
    public static function range(int $start, int $end, int $step = 1): self
    {
        return new self(range($start, $end, $step));
    }

    /**
     * Get all items as array (ToArray in LINQ)
     */
    public function toArray(): array
    {
        return $this->items;
    }

    /**
     * Create an empty collection
     *
     * @return self A new empty collection
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Create a collection with repeated elements
     *
     * @param mixed $element The element to repeat
     * @param int $count The number of times to repeat the element
     * @return self A new collection with the repeated element
     */
    public static function repeat($element, int $count): self
    {
        if ($count < 0) {
            throw new \InvalidArgumentException("Count cannot be negative");
        }

        return new self(array_fill(0, $count, $element));
    }
}
