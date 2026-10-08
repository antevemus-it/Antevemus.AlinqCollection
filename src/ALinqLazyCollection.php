<?php

declare(strict_types=1);

namespace Antevemus\ALinq;

use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use ArrayIterator;
use Closure;
use Generator;
use InvalidArgumentException;
use Iterator;
use IteratorAggregate;
use IteratorIterator;
use OverflowException;
use PDOStatement;
use RuntimeException;
use stdClass;
use Traversable;
use UnderflowException;

/**
 * ALinqLazyCollection
 *
 * A generator-based streaming LINQ-style collection with deferred execution and constant
 * O(1) memory overhead, designed for multi-gigabyte files, CSV streams and unbuffered
 * database cursors, interoperable with the in-memory ALinqCollection
 *
 * @version    1.1.2
 * @package    antevemus
 * @subpackage alinq
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class ALinqLazyCollection implements IALinqLazyCollection
{
    /**
     * @var Closure(): iterable
     */
    private Closure $sourceFactory;

    /**
     * Initialize lazy collection with an iterable or generator factory closure.
     *
     * @param iterable|callable $source
     */
    public function __construct(iterable|callable $source)
    {
        if (is_callable($source)) {
            $this->sourceFactory = $source instanceof Closure ? $source : $source(...);
        } elseif (is_array($source)) {
            $this->sourceFactory = static fn(): array => $source;
        } elseif ($source instanceof PDOStatement) {
            // A PDO cursor is single-pass: a second traversal would silently yield nothing.
            $this->sourceFactory = self::singlePassCursorFactory($source, null);
        } elseif ($source instanceof Traversable && !($source instanceof Generator)) {
            $this->sourceFactory = static fn(): Traversable => $source;
        } else {
            // For single-use Generators or raw iterables:
            $this->sourceFactory = static fn(): iterable => $source;
        }
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
     */
    public static function fromFile(string $filePath, int $bufferSize = 4096, ?callable $lineParser = null): self
    {
        if ($bufferSize <= 0) {
            throw new InvalidArgumentException('Buffer size must be greater than zero.');
        }

        return new self(function () use ($filePath, $bufferSize, $lineParser) {
            $handle = @fopen($filePath, 'r');
            if ($handle === false) {
                throw new RuntimeException(sprintf('Unable to open file for streaming: "%s"', $filePath));
            }

            try {
                // $bufferSize is the I/O chunk hint only. It must never bound the line length:
                // fgets() with a length argument splits lines longer than length-1 bytes into
                // several items, which corrupts the stream silently.
                @stream_set_chunk_size($handle, $bufferSize);

                $lineNumber = 0;
                while (($line = fgets($handle)) !== false) {
                    $trimmed = rtrim($line, "\r\n");
                    yield $lineNumber => ($lineParser !== null ? $lineParser($trimmed, $lineNumber) : $trimmed);
                    $lineNumber++;
                }
            } finally {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function fromCsv(
        string $filePath,
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '\\',
        bool $hasHeader = true
    ): self {
        return new self(function () use ($filePath, $separator, $enclosure, $escape, $hasHeader) {
            $handle = @fopen($filePath, 'r');
            if ($handle === false) {
                throw new RuntimeException(sprintf('Unable to open CSV file for streaming: "%s"', $filePath));
            }

            try {
                $headers = null;
                $rowNumber = 0;

                if ($hasHeader) {
                    $headerRow = fgetcsv($handle, 0, $separator, $enclosure, $escape);
                    if ($headerRow !== false) {
                        $headers = $headerRow;
                    }
                }

                while (($row = fgetcsv($handle, 0, $separator, $enclosure, $escape)) !== false) {
                    if ($headers !== null && count($headers) === count($row)) {
                        $data = array_combine($headers, $row);
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
        });
    }

    /**
     * Create a lazy collection from a PDOStatement cursor.
     *
     * @param PDOStatement $statement
     * @param callable|null $rowMapper fn($row, $index): mixed
     * @return self
     */
    public static function fromCursor(PDOStatement $statement, ?callable $rowMapper = null): self
    {
        return new self(self::singlePassCursorFactory($statement, $rowMapper));
    }

    /**
     * Build the generator factory for a single-pass PDO cursor.
     *
     * A PDOStatement cannot be rewound: once fetched, a second traversal yields no rows.
     * Returning an empty stream in that case is a silent wrong result, so the second
     * traversal throws instead, pointing to remember() as the documented way to make
     * the stream re-traversable.
     *
     * @param PDOStatement $statement
     * @param callable|null $rowMapper fn($row, $index): mixed
     * @return Closure(): Generator
     */
    private static function singlePassCursorFactory(PDOStatement $statement, ?callable $rowMapper): Closure
    {
        $consumed = false;

        return function () use ($statement, $rowMapper, &$consumed): Generator {
            if ($consumed) {
                throw new RuntimeException(
                    'A PDO cursor is single-pass and has already been traversed. ' .
                    'Call ->remember() before the first complete traversal to re-traverse the stream, ' .
                    'or execute the statement again.'
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
        return new self(static fn() => []);
    }

    /**
     * {@inheritdoc}
     */
    public static function range(int $start, int $end, int $step = 1): self
    {
        if ($step <= 0) {
            throw new InvalidArgumentException('Range step must be greater than zero.');
        }

        return new self(function () use ($start, $end, $step) {
            if ($start <= $end) {
                for ($i = $start; $i <= $end; $i += $step) {
                    yield $i;
                }
            } else {
                for ($i = $start; $i >= $end; $i -= $step) {
                    yield $i;
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public static function repeat(mixed $element, int $count): self
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Repeat count must be greater than or equal to zero.');
        }

        return new self(function () use ($element, $count) {
            for ($i = 0; $i < $count; $i++) {
                yield $i => $element;
            }
        });
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
        return new self(function () use ($predicate) {
            foreach ($this as $key => $item) {
                if ($predicate($item, $key)) {
                    yield $key => $item;
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function whereNot(callable $predicate): self
    {
        return $this->where(fn($item, $key) => !$predicate($item, $key));
    }

    /**
     * {@inheritdoc}
     */
    public function select(callable $selector): self
    {
        return new self(function () use ($selector) {
            foreach ($this as $key => $item) {
                yield $key => $selector($item, $key);
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function selectMany(callable $selector): self
    {
        return new self(function () use ($selector) {
            foreach ($this as $key => $item) {
                $inner = $selector($item, $key);
                if (is_iterable($inner)) {
                    foreach ($inner as $innerKey => $innerItem) {
                        yield $innerItem;
                    }
                } else {
                    yield $inner;
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function take(int $count): self
    {
        return new self(function () use ($count) {
            if ($count <= 0) {
                return;
            }

            $taken = 0;
            foreach ($this as $key => $item) {
                yield $key => $item;
                $taken++;
                if ($taken >= $count) {
                    break;
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function skip(int $count): self
    {
        return new self(function () use ($count) {
            $skipped = 0;
            foreach ($this as $key => $item) {
                if ($skipped < $count) {
                    $skipped++;
                    continue;
                }
                yield $key => $item;
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function takeWhile(callable $predicate): self
    {
        return new self(function () use ($predicate) {
            foreach ($this as $key => $item) {
                if (!$predicate($item, $key)) {
                    break;
                }
                yield $key => $item;
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function skipWhile(callable $predicate): self
    {
        return new self(function () use ($predicate) {
            $skipping = true;
            foreach ($this as $key => $item) {
                if ($skipping) {
                    if ($predicate($item, $key)) {
                        continue;
                    }
                    $skipping = false;
                }
                yield $key => $item;
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function distinct(?callable $keySelector = null): self
    {
        return new self(function () use ($keySelector) {
            $seen = [];
            foreach ($this as $key => $item) {
                $identifier = $keySelector !== null ? $keySelector($item, $key) : $item;
                $hash = is_scalar($identifier) ? (string)$identifier : serialize($identifier);

                if (!isset($seen[$hash])) {
                    $seen[$hash] = true;
                    yield $key => $item;
                }
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function distinctBy(callable $keySelector): self
    {
        return $this->distinct($keySelector);
    }

    /**
     * {@inheritdoc}
     */
    public function chunk(int $size): self
    {
        if ($size <= 0) {
            throw new InvalidArgumentException('Chunk size must be greater than zero.');
        }

        return new self(function () use ($size) {
            // Chunks are reindexed lists, as array_chunk() does on the eager side. Keying the
            // chunk by the source key made a repeated key overwrite the previous item and
            // count($chunk) never reach $size: six items with key 0 came out as one chunk of
            // one (review 2026-10-08, 4.3).
            $chunk = [];
            $filled = 0;
            foreach ($this as $item) {
                $chunk[] = $item;
                if (++$filled === $size) {
                    yield $chunk;
                    $chunk = [];
                    $filled = 0;
                }
            }

            if ($chunk !== []) {
                yield $chunk;
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function pad(int $size, mixed $value): self
    {
        return new self(function () use ($size, $value) {
            $count = 0;
            foreach ($this as $key => $item) {
                yield $key => $item;
                $count++;
            }

            // Padding is yielded without an explicit key: the generator continues from the
            // highest integer key already emitted, as array_pad() does. Keying it by the
            // counter collided with the source keys after where()/skip() and overwrote real
            // items (review 2026-10-08, 4.4).
            while ($count < $size) {
                yield $value;
                $count++;
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function concat(iterable|callable $second): self
    {
        return new self(function () use ($second) {
            foreach ($this as $key => $item) {
                yield $item;
            }

            $secondIterable = is_callable($second) ? $second() : $second;
            foreach ($secondIterable as $key => $item) {
                yield $item;
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function zip(iterable|callable $second, ?callable $resultSelector = null): self
    {
        return new self(function () use ($second, $resultSelector) {
            $secondIterable = is_callable($second) ? $second() : $second;
            $secondIterator = is_array($secondIterable)
                ? new ArrayIterator($secondIterable)
                : ($secondIterable instanceof Traversable ? $secondIterable : new ArrayIterator(iterator_to_array($secondIterable)));

            if ($secondIterator instanceof IteratorAggregate) {
                $secondIterator = $secondIterator->getIterator();
            }

            $secondIterator->rewind();

            foreach ($this as $key => $firstItem) {
                if (!$secondIterator->valid()) {
                    break;
                }

                $secondItem = $secondIterator->current();
                yield $resultSelector !== null ? $resultSelector($firstItem, $secondItem) : [$firstItem, $secondItem];
                $secondIterator->next();
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function tap(callable $callback): self
    {
        return new self(function () use ($callback) {
            foreach ($this as $key => $item) {
                $callback($item, $key);
                yield $key => $item;
            }
        });
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

        return new self(function () use (&$buffer, &$upstream, &$complete): Generator {
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
        });
    }

    /**
     * {@inheritdoc}
     */
    public function first(?callable $predicate = null): mixed
    {
        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                return $item;
            }
        }

        throw new UnderflowException('Collection is empty or no element matches the predicate.');
    }

    /**
     * {@inheritdoc}
     */
    public function firstOrDefault(mixed $default = null, ?callable $predicate = null): mixed
    {
        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * {@inheritdoc}
     */
    public function last(?callable $predicate = null): mixed
    {
        $found = false;
        $last = null;

        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                $last = $item;
                $found = true;
            }
        }

        if (!$found) {
            throw new UnderflowException('Collection is empty or no element matches the predicate.');
        }

        return $last;
    }

    /**
     * {@inheritdoc}
     */
    public function lastOrDefault(mixed $default = null, ?callable $predicate = null): mixed
    {
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
     */
    public function singleOrDefault(mixed $default = null, ?callable $predicate = null): mixed
    {
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
     */
    public function any(?callable $predicate = null): bool
    {
        foreach ($this as $key => $item) {
            if ($predicate === null || $predicate($item, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function all(?callable $predicate = null): bool
    {
        if ($predicate === null) {
            return false;
        }

        $hasItems = false;
        foreach ($this as $key => $item) {
            $hasItems = true;
            if (!$predicate($item, $key)) {
                return false;
            }
        }

        return $hasItems;
    }

    /**
     * {@inheritdoc}
     */
    public function contains(mixed $value, ?callable $comparer = null): bool
    {
        foreach ($this as $key => $item) {
            if ($comparer !== null ? $comparer($item, $value) : $item === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        $count = 0;
        foreach ($this as $_) {
            $count++;
        }

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function sum(?callable $selector = null): int|float
    {
        $sum = 0;
        foreach ($this as $key => $item) {
            $sum += $selector !== null ? $selector($item, $key) : $item;
        }

        return $sum;
    }

    /**
     * {@inheritdoc}
     */
    public function average(?callable $selector = null): int|float
    {
        $sum = 0;
        $count = 0;

        foreach ($this as $key => $item) {
            $sum += $selector !== null ? $selector($item, $key) : $item;
            $count++;
        }

        if ($count === 0) {
            throw new UnderflowException('Cannot compute average on an empty collection.');
        }

        return $sum / $count;
    }

    /**
     * {@inheritdoc}
     */
    public function min(?callable $selector = null): mixed
    {
        $min = null;
        $first = true;

        foreach ($this as $key => $item) {
            $val = $selector !== null ? $selector($item, $key) : $item;
            if ($first || $val < $min) {
                $min = $val;
                $first = false;
            }
        }

        if ($first) {
            throw new UnderflowException('Cannot compute min on an empty collection.');
        }

        return $min;
    }

    /**
     * {@inheritdoc}
     */
    public function max(?callable $selector = null): mixed
    {
        $max = null;
        $first = true;

        foreach ($this as $key => $item) {
            $val = $selector !== null ? $selector($item, $key) : $item;
            if ($first || $val > $max) {
                $max = $val;
                $first = false;
            }
        }

        if ($first) {
            throw new UnderflowException('Cannot compute max on an empty collection.');
        }

        return $max;
    }

    /**
     * {@inheritdoc}
     */
    public function minBy(callable $keySelector): mixed
    {
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
            throw new UnderflowException('Cannot compute minBy on an empty collection.');
        }

        return $bestItem;
    }

    /**
     * {@inheritdoc}
     */
    public function maxBy(callable $keySelector): mixed
    {
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
            throw new UnderflowException('Cannot compute maxBy on an empty collection.');
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
        return self::materialize($this);
    }

    /**
     * Materializes a stream without ever losing an item (key policy of the 2026-10-08
     * review, decision 1a: "a list is reindexed, a dictionary keeps its keys").
     *
     * A stream whose keys are all integers is a list and comes out reindexed, exactly as
     * the eager ALinqCollection does after where()/take()/skip(); a stream with at least one
     * string key is a dictionary and keeps its keys, appending an item whose key collides
     * instead of overwriting it. Before, every materializer did `$result[$key] = $item`:
     * a source built from two `yield from`, or any where()/skip() over a plain list,
     * reported count() = 4 and returned two items from toArray().
     *
     * @param iterable $items
     * @return array
     */
    private static function materialize(iterable $items): array
    {
        $pairs = [];
        $allIntegerKeys = true;
        foreach ($items as $key => $item) {
            $pairs[] = [$key, $item];
            if (!is_int($key)) {
                $allIntegerKeys = false;
            }
        }

        if ($allIntegerKeys) {
            return array_column($pairs, 1);
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
