<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Interfaces;

use Antevemus\ALinq\ALinqCollection;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use PDOStatement;
use stdClass;
use Traversable;

/**
 * IALinqLazyCollection
 *
 * Interface for generator-based lazy streaming collection operations with deferred
 * execution and constant O(1) memory overhead.
 *
 * Contract (1.3.0), the same as the eager ALinqCollection: a source known to be a list
 * (array list, file, CSV, cursor, range, repeat) streams reindexed keys through filtering
 * operators and a dictionary streams its original keys; materialization never loses an
 * item; set operators use the strict, type-aware identity of `ALinqCallable::hashKey()`;
 * callbacks receive `($item, $key)` only when they accept two parameters; an empty stream
 * throws on first/last/min/max/minBy/maxBy/average; numeric aggregations skip `null`;
 * a comparer may answer `bool` or `<=>`.
 *
 * @version    1.3.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
interface IALinqLazyCollection extends Countable, IteratorAggregate, JsonSerializable
{
    /**
     * Create a lazy collection from any iterable or generator factory closure.
     *
     * @param iterable|callable $source
     * @return self
     */
    public static function from(iterable|callable $source): self;

    /**
     * Create a lazy stream that reads a file line-by-line using constant O(1) memory.
     *
     * @param string $filePath
     * @param int $bufferSize
     * @param callable|null $lineParser
     * @return self
     */
    public static function fromFile(string $filePath, int $bufferSize = 4096, ?callable $lineParser = null): self;

    /**
     * Create a lazy stream that reads a CSV file row-by-row.
     *
     * @param string $filePath
     * @param string $separator
     * @param string $enclosure
     * @param string $escape
     * @param bool $hasHeader If true, yields associative arrays keyed by header columns.
     * @return self
     */
    public static function fromCsv(
        string $filePath,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $hasHeader = true
    ): self;

    /**
     * Create a lazy stream over a single-pass PDOStatement cursor (one statement, one collection).
     *
     * @param PDOStatement $statement
     * @param callable|null $rowMapper fn($row, $index): mixed
     * @return self
     */
    public static function fromCursor(PDOStatement $statement, ?callable $rowMapper = null): self;

    /**
     * Create an empty lazy collection.
     *
     * @return self
     */
    public static function empty(): self;

    /**
     * Generate an arithmetic progression lazily without allocating array memory.
     *
     * @param int $start
     * @param int $end
     * @param int $step
     * @return self
     * @throws \InvalidArgumentException when $step is lower than 1
     */
    public static function range(int $start, int $end, int $step = 1): self;

    /**
     * Generate repeated elements lazily without allocating array memory.
     *
     * @param mixed $element
     * @param int $count
     * @return self
     * @throws \InvalidArgumentException when $count is negative
     */
    public static function repeat(mixed $element, int $count): self;

    /**
     * Filter items yielding only elements that satisfy the predicate.
     *
     * @param callable $predicate fn($item, $key): bool
     * @return self
     */
    public function where(callable $predicate): self;

    /**
     * Filter items yielding elements that do NOT satisfy the predicate.
     *
     * @param callable $predicate fn($item, $key): bool
     * @return self
     */
    public function whereNot(callable $predicate): self;

    /**
     * Transform each item using a selector closure.
     *
     * @param callable $selector fn($item, $key): mixed
     * @return self
     */
    public function select(callable $selector): self;

    /**
     * Project each item to an iterable and flatten the resulting sequences.
     *
     * @param callable $selector fn($item, $key): iterable
     * @return self
     * @throws \UnexpectedValueException when the selector returns a non-iterable value
     */
    public function selectMany(callable $selector): self;

    /**
     * Yield up to $count elements and immediately terminate the stream.
     *
     * @param int $count
     * @return self
     * @throws \InvalidArgumentException when $count is negative
     */
    public function take(int $count): self;

    /**
     * Discard the first $count elements and stream the remaining items.
     *
     * @param int $count
     * @return self
     * @throws \InvalidArgumentException when $count is negative
     */
    public function skip(int $count): self;

    /**
     * Yield elements while the predicate returns true, then stop.
     *
     * @param callable $predicate fn($item, $key): bool
     * @return self
     */
    public function takeWhile(callable $predicate): self;

    /**
     * Skip elements while the predicate returns true, then yield the rest.
     *
     * @param callable $predicate fn($item, $key): bool
     * @return self
     */
    public function skipWhile(callable $predicate): self;

    /**
     * Yield unique elements by tracking a hash set of observed keys lazily.
     *
     * @param callable|null $keySelector
     * @return self
     */
    public function distinct(?callable $keySelector = null): self;

    /**
     * Yield unique elements based on a key selector.
     *
     * @param callable $keySelector
     * @return self
     */
    public function distinctBy(callable $keySelector): self;

    /**
     * Yield chunks of $size elements (the last one may be shorter), each chunk an eager
     * ALinqCollection; inside each chunk a list is reindexed and a dictionary keeps its keys.
     *
     * @param int $size
     * @return self
     * @throws \InvalidArgumentException when $size is lower than 1
     */
    public function chunk(int $size): self;

    /**
     * Pad the stream up to a specified size with a fallback value.
     *
     * @param int $size
     * @param mixed $value
     * @return self
     * @throws \InvalidArgumentException when $size is negative
     */
    public function pad(int $size, mixed $value): self;

    /**
     * Concatenate another sequence after the current stream.
     *
     * @param iterable|callable $second
     * @return self
     */
    public function concat(iterable|callable $second): self;

    /**
     * Pairwise combine two sequences into a stream of paired elements.
     *
     * @param iterable|callable $second
     * @param callable|null $resultSelector fn($first, $second): mixed
     * @return self
     */
    public function zip(iterable|callable $second, ?callable $resultSelector = null): self;

    /**
     * Inspect elements as they stream through the pipeline without altering the stream.
     *
     * @param callable $callback fn($item, $key): void
     * @return self
     */
    public function tap(callable $callback): self;

    /**
     * Cache the evaluated items in memory so subsequent traversals do not re-run generators.
     *
     * @return self
     */
    public function remember(): self;

    /**
     * Return the first element matching the predicate with short-circuiting.
     *
     * @param callable|null $predicate
     * @return mixed
     * @throws \UnderflowException when the stream is empty or no element matches
     */
    public function first(?callable $predicate = null): mixed;

    /**
     * Return the first element matching the predicate, or fallback default if not found.
     *
     * @param mixed $default
     * @param callable|null $predicate
     * @return mixed
     */
    public function firstOrDefault(mixed $default = null, ?callable $predicate = null): mixed;

    /**
     * Return the last element matching the predicate.
     *
     * @param callable|null $predicate
     * @return mixed
     * @throws \UnderflowException when the stream is empty or no element matches
     */
    public function last(?callable $predicate = null): mixed;

    /**
     * Return the last element matching the predicate, or fallback default if not found.
     *
     * @param mixed $default
     * @param callable|null $predicate
     * @return mixed
     */
    public function lastOrDefault(mixed $default = null, ?callable $predicate = null): mixed;

    /**
     * Ensure the sequence contains at most one element (or one matching predicate).
     *
     * @param mixed $default
     * @param callable|null $predicate
     * @return mixed
     * @throws \OverflowException when more than one element matches
     */
    public function singleOrDefault(mixed $default = null, ?callable $predicate = null): mixed;

    /**
     * Check if any element satisfies the predicate with short-circuiting.
     *
     * @param callable|null $predicate
     * @return bool
     */
    public function any(?callable $predicate = null): bool;

    /**
     * Check if all elements satisfy the predicate with short-circuiting; vacuously true on
     * an empty stream. Without a predicate, whether every element is truthy.
     *
     * @param callable|null $predicate
     * @return bool
     */
    public function all(?callable $predicate = null): bool;

    /**
     * Check if the stream contains a specific value with short-circuiting. Without a
     * comparer the comparison is strict; a comparer may answer `bool` (`true` = equal) or an
     * int in the `<=>` convention (`0` = equal).
     *
     * @param mixed $value
     * @param callable|null $comparer
     * @return bool
     */
    public function contains(mixed $value, ?callable $comparer = null): bool;

    /**
     * Compute the running sum of elements; `null` values are skipped, an empty stream sums to 0.
     *
     * @param callable|null $selector
     * @return int|float
     * @throws \InvalidArgumentException when a value is not numeric
     */
    public function sum(?callable $selector = null): int|float;

    /**
     * Compute the running arithmetic mean of elements; `null` values are skipped.
     *
     * @param callable|null $selector
     * @return int|float
     * @throws \UnderflowException when there is no non-null value to average
     * @throws \InvalidArgumentException when a value is not numeric
     */
    public function average(?callable $selector = null): int|float;

    /**
     * Find the minimum value in the stream; `null` values are skipped.
     *
     * @param callable|null $selector
     * @return mixed
     * @throws \UnderflowException when there is no non-null value
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function min(?callable $selector = null): mixed;

    /**
     * Find the maximum value in the stream; `null` values are skipped.
     *
     * @param callable|null $selector
     * @return mixed
     * @throws \UnderflowException when there is no non-null value
     * @throws \InvalidArgumentException when a value is not comparable
     */
    public function max(?callable $selector = null): mixed;

    /**
     * Find the element yielding the minimum value via key selector.
     *
     * @param callable $keySelector
     * @return mixed
     * @throws \UnderflowException when the stream is empty
     */
    public function minBy(callable $keySelector): mixed;

    /**
     * Find the element yielding the maximum value via key selector.
     *
     * @param callable $keySelector
     * @return mixed
     * @throws \UnderflowException when the stream is empty
     */
    public function maxBy(callable $keySelector): mixed;

    /**
     * Perform an accumulator fold over the stream with constant memory.
     *
     * @param mixed $seed
     * @param callable $func fn($accumulator, $item, $key): mixed
     * @return mixed
     */
    public function aggregate(mixed $seed, callable $func): mixed;

    /**
     * Execute a callback for each element in the stream.
     *
     * @param callable $callback fn($item, $key): void
     * @return self
     */
    public function each(callable $callback): self;

    /**
     * Count the elements, or the elements that satisfy the predicate, consuming the stream.
     *
     * @param callable|null $predicate fn($item) or fn($item, $key): bool
     * @return int
     */
    public function count(?callable $predicate = null): int;

    /**
     * Implement JsonSerializable: materializes the stream (list → JSON array, dictionary → JSON object).
     *
     * @return array
     */
    public function jsonSerialize(): array;

    /**
     * Materialize the lazy stream into a native PHP array.
     *
     * @return array
     */
    public function toArray(): array;

    /**
     * Materialize the lazy stream into an in-memory eager ALinqCollection.
     *
     * @return ALinqCollection
     */
    public function toCollection(): ALinqCollection;

    /**
     * Materialize the lazy stream into a stdClass object.
     *
     * @return stdClass
     */
    public function toObject(): stdClass;
}
