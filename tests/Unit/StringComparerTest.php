<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Comparison\StringComparer;
use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Stringable;

/**
 * StringComparer (2.2.0, forward 047, D1-D4): ordinal(), ordinalIgnoreCase() and culture().
 *
 * The culture tests need ext-intl and are skipped without it (the local PHP of the
 * maintainer has no intl; the CI runs one leg with it and one without); the test of the
 * missing extension is skipped when intl is loaded.
 */
final class StringComparerTest extends TestCase
{
    public function testOrdinalComparesBytesAndIsNotThePhpSpaceship(): void
    {
        $ordinal = StringComparer::ordinal();

        $this->assertSame(-1, $ordinal->compare('10', '9'));   // `'10' <=> '9'` is 1: PHP compares numeric strings as numbers
        $this->assertSame(1, '10' <=> '9');
        $this->assertSame(1, $ordinal->compare('1e1', '10'));  // `'1e1' <=> '10'` is 0
        $this->assertSame(0, '1e1' <=> '10');
        $this->assertSame(0, $ordinal->compare('abc', 'abc'));
        $this->assertSame(-1, $ordinal->compare('B', 'a'));    // bytes: upper case first
        $this->assertSame(1, $ordinal->compare('Álvaro', 'Zeca'));
        $this->assertSame(1, $ordinal->compare('b', 'a'));
        $this->assertSame(-1, $ordinal->compare('a', 'ab'));
        $this->assertFalse($ordinal->equals('a', 'A'));
        $this->assertTrue($ordinal->equals('a', 'a'));
        $this->assertSame('sa', $ordinal->hash('a'));
        $this->assertNotSame($ordinal->hash('a'), $ordinal->hash('A'));
    }

    public function testCompareAnswersMinusOneZeroOrOne(): void
    {
        $ordinal = StringComparer::ordinal();
        $this->assertSame(-1, $ordinal->compare('a', 'z'));     // strcmp() may answer -25
        $this->assertSame(1, $ordinal->compare('z', 'a'));
        $this->assertSame(-1, $ordinal('a', 'z'));               // invokable = compare()
        $this->assertSame(1, StringComparer::ordinalIgnoreCase()->compare('Z', 'a'));
    }

    public function testNullComesFirstAndEqualsOnlyNull(): void
    {
        foreach ([StringComparer::ordinal(), StringComparer::ordinalIgnoreCase()] as $comparer) {
            $this->assertSame(0, $comparer->compare(null, null));
            $this->assertSame(-1, $comparer->compare(null, ''));
            $this->assertSame(1, $comparer->compare('', null));
            $this->assertSame(-1, $comparer->compare(null, 'a'));
            $this->assertTrue($comparer->equals(null, null));
            $this->assertFalse($comparer->equals(null, ''));
            $this->assertSame('n', $comparer->hash(null));
            $this->assertNotSame($comparer->hash(null), $comparer->hash('n'));  // a text never shares the hash of null
            $this->assertNotSame($comparer->hash(null), $comparer->hash(''));
        }
    }

    public function testStringableIsComparedByItsText(): void
    {
        $text = new class implements Stringable {
            public function __toString(): string
            {
                return 'Ana';
            }
        };
        $this->assertSame(0, StringComparer::ordinal()->compare($text, 'Ana'));
        $this->assertTrue(StringComparer::ordinalIgnoreCase()->equals($text, 'ANA'));
        $this->assertSame(StringComparer::ordinal()->hash('Ana'), StringComparer::ordinal()->hash($text));
    }

    public function testAnyOtherTypeThrows(): void
    {
        $comparer = StringComparer::ordinal();
        foreach (
            [
                'int' => [fn() => $comparer->compare(1, 'a'), 'int'],
                'float' => [fn() => $comparer->compare('a', 1.5), 'float'],
                'bool' => [fn() => $comparer->equals(true, 'a'), 'bool'],
                'array' => [fn() => $comparer->hash([]), 'array'],
                'stdClass' => [fn() => $comparer(new \stdClass(), 'a'), 'stdClass'],
            ] as $case => [$call, $type]
        ) {
            try {
                $call();
                $this->fail("$case must throw");
            } catch (InvalidArgumentException $e) {
                $this->assertSame("StringComparer expects strings or null, $type given", $e->getMessage(), $case);
            }
        }
    }

