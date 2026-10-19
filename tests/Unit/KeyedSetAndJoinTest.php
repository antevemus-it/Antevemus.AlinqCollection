<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqJoinable;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * exceptByKeys()/intersectByKeys() (the ExceptBy/IntersectBy of .NET 6) and join()/groupJoin()
 * with any iterable as the inner side (2.2.0, forward 047, D15-D18).
 */
final class KeyedSetAndJoinTest extends TestCase
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
     * The keys as an array, a generator, an eager and a lazy collection.
     *
     * @return array<string, \Closure(): iterable>
     */
    private static function keySources(array $keys): array
    {
        return [
            'array' => fn() => $keys,
            'generator' => fn() => (function () use ($keys): Generator { yield from $keys; })(),
            'ALinqCollection' => fn() => ALinqCollection::from($keys),
            'ALinqLazyCollection' => fn() => ALinqLazyCollection::from($keys),
        ];
    }

    #[DataProvider('sides')]
    public function testExceptByKeysTakesASequenceOfKeys(\Closure $from): void
    {
        $documents = [['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => 'b'], ['id' => 3, 'name' => 'c'], ['id' => 1, 'name' => 'a again']];
        foreach (self::keySources([2]) as $kind => $keys) {
            $this->assertSame(
                [['id' => 1, 'name' => 'a'], ['id' => 3, 'name' => 'c']],
                $from($documents)->exceptByKeys($keys(), fn($d) => $d['id'])->toArray(),
                "$kind: a set operation, each key once (the first occurrence)"
            );
            $this->assertSame(
                [['id' => 2, 'name' => 'b']],
                $from($documents)->intersectByKeys($keys(), fn($d) => $d['id'])->toArray(),
                $kind
            );
        }
    }

    #[DataProvider('sides')]
    public function testTheSelectorRunsOnTheItemsOnly(\Closure $from): void
    {
        $seen = [];
        $selector = function ($d) use (&$seen) {
            $seen[] = $d;
            return $d['id'];
        };
        $from([['id' => 1], ['id' => 2]])->exceptByKeys([2, 9], $selector)->toArray();
        $this->assertSame([['id' => 1], ['id' => 2]], $seen);
    }

    #[DataProvider('sides')]
    public function testIdentityIsStrictAndNullMatchesNull(\Closure $from): void
    {
        $rows = [['k' => 1], ['k' => '1'], ['k' => null], ['k' => 1.0]];

        $this->assertSame([['k' => '1'], ['k' => null], ['k' => 1.0]], $from($rows)->exceptByKeys([1], fn($r) => $r['k'])->toArray());
        $this->assertSame([['k' => 1], ['k' => '1'], ['k' => 1.0]], $from($rows)->exceptByKeys([null], fn($r) => $r['k'])->toArray());
        $this->assertSame([['k' => null]], $from($rows)->intersectByKeys([null, 2], fn($r) => $r['k'])->toArray());
    }

    #[DataProvider('sides')]
    public function testADictionaryKeepsItsKeys(\Closure $from): void
    {
        $this->assertSame(['b' => 2], $from(['a' => 1, 'b' => 2, 'c' => 3])->exceptByKeys([1, 3], fn($v) => $v)->toArray());
        $this->assertSame(['a' => 1, 'c' => 3], $from(['a' => 1, 'b' => 2, 'c' => 3])->intersectByKeys([3, 1], fn($v) => $v)->toArray());
        $this->assertSame([], $from([])->exceptByKeys([1], fn($v) => $v)->toArray());
        $this->assertSame([1, 2], $from([1, 2, 2])->exceptByKeys([], fn($v) => $v)->toArray());
    }

    /**
     * The .NET-style call on exceptBy() gives a wrong answer with a warning (forward 047 §1.5);
     * exceptByKeys() is the right call. exceptBy() itself does not change (D17).
     */
    public function testExceptByKeepsItsAnswerAndExceptByKeysIsTheNetForm(): void
    {
        $rows = ALinqCollection::from([['id' => 1], ['id' => 2]]);

        $this->assertSame([['id' => 1]], $rows->exceptBy([['id' => 2]], fn($r) => $r['id'])->toArray());
        $this->assertSame([['id' => 1]], $rows->exceptByKeys([2], fn($r) => $r['id'])->toArray());

        set_error_handler(static fn(): bool => true);
        try {
            $this->assertSame([['id' => 1], ['id' => 2]], $rows->exceptBy([1], fn($r) => $r['id'])->toArray());
        } finally {
            restore_error_handler();
        }
    }

    public function testTheLazyStageReadsTheKeysOnTraversal(): void
    {
        $read = 0;
        $keys = ALinqLazyCollection::from(function () use (&$read): Generator {
            $read++;
            yield 2;
        });
        $stage = ALinqLazyCollection::range(1, 4)->exceptByKeys($keys, fn($n) => $n);
        $this->assertSame(0, $read, 'nothing runs before the traversal');
        $this->assertSame([1, 3, 4], $stage->toArray());
        $this->assertSame(1, $read);
        $this->assertSame([1, 3], $stage->take(2)->toArray());
        $this->assertSame(2, $read, 'every traversal reads the keys again');
    }

    #[DataProvider('sides')]
    public function testJoinAndGroupJoinAcceptAnyIterableReadOnce(\Closure $from): void
    {
        $customers = [['id' => 10, 'name' => 'Acme'], ['id' => 20, 'name' => 'Globex'], ['id' => null, 'name' => 'none']];
        $orders = [['id' => 1, 'customer' => 10], ['id' => 2, 'customer' => 99], ['id' => 3, 'customer' => 10], ['id' => 4, 'customer' => null]];

        $join = fn($inner) => ALinqCollection::from($orders)->join($inner, fn($o) => $o['customer'], fn($c) => $c['id'], fn($o, $c) => $o['id'] . ':' . $c['name'])->toArray();
        $groupJoin = fn($inner) => ALinqCollection::from($orders)->groupJoin($inner, fn($o) => $o['customer'], fn($c) => $c['id'], fn($o, $cs) => $o['id'] . ':' . $cs->count())->toArray();

        $expectedJoin = ['1:Acme', '3:Acme'];
        $expectedGroupJoin = ['1:1', '2:0', '3:1', '4:0'];
        $this->assertSame($expectedJoin, $join($customers));
        $this->assertSame($expectedGroupJoin, $groupJoin($customers));

        $opened = 0;
        $inners = [
            'generator' => fn() => (function () use ($customers, &$opened): Generator { $opened++; yield from $customers; })(),
            'ALinqCollection' => fn() => ALinqCollection::from($customers),
            'ALinqLazyCollection' => fn() => ALinqLazyCollection::from(function () use ($customers, &$opened): Generator { $opened++; yield from $customers; }),
            'side under test' => fn() => $from($customers),
        ];
        foreach ($inners as $kind => $inner) {
            $opened = 0;
            $this->assertSame($expectedJoin, $join($inner()), "join over $kind");
            $this->assertLessThanOrEqual(1, $opened, "join over $kind reads the inner side once");
            $opened = 0;
            $this->assertSame($expectedGroupJoin, $groupJoin($inner()), "groupJoin over $kind");
            $this->assertLessThanOrEqual(1, $opened, "groupJoin over $kind reads the inner side once");
        }
    }

    public function testTheInterfacesDeclareTheWiderAndTheNewSignatures(): void
    {
        foreach (['join', 'groupJoin'] as $method) {
            $this->assertSame('iterable', (string) (new ReflectionMethod(IALinqJoinable::class, $method))->getParameters()[0]->getType());
        }
        foreach ([IALinqJoinable::class, IALinqLazyCollection::class] as $interface) {
            foreach (['exceptByKeys', 'intersectByKeys'] as $method) {
                $parameters = (new ReflectionMethod($interface, $method))->getParameters();
                $this->assertSame(['keys', 'keySelector'], array_map(fn($p) => $p->getName(), $parameters), "$interface::$method");
                $this->assertSame('iterable', (string) $parameters[0]->getType());
            }
        }
    }
}
