<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

use Antevemus\ALinq\ALinqLazyCollection;
use Traversable;

/**
 * IALinqBaseCollection
 *
 * Core interface for LINQ-style collection operations.
 *
 * Key rule (1.3.0 contract): a collection is a *list* when `array_is_list()` holds and a
 * *dictionary* otherwise. Filtering and reordering operators reindex a list and keep the
 * keys of a dictionary; projections keep keys; materialization never loses an item.
 *
 * @version    1.3.2
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqBaseCollection extends \Countable, \IteratorAggregate, \JsonSerializable
{
    /**
     * Create an empty collection
     */
    public static function empty(): self;

    /**
     * Create a collection with repeated elements
     *
     * @throws \InvalidArgumentException when $count is negative
     */
    public static function repeat($element, int $count): self;

    /**
     * Create a new collection from an array
     */
    public static function from(array $items): self;

    /**
     * Create a collection from a range of numbers
     *
     * @throws \InvalidArgumentException when $step is lower than 1
     */
    public static function range(int $start, int $end, int $step = 1): self;

    /**
     * Convert this in-memory collection into a Generator-based lazy streaming pipeline
     */
    public function lazy(): ALinqLazyCollection;

    /**
     * Create a lazy streaming collection that reads a file line-by-line with O(1) memory
     *
     * @param callable|null $lineParser fn($line, $index): mixed
     */
    public static function fromFile(string $filePath, int $bufferSize = 4096, ?callable $lineParser = null): ALinqLazyCollection;

    /**
     * Create a lazy streaming collection from a CSV file
     *
     * @param bool $strict With a header, a record whose width differs from the header throws
     *                     when read (true); false hands it over as a list (1.3.1 behaviour)
     */
    public static function fromCsv(
        string $filePath,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $hasHeader = true,
        bool $strict = true
    ): ALinqLazyCollection;

    /**
     * Create a lazy streaming collection from a database PDOStatement cursor
     *
     * @param callable|null $rowMapper fn($row, $index): mixed
     */
    public static function fromCursor(\PDOStatement $statement, ?callable $rowMapper = null): ALinqLazyCollection;

    /**
     * Get all items as array (ToArray in LINQ)
     */
    public function toArray(): array;

    /**
     * Implement IteratorAggregate interface
     */
    public function getIterator(): Traversable;

    /**
     * Number of items, or of the items that satisfy the predicate (Count in LINQ)
     *
     * @param callable|null $predicate `fn($item)` or `fn($item, $key)`
     */
    public function count(?callable $predicate = null): int;

    /**
     * Implement JsonSerializable: the items as they are (list → JSON array, dictionary → JSON object)
     */
    public function jsonSerialize(): array;
}
