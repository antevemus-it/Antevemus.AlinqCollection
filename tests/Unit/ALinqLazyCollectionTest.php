<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use ArrayIterator;
use Closure;
use OverflowException;
use PDO;
use PHPUnit\Framework\TestCase;
use UnderflowException;

/**
 * ALinqLazyCollectionTest - Exhaustive unit test suite for ALinqLazyCollection streaming engine
 *
 * Validates O(1) memory guarantees, generator rewindability, file/CSV/cursor streams,
 * LINQ pipeline transformations, short-circuiting, and eager materialization.
 *
 * @version    1.1.0
 * @package    Antevemus\ALinq\Tests
 * @subpackage Unit
 * @author     Heliton Junior (CTO) - <contato@antevemus.com.br>
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda.
 * @license    MIT License
 */
final class ALinqLazyCollectionTest extends TestCase
{
    // =========================================================================
    // 1. Factory & Instantiation Tests
    // =========================================================================

    public function testFromWithArray(): void
    {
        $lazy = ALinqLazyCollection::from([1, 2, 3]);

        $this->assertInstanceOf(IALinqLazyCollection::class, $lazy);
        $this->assertSame([1, 2, 3], $lazy->toArray());
    }

    public function testFromWithGeneratorClosure(): void
    {
        $lazy = ALinqLazyCollection::from(function () {
            yield 'a';
            yield 'b';
            yield 'c';
        });

        $this->assertSame(['a', 'b', 'c'], $lazy->toArray());
    }

    public function testRangeAscendingAndDescending(): void
    {
        $asc = ALinqLazyCollection::range(1, 5, 1)->toArray();
        $this->assertSame([1, 2, 3, 4, 5], $asc);

        $desc = ALinqLazyCollection::range(10, 2, 2)->toArray();
        $this->assertSame([10, 8, 6, 4, 2], $desc);
    }

    public function testRepeat(): void
    {
        $lazy = ALinqLazyCollection::repeat('X', 4);
        $this->assertSame(['X', 'X', 'X', 'X'], $lazy->toArray());
    }

    public function testEmpty(): void
    {
        $lazy = ALinqLazyCollection::empty();
        $this->assertSame([], $lazy->toArray());
        $this->assertSame(0, $lazy->count());
    }

    // =========================================================================
    // 2. Multi-Pass Re-traversal (Rewindability)
    // =========================================================================

    public function testGeneratorClosureCanBeIteratedMultipleTimes(): void
    {
        $invocations = 0;
        $lazy = ALinqLazyCollection::from(function () use (&$invocations) {
            $invocations++;
            yield 10;
            yield 20;
        });

        // Pass 1
        $pass1 = $lazy->toArray();
        $this->assertSame([10, 20], $pass1);

        // Pass 2 - Must not throw "Cannot traverse an already closed generator"
        $pass2 = $lazy->toArray();
        $this->assertSame([10, 20], $pass2);

        $this->assertSame(2, $invocations);
    }

    public function testRememberCachesEvaluatedItems(): void
    {
        $invocations = 0;
        $lazy = ALinqLazyCollection::from(function () use (&$invocations) {
            $invocations++;
            yield 'k1' => 'v1';
            yield 'k2' => 'v2';
        })->remember();

        $firstRun = $lazy->toArray();
        $secondRun = $lazy->toArray();

        $this->assertSame(['k1' => 'v1', 'k2' => 'v2'], $firstRun);
        $this->assertSame(['k1' => 'v1', 'k2' => 'v2'], $secondRun);
        // Because of remember(), the upstream generator factory ran only once!
        $this->assertSame(1, $invocations);
    }

    // =========================================================================
    // 3. Transformation & Slicing Pipeline Operators
    // =========================================================================

    public function testWhereAndWhereNot(): void
    {
        $numbers = ALinqLazyCollection::from([1, 2, 3, 4, 5, 6]);

        // Key policy (review 2026-10-08, decision 1a): a list comes out reindexed, as the
        // eager where() does; before, the lazy side kept the gaps ([1 => 2, 3 => 4, 5 => 6]).
        $evens = $numbers->where(fn($n) => $n % 2 === 0)->toArray();
        $this->assertSame([2, 4, 6], $evens);

        $odds = $numbers->whereNot(fn($n) => $n % 2 === 0)->toArray();
        $this->assertSame([1, 3, 5], $odds);

        // A dictionary keeps its keys.
        $dictionary = ALinqLazyCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertSame(['b' => 2], $dictionary->where(fn($n) => $n === 2)->toArray());
        $this->assertSame(['a' => 1, 'c' => 3], $dictionary->whereNot(fn($n) => $n === 2)->toArray());
    }

    // =========================================================================
    // 3b. Key policy and materialization (review 2026-10-08, 4.1 / 4.3 / 4.4)
    // =========================================================================

