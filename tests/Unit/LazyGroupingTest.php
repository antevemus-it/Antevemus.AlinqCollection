<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\DuplicateKeyPolicy;
use Generator;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Grouping on the lazy side (2.2.0, forward 047, D11-D14): groupBy(), countBy() and
 * aggregateBy() are deferred stages; toDictionary() and toLookup() are terminal; every one
 * reads the upstream once per traversal; countBy() keeps one integer per key.
 */
final class LazyGroupingTest extends TestCase
{
    /**
     * A source that counts its openings and refuses a second one (the 036 single-pass probe).
     *
     * @return array{0: ALinqLazyCollection, 1: \Closure(): int}
     */
    private static function singlePass(array $items): array
    {
        $opened = 0;
        $source = ALinqLazyCollection::from(function () use (&$opened, $items): Generator {
            if (++$opened > 1) {
                throw new RuntimeException('opened twice');
            }
            yield from $items;
        });

        return [$source, function () use (&$opened): int {
            return $opened;
        }];
    }

    public function testTheGroupingStagesAreDeferredAndReadTheSourceOnce(): void
    {
        $items = ['x' => 'a1', 'y' => 'b1', 'z' => 'a2'];
        $stages = [
            'groupBy' => [fn($s) => $s->groupBy(fn($v) => $v[0]), fn($r) => array_map(fn($g) => $g->toArray(), $r), ['a' => ['a1', 'a2'], 'b' => ['b1']]],
            'countBy' => [fn($s) => $s->countBy(fn($v) => $v[0]), fn($r) => $r, ['a' => 2, 'b' => 1]],
            'aggregateBy' => [fn($s) => $s->aggregateBy(fn($v) => $v[0], '', fn($acc, $v) => $acc . $v), fn($r) => $r, ['a' => 'a1a2', 'b' => 'b1']],
            'exceptByKeys' => [fn($s) => $s->exceptByKeys(['b'], fn($v) => $v[0]), fn($r) => $r, ['x' => 'a1']],
        ];
        foreach ($stages as $name => [$stage, $shape, $expected]) {
            [$source, $openings] = self::singlePass($items);
            $result = $stage($source);
            $this->assertInstanceOf(ALinqLazyCollection::class, $result, $name);
            $this->assertSame(0, $openings(), "$name: nothing runs before the traversal");
            $this->assertSame($expected, $shape($result->toArray()), $name);
            $this->assertSame(1, $openings(), "$name: one opening");

            // a second traversal re-reads the upstream, as orderBy() does: remember() is the way
            try {
                $result->toArray();
                $this->fail("$name: a second traversal must re-open the source");
            } catch (RuntimeException $e) {
                $this->assertSame('opened twice', $e->getMessage(), $name);
            }

            [$source, $openings] = self::singlePass($items);
            $remembered = $stage($source->remember());
            $this->assertSame($expected, $shape($remembered->toArray()), "$name over remember()");
            $this->assertSame($expected, $shape($remembered->toArray()), "$name over remember(), again");
            $this->assertSame(1, $openings(), "$name over remember(): one opening");
        }
    }

    public function testTheTerminalsReadTheSourceOnce(): void
    {
        $items = [['id' => 1, 'g' => 'a'], ['id' => 2, 'g' => 'b'], ['id' => 3, 'g' => 'a']];
        $terminals = [
            'toDictionary' => [fn($s) => $s->toDictionary(fn($r) => $r['id'], fn($r) => $r['g']), [1 => 'a', 2 => 'b', 3 => 'a']],
            'toDictionary KeepLast' => [fn($s) => $s->toDictionary(fn($r) => $r['g'], fn($r) => $r['id'], DuplicateKeyPolicy::KeepLast), ['a' => 3, 'b' => 2]],
            'toLookup' => [fn($s) => $s->toLookup(fn($r) => $r['g'], fn($r) => $r['id'])->toArray(), ['a' => [1, 3], 'b' => [2]]],
            'implode' => [fn($s) => $s->implode(',', fn($r) => $r['id']), '1,2,3'],
        ];
        foreach ($terminals as $name => [$terminal, $expected]) {
            [$source, $openings] = self::singlePass($items);
            $this->assertSame($expected, $terminal($source), $name);
            $this->assertSame(1, $openings(), $name);
        }
    }

    public function testAggregateByHandsThePositionInsideTheGroupOnBothSides(): void
    {
        // §1.4 item 4 of the forward: the third argument is 0, 1 inside group "a", not 'x', 'z'
        $items = ['x' => 'a1', 'y' => 'b1', 'z' => 'a2'];
        $trace = fn($acc, $item, $key) => $acc . '[' . var_export($key, true) . "=$item]";
        $expected = ['a' => '[0=a1][1=a2]', 'b' => '[0=b1]'];

        $this->assertSame($expected, ALinqCollection::from($items)->aggregateBy(fn($v) => $v[0], '', $trace)->toArray());
        $this->assertSame($expected, ALinqLazyCollection::from($items)->aggregateBy(fn($v) => $v[0], '', $trace)->toArray());
    }

