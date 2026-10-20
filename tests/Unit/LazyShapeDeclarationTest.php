<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use ArrayIterator;
use Closure;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * from($factory, ?bool $isList) (2.3.0, forward 052): the declared shape of a Closure factory's stream.
 * `true` makes it a list, `false` a dictionary (an integer-keyed one keeps its keys), `null` keeps the
 * unknown shape of 2.2; a declaration with any other source is refused; the operators that read the shape
 * answer over a declared stream as over an array of that shape; isList() (2.3.0) answers the shape the
 * collection knows, after a source or an operator, and agrees with the eager isList() over an array.
 */
final class LazyShapeDeclarationTest extends TestCase
{
    /** A factory that yields the given pairs, keys as written (repeated keys allowed). */
    private static function pairs(array ...$pairs): Closure
    {
        return static function () use ($pairs): Generator {
            foreach ($pairs as [$key, $value]) {
                yield $key => $value;
            }
        };
    }

    /** @return list<array{mixed, mixed}> the [key, value] pairs of a traversal */
    private static function streamed(iterable $items): array
    {
        $out = [];
        foreach ($items as $key => $value) {
            $out[] = [$key, $value];
        }
        return $out;
    }

    public function testATrueDeclarationMakesTheStreamAList(): void
    {
        $stage = ALinqLazyCollection::from(self::pairs([5, 'a'], [9, 'b'], ['x', 'c']), true);

        $this->assertSame(['a', 'b', 'c'], $stage->toArray(), 'the keys of the generator are ignored');
        $this->assertSame([[0, 'a'], [1, 'b'], [2, 'c']], self::streamed($stage->where(static fn(): bool => true)), 'the streaming operators renumber');
        $this->assertSame(['a', 'b', 'c'], ALinqLazyCollection::from(self::pairs([1, 'a'], [1, 'b'], [1, 'c']), true)->toArray(), 'repeated keys lose nothing');
        $this->assertSame('["a","b","c"]', json_encode($stage), 'a JSON array');
        // the declaration does not rewrite the keys the generator yields: the source stage and remember()
        // (a replay of its upstream) stream them as yielded, the renumbering operators and toArray() do not
        $this->assertSame([[5, 'a'], [9, 'b'], ['x', 'c']], self::streamed($stage));
        $this->assertSame([[5, 'a'], [9, 'b'], ['x', 'c']], self::streamed($stage->remember()));
        $this->assertSame(['a', 'b', 'c'], $stage->remember()->toArray());
        $this->assertSame([5 => 'a', 9 => 'b', 'x' => 'c'], ALinqLazyCollection::from(self::pairs([5, 'a'], [9, 'b'], ['x', 'c']), false)->remember()->toArray(), 'remember() keeps the declared dictionary');
    }

    public function testAFalseDeclarationKeepsTheKeysOfAnIntegerKeyedDictionary(): void
    {
        $this->assertSame([5 => 'a', 9 => 'b'], ALinqLazyCollection::from(self::pairs([5, 'a'], [9, 'b']), false)->toArray());
        $this->assertSame([0 => 'a', 1 => 'b'], ALinqLazyCollection::from(self::pairs([0, 'a'], [1, 'b']), false)->toArray(), 'keys 0 and 1 are kept as keys, as for an array dictionary');
        $this->assertSame([5 => 'a', 6 => 'b', 'k' => 'c'], ALinqLazyCollection::from(self::pairs([5, 'a'], [5, 'b'], ['k', 'c']), false)->toArray(), 'a repeated key appends instead of overwriting');
        $this->assertSame('{"5":"a","9":"b"}', json_encode(ALinqLazyCollection::from(self::pairs([5, 'a'], [9, 'b']), false)), 'a JSON object');
    }

    public function testNullKeepsTheUnknownShapeOf22(): void
    {
        $this->assertSame(['a', 'b'], ALinqLazyCollection::from(self::pairs([5, 'a'], [9, 'b']))->toArray(), 'every key an integer: a list');
        $this->assertSame(['a', 'b'], ALinqLazyCollection::from(self::pairs([5, 'a'], [9, 'b']), null)->toArray());
        $this->assertSame(['x' => 'a', 9 => 'b'], ALinqLazyCollection::from(self::pairs(['x', 'a'], [9, 'b']), null)->toArray(), 'a string key: a dictionary');
        $this->assertSame([[5, 'a'], [9, 'b']], self::streamed(ALinqLazyCollection::from(self::pairs([5, 'a'], [9, 'b']))->where(static fn(): bool => true)), 'keys streamed as yielded');
    }

