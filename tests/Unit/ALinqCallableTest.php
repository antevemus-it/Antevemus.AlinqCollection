<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Helpers\ALinqCallable;
use PHPUnit\Framework\TestCase;

/**
 * Helpers\ALinqCallable: the arity rule (withKey) and the identity rule (hashKey).
 *
 * L4 (review 2026-10-08, process): the variadic branch of acceptsKey() and the float/NAN
 * buckets of hashKey() were exercised only indirectly.
 */
class ALinqCallableTest extends TestCase
{
    public function testWithKeyHandsTheKeyToVariadicAndTwoParameterCallbacks(): void
    {
        $variadic = ALinqCallable::withKey(fn(...$args) => implode(':', $args));
        $this->assertSame('v:k', $variadic('v', 'k'));

        $two = ALinqCallable::withKey(fn($v, $k) => "$k=$v");
        $this->assertSame('k=v', $two('v', 'k'));

        $one = ALinqCallable::withKey('strtoupper');
        $this->assertSame('V', $one('v', 'k'));

        $this->assertTrue(ALinqCallable::acceptsKey(fn(...$a) => $a));
        $this->assertFalse(ALinqCallable::acceptsKey(fn($a) => $a));

        $this->assertSame(['k' => 'v:k'], ALinqCollection::from(['k' => 'v'])->select(fn(...$args) => implode(':', $args))->toArray());
    }

    public function testHashKeyIsTypedAndStable(): void
    {
        $this->assertNotSame(ALinqCallable::hashKey(1), ALinqCallable::hashKey('1'));
        $this->assertNotSame(ALinqCallable::hashKey(1), ALinqCallable::hashKey(1.0));
        $this->assertNotSame(ALinqCallable::hashKey(1), ALinqCallable::hashKey(true));
        $this->assertNotSame(ALinqCallable::hashKey(null), ALinqCallable::hashKey(''));
        $this->assertSame(ALinqCallable::hashKey(0.1 + 0.2), ALinqCallable::hashKey(0.30000000000000004));
        $this->assertSame(ALinqCallable::hashKey(NAN), ALinqCallable::hashKey(sqrt(-1)));
        $this->assertSame(ALinqCallable::hashKey([1, [2, 'x']]), ALinqCallable::hashKey([1, [2, 'x']]));
        $this->assertNotSame(ALinqCallable::hashKey([1, 2]), ALinqCallable::hashKey([2, 1]));

        $object = new \stdClass();
        $this->assertSame(ALinqCallable::hashKey($object), ALinqCallable::hashKey($object));
        $this->assertNotSame(ALinqCallable::hashKey($object), ALinqCallable::hashKey(new \stdClass()));
    }
}