    public function testOrdinalIgnoreCaseFoldsUnicodeCase(): void
    {
        $comparer = StringComparer::ordinalIgnoreCase();

        $this->assertTrue($comparer->equals('ÉTICA', 'ética'));
        $this->assertTrue($comparer->equals('Ana', 'aNA'));
        $this->assertFalse($comparer->equals('Ética', 'Etica'));  // case only, accents count
        $this->assertSame($comparer->hash('ÉTICA'), $comparer->hash('ética'));
        $this->assertSame('sética', $comparer->hash('ÉTICA'));
        $this->assertSame(-1, $comparer->compare('ana', 'Bruno'));
        $this->assertSame(
            ['ana', 'Bruno', 'Zeca', 'Álvaro', 'Ética'],
            ALinqCollection::from(['Zeca', 'Álvaro', 'ana', 'Bruno', 'Ética'])->orderBy(fn($s) => $s, $comparer)->toArray()
        );
    }

    public function testOrdinalIgnoreCaseRefusesInvalidUtf8(): void
    {
        // mb_convert_case() turns an invalid byte into '?': "a\xFF" and "a?" would be equal
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('StringComparer::ordinalIgnoreCase() cannot fold a text that is not valid UTF-8');
        StringComparer::ordinalIgnoreCase()->compare("a\xFF", 'a?');
    }

    public function testOrdinalAcceptsAnyBytes(): void
    {
        $this->assertSame(1, StringComparer::ordinal()->compare("a\xFF", 'a?'));
    }

    public function testItIsAnEqualityComparerAndImmutable(): void
    {
        $this->assertInstanceOf(IALinqEqualityComparer::class, StringComparer::ordinal());
        $class = new ReflectionClass(StringComparer::class);
        $this->assertTrue($class->isFinal());
        $this->assertTrue($class->getConstructor()->isPrivate());
        foreach ($class->getProperties() as $property) {
            $this->assertTrue($property->isReadOnly(), $property->getName());
        }
        $this->assertNotSame(StringComparer::ordinal(), StringComparer::ordinal());
    }

    public function testItIsAcceptedEverywhereACallableComparerIs(): void
    {
        $comparer = StringComparer::ordinalIgnoreCase();
        $names = ['b', 'A', 'a', 'B'];

        $this->assertTrue(ALinqCollection::from($names)->contains('b', $comparer));
        $this->assertTrue(ALinqLazyCollection::from($names)->contains('b', $comparer));
        $this->assertSame(['A', 'a', 'b', 'B'], ALinqCollection::from($names)->orderByCustom($comparer)->toArray());
        $this->assertSame(['A', 'a', 'b', 'B'], ALinqCollection::from($names)->orderBy(fn() => 0)->thenBy(fn($s) => $s, $comparer)->toArray());
        $this->assertSame(['b', 'A', 'a', 'B'], ALinqCollection::from($names)->intersectWith(['a', 'B'], $comparer)->toArray());
        $this->assertSame([], ALinqCollection::from($names)->exceptWith(['A', 'b'], $comparer)->toArray());
    }

