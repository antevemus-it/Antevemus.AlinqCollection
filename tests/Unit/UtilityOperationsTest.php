<?php

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Test class for Utility Operations
 * Tests all 7 utility methods in UtilityOperations trait
 */
class UtilityOperationsTest extends TestCase
{
    // ===== IS LIST TESTS =====

    /**
     * Test isList returns true for sequential numeric array
     */
    public function testIsListReturnsTrueForSequentialNumericArray(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        $this->assertTrue($collection->isList());
    }

    /**
     * Test isList returns false for associative array
     */
    public function testIsListReturnsFalseForAssociativeArray(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertFalse($collection->isList());
    }

    /**
     * Test isList returns false for non-sequential numeric keys
     */
    public function testIsListReturnsFalseForNonSequentialKeys(): void
    {
        $collection = ALinqCollection::from([0 => 'a', 2 => 'b', 3 => 'c']);

        $this->assertFalse($collection->isList());
    }

    /**
     * Test isList returns true for empty array
     */
    public function testIsListReturnsTrueForEmptyArray(): void
    {
        $collection = ALinqCollection::empty();

        $this->assertTrue($collection->isList());
    }

    /**
     * Test isList after filtering operation
     * Lists are reindexed after where(), so they remain lists
     */
    public function testIsListAfterFilteringOperation(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $filtered = $collection->where(fn($x) => $x > 2);

        // After where(), lists are reindexed, so they remain lists
        $this->assertTrue($filtered->isList());
    }

    // ===== EACH TESTS =====

    /**
     * Test each applies callback to all elements
     */
    public function testEachAppliesCallbackToAllElements(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = [];

        $collection->each(function($value, $key) use (&$result) {
            $result[$key] = $value * 2;
        });

        $this->assertEquals([0 => 2, 1 => 4, 2 => 6], $result);
    }

    /**
     * Test each returns same collection instance
     */
    public function testEachReturnsSameCollectionInstance(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->each(fn($value) => $value);

        $this->assertSame($collection, $result);
    }

    /**
     * Test each with side effects
     */
    public function testEachWithSideEffects(): void
    {
        $collection = ALinqCollection::from(['a', 'b', 'c']);
        $output = [];

        $collection->each(function($value, $key) use (&$output) {
            $output[] = strtoupper($value);
        });

        $this->assertEquals(['A', 'B', 'C'], $output);
    }

    /**
     * Test each on empty collection
     */
    public function testEachOnEmptyCollection(): void
    {
        $collection = ALinqCollection::empty();
        $count = 0;

        $collection->each(function() use (&$count) {
            $count++;
        });

        $this->assertEquals(0, $count);
    }

    // ===== EACH RECURSIVE TESTS =====

    /**
     * Test eachRecursive applies callback recursively
     */
    public function testEachRecursiveAppliesCallbackRecursively(): void
    {
        $collection = ALinqCollection::from([
            'a' => [
                'b' => [
                    'c' => 'value'
                ]
            ],
            'd' => 'another'
        ]);

        $values = [];
        $collection->eachRecursive(function($value, $key) use (&$values) {
            $values[] = $value;
        });

        $this->assertContains('value', $values);
        $this->assertContains('another', $values);
    }

    /**
     * Test eachRecursive returns same collection
     */
    public function testEachRecursiveReturnsSameCollection(): void
    {
        $collection = ALinqCollection::from([1, [2, 3]]);
        $result = $collection->eachRecursive(fn($value) => $value);

        $this->assertSame($collection, $result);
    }

    /**
     * Test eachRecursive with nested arrays
     */
    public function testEachRecursiveWithNestedArrays(): void
    {
        $collection = ALinqCollection::from([
            [1, 2, [3, 4]],
            [5, [6, 7]]
        ]);

        $sum = 0;
        $collection->eachRecursive(function($value) use (&$sum) {
            if (is_numeric($value)) {
                $sum += $value;
            }
        });

        $this->assertEquals(28, $sum);
    }

    // ===== RANDOM TESTS =====

    /**
     * Test random returns single element by default
     */
    public function testRandomReturnsSingleElement(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->random();

        $this->assertContains($result, [1, 2, 3, 4, 5]);
    }

