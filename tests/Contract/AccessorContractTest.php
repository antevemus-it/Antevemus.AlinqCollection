<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Contract;

use Antevemus\ADeepPropertyAccess\PropertyAccess;
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\ALinqQueryBuilder;
use Antevemus\ALinq\Helpers\ALinqPropertyAccess;
use ArrayIterator;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The property accessor contract of ALinq.
 *
 * RN-19 (forward 015, 1.3.0): one property resolution for the whole library; every public entry
 * point that reads a property answers what the accessor answers. 1.4.0 added the non-public last
 * resort; 1.4.1 made it opt-in with #[Specifiable].
 *
 * 2.0.0 (forward 033, D14, ROADMAP milestone 11): the resolution is no longer ALinq's own copy. It
 * lives in antevemus/adeep-property-access (`PropertyAccess`), shared with ASpecification, and the
 * contract battery with the expected answers written down lives in that package. What this test
 * proves on the ALinq side:
 *
 * - the deprecated facade `ALinqPropertyAccess` answers exactly what the package answers, case by
 *   case, without raising any notice, and `createPropertyComparer()` reads through the same accessor;
 * - every internal entry point (the query builder, `createPropertySelector()`, `whereIn()` on both
 *   collections) answers what the package answers;
 * - the 14 cases of the ALinq contract (1.3.0 to 1.5.1) keep their written answers;
 * - the answers that changed from 1.5.1 to 2.0.0 reach the internal readers;
 * - there is no local copy of the resolution in src/ (the facade only delegates, nobody calls the
 *   facade internally, nobody resolves a property by reflection or builds a getter name).
 */
