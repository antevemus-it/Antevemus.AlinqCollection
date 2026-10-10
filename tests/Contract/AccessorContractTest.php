<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Contract;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqQueryBuilder;
use Antevemus\ALinq\Helpers\ALinqPropertyAccess;
use PHPUnit\Framework\TestCase;

/**
 * RN-19 (forward 015, 1.3.0): ALinqPropertyAccess is the only property resolution of the
 * library. The five public entry points that read a property are driven through the same
 * fixture of cases (13 in REVISAO-2026-10-08 §3.15, bug P4CS; 14 since 1.4.1) and must agree
 * with ALinqPropertyAccess::getValue() case by case.
 *
 * 1.4.0 (forward 021, RN-07): the resolution order gained a last step, a private or
 * protected property declared by the class or an ancestor, so 'private without getter'
 * resolved to its value.
 *
 * 1.4.1 (security): that last step is opt-in with #[Specifiable], so 'private without getter'
 * is back to null (a name from a request must not read a private field) and the new case
 * 'private without getter, #[Specifiable]' resolves to its value; the other 12 cases are
 * unchanged.
 */
final class AccessorContractTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string, mixed}> case => [target, property, expected]
     */
    public static function cases(): array
    {
        $nested = new class {
            public object $a;
            public function __construct()
            {
                $this->a = (object)['b' => ['c' => 'deep']];
            }
        };

        return [
            'private + getter'               => [new C\PrivateWithGetter(), 'name', 'priv'],
            // 1.4.1: an unmarked non-public property is not readable by name (1.4.0 read it)
            'private without getter'         => [new C\PrivateWithoutGetter(), 'name', null],
            // 1.4.1: the author opted in, so the last resort reads it (as 1.4.0 did for any)
            'private without getter, #[Specifiable]' => [new C\SpecifiablePrivateWithoutGetter(), 'name', 'priv-specifiable'],
            'public $active + isActive()'    => [new C\PublicActiveWithIsMethod(), 'active', false],
            'public $status + getStatus()'   => [new C\PublicStatusWithGetter(), 'status', 'getter'],
            '__get + __isset'                => [new C\MagicGetWithIsset(), 'name', 'magic:name'],
            'ArrayAccess'                    => [new C\ArrayAccessFixture(['name' => 'aa']), 'name', 'aa'],
            'method named like the property' => [new C\MethodNamedLikeProperty(), 'name', 'method-name'],
            'typed, uninitialized'           => [new C\UninitializedTyped(), 'age', null],
            'public static'                  => [new C\PublicStaticProperty(), 'name', 'static'],
            'has* vs public property'        => [new C\PublicValueWithHasMethod(), 'value', 'has'],
            'private getter only'            => [new C\PrivateGetterOnly(), 'name', null],
            'dot path a.b.c'                 => [$nested, 'a.b.c', 'deep'],
            'literal key a.b is a path'      => [['a.b' => 'literal', 'a' => ['b' => 'nested']], 'a.b', 'nested'],
        ];
    }

    /**
     * Every entry point, as `fn($target): mixed`, keyed by its public name.
     *
     * @return array<string, callable(string): \Closure>
     */
    private static function entryPoints(): array
    {
        return [
            'ALinqPropertyAccess::getPropertyAccessor' => fn(string $p) => ALinqPropertyAccess::getPropertyAccessor($p),
            'ALinqPropertyAccess::getNestedPropertyAccessor' => fn(string $p) => ALinqPropertyAccess::getNestedPropertyAccessor($p),
            'ALinqCollection::createPropertySelector' => fn(string $p) => ALinqCollection::empty()->createPropertySelector($p),
            'ALinqPropertyAccess::createPropertyComparer' => fn(string $p) => static function (mixed $target) use ($p): mixed {
                // the comparer reads both sides through the accessor; comparing a property with
                // itself must be true for every case, including the null ones
                return ALinqPropertyAccess::createPropertyComparer($p, $p)($target) ? ALinqPropertyAccess::getValue($target, $p) : '<<comparer disagreed>>';
            },
            'ALinqQueryBuilder::where' => fn(string $p) => static function (mixed $target) use ($p): mixed {
                $expected = ALinqPropertyAccess::getValue($target, $p);
                $predicate = ALinqQueryBuilder::create()->where($p, '===', $expected)->toPredicate();
                return $predicate($target) ? $expected : '<<builder disagreed>>';
            },
        ];
    }

    public function testEveryEntryPointResolvesEveryCaseLikeGetValue(): void
    {
        $divergences = [];
        foreach (self::cases() as $case => [$target, $property, $expected]) {
            $this->assertSame($expected, ALinqPropertyAccess::getValue($target, $property), "getValue: $case");

            foreach (self::entryPoints() as $name => $factory) {
                $actual = $factory($property)($target);
                if ($actual !== $expected) {
                    $divergences[] = sprintf('%s [%s]: expected %s, got %s', $name, $case, var_export($expected, true), var_export($actual, true));
                }
            }
        }

        $this->assertSame([], $divergences, "entry points diverging from ALinqPropertyAccess::getValue():\n" . implode("\n", $divergences));
    }

    public function testOrderByAndWhereOnTheCollectionUseTheSameAccessor(): void
    {
        $people = ALinqCollection::from([
            new C\Person('Bob', 17),
            new C\Person('Alice', 30),
        ]);

        $ordered = $people->orderBy($people->createPropertySelector('name'))->select(fn(C\Person $p) => $p->getName())->toArray();
        $this->assertSame(['Alice', 'Bob'], $ordered);

        $adults = $people->where(ALinqQueryBuilder::create()->where('age', '>=', 18)->toPredicate());
        $this->assertSame(['Alice'], $adults->select(fn(C\Person $p) => $p->getName())->toArray());

        $this->assertTrue(ALinqPropertyAccess::hasProperty(new C\Person('x', 1), 'name'));
        $this->assertFalse(ALinqPropertyAccess::hasProperty(new C\Person('x', 1), 'salary'));
    }

    public function testOnlyThePropertyAccessHelperReadsProperties(): void
    {
        // Every other source file delegates: no direct property reflection, property_exists()
        // or method_exists() lookups on user objects outside ALinqPropertyAccess.
        $offenders = [];
        foreach (glob(__DIR__ . '/../../src/{*.php,Traits/*.php,Helpers/*.php}', GLOB_BRACE) as $file) {
            if (str_ends_with($file, 'ALinqPropertyAccess.php')) {
                continue;
            }
            $code = file_get_contents($file);
            if (preg_match('/\b(property_exists|ReflectionProperty|get_object_vars)\s*\(/', $code)) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'property resolution must live in ALinqPropertyAccess only');
    }
}

