<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqAggregatable;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Stringable;

/**
 * implode() (2.2.0, forward 047, D24), on both collections.
 */
final class ImplodeTest extends TestCase
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
    public function testItJoinsTheTextOfTheItems(\Closure $from): void
    {
        $label = new class implements Stringable {
            public function __toString(): string
            {
                return 'label';
            }
        };

        $this->assertSame('a, 1, 2.5, label, , z', $from(['a', 1, 2.5, $label, null, 'z'])->implode(', '));
        $this->assertSame('abc', $from(['a', 'b', 'c'])->implode());
        $this->assertSame('', $from([])->implode(', '));
        $this->assertSame('', $from([null])->implode(', '));
        $this->assertSame('0.1', $from([0.1])->implode());
        $this->assertSame('1', $from([1.0])->implode(), 'PHP\'s own text of a float');
    }

    #[DataProvider('sides')]
    public function testASelectorReceivesTheItemAndItsKey(\Closure $from): void
    {
        $rows = ['x' => ['name' => 'Ana'], 'y' => ['name' => 'Bruno']];
        $this->assertSame('Ana | Bruno', $from($rows)->implode(' | ', fn($r) => $r['name']));
        $this->assertSame('x=Ana;y=Bruno', $from($rows)->implode(';', fn($r, $k) => "$k=" . $r['name']));
    }

    #[DataProvider('sides')]
    public function testAValueWithoutTextThrows(\Closure $from): void
    {
        foreach (
            [
                'bool' => [['a', true], 'bool', '1'],
                'array' => [['k' => [1]], 'array', "'k'"],
                'object' => [['a', 'b', new \stdClass()], 'stdClass', '2'],
            ] as $case => [$items, $type, $key]
        ) {
            try {
                $from($items)->implode(',');
                $this->fail("$case must throw");
            } catch (InvalidArgumentException $e) {
                $this->assertSame("implode() expects strings, numbers, Stringable or null, $type found at key $key", $e->getMessage(), $case);
            }
        }
    }

    public function testTheLazySideStreams(): void
    {
        $text = ALinqLazyCollection::range(1, 100000)->where(fn($n) => $n % 25000 === 0)->implode(',');
        $this->assertSame('25000,50000,75000,100000', $text);
    }

    public function testBothInterfacesDeclareIt(): void
    {
        foreach ([IALinqAggregatable::class, IALinqLazyCollection::class] as $interface) {
            $method = new ReflectionMethod($interface, 'implode');
            $this->assertSame(['separator', 'selector'], array_map(fn($p) => $p->getName(), $method->getParameters()), $interface);
            $this->assertSame('', $method->getParameters()[0]->getDefaultValue());
            $this->assertSame('string', (string) $method->getReturnType());
        }
    }
}
