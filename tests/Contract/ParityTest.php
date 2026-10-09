<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Contract;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use ErrorException;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Eager × lazy parity matrix (contract 1.3.0, RN-28).
 *
 * Every operation both collections share runs over the same inputs on both sides and the
 * results are compared strictly, keys included; when an operation throws, the exception
 * class must be the same on both sides. The 2026-10-08 review measured 114 divergences in
 * 350 comparisons on 1.1.1; the contract allows none.
 *
 * The only input whose keys differ between the sides is "D" (a generator that yields the
 * keys 0, 1, 0, 1): the eager side cannot hold repeated keys, so it receives the list
 * [1, 2, 3, 4]. Operations that read the key are therefore excluded for "D" only
 * (KEY_DEPENDENT): the source keys are the data, not a divergence.
 */
final class ParityTest extends TestCase
{
    private const KEY_DEPENDENT = [
        'where(fn(v,k) k!==0 && k!=="a")',
        'select(fn(v,k))',
        'first(fn(v,k) k!==0)',
        'last(fn(v,k) k!==0)',
        'lastOrDefault("d", fn(v,k) k===0)',
        'count(fn(v,k) k!==0)',
        'sum(fn(v,k))',
        'aggregate("", concat k)',
        'each(collect k)',
    ];

    /**
     * @return array<string, array{array, ?\Closure}> [eager input, lazy factory or null (= same array)]
     */
    private static function inputs(): array
    {
        return [
            'A list' => [[1, 2, 3, 4, 5], null],
            'B dictionary' => [['a' => 1, 'b' => 2, 'c' => 3], null],
            'C non-sequential' => [[5 => 'x', 2 => 'y', 9 => 'z'], null],
            'D repeated keys' => [[1, 2, 3, 4], fn() => (function () { yield from [1, 2]; yield from [3, 4]; })()],
            'E empty' => [[], null],
            'F one item' => [[42], null],
            'G falsy values' => [[null, false, 0, '', '0'], null],
        ];
    }

    /**
     * @return array<string, \Closure>
     */
    private static function operations(): array
    {
        return [
            'where(v!==2 && v!=="y")' => fn($c) => $c->where(fn($v) => $v !== 2 && $v !== 'y'),
            'where(fn(v,k) k!==0 && k!=="a")' => fn($c) => $c->where(fn($v, $k) => $k !== 0 && $k !== 'a'),
            'select(json(v).":")' => fn($c) => $c->select(fn($v) => json_encode($v) . ':'),
            'select(fn(v,k))' => fn($c) => $c->select(fn($v, $k) => "$k=" . json_encode($v)),
            'selectMany([v,v])' => fn($c) => $c->selectMany(fn($v) => [$v, $v]),
            'selectMany(scalar)' => fn($c) => $c->selectMany(fn($v) => $v),
            'take(2)' => fn($c) => $c->take(2),
            'take(-2)' => fn($c) => $c->take(-2),
            'take(0)' => fn($c) => $c->take(0),
            'skip(2)' => fn($c) => $c->skip(2),
            'skip(-2)' => fn($c) => $c->skip(-2),
            'distinct()' => fn($c) => $c->distinct(),
            'distinct(fn)' => fn($c) => $c->distinct(fn($v) => is_int($v) ? $v % 2 : (string) $v),
            'distinctBy(fn)' => fn($c) => $c->distinctBy(fn($v) => is_int($v) ? $v % 2 : (string) $v),
            'chunk(2)' => fn($c) => $c->chunk(2),
            'chunk(0)' => fn($c) => $c->chunk(0),
            'pad(6,"p")' => fn($c) => $c->pad(6, 'p'),
            'pad(-6,"p")' => fn($c) => $c->pad(-6, 'p'),
            'concat([9,8])' => fn($c) => $c->concat([9, 8]),
            'concat(["a"=>9,"z"=>8])' => fn($c) => $c->concat(['a' => 9, 'z' => 8]),
            'first()' => fn($c) => $c->first(),
            'first(v>2)' => fn($c) => $c->first(fn($v) => is_int($v) && $v > 2),
            'first(fn(v,k) k!==0)' => fn($c) => $c->first(fn($v, $k) => $k !== 0),
            'firstOrDefault("d")' => fn($c) => $c->firstOrDefault('d'),
            'firstOrDefault("d", v>2)' => fn($c) => $c->firstOrDefault('d', fn($v) => is_int($v) && $v > 2),
            'last()' => fn($c) => $c->last(),
            'last(v<3)' => fn($c) => $c->last(fn($v) => is_int($v) && $v < 3),
            'last(fn(v,k) k!==0)' => fn($c) => $c->last(fn($v, $k) => $k !== 0),
            'lastOrDefault("d")' => fn($c) => $c->lastOrDefault('d'),
            'lastOrDefault("d", fn(v,k) k===0)' => fn($c) => $c->lastOrDefault('d', fn($v, $k) => $k === 0),
            'singleOrDefault()' => fn($c) => $c->singleOrDefault(),
            'singleOrDefault("d", v===3)' => fn($c) => $c->singleOrDefault('d', fn($v) => $v === 3),
            'any()' => fn($c) => $c->any(),
            'any(v===3)' => fn($c) => $c->any(fn($v) => $v === 3),
            'all()' => fn($c) => $c->all(),
            'all(is_int)' => fn($c) => $c->all('is_int'),
            'contains(3)' => fn($c) => $c->contains(3),
            'contains(3, <=>)' => fn($c) => $c->contains(3, fn($a, $b) => $a <=> $b),
            'contains(3, ===)' => fn($c) => $c->contains(3, fn($a, $b) => $a === $b),
            'count()' => fn($c) => $c->count(),
            'count(is_int)' => fn($c) => $c->count('is_int'),
            'count(fn(v,k) k!==0)' => fn($c) => $c->count(fn($v, $k) => $k !== 0),
            'sum()' => fn($c) => $c->sum(),
            'sum(fn(v,k))' => fn($c) => $c->sum(fn($v, $k) => is_int($k) ? $k : 0),
            'average()' => fn($c) => $c->average(),
            'min()' => fn($c) => $c->min(),
            'max()' => fn($c) => $c->max(),
            'minBy(fn)' => fn($c) => $c->minBy(fn($v) => is_int($v) ? -$v : 0),
            'maxBy(fn)' => fn($c) => $c->maxBy(fn($v) => is_int($v) ? -$v : 0),
            'aggregate("", concat k)' => fn($c) => $c->aggregate('', fn($acc, $v, $k) => $acc . "[$k=" . json_encode($v) . ']'),
            'each(collect k)' => function ($c) {
                $seen = [];
                $c->each(function ($v, $k) use (&$seen) { $seen[] = [$k, $v]; });
                return $seen;
            },
            'toArray()' => fn($c) => $c->toArray(),
            'toObject()' => fn($c) => (array) $c->toObject(),
            'jsonSerialize()' => fn($c) => json_encode($c),
        ];
    }

