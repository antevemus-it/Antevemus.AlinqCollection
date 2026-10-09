<?php

declare(strict_types=1);

namespace Antevemus\ALinq;

use Antevemus\ALinq\Helpers\ALinqCallable;
use Antevemus\ALinq\Helpers\ALinqContract;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use ArrayIterator;
use Closure;
use Generator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use IteratorIterator;
use JsonSerializable;
use OverflowException;
use PDOStatement;
use RuntimeException;
use stdClass;
use Traversable;
use UnderflowException;
use UnexpectedValueException;
use WeakMap;

/**
 * ALinqLazyCollection
 *
 * A generator-based streaming LINQ-style collection with deferred execution and constant
 * O(1) memory overhead, designed for multi-gigabyte files, CSV streams and unbuffered
 * database cursors, interoperable with the in-memory ALinqCollection.
 *
 * Contract shared with ALinqCollection since 1.3.0 (review 2026-10-08, forward 015):
 *
 * - Keys: "a list reindexes, a dictionary keeps its keys". The collection knows whether its
 *   source is a list (`from(array)` by array_is_list(); files, CSV, cursors, range(),
 *   repeat() and empty() are lists; a Generator, a Traversable or a factory closure is
 *   unknown). Filtering and projecting operators inherit that knowledge and, while
 *   streaming, a list emits renumbered keys and a dictionary (or an unknown source) emits
 *   the original key; chunk(), pad() padding, concat(), zip() and selectMany() produce
 *   lists. Materialization never loses an item: a known list is reindexed, a dictionary
 *   keeps its keys and appends on collision, an unknown source is a list when every key
 *   is an integer.
 * - Only a Closure is a re-iterable factory; an array (even a callable one) is data.
 * - Empty throws: first(), last(), min(), max(), minBy(), maxBy() and average() throw
 *   UnderflowException; the *OrDefault() variants return the default.
 * - Numeric aggregations skip null and throw InvalidArgumentException on a non-numeric
 *   value; min()/max() compare scalars and DateTimeInterface.
 * - A negative take()/skip()/pad() size, chunk(0) or range step 0 throw
 *   InvalidArgumentException instead of being ignored.
 * - A comparer given to contains() may answer bool or `<=>` style int.
 *
 * @version    1.3.2
 * @package    antevemus
 * @subpackage alinq
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class ALinqLazyCollection implements IALinqLazyCollection, JsonSerializable
{
    /**
     * @var Closure(): iterable
     */
    private Closure $sourceFactory;

    /**
     * Whether the source is a list (true), a dictionary (false) or unknown (null): the key
     * rule of the 1.3.0 contract (RN-03). Set once at construction and propagated by every
     * operator through pipe().
     */
    private ?bool $sourceIsList = null;

    /**
     * PDOStatements already bound to a lazy collection (one statement, one collection).
     *
     * @var WeakMap<PDOStatement, true>|null
     */
    private static ?WeakMap $boundStatements = null;

    /**
     * Initialize lazy collection with an iterable or a generator factory closure.
     *
     * Only a Closure is treated as a re-iterable factory (RN-18): an array is data even when
     * it happens to be callable (`[$object, 'method']`), and a string or an invokable object
     * is refused, because neither can be iterated.
     *
     * @param iterable|callable $source
     * @throws InvalidArgumentException When the source is a callable that is not a Closure and not iterable
     */
    public function __construct(iterable|callable $source)
    {
        if ($source instanceof Closure) {
            $this->sourceFactory = $source;
            $this->sourceIsList = null;
        } elseif (is_array($source)) {
            $this->sourceFactory = static fn(): array => $source;
            $this->sourceIsList = array_is_list($source);
        } elseif ($source instanceof PDOStatement) {
            // A PDO cursor is single-pass: a second traversal would silently yield nothing.
            $this->sourceFactory = self::singlePassCursorFactory($source, null);
            $this->sourceIsList = true;
        } elseif ($source instanceof Traversable && !($source instanceof Generator)) {
            $this->sourceFactory = static fn(): Traversable => $source;
            $this->sourceIsList = null;
        } elseif (is_iterable($source)) {
            // For single-use Generators or raw iterables:
            $this->sourceFactory = static fn(): iterable => $source;
            $this->sourceIsList = null;
        } else {
            throw new InvalidArgumentException(sprintf(
                'ALinqLazyCollection accepts an iterable or a Closure factory, %s given; wrap a callable with Closure::fromCallable() or $callable(...) to use it as a factory',
                get_debug_type($source)
            ));
        }
    }

    /**
     * Builds a downstream stage: a generator factory plus the list/dictionary knowledge it
     * inherits (or produces).
     *
     * @param Closure(): iterable $factory
     * @param bool|null $isList
     * @return self
     */
    private static function pipe(Closure $factory, ?bool $isList): self
    {
        $stage = new self($factory);
        $stage->sourceIsList = $isList;
        return $stage;
    }

    /**
     * {@inheritdoc}
     */
    public static function from(iterable|callable $source): self
    {
        return new self($source);
    }

    /**
     * {@inheritdoc}
     *
     * Irregular input (1.3.2): a UTF-8 BOM at the start of the file is removed from the
     * first line; a directory is refused instead of producing an empty stream.
     *
     * @throws InvalidArgumentException When the buffer size is not positive
     * @throws RuntimeException When the path is a directory or the file cannot be opened
     *                          (both deferred to the first traversal)
     */
    public static function fromFile(string $filePath, int $bufferSize = 4096, ?callable $lineParser = null): self
    {
        if ($bufferSize <= 0) {
            throw new InvalidArgumentException('Buffer size must be greater than zero.');
        }

        return self::pipe(function () use ($filePath, $bufferSize, $lineParser) {
            $handle = self::openForStreaming($filePath, 'Unable to open file for streaming: "%s"');

            try {
                // $bufferSize is the I/O chunk hint only. It must never bound the line length:
                // fgets() with a length argument splits lines longer than length-1 bytes into
                // several items, which corrupts the stream silently.
                @stream_set_chunk_size($handle, $bufferSize);

                $lineNumber = 0;
                while (($line = fgets($handle)) !== false) {
                    $trimmed = rtrim($line, "\r\n");
                    if ($lineNumber === 0) {
                        $trimmed = self::stripUtf8Bom($trimmed);
                    }
                    yield $lineNumber => ($lineParser !== null ? $lineParser($trimmed, $lineNumber) : $trimmed);
                    $lineNumber++;
                }
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }, true);
    }

    /**
     * {@inheritdoc}
     *
     * Irregular input (1.3.2):
     * - With a header and `$strict = true` (the default), a record whose field count differs
     *   from the header throws when it is read (the records before it were already yielded).
     *   `$strict = false` hands such a record over as a positional list (the 1.3.1 behaviour).
     *   Without a header there is no reference width and every record is a list.
     * - A blank line is not a record: it is skipped in every mode.
     * - A UTF-8 BOM is removed from the start of the file before parsing, so a quoted first
     *   field is parsed as such; on a stream that cannot seek it is removed from the first
     *   field of the first record (header or data) instead.
     * - A directory is refused instead of producing an empty stream.
     *
     * @throws RuntimeException When the path is a directory or the file cannot be opened
     *                          (both deferred to the first traversal), or, in strict mode,
     *                          when a record's width differs from the header's
     */
    public static function fromCsv(
        string $filePath,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $hasHeader = true,
        bool $strict = true
    ): self {
        return self::pipe(function () use ($filePath, $separator, $enclosure, $escape, $hasHeader, $strict) {
            $handle = self::openForStreaming($filePath, 'Unable to open CSV file for streaming: "%s"');

            try {
                $headers = null;
                $headerWidth = 0;
                $recordNumber = 0;
                $rowNumber = 0;
                // True while the first record still has to lose a BOM at field level.
                $stripFirstField = !self::skipLeadingUtf8Bom($handle);

                if ($hasHeader) {
                    $headerRow = fgetcsv($handle, 0, $separator, $enclosure, $escape);
                    if ($headerRow !== false) {
                        $headers = $stripFirstField ? self::stripCsvBom($headerRow) : $headerRow;
                        $headerWidth = count($headers);
                    }
                    $stripFirstField = false;
                }

                while (($row = fgetcsv($handle, 0, $separator, $enclosure, $escape)) !== false) {
                    // fgetcsv() answers [null] for a blank line: it is not a record.
                    if ($row === [null]) {
                        continue;
                    }
                    // Records after the header, 1-based, blank lines excluded.
                    $recordNumber++;

                    if ($stripFirstField) {
                        $row = self::stripCsvBom($row);
                        $stripFirstField = false;
                    }

                    if ($headers === null) {
                        $data = $row;
                    } elseif (count($row) === $headerWidth) {
                        $data = array_combine($headers, $row);
                    } elseif ($strict) {
                        throw new RuntimeException(sprintf(
                            'CSV row %d has %d fields, header has %d',
                            $recordNumber,
                            count($row),
                            $headerWidth
                        ));
                    } else {
                        $data = $row;
                    }

                    yield $rowNumber => $data;
                    $rowNumber++;
                }
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }, true);
    }

    /**
     * Open a file for one streaming pass, refusing a directory explicitly: fopen() on a
     * directory succeeds on some systems and the stream is then silently empty.
     *
     * @param string $filePath
     * @param string $openFailureFormat sprintf() format receiving the path
     * @return resource
     * @throws RuntimeException When the path is a directory or cannot be opened
     */
    private static function openForStreaming(string $filePath, string $openFailureFormat)
    {
        // @: a stream wrapper without url_stat() warns here; it is simply not a directory.
        if (@is_dir($filePath)) {
            throw new RuntimeException(sprintf('Path is a directory, not a file: "%s"', $filePath));
        }

        // The @ only silences the native warning: the failure is reported by the exception.
        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException(sprintf($openFailureFormat, $filePath));
        }

        return $handle;
    }

    /**
     * Consume a UTF-8 byte order mark at the current (initial) position of the stream, before
     * any parser sees it: a BOM in front of a quoted field would otherwise make fgetcsv()
     * read the quotes as data. Bytes that are not a BOM are given back by seeking.
     *
     * @param resource $handle
     * @return bool True when the stream was checked (BOM consumed or absent); false when the
     *              stream cannot seek and the caller must strip the BOM at field level
     */
    private static function skipLeadingUtf8Bom($handle): bool
    {
        $start = @ftell($handle);
        if ($start === false || @fseek($handle, $start) !== 0) {
            return false;
        }

        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            fseek($handle, $start);
        }

        return true;
    }

    /**
     * Remove a UTF-8 byte order mark from the start of a string (a BOM is never data).
     */
    private static function stripUtf8Bom(string $value): string
    {
        return str_starts_with($value, "\xEF\xBB\xBF") ? substr($value, 3) : $value;
    }

    /**
     * Remove a UTF-8 byte order mark from the first field of a CSV record.
     *
     * @param array<int, string|null> $row
     * @return array<int, string|null>
     */
    private static function stripCsvBom(array $row): array
    {
        if (isset($row[0]) && is_string($row[0])) {
            $row[0] = self::stripUtf8Bom($row[0]);
        }

        return $row;
    }

    /**
     * Create a lazy collection from a PDOStatement cursor.
     *
     * One statement, one collection (1.3.2): a PDOStatement is a single cursor, so a second
     * fromCursor() (or from()) over the same statement object returns a collection whose
     * first traversal throws, instead of silently reading what the first one left behind.
     * remember() on the first collection is the way to traverse the rows more than once.
     *
     * @param PDOStatement $statement
     * @param callable|null $rowMapper fn($row, $index): mixed
     * @return self
     * @throws RuntimeException On a second traversal of the consumed cursor (use remember()),
     *                          or on the first traversal when the statement is already bound
     *                          to another lazy collection
     */
    public static function fromCursor(PDOStatement $statement, ?callable $rowMapper = null): self
    {
        return self::pipe(self::singlePassCursorFactory($statement, $rowMapper), true);
    }

    /**
     * Build the generator factory for a single-pass PDO cursor.
     *
     * A PDOStatement cannot be rewound: once fetched, a second traversal yields no rows.
     * Returning an empty stream in that case is a silent wrong result, so the second
     * traversal throws instead, pointing to remember() as the documented way to make
     * the stream re-traversable.
     *
     * The statement is bound to the first collection built over it (a WeakMap, so the
     * binding disappears with the statement); a later factory over the same object throws
     * on its first traversal, because two collections would share one cursor.
     *
     * @param PDOStatement $statement
     * @param callable|null $rowMapper fn($row, $index): mixed
     * @return Closure(): Generator
     */
    private static function singlePassCursorFactory(PDOStatement $statement, ?callable $rowMapper): Closure
    {
        $bound = self::$boundStatements ??= new WeakMap();
        if (isset($bound[$statement])) {
            return static function (): Generator {
                throw new RuntimeException('PDOStatement already bound to another lazy collection');
                yield; // unreachable: makes the closure a generator, so the throw is deferred
            };
        }
        $bound[$statement] = true;

        $consumed = false;

        return function () use ($statement, $rowMapper, &$consumed): Generator {
            if ($consumed) {
                throw new RuntimeException(
                    'A PDO cursor is single-pass and has already been traversed. ' .
                    'Call ->remember() before the first complete traversal to re-traverse the stream, ' .
                    'or run the query again on a new statement.'
                );
            }
            $consumed = true;

            $index = 0;
            while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
                yield $index => ($rowMapper !== null ? $rowMapper($row, $index) : $row);
                $index++;
            }
        };
    }

    /**
     * {@inheritdoc}
     */
    public static function empty(): self
    {
        return self::pipe(static fn() => [], true);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException When the step is below 1
     */
    public static function range(int $start, int $end, int $step = 1): self
    {
        ALinqContract::requireAtLeast($step, 1, 'step', 'range');

        return self::pipe(function () use ($start, $end, $step) {
            if ($start <= $end) {
                for ($i = $start; $i <= $end; $i += $step) {
                    yield $i;
                }
            } else {
                for ($i = $start; $i >= $end; $i -= $step) {
                    yield $i;
                }
            }
        }, true);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException When the count is negative
     */
    public static function repeat(mixed $element, int $count): self
    {
        ALinqContract::requireAtLeast($count, 0, 'count', 'repeat');

        return self::pipe(function () use ($element, $count) {
            for ($i = 0; $i < $count; $i++) {
                yield $i => $element;
            }
        }, true);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        $source = ($this->sourceFactory)();
        if ($source instanceof Traversable) {
            return $source;
        }

        return new ArrayIterator($source);
    }

    /**
     * {@inheritdoc}
     */
    public function where(callable $predicate): self
    {
        $predicate = ALinqCallable::withKey($predicate);
        $isList = $this->sourceIsList;
        return self::pipe(function () use ($predicate, $isList) {
            foreach ($this as $key => $item) {
                if ($predicate($item, $key)) {
                    if ($isList) {
                        yield $item;
                    } else {
                        yield $key => $item;
                    }
                }
            }
        }, $isList);
    }

    /**
     * {@inheritdoc}
     */
    public function whereNot(callable $predicate): self
    {
        $predicate = ALinqCallable::withKey($predicate);
        return $this->where(fn($item, $key) => !$predicate($item, $key));
    }

    /**
     * {@inheritdoc}
     */
    public function select(callable $selector): self
    {
        $selector = ALinqCallable::withKey($selector);
        $isList = $this->sourceIsList;
        return self::pipe(function () use ($selector, $isList) {
            foreach ($this as $key => $item) {
                if ($isList) {
                    yield $selector($item, $key);
                } else {
                    yield $key => $selector($item, $key);
                }
            }
        }, $isList);
    }

    /**
     * {@inheritdoc}
     *
     * Any iterable returned by the selector is flattened; a non-iterable return is an
     * error, not an item (RN-16). The result is a list.
     *
     * @throws UnexpectedValueException When the selector returns a non-iterable value (at traversal)
     */
    public function selectMany(callable $selector): self
    {
        $selector = ALinqCallable::withKey($selector);
        return self::pipe(function () use ($selector) {
            foreach ($this as $key => $item) {
                $inner = $selector($item, $key);
                if (!is_iterable($inner)) {
                    throw new UnexpectedValueException(sprintf(
                        'selectMany() expects the selector to return an iterable, %s returned for key %s',
                        get_debug_type($inner),
                        var_export($key, true)
                    ));
                }
                foreach ($inner as $innerItem) {
                    yield $innerItem;
                }
            }
        }, true);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException When the count is negative
     */
    public function take(int $count): self
    {
        ALinqContract::requireAtLeast($count, 0, 'count', 'take');
        $isList = $this->sourceIsList;

        return self::pipe(function () use ($count, $isList) {
            if ($count === 0) {
                return;
            }

            $taken = 0;
            foreach ($this as $key => $item) {
                if ($isList) {
                    yield $item;
                } else {
                    yield $key => $item;
                }
                $taken++;
                if ($taken >= $count) {
                    break;
                }
            }
        }, $isList);
    }

    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException When the count is negative
     */
    public function skip(int $count): self
    {
        ALinqContract::requireAtLeast($count, 0, 'count', 'skip');
        $isList = $this->sourceIsList;

        return self::pipe(function () use ($count, $isList) {
            $skipped = 0;
            foreach ($this as $key => $item) {
                if ($skipped < $count) {
                    $skipped++;
                    continue;
                }
                if ($isList) {
                    yield $item;
                } else {
                    yield $key => $item;
                }
            }
        }, $isList);
    }

    /**
     * {@inheritdoc}
     */
    public function takeWhile(callable $predicate): self
    {
        $predicate = ALinqCallable::withKey($predicate);
        $isList = $this->sourceIsList;
        return self::pipe(function () use ($predicate, $isList) {
            foreach ($this as $key => $item) {
                if (!$predicate($item, $key)) {
                    break;
                }
                if ($isList) {
                    yield $item;
                } else {
                    yield $key => $item;
                }
            }
        }, $isList);
    }

    /**
     * {@inheritdoc}
     */
    public function skipWhile(callable $predicate): self
    {
        $predicate = ALinqCallable::withKey($predicate);
        $isList = $this->sourceIsList;
        return self::pipe(function () use ($predicate, $isList) {
            $skipping = true;
            foreach ($this as $key => $item) {
                if ($skipping) {
                    if ($predicate($item, $key)) {
                        continue;
                    }
                    $skipping = false;
                }
                if ($isList) {
                    yield $item;
                } else {
                    yield $key => $item;
                }
            }
        }, $isList);
    }

    /**
     * {@inheritdoc}
     *
     * Identity by ALinqCallable::hashKey(): typed and strict for scalars, by value for
     * arrays, by identity for objects (RN-06).
     */
    public function distinct(?callable $keySelector = null): self
    {
        $keySelector = $keySelector === null ? null : ALinqCallable::withKey($keySelector);
        $isList = $this->sourceIsList;
        return self::pipe(function () use ($keySelector, $isList) {
            $seen = [];
            foreach ($this as $key => $item) {
                $identifier = $keySelector !== null ? $keySelector($item, $key) : $item;
                $hash = ALinqCallable::hashKey($identifier);

                if (!isset($seen[$hash])) {
                    $seen[$hash] = true;
                    if ($isList) {
                        yield $item;
                    } else {
                        yield $key => $item;
                    }
                }
            }
        }, $isList);
    }

    /**
     * {@inheritdoc}
     */
    public function distinctBy(callable $keySelector): self
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        return $this->distinct($keySelector);
    }

    /**
     * {@inheritdoc}
     *
     * The result is a list of eager ALinqCollection chunks (each chunk is materialized by
     * nature, and stays queryable). Inside each chunk the key rule of the source applies:
     * a list is reindexed, a dictionary (or an unknown source) keeps its keys and appends on
     * collision, so a repeated key never overwrites an item (review 2026-10-08, 4.3).
     *
     * @throws InvalidArgumentException When the size is below 1
     */
    public function chunk(int $size): self
    {
        ALinqContract::requireAtLeast($size, 1, 'size', 'chunk');
        $isList = $this->sourceIsList;

        return self::pipe(function () use ($size, $isList) {
            $pairs = [];
            foreach ($this as $key => $item) {
                $pairs[] = [$key, $item];
                if (count($pairs) === $size) {
                    yield new ALinqCollection(self::materializePairs($pairs, $isList));
                    $pairs = [];
                }
            }

            if ($pairs !== []) {
                yield new ALinqCollection(self::materializePairs($pairs, $isList));
            }
        }, true);
    }

    /**
     * {@inheritdoc}
     *
     * Follows the key rule of the source: a list is reindexed, a dictionary keeps its keys;
     * the padding is appended without an explicit key, so it never collides with a real
     * item (review 2026-10-08, 4.4).
     *
     * @throws InvalidArgumentException When the size is negative
     */
    public function pad(int $size, mixed $value): self
    {
        ALinqContract::requireAtLeast($size, 0, 'size', 'pad');
        $isList = $this->sourceIsList;

        return self::pipe(function () use ($size, $value, $isList) {
            $count = 0;
            foreach ($this as $key => $item) {
                if ($isList) {
                    yield $item;
                } else {
                    yield $key => $item;
                }
                $count++;
            }

            while ($count < $size) {
                yield $value;
                $count++;
            }
        }, $isList);
    }

    /**
     * {@inheritdoc}
     *
     * A sequence operation (RN-17): the result is always a reindexed list, nothing is
     * overwritten.
     */
    public function concat(iterable|callable $second): self
    {
        return self::pipe(function () use ($second) {
            foreach ($this as $item) {
                yield $item;
            }

            $secondIterable = is_callable($second) && !is_array($second) ? $second() : $second;
            foreach ($secondIterable as $item) {
                yield $item;
            }
        }, true);
    }

    /**
     * {@inheritdoc}
     */
    public function zip(iterable|callable $second, ?callable $resultSelector = null): self
    {
        return self::pipe(function () use ($second, $resultSelector) {
            $secondIterable = is_callable($second) && !is_array($second) ? $second() : $second;
            $secondIterator = is_array($secondIterable)
                ? new ArrayIterator($secondIterable)
                : ($secondIterable instanceof Traversable ? $secondIterable : new ArrayIterator(iterator_to_array($secondIterable)));

            if ($secondIterator instanceof IteratorAggregate) {
                $secondIterator = $secondIterator->getIterator();
            }

            $secondIterator->rewind();

            foreach ($this as $firstItem) {
                if (!$secondIterator->valid()) {
                    break;
                }

                $secondItem = $secondIterator->current();
                yield $resultSelector !== null ? $resultSelector($firstItem, $secondItem) : [$firstItem, $secondItem];
                $secondIterator->next();
            }
        }, true);
    }

    /**
     * {@inheritdoc}
     */
    public function tap(callable $callback): self
    {
        $callback = ALinqCallable::withKey($callback);
        $isList = $this->sourceIsList;
        return self::pipe(function () use ($callback, $isList) {
            foreach ($this as $key => $item) {
                $callback($item, $key);
                if ($isList) {
                    yield $item;
                } else {
                    yield $key => $item;
                }
            }
        }, $isList);
    }

    /**
     * {@inheritdoc}
     */
    public function remember(): self
    {
        // Resumable memoization (review 2026-10-08, 4.2 and 4.12). The buffer holds
        // [key, item] pairs, so a source with repeated keys is replayed item by item
        // instead of collapsing on the key; and the upstream iterator is kept alive between
        // passes: a short-circuited pass (first(), any(), take(n)) caches what it consumed,
        // and the next pass replays the buffer and then keeps pulling from where the
        // upstream stopped. The upstream runs once, whatever the shape of the passes; a
        // single-pass PDO cursor can therefore be followed by first() and then toArray().
        $buffer   = [];
        $upstream = null;
        $complete = false;

        return self::pipe(function () use (&$buffer, &$upstream, &$complete): Generator {
            $index = 0;
            while (true) {
                if ($index < count($buffer)) {
                    [$key, $item] = $buffer[$index++];
                    yield $key => $item;
                    continue;
                }

                if ($complete) {
                    return;
                }

                if ($upstream === null) {
                    $source   = $this->getIterator();
                    $upstream = $source instanceof Iterator ? $source : new IteratorIterator($source);
                    $upstream->rewind();
                }

                if (!$upstream->valid()) {
                    $complete = true;
                    $upstream = null;
                    return;
                }

                $buffer[] = [$upstream->key(), $upstream->current()];
                $upstream->next();
            }
        }, $this->sourceIsList);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnderflowException When the collection is empty or no element matches
     */
    public function first(?callable $predicate = null): mixed
    {
        $predicate = $predicate === null ? null : ALinqCallable::withKey($predicate);
        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                return $item;
            }
        }

        throw new UnderflowException(
            $predicate === null
                ? 'Cannot take first() of an empty collection.'
                : 'first(): no element matches the predicate.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function firstOrDefault(mixed $default = null, ?callable $predicate = null): mixed
    {
        $predicate = $predicate === null ? null : ALinqCallable::withKey($predicate);
        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnderflowException When the collection is empty or no element matches
     */
    public function last(?callable $predicate = null): mixed
    {
        $predicate = $predicate === null ? null : ALinqCallable::withKey($predicate);
        $found = false;
        $last = null;

        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                $last = $item;
                $found = true;
            }
        }

        if (!$found) {
            throw new UnderflowException(
                $predicate === null
                    ? 'Cannot take last() of an empty collection.'
                    : 'last(): no element matches the predicate.'
            );
        }

        return $last;
    }

    /**
     * {@inheritdoc}
     */
    public function lastOrDefault(mixed $default = null, ?callable $predicate = null): mixed
    {
        $predicate = $predicate === null ? null : ALinqCallable::withKey($predicate);
        $last = $default;

        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                $last = $item;
            }
        }

        return $last;
    }

    /**
     * {@inheritdoc}
     *
     * @throws OverflowException When more than one element matches
     */
    public function singleOrDefault(mixed $default = null, ?callable $predicate = null): mixed
    {
        $predicate = $predicate === null ? null : ALinqCallable::withKey($predicate);
        $match = null;
        $found = false;

        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                if ($found) {
                    throw new OverflowException('Collection contains more than one matching element.');
                }
                $match = $item;
                $found = true;
            }
        }

        return $found ? $match : $default;
    }

    /**
     * {@inheritdoc}
     *
     * Without a predicate, answers whether the collection has at least one item (LINQ Any()).
     */
    public function any(?callable $predicate = null): bool
    {
        $predicate = $predicate === null ? null : ALinqCallable::withKey($predicate);
        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * Vacuous truth: an empty collection satisfies every predicate (RN-14). Without a
     * predicate, answers whether every item is truthy.
     */
    public function all(?callable $predicate = null): bool
    {
        $predicate = $predicate === null
            ? static fn(mixed $item): bool => (bool) $item
            : ALinqCallable::withKey($predicate);

        foreach ($this as $key => $item) {
            if (!$predicate($item, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     *
     * Without a comparer the match is strict (===). A comparer may answer bool (true =
     * equal) or an int in the `<=>` convention (0 = equal), RN-09.
     */
    public function contains(mixed $value, ?callable $comparer = null): bool
    {
        $equals = $comparer === null ? null : ALinqContract::equality($comparer);
        foreach ($this as $item) {
            if ($equals !== null ? $equals($item, $value) : $item === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * With a predicate, counts the items that satisfy it (RN-23).
     */
    public function count(?callable $predicate = null): int
    {
        $predicate = $predicate === null ? null : ALinqCallable::withKey($predicate);
        $count = 0;
        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     *
     * null values are skipped; an empty collection sums to 0 (RN-15).
     *
     * @throws InvalidArgumentException When a value is not numeric
     */
    public function sum(?callable $selector = null): int|float
    {
        $selector = $selector === null ? null : ALinqCallable::withKey($selector);
        $sum = 0;
        foreach ($this as $key => $item) {
            $value = ALinqContract::numericValue($selector !== null ? $selector($item, $key) : $item, $key, 'sum');
            if ($value !== null) {
                $sum += $value;
            }
        }

        return $sum;
    }

    /**
     * {@inheritdoc}
     *
     * null values are skipped and do not count in the denominator (RN-15).
     *
     * @throws UnderflowException When the collection is empty or holds only null
     * @throws InvalidArgumentException When a value is not numeric
     */
    public function average(?callable $selector = null): int|float
    {
        $selector = $selector === null ? null : ALinqCallable::withKey($selector);
        $sum = 0;
        $count = 0;

        foreach ($this as $key => $item) {
            $value = ALinqContract::numericValue($selector !== null ? $selector($item, $key) : $item, $key, 'average');
            if ($value !== null) {
                $sum += $value;
                $count++;
            }
        }

        if ($count === 0) {
            throw new UnderflowException('Cannot compute average() of an empty collection (null values are skipped).');
        }

        return $sum / $count;
    }

    /**
     * {@inheritdoc}
     *
     * null values are skipped (RN-15).
     *
     * @throws UnderflowException When the collection is empty or holds only null
     * @throws InvalidArgumentException When a value is not comparable (array, non-DateTime object)
     */
    public function min(?callable $selector = null): mixed
    {
        $selector = $selector === null ? null : ALinqCallable::withKey($selector);
        $min = null;
        $first = true;

        foreach ($this as $key => $item) {
            $val = ALinqContract::comparableValue($selector !== null ? $selector($item, $key) : $item, $key, 'min');
            if ($val === null) {
                continue;
            }
            if ($first || $val < $min) {
                $min = $val;
                $first = false;
            }
        }

        if ($first) {
            throw new UnderflowException('Cannot compute min() of an empty collection (null values are skipped).');
        }

        return $min;
    }

    /**
     * {@inheritdoc}
     *
     * null values are skipped (RN-15).
     *
     * @throws UnderflowException When the collection is empty or holds only null
     * @throws InvalidArgumentException When a value is not comparable (array, non-DateTime object)
     */
    public function max(?callable $selector = null): mixed
    {
        $selector = $selector === null ? null : ALinqCallable::withKey($selector);
        $max = null;
        $first = true;

        foreach ($this as $key => $item) {
            $val = ALinqContract::comparableValue($selector !== null ? $selector($item, $key) : $item, $key, 'max');
            if ($val === null) {
                continue;
            }
            if ($first || $val > $max) {
                $max = $val;
                $first = false;
            }
        }

        if ($first) {
            throw new UnderflowException('Cannot compute max() of an empty collection (null values are skipped).');
        }

        return $max;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnderflowException When the collection is empty
     */
    public function minBy(callable $keySelector): mixed
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        $bestItem = null;
        $bestKey = null;
        $first = true;

        foreach ($this as $key => $item) {
            $val = $keySelector($item, $key);
            if ($first || $val < $bestKey) {
                $bestKey = $val;
                $bestItem = $item;
                $first = false;
            }
        }

        if ($first) {
            throw new UnderflowException('Cannot compute minBy() of an empty collection.');
        }

        return $bestItem;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnderflowException When the collection is empty
     */
    public function maxBy(callable $keySelector): mixed
    {
        $keySelector = ALinqCallable::withKey($keySelector);
        $bestItem = null;
        $bestKey = null;
        $first = true;

        foreach ($this as $key => $item) {
            $val = $keySelector($item, $key);
            if ($first || $val > $bestKey) {
                $bestKey = $val;
                $bestItem = $item;
                $first = false;
            }
        }

        if ($first) {
            throw new UnderflowException('Cannot compute maxBy() of an empty collection.');
        }

        return $bestItem;
    }

    /**
     * {@inheritdoc}
     */
    public function aggregate(mixed $seed, callable $func): mixed
    {
        $acc = $seed;
        foreach ($this as $key => $item) {
            $acc = $func($acc, $item, $key);
        }

        return $acc;
    }

    /**
     * {@inheritdoc}
     */
    public function each(callable $callback): self
    {
        $callback = ALinqCallable::withKey($callback);
        foreach ($this as $key => $item) {
            $callback($item, $key);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return self::materialize($this, $this->sourceIsList);
    }

    /**
     * JsonSerializable: a list becomes a JSON array, a dictionary a JSON object (RN-24).
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Materializes a stream without ever losing an item (key rule, RN-04).
     *
     * A known list is reindexed; a known dictionary keeps its keys and appends an item
     * whose key collides instead of overwriting it; an unknown source (Generator, factory)
     * is a list when every key is an integer and a dictionary otherwise (the 1.1.2 rule).
     * Before 1.1.2 every materializer did `$result[$key] = $item`: a source built from two
     * `yield from`, or any where()/skip() over a plain list, reported count() = 4 and
     * returned two items from toArray().
     *
     * @param iterable $items
     * @param bool|null $isList
     * @return array
     */
    private static function materialize(iterable $items, ?bool $isList): array
    {
        if ($isList === true) {
            $result = [];
            foreach ($items as $item) {
                $result[] = $item;
            }
            return $result;
        }

        $pairs = [];
        foreach ($items as $key => $item) {
            $pairs[] = [$key, $item];
        }

        return self::materializePairs($pairs, $isList);
    }

    /**
     * The key rule applied to a buffered run of [key, item] pairs (materialize() and the
     * chunks of chunk()).
     *
     * @param array<int, array{0: mixed, 1: mixed}> $pairs
     * @param bool|null $isList
     * @return array
     */
    private static function materializePairs(array $pairs, ?bool $isList): array
    {
        if ($isList === true) {
            return array_column($pairs, 1);
        }

        if ($isList === null) {
            $allIntegerKeys = true;
            foreach ($pairs as [$key]) {
                if (!is_int($key)) {
                    $allIntegerKeys = false;
                    break;
                }
            }
            if ($allIntegerKeys) {
                return array_column($pairs, 1);
            }
        }

        $result = [];
        foreach ($pairs as [$key, $item]) {
            if (array_key_exists($key, $result)) {
                $result[] = $item;
            } else {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function toCollection(): ALinqCollection
    {
        return ALinqCollection::from($this->toArray());
    }

    /**
     * {@inheritdoc}
     */
    public function toObject(): stdClass
    {
        return (object)$this->toArray();
    }
}
