<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Comparison\StringComparer;
use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnderflowException;

/**
 * The comparer parameter of 2.2.0 (forward 047, D5): orderBy()/orderByDescending(),
 * distinct()/distinctBy() with an IALinqEqualityComparer, min()/max()/minBy()/maxBy() and their
 * *OrDefault() variants, on both collections.
 */
final class ComparerOperationsTest extends TestCase
{
    /**
     * @return array<string, array{\Closure(array): object}>
     */
    public static function sides(): array
    {
        return [
            'eager' => [fn(array $items) => ALinqCollection::from($items)],
            'lazy' => [fn(array $items) => ALinqLazyCollection::from($items)],
        ];
    }

    #[DataProvider('sides')]
    public function testOrderByTakesAComparerOverTheKeys(\Closure $from): void
    {
        $byLength = fn(string $a, string $b): int => strlen($a) <=> strlen($b);
        $words = ['ccc', 'a', 'bb', 'dd'];

        $this->assertSame(['a', 'bb', 'dd', 'ccc'], $from($words)->orderBy(fn($w) => $w, $byLength)->toArray());
        $this->assertSame(['ccc', 'bb', 'dd', 'a'], $from($words)->orderByDescending(fn($w) => $w, $byLength)->toArray());
        // thenBy() refines it as before; a dictionary keeps its keys
        $this->assertSame(['a', 'dd', 'bb', 'ccc'], $from($words)->orderBy(fn($w) => $w, $byLength)->thenByDescending(fn($w) => $w)->toArray());
        $this->assertSame(['y' => 'a', 'x' => 'ccc'], $from(['x' => 'ccc', 'y' => 'a'])->orderBy(fn($w) => $w, $byLength)->toArray());
        // without a comparer: `<=>`, unchanged
        $this->assertSame(['a', 'bb', 'ccc', 'dd'], $from($words)->orderBy(fn($w) => $w)->toArray());
    }

    #[DataProvider('sides')]
    public function testDistinctTakesAnEqualityComparer(\Closure $from): void
    {
        $names = ['Ana', 'ANA', 'bruno', 'Bruno', 'ana', null, null];

        $this->assertSame(['Ana', 'bruno', null], $from($names)->distinct(null, StringComparer::ordinalIgnoreCase())->toArray());
        $this->assertSame(['Ana', 'ANA', 'bruno', 'Bruno', 'ana', null], $from($names)->distinct()->toArray());
        $this->assertSame(
            ['a' => ['n' => 'Ana'], 'c' => ['n' => 'bruno']],
            $from(['a' => ['n' => 'Ana'], 'b' => ['n' => 'aNa'], 'c' => ['n' => 'bruno']])->distinctBy(fn($r) => $r['n'], StringComparer::ordinalIgnoreCase())->toArray()
        );
        $this->assertSame(['Ana', 'bruno'], $from(['Ana', 'bruno', 'ANA'])->distinct(fn($s) => $s, StringComparer::ordinalIgnoreCase())->toArray());
    }

    #[DataProvider('sides')]
    public function testDistinctAsksEqualsInsideAHashBucket(\Closure $from): void
    {
        // every value shares one bucket: the hash only narrows, equals() decides
        $sameBucket = new class implements IALinqEqualityComparer {
            public int $equalsCalls = 0;

            public function equals(mixed $a, mixed $b): bool
            {
                $this->equalsCalls++;
                return $a % 10 === $b % 10;
            }

            public function hash(mixed $value): string
            {
                return 'all';
            }
        };

        $this->assertSame([1, 2, 3], $from([1, 2, 11, 3, 21, 12])->distinct(null, $sameBucket)->toArray());
        $this->assertGreaterThan(0, $sameBucket->equalsCalls);
    }

