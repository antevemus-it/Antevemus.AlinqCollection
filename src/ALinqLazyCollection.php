<?php

declare(strict_types=1);

namespace Antevemus\ALinq;

use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use ArrayIterator;
use Closure;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use OverflowException;
use PDOStatement;
use RuntimeException;
use stdClass;
use Traversable;
use UnderflowException;

/**
 * ALinqLazyCollection - Generator-based streaming collection with deferred execution
 *
 * Implements a high-performance, stream-oriented LINQ pipeline that processes data
 * item-by-item with constant O(1) memory overhead. Designed for multi-gigabyte files,
 * streaming API consumers, and unbuffered database cursors.
 *
 * Funcionalidades:
 * - Deferred/Lazy evaluation leveraging PHP Generators
 * - Multi-gigabyte file and CSV streaming with automatic resource disposal
 * - Re-traversable streams via closure factory encapsulation
 * - O(1) memory footprint during filtering, projection, and slicing
 * - Bidirectional interoperability with in-memory ALinqCollection
 *
 * @version    1.1.0
 * @package    Antevemus\ALinq
 * @subpackage Core
 * @author     Heliton Junior (CTO) - <contato@antevemus.com.br>
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda.
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
        return new self(function () use ($filePath, $bufferSize, $lineParser) {
            $handle = @fopen($filePath, 'r');
            if ($handle === false) {
                throw new RuntimeException(sprintf('Unable to open file for streaming: "%s"', $filePath));
            }

            try {
                $lineNumber = 0;
                while (($line = fgets($handle, $bufferSize)) !== false) {
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
        return new self(function () use ($statement, $rowMapper) {
            $index = 0;
            while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
                yield $index => ($rowMapper !== null ? $rowMapper($row, $index) : $row);
                $index++;
            }
        });
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
            $chunk = [];
            foreach ($this as $key => $item) {
                $chunk[$key] = $item;
                if (count($chunk) === $size) {
                    yield $chunk;
                    $chunk = [];
                }
            }

            if (!empty($chunk)) {
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

            while ($count < $size) {
                yield $count => $value;
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
        $cache = null;

        return new self(function () use (&$cache) {
            if ($cache === null) {
                $cache = [];
                foreach ($this as $key => $item) {
                    $cache[$key] = $item;
                    yield $key => $item;
                }
            } else {
                foreach ($cache as $key => $item) {
                    yield $key => $item;
                }
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
        $result = [];
        foreach ($this as $key => $item) {
            $result[$key] = $item;
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