    public function testEverySeedIsTheSameValue(): void
    {
        $seed = new \ArrayObject();
        $add = function (\ArrayObject $acc, int $n): \ArrayObject {
            $acc[] = $n;
            return $acc;
        };
        foreach ([ALinqCollection::from([1, 2, 3]), ALinqLazyCollection::from([1, 2, 3])] as $source) {
            $seed->exchangeArray([]);
            $result = $source->aggregateBy(fn($n) => $n % 2, $seed, $add)->toArray();
            $this->assertSame($result[0], $result[1], 'an object seed is the same instance in every group');
        }
    }

    public function testAnInvalidKeyThrowsAtTraversalWithTheEagerMessage(): void
    {
        $stage = ALinqLazyCollection::from([1, 2])->countBy(fn($n) => $n === 2 ? null : 'k');
        $message = 'groupBy() expects the key selector to return an int, a string or a BackedEnum, null returned for item at key 1';
        try {
            ALinqCollection::from([1, 2])->countBy(fn($n) => $n === 2 ? null : 'k');
            $this->fail('the eager countBy() must throw');
        } catch (InvalidArgumentException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $stage->toArray();
    }

    public function testTheGroupingStagesAreDictionariesDownstream(): void
    {
        $counts = ALinqLazyCollection::range(1, 9)->countBy(fn($n) => $n % 3 === 0 ? 'fizz' : 'other');
        $this->assertSame(['other' => 6, 'fizz' => 3], $counts->toArray());
        $this->assertSame(['fizz' => 3], $counts->where(fn($n) => $n < 5)->toArray());
        $this->assertSame(['other' => 'other:6', 'fizz' => 'fizz:3'], $counts->select(fn($n, $k) => "$k:$n")->toArray());
        $this->assertSame(['small' => [1, 2], 'big' => [3]], ALinqLazyCollection::from([1, 2, 3])->groupBy(fn($n) => $n > 2 ? 'big' : 'small')->select(fn($g) => $g->toArray())->toArray());
    }

    /**
     * RN-04: countBy() keeps one integer per key. Over 200 000 items and 3 keys the peak growth
     * stays far below the ceiling; a countBy() that built the groups (the eager one of 2.1.0)
     * would hold 200 000 integers (more than 4 MB measured) and fail.
     */
    public function testLazyCountByKeepsOneIntegerPerKey(): void
    {
        $ceiling = 1024 * 1024;
        $source = ALinqLazyCollection::from(function (): Generator {
            for ($i = 0; $i < 200000; $i++) {
                yield $i;
            }
        });

        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $counts = $source->countBy(fn(int $n) => $n % 3)->toArray();
        $growth = memory_get_peak_usage() - $before;

        $this->assertSame([0 => 66667, 1 => 66667, 2 => 66666], $counts);
        $this->assertLessThan($ceiling, $growth, "peak growth of countBy() over 200 000 items: $growth bytes");

        // the control in the same process: building the groups crosses the ceiling
        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_usage();
        $groups = $source->groupBy(fn(int $n) => $n % 3)->toArray();
        $groupGrowth = memory_get_peak_usage() - $before;
        $this->assertCount(3, $groups);
        $this->assertGreaterThan($ceiling, $groupGrowth, "groupBy() keeps the items: $groupGrowth bytes");
    }

    #[RequiresPhpExtension('pdo_sqlite')]
    public function testCountByOverACursor(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE t (dept TEXT)');
        $pdo->exec("INSERT INTO t VALUES ('a'), ('b'), ('a'), ('c'), ('a')");

        $counts = ALinqLazyCollection::fromCursor($pdo->query('SELECT dept FROM t'))->countBy(fn($r) => $r['dept']);
        $this->assertSame(['a' => 3, 'b' => 1, 'c' => 1], $counts->toArray());

        $lookup = ALinqLazyCollection::fromCursor($pdo->query('SELECT dept FROM t'))->toLookup(fn($r) => $r['dept']);
        $this->assertCount(3, $lookup->get('a'));
        $this->assertCount(0, $lookup->get('z'));
    }

    public function testEagerCountByAndAggregateByAnswerAsBefore(): void
    {
        $sales = ALinqCollection::from([
            ['dept' => 'Sales', 'amount' => 100],
            ['dept' => 'IT', 'amount' => 150],
            ['dept' => 'Sales', 'amount' => 200],
            ['dept' => 'IT', 'amount' => 250],
        ]);
        $this->assertSame(['Sales' => 300, 'IT' => 400], $sales->aggregateBy(fn($s) => $s['dept'], 0, fn($carry, $s) => $carry + $s['amount'])->toArray());
        $this->assertSame(['Sales' => 2, 'IT' => 2], $sales->countBy(fn($s) => $s['dept'])->toArray());
        $this->assertSame([], ALinqCollection::from([])->countBy(fn($s) => $s)->toArray());
        $this->assertSame([], ALinqLazyCollection::empty()->aggregateBy(fn($s) => $s, 0, fn($c) => $c)->toArray());
    }
}
