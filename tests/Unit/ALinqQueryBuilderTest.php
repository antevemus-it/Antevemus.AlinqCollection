<?php

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqQueryBuilder;
use Antevemus\ALinq\ALinqCollection;
use PHPUnit\Framework\TestCase;

/**
 * Test class for ALinqQueryBuilder
 * Tests query builder methods for complex filtering operations
 */
class ALinqQueryBuilderTest extends TestCase
{
    // ===== CREATE TESTS =====

    /**
     * Test create returns new query builder instance
     */
    public function testCreateReturnsNewQueryBuilderInstance(): void
    {
        $builder = ALinqQueryBuilder::create();

        $this->assertInstanceOf(ALinqQueryBuilder::class, $builder);
    }

    /**
     * Test create with AND mode by default
     */
    public function testCreateWithAndModeByDefault(): void
    {
        $builder = ALinqQueryBuilder::create();

        $predicate = $builder
            ->where('value', '>', 5)
            ->where('value', '<', 10)
            ->toPredicate();

        $this->assertTrue($predicate(['value' => 7]));
        $this->assertFalse($predicate(['value' => 3]));
        $this->assertFalse($predicate(['value' => 12]));
    }

    /**
     * Test create with OR mode
     */
    public function testCreateWithOrMode(): void
    {
        $builder = ALinqQueryBuilder::create('or');

        $predicate = $builder
            ->where('value', '<', 5)
            ->where('value', '>', 10)
            ->toPredicate();

        $this->assertTrue($predicate(['value' => 3]));
        $this->assertTrue($predicate(['value' => 12]));
        $this->assertFalse($predicate(['value' => 7]));
    }

    /**
     * Test create is case insensitive for mode
     */
    public function testCreateIsCaseInsensitiveForMode(): void
    {
        $builderAnd = ALinqQueryBuilder::create('AND');
        $builderOr = ALinqQueryBuilder::create('OR');

        $this->assertInstanceOf(ALinqQueryBuilder::class, $builderAnd);
        $this->assertInstanceOf(ALinqQueryBuilder::class, $builderOr);
    }

    // ===== WHERE TESTS =====

    /**
     * Test where with equality operator
     */
    public function testWhereWithEqualityOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('name', '=', 'Alice')
            ->toPredicate();