    public function testEveryOperationAnswersTheSameOnBothSides(): void
    {
        $rows = [];
        $total = 0;
        foreach (self::operations() as $opName => $op) {
            foreach (self::inputs() as $inputName => [$array, $factory]) {
                if ($factory !== null && in_array($opName, self::KEY_DEPENDENT, true)) {
                    continue;
                }
                $total++;
                $eager = self::evaluate($op, ALinqCollection::from($array));
                $lazy = self::evaluate($op, $factory !== null ? ALinqLazyCollection::from($factory) : ALinqLazyCollection::from($array));
                if ($eager !== $lazy) {
                    $rows[] = sprintf('%s | %s | eager %s | lazy %s', $opName, $inputName, self::fmt($eager), self::fmt($lazy));
                }
            }
        }

        $this->assertGreaterThan(300, $total);
        $this->assertSame([], $rows, "Eager × lazy divergences:\n" . implode("\n", $rows));
    }

    public function testTheSharedOperationListIsComplete(): void
    {
        // Every public method both classes share must appear in the matrix, so a new
        // operator cannot be added to one side with a different contract unnoticed.
        $covered = [];
        foreach (array_keys(self::operations()) as $name) {
            $covered[] = substr($name, 0, (int) strpos($name, '('));
        }
        $covered = array_unique($covered);

        $eager = array_map(fn($m) => $m->name, (new \ReflectionClass(ALinqCollection::class))->getMethods(\ReflectionMethod::IS_PUBLIC));
        $lazy = array_map(fn($m) => $m->name, (new \ReflectionClass(ALinqLazyCollection::class))->getMethods(\ReflectionMethod::IS_PUBLIC));
        $shared = array_values(array_intersect($eager, $lazy));

        $notQueryOperations = ['__construct', 'getIterator', 'from', 'empty', 'range', 'repeat', 'fromFile', 'fromCsv', 'fromCursor', 'toCollection', 'lazy'];
        $missing = array_diff($shared, $covered, $notQueryOperations);
        sort($missing);

        $this->assertSame([], $missing, 'Shared operations missing from the parity matrix: ' . implode(', ', $missing));
    }

    /**
     * @return array{kind: string, val: mixed}
     */
    private static function evaluate(callable $op, object $collection): array
    {
        set_error_handler(static function (int $no, string $str): bool {
            throw new ErrorException($str, $no);
        });
        try {
            $result = $op($collection);
            if ($result instanceof IALinqCollection || $result instanceof ALinqLazyCollection) {
                return ['kind' => 'collection', 'val' => self::deep($result->toArray())];
            }
            return ['kind' => 'value', 'val' => self::deep($result)];
        } catch (Throwable $e) {
            return ['kind' => 'throw', 'val' => $e::class];
        } finally {
            restore_error_handler();
        }
    }

    private static function deep(mixed $value): mixed
    {
        if ($value instanceof IALinqCollection || $value instanceof ALinqLazyCollection) {
            return self::deep($value->toArray());
        }
        if (is_array($value)) {
            return array_map(self::deep(...), $value);
        }
        return $value;
    }

    private static function fmt(array $r): string
    {
        return $r['kind'] === 'throw' ? 'THROW ' . $r['val'] : json_encode($r['val'], JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}