    #[DataProvider('sides')]
    public function testMinAndMaxTakeAComparer(\Closure $from): void
    {
        $ordinal = StringComparer::ordinal();
        $values = ['9', '10', null, '1e1'];

        // `<=>` reads numeric strings as numbers; the ordinal comparer reads bytes
        $this->assertSame('9', $from($values)->min());
        $this->assertSame('10', $from($values)->min(null, $ordinal));
        $this->assertSame('9', $from($values)->max(null, $ordinal));
        $this->assertSame('10', $from($values)->max());
        $this->assertSame('10', $from($values)->minOrDefault('d', null, $ordinal));
        $this->assertSame('d', $from([null, null])->minOrDefault('d', null, $ordinal), 'null is still skipped');
        $this->assertSame('9', $from($values)->maxOrDefault('d', null, $ordinal));
        $this->assertSame('x9', $from($values)->max(fn($v) => $v === null ? null : "x$v", $ordinal));

        $this->expectException(UnderflowException::class);
        $this->expectExceptionMessage('Cannot compute min() of an empty collection (null values are skipped).');
        $from([null])->min(null, $ordinal);
    }

    #[DataProvider('sides')]
    public function testWithAComparerTheComparerDecidesWhatIsComparable(\Closure $from): void
    {
        $byCount = fn(array $a, array $b): int => count($a) <=> count($b);
        // arrays are refused by `<=>` (comparableValue()), accepted when a comparer decides
        $this->assertSame([1, 2, 3], $from([[1], [1, 2, 3], [1, 2]])->max(null, $byCount));
        $this->assertSame([1], $from([[1], [1, 2, 3], [1, 2]])->min(null, $byCount));

        try {
            $from([[1], [2]])->max();
            $this->fail('max() of arrays without a comparer must throw');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('max() expects comparable values (scalars or DateTimeInterface), array found at key 0', $e->getMessage());
        }

        // and the comparer itself may refuse
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('StringComparer expects strings or null, int given');
        $from(['a', 1])->max(null, StringComparer::ordinal());
    }

    #[DataProvider('sides')]
    public function testMinByAndMaxByTakeAComparer(\Closure $from): void
    {
        $people = [['n' => 'bruno'], ['n' => 'Ana'], ['n' => 'ana'], ['n' => 'Carla']];
        $ignoreCase = StringComparer::ordinalIgnoreCase();

        $this->assertSame(['n' => 'Ana'], $from($people)->minBy(fn($p) => $p['n'], $ignoreCase));       // first on a tie
        $this->assertSame(['n' => 'Ana'], $from($people)->minBy(fn($p) => $p['n']));                    // `<=>`: 'A' < 'C' < 'a'
        $this->assertSame(['n' => 'Carla'], $from($people)->maxBy(fn($p) => $p['n'], $ignoreCase));
        $this->assertSame(['n' => 'bruno'], $from($people)->maxBy(fn($p) => $p['n']));
        $this->assertSame(['n' => 'Ana'], $from($people)->minByOrDefault('d', fn($p) => $p['n'], $ignoreCase));
        $this->assertSame(['n' => 'Carla'], $from($people)->maxByOrDefault('d', fn($p) => $p['n'], $ignoreCase));
        $this->assertSame('d', $from([])->maxByOrDefault('d', fn($p) => $p['n'], $ignoreCase));
        // a null key is compared, not skipped; the StringComparer puts it first
        $this->assertSame(['n' => null], $from([['n' => 'a'], ['n' => null]])->minBy(fn($p) => $p['n'], $ignoreCase));
    }

    /**
     * D5: one comparison for both sides. Before 2.2.0 the lazy side compared with `<`/`>` and
     * the eager side with `<=>`; they disagreed where a value does not compare both ways.
     */
    public function testBothSidesShareOneExtremeComparison(): void
    {
        $sources = [
            'NAN first' => [NAN, 1, 2],
            'arrays with different keys' => [['a' => 1], ['b' => 1], ['a' => 0]],
        ];
        foreach (['NAN first' => 'max', 'arrays with different keys' => 'maxBy'] as $case => $operation) {
            $eager = ALinqCollection::from($sources[$case]);
            $lazy = ALinqLazyCollection::from($sources[$case]);
            $answer = fn($c) => $operation === 'max' ? $c->max() : $c->maxBy(fn($v) => $v);
            $this->assertSame(json_encode($answer($eager)), json_encode($answer($lazy)), $case);
        }
        $this->assertSame(2, ALinqLazyCollection::from([NAN, 1, 2])->max(), 'the lazy side answers as the eager side (2.1.0 lazy: NAN)');
        $this->assertNan(ALinqLazyCollection::from([NAN, 1, 2])->min());
    }
}
