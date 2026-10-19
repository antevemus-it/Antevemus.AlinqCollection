<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Comparison;

use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;
use Collator;
use InvalidArgumentException;
use Locale;
use LogicException;
use Stringable;

/**
 * StringComparer - Text comparison by bytes, ignoring case, or by the rules of a culture
 *
 * Since 2.2.0 (forward 047, D1-D4): the `StringComparer` of .NET for ALinq. Three factories:
 *
 * - ordinal(): byte by byte (`strcmp`); equality is `===`. It is NOT the default comparison
 *   of the library: without a comparer the operators use PHP's `<=>`, which compares numeric
 *   strings as numbers (`'10' <=> '9'` is 1, `'1e1' <=> '10'` is 0); ordinal() puts `'10'`
 *   before `'9'` and keeps `'1e1'` and `'10'` apart. Needs no extension.
 * - ordinalIgnoreCase(): simple Unicode case folding (`mb_convert_case(..., MB_CASE_FOLD_SIMPLE)`)
 *   and then ordinal. .NET upper-cases with the invariant culture instead: equality agrees in
 *   the usual cases, the order may differ in rare ones. Requires ext-mbstring.
 * - culture($locale, $ignoreCase, $ignoreAccents, $numeric): an ICU `Collator`, so
 *   `culture('pt_BR')` orders `Álvaro` before `Bruno` and `ignoreAccents: true` makes `Ética`
 *   equal to `Etica`. Strength: no option = tertiary (accents and case count); ignoreCase =
 *   secondary; ignoreAccents = primary with the case level on; both = primary. `numeric`
 *   orders digit runs by value (`item2` before `item10`), the `NumericOrdering` of .NET 10.
 *   Requires ext-intl (LogicException at the factory otherwise, as fromHttpPages() without
 *   ext-curl). A locale that ICU resolves to its root rules without having been asked for
 *   `root` throws InvalidArgumentException; a fallback to the parent (`pt_XX` → `pt`) is
 *   accepted.
 *
 * Values: a string, a Stringable (by its text) or null; null comes before every text and
 * equals only null (as `StringComparer.Compare` in .NET); anything else throws
 * InvalidArgumentException. In all three: `equals($a, $b)` ⇔ `compare($a, $b) === 0`, and
 * equal values have the same hash() (IALinqEqualityComparer), so one comparer serves both
 * the ordering operators (it is invokable as `fn($a, $b): int`: orderBy, thenBy, min, max,
 * minBy, maxBy, orderByCustom, contains, intersectWith, exceptWith) and distinct()/distinctBy().
 *
 * Immutable: the factories return a new comparer whose configuration never changes.
 *
 * @version    2.2.0
 * @package    antevemus
 * @subpackage alinq.comparison
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class StringComparer implements IALinqEqualityComparer
{
    private const ORDINAL = 'ordinal';
    private const ORDINAL_IGNORE_CASE = 'ordinalIgnoreCase';
    private const CULTURE = 'culture';

    private function __construct(
        private readonly string $kind,
        private readonly ?Collator $collator = null
    ) {
    }

    /**
     * Byte-by-byte comparison (`StringComparer.Ordinal`); needs no extension.
     */
    public static function ordinal(): self
    {
        return new self(self::ORDINAL);
    }

    /**
     * Ordinal comparison after simple Unicode case folding (`StringComparer.OrdinalIgnoreCase`).
     *
     * @throws LogicException When ext-mbstring is not loaded
     */
    public static function ordinalIgnoreCase(): self
    {
        if (!function_exists('mb_convert_case')) {
            throw new LogicException('StringComparer::ordinalIgnoreCase() requires the mbstring extension (ext-mbstring).');
        }

        return new self(self::ORDINAL_IGNORE_CASE);
    }

    /**
     * Comparison by the collation rules of a culture (`StringComparer.Create(CultureInfo, CompareOptions)`),
     * through an ICU Collator.
     *
     * @param string $locale An ICU locale such as 'pt_BR', 'en_US', 'de@collation=phonebook' or 'root'
     * @param bool $ignoreCase `a` equals `A` (CompareOptions.IgnoreCase)
     * @param bool $ignoreAccents `é` equals `e` (CompareOptions.IgnoreNonSpace)
     * @param bool $numeric digit runs compare by value, `2` before `10` (CompareOptions.NumericOrdering)
     * @throws LogicException When ext-intl is not loaded
     * @throws InvalidArgumentException When ICU does not know the locale (it would fall back to root)
     */
    public static function culture(string $locale, bool $ignoreCase = false, bool $ignoreAccents = false, bool $numeric = false): self
    {
        if (!class_exists(Collator::class)) {
            throw new LogicException('StringComparer::culture() requires the intl extension (ext-intl).');
        }

        $collator = new Collator($locale);
        // '' is ICU's default locale (whatever the server is set to), not a choice: refused too.
        $askedForRoot = strcasecmp($locale, 'root') === 0;
        if ($locale === '' || (!$askedForRoot && $collator->getLocale(Locale::VALID_LOCALE) === 'root')) {
            throw new InvalidArgumentException(sprintf('Unknown locale for StringComparer::culture(): "%s"', $locale));
        }

        $collator->setStrength(match (true) {
            $ignoreAccents => Collator::PRIMARY,
            $ignoreCase => Collator::SECONDARY,
            default => Collator::TERTIARY,
        });
        if ($ignoreAccents && !$ignoreCase) {
            // Primary strength ignores accents and case; the case level brings case back.
            $collator->setAttribute(Collator::CASE_LEVEL, Collator::ON);
        }
        if ($numeric) {
            $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
        }

        return new self(self::CULTURE, $collator);
    }

    /**
     * Compares two values: -1, 0 or 1. Null comes before every text and equals only null.
     *
     * @param string|Stringable|null $a
     * @param string|Stringable|null $b
     * @throws InvalidArgumentException For a value that is not a string, a Stringable or null,
     *                                  or a text that cannot be compared (invalid UTF-8)
     */
    public function compare(mixed $a, mixed $b): int
    {
        $a = self::text($a);
        $b = self::text($b);
        if ($a === null || $b === null) {
            return ($b === null ? 1 : 0) - ($a === null ? 1 : 0);
        }

        return match ($this->kind) {
            self::ORDINAL => strcmp($a, $b) <=> 0,
            self::ORDINAL_IGNORE_CASE => strcmp(self::fold($a), self::fold($b)) <=> 0,
            default => $this->collate($a, $b),
        };
    }

    /**
     * Whether the two values are equal under this comparer (compare() === 0).
     *
     * @throws InvalidArgumentException As compare()
     */
    public function equals(mixed $a, mixed $b): bool
    {
        return $this->compare($a, $b) === 0;
    }

    /**
     * A hash compatible with equals(): `n` for null, `s` followed by the text (ordinal), the
     * folded text (ordinalIgnoreCase) or the ICU sort key (culture). Opaque: not for display.
     *
     * @throws InvalidArgumentException As compare()
     */
    public function hash(mixed $value): string
    {
        $text = self::text($value);
        if ($text === null) {
            return 'n';
        }

        return 's' . match ($this->kind) {
            self::ORDINAL => $text,
            self::ORDINAL_IGNORE_CASE => self::fold($text),
            default => $this->sortKey($text),
        };
    }

    /**
     * The comparer as a callable `fn($a, $b): int` (= compare()).
     *
     * @throws InvalidArgumentException As compare()
     */
    public function __invoke(mixed $a, mixed $b): int
    {
        return $this->compare($a, $b);
    }

    /**
     * The text of an accepted value, null for null.
     *
     * @throws InvalidArgumentException For any other type
     */
    private static function text(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }
        if ($value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException(sprintf('StringComparer expects strings or null, %s given', get_debug_type($value)));
    }

    /**
     * Simple Unicode case folding of a valid UTF-8 text.
     *
     * @throws InvalidArgumentException When the text is not valid UTF-8 (mb_convert_case() would
     *                                  turn every invalid byte into '?', making different texts equal)
     */
    private static function fold(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException('StringComparer::ordinalIgnoreCase() cannot fold a text that is not valid UTF-8');
        }

        return mb_convert_case($text, MB_CASE_FOLD_SIMPLE, 'UTF-8');
    }

    private function collate(string $a, string $b): int
    {
        $order = $this->collator->compare($a, $b);
        if ($order === false) {
            throw $this->icuFailure();
        }

        return $order <=> 0;
    }

    private function sortKey(string $text): string
    {
        $key = $this->collator->getSortKey($text);
        if ($key === false) {
            throw $this->icuFailure();
        }

        return $key;
    }

    /**
     * The exception for a text ICU refused: the ICU error, never the text.
     */
    private function icuFailure(): InvalidArgumentException
    {
        $code = $this->collator->getErrorCode();

        return new InvalidArgumentException(sprintf(
            'StringComparer::culture() cannot collate the given text (ICU error %s, code %d)',
            intl_error_name($code),
            $code
        ));
    }
}