        $this->assertTrue($predicate(['name' => 'Alice']));
        $this->assertFalse($predicate(['name' => 'Bob']));
    }

    /**
     * Test where with strict equality operator
     */
    public function testWhereWithStrictEqualityOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('value', '===', 1)
            ->toPredicate();

        $this->assertTrue($predicate(['value' => 1]));
        $this->assertFalse($predicate(['value' => '1']));
    }

    /**
     * Test where with not equal operator
     */
    public function testWhereWithNotEqualOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('status', '!=', 'inactive')
            ->toPredicate();

        $this->assertTrue($predicate(['status' => 'active']));
        $this->assertFalse($predicate(['status' => 'inactive']));
    }

    /**
     * Test where with strict not equal operator
     */
    public function testWhereWithStrictNotEqualOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('id', '!==', '1')
            ->toPredicate();

        $this->assertTrue($predicate(['id' => 1]));
        $this->assertFalse($predicate(['id' => '1']));
    }

    /**
     * Test where with greater than operator
     */
    public function testWhereWithGreaterThanOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('age', '>', 18)
            ->toPredicate();

        $this->assertTrue($predicate(['age' => 25]));
        $this->assertFalse($predicate(['age' => 15]));
    }

    /**
     * Test where with greater than or equal operator
     */
    public function testWhereWithGreaterThanOrEqualOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('score', '>=', 50)
            ->toPredicate();

        $this->assertTrue($predicate(['score' => 50]));
        $this->assertTrue($predicate(['score' => 75]));
        $this->assertFalse($predicate(['score' => 40]));
    }

    /**
     * Test where with less than operator
     */
    public function testWhereWithLessThanOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('price', '<', 100)
            ->toPredicate();

        $this->assertTrue($predicate(['price' => 50]));
        $this->assertFalse($predicate(['price' => 150]));
    }

    /**
     * Test where with less than or equal operator
     */
    public function testWhereWithLessThanOrEqualOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('quantity', '<=', 10)
            ->toPredicate();

        $this->assertTrue($predicate(['quantity' => 10]));
        $this->assertTrue($predicate(['quantity' => 5]));
        $this->assertFalse($predicate(['quantity' => 15]));
    }

    /**
     * Test where with in operator
     */
    public function testWhereWithInOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('category', 'in', ['A', 'B', 'C'])
            ->toPredicate();

        $this->assertTrue($predicate(['category' => 'A']));
        $this->assertTrue($predicate(['category' => 'B']));
        $this->assertFalse($predicate(['category' => 'D']));
    }

    /**
     * Test where with contains operator
     */
    public function testWhereWithContainsOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('description', 'contains', 'test')
            ->toPredicate();

        $this->assertTrue($predicate(['description' => 'This is a test']));
        $this->assertFalse($predicate(['description' => 'No match here']));
    }

    /**
     * Test where with startsWith operator
     */
    public function testWhereWithStartsWithOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('name', 'startsWith', 'Mr')
            ->toPredicate();

        $this->assertTrue($predicate(['name' => 'Mr. Smith']));
        $this->assertFalse($predicate(['name' => 'Dr. Jones']));
    }

    /**
     * Test where with endsWith operator
     */
    public function testWhereWithEndsWithOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('email', 'endsWith', '@example.com')
            ->toPredicate();

        $this->assertTrue($predicate(['email' => 'user@example.com']));
        $this->assertFalse($predicate(['email' => 'user@test.com']));
    }

    /**
     * Test where with invalid operator throws exception
     */
    public function testWhereWithInvalidOperatorThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown operator: invalid");

        ALinqQueryBuilder::create()
            ->where('field', 'invalid', 'value');
    }

    /**
     * Test where can be chained multiple times
     */
    public function testWhereCanBeChainedMultipleTimes(): void
    {
        $builder = ALinqQueryBuilder::create()
            ->where('age', '>', 18)
            ->where('age', '<', 65)
            ->where('status', '=', 'active');

        $predicate = $builder->toPredicate();

        $this->assertTrue($predicate(['age' => 30, 'status' => 'active']));
        $this->assertFalse($predicate(['age' => 70, 'status' => 'active']));
        $this->assertFalse($predicate(['age' => 30, 'status' => 'inactive']));
    }

    // ===== WHERE CUSTOM TESTS =====

    /**
     * Test whereCustom with custom predicate
     */
    public function testWhereCustomWithCustomPredicate(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->whereCustom(fn($item) => $item['value'] % 2 === 0)
            ->toPredicate();

        $this->assertTrue($predicate(['value' => 4]));
        $this->assertFalse($predicate(['value' => 3]));
    }

    /**
     * Test whereCustom can be combined with where
     */
    public function testWhereCustomCanBeCombinedWithWhere(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('value', '>', 0)
            ->whereCustom(fn($item) => $item['value'] % 2 === 0)
            ->toPredicate();

        $this->assertTrue($predicate(['value' => 4]));
        $this->assertFalse($predicate(['value' => 3]));
        $this->assertFalse($predicate(['value' => -2]));
    }

    /**
     * Test whereCustom with complex logic
     */
    public function testWhereCustomWithComplexLogic(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->whereCustom(function($item) {
                return isset($item['name']) &&
                       isset($item['age']) &&
                       strlen($item['name']) > 3 &&
                       $item['age'] >= 18;
            })
            ->toPredicate();

        $this->assertTrue($predicate(['name' => 'Alice', 'age' => 25]));
        $this->assertFalse($predicate(['name' => 'Bob', 'age' => 25]));
        $this->assertFalse($predicate(['name' => 'Alice', 'age' => 15]));
    }

    // ===== TO PREDICATE TESTS =====

    /**
     * Test toPredicate returns closure
     */
    public function testToPredicateReturnsClosure(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('value', '=', 1)
            ->toPredicate();

        $this->assertInstanceOf(\Closure::class, $predicate);
    }

    /**
     * Test toPredicate with no conditions returns always true
     */
    public function testToPredicateWithNoConditionsReturnsAlwaysTrue(): void
    {
        $predicate = ALinqQueryBuilder::create()->toPredicate();

        $this->assertTrue($predicate(['anything' => 'value']));
        $this->assertTrue($predicate([]));
    }

    /**
     * Test toPredicate AND mode requires all conditions
     */
    public function testToPredicateAndModeRequiresAllConditions(): void
    {
        $predicate = ALinqQueryBuilder::create('and')
            ->where('age', '>', 18)
            ->where('age', '<', 65)
            ->where('status', '=', 'active')
            ->toPredicate();

        $this->assertTrue($predicate(['age' => 30, 'status' => 'active']));
        $this->assertFalse($predicate(['age' => 70, 'status' => 'active']));
    }

    /**
     * Test toPredicate OR mode requires any condition
     */
    public function testToPredicateOrModeRequiresAnyCondition(): void
    {
        $predicate = ALinqQueryBuilder::create('or')
            ->where('type', '=', 'admin')
            ->where('type', '=', 'moderator')
            ->toPredicate();

        $this->assertTrue($predicate(['type' => 'admin']));
        $this->assertTrue($predicate(['type' => 'moderator']));
        $this->assertFalse($predicate(['type' => 'user']));
    }

    // ===== INTEGRATION TESTS =====

    /**
     * Test query builder integration with ALinqCollection
     */
    public function testQueryBuilderIntegrationWithCollection(): void
    {
        $collection = ALinqCollection::from([
            ['name' => 'Alice', 'age' => 25, 'city' => 'New York'],
            ['name' => 'Bob', 'age' => 30, 'city' => 'London'],
            ['name' => 'Charlie', 'age' => 35, 'city' => 'Paris'],
            ['name' => 'David', 'age' => 22, 'city' => 'New York']
        ]);

        $predicate = ALinqQueryBuilder::create()
            ->where('age', '>=', 25)
            ->where('city', '=', 'New York')
            ->toPredicate();

        $result = $collection->where($predicate);

        $this->assertCount(1, $result->toArray());
    }

    /**
     * Test complex query with multiple conditions
     */
    public function testComplexQueryWithMultipleConditions(): void
    {
        $collection = ALinqCollection::from([
            ['id' => 1, 'status' => 'active', 'score' => 85, 'premium' => true],
            ['id' => 2, 'status' => 'active', 'score' => 92, 'premium' => false],
            ['id' => 3, 'status' => 'inactive', 'score' => 78, 'premium' => true],
            ['id' => 4, 'status' => 'active', 'score' => 95, 'premium' => true]
        ]);

        $predicate = ALinqQueryBuilder::create()
            ->where('status', '=', 'active')
            ->where('score', '>=', 90)
            ->whereCustom(fn($item) => $item['premium'] === true)
            ->toPredicate();

        $result = $collection->where($predicate);

        $this->assertCount(1, $result->toArray());
        $this->assertEquals(4, $result->toArray()[0]['id']);
    }

    /**
     * Test query builder with OR mode and collection
     */
    public function testQueryBuilderWithOrModeAndCollection(): void
    {
        $collection = ALinqCollection::from([
            ['role' => 'admin'],
            ['role' => 'moderator'],
            ['role' => 'user'],
            ['role' => 'guest']
        ]);

        $predicate = ALinqQueryBuilder::create('or')
            ->where('role', '=', 'admin')
            ->where('role', '=', 'moderator')
            ->toPredicate();

        $result = $collection->where($predicate);

        $this->assertCount(2, $result->toArray());
    }

    /**
     * Test query builder fluent API
     */
    public function testQueryBuilderFluentApi(): void
    {
        $builder = ALinqQueryBuilder::create();

        $result = $builder
            ->where('field1', '>', 10)
            ->where('field2', '<', 100)
            ->whereCustom(fn($item) => isset($item['field3']))
            ->toPredicate();

        $this->assertInstanceOf(\Closure::class, $result);
    }

    /**
     * Test query builder with nested array access
     */
    public function testQueryBuilderWithNestedArrayAccess(): void
    {
        $collection = ALinqCollection::from([
            ['user' => ['name' => 'Alice', 'age' => 25]],
            ['user' => ['name' => 'Bob', 'age' => 30]]
        ]);

        $predicate = ALinqQueryBuilder::create()
            ->whereCustom(fn($item) => $item['user']['age'] > 25)
            ->toPredicate();

        $result = $collection->where($predicate);

        $this->assertCount(1, $result->toArray());
    }

    /**
     * Test query builder returns instance for chaining
     */
    public function testQueryBuilderReturnsInstanceForChaining(): void
    {
        $builder = ALinqQueryBuilder::create();

        $result1 = $builder->where('field', '=', 'value');
        $result2 = $result1->whereCustom(fn($x) => true);

        $this->assertInstanceOf(ALinqQueryBuilder::class, $result1);
        $this->assertInstanceOf(ALinqQueryBuilder::class, $result2);
        $this->assertSame($builder, $result1);
        $this->assertSame($builder, $result2);
    }

    // ===== NEW OPERATORS (review 2026-10-08 §3.3; README §8) =====

    public function testWhereWithBetweenOperatorIsInclusive(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('age', 'between', [21, 65])
            ->toPredicate();

        $this->assertTrue($predicate(['age' => 21]));
        $this->assertTrue($predicate(['age' => 40]));
        $this->assertTrue($predicate(['age' => 65]));
        $this->assertFalse($predicate(['age' => 20]));
        $this->assertFalse($predicate(['age' => 66]));
    }

    public function testWhereWithNotBetweenOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('age', 'notBetween', [21, 65])
            ->toPredicate();

        $this->assertTrue($predicate(['age' => 20]));
        $this->assertTrue($predicate(['age' => 66]));
        $this->assertFalse($predicate(['age' => 21]));
        $this->assertFalse($predicate(['age' => 65]));
    }

    public function testBetweenRejectsARangeThatIsNotAPair(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('between expects a [min, max] array');

        ALinqQueryBuilder::create()->where('age', 'between', 21);
    }

    public function testWhereWithNotInOperator(): void
    {
        $predicate = ALinqQueryBuilder::create()
            ->where('role', 'notIn', ['admin', 'root'])
            ->toPredicate();

        $this->assertTrue($predicate(['role' => 'user']));
        $this->assertFalse($predicate(['role' => 'admin']));
    }

    public function testWhereWithIsNullAndIsNotNullOperators(): void
    {
        $isNull = ALinqQueryBuilder::create()->where('deletedAt', 'isNull')->toPredicate();
        $isNotNull = ALinqQueryBuilder::create()->where('deletedAt', 'isNotNull')->toPredicate();

        $this->assertTrue($isNull(['deletedAt' => null]));
        $this->assertTrue($isNull(['other' => 1]), 'a missing property reads as null');
        $this->assertFalse($isNull(['deletedAt' => '2026-01-01']));
        $this->assertFalse($isNull(['deletedAt' => 0]), 'isNull is strict: 0 is not null');

        $this->assertTrue($isNotNull(['deletedAt' => '2026-01-01']));
        $this->assertTrue($isNotNull(['deletedAt' => 0]));
        $this->assertFalse($isNotNull(['deletedAt' => null]));
    }

    public function testOperatorsAreCaseInsensitiveAndIgnoreSpacesAndUnderscores(): void
    {
        $row = ['age' => 30, 'role' => 'user', 'name' => 'Mr. X', 'gone' => null];

        $this->assertTrue(ALinqQueryBuilder::create()->where('age', 'BETWEEN', [21, 65])->toPredicate()($row));
        $this->assertTrue(ALinqQueryBuilder::create()->where('age', 'Between', [21, 65])->toPredicate()($row));
        $this->assertTrue(ALinqQueryBuilder::create()->where('age', 'IN', [30])->toPredicate()($row));
        $this->assertTrue(ALinqQueryBuilder::create()->where('role', 'NOT IN', ['admin'])->toPredicate()($row));
        $this->assertTrue(ALinqQueryBuilder::create()->where('role', 'not_in', ['admin'])->toPredicate()($row));
        $this->assertTrue(ALinqQueryBuilder::create()->where('gone', 'IS NULL')->toPredicate()($row));
        $this->assertTrue(ALinqQueryBuilder::create()->where('age', 'is not null')->toPredicate()($row));
        $this->assertTrue(ALinqQueryBuilder::create()->where('name', 'STARTSWITH', 'Mr')->toPredicate()($row));
        $this->assertTrue(ALinqQueryBuilder::create()->where('name', 'Contains', '. ')->toPredicate()($row));
        $this->assertTrue(ALinqQueryBuilder::create()->where('name', 'endswith', 'X')->toPredicate()($row));
    }

    public function testUnknownOperatorMessageListsTheSupportedOnes(): void
    {
        try {
            ALinqQueryBuilder::create()->where('field', 'like', '%x%');
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringStartsWith('Unknown operator: like', $e->getMessage());
            $this->assertStringContainsString('between', $e->getMessage());
            $this->assertStringContainsString('notIn', $e->getMessage());
            $this->assertStringContainsString('isNull', $e->getMessage());
        }
    }

    public function testSupportedOperatorsListsEveryOperatorTheEvaluatorAccepts(): void
    {
        $operators = ALinqQueryBuilder::supportedOperators();

        $this->assertSame(
            ['=', '==', '===', '!=', '<>', '!==', '>', '>=', '<', '<=',
             'in', 'notIn', 'between', 'notBetween', 'isNull', 'isNotNull',
             'contains', 'startsWith', 'endsWith'],
            $operators
        );

        foreach ($operators as $operator) {
            $value = match ($operator) {
                'between', 'notBetween' => [1, 2],
                'in', 'notIn' => [1],
                default => 1,
            };
            $this->assertInstanceOf(\Closure::class, ALinqQueryBuilder::operatorPredicate($operator, $value));
        }
    }

    public function testReadmeSection8ExampleRunsAsWritten(): void
    {
        $applicants = ALinqCollection::from([
            ['name' => 'Ana', 'status' => 'APPROVED', 'creditScore' => 700, 'age' => 30],
            ['name' => 'Bia', 'status' => 'APPROVED', 'creditScore' => 640, 'age' => 30],
            ['name' => 'Caio', 'status' => 'APPROVED', 'creditScore' => 720, 'age' => 70],
            ['name' => 'Davi', 'status' => 'REJECTED', 'creditScore' => 800, 'age' => 40],
        ]);

        $query = ALinqQueryBuilder::create('and')
            ->where('status', '=', 'APPROVED')
            ->where('creditScore', '>=', 650)
            ->where('age', 'between', [21, 65]);

        $predicate = $query->toPredicate();

        $approvedApplicants = $applicants->where($predicate);

        $this->assertSame(['Ana'], array_column($approvedApplicants->toArray(), 'name'));
    }

    // ===== ACCESSOR INTEGRATION (review 2026-10-08 §3.1, §3.2) =====

    public function testWhereResolvesDotNotationPaths(): void
    {
        $rows = [
            ['user' => ['name' => 'A']],
            ['user' => ['name' => 'B']],
            ['user' => []],
        ];

        $equalsA = ALinqQueryBuilder::create()->where('user.name', '=', 'A')->toPredicate();
        $this->assertSame([['user' => ['name' => 'A']]], array_values(array_filter($rows, $equalsA)));

        // Before the fix, 'user.name' was looked up as a literal key and compared null == null (false positive)
        $isNull = ALinqQueryBuilder::create()->where('user.name', '=', null)->toPredicate();
        $this->assertFalse($isNull(['user' => ['name' => 'A']]));
        $this->assertTrue($isNull(['user' => []]));
    }

    public function testWhereWorksOnEntitiesWithPrivatePropertiesAndGetters(): void
    {
        $people = ALinqCollection::from([
            new QueryBuilderPerson('Alice', 30, new QueryBuilderAddress('Paris')),
            new QueryBuilderPerson('Bob', 17, new QueryBuilderAddress('London')),
            new QueryBuilderPerson('Carol', 45, new QueryBuilderAddress('Paris')),
        ]);

        $adults = $people->where(ALinqQueryBuilder::create()->where('age', '>', 18)->toPredicate());
        $this->assertSame(['Alice', 'Carol'], $adults->select(fn($p) => $p->getName())->toArray());

        $inParis = $people->where(ALinqQueryBuilder::create()->where('address.city', '=', 'Paris')->toPredicate());
        $this->assertCount(2, $inParis->toArray());

        $between = $people->where(ALinqQueryBuilder::create()->where('age', 'between', [18, 40])->toPredicate());
        $this->assertSame(['Alice'], $between->select(fn($p) => $p->getName())->toArray());
    }
}

final class QueryBuilderPerson
{
    public function __construct(
        private string $name,
        private int $age,
        private QueryBuilderAddress $address,
    ) {}

    public function getName(): string { return $this->name; }
    public function getAge(): int { return $this->age; }
    public function getAddress(): QueryBuilderAddress { return $this->address; }
}

final class QueryBuilderAddress
{
    public function __construct(private string $city) {}
    public function getCity(): string { return $this->city; }
}