    /**
     * Test random returns multiple elements
     */
    public function testRandomReturnsMultipleElements(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->random(3);

        $this->assertInstanceOf(ALinqCollection::class, $result);
        $this->assertCount(3, $result->toArray());
    }

    /**
     * D11 (forward 015): random() of an empty collection throws like first() (before 1.3.0:
     * null, which neither LINQ's First() nor PHP's array_rand() would answer).
     */
    public function testRandomOnEmptyCollectionThrowsLikeFirst(): void
    {
        $this->expectException(\UnderflowException::class);
        $this->expectExceptionMessage('Cannot take random() of an empty collection.');

        ALinqCollection::empty()->random();
    }

    /**
     * D11 (forward 015): random(n > 1) of an empty collection is an empty collection, like
     * take(n), so the pipeline keeps chaining (before 1.3.0: null).
     */
    public function testRandomOfManyOnEmptyCollectionIsAnEmptyCollection(): void
    {
        $result = ALinqCollection::empty()->random(3);

        $this->assertInstanceOf(ALinqCollection::class, $result);
        $this->assertSame([], $result->toArray());
    }

    /**
     * Test random with count larger than collection size
     */
    public function testRandomWithCountLargerThanCollectionSize(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $result = $collection->random(10);

        $this->assertCount(3, $result->toArray());
    }

    /**
     * Test random with single element collection
     */
    public function testRandomWithSingleElementCollection(): void
    {
        $collection = ALinqCollection::from([42]);
        $result = $collection->random();

        $this->assertEquals(42, $result);
    }

    /**
     * Test random returns collection when num > 1
     */
    public function testRandomReturnsCollectionWhenNumGreaterThanOne(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $result = $collection->random(2);

        $this->assertInstanceOf(ALinqCollection::class, $result);
    }

    // ===== EXTRACT TESTS =====

    /**
     * RN-26 (forward 015): extract() cannot export into the caller's scope (review 2026-10-08,
     * 3.10). It is deprecated since 1.3.0: emits E_USER_DEPRECATED, exports nothing to the
     * caller and still returns the count. Before, this test only asserted the count.
     */
    public function testExtractIsDeprecatedAndExportsNothingToTheCaller(): void
    {
        $collection = ALinqCollection::from([
            'var1' => 'value1',
            'var2' => 'value2'
        ]);

        $count = $this->capturingDeprecation(fn() => $collection->extract(EXTR_SKIP), $message);

        $this->assertSame('ALinqCollection::extract() is deprecated since 1.3.0 and will be removed in 2.0: a method cannot export variables into the scope of its caller.', $message);
        $this->assertSame(2, $count);
        $this->assertFalse(isset($var1), 'extract() never reached this scope');
    }

