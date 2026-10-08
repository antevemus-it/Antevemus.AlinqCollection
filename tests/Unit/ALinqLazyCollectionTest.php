<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use ArrayIterator;
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
        $this->assertSame([[1, 2, 3], [4]], $stream->chunk(3)->toArray());
        $this->assertSame([1, 2, 3, 4, 'p'], $stream->pad(5, 'p')->toArray());
        $this->assertSame([2, 3, 4], $stream->skip(1)->toArray());
        $this->assertSame([1, 2, 3], $stream->take(3)->toArray());

        // Six items that all carry key 0: the chunk used to "never close".
        $sameKey = ALinqLazyCollection::from(static function () {
            for ($i = 1; $i <= 6; $i++) {
                yield 0 => $i;
            }
        });
        $this->assertSame([[1, 2], [3, 4], [5, 6]], $sameKey->chunk(2)->toArray());
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
        $this->assertSame(['a', 'b', 'p', 'p'], ALinqLazyCollection::from([0 => 'a', 3 => 'b'])->pad(4, 'p')->toArray());

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
        $chunks = ALinqLazyCollection::range(1, 7)->chunk(3)->toArray();
        $this->assertSame([[1, 2, 3], [4, 5, 6], [7]], array_values(array_map('array_values', $chunks)));

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
}
