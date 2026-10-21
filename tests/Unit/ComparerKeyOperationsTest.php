<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\ALinqLookup;
use Antevemus\ALinq\Comparison\StringComparer;
use Antevemus\ALinq\DuplicateKeyPolicy;
use Antevemus\ALinq\Interfaces\IALinqAggregatable;
use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;
use Antevemus\ALinq\Interfaces\IALinqGroupable;
use Antevemus\ALinq\Interfaces\IALinqJoinable;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqSelectable;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * The comparer of 2.4.0 in grouping, set operations and joins, on both collections; join() and
 * groupJoin() on the lazy side; the seed selector of aggregateBy().
 *
 * The key of a group or of a set is the first one seen (the São Paulo rule); the identity is
 * hash-based, never a comparison of every pair (a join of 10 000 × 10 000 rows calls equals() once
 * per outer row); without a comparer every answer is the one of 2.3.1 (the rest of the suite and
 * ParityTest).
 */
final class ComparerKeyOperationsTest extends TestCase
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

    /**
     * Counts the calls of a comparer it wraps.
     */
    private static function counting(IALinqEqualityComparer $inner): IALinqEqualityComparer
    {
        return new class ($inner) implements IALinqEqualityComparer {
            public int $equalsCalls = 0;
            public int $hashCalls = 0;

            public function __construct(private readonly IALinqEqualityComparer $inner)
            {
            }

            public function equals(mixed $a, mixed $b): bool
            {
                $this->equalsCalls++;
                return $this->inner->equals($a, $b);
            }

            public function hash(mixed $value): string
            {
                $this->hashCalls++;
                return $this->inner->hash($value);
            }
        };
    }

    /**
     * A comparer whose hash puts every value in one bucket: equals() alone decides (case-insensitive).
     */
    private static function oneBucket(): IALinqEqualityComparer
    {
        return new class implements IALinqEqualityComparer {
            public function equals(mixed $a, mixed $b): bool
            {
                return strtolower((string) $a) === strtolower((string) $b);
            }

            public function hash(mixed $value): string
            {
                return 'all';
            }
        };
    }

    private const CITIES = [
        ['city' => 'Rio', 'n' => 1],
        ['city' => 'SÃO PAULO', 'n' => 2],
        ['city' => 'rio', 'n' => 3],
        ['city' => 'são paulo', 'n' => 4],
        ['city' => 'Recife', 'n' => 5],
    ];

    // ------------------------------------------------------------------ grouping (the first key seen)

    #[DataProvider('sides')]
    public function testTheFirstKeySeenNamesTheGroup(\Closure $from): void
    {
        $byCity = fn($r) => $r['city'];
        $ignoreCase = StringComparer::ordinalIgnoreCase();
        $numbers = fn($group) => array_column($group->toArray(), 'n');

        $groups = $from(self::CITIES)->groupBy($byCity, $ignoreCase);
        $this->assertSame(['Rio' => [1, 3], 'SÃO PAULO' => [2, 4], 'Recife' => [5]], $groups->select($numbers)->toArray());
        $this->assertSame(['Rio' => 2, 'SÃO PAULO' => 2, 'Recife' => 1], $from(self::CITIES)->countBy($byCity, $ignoreCase)->toArray());
        $this->assertSame(
            ['Rio' => '[0:1][1:3]', 'SÃO PAULO' => '[0:2][1:4]', 'Recife' => '[0:5]'],
            $from(self::CITIES)->aggregateBy($byCity, '', fn($trace, $r, $position) => $trace . "[$position:{$r['n']}]", $ignoreCase)->toArray()
        );
        // the order of the source decides: reversed, the lower-case keys name the groups
        $this->assertSame(['Recife', 'são paulo', 'rio'], array_keys($from(array_reverse(self::CITIES))->countBy($byCity, $ignoreCase)->toArray()));
        // without a comparer the keys are PHP array keys, as in 2.3.1
        $this->assertSame(['Rio' => 1, 'SÃO PAULO' => 1, 'rio' => 1, 'são paulo' => 1, 'Recife' => 1], $from(self::CITIES)->countBy($byCity)->toArray());
    }

    #[RequiresPhpExtension('intl')]
    public function testAnAccentInsensitiveComparerMakesSaoPauloOneGroup(): void
    {
        foreach (self::sides() as [$from]) {
            $places = ['São Paulo', 'sao paulo', 'Sao Paulo', 'Ética', 'etica', 'Rio'];
            $culture = StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true);

            $this->assertSame(['São Paulo' => 3, 'Ética' => 2, 'Rio' => 1], $from($places)->countBy(fn($p) => $p, $culture)->toArray());
            $this->assertSame(
                ['sao paulo' => ['sao paulo', 'Sao Paulo', 'São Paulo']],
                $from(['sao paulo', 'Sao Paulo', 'São Paulo'])->groupBy(fn($p) => $p, $culture)->select(fn($g) => $g->toArray())->toArray()
            );
            $lookup = $from($places)->toLookup(fn($p) => $p, null, $culture);
            $this->assertSame(['São Paulo', 'sao paulo', 'Sao Paulo'], $lookup->get('SAO PAULO')->toArray());
            $this->assertTrue($lookup->contains('ETICA'));
        }
    }

    #[DataProvider('sides')]
    public function testToLookupKeepsTheComparerForGetAndContains(\Closure $from): void
    {
        $lookup = $from(self::CITIES)->toLookup(fn($r) => $r['city'], fn($r) => $r['n'], StringComparer::ordinalIgnoreCase());

        $this->assertInstanceOf(ALinqLookup::class, $lookup);
        $this->assertSame(['Rio', 'SÃO PAULO', 'Recife'], $lookup->keys());
        $this->assertSame([1, 3], $lookup->get('RIO')->toArray());
        $this->assertSame([2, 4], $lookup['São Paulo']->toArray());
        $this->assertTrue($lookup->contains('recife'));
        $this->assertTrue(isset($lookup['rECIFE']));
        $this->assertFalse($lookup->contains('Natal'));
        $this->assertSame([], $lookup->get('Natal')->toArray());
        $this->assertSame(['Rio' => [1, 3], 'SÃO PAULO' => [2, 4], 'Recife' => [5]], $lookup->toArray());

        // A numeric text is an int array key: the comparer still receives the text, and the int
        // (what keys() and iteration answer) finds its group without asking the comparer.
        $codes = $from(['10', 'x', '10'])->toLookup(fn($c) => $c, null, StringComparer::ordinal());
        $this->assertSame([10, 'x'], $codes->keys());
        $this->assertSame(['10', '10'], $codes->get('10')->toArray());
        $this->assertSame(['10', '10'], $codes->get(10)->toArray());
        $this->assertSame(['x'], $codes->get('x')->toArray());

        // without a comparer, get() is by array key, as in 2.3.1
        $this->assertSame([], $from(self::CITIES)->toLookup(fn($r) => $r['city'])->get('RIO')->toArray());
    }

    public function testALookupBuiltWithAComparerAndNoKeysUsesItsArrayKeys(): void
    {
        $lookup = new ALinqLookup(['Rio' => [1], 'Recife' => [2]], StringComparer::ordinalIgnoreCase());

        $this->assertSame([1], $lookup->get('RIO')->toArray());
        $this->assertFalse($lookup->contains('Natal'));
        $this->assertSame([2], (new ALinqLookup(['Rio' => [1], 'Recife' => [2]]))->get('Recife')->toArray());
    }

    #[DataProvider('sides')]
    public function testToDictionaryKeepsTheFirstKeySeen(\Closure $from): void
    {
        $byCity = fn($r) => $r['city'];
        $n = fn($r) => $r['n'];
        $ignoreCase = StringComparer::ordinalIgnoreCase();

        $this->assertSame(['Rio' => 1, 'SÃO PAULO' => 2, 'Recife' => 5], $from(self::CITIES)->toDictionary($byCity, $n, DuplicateKeyPolicy::KeepFirst, $ignoreCase));
        $this->assertSame(['Rio' => 3, 'SÃO PAULO' => 4, 'Recife' => 5], $from(self::CITIES)->toDictionary($byCity, $n, DuplicateKeyPolicy::KeepLast, $ignoreCase));
        $this->assertSame(['Rio' => 1, 'Recife' => 5], $from([self::CITIES[0], self::CITIES[4]])->toDictionary($byCity, $n, DuplicateKeyPolicy::Throw, $ignoreCase));

        try {
            $from(self::CITIES)->toDictionary($byCity, $n, DuplicateKeyPolicy::Throw, $ignoreCase);
            $this->fail('rio repeats Rio under the comparer');
        } catch (InvalidArgumentException $e) {
            $this->assertSame("toDictionary() key 'Rio' produced twice (second time as 'rio', for item at key 2)", $e->getMessage());
        }
        try {
            $from([['city' => 'Rio'], ['city' => 'Rio']])->toDictionary($byCity, null, DuplicateKeyPolicy::Throw, $ignoreCase);
            $this->fail('Rio twice');
        } catch (InvalidArgumentException $e) {
            $this->assertSame("toDictionary() key 'Rio' produced twice (second time for item at key 1)", $e->getMessage());
        }
    }

    #[DataProvider('sides')]
    public function testTheComparerSeesTheKeyAfterTheKeyRule(\Closure $from): void
    {
        $seen = [];
        $recording = new class ($seen) implements IALinqEqualityComparer {
            public function __construct(private array &$seen)
            {
            }

            public function equals(mixed $a, mixed $b): bool
            {
                return $a === $b;
            }

            public function hash(mixed $value): string
            {
                $this->seen[] = $value;
                return get_debug_type($value) . ':' . $value;
            }
        };

        $this->assertSame(['on' => 2, 7 => 1], $from([KeyFlag::On, 7, KeyFlag::On])->countBy(fn($v) => $v, $recording)->toArray());
        $this->assertSame(['on', 7, 'on'], $seen, 'One hash() per key, a BackedEnum as its value; an invalid key never reaches the comparer.');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('groupBy() expects the key selector to return an int, a string or a BackedEnum, null returned for item at key 0');
        $from([null])->countBy(fn($v) => $v, $recording)->toArray();
    }

    /**
     * @return array<string, array{\Closure(object, IALinqEqualityComparer): mixed, string}>
     */
    public static function groupingOperators(): array
    {
        $key = fn($v) => $v;
        return [
            'groupBy' => [fn($c, $comparer) => $c->groupBy($key, $comparer)->toArray(), 'groupBy'],
            'countBy' => [fn($c, $comparer) => $c->countBy($key, $comparer)->toArray(), 'groupBy'],
            'aggregateBy' => [fn($c, $comparer) => $c->aggregateBy($key, 0, fn($n) => $n + 1, $comparer)->toArray(), 'groupBy'],
            'toLookup' => [fn($c, $comparer) => $c->toLookup($key, null, $comparer)->toArray(), 'toLookup'],
            'toDictionary' => [fn($c, $comparer) => $c->toDictionary($key, null, DuplicateKeyPolicy::KeepFirst, $comparer), 'toDictionary'],
        ];
    }

    /**
     * A comparer that tells `1` and `'1'` apart cannot have both as group keys: PHP makes them one
     * array key. The operator throws instead of merging two groups the comparer keeps apart.
     */
    #[DataProvider('groupingOperators')]
    public function testTwoKeysThatAreOneArrayKeyThrow(\Closure $operator, string $name): void
    {
        $strict = new class implements IALinqEqualityComparer {
            public function equals(mixed $a, mixed $b): bool
            {
                return $a === $b;
            }

            public function hash(mixed $value): string
            {
                return get_debug_type($value) . ':' . $value;
            }
        };

        foreach (self::sides() as [$from]) {
            $this->assertCount(2, $operator($from([1, 'a', 1]), $strict));
            try {
                $operator($from([1, 'a', '1']), $strict);
                $this->fail("$name: 1 and '1' are one array key");
            } catch (InvalidArgumentException $e) {
                $this->assertSame("$name() keys 1 and '1' are different under the comparer but are the same array key", $e->getMessage());
            }
        }
    }

    // ------------------------------------------------------------------ aggregateBy(): the seed selector

    #[DataProvider('sides')]
    public function testTheSeedSelectorOfAggregateByGivesEachGroupItsSeed(\Closure $from): void
    {
        $orders = [['c' => 'a', 'v' => 1], ['c' => 'b', 'v' => 2], ['c' => 'a', 'v' => 3]];
        $calls = [];
        $seedSelector = function (string $key) use (&$calls): string {
            $calls[] = $key;
            return "$key:";
        };

        $this->assertSame(['a' => 'a:13', 'b' => 'b:2'], $from($orders)->aggregateBy(fn($o) => $o['c'], null, fn($acc, $o) => $acc . $o['v'], null, $seedSelector)->toArray());
        $this->assertSame(['a', 'b'], $calls, 'Once per group, with its key, when the group starts.');
        // the seed is ignored when a seed selector is given
        $this->assertSame(['a' => 'a:13', 'b' => 'b:2'], $from($orders)->aggregateBy(fn($o) => $o['c'], 'ignored', fn($acc, $o) => $acc . $o['v'], seedSelector: fn($key) => "$key:")->toArray());

        // a fresh accumulator per group (an array seed is copied anyway; an object needs the selector)
        $bags = $from($orders)->aggregateBy(fn($o) => $o['c'], null, function (\ArrayObject $bag, $o) {
            $bag[] = $o['v'];
            return $bag;
        }, seedSelector: fn() => new \ArrayObject())->toArray();
        $this->assertSame([[1, 3], [2]], [$bags['a']->getArrayCopy(), $bags['b']->getArrayCopy()]);
        $this->assertNotSame($bags['a'], $bags['b']);

        // with a comparer the selector receives the key that names the group (the first seen)
        $keys = [];
        $this->assertSame(['A' => 4, 'b' => 2], $from([['c' => 'A', 'v' => 1], ['c' => 'b', 'v' => 2], ['c' => 'a', 'v' => 3]])->aggregateBy(
            fn($o) => $o['c'],
            null,
            fn($sum, $o) => $sum + $o['v'],
            StringComparer::ordinalIgnoreCase(),
            function (string $key) use (&$keys): int {
                $keys[] = $key;
                return 0;
            }
        )->toArray());
        $this->assertSame(['A', 'b'], $keys);

        // a callable that is not a Closure works as a selector too
        $this->assertSame(['a' => 'A13', 'b' => 'B2'], $from($orders)->aggregateBy(fn($o) => $o['c'], null, fn($acc, $o) => $acc . $o['v'], null, 'strtoupper')->toArray());
    }

    /**
     * Without a seed selector the seed is the seed of every group whatever its type, as in 2.3.1:
     * a Closure given as the seed is the accumulator itself, never called by aggregateBy().
     */
    #[DataProvider('sides')]
    public function testAClosureSeedIsTheAccumulatorAsIn231(\Closure $from): void
    {
        $calls = 0;
        $identity = function ($x) use (&$calls) {
            $calls++;
            return $x;
        };
        $composed = $from([1, 2, 3])->aggregateBy(fn() => 'all', $identity, fn(\Closure $f, $n) => fn($x) => $f($x) + $n)->toArray();

        $this->assertSame(0, $calls, 'aggregateBy() never calls the seed.');
        $this->assertSame(16, $composed['all'](10), 'The Closure seed was the first accumulator: ((10 + 1) + 2) + 3.');
        $this->assertSame(1, $calls);

        $first = $from([1, 2])->aggregateBy(fn($n) => $n % 2 ? 'odd' : 'even', $identity, fn($acc, $n, $position) => $acc)->toArray();
        $this->assertSame(['odd' => $identity, 'even' => $identity], $first, 'Every group starts from the same Closure.');
        $this->assertSame(['odd' => 4, 'even' => 2], $from([1, 2, 3])->aggregateBy(fn($n) => $n % 2 ? 'odd' : 'even', 0, fn($sum, $n) => $sum + $n)->toArray(), 'A value seed, as in 2.3.1.');
    }

    // ------------------------------------------------------------------ set operations

    public function testTheSetOperationsOfTheEagerSideTakeAComparer(): void
    {
        $ignoreCase = StringComparer::ordinalIgnoreCase();
        $names = ALinqCollection::from(['Ana', 'BRUNO', 'ana', 'Caio']);
        $dict = ALinqCollection::from(['x' => 'Ana', 'y' => 'ANA', 'z' => 'Caio']);

        $this->assertSame(['Ana', 'BRUNO'], $names->intersect(['bruno', 'ANA'], $ignoreCase)->toArray());
        $this->assertSame(['x' => 'Ana'], $dict->intersect(['ana'], $ignoreCase)->toArray());
        $this->assertSame(['Caio'], $names->except(['bruno', 'ANA'], $ignoreCase)->toArray());
        $this->assertSame(['z' => 'Caio'], $dict->except(['ana'], $ignoreCase)->toArray());
        $this->assertSame(['Ana', 'BRUNO', 'Caio'], $names->except([], $ignoreCase)->toArray(), 'except() deduplicates under the comparer too');

        $rows = ALinqCollection::from([['e' => 'A@x.io', 'n' => 1], ['e' => 'b@x.io', 'n' => 2], ['e' => 'a@X.IO', 'n' => 3]]);
        $email = fn($r) => $r['e'];
        $this->assertSame([1], array_column($rows->intersectBy([['e' => 'a@x.io']], $email, $ignoreCase)->toArray(), 'n'));
        $this->assertSame([2], array_column($rows->exceptBy([['e' => 'A@X.IO']], $email, $ignoreCase)->toArray(), 'n'));
        $this->assertSame([1, 2, 9], array_column($rows->unionBy([['e' => 'B@x.io', 'n' => 8], ['e' => 'c@x.io', 'n' => 9]], $email, $ignoreCase)->toArray(), 'n'));

        // without a comparer, strict identity as in 2.3.1
        $this->assertSame([], $names->intersect(['bruno'])->toArray());
        $this->assertSame([1, 2, 3, 8, 9], array_column($rows->unionBy([['e' => 'B@x.io', 'n' => 8], ['e' => 'c@x.io', 'n' => 9]], $email)->toArray(), 'n'));
    }

    #[DataProvider('sides')]
    public function testExceptByKeysAndIntersectByKeysTakeAComparer(\Closure $from): void
    {
        $ignoreCase = StringComparer::ordinalIgnoreCase();
        $rows = [['e' => 'A@x.io', 'n' => 1], ['e' => 'b@x.io', 'n' => 2], ['e' => 'a@X.IO', 'n' => 3], ['e' => null, 'n' => 4]];
        $email = fn($r) => $r['e'];

        $this->assertSame([2, 4], array_column($from($rows)->exceptByKeys(['a@x.io'], $email, $ignoreCase)->toArray(), 'n'));
        $this->assertSame([1, 4], array_column($from($rows)->intersectByKeys(['A@X.IO', null], $email, $ignoreCase)->toArray(), 'n'));
        $this->assertSame([1, 2, 4], array_column($from($rows)->exceptByKeys([], $email, $ignoreCase)->toArray(), 'n'), 'one item per key, the first');
        $this->assertSame(['k' => ['e' => 'b@x.io', 'n' => 2]], $from(['j' => $rows[0], 'k' => $rows[1]])->exceptByKeys(['A@X.IO'], $email, $ignoreCase)->toArray());
    }

    // ------------------------------------------------------------------ joins

    #[DataProvider('sides')]
    public function testEveryJoinTakesAComparer(\Closure $from): void
    {
        $ignoreCase = StringComparer::ordinalIgnoreCase();
        $users = [['e' => 'Ana@x.io', 'u' => 'ana'], ['e' => null, 'u' => 'nil'], ['e' => 'caio@x.io', 'u' => 'caio']];
        $orders = [['e' => 'ana@X.IO', 'o' => 1], ['e' => 'nobody@x.io', 'o' => 2], ['e' => 'ANA@x.io', 'o' => 3], ['e' => null, 'o' => 4]];
        $email = fn($r) => $r['e'];
        $label = fn(?array $u, ?array $o) => ($u['u'] ?? '-') . ':' . ($o['o'] ?? '-');

        $this->assertSame(['ana:1', 'ana:3'], $from($users)->join($orders, $email, $email, $label, $ignoreCase)->toArray());
        $this->assertSame(['ana:1,3', 'nil:', 'caio:'], $from($users)->groupJoin($orders, $email, $email, fn($u, $os) => $u['u'] . ':' . $os->implode(',', fn($o) => $o['o']), $ignoreCase)->toArray());
        $this->assertSame(['ana:1', 'ana:3', 'nil:-', 'caio:-'], $from($users)->leftJoin($orders, $email, $email, $label, $ignoreCase)->toArray());
        $this->assertSame(['ana:1', '-:2', 'ana:3', '-:4'], $from($users)->rightJoin($orders, $email, $email, $label, $ignoreCase)->toArray());
        $this->assertSame(['ana:1', 'ana:3', 'nil:-', 'caio:-', '-:2', '-:4'], $from($users)->fullJoin($orders, $email, $email, $label, $ignoreCase)->toArray());

        // a null key never matches, even under a comparer that finds null equal to null: it never
        // reaches the comparer
        $this->assertTrue($ignoreCase->equals(null, null));
        $counting = self::counting($ignoreCase);
        $this->assertSame([], $from([['e' => null]])->join([['e' => null]], $email, $email, $label, $counting)->toArray());
        $this->assertSame([0], $from([['e' => null]])->groupJoin([['e' => null]], $email, $email, fn($u, $os) => $os->count(), $counting)->toArray());
        $this->assertSame([0, 0], [$counting->hashCalls, $counting->equalsCalls]);

        // without a comparer, strict identity as in 2.3.1
        $this->assertSame([], $from($users)->join($orders, $email, $email, $label)->toArray());
    }

    #[DataProvider('sides')]
    public function testInsideABucketEqualsDecides(\Closure $from): void
    {
        $oneBucket = self::oneBucket();
        $left = ['a', 'B', 'c'];
        $right = ['b', 'A', 'x', 'a'];
        $pair = fn($l, $r) => "$l$r";

        $this->assertSame(['aA', 'aa', 'Bb'], $from($left)->join($right, fn($v) => $v, fn($v) => $v, $pair, $oneBucket)->toArray());
        $this->assertSame(['a' => 2, 'B' => 1], $from(['a', 'B', 'A'])->countBy(fn($v) => $v, $oneBucket)->toArray());
        $this->assertSame(['B'], $from($left)->intersectByKeys(['b'], fn($v) => $v, $oneBucket)->toArray());
    }

    /**
     * The linearity of 2.4.0: a join under a comparer stays linear. 10 000 × 10 000 rows, every outer
     * key matching one inner key that differs only in case: equals() runs once per outer row (to
     * confirm the match of its bucket), never once per pair (100 000 000).
     */
    #[DataProvider('sides')]
    public function testAJoinUnderAComparerRunsInLinearTime(\Closure $from): void
    {
        $count = 10_000;
        $outer = [];
        $inner = [];
        for ($i = 0; $i < $count; $i++) {
            $outer[] = ['key' => "Key-$i", 'n' => $i];
            $inner[] = ['key' => "KEY-$i", 'm' => $i];
        }
        $comparer = self::counting(StringComparer::ordinalIgnoreCase());

        $start = hrtime(true);
        $joined = $from($outer)->join($inner, fn($o) => $o['key'], fn($i) => $i['key'], fn($o, $i) => $o['n'] === $i['m'], $comparer)->toArray();
        $elapsed = (hrtime(true) - $start) / 1e9;

        $this->assertCount($count, $joined);
        $this->assertNotContains(false, $joined, 'Every outer row met its own inner row.');
        $this->assertSame($count, $comparer->equalsCalls, 'One equals() per outer row: the one confirming its bucket.');
        $this->assertSame(2 * $count, $comparer->hashCalls, 'One hash() per row of each side.');
        $this->assertLessThan(5.0, $elapsed, sprintf('Linear: measured %.3f s.', $elapsed));

        $groups = self::counting(StringComparer::ordinalIgnoreCase());
        $this->assertCount($count, $from($outer)->groupJoin($inner, fn($o) => $o['key'], fn($i) => $i['key'], fn($o, $is) => $is->count(), $groups)->toArray());
        $this->assertSame($count, $groups->equalsCalls);
    }

    // ------------------------------------------------------------------ lazy join() and groupJoin()

    public function testTheLazyJoinsAreDeferredStagesThatStreamTheOuterSide(): void
    {
        $outerReads = 0;
        $innerReads = 0;
        $outer = ALinqLazyCollection::from(function () use (&$outerReads): Generator {
            for ($i = 1; ; $i++) { // endless: only streaming can answer
                $outerReads++;
                yield $i;
            }
        });
        $inner = ALinqLazyCollection::from(function () use (&$innerReads): Generator {
            $innerReads++;
            yield from [['k' => 2, 'v' => 'two'], ['k' => 3, 'v' => 'three'], ['k' => 2, 'v' => 'deux']];
        });

        $join = $outer->join($inner, fn($n) => $n, fn($r) => $r['k'], fn($n, $r) => "$n:{$r['v']}");
        $groupJoin = $outer->groupJoin($inner, fn($n) => $n, fn($r) => $r['k'], fn($n, $rs) => "$n:" . $rs->count());
        $this->assertInstanceOf(IALinqLazyCollection::class, $join);
        $this->assertSame([0, 0], [$outerReads, $innerReads], 'Nothing runs before the first traversal.');

        $this->assertSame(['2:two', '2:deux', '3:three'], $join->take(3)->toArray());
        $this->assertSame(1, $innerReads, 'The inner side is read once per traversal.');
        $this->assertSame(3, $outerReads, 'The outer side is streamed: three rows read for three results.');
        $this->assertTrue($join->isList());

        $this->assertSame(['1:0', '2:2', '3:1', '4:0'], $groupJoin->take(4)->toArray());
        $this->assertSame(2, $innerReads);
        $this->assertTrue($groupJoin->isList());
        $this->assertSame(['2:two', '2:deux'], $join->take(2)->toArray(), 'Every traversal starts again.');
    }

    public function testTheLazyJoinsAnswerAsTheEagerOnes(): void
    {
        $departments = [['id' => 10, 'name' => 'Eng'], ['id' => 20, 'name' => 'Ops'], ['id' => 10, 'name' => 'Eng 2']];
        $people = ['a' => ['name' => 'Ana', 'dept' => 10], 'b' => ['name' => 'Bia', 'dept' => 30], 'c' => ['name' => 'Caio', 'dept' => 20], 'd' => ['name' => 'Davi', 'dept' => null]];
        $join = fn($c) => $c->join($departments, fn($p) => $p['dept'], fn($d) => $d['id'], fn($p, $d) => "{$p['name']}@{$d['name']}")->toArray();
        $groupJoin = fn($c) => $c->groupJoin(ALinqLazyCollection::from($departments), fn($p) => $p['dept'], fn($d) => $d['id'], fn($p, $ds) => [$p['name'], $ds->count()])->toArray();

        $this->assertSame(['Ana@Eng', 'Ana@Eng 2', 'Caio@Ops'], $join(ALinqLazyCollection::from($people)));
        $this->assertSame($join(ALinqCollection::from($people)), $join(ALinqLazyCollection::from($people)));
        $this->assertSame([['Ana', 2], ['Bia', 0], ['Caio', 1], ['Davi', 0]], $groupJoin(ALinqLazyCollection::from($people)));
        $this->assertSame($groupJoin(ALinqCollection::from($people)), $groupJoin(ALinqLazyCollection::from($people)));
        // the selectors receive the source key when they take two parameters
        $this->assertSame(['a:10', 'c:20'], ALinqLazyCollection::from($people)->join([10, 20], fn($p, $k) => $p['dept'], fn($d, $k) => $d, fn($p, $d) => strtolower($p['name'][0]) . ":$d")->toArray());
    }

    // ------------------------------------------------------------------ the signatures

    public function testTheComparerIsTheLastOptionalParameterEverywhereButBeforeTheSeedSelector(): void
    {
        $methods = [
            IALinqGroupable::class => ['groupBy'],
            IALinqSelectable::class => ['toDictionary', 'toLookup'],
            IALinqAggregatable::class => ['countBy', 'aggregateBy'],
            IALinqJoinable::class => ['join', 'groupJoin', 'leftJoin', 'rightJoin', 'fullJoin', 'intersect', 'except', 'intersectBy', 'exceptBy', 'unionBy', 'exceptByKeys', 'intersectByKeys'],
            IALinqLazyCollection::class => ['join', 'groupJoin', 'leftJoin', 'rightJoin', 'fullJoin', 'groupBy', 'countBy', 'aggregateBy', 'toDictionary', 'toLookup', 'exceptByKeys', 'intersectByKeys'],
            ALinqCollection::class => ['groupBy', 'toDictionary', 'toLookup', 'countBy', 'aggregateBy', 'join', 'groupJoin', 'leftJoin', 'rightJoin', 'fullJoin', 'intersect', 'except', 'intersectBy', 'exceptBy', 'unionBy', 'exceptByKeys', 'intersectByKeys'],
            ALinqLazyCollection::class => ['join', 'groupJoin', 'leftJoin', 'rightJoin', 'fullJoin', 'groupBy', 'countBy', 'aggregateBy', 'toDictionary', 'toLookup', 'exceptByKeys', 'intersectByKeys'],
        ];
        foreach ($methods as $class => $names) {
            foreach ($names as $name) {
                $parameters = (new ReflectionMethod($class, $name))->getParameters();
                if ($name === 'aggregateBy') {
                    // the seed selector comes after the comparer
                    $selector = array_pop($parameters);
                    $this->assertSame('seedSelector', $selector->getName(), "$class::$name");
                    $this->assertSame('?callable', (string) $selector->getType(), "$class::$name");
                    $this->assertNull($selector->getDefaultValue(), "$class::$name");
                }
                $last = end($parameters);
                $this->assertSame('comparer', $last->getName(), "$class::$name");
                $this->assertSame('?' . IALinqEqualityComparer::class, (string) $last->getType(), "$class::$name");
                $this->assertNull($last->getDefaultValue(), "$class::$name");
            }
        }

        $byIndex = (new ReflectionMethod(IALinqLazyCollection::class, 'fromHttpPagesByIndex'))->getParameters();
        $this->assertSame(['requestFor', 'parsePage', 'prefetch', 'until', 'options'], array_map(fn($p) => $p->getName(), $byIndex));
        $this->assertSame(2, $byIndex[2]->getDefaultValue());
    }
}

enum KeyFlag: string
{
    case On = 'on';
}