    /**
     * Runs $call with an E_USER_DEPRECATED handler that records the message, so the
     * deprecation is asserted here instead of being reported by the runner.
     */
    private function capturingDeprecation(callable $call, ?string &$message): mixed
    {
        $message = null;
        set_error_handler(static function (int $level, string $text) use (&$message): bool {
            $message = $text;
            return true;
        }, E_USER_DEPRECATED);
        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * RN-26: the count is still returned for every flag.
     */
    public function testExtractReturnsCountOfExtractedVariables(): void
    {
        $collection = ALinqCollection::from(['a' => 1, 'b' => 2, 'c' => 3]);

        $this->assertSame(3, $this->capturingDeprecation(fn() => $collection->extract(), $message));
        $this->assertStringContainsString('deprecated since 1.3.0', $message);
    }

    /**
     * RN-25 (forward 015, D9): each()/eachRecursive() never mutate the collection, even with
     * a by-reference callback (before 1.3.0 array_walk() on the property did, review
     * 2026-10-08, 2.12); the callback follows the arity rule, so a native one-parameter
     * callable works.
     */
    public function testEachNeverMutatesTheCollection(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $nested = ALinqCollection::from(['a' => [1, 2], 'b' => 3]);

        $seen = [];
        $result = $collection->each(function (&$value, $key) use (&$seen) {
            $seen[$key] = $value;
            $value *= 10;
        });
        $this->assertSame($collection, $result);
        $this->assertSame([1, 2, 3], $collection->toArray());
        $this->assertSame([1, 2, 3], $seen);

        $nested->eachRecursive(function (&$value) {
            $value = 'x';
        });
        $this->assertSame(['a' => [1, 2], 'b' => 3], $nested->toArray());

        $collection->each('intval');
        $collection->eachRecursive('intval');
        $this->assertSame([1, 2, 3], $collection->toArray());
    }

    /**
     * RN-13 and RN-02: random(0) throws; random(n > 1) keeps the keys of a dictionary.
     */
    public function testRandomRejectsCountBelowOneAndKeepsDictionaryKeys(): void
    {
        $dict = ['a' => 1, 'b' => 2, 'c' => 3];
        $picked = ALinqCollection::from($dict)->random(2)->toArray();
        $this->assertCount(2, $picked);
        foreach ($picked as $key => $value) {
            $this->assertSame($dict[$key], $value);
        }
        $this->assertTrue(array_is_list(ALinqCollection::from([1, 2, 3])->random(2)->toArray()));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('random() expects num to be at least 1, 0 given');
        ALinqCollection::from([1, 2, 3])->random(0);
    }

    // ===== CREATE PREDICATE TESTS =====

    /**
     * Test createPredicate with equality operator
     */
    public function testCreatePredicateWithEqualityOperator(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $predicate = $collection->createPredicate('=', 3);

        $this->assertTrue($predicate(3));
        $this->assertFalse($predicate(5));
    }

    /**
     * Test createPredicate with strict equality
     */
    public function testCreatePredicateWithStrictEquality(): void
    {
        $collection = ALinqCollection::from([1, '1', 2]);
        $predicate = $collection->createPredicate('===', 1);

        $this->assertTrue($predicate(1));
        $this->assertFalse($predicate('1'));
    }

    /**
     * Test createPredicate with greater than operator
     */
    public function testCreatePredicateWithGreaterThanOperator(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $predicate = $collection->createPredicate('>', 3);

        $this->assertTrue($predicate(4));
        $this->assertTrue($predicate(5));
        $this->assertFalse($predicate(2));
    }

    /**
     * Test createPredicate with less than or equal operator
     */
    public function testCreatePredicateWithLessThanOrEqualOperator(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $predicate = $collection->createPredicate('<=', 3);

        $this->assertTrue($predicate(1));
        $this->assertTrue($predicate(3));
        $this->assertFalse($predicate(4));
    }

    /**
     * Test createPredicate with in operator
     */
    public function testCreatePredicateWithInOperator(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $predicate = $collection->createPredicate('in', [2, 4, 6]);

        $this->assertTrue($predicate(2));
        $this->assertTrue($predicate(4));
        $this->assertFalse($predicate(3));
    }

    /**
     * Test createPredicate with contains operator
     */
    public function testCreatePredicateWithContainsOperator(): void
    {
        $collection = ALinqCollection::from(['hello world', 'foo bar']);
        $predicate = $collection->createPredicate('contains', 'world');

        $this->assertTrue($predicate('hello world'));
        $this->assertFalse($predicate('foo bar'));
    }

    /**
     * Test createPredicate with startsWith operator
     */
    public function testCreatePredicateWithStartsWithOperator(): void
    {
        $collection = ALinqCollection::from(['hello', 'world']);
        $predicate = $collection->createPredicate('startsWith', 'hel');

        $this->assertTrue($predicate('hello'));
        $this->assertFalse($predicate('world'));
    }

    /**
     * Test createPredicate with endsWith operator
     */
    public function testCreatePredicateWithEndsWithOperator(): void
    {
        $collection = ALinqCollection::from(['hello', 'world']);
        $predicate = $collection->createPredicate('endsWith', 'ld');

        $this->assertTrue($predicate('world'));
        $this->assertFalse($predicate('hello'));
    }

    /**
     * Test createPredicate with invalid operator throws exception
     */
    public function testCreatePredicateWithInvalidOperatorThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown operator: invalid");

        $collection = ALinqCollection::from([1, 2, 3]);
        $collection->createPredicate('invalid', 5);
    }

    /**
     * Test createPredicate with not equal operator
     */
    public function testCreatePredicateWithNotEqualOperator(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);
        $predicate = $collection->createPredicate('!=', 2);