    public function testMaterializersNeverLoseItemsWithRepeatedKeys(): void
    {
        // Two `yield from` restart the integer keys: 0,1,0,1. Every materializer collapsed
        // on the key and returned two items while count() said four.
        $source = static fn() => (static function () {
            yield from [1, 2];
            yield from [3, 4];
        })();
        $stream = ALinqLazyCollection::from($source);

        $this->assertSame(4, $stream->count());
        $this->assertSame([1, 2, 3, 4], $stream->toArray());
        $this->assertSame([1, 2, 3, 4], $stream->toCollection()->toArray());
        $this->assertSame(4, $stream->toCollection()->count());
        $this->assertSame(['0' => 1, '1' => 2, '2' => 3, '3' => 4], (array) $stream->toObject());
        $this->assertSame([[1, 2, 3], [4]], self::chunks($stream->chunk(3)));
        $this->assertSame([1, 2, 3, 4, 'p'], $stream->pad(5, 'p')->toArray());
        $this->assertSame([2, 3, 4], $stream->skip(1)->toArray());
        $this->assertSame([1, 2, 3], $stream->take(3)->toArray());

        // Six items that all carry key 0: the chunk used to "never close".
        $sameKey = ALinqLazyCollection::from(static function () {
            for ($i = 1; $i <= 6; $i++) {
                yield 0 => $i;
            }
        });
        $this->assertSame([[1, 2], [3, 4], [5, 6]], self::chunks($sameKey->chunk(2)));
        $this->assertSame(3, $sameKey->chunk(2)->count());

        // A dictionary keeps its keys; a colliding key is appended, never overwritten.
        $dictionary = ALinqLazyCollection::from(static function () {
            yield 'a' => 1;
            yield 'b' => 2;
            yield 'a' => 3;
        });
        $this->assertSame(['a' => 1, 'b' => 2, 0 => 3], $dictionary->toArray());
        $this->assertSame(3, $dictionary->toCollection()->count());
    }

    public function testPadNeverOverwritesRealItems(): void
    {
        // pad() keyed the padding by its counter, which collided with the keys that
        // where()/skip() leave behind: "c" became "p" and count() still said five.
        $padded = ALinqLazyCollection::from(['a', 'b', 'c'])->where(fn($v) => $v !== 'a')->pad(5, 'p');
        $this->assertSame(['b', 'c', 'p', 'p', 'p'], $padded->toArray());
        $this->assertSame(5, $padded->count());

        $this->assertSame(['c', 'd', 'p', 'p'], ALinqLazyCollection::from(['a', 'b', 'c', 'd'])->skip(2)->pad(4, 'p')->toArray());
        // [0 => 'a', 3 => 'b'] is a dictionary (not a list): since 1.3.0 (RN-02) it keeps its
        // keys and the padding is appended; before, the lazy side reindexed it to a list.
        $this->assertSame([0 => 'a', 3 => 'b', 4 => 'p', 5 => 'p'], ALinqLazyCollection::from([0 => 'a', 3 => 'b'])->pad(4, 'p')->toArray());

        // Parity with the eager side on the same pipeline.
        $this->assertSame(
            ALinqCollection::from(['a', 'b', 'c'])->where(fn($v) => $v !== 'a')->pad(5, 'p')->toArray(),
            $padded->toArray()
        );
    }

    public function testSelect(): void
    {
        $stream = ALinqLazyCollection::from([1, 2, 3])
            ->select(fn($x) => $x * 10)
            ->toArray();

        $this->assertSame([10, 20, 30], $stream);
    }

    public function testSelectManyFlattensNestedSequences(): void
    {
        $records = ALinqLazyCollection::from([
            ['id' => 1, 'tags' => ['php', 'linq']],
            ['id' => 2, 'tags' => ['stream', 'generator']],
        ]);

        $flattened = $records->selectMany(fn($r) => $r['tags'])->toArray();
        $this->assertSame(['php', 'linq', 'stream', 'generator'], array_values($flattened));
    }

    public function testTakeAndSkip(): void
    {
        $range = ALinqLazyCollection::range(1, 100);

        $page = $range->skip(10)->take(5)->toArray();
        $this->assertSame([11, 12, 13, 14, 15], array_values($page));
    }

    public function testTakeWhileAndSkipWhile(): void
    {
        $numbers = ALinqLazyCollection::from([2, 4, 6, 7, 8, 10]);

        $whileEvens = $numbers->takeWhile(fn($n) => $n % 2 === 0)->toArray();
        $this->assertSame([2, 4, 6], array_values($whileEvens));

        $afterFirstOdd = $numbers->skipWhile(fn($n) => $n % 2 === 0)->toArray();
        $this->assertSame([7, 8, 10], array_values($afterFirstOdd));
    }

    public function testDistinctAndDistinctBy(): void
    {
        $items = ALinqLazyCollection::from([1, 2, 2, 3, 1, 4]);
        $this->assertSame([1, 2, 3, 4], array_values($items->distinct()->toArray()));

        $users = ALinqLazyCollection::from([
            ['id' => 1, 'role' => 'admin'],
            ['id' => 2, 'role' => 'user'],
            ['id' => 3, 'role' => 'admin'],
        ]);
        $uniqueRoles = $users->distinctBy(fn($u) => $u['role'])->toArray();
        $this->assertCount(2, $uniqueRoles);
        $this->assertSame(1, array_values($uniqueRoles)[0]['id']);
        $this->assertSame(2, array_values($uniqueRoles)[1]['id']);
    }

    public function testChunkAndPad(): void
    {
        $chunked = ALinqLazyCollection::range(1, 7)->chunk(3);
        $this->assertContainsOnlyInstancesOf(ALinqCollection::class, $chunked->toArray());
        $this->assertSame([[1, 2, 3], [4, 5, 6], [7]], self::chunks($chunked));
        // D10: each chunk stays queryable
        $this->assertSame([6, 15, 7], $chunked->select(fn(ALinqCollection $c) => $c->sum())->toArray());

        $padded = ALinqLazyCollection::from([10, 20])->pad(5, 0)->toArray();
        $this->assertSame([10, 20, 0, 0, 0], array_values($padded));
    }

