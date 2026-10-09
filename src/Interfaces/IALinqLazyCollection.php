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
 * Since 1.4.0 (forward 021) the lazy side also offers single(), whereIn()/whereNotIn()/
 * whereBetween(), the outer joins leftJoin()/rightJoin()/fullJoin() and the composite
 * ordering orderBy()/orderByDescending() + thenBy()/thenByDescending(), with the results and
 * exceptions of the eager side. Ordering and joins buffer: orderBy() consumes the stream when
 * it is traversed; a join indexes one side (the inner side for leftJoin()/fullJoin(), this
 * stream for rightJoin()) and streams the other.
 *
 * @version    1.4.0
 * @package    antevemus
 * @subpackage alinq.interfaces
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
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
     * A UTF-8 BOM at the start of the file is removed from the first line. A directory or a
     * file that cannot be opened throws RuntimeException on the first traversal.
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
     * Irregular input: a blank line is skipped; a UTF-8 BOM at the start of the file is
     * removed before parsing (on a stream that cannot seek, from the first field of the first
     * record); a directory or a file that cannot be opened throws RuntimeException on the
     * first traversal.
     *
     * @param string $filePath
     * @param string $separator
     * @param string $enclosure
     * @param string $escape
     * @param bool $hasHeader If true, yields associative arrays keyed by header columns.
     * @param bool $strict With a header, a record whose field count differs from the header
     *                     throws RuntimeException('CSV row N has K fields, header has H') when
     *                     it is read (true, the default); false hands it over as a positional
     *                     list (the 1.3.1 behaviour, for dirty files).
     * @return self
     */
    public static function fromCsv(
        string $filePath,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $hasHeader = true,
        bool $strict = true
    ): self;

    /**
     * Create a lazy stream over a single-pass PDOStatement cursor (one statement, one collection).
     *
     * A second traversal throws (call remember() to re-traverse). A second fromCursor() over
     * the same statement object returns a collection whose first traversal throws
     * RuntimeException('PDOStatement already bound to another lazy collection').
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
     * Keep the items whose field is one of $values, by strict identity (`in_array(..., true)`).
     *
     * @param string $field Property name or dot-notation path, read by ALinqPropertyAccess
     * @param array $values
     * @return self
     */
    public function whereIn(string $field, array $values): self;

    /**
     * Keep the items whose field is none of $values, by strict identity (`in_array(..., true)`).
     *
     * @param string $field Property name or dot-notation path, read by ALinqPropertyAccess
     * @param array $values
     * @return self
     */
    public function whereNotIn(string $field, array $values): self;

    /**
     * Keep the items whose field lies in [$min, $max], bounds included; null is never between.
     *
     * @param string $field Property name or dot-notation path, read by ALinqPropertyAccess
     * @param mixed $min
     * @param mixed $max
     * @return self
     */
    public function whereBetween(string $field, mixed $min, mixed $max): self;

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
     * Left outer join (LeftJoin in .NET 10/11 LINQ): the inner side is indexed on traversal and
     * this stream is streamed; every outer item appears, with `null` as the inner side when
     * nothing matches (a null key never matches). The result is a list.
     *
     * @param iterable $inner
     * @param callable $outerKeySelector fn($outer, $key): mixed
     * @param callable $innerKeySelector fn($inner, $key): mixed
     * @param callable|null $resultSelector fn($outer, $inner): mixed; the pair [$outer, $inner] when null
     * @return self
     */
    public function leftJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null): self;

    /**
     * Right outer join (RightJoin in .NET 10/11 LINQ): this stream is indexed on traversal and
     * the inner side is streamed; every inner item appears, with `null` as the outer side when
     * nothing matches (a null key never matches). The result is a list, in inner order.
     *
     * @param iterable $inner
     * @param callable $outerKeySelector fn($outer, $key): mixed
     * @param callable $innerKeySelector fn($inner, $key): mixed
     * @param callable|null $resultSelector fn($outer, $inner): mixed; the pair [$outer, $inner] when null
     * @return self
     */
    public function rightJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null): self;

    /**
     * Full outer join (FullJoin in .NET 10/11 LINQ): the inner side is indexed on traversal and
     * this stream is streamed (pairs and unmatched outer items, in outer order), then the
     * unmatched inner items follow in inner order. A null key never matches. The result is a list.
     *
     * @param iterable $inner
     * @param callable $outerKeySelector fn($outer, $key): mixed
     * @param callable $innerKeySelector fn($inner, $key): mixed
     * @param callable|null $resultSelector fn($outer, $inner): mixed; the pair [$outer, $inner] when null
     * @return self
     */
    public function fullJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null): self;

    /**
     * Stable ascending sort by the selected key (OrderBy in LINQ). The stream is consumed and
     * sorted when the result is traversed; thenBy()/thenByDescending() may follow.
     *
     * @param callable $keySelector fn($item, $key): mixed
     * @return self
     */
    public function orderBy(callable $keySelector): self;

    /**
     * Stable descending sort by the selected key (OrderByDescending in LINQ).
     *
     * @param callable $keySelector fn($item, $key): mixed
     * @return self
     */
    public function orderByDescending(callable $keySelector): self;

    /**
     * Subsequent ascending ordering (ThenBy in LINQ), applied in the same sort as the
     * preceding orderBy*()/thenBy*().
     *
     * @param callable $keySelector fn($item, $key): mixed
     * @param callable|null $comparer fn($keyA, $keyB): int in the `<=>` convention
     * @return self
     * @throws \LogicException when the collection does not come straight from orderBy*()/thenBy*()
     */
    public function thenBy(callable $keySelector, ?callable $comparer = null): self;

    /**
     * Subsequent descending ordering (ThenByDescending in LINQ).
     *
     * @param callable $keySelector fn($item, $key): mixed
     * @param callable|null $comparer fn($keyA, $keyB): int in the `<=>` convention
     * @return self
     * @throws \LogicException when the collection does not come straight from orderBy*()/thenBy*()
     */
    public function thenByDescending(callable $keySelector, ?callable $comparer = null): self;

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
     * Return the single element, or the single element matching the predicate (Single in LINQ);
     * stops at the second match.
     *
     * @param callable|null $predicate
     * @return mixed
     * @throws \UnderflowException when the stream is empty or no element matches
     * @throws \OverflowException when more than one element (or matching element) exists
     */
    public function single(?callable $predicate = null): mixed;

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