namespace Antevemus\ALinq\Tests\Contract\C;

use Antevemus\ALinq\Attributes\Specifiable;

final class PrivateWithGetter
{
    private string $name = 'priv';
    public function getName(): string { return $this->name; }
}

final class PrivateWithoutGetter
{
    private string $name = 'priv-no-getter';
    public function describe(): string { return $this->name; }
}

final class SpecifiablePrivateWithoutGetter
{
    #[Specifiable]
    private string $name = 'priv-specifiable';
    public function describe(): string { return $this->name; }
}

final class PublicActiveWithIsMethod
{
    public bool $active = true;
    public function isActive(): bool { return false; }
}

final class PublicStatusWithGetter
{
    public string $status = 'prop';
    public function getStatus(): string { return 'getter'; }
}

final class PublicValueWithHasMethod
{
    public string $value = 'v';
    public function hasValue(): string { return 'has'; }
}

final class MagicGetWithIsset
{
    public function __get(string $name): string { return "magic:$name"; }
    public function __isset(string $name): bool { return true; }
}

final class ArrayAccessFixture implements \ArrayAccess
{
    public function __construct(private array $data) {}
    public function offsetExists(mixed $offset): bool { return isset($this->data[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->data[$offset]; }
    public function offsetSet(mixed $offset, mixed $value): void { $this->data[$offset] = $value; }
    public function offsetUnset(mixed $offset): void { unset($this->data[$offset]); }
}

final class MethodNamedLikeProperty
{
    public function name(): string { return 'method-name'; }
}

final class UninitializedTyped
{
    public int $age;
}

final class PublicStaticProperty
{
    public static string $name = 'static';
}

final class PrivateGetterOnly
{
    private function getName(): string { return 'private-getter'; }
}

final class Person
{
    public function __construct(private string $name, private int $age) {}
    public function getName(): string { return $this->name; }
    public function getAge(): int { return $this->age; }
}