    public function testConcatAndZip(): void
    {
        $first = ALinqLazyCollection::from(['A', 'B']);
        $second = ['C', 'D'];

        $concatenated = $first->concat($second)->toArray();
        $this->assertSame(['A', 'B', 'C', 'D'], array_values($concatenated));

        $letters = ALinqLazyCollection::from(['X', 'Y', 'Z']);
        $numbers = [1, 2, 3];

        $zipped = $letters->zip($numbers, fn($l, $n) => "$l$n")->toArray();
        $this->assertSame(['X1', 'Y2', 'Z3'], array_values($zipped));
    }

    public function testTapPerformsSideEffect(): void
    {
        $log = [];
        $result = ALinqLazyCollection::from([1, 2, 3])
            ->tap(function ($item) use (&$log) {
                $log[] = "saw_$item";
            })
            ->select(fn($x) => $x * 2)
            ->toArray();

        $this->assertSame([2, 4, 6], $result);
        $this->assertSame(['saw_1', 'saw_2', 'saw_3'], $log);
    }

    // =========================================================================
    // 4. Memory Constancy Guarantee (O(1) RAM)
    // =========================================================================

    public function testLargeDatasetMaintainsConstantMemoryUsage(): void
    {
        $memBefore = memory_get_usage();

        // 500,000 numbers streamed lazily
        $sumOfFirst100Squares = ALinqLazyCollection::range(1, 500000)
            ->where(fn($n) => $n % 2 === 0)
            ->select(fn($n) => $n * $n)
            ->take(100)
            ->sum();

        $memAfter = memory_get_usage();
        $memDelta = $memAfter - $memBefore;

        $this->assertGreaterThan(0, $sumOfFirst100Squares);
        // Memory delta must be negligible (< 100 KB) since no 500,000 items array was created
        $this->assertLessThan(100 * 1024, $memDelta, 'Memory usage exceeded O(1) streaming bounds.');
    }

    // =========================================================================
    // 5. File & CSV Streaming with Automatic Resource Disposal
    // =========================================================================

    public function testFromFileStreamsLinesAndClosesResource(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'alinq_test_');
        $lines = ["Line 1", "Line 2", "Line 3", "Line 4", "Line 5"];
        file_put_contents($tempFile, implode("\n", $lines));