    public function testCultureWithoutIntlThrowsAtTheFactory(): void
    {
        if (extension_loaded('intl')) {
            $this->markTestSkipped('ext-intl is loaded: the LogicException of its absence cannot happen here');
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('StringComparer::culture() requires the intl extension (ext-intl).');
        StringComparer::culture('pt_BR');
    }

    #[RequiresPhpExtension('intl')]
    public function testCultureOrdersAccentedNamesAsALanguageDoes(): void
    {
        $names = ['Zeca', 'Álvaro', 'ana', 'Bruno', 'Ética'];

        $this->assertSame(['Bruno', 'Zeca', 'ana', 'Álvaro', 'Ética'], ALinqCollection::from($names)->orderBy(fn($s) => $s)->toArray()); // `<=>`: bytes
        $this->assertSame(
            ['Álvaro', 'ana', 'Bruno', 'Ética', 'Zeca'],
            ALinqCollection::from($names)->orderBy(fn($s) => $s, StringComparer::culture('pt_BR'))->toArray()
        );
        $this->assertSame(
            ['Álvaro', 'ana', 'Bruno', 'Ética', 'Zeca'],
            ALinqLazyCollection::from($names)->orderBy(fn($s) => $s, StringComparer::culture('pt_BR'))->toArray()
        );
    }

    #[RequiresPhpExtension('intl')]
    public function testCultureStrengthFollowsTheOptions(): void
    {
        $pairs = [['Ética', 'etica'], ['Ética', 'ÉTICA'], ['Ética', 'Etica'], ['a', 'A']];
        $expected = [
            'none (tertiary)' => [[], [false, false, false, false]],
            'ignoreCase (secondary)' => [['ignoreCase' => true], [false, true, false, true]],
            'ignoreAccents (primary + case level)' => [['ignoreAccents' => true], [false, false, true, false]],
            'both (primary)' => [['ignoreCase' => true, 'ignoreAccents' => true], [true, true, true, true]],
        ];
        foreach ($expected as $case => [$options, $equal]) {
            $comparer = StringComparer::culture('pt_BR', ...$options);
            foreach ($pairs as $i => [$a, $b]) {
                $this->assertSame($equal[$i], $comparer->equals($a, $b), "$case: $a = $b");
                $this->assertSame($equal[$i], $comparer->hash($a) === $comparer->hash($b), "$case: hash($a) = hash($b)");
            }
        }
    }

    #[RequiresPhpExtension('intl')]
    public function testCultureNumericOrdersDigitRunsByValue(): void
    {
        $items = ['item10', 'item2', 'item1'];
        $this->assertSame(['item1', 'item10', 'item2'], ALinqCollection::from($items)->orderBy(fn($s) => $s, StringComparer::culture('en_US'))->toArray());
        $this->assertSame(['item1', 'item2', 'item10'], ALinqCollection::from($items)->orderBy(fn($s) => $s, StringComparer::culture('en_US', numeric: true))->toArray());
    }

    #[RequiresPhpExtension('intl')]
    public function testCultureLocaleResolution(): void
    {
        // a fallback to the parent is accepted; root only when asked for
        $this->assertSame(0, StringComparer::culture('pt_XX', ignoreCase: true)->compare('A', 'a'));
        $this->assertSame(-1, StringComparer::culture('root')->compare('a', 'b'));
        $this->assertSame(-1, StringComparer::culture('de@collation=phonebook')->compare('a', 'b'));

        foreach (['xx', 'zz_ZZ', 'klingon', '', '  '] as $locale) {
            try {
                StringComparer::culture($locale);
                $this->fail("culture(\"$locale\") must throw");
            } catch (InvalidArgumentException $e) {
                $this->assertSame(sprintf('Unknown locale for StringComparer::culture(): "%s"', $locale), $e->getMessage());
            }
        }
    }

    #[RequiresPhpExtension('intl')]
    public function testCultureRefusesInvalidUtf8WithTheIcuErrorAndNotTheText(): void
    {
        $comparer = StringComparer::culture('pt_BR');
        foreach ([fn() => $comparer->compare("secret\xFF", 'b'), fn() => $comparer->hash("secret\xFF")] as $call) {
            try {
                $call();
                $this->fail('invalid UTF-8 must throw');
            } catch (InvalidArgumentException $e) {
                $this->assertSame('StringComparer::culture() cannot collate the given text (ICU error U_INVALID_CHAR_FOUND, code 10)', $e->getMessage());
                $this->assertStringNotContainsString('secret', $e->getMessage());
            }
        }
        $this->assertSame(0, $comparer->compare('a', 'a'), 'the comparer still works after an error');
    }

    #[RequiresPhpExtension('intl')]
    public function testCultureNullAndStringable(): void
    {
        $comparer = StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true);
        $this->assertSame(-1, $comparer->compare(null, ''));
        $this->assertSame('n', $comparer->hash(null));
        $this->assertTrue($comparer->equals(new class implements Stringable {
            public function __toString(): string
            {
                return 'ÉTICA';
            }
        }, 'etica'));
    }
}
