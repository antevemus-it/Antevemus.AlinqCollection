<?php

declare(strict_types=1);

namespace Antevemus\ALinq;

use Antevemus\ALinq\Helpers\ALinqCallable;
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
 * Contract (1.3.0): a list (array_is_list) is reindexed by filtering and reordering
 * operators, a dictionary keeps its keys; set operations compare by strict, type-aware
 * identity; an empty collection throws on first/last/min/max/minBy/maxBy/average;
 * json_encode() serializes the items (JsonSerializable). Since 1.4.0 an orderBy*() result
 * carries its pending ordering, so thenBy*() can refine it.
 *
 * @version    1.4.0
 * @package    antevemus
 * @subpackage alinq
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
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

    /**
     * The ordering pending on a collection returned by orderBy*()/thenBy*() (1.4.0): the
     * source items before ordering and the criteria already applied, so thenBy*() can append
     * one. Null on every other collection, where thenBy*() throws LogicException.
     *
     * @var array{0: array, 1: list<array{0: list<mixed>, 1: int, 2: callable|null}>}|null
     */
    private ?array $pendingOrdering = null;

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
     * Number of items, or of the items that satisfy the predicate (Count in LINQ)
     *
     * Before 1.3.0 the predicate was silently discarded (review 2026-10-08, 3.6).
     *
     * @param callable|null $predicate `fn($item)` or `fn($item, $key)`
     */
    public function count(?callable $predicate = null): int
    {
        if ($predicate === null) {
            return count($this->items);
        }

        $predicate = ALinqCallable::withKey($predicate);
        $total = 0;
        foreach ($this->items as $key => $item) {
            if ($predicate($item, $key)) {
                $total++;
            }
        }
        return $total;
    }

    /**
     * Implement JsonSerializable: json_encode() of a list is a JSON array, of a dictionary
     * a JSON object. Before 1.3.0 the private items made json_encode() return "{}".
     */
    public function jsonSerialize(): array
    {
        return $this->items;
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

    /**
     * Convert this in-memory collection into a Generator-based lazy streaming pipeline.
     *
     * @return ALinqLazyCollection
     */
    public function lazy(): ALinqLazyCollection
    {
        return ALinqLazyCollection::from($this->items);
    }

    /**
     * Create a lazy streaming collection that reads a file line-by-line with O(1) memory.
     *
     * @param string $filePath Path to the file.
     * @param int $bufferSize Buffer size for fgets.
     * @param callable|null $lineParser Optional parser fn($line, $index): mixed.
     * @return ALinqLazyCollection
     */
    public static function fromFile(string $filePath, int $bufferSize = 4096, ?callable $lineParser = null): ALinqLazyCollection
    {
        return ALinqLazyCollection::fromFile($filePath, $bufferSize, $lineParser);
    }

    /**
     * Create a lazy streaming collection from a CSV file.
     *
     * @param string $filePath
     * @param string $separator
     * @param string $enclosure
     * @param string $escape
     * @param bool $hasHeader
     * @param bool $strict With a header, a record whose width differs from the header throws
     *                     when read (true); false hands it over as a list (1.3.1 behaviour)
     * @return ALinqLazyCollection
     */
    public static function fromCsv(
        string $filePath,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $hasHeader = true,
        bool $strict = true
    ): ALinqLazyCollection {
        return ALinqLazyCollection::fromCsv($filePath, $separator, $enclosure, $escape, $hasHeader, $strict);
    }

    /**
     * Create a lazy streaming collection from a database PDOStatement cursor.
     *
     * @param \PDOStatement $statement
     * @param callable|null $rowMapper
     * @return ALinqLazyCollection
     */
    public static function fromCursor(\PDOStatement $statement, ?callable $rowMapper = null): ALinqLazyCollection
    {
        return ALinqLazyCollection::fromCursor($statement, $rowMapper);
    }
}