        try {
            $stream = ALinqLazyCollection::fromFile($tempFile, 1024, fn($line) => strtoupper($line));
            $this->assertSame(["LINE 1", "LINE 2", "LINE 3", "LINE 4", "LINE 5"], $stream->toArray());

            // Test short-circuiting: take(2) must not read all lines
            $taken = ALinqLazyCollection::fromFile($tempFile)->take(2)->toArray();
            $this->assertSame(["Line 1", "Line 2"], $taken);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testFromCsvStreamsAssociativeRows(): void
    {
        $tempCsv = tempnam(sys_get_temp_dir(), 'alinq_csv_');
        $csvContent = "id,name,role\n1,Alice,admin\n2,Bob,editor\n3,Charlie,viewer";
        file_put_contents($tempCsv, $csvContent);

        try {
            $stream = ALinqLazyCollection::fromCsv($tempCsv);
            $rows = $stream->toArray();

            $this->assertCount(3, $rows);
            $this->assertSame(['id' => '1', 'name' => 'Alice', 'role' => 'admin'], $rows[0]);
            $this->assertSame(['id' => '2', 'name' => 'Bob', 'role' => 'editor'], $rows[1]);
            $this->assertSame(['id' => '3', 'name' => 'Charlie', 'role' => 'viewer'], $rows[2]);

            // Querying CSV with LINQ operators
            $adminName = $stream->where(fn($r) => $r['role'] === 'admin')->first()['name'];
            $this->assertSame('Alice', $adminName);
        } finally {
            @unlink($tempCsv);
        }
    }

    // =========================================================================
    // 6. Database Cursor Streaming (PDO SQLite)
    // =========================================================================

    public function testFromCursorStreamsDatabaseRows(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE employees (id INTEGER PRIMARY KEY, name TEXT, salary REAL)');
        $pdo->exec("INSERT INTO employees VALUES (1, 'Alice', 5000), (2, 'Bob', 3500), (3, 'Charlie', 4200)");

        $stmt = $pdo->query('SELECT * FROM employees ORDER BY id ASC');
        $this->assertInstanceOf(\PDOStatement::class, $stmt);

        $stream = ALinqLazyCollection::fromCursor($stmt, fn($row) => [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'salary' => (float)$row['salary'],
        ]);

        $highestEarner = $stream->maxBy(fn($e) => $e['salary']);
        $this->assertSame('Alice', $highestEarner['name']);
        $this->assertSame(5000.0, $highestEarner['salary']);
    }

    // =========================================================================
    // 7. Terminal Operations & Aggregations
    // =========================================================================

    public function testFirstAndFirstOrDefault(): void
    {
        $lazy = ALinqLazyCollection::from([10, 25, 30, 45]);

        $this->assertSame(25, $lazy->first(fn($x) => $x > 20));
        $this->assertSame(10, $lazy->first());

        $this->assertSame('DEFAULT', $lazy->firstOrDefault('DEFAULT', fn($x) => $x > 100));
        $this->assertSame(10, $lazy->firstOrDefault('DEFAULT'));

        $this->expectException(UnderflowException::class);
        ALinqLazyCollection::empty()->first();
    }

    public function testLastAndLastOrDefault(): void
    {
        $lazy = ALinqLazyCollection::from([10, 25, 30, 45]);

        $this->assertSame(45, $lazy->last());
        $this->assertSame(30, $lazy->last(fn($x) => $x < 40));
        $this->assertSame(999, ALinqLazyCollection::empty()->lastOrDefault(999));
    }

    public function testSingleOrDefault(): void
    {
        $single = ALinqLazyCollection::from([42]);
        $this->assertSame(42, $single->singleOrDefault());

        $empty = ALinqLazyCollection::empty();
        $this->assertNull($empty->singleOrDefault());

        $multiple = ALinqLazyCollection::from([1, 2, 3]);
        $this->expectException(OverflowException::class);
        $multiple->singleOrDefault();
    }

    public function testAnyAndAll(): void
    {
        $numbers = ALinqLazyCollection::from([2, 4, 6, 8]);

        $this->assertTrue($numbers->any(fn($n) => $n === 6));
        $this->assertFalse($numbers->any(fn($n) => $n === 99));

        $this->assertTrue($numbers->all(fn($n) => $n % 2 === 0));
        $this->assertFalse($numbers->all(fn($n) => $n > 5));

        $this->assertTrue(ALinqLazyCollection::from([1])->any());
        $this->assertFalse(ALinqLazyCollection::empty()->any());
    }

    public function testContains(): void
    {
        $fruits = ALinqLazyCollection::from(['apple', 'banana', 'cherry']);

        $this->assertTrue($fruits->contains('banana'));
        $this->assertFalse($fruits->contains('orange'));

        $caseInsensitive = $fruits->contains('BANANA', fn($a, $b) => strcasecmp($a, $b) === 0);
        $this->assertTrue($caseInsensitive);
    }

    public function testArithmeticAggregations(): void
    {
        $sales = ALinqLazyCollection::from([
            ['product' => 'A', 'qty' => 10, 'price' => 5.0],
            ['product' => 'B', 'qty' => 2,  'price' => 15.0],
            ['product' => 'C', 'qty' => 4,  'price' => 20.0],
        ]);

        $totalUnits = $sales->sum(fn($s) => $s['qty']);
        $this->assertSame(16, $totalUnits);

        $avgPrice = $sales->average(fn($s) => $s['price']);
        $this->assertEqualsWithDelta(13.333, $avgPrice, 0.001);

        $cheapest = $sales->min(fn($s) => $s['price']);
        $this->assertSame(5.0, $cheapest);

        $mostExpensiveItem = $sales->maxBy(fn($s) => $s['price']);
        $this->assertSame('C', $mostExpensiveItem['product']);

        $folded = $sales->aggregate(0.0, fn($acc, $s) => $acc + ($s['qty'] * $s['price']));
        // (10*5) + (2*15) + (4*20) = 50 + 30 + 80 = 160.0
        $this->assertSame(160.0, $folded);
    }

    public function testEach(): void
    {
        $collected = [];
        $lazy = ALinqLazyCollection::from(['x', 'y', 'z']);
        $chain = $lazy->each(function ($val, $key) use (&$collected) {
            $collected[$key] = $val;
        });

        $this->assertSame($lazy, $chain);
        $this->assertSame(['x', 'y', 'z'], $collected);
    }

    // =========================================================================
    // 8. Interoperability with Eager ALinqCollection
    // =========================================================================

    public function testToCollectionConvertsToEagerALinqCollection(): void
    {
        $lazy = ALinqLazyCollection::range(1, 5)->where(fn($x) => $x > 2);
        $eager = $lazy->toCollection();

        $this->assertInstanceOf(ALinqCollection::class, $eager);
        $this->assertSame([3, 4, 5], array_values($eager->toArray()));
    }

    public function testALinqCollectionLazyBridge(): void
    {
        $eager = ALinqCollection::from([10, 20, 30, 40]);
        $lazy = $eager->lazy();

        $this->assertInstanceOf(ALinqLazyCollection::class, $lazy);
        $result = $lazy->where(fn($x) => $x >= 20)->take(2)->toArray();

        $this->assertSame([20, 30], array_values($result));
    }

    public function testALinqCollectionFromFileShortcut(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'alinq_eager_bridge_');
        file_put_contents($tempFile, "alpha\nbeta\ngamma");

        try {
            $lazy = ALinqCollection::fromFile($tempFile);
            $this->assertInstanceOf(ALinqLazyCollection::class, $lazy);
            $this->assertSame(['alpha', 'beta', 'gamma'], $lazy->toArray());
        } finally {
            @unlink($tempFile);
        }
    }

    // =========================================================================
    // 11. Regression: silent wrong results (review 2026-10-07, §5.1 / §5.3 / §5.2)
    // =========================================================================

    public function testRememberDoesNotPoisonCacheOnPartialPass(): void
    {
        $invocations = 0;
        $lazy = ALinqLazyCollection::from(function () use (&$invocations) {
            $invocations++;
            yield from range(1, 10);
        })->remember();

        // Short-circuiting passes must never leave a truncated cache behind.
        $this->assertSame(1, $lazy->first());
        $this->assertSame(range(1, 10), $lazy->toArray(), 'toArray() after first() served a truncated cache');

        $lazy2 = ALinqLazyCollection::from(fn() => yield from range(1, 10))->remember();
        $this->assertTrue($lazy2->any(fn($x) => $x > 2));
        $this->assertSame(10, $lazy2->count(), 'count() after any() served a truncated cache');

        $lazy3 = ALinqLazyCollection::from(fn() => yield from range(1, 10))->remember();
        $this->assertSame([1, 2, 3], $lazy3->take(3)->toArray());
        $this->assertSame(range(1, 10), $lazy3->toArray(), 'toArray() after take(3) served a truncated cache');

        $lazy4 = ALinqLazyCollection::from(fn() => yield from range(1, 10))->remember();
        $this->assertCount(10, $lazy4->zip($lazy4)->toArray(), 'zip() of the same remembered stream lost items');

        // Resumable memoization (review 2026-10-08, 4.12): the partial pass caches what it
        // consumed and the next pass continues from there, so the upstream runs ONCE. The
        // 1.1.1 fix discarded the partial buffer and re-ran the upstream (2 invocations).
        $this->assertSame(1, $invocations);
        $lazy->toArray();
        $lazy->count();
        $this->assertSame(1, $invocations, 'upstream re-executed after the cache was complete');
    }