        $this->assertTrue($predicate(1));
        $this->assertTrue($predicate(3));
        $this->assertFalse($predicate(2));
    }

    /**
     * Test createPredicate with strict not equal operator (!==)
     */
    public function testCreatePredicateWithStrictNotEqualOperator(): void
    {
        $collection = ALinqCollection::from([1, '1', 2]);
        $predicate = $collection->createPredicate('!==', 1);

        $this->assertTrue($predicate('1')); // string '1' !== int 1
        $this->assertFalse($predicate(1));  // int 1 === int 1
        $this->assertTrue($predicate(2));
    }

    /**
     * Test createPredicate with greater than or equal operator (>=)
     */
    public function testCreatePredicateWithGreaterThanOrEqualOperator(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $predicate = $collection->createPredicate('>=', 3);

        $this->assertFalse($predicate(1));
        $this->assertFalse($predicate(2));
        $this->assertTrue($predicate(3));  // 3 >= 3
        $this->assertTrue($predicate(4));
        $this->assertTrue($predicate(5));
    }

    /**
     * Test createPredicate with less than operator (<)
     */
    public function testCreatePredicateWithLessThanOperator(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);
        $predicate = $collection->createPredicate('<', 3);

        $this->assertTrue($predicate(1));
        $this->assertTrue($predicate(2));
        $this->assertFalse($predicate(3)); // 3 < 3 é false
        $this->assertFalse($predicate(4));
        $this->assertFalse($predicate(5));
    }

    // ===== CREATE PROPERTY SELECTOR TESTS =====

    /**
     * Test createPropertySelector for array access
     */
    public function testCreatePropertySelectorForArrayAccess(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30]
        ]);

        $selector = $collection->createPropertySelector('name');

        $this->assertEquals('Alice', $selector(['name' => 'Alice', 'age' => 25]));
        $this->assertEquals('Bob', $selector(['name' => 'Bob', 'age' => 30]));
    }

    /**
     * Test createPropertySelector for object access
     */
    public function testCreatePropertySelectorForObjectAccess(): void
    {
        $obj = (object)['name' => 'Alice', 'age' => 25];

        $collection = ALinqCollection::from([$obj]);
        $selector = $collection->createPropertySelector('name');

        $this->assertEquals('Alice', $selector($obj));
    }

    /**
     * Test createPropertySelector returns null for missing property
     */
    public function testCreatePropertySelectorReturnsNullForMissingProperty(): void
    {
        $collection = ALinqCollection::from([['name' => 'Alice']]);
        $selector = $collection->createPropertySelector('age');

        $this->assertNull($selector(['name' => 'Alice']));
    }

    /**
     * Test createPropertySelector with getter method
     */
    public function testCreatePropertySelectorWithGetterMethod(): void
    {
        $obj = new class {
            public function getValue() {
                return 'test from getter';
            }
        };

        $collection = ALinqCollection::from([$obj]);
        $selector = $collection->createPropertySelector('value');

        // O ALinqPropertyAccess vai tentar getValue() como fallback
        $this->assertEquals('test from getter', $selector($obj));
    }

    /**
     * Test fluent API chaining with utility operations
     */
    public function testFluentApiChainingWithUtilityOperations(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5]);

        $count = 0;
        $result = $collection
            ->where(fn($x) => $x > 2)
            ->each(function($value) use (&$count) {
                $count++;
            });

        $this->assertEquals(3, $count);
        $this->assertInstanceOf(ALinqCollection::class, $result);
    }

    /**
     * Test utility operations return correct types
     */
    public function testUtilityOperationsReturnCorrectTypes(): void
    {
        $collection = ALinqCollection::from([1, 2, 3]);

        $this->assertIsBool($collection->isList());
        $this->assertInstanceOf(ALinqCollection::class, $collection->each(fn($x) => $x));
        $this->assertInstanceOf(\Closure::class, $collection->createPredicate('=', 1));
        $this->assertInstanceOf(\Closure::class, $collection->createPropertySelector('name'));
    }

    // ===== CREATE PREDICATE: SHARED EVALUATOR (review 2026-10-08 §3.20) =====

    public function testCreatePredicateSupportsBetweenNotInAndIsNull(): void
    {
        $collection = ALinqCollection::from([1, 2, 3, 4, 5, null]);

        $between = $collection->createPredicate('between', [2, 4]);
        $this->assertTrue($between(2));
        $this->assertTrue($between(4));
        $this->assertFalse($between(5));

        $notBetween = $collection->createPredicate('notBetween', [2, 4]);
        $this->assertTrue($notBetween(5));
        $this->assertFalse($notBetween(3));

        $notIn = $collection->createPredicate('notIn', [2, 4]);
        $this->assertTrue($notIn(1));
        $this->assertFalse($notIn(2));

        $isNull = $collection->createPredicate('isNull');
        $this->assertTrue($isNull(null));
        $this->assertFalse($isNull(0));

        $isNotNull = $collection->createPredicate('isNotNull');
        $this->assertTrue($isNotNull(0));
        $this->assertFalse($isNotNull(null));

        $this->assertSame([2, 3, 4], array_values(array_filter([1, 2, 3, 4, 5], $between)));
    }

    public function testCreatePredicateOperatorsAreCaseInsensitive(): void
    {
        $collection = ALinqCollection::from(['hello', 'world']);

        $this->assertTrue($collection->createPredicate('STARTSWITH', 'hel')('hello'));
        $this->assertTrue($collection->createPredicate('EndsWith', 'ld')('world'));
        $this->assertTrue($collection->createPredicate('IN', ['hello'])('hello'));
        $this->assertTrue($collection->createPredicate('Between', [1, 3])(2));
    }

    public function testCreatePredicateAgreesWithTheQueryBuilderForEveryOperator(): void
    {
        $collection = ALinqCollection::from([]);
        $samples = [1, '1', 2, 0, null, '', 'abc', 'xabcx', [1], true, false, 10, '1e1'];

        foreach (ALinqQueryBuilder::supportedOperators() as $operator) {
            $value = match ($operator) {
                'between', 'notBetween' => [1, 5],
                'in', 'notIn' => [1, 'abc'],
                'contains', 'startsWith', 'endsWith' => 'abc',
                default => 1,
            };

            $fromCollection = $collection->createPredicate($operator, $value);
            $fromBuilder = ALinqQueryBuilder::create()->where('v', $operator, $value)->toPredicate();

            foreach ($samples as $sample) {
                $this->assertSame(
                    $fromBuilder(['v' => $sample]),
                    $fromCollection($sample),
                    sprintf('operator %s diverges for %s', $operator, var_export($sample, true))
                );
            }
        }
    }

    public function testCreatePredicateUnknownOperatorMessageListsTheSupportedOnes(): void
    {
        try {
            ALinqCollection::from([1])->createPredicate('like', '%x%');
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringStartsWith('Unknown operator: like', $e->getMessage());
            $this->assertStringContainsString('between', $e->getMessage());
        }
    }

    // ===== CREATE PROPERTY SELECTOR: VISIBILITY AND DOT NOTATION (review 2026-10-08 §3.1, §3.2) =====

    public function testCreatePropertySelectorResolvesPrivatePropertiesThroughGettersInOrderBy(): void
    {
        $collection = ALinqCollection::from([
            new UtilityPerson('Carol', 45),
            new UtilityPerson('Alice', 30),
            new UtilityPerson('Bob', 17),
        ]);

        $byName = $collection->orderBy($collection->createPropertySelector('name'));
        $this->assertSame(['Alice', 'Bob', 'Carol'], $byName->select(fn($p) => $p->getName())->toArray());

        $byAgeDesc = $collection->orderByDescending($collection->createPropertySelector('age'));
        $this->assertSame([45, 30, 17], $byAgeDesc->select(fn($p) => $p->getAge())->toArray());
    }

    public function testCreatePropertySelectorResolvesDotNotation(): void
    {
        $collection = ALinqCollection::from([
            ['user' => ['address' => ['city' => 'Paris']]],
            ['user' => ['address' => ['city' => 'Lima']]],
        ]);

        $selector = $collection->createPropertySelector('user.address.city');

        $this->assertSame(['Paris', 'Lima'], $collection->select($selector)->toArray());
    }
}

final class UtilityPerson
{
    public function __construct(private string $name, private int $age) {}
    public function getName(): string { return $this->name; }
    public function getAge(): int { return $this->age; }
}