    /** @return array<string, array{\Closure(): mixed}> */
    public static function iterableSources(): array
    {
        return [
            'array list' => [static fn(): array => [1, 2]],
            'array dictionary' => [static fn(): array => [5 => 1]],
            'callable array (data)' => [static fn(): array => [new ArrayIterator(), 'count']],
            'Traversable' => [static fn(): ArrayIterator => new ArrayIterator([1])],
            'Generator object' => [static fn(): Generator => (static function (): Generator {
                yield 1;
            })()],
        ];
    }

    /** @param \Closure(): mixed $source */
    #[DataProvider('iterableSources')]
    public function testADeclarationWithAnIterableIsRefused(Closure $source): void
    {
        foreach ([true, false] as $isList) {
            $value = $source();
            try {
                ALinqLazyCollection::from($value, $isList);
                $this->fail('accepted');
            } catch (InvalidArgumentException $e) {
                $this->assertSame(sprintf('ALinqLazyCollection::from() accepts $isList only with a Closure factory, %s given: the shape of an iterable is known or inferred from the iterable itself', get_debug_type($value)), $e->getMessage());
            }
        }
        $this->assertInstanceOf(ALinqLazyCollection::class, ALinqLazyCollection::from($source(), null), 'null is accepted with any source');
    }

    /**
     * Each operator that reads the shape (it renumbers a list and keeps the keys of a dictionary) answers
     * over a declared stream as over an array of the same shape, streamed pairs and toArray() alike.
     *
     * @return array<string, array{\Closure(IALinqLazyCollection): IALinqLazyCollection}>
     */
    public static function shapeReadingOperators(): array
    {
        return [
            'where' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->where(static fn(int $v): bool => $v !== 2)],
            'whereNot' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->whereNot(static fn(int $v): bool => $v === 2)],
            'select' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->select(static fn(int $v): int => $v * 10)],
            'take' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->take(2)],
            'skip' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->skip(1)],
            'takeWhile' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->takeWhile(static fn(int $v): bool => $v < 3)],
            'skipWhile' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->skipWhile(static fn(int $v): bool => $v < 2)],
            'distinct' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->distinct()],
            'pad' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->pad(3, 0)],
            'tap' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->tap(static function (): void {
            })],
            'orderByDescending' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->orderByDescending(static fn(int $v): int => $v)],
            'exceptByKeys' => [static fn(IALinqLazyCollection $s): IALinqLazyCollection => $s->exceptByKeys([2], static fn(int $v): int => $v)],
        ];
    }

    /** @param \Closure(IALinqLazyCollection): IALinqLazyCollection $operator */
    #[DataProvider('shapeReadingOperators')]
    public function testOperatorsDownstreamHonourTheDeclaredShape(Closure $operator): void
    {
        $dictionary = [5 => 1, 9 => 2, 12 => 3];
        $declaredDictionary = $operator(ALinqLazyCollection::from(self::pairs([5, 1], [9, 2], [12, 3]), false));
        $arrayDictionary = $operator(ALinqLazyCollection::from($dictionary));
        $this->assertSame(self::streamed($arrayDictionary), self::streamed($declaredDictionary), 'dictionary: the streamed pairs');
        $this->assertSame($arrayDictionary->toArray(), $declaredDictionary->toArray(), 'dictionary: toArray()');
        $this->assertNotSame(array_values($arrayDictionary->toArray()), $declaredDictionary->toArray(), 'the integer keys survive (not a list)');

        // a list declared over a generator whose keys are not 0..n-1: the operators renumber as over an array list
        $declaredList = $operator(ALinqLazyCollection::from(self::pairs([5, 1], [9, 2], [12, 3]), true));
        $arrayList = $operator(ALinqLazyCollection::from([1, 2, 3]));
        $this->assertSame(self::streamed($arrayList), self::streamed($declaredList), 'list: the streamed pairs');
        $this->assertSame($arrayList->toArray(), $declaredList->toArray(), 'list: toArray()');
    }

    public function testIsListAnswersTheShapeTheCollectionKnows(): void
    {
        $this->assertTrue(ALinqLazyCollection::from([1, 2])->isList(), 'an array list');
        $this->assertTrue(ALinqLazyCollection::from([])->isList(), 'an empty array is a list (array_is_list)');
        $this->assertFalse(ALinqLazyCollection::from([5 => 1, 9 => 2])->isList(), 'an array dictionary');
        $this->assertFalse(ALinqLazyCollection::from(['a' => 1])->isList());
        $this->assertTrue(ALinqLazyCollection::from(self::pairs([5, 'a']), true)->isList(), 'a factory declared a list');
        $this->assertFalse(ALinqLazyCollection::from(self::pairs([5, 'a']), false)->isList(), 'a factory declared a dictionary');
        $this->assertNull(ALinqLazyCollection::from(self::pairs([5, 'a']))->isList(), 'an undeclared factory: unknown');
        $this->assertNull(ALinqLazyCollection::from(self::pairs([5, 'a']), null)->isList());
        $this->assertNull(ALinqLazyCollection::from(new ArrayIterator([1, 2]))->isList(), 'a Traversable: unknown');
        $this->assertNull(ALinqLazyCollection::from((static function (): Generator {
            yield 1;
        })())->isList(), 'a Generator object: unknown');
        $this->assertTrue(ALinqLazyCollection::range(1, 3)->isList(), 'a factory of the core that yields a list');
        $this->assertTrue(ALinqLazyCollection::empty()->isList());
    }

    public function testIsListAfterAnOperatorIsTheShapeTheOperatorProduces(): void
    {
        $list = ALinqLazyCollection::from([1, 2, 3]);
        $dictionary = ALinqLazyCollection::from([5 => 1, 9 => 2]);
        $unknown = ALinqLazyCollection::from(self::pairs([5, 1], [9, 2]));

        foreach (['list' => [$list, true], 'dictionary' => [$dictionary, false], 'unknown' => [$unknown, null]] as $name => [$source, $shape]) {
            $this->assertSame($shape, $source->where(static fn(): bool => true)->isList(), "$name: where() keeps the shape of its source");
            $this->assertSame($shape, $source->select(static fn(int $v): int => $v)->isList(), "$name: select() keeps it");
            $this->assertSame($shape, $source->take(1)->isList(), "$name: take() keeps it");
            $this->assertSame($shape, $source->orderBy(static fn(int $v): int => $v)->isList(), "$name: orderBy() keeps it");
            $this->assertSame($shape, $source->remember()->isList(), "$name: remember() keeps it");
            $this->assertTrue($source->selectMany(static fn(int $v): array => [$v])->isList(), "$name: selectMany() is a list");
            $this->assertTrue($source->concat([0])->isList(), "$name: concat() is a list");
            $this->assertTrue($source->chunk(1)->isList(), "$name: chunk() is a list");
            $this->assertFalse($source->groupBy(static fn(int $v): int => $v % 2)->isList(), "$name: groupBy() is a dictionary");
            $this->assertFalse($source->countBy(static fn(int $v): int => $v % 2)->isList(), "$name: countBy() is a dictionary");
        }
    }

    /** @return array<string, array{array<mixed>}> */
    public static function arrays(): array
    {
        return ['list' => [[1, 2, 3]], 'empty' => [[]], 'integer keys' => [[5 => 1, 9 => 2]], 'keys 0 and 2' => [[0 => 1, 2 => 2]], 'string keys' => [['a' => 1]], 'mixed' => [[0 => 1, 'b' => 2]]];
    }

    /** @param array<mixed> $items */
    #[DataProvider('arrays')]
    public function testOverAnArrayTheLazyAndTheEagerIsListAgree(array $items): void
    {
        $this->assertSame(\Antevemus\ALinq\ALinqCollection::from($items)->isList(), ALinqLazyCollection::from($items)->isList());
    }

    public function testTheInterfaceDeclaresTheParameter(): void
    {
        foreach ([IALinqLazyCollection::class, ALinqLazyCollection::class] as $class) {
            $this->assertSame('?bool', (string) (new ReflectionMethod($class, 'isList'))->getReturnType(), "$class::isList(): ?bool");
            $parameters = (new ReflectionMethod($class, 'from'))->getParameters();
            $this->assertCount(2, $parameters, $class);
            $this->assertSame('isList', $parameters[1]->getName());
            $this->assertSame('?bool', (string) $parameters[1]->getType());
            $this->assertTrue($parameters[1]->isDefaultValueAvailable());
            $this->assertNull($parameters[1]->getDefaultValue());
        }
    }
}