    public function testRememberReplaysRepeatedKeysAndResumesACursor(): void
    {
        // Repeated keys: the cache was keyed by the source key, so the second pass saw
        // two items where the first saw four (review 2026-10-08, 4.2).
        $invocations = 0;
        $remembered = ALinqLazyCollection::from(static function () use (&$invocations) {
            $invocations++;
            yield from [1, 2];
            yield from [3, 4];
        })->remember();

        $this->assertSame(4, $remembered->count());
        $this->assertSame(4, $remembered->count());
        $this->assertSame([1, 2, 3, 4], $remembered->toArray());
        $this->assertSame(10, $remembered->sum());
        $this->assertSame(1, $invocations);

        // Single-pass cursor + remember() + partial pass: before, toArray() after first()
        // threw "already been traversed" and the rows were lost (review 2026-10-08, 4.12).
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER, n TEXT)');
        $pdo->exec("INSERT INTO t VALUES (1, 'a'), (2, 'b'), (3, 'c')");
        $cursor = ALinqLazyCollection::fromCursor($pdo->query('SELECT id, n FROM t ORDER BY id'))->remember();

        $this->assertSame(['id' => 1, 'n' => 'a'], $cursor->first());
        $this->assertSame([['id' => 1, 'n' => 'a'], ['id' => 2, 'n' => 'b'], ['id' => 3, 'n' => 'c']], $cursor->toArray());
        $this->assertSame(3, $cursor->count(), 'the remembered cursor is re-traversable after a partial pass');
    }

    public function testFromFileDoesNotSplitLinesLongerThanBuffer(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'alinq_longline_');
        $longLine = str_repeat('x', 5000);
        file_put_contents($tempFile, $longLine . "\nb\nc\n");

        try {
            $stream = ALinqLazyCollection::fromFile($tempFile);
            $this->assertSame(3, $stream->count(), 'a line longer than the buffer was split into several items');
            $this->assertSame([$longLine, 'b', 'c'], $stream->toArray());

            // Explicit buffer size smaller than the line must not change the result either.
            $this->assertSame(3, ALinqLazyCollection::fromFile($tempFile, 64)->count());
        } finally {
            @unlink($tempFile);
        }
    }

    public function testCursorSecondPassThrowsInsteadOfReturningEmpty(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (id INTEGER PRIMARY KEY)');
        $pdo->exec('INSERT INTO t VALUES (1), (2), (3)');

        // fromCursor(): the first pass works, the second must fail loudly, never yield [].
        $stream = ALinqLazyCollection::fromCursor($pdo->query('SELECT * FROM t ORDER BY id'));
        $this->assertSame(3, $stream->count());
        $second = null;
        $thrown = null;
        try {
            $second = $stream->toArray();
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'second pass over a consumed PDO cursor returned ' . json_encode($second) . ' instead of throwing');
        $this->assertStringContainsString('remember()', $thrown->getMessage());

        // from(PDOStatement): same contract.
        $stream2 = ALinqLazyCollection::from($pdo->query('SELECT * FROM t ORDER BY id'));
        $this->assertSame(2, $stream2->where(fn($r) => (int)$r['id'] > 1)->count());
        $second = null;
        $thrown = null;
        try {
            $second = $stream2->toArray();
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'second pass over a consumed PDOStatement returned ' . json_encode($second) . ' instead of throwing');
        $this->assertStringContainsString('remember()', $thrown->getMessage());

        // remember() with a complete first pass is the documented way to re-traverse a cursor.
        $cached = ALinqLazyCollection::fromCursor($pdo->query('SELECT * FROM t ORDER BY id'))->remember();
        $this->assertSame(3, $cached->count());
        $this->assertCount(3, $cached->toArray());
    }


    // =========================================================================
    // 12. Callable arity (review 2026-10-08, decision 4a) and typed distinct()
    // =========================================================================

    public function testCallablesFollowTheArityRuleOnTheLazySide(): void
    {
        // Native one-argument functions used to receive ($item, $key) and throw ArgumentCountError.
        $this->assertSame(['1', '2', '3'], ALinqLazyCollection::from([1, 2, 3])->select('strval')->toArray());
        $this->assertSame([1, 2], ALinqLazyCollection::from([1, 'a', 2])->where('is_int')->toArray());
        $this->assertSame(1, ALinqLazyCollection::from([1, 'a', 2])->first('is_int'));
        $this->assertSame(6, ALinqLazyCollection::from(['1', '2', '3'])->sum('intval'));
        $this->assertTrue(ALinqLazyCollection::from([1, 'a'])->any('is_string'));

        // Two-parameter callbacks still receive the key.
        $this->assertSame(['x' => 'x:1', 'y' => 'y:2'], ALinqLazyCollection::from(['x' => 1, 'y' => 2])->select(fn($v, $k) => "$k:$v")->toArray());
        $this->assertSame(3, ALinqLazyCollection::from([10, 20, 30])->sum(fn($v, $k) => $k));

        // distinct() shares the typed identity rule with the eager side: 1, '1' and true stay distinct.
        $this->assertSame([1, '1', true], ALinqLazyCollection::from([1, '1', true, 1])->distinct()->toArray());
        $this->assertSame([[1, 2], [3, 4]], ALinqLazyCollection::from([[1, 2], [3, 4], [1, 2]])->distinct()->toArray());
    }

    // =========================================================================
    // 13. The 1.3.0 contract (review 2026-10-08, forward 015): keys, empty, null, arguments
    // =========================================================================

    /**
     * @return array<int|string, mixed> the keys and items as the stream emits them
     */
    private static function streamed(iterable $stream): array
    {
        $pairs = [];
        foreach ($stream as $key => $item) {
            $pairs[] = [$key, $item];
        }
        return $pairs;
    }

    public function testWhereOnListEmitsSequentialKeysWhileStreaming(): void
    {
        // RN-03: a list source emits renumbered keys already while streaming, not only after
        // toArray(); before, foreach saw the gaps [1 => 'b', 2 => 'c'].
        $stream = ALinqLazyCollection::from(['a', 'b', 'c'])->where(fn($v) => $v !== 'a');
        $this->assertSame([[0, 'b'], [1, 'c']], self::streamed($stream));

        $pipeline = ALinqLazyCollection::from([1, 2, 3, 4, 5, 6])
            ->skip(1)
            ->where(fn($v) => $v % 2 === 0)
            ->select(fn($v) => $v * 10)
            ->distinct()
            ->tap(fn() => null)
            ->take(2);
        $this->assertSame([[0, 20], [1, 40]], self::streamed($pipeline));
        $this->assertSame([[0, 60]], self::streamed(
            ALinqLazyCollection::from([10, 20, 60, 70])->skipWhile(fn($v) => $v < 60)->takeWhile(fn($v) => $v < 70)->skip(0)
        ));

        // Sources that are lists by construction.
        $this->assertSame([[0, 3], [1, 4]], self::streamed(ALinqLazyCollection::range(1, 4)->where(fn($v) => $v > 2)));
        $this->assertSame([[0, 'x']], self::streamed(ALinqLazyCollection::repeat('x', 3)->take(1)));
        $this->assertSame([[0, 2]], self::streamed(ALinqLazyCollection::from([1, 2])->skip(1)));
    }

    public function testWhereOnDictionaryKeepsKeys(): void
    {
        $dictionary = ALinqLazyCollection::from(['x' => 1, 'y' => 2, 'z' => 3]);
        $this->assertSame([['y', 2], ['z', 3]], self::streamed($dictionary->where(fn($v) => $v > 1)));
        $this->assertSame(['y' => 2, 'z' => 3], $dictionary->where(fn($v) => $v > 1)->toArray());
        $this->assertSame(['x' => 10, 'y' => 20, 'z' => 30], $dictionary->select(fn($v) => $v * 10)->toArray());

        // Integer keys that are not sequential are a dictionary too (RN-01), on both sides.
        $sparse = ALinqLazyCollection::from([10 => 'a', 20 => 'b', 30 => 'c']);
        $this->assertSame([20 => 'b', 30 => 'c'], $sparse->skip(1)->toArray());
        $this->assertSame([10 => 'a', 20 => 'b'], $sparse->take(2)->toArray());
        $this->assertSame([10 => 'a'], $sparse->distinct()->takeWhile(fn($v) => $v === 'a')->toArray());
    }

    public function testUnknownSourceKeepsKeysWhileStreamingAndMaterializesByKeyType(): void
    {
        // A generator is an unknown source: the original key is emitted while streaming and
        // toArray() applies the 1.1.2 rule (all-integer keys -> list, otherwise dictionary).
        $integers = ALinqLazyCollection::from(static function () {
            yield 0 => 'a';
            yield 1 => 'b';
            yield 2 => 'c';
        })->where(fn($v) => $v !== 'a');
        $this->assertSame([[1, 'b'], [2, 'c']], self::streamed($integers));
        $this->assertSame(['b', 'c'], $integers->toArray());

        $strings = ALinqLazyCollection::from(static function () {
            yield 'k1' => 1;
            yield 'k2' => 2;
        })->select(fn($v) => $v + 1);
        $this->assertSame([['k1', 2], ['k2', 3]], self::streamed($strings));
        $this->assertSame(['k1' => 2, 'k2' => 3], $strings->toArray());
    }

    public function testChunkPadConcatZipSelectManyFollowTheKeyRule(): void
    {
        // chunk(): a list of collections; inside each chunk a dictionary keeps its keys.
        $this->assertSame([['a' => 1, 'b' => 2], ['c' => 3]], self::chunks(ALinqLazyCollection::from(['a' => 1, 'b' => 2, 'c' => 3])->chunk(2)));
        $this->assertSame([[2, 3], [4]], self::chunks(ALinqLazyCollection::from([1, 2, 3, 4])->where(fn($v) => $v > 1)->chunk(2)));
        $streamedChunks = array_map(
            static fn(array $pair) => [$pair[0], $pair[1]->toArray()],
            self::streamed(ALinqLazyCollection::from([1, 2, 3, 4])->skip(1)->chunk(2))
        );
        $this->assertSame([[0, [2, 3]], [1, [4]]], $streamedChunks);

        // pad(): a dictionary keeps its keys and the padding is appended.
        $this->assertSame(['a' => 1, 0 => 'p', 1 => 'p'], ALinqLazyCollection::from(['a' => 1])->pad(3, 'p')->toArray());
        $this->assertSame([1, 2, 'p'], ALinqLazyCollection::from([1, 2])->pad(3, 'p')->toArray());

        // concat(), zip(), selectMany(): always a list (RN-17), nothing overwritten.
        $this->assertSame([1, 2, 9], ALinqLazyCollection::from(['a' => 1, 'b' => 2])->concat(['a' => 9])->toArray());
        $this->assertSame([[0, 1], [1, 2]], self::streamed(ALinqLazyCollection::from(['a' => 1, 'b' => 2])->concat([])));
        $this->assertSame([[1, 'x'], [2, 'y']], ALinqLazyCollection::from(['a' => 1, 'b' => 2])->zip(['x', 'y'])->toArray());
        $this->assertSame([1, 1, 2, 2], ALinqLazyCollection::from(['a' => 1, 'b' => 2])->selectMany(fn($v) => [$v, $v])->toArray());
        // concat() with a Closure factory and with a plain callable array as data.
        $this->assertSame([1, 2, 3], ALinqLazyCollection::from([1])->concat(fn() => yield from [2, 3])->toArray());
    }

    public function testTakeSkipPadChunkRangeRepeatRejectInvalidArguments(): void
    {
        // RN-13: before, take(-1)/skip(-1)/pad(-1) were silently ignored on the lazy side.
        $calls = [
            'take(-1)'          => fn() => ALinqLazyCollection::from([1])->take(-1),
            'skip(-1)'          => fn() => ALinqLazyCollection::from([1])->skip(-1),
            'pad(-1)'           => fn() => ALinqLazyCollection::from([1])->pad(-1, 0),
            'chunk(0)'          => fn() => ALinqLazyCollection::from([1])->chunk(0),
            'range(step 0)'     => fn() => ALinqLazyCollection::range(1, 5, 0),
            'repeat(-1)'        => fn() => ALinqLazyCollection::repeat('x', -1),
        ];
        foreach ($calls as $label => $call) {
            try {
                $call();
                $this->fail("$label did not throw");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('at least', $e->getMessage(), $label);
            }
        }

        // The boundary values are still accepted.
        $this->assertSame([], ALinqLazyCollection::from([1])->take(0)->toArray());
        $this->assertSame([1], ALinqLazyCollection::from([1])->skip(0)->toArray());
        $this->assertSame([1], ALinqLazyCollection::from([1])->pad(0, 'p')->toArray());
        $this->assertSame([[1]], self::chunks(ALinqLazyCollection::from([1])->chunk(1)));
        $this->assertSame([], ALinqLazyCollection::repeat('x', 0)->toArray());
    }

    public function testAllIsVacuouslyTrueAndDefaultsToTruthiness(): void
    {
        // RN-14: before, all($pred) on an empty stream answered false and all() always false.
        $this->assertTrue(ALinqLazyCollection::empty()->all(fn($v) => $v > 100));
        $this->assertTrue(ALinqLazyCollection::empty()->all());
        $this->assertTrue(ALinqLazyCollection::from([1, 'a', true, [0]])->all());
        $this->assertFalse(ALinqLazyCollection::from([1, 0, 2])->all());
        $this->assertFalse(ALinqLazyCollection::from([1, null])->all());

        // any() without a predicate is unchanged: "has at least one item", truthy or not.
        $this->assertTrue(ALinqLazyCollection::from([0, false])->any());
        $this->assertFalse(ALinqLazyCollection::empty()->any());
    }

    public function testContainsAcceptsBoolOrSpaceshipComparer(): void
    {
        // RN-09: before, the lazy side took the comparer answer as truthy, so a `<=>` comparer
        // reported a non-member as present (`1 <=> 99` is -1, truthy).
        $numbers = ALinqLazyCollection::from([1, 2, 3]);
        $this->assertTrue($numbers->contains(3, fn($a, $b) => $a == $b));
        $this->assertTrue($numbers->contains(3, fn($a, $b) => $a <=> $b));
        $this->assertFalse($numbers->contains(99, fn($a, $b) => $a == $b));
        $this->assertFalse($numbers->contains(99, fn($a, $b) => $a <=> $b));
        $this->assertTrue($numbers->contains('3', fn($a, $b) => $a == $b));
        $this->assertFalse($numbers->contains('3'));
        $this->assertSame(
            ALinqCollection::from([1, 2, 3])->contains(99, fn($a, $b) => $a <=> $b),
            $numbers->contains(99, fn($a, $b) => $a <=> $b)
        );
    }

    public function testAggregationsIgnoreNullAndRejectNonNumeric(): void
    {
        // RN-15: null is skipped (nullable column semantics); before, average([1, null, 2])
        // counted the null in the denominator and min([3, null, 1]) answered null.
        $withNull = ALinqLazyCollection::from([1, null, 2]);
        $this->assertSame(1.5, $withNull->average());
        $this->assertSame(3, $withNull->sum());
        $this->assertSame(1, $withNull->min());
        $this->assertSame(2, $withNull->max());
        $this->assertSame(1, ALinqLazyCollection::from([3, null, 1])->min());
        $this->assertSame(3, ALinqLazyCollection::from([['v' => 1], ['v' => null], ['v' => 2]])->sum(fn($r) => $r['v']));

        // Only null is an empty collection for average/min/max; sum is 0.
        $this->assertSame(0, ALinqLazyCollection::from([null, null])->sum());
        foreach (['average', 'min', 'max'] as $operation) {
            try {
                ALinqLazyCollection::from([null, null])->$operation();
                $this->fail("$operation() over nulls did not throw");
            } catch (UnderflowException $e) {
                $this->assertStringContainsString($operation, $e->getMessage());
            }
        }

        // Numeric strings and booleans are numbers; anything else throws, before a TypeError leaked.
        $this->assertSame(3, ALinqLazyCollection::from(['1', '2'])->sum());
        $this->assertSame(2, ALinqLazyCollection::from([true, false, true])->sum());
        $this->assertSame(2.5, ALinqLazyCollection::from(['2.5'])->average());
        foreach ([['a'], [[1]], [new \stdClass()]] as $items) {
            try {
                ALinqLazyCollection::from($items)->sum();
                $this->fail('sum() over ' . get_debug_type($items[0]) . ' did not throw');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('sum() expects numeric values', $e->getMessage());
            }
        }

        // min()/max() compare scalars and DateTimeInterface; arrays and other objects throw.
        $this->assertSame('a', ALinqLazyCollection::from(['b', 'a', 'c'])->min());
        $dates = [new \DateTimeImmutable('2026-01-02'), new \DateTimeImmutable('2026-01-01')];
        $this->assertSame('2026-01-01', ALinqLazyCollection::from($dates)->min()->format('Y-m-d'));
        $this->expectException(\InvalidArgumentException::class);
        ALinqLazyCollection::from([[1], [2]])->max();
    }

    public function testFirstAndLastThrowWhenNoElementMatches(): void
    {
        $numbers = ALinqLazyCollection::from([1, 2]);
        try {
            $numbers->first(fn($v) => $v > 5);
            $this->fail('first() with no match did not throw');
        } catch (UnderflowException $e) {
            $this->assertStringContainsString('no element matches', $e->getMessage());
        }
        try {
            $numbers->last(fn($v) => $v > 5);
            $this->fail('last() with no match did not throw');
        } catch (UnderflowException $e) {
            $this->assertStringContainsString('no element matches', $e->getMessage());
        }
        $this->assertSame('d', $numbers->firstOrDefault('d', fn($v) => $v > 5));
        $this->assertSame('d', $numbers->lastOrDefault('d', fn($v) => $v > 5));
        $this->expectException(UnderflowException::class);
        ALinqLazyCollection::empty()->last();
    }

    public function testSelectManyNonIterableThrows(): void
    {
        // RN-16: before, a scalar returned by the selector was emitted as an item.
        $this->assertSame([1, 1, 2, 2], ALinqLazyCollection::from([1, 2])->selectMany(fn($v) => ALinqCollection::from([$v, $v]))->toArray());
        $this->assertSame([1, 2], ALinqLazyCollection::from([[1], [2]])->selectMany(fn($v) => new ArrayIterator($v))->toArray());
        $this->assertSame([1, 2], ALinqLazyCollection::from([1, 2])->selectMany(fn($v) => yield $v)->toArray());

        try {
            ALinqLazyCollection::from([1])->selectMany(fn($v) => $v)->toArray();
            $this->fail('selectMany() with a scalar did not throw');
        } catch (\UnexpectedValueException $e) {
            $this->assertStringContainsString('int returned for key 0', $e->getMessage());
        }
    }

    public function testCountWithPredicate(): void
    {
        // RN-23: before, count($predicate) ignored the predicate in silence.
        $numbers = ALinqLazyCollection::from([1, 2, 3, 4]);
        $this->assertSame(4, $numbers->count());
        $this->assertSame(2, $numbers->count(fn($v) => $v > 2));
        $this->assertSame(1, $numbers->count(fn($v, $k) => $k === 0));
        $this->assertSame(2, ALinqLazyCollection::from([1, 'a', 2])->count('is_int'));
        $this->assertSame(0, ALinqLazyCollection::empty()->count(fn() => true));
    }

    public function testJsonSerialize(): void
    {
        // RN-24: before, json_encode() of a lazy collection produced "{}".
        $this->assertInstanceOf(\JsonSerializable::class, ALinqLazyCollection::empty());
        $this->assertSame('[1,2]', json_encode(ALinqLazyCollection::from([1, 2])));
        $this->assertSame('{"a":1}', json_encode(ALinqLazyCollection::from(['a' => 1])));
        $this->assertSame('[2,3]', json_encode(ALinqLazyCollection::from([1, 2, 3])->where(fn($v) => $v > 1)));
        $this->assertSame('[]', json_encode(ALinqLazyCollection::empty()));
        $this->assertSame('{"10":"a"}', json_encode(ALinqLazyCollection::from([10 => 'a', 20 => 'b'])->take(1)));
    }

    public function testFromArrayCallableIsDataAndOnlyClosureIsAFactory(): void
    {
        // RN-18: before, a callable array was taken as a factory and threw ArgumentCountError.
        $object = new class {
            public function method(): string
            {
                return 'called';
            }
        };
        $this->assertSame([$object, 'method'], ALinqLazyCollection::from([$object, 'method'])->toArray());
        $this->assertSame(2, ALinqLazyCollection::from([$object, 'method'])->count());
        $this->assertSame(['DateTime', 'createFromFormat'], ALinqLazyCollection::from(['DateTime', 'createFromFormat'])->toArray());

        // A Closure is a re-iterable factory; a closure made from a callable works the same.
        $this->assertSame([1, 2], ALinqLazyCollection::from(fn() => yield from [1, 2])->toArray());
        $factory = static fn(): array => [3, 4];
        $this->assertSame([3, 4], ALinqLazyCollection::from(Closure::fromCallable($factory))->toArray());

        // A string callable cannot be iterated: refused with a clear message.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Closure factory');
        ALinqLazyCollection::from('strlen');
    }

    /**
     * @return array<int, array> the chunks as native arrays (D10: chunk() yields collections)
     */
    private static function chunks(ALinqLazyCollection $chunked): array
    {
        return array_map(static fn(ALinqCollection $chunk) => $chunk->toArray(), $chunked->toArray());
    }
}