final class AccessorContractTest extends TestCase
{
    /**
     * The 14 cases of the ALinq contract (13 in REVISAO-2026-10-08 §3.15, bug P4CS; 14 since 1.4.1),
     * with the answer written down. Unchanged by 2.0.0.
     *
     * @return array<string, array{mixed, string, mixed}> case => [target, property, expected]
     */
    public static function alinqCases(): array
    {
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
            'dot path a.b.c'                 => [new C\NestedHolder(), 'a.b.c', 'deep'],
            'literal key a.b is a path'      => [['a.b' => 'literal', 'a' => ['b' => 'nested']], 'a.b', 'nested'],
        ];
    }

    /**
     * The cases on which the facade and the entry points are compared with the package: the 14 of
     * the ALinq contract plus the edge cases of the package battery (antevemus/adeep-property-access,
     * tests/Contract/AccessorContractTest.php), whose expected answers are written there.
     *
     * @return array<string, array{mixed, string}> case => [target, path]
     */
    public static function packageCases(): array
    {
        $cases = [];
        foreach (self::alinqCases() as $case => [$target, $path]) {
            $cases[$case] = [$target, $path];
        }

        $dynamic = new C\Dynamic();
        $dynamic->name = 'dyn';

        return $cases + [
            '#[Specifiable] of ASpecification'  => [new C\SpecifiableByASpecName(), 'name'],
            '#[Specifiable] of the package'     => [new C\SpecifiableByPackage(), 'name'],
            'class-level #[Specifiable]'        => [new C\SpecifiableClass(), 'name'],
            '#[Specifiable] in the parent'      => [new C\ChildOfSpecifiable(), 'name'],
            '__get without __isset'             => [new C\MagicGetWithoutIsset(), 'name'],
            'ArrayAccess, key missing'          => [new C\ArrayAccessFixture([]), 'name'],
            'getter with a required parameter'  => [new C\GetterWithRequiredParameter(), 'name'],
            'required parameter, then public property' => [new C\GetterWithRequiredParameterAndPublicProperty(), 'name'],
            'required parameter, then isX()'    => [new C\GetterWithRequiredParameterAndIsMethod(), 'ready'],
            'optional parameter is a getter'    => [new C\GetterWithOptionalParameter(), 'name'],
            'variadic parameter is a getter'    => [new C\GetterWithVariadicParameter(), 'name'],
            'public null + getter'              => [new C\PublicNullWithGetter(), 'name'],
            'public null'                       => [new C\PublicNull(), 'name'],
            'dynamic property'                  => [$dynamic, 'name'],
            'stdClass'                          => [(object)['name' => 'std'], 'name'],
            'stdClass, property holding null'   => [(object)['name' => null], 'name'],
            'array, key holding null'           => [['name' => null], 'name'],
            'array, key missing'                => [[], 'name'],
            'literal key a.b alone'             => [['a.b' => 'literal'], 'a.b'],
            'path through null'                 => [['a' => null], 'a.b'],
            'path through a scalar'             => [['a' => 5], 'a.b'],
            'path ending on a key holding null' => [['a' => ['b' => null]], 'a.b'],
            'numeric key'                       => [[10, 20], '1'],
            'empty name on an array'            => [['' => 'empty'], ''],
            'empty name on an object'           => [new C\Dynamic(), ''],
            'empty name and a get() method'     => [new C\OnlyGetMethod(), ''],
            'scalar target'                     => ['text', 'name'],
            'null target'                       => [null, 'name'],
            'getter found case-insensitively'   => [new C\PrivateWithGetter(), 'Name'],
            'getter chain'                      => [new C\Customer('Ana', new C\Address('Lisbon')), 'address.city'],
            'ArrayObject, key present'          => [new ArrayObject(['name' => 'v']), 'name'],
            'ArrayObject, key holding null'     => [new ArrayObject(['name' => null]), 'name'],
            'ArrayObject, key missing'          => [new ArrayObject([]), 'name'],
            'ArrayIterator, key holding null'   => [new ArrayIterator(['name' => null]), 'name'],
            'path ending on an ArrayObject key holding null' => [['box' => new ArrayObject(['name' => null])], 'box.name'],
            'ArrayAccess with a public property' => [new C\ArrayAccessWithPublicProperty(), 'name'],
            'private getter + __call()'         => [new C\PrivateGetterWithCall(), 'name'],
            '__construct as x()'                => [new C\ConstructorCounter(), '__construct'],
            'unset public property + __get'     => [new C\UnsetUntypedWithGet(), 'name'],
        ];
    }

    /**
     * Every internal entry point that reads a property, as `fn(string $path): Closure(mixed): mixed`.
     * Entry points that answer a boolean return the value read when they agree with the package and
     * a marker when they do not.
     *
     * @return array<string, callable(string): \Closure>
     */
    private static function entryPoints(): array
    {
        return [
            'ALinqCollection::createPropertySelector' => fn(string $p) => ALinqCollection::empty()->createPropertySelector($p),
            'ALinqPropertyAccess::createPropertyComparer' => fn(string $p) => static function (mixed $target) use ($p): mixed {
                // the comparer reads both sides through the accessor; a property compared with
                // itself is equal for every case, including the null ones
                return ALinqPropertyAccess::createPropertyComparer($p, $p)($target) ? PropertyAccess::getValue($target, $p) : '<<comparer disagreed>>';
            },
            'ALinqQueryBuilder::where' => fn(string $p) => static function (mixed $target) use ($p): mixed {
                $expected = PropertyAccess::getValue($target, $p);
                $predicate = ALinqQueryBuilder::create()->where($p, '===', $expected)->toPredicate();
                return $predicate($target) ? $expected : '<<builder disagreed>>';
            },
            'ALinqCollection::whereIn' => fn(string $p) => static function (mixed $target) use ($p): mixed {
                $expected = PropertyAccess::getValue($target, $p);
                return ALinqCollection::from([$target])->whereIn($p, [$expected])->count() === 1 ? $expected : '<<whereIn disagreed>>';
            },
            'ALinqLazyCollection::whereIn' => fn(string $p) => static function (mixed $target) use ($p): mixed {
                $expected = PropertyAccess::getValue($target, $p);
                return ALinqLazyCollection::from([$target])->whereIn($p, [$expected])->count() === 1 ? $expected : '<<lazy whereIn disagreed>>';
            },
        ];
    }

    /**
     * D14: the deprecated facade returns exactly what the package returns (getValue, hasProperty and
     * both accessors), case by case, and raises no notice (it sits on the hot path).
     */
    public function testFacadeAnswersExactlyAsThePackage(): void
    {
        $notices = [];
        set_error_handler(static function (int $level, string $message) use (&$notices): bool {
            $notices[] = $message;
            return true;
        });
        try {
            foreach (self::packageCases() as $case => [$target, $path]) {
                $value = PropertyAccess::getValue($target, $path);
                $this->assertSame($value, ALinqPropertyAccess::getValue($target, $path), "getValue: $case");
                $this->assertSame(PropertyAccess::hasProperty($target, $path), ALinqPropertyAccess::hasProperty($target, $path), "hasProperty: $case");
                $this->assertSame($value, ALinqPropertyAccess::getPropertyAccessor($path)($target), "getPropertyAccessor: $case");
                $this->assertSame($value, ALinqPropertyAccess::getNestedPropertyAccessor($path)($target), "getNestedPropertyAccessor: $case");
            }
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $notices, 'the facade raises no runtime notice');
    }

    /**
     * Every internal entry point answers what the package answers, on every case.
     */
    public function testEveryEntryPointResolvesEveryCaseLikeThePackage(): void
    {
        $divergences = [];
        foreach (self::packageCases() as $case => [$target, $path]) {
            $expected = PropertyAccess::getValue($target, $path);
            foreach (self::entryPoints() as $name => $factory) {
                $actual = $factory($path)($target);
                if ($actual !== $expected) {
                    $divergences[] = sprintf('%s [%s]: expected %s, got %s', $name, $case, var_export($expected, true), var_export($actual, true));
                }
            }
        }

        $this->assertSame([], $divergences, "entry points diverging from PropertyAccess::getValue():\n" . implode("\n", $divergences));
    }

    /**
     * The 14 cases of the ALinq contract keep the answers written in 1.3.0 to 1.5.1.
     */
    public function testTheAlinqContractCasesKeepTheirAnswers(): void
    {
        foreach (self::alinqCases() as $case => [$target, $path, $expected]) {
            $this->assertSame($expected, PropertyAccess::getValue($target, $path), "package: $case");
            $this->assertSame($expected, ALinqCollection::empty()->createPropertySelector($path)($target), "selector: $case");
        }
    }

    /**
     * The answers that changed from 1.5.1 to 2.0.0 (CHANGELOG 2.0.0, Changed), seen through the
     * internal readers. The 1.5.1 answer is in the message of each assertion.
     */
    public function testAnswersThatChangedIn200ReachTheInternalReaders(): void
    {
        $selector = static fn(string $p): \Closure => ALinqCollection::empty()->createPropertySelector($p);
        $builder = static fn(string $p, string $op, mixed $v): \Closure => ALinqQueryBuilder::create()->where($p, $op, $v)->toPredicate();

        // A public method with a required parameter is not a getter (1.5.1: ArgumentCountError from
        // every reader, while hasProperty() answered true); the next step answers
        $this->assertNull($selector('name')(new C\GetterWithRequiredParameter()), '1.5.1: ArgumentCountError');
        $this->assertFalse(PropertyAccess::hasProperty(new C\GetterWithRequiredParameter(), 'name'), '1.5.1: true');
        $this->assertTrue($builder('name', '===', 'prop')(new C\GetterWithRequiredParameterAndPublicProperty()), '1.5.1: ArgumentCountError; the public property answers');
        $this->assertSame(1, ALinqCollection::from([new C\GetterWithRequiredParameterAndIsMethod()])->whereIn('ready', [true])->count(), '1.5.1: ArgumentCountError; isReady() answers');
        $this->assertSame('optional', $selector('name')(new C\GetterWithOptionalParameter()), 'an optional parameter still makes a getter');

        // __call() is never reached through a private getter (1.5.1: '__call:getName', hasProperty true)
        $call = new C\PrivateGetterWithCall();
        $this->assertNull($selector('name')($call), "1.5.1: '__call:getName'");
        $this->assertFalse(PropertyAccess::hasProperty($call, 'name'), '1.5.1: true');
        $this->assertSame(0, $call->calls, '__call() is never called');

        // A name starting with __ is never called (1.5.1: reading '__construct' ran the constructor again)
        $counter = new C\ConstructorCounter();
        $before = C\ConstructorCounter::$constructed;
        $this->assertNull($selector('__construct')($counter));
        $this->assertFalse(PropertyAccess::hasProperty($counter, '__construct'), '1.5.1: true');
        $this->assertSame($before, C\ConstructorCounter::$constructed, 'the constructor did not run again');

        // An empty name names no method (1.5.1: called the public get(), and hasProperty() answered true)
        $this->assertNull($selector('')(new C\OnlyGetMethod()), "1.5.1: 'get()'");
        $this->assertFalse(PropertyAccess::hasProperty(new C\OnlyGetMethod(), ''), '1.5.1: true');

        // A key of an ArrayObject/ArrayIterator holding null exists, as in an array (1.5.1: false)
        $this->assertTrue(PropertyAccess::hasProperty(new ArrayObject(['name' => null]), 'name'), '1.5.1: false');
        $this->assertTrue(PropertyAccess::hasProperty(new ArrayIterator(['name' => null]), 'name'), '1.5.1: false');
        $this->assertTrue(PropertyAccess::hasProperty(['box' => new ArrayObject(['name' => null])], 'box.name'), '1.5.1: false');
        $this->assertFalse(PropertyAccess::hasProperty(new ArrayObject([]), 'name'), 'a missing key still does not exist');

        // #[Antevemus\ADeepPropertyAccess\Attributes\Specifiable] is the same marker (1.5.1: null/false)
        $this->assertSame('pkg', $selector('name')(new C\SpecifiableByPackage()), '1.5.1: null');
        $this->assertSame(1, ALinqLazyCollection::from([new C\SpecifiableByPackage()])->whereIn('name', ['pkg'])->count(), '1.5.1: 0');

        // Answers ALinq already gave in 1.5.1 and the package settled on (ASpecification changed)
        $this->assertNull($selector('')(new C\Dynamic()), 'empty name on an object');
        $this->assertFalse(PropertyAccess::hasProperty(new C\ArrayAccessWithPublicProperty(), 'name'), 'ArrayAccess answers through its offsets only');
        $this->assertNull($selector('name')(new C\UnsetUntypedWithGet()), '__get() without __isset() is not read');
    }

    /**
     * D14: getValue(), hasProperty(), getPropertyAccessor() and getNestedPropertyAccessor() are
     * @deprecated 2.0.0 (removed in 3.0.0); createPropertyComparer() is ALinq's and is not; the public
     * API is the one of 1.x.
     */
    public function testFacadeIsDeprecatedExceptTheComparer(): void
    {
        $class = new ReflectionClass(ALinqPropertyAccess::class);

        $this->assertSame(
            ['getValue', 'hasProperty', 'getPropertyAccessor', 'getNestedPropertyAccessor', 'createPropertyComparer'],
            array_map(static fn(ReflectionMethod $m): string => $m->getName(), $class->getMethods(ReflectionMethod::IS_PUBLIC)),
            'same public API as 1.x'
        );
        $this->assertFalse($class->isFinal(), 'not made final: extending it was allowed in 1.x');

        foreach (['getValue', 'hasProperty', 'getPropertyAccessor', 'getNestedPropertyAccessor'] as $method) {
            $doc = (string) $class->getMethod($method)->getDocComment();
            $this->assertStringContainsString('@deprecated 2.0.0 Removed in 3.0.0', $doc, "$method() is deprecated");
            $this->assertStringContainsString('PropertyAccess::', $doc, "$method() names its replacement");
        }
        $this->assertStringNotContainsString('@deprecated', (string) $class->getMethod('createPropertyComparer')->getDocComment());
    }

    /**
     * D14: ALinq has no copy of the property resolution. The facade only delegates; no file of src/
     * calls the facade; the internal readers use the package; nobody in src/ resolves a property by
     * reflection, property_exists(), method_exists() or get_object_vars(), nor builds a getter name.
     */
    public function testNoLocalCopyOfThePropertyResolution(): void
    {
        $src = dirname(__DIR__, 2) . '/src';
        $code = static function (string $file): string {
            $out = '';
            foreach (token_get_all((string) file_get_contents($file)) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $out .= is_array($token) ? $token[1] : $token;
            }
            return $out;
        };

        // 1. The facade: one return per method, each delegating to the package, nothing resolved locally
        $facade = $code($src . '/Helpers/ALinqPropertyAccess.php');
        foreach (['method_exists', 'property_exists', 'is_callable', 'ucfirst', 'Reflection', 'isset(', 'ArrayAccess', 'explode(', 'get_object_vars', '->{', 'self::', 'static::'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $facade, "the facade resolves nothing itself ($forbidden)");
        }
        $this->assertSame(5, substr_count($facade, 'return '), 'one return per method');
        foreach ([
            'return PropertyAccess::getValue($item, $property);',
            'return PropertyAccess::hasProperty($item, $property);',
            'return PropertyAccess::accessor($property);',
            'return PropertyAccess::accessor($path);',
            '$accessor1 = PropertyAccess::accessor($property1);',
            '$accessor2 = PropertyAccess::accessor($property2);',
        ] as $delegation) {
            $this->assertStringContainsString($delegation, $facade, "delegates: $delegation");
        }

        // 2. Internal readers use the package
        foreach (['ALinqQueryBuilder.php', 'Helpers/ALinqContract.php', 'Traits/UtilityOperations.php'] as $reader) {
            $this->assertStringContainsString('use Antevemus\ADeepPropertyAccess\PropertyAccess;', $code($src . '/' . $reader), "$reader uses the package");
        }

        // 3. Nobody else resolves a property
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($src) + 1);
            $body = $code($file->getPathname());
            if ($relative !== 'Helpers/ALinqPropertyAccess.php' && str_contains($body, 'ALinqPropertyAccess::')) {
                $offenders[] = "$relative calls the facade";
            }
            if (preg_match('/\b(property_exists|method_exists|get_object_vars)\s*\(|\bReflectionProperty\b|[\'"](get|is|has)[\'"]\s*\.\s*ucfirst\s*\(/i', $body, $match)) {
                $offenders[] = "$relative: {$match[0]}";
            }
        }

        $this->assertSame([], $offenders, "property resolution must live in antevemus/adeep-property-access only:\n" . implode("\n", $offenders));
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

        $this->assertTrue(PropertyAccess::hasProperty(new C\Person('x', 1), 'name'));
        $this->assertFalse(PropertyAccess::hasProperty(new C\Person('x', 1), 'salary'));
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

