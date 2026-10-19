<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\ALinqLookup;
use Antevemus\ALinq\DuplicateKeyPolicy;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqSelectable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use TypeError;

enum LookupStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';
}

/**
 * toLookup() + ALinqLookup and toDictionary(onDuplicate:) + DuplicateKeyPolicy (2.2.0,
 * forward 047, D7-D10), on both collections.
 */
final class LookupAndDictionaryTest extends TestCase
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

    private const ORDERS = [
        ['id' => 1, 'customer' => 'acme', 'total' => 10],
        ['id' => 2, 'customer' => 'globex', 'total' => 20],
        ['id' => 3, 'customer' => 'acme', 'total' => 30],
    ];

    #[DataProvider('sides')]
    public function testToLookupGroupsAndAnswersEmptyForAMissingKey(\Closure $from): void
    {
        $lookup = $from(self::ORDERS)->toLookup(fn($o) => $o['customer']);

        $this->assertInstanceOf(ALinqLookup::class, $lookup);
        $this->assertSame(['acme', 'globex'], $lookup->keys());
        $this->assertCount(2, $lookup);
        $this->assertSame([1, 3], $lookup->get('acme')->column('id')->toArray());
        $this->assertSame([1, 3], $lookup['acme']->column('id')->toArray());
        $this->assertInstanceOf(ALinqCollection::class, $lookup->get('initech'));
        $this->assertSame([], $lookup->get('initech')->toArray());   // never throws
        $this->assertSame(0, $lookup['initech']->sum(fn($o) => $o['total']));
        $this->assertTrue($lookup->contains('acme'));
        $this->assertFalse($lookup->contains('initech'));
        $this->assertTrue(isset($lookup['globex']));
        $this->assertFalse(isset($lookup['initech']));
        $this->assertFalse(isset($lookup[null]));
        $this->assertFalse(isset($lookup[1.5]));
    }

    #[DataProvider('sides')]
    public function testToLookupWithAnElementSelectorAndTheSourceKey(\Closure $from): void
    {
        $lookup = $from(['x' => 'a1', 'y' => 'b1', 'z' => 'a2'])->toLookup(fn($v) => $v[0], fn($v, $k) => "$k:$v");

        $this->assertSame(['a' => ['x:a1', 'z:a2'], 'b' => ['y:b1']], $lookup->toArray());
        $this->assertSame('{"a":["x:a1","z:a2"],"b":["y:b1"]}', json_encode($lookup));
        $iterated = [];
        foreach ($lookup as $key => $group) {
            $this->assertInstanceOf(ALinqCollection::class, $group);
            $iterated[$key] = $group->toArray();
        }
        $this->assertSame($lookup->toArray(), $iterated);
    }

    #[DataProvider('sides')]
    public function testToLookupAnswersAsGroupBy(\Closure $from): void
    {
        $items = [3, 1, 4, 1, 5, 9, 2, 6];
        $grouped = array_map(fn($g) => $g->toArray(), ALinqCollection::from($items)->groupBy(fn($n) => $n % 3)->toArray());

        $this->assertSame([0 => [3, 9, 6], 1 => [1, 4, 1], 2 => [5, 2]], $grouped);
        $this->assertSame($grouped, $from($items)->toLookup(fn($n) => $n % 3)->toArray());
    }

    #[DataProvider('sides')]
    public function testToLookupKeysFollowGroupBy(\Closure $from): void
    {
        // a BackedEnum is its value; 1 and '1' are one PHP array key, '01' is another (D7)
        $lookup = $from([LookupStatus::Active, LookupStatus::Blocked, LookupStatus::Active])->toLookup(fn($s) => $s);
        $this->assertSame(['active', 'blocked'], $lookup->keys());
        $this->assertCount(2, $lookup->get(LookupStatus::Active));
        $this->assertTrue($lookup->contains(LookupStatus::Blocked));

        $mixed = $from([['k' => 1], ['k' => '1'], ['k' => '01']])->toLookup(fn($r) => $r['k']);
        $this->assertSame([1, '01'], $mixed->keys());
        $this->assertCount(2, $mixed->get('1'));
        $this->assertCount(2, $mixed->get(1));

        // null (and bool, float, array, object) throws, unlike .NET: a lookup answers as groupBy()
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('toLookup() expects the key selector to return an int, a string or a BackedEnum, null returned for item at key 0');
        $from([1])->toLookup(fn() => null);
    }

    public function testALookupIsImmutable(): void
    {
        $lookup = ALinqCollection::from([1, 2])->toLookup(fn($n) => $n % 2);
        foreach ([fn() => $lookup['x'] = ALinqCollection::from([]), function () use ($lookup) { unset($lookup[1]); }] as $write) {
            try {
                $write();
                $this->fail('a lookup must refuse writes');
            } catch (LogicException $e) {
                $this->assertSame('ALinqLookup is immutable.', $e->getMessage());
            }
        }
        // a group taken out of it is a copy
        $group = $lookup->get(1);
        $this->assertSame([1], $group->toArray());
        $this->assertSame([1], $lookup->get(1)->toArray());

        $this->expectException(TypeError::class);
        $lookup->get(1.5);
    }

    public function testTheConstructorKeepsEachGroupAsAList(): void
    {
        $lookup = new ALinqLookup(['a' => ['x' => 1, 'y' => 2], 'b' => [5 => 3]]);
        $this->assertSame(['a' => [1, 2], 'b' => [3]], $lookup->toArray());
        $this->assertSame([1, 2], $lookup->get('a')->toArray());
    }

    public function testAnEmptySourceGivesAnEmptyLookup(): void
    {
        foreach ([ALinqCollection::from([]), ALinqLazyCollection::empty()] as $source) {
            $lookup = $source->toLookup(fn($v) => $v);
            $this->assertCount(0, $lookup);
            $this->assertSame([], $lookup->keys());
            $this->assertSame([], $lookup->toArray());
            $this->assertSame('[]', json_encode($lookup));
        }
    }

    #[DataProvider('sides')]
    public function testToDictionaryThrowsByDefaultWithTheMessageOfEarlierVersions(\Closure $from): void
    {
        $this->assertSame([1 => self::ORDERS[0], 2 => self::ORDERS[1], 3 => self::ORDERS[2]], $from(self::ORDERS)->toDictionary(fn($o) => $o['id']));

        foreach ([null, DuplicateKeyPolicy::Throw] as $policy) {
            try {
                $policy === null
                    ? $from(self::ORDERS)->toDictionary(fn($o) => $o['customer'])
                    : $from(self::ORDERS)->toDictionary(fn($o) => $o['customer'], null, $policy);
                $this->fail('a repeated key must throw');
            } catch (InvalidArgumentException $e) {
                $this->assertSame("toDictionary() key 'acme' produced twice (second time for item at key 2)", $e->getMessage());
            }
        }
    }

    #[DataProvider('sides')]
    public function testKeepFirstAndKeepLast(\Closure $from): void
    {
        $calls = [];
        $total = function ($o) use (&$calls) {
            $calls[] = $o['id'];
            return $o['total'];
        };

        $this->assertSame(['acme' => 10, 'globex' => 20], $from(self::ORDERS)->toDictionary(fn($o) => $o['customer'], $total, DuplicateKeyPolicy::KeepFirst));
        $this->assertSame([1, 2], $calls, 'KeepFirst: the element selector does not run for a repeated key');

        $calls = [];
        $last = $from(self::ORDERS)->toDictionary(fn($o) => $o['customer'], $total, DuplicateKeyPolicy::KeepLast);
        $this->assertSame(['acme' => 30, 'globex' => 20], $last);
        $this->assertSame(['acme', 'globex'], array_keys($last), 'KeepLast: the key keeps the position of its first occurrence');
        $this->assertSame([1, 2, 3], $calls, 'KeepLast: the element selector runs for every item');

        $this->assertSame(['acme' => self::ORDERS[0], 'globex' => self::ORDERS[1]], $from(self::ORDERS)->toDictionary(fn($o) => $o['customer'], onDuplicate: DuplicateKeyPolicy::KeepFirst));
    }

    #[DataProvider('sides')]
    public function testEveryKeyIsValidatedInEveryPolicy(\Closure $from): void
    {
        foreach (DuplicateKeyPolicy::cases() as $policy) {
            try {
                $from([['k' => 'a'], ['k' => 'a'], ['k' => 1.5]])->toDictionary(fn($r) => $r['k'], null, $policy);
                $this->fail($policy->name . ': an invalid key must throw');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(
                    $policy === DuplicateKeyPolicy::Throw
                        ? "toDictionary() key 'a' produced twice (second time for item at key 1)"
                        : 'toDictionary() expects the key selector to return an int, a string or a BackedEnum, float returned for item at key 2',
                    $e->getMessage(),
                    $policy->name
                );
            }
        }
    }

    #[DataProvider('sides')]
    public function testOneAndStringOneAreTheSameDictionaryKey(\Closure $from): void
    {
        $rows = [['k' => 1, 'v' => 'int'], ['k' => '1', 'v' => 'string']];
        $this->assertSame([1 => 'string'], $from($rows)->toDictionary(fn($r) => $r['k'], fn($r) => $r['v'], DuplicateKeyPolicy::KeepLast));
        $this->expectExceptionMessage("toDictionary() key '1' produced twice (second time for item at key 1)");
        $from($rows)->toDictionary(fn($r) => $r['k']);
    }

    public function testThePolicyIsAnEnumNotAString(): void
    {
        $this->assertSame(['throw', 'first', 'last'], array_map(fn($c) => $c->value, DuplicateKeyPolicy::cases()));
        $this->expectException(TypeError::class);
        $collection = ALinqCollection::from([1]);
        $collection->toDictionary(fn($v) => $v, null, ...['onDuplicate' => 'last']);
    }

    public function testBothInterfacesDeclareTheNewSignatures(): void
    {
        foreach ([IALinqSelectable::class, IALinqLazyCollection::class] as $interface) {
            $toDictionary = new ReflectionMethod($interface, 'toDictionary');
            $this->assertSame('onDuplicate', $toDictionary->getParameters()[2]->getName(), $interface);
            $this->assertSame(DuplicateKeyPolicy::Throw, $toDictionary->getParameters()[2]->getDefaultValue(), $interface);
            $this->assertSame(ALinqLookup::class, (string) (new ReflectionMethod($interface, 'toLookup'))->getReturnType(), $interface);
        }
    }
}
