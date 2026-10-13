<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqAggregatable;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

/**
 * The aggregates with a default (2.1.0, forward 036): minOrDefault(), maxOrDefault(),
 * averageOrDefault(), minByOrDefault() and maxByOrDefault(), eager and lazy.
 *
 * D3: none of them traverses the source more than once, so they are the correct fallback on
 * a single-pass source (fromCursor(), a generator), where the guard `any() ? min() : null`
 * reads the source twice. The eager × lazy matrix of the five methods is
 * tests/Contract/AggregateOrDefaultContractTest.php.
 */
final class AggregationOrDefaultTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function sides(): array
    {
        return [
            'eager' => [ALinqCollection::class],
            'lazy' => [ALinqLazyCollection::class],
        ];
    }

    // ===== D2: the sibling's answer, the default instead of "nothing to aggregate" =====

    #[DataProvider('sides')]
    public function testAnEmptyCollectionAnswersTheDefault(string $class): void
    {
        $empty = $class::from([]);
        $default = new stdClass();

        $this->assertSame($default, $empty->minOrDefault($default));
        $this->assertSame($default, $empty->maxOrDefault($default));
        $this->assertSame($default, $empty->averageOrDefault($default));
        $this->assertSame($default, $empty->minByOrDefault($default, fn($v) => $v));
        $this->assertSame($default, $empty->maxByOrDefault($default, fn($v) => $v));
    }

    #[DataProvider('sides')]
    public function testTheDefaultIsNullWhenOmitted(string $class): void
    {
        $empty = $class::from([]);

        $this->assertNull($empty->minOrDefault());
        $this->assertNull($empty->maxOrDefault());
        $this->assertNull($empty->averageOrDefault());
        $this->assertSame(0, $empty->minOrDefault(0, fn($v) => $v));
    }

    #[DataProvider('sides')]
    public function testOnlyNullsAnswerTheDefaultWhereTheSiblingSkipsNulls(string $class): void
    {
        $nulls = $class::from([null, null]);

        $this->assertSame('none', $nulls->minOrDefault('none'));
        $this->assertSame('none', $nulls->maxOrDefault('none'));
        $this->assertSame('none', $nulls->averageOrDefault('none'));
        // minBy()/maxBy() compare a null key instead of skipping it, so a collection of
        // nulls is not empty for them: the answer is the first item, as minBy() answers.
        $this->assertNull($nulls->minByOrDefault('none', fn($v) => $v));
        $this->assertSame($nulls->maxBy(fn($v) => $v), $nulls->maxByOrDefault('none', fn($v) => $v));
    }

    #[DataProvider('sides')]
    public function testWithValuesTheAnswerIsTheSiblings(string $class): void
    {
        $collection = $class::from(['a' => 3, 'b' => null, 'c' => 1, 'd' => 8]);

        $this->assertSame(1, $collection->minOrDefault('none'));
        $this->assertSame(8, $collection->maxOrDefault('none'));
        $this->assertSame(4, $collection->averageOrDefault('none'));
        $this->assertSame(30, $collection->minOrDefault('none', fn($v, $k) => $v === null ? null : $v * 10 + ($k === 'a' ? 0 : 100)));
        $this->assertSame(1, $collection->minByOrDefault('none', fn($v) => $v ?? PHP_INT_MAX));
        $this->assertSame(8, $collection->maxByOrDefault('none', fn($v, $k) => $k === 'd' ? 1 : 0));
    }

    #[DataProvider('sides')]
    public function testANullDefaultIsIndistinguishableFromNothingByDesign(string $class): void
    {
        // As firstOrDefault(): pick a default that cannot be an answer when the difference matters.
        $this->assertNull($class::from([])->minByOrDefault(null, fn($v) => $v));
        $this->assertNull($class::from([null])->minByOrDefault(null, fn($v) => 0));
    }

    #[DataProvider('sides')]
    public function testEveryOtherErrorThrowsAsTheSibling(string $class): void
    {
        $notComparable = $class::from([1, [2], 3]);
        $notNumeric = $class::from([1, 'x']);

        foreach ([
            [fn() => $notComparable->min(), fn() => $notComparable->minOrDefault('none')],
            [fn() => $notComparable->max(), fn() => $notComparable->maxOrDefault('none')],
            [fn() => $notNumeric->average(), fn() => $notNumeric->averageOrDefault('none')],
            [fn() => $notNumeric->minBy(fn() => throw new LogicException('key')), fn() => $notNumeric->minByOrDefault('none', fn() => throw new LogicException('key'))],
        ] as [$sibling, $variant]) {
            $expected = self::thrown($sibling);
            $this->assertNotNull($expected);
            $this->assertSame($expected, self::thrown($variant));
        }

        $this->assertSame([InvalidArgumentException::class, 'min() expects comparable values (scalars or DateTimeInterface), array found at key 1'], self::thrown(fn() => $notComparable->minOrDefault()));
    }

    #[DataProvider('sides')]
    public function testDatesCompareAsInMinAndMax(string $class): void
    {
        $older = new DateTimeImmutable('2025-12-31');
        $newer = new DateTimeImmutable('2026-10-12');
        $collection = $class::from([$newer, null, $older]);

        $this->assertSame($older, $collection->minOrDefault());
        $this->assertSame($newer, $collection->maxOrDefault());
    }

    public function testTheInterfacesDeclareTheFiveMethods(): void
    {
        $methods = ['minOrDefault', 'maxOrDefault', 'averageOrDefault', 'minByOrDefault', 'maxByOrDefault'];
        foreach ([IALinqAggregatable::class, IALinqLazyCollection::class] as $interface) {
            foreach ($methods as $method) {
                $this->assertTrue(method_exists($interface, $method), "$interface::$method");
                $parameter = (new \ReflectionMethod($interface, $method))->getParameters()[0];
                $this->assertSame('default', $parameter->getName(), "$interface::$method: \$default comes first");
            }
        }
    }

    // ===== D3: one traversal =====

    /**
     * A lazy source that can be opened once: the second opening throws, like a PDO cursor
     * (1.3.2) or a raw generator that has run.
     *
     * @param array<mixed> $items
     */
    private static function singlePass(array $items, int &$openings): ALinqLazyCollection
    {
        $openings = 0;
        return ALinqLazyCollection::from(function () use ($items, &$openings) {
            if (++$openings > 1) {
                throw new LogicException('single-pass source opened twice');
            }
            yield from $items;
        });
    }

    /**
     * @return array<string, array{array<mixed>, string, mixed}> [items, method, expected]
     */
    public static function singlePassCases(): array
    {
        $cases = [];
        foreach ([
            'values' => [[4, null, 2, 9], ['minOrDefault' => 2, 'maxOrDefault' => 9, 'averageOrDefault' => 5, 'minByOrDefault' => null, 'maxByOrDefault' => 9]],
            'empty' => [[], ['minOrDefault' => 'D', 'maxOrDefault' => 'D', 'averageOrDefault' => 'D', 'minByOrDefault' => 'D', 'maxByOrDefault' => 'D']],
            'only nulls' => [[null, null], ['minOrDefault' => 'D', 'maxOrDefault' => 'D', 'averageOrDefault' => 'D', 'minByOrDefault' => null, 'maxByOrDefault' => null]],
        ] as $label => [$items, $expectations]) {
            foreach ($expectations as $method => $expected) {
                $cases["$method, $label"] = [$items, $method, $expected];
            }
        }
        return $cases;
    }

    #[DataProvider('singlePassCases')]
    public function testEachMethodTraversesASinglePassSourceOnce(array $items, string $method, mixed $expected): void
    {
        $openings = 0;
        $source = self::singlePass($items, $openings);
        $call = static fn(ALinqLazyCollection $c) => str_contains($method, 'By')
            ? $c->$method('D', fn($v) => $v)
            : $c->$method('D');

        $this->assertSame($expected, $call($source));
        $this->assertSame(1, $openings);

        // through a stage, as in the consumer (where() then the aggregate)
        $source = self::singlePass($items, $openings);
        $this->assertSame($expected, $call($source->where(fn($v) => true)));
        $this->assertSame(1, $openings);
    }

    public function testTheGuardWithAnyFailsOnTheSameSource(): void
    {
        // RN-03, the reason for the methods: the two-pass guard cannot run on this source,
        // whether the source is empty or not.
        foreach ([[3, 1, 2], []] as $items) {
            $openings = 0;
            $dated = self::singlePass($items, $openings)->where(fn($v) => $v !== null);
            try {
                $result = $dated->any() ? $dated->min() : null;
                $this->assertSame([], $items, 'an empty source is opened once by any() alone');
                $this->assertNull($result);
                $this->assertSame(1, $openings);
            } catch (LogicException $e) {
                $this->assertSame('single-pass source opened twice', $e->getMessage());
                $this->assertSame(2, $openings);
            }
        }
    }

    // ===== RN-04: the consumer's code, rewritten =====

    /**
     * @return list<stdClass>
     */
    private static function quotes(): array
    {
        $quote = static function (?string $createdAt): stdClass {
            $q = new stdClass();
            $q->created_at = $createdAt;
            return $q;
        };

        return [
            $quote('2026-03-10 09:00:00'),
            $quote(null),
            $quote(''),
            $quote('not a date'),
            $quote('2026-01-15 14:30:00'),
            $quote('2026-02-01 00:00:00'),
        ];
    }

    public function testTheConsumerOldestQuoteIsOneCallWithADefault(): void
    {
        // Before (Virttus.RentalGuaranteeManager, RentalGuaranteeQuoteList):
        //   $dated_quotes = $quotesCollection->where(fn($q) => !empty($q->created_at));
        //   $oldest_created_at = $dated_quotes->any() ? $dated_quotes->min(fn($q) => strtotime($q->created_at)) : null;
        // After: one call, one pass. strtotime() answers false (not null) for a malformed
        // date, and false is not skipped as a null, hence `?: null`.
        $oldest = static fn($quotes) => $quotes
            ->where(fn($q) => !empty($q->created_at))
            ->minOrDefault(null, fn($q) => strtotime($q->created_at) ?: null);

        $expected = strtotime('2026-01-15 14:30:00');
        $this->assertSame($expected, $oldest(ALinqCollection::from(self::quotes())));
        $this->assertSame($expected, $oldest(ALinqLazyCollection::from(self::quotes())));

        $undated = [self::quotes()[1], self::quotes()[2]];
        $this->assertNull($oldest(ALinqCollection::from($undated)));
        $this->assertNull($oldest(ALinqLazyCollection::from($undated)));
        $this->assertNull($oldest(ALinqCollection::from([])));

        // without `?: null` the malformed date is a false, and false is the smallest answer
        $this->assertFalse(ALinqCollection::from(self::quotes())
            ->where(fn($q) => !empty($q->created_at))
            ->minOrDefault(null, fn($q) => strtotime($q->created_at)));
    }

    public function testTheConsumerCodeRunsOnACursor(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }

        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE quote (id INTEGER PRIMARY KEY, created_at TEXT)');
        foreach (self::quotes() as $i => $q) {
            $stmt = $pdo->prepare('INSERT INTO quote (id, created_at) VALUES (?, ?)');
            $stmt->execute([$i + 1, $q->created_at]);
        }
        $cursor = static fn() => ALinqLazyCollection::fromCursor(
            $pdo->query('SELECT created_at FROM quote ORDER BY id'),
            fn(array $row) => (object) $row
        )->where(fn($q) => !empty($q->created_at));

        $this->assertSame(
            strtotime('2026-01-15 14:30:00'),
            $cursor()->minOrDefault(null, fn($q) => strtotime($q->created_at) ?: null)
        );

        // the guard of the old code reads the cursor twice
        $dated = $cursor();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('A PDO cursor is single-pass and has already been traversed.');
        $dated->any() ? $dated->min(fn($q) => strtotime($q->created_at) ?: null) : null;
    }

    /**
     * @return array{0: class-string, 1: string}|null
     */
    private static function thrown(callable $call): ?array
    {
        try {
            $call();
        } catch (\Throwable $e) {
            return [$e::class, $e->getMessage()];
        }
        return null;
    }
}