/** The attribute of ASpecification, recognized by name (its class is not loaded here). */
final class SpecifiableByASpecName
{
    #[\Antevemus\ASpecification\Attributes\Specifiable]
    private string $name = 'aspec';
    public function describe(): string { return $this->name; }
}

final class SpecifiableByPackage
{
    #[\Antevemus\ADeepPropertyAccess\Attributes\Specifiable]
    private string $name = 'pkg';
    public function describe(): string { return $this->name; }
}

#[Specifiable]
final class SpecifiableClass
{
    private string $name = 'class-level';
    public function describe(): string { return $this->name; }
}

class SpecifiableParent
{
    #[Specifiable]
    private string $name = 'parent';
    public function describe(): string { return $this->name; }
}

final class ChildOfSpecifiable extends SpecifiableParent
{
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

final class MagicGetWithoutIsset
{
    public function __get(string $name): string { return "magic:$name"; }
}

final class ArrayAccessFixture implements \ArrayAccess
{
    public function __construct(private array $data) {}
    public function offsetExists(mixed $offset): bool { return isset($this->data[$offset]); }
    public function offsetGet(mixed $offset): mixed { return $this->data[$offset]; }
    public function offsetSet(mixed $offset, mixed $value): void { $this->data[$offset] = $value; }
    public function offsetUnset(mixed $offset): void { unset($this->data[$offset]); }
}

final class ArrayAccessWithPublicProperty implements \ArrayAccess
{
    public string $name = 'prop';
    public function offsetExists(mixed $offset): bool { return false; }
    public function offsetGet(mixed $offset): mixed { return null; }
    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
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

final class PrivateGetterWithCall
{
    public int $calls = 0;
    private function getName(): string { return 'private-getter'; }
    public function __call(string $method, array $arguments): string
    {
        $this->calls++;
        return "__call:$method";
    }
}

final class ConstructorCounter
{
    public static int $constructed = 0;
    public function __construct() { self::$constructed++; }
}

final class GetterWithRequiredParameter
{
    public function getName(string $x): string { return $x; }
}

final class GetterWithRequiredParameterAndPublicProperty
{
    public string $name = 'prop';
    public function getName(string $x): string { return $x; }
}

final class GetterWithRequiredParameterAndIsMethod
{
    public function getReady(int $timeout): bool { return false; }
    public function isReady(): bool { return true; }
}

final class GetterWithOptionalParameter
{
    public function getName(string $x = 'optional'): string { return $x; }
}

final class GetterWithVariadicParameter
{
    public function getName(string ...$parts): string { return 'variadic:' . count($parts); }
}

final class OnlyGetMethod
{
    public function get(): string { return 'get()'; }
}

final class PublicNullWithGetter
{
    public ?string $name = null;
    public function getName(): string { return 'getter'; }
}

final class PublicNull
{
    public ?string $name = null;
}

final class UnsetUntypedWithGet
{
    public $name = 'x';
    public function __construct() { unset($this->name); }
    public function __get(string $name): string { return "magic:$name"; }
}

#[\AllowDynamicProperties]
final class Dynamic
{
}

final class NestedHolder
{
    public object $a;
    public function __construct() { $this->a = (object)['b' => ['c' => 'deep']]; }
}

final class Address
{
    public function __construct(private string $city) {}
    public function getCity(): string { return $this->city; }
}

final class Customer
{
    public function __construct(private string $name, private Address $address) {}
    public function getName(): string { return $this->name; }
    public function getAddress(): Address { return $this->address; }
}

final class Person
{
    public function __construct(private string $name, private int $age) {}
    public function getName(): string { return $this->name; }
    public function getAge(): int { return $this->age; }
}
