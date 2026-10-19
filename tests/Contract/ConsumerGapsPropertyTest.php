<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Contract;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Comparison\StringComparer;
use Antevemus\ALinq\DuplicateKeyPolicy;
use BackedEnum;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Throwable;

enum PropertyFlag: string
{
    case On = 'on';
    case Off = 'off';
}

/**
 * Properties with an independent oracle for the operators of 2.2.0 (forward 047, RN-03): no
 * hand-picked example decides. Each property runs over CASES generated inputs (lists and
 * dictionaries of 0-60 items; keys int, string, `1` × `'1'` × `'01'`, BackedEnum, and, now and
 * then, an invalid key; repeated values; null), seeded so a failure is reproducible, on the
 * eager and on the lazy collection. The oracle is the loop a consumer writes by hand
 * (`$m[$k][] = $x`, `$m[$k] = $x`), never the library.
 */
final class ConsumerGapsPropertyTest extends TestCase
{
    private const CASES = 300;
    private const SEED = 20261019;

    /**
     * @return list<array{0: string, 1: array}> [case label, items]
     */
    private static function inputs(bool $withInvalidKeys): array
    {
        $validKeys = [0, 1, 2, '1', '01', 'a', 'b', 'Ética', PropertyFlag::On, PropertyFlag::Off];
        $invalidKeys = [null, 1.5, true];
        $cases = [];
        mt_srand(self::SEED + ($withInvalidKeys ? 1 : 0));
        for ($case = 0; $case < self::CASES; $case++) {
            $size = mt_rand(0, 60);
            $dictionary = mt_rand(0, 1) === 1;
            $items = [];
            for ($i = 0; $i < $size; $i++) {
                $key = $withInvalidKeys && mt_rand(0, 40) === 0
                    ? $invalidKeys[mt_rand(0, count($invalidKeys) - 1)]
                    : $validKeys[mt_rand(0, count($validKeys) - 1)];
                $item = ['k' => $key, 'v' => mt_rand(0, 6) === 0 ? null : mt_rand(0, 5)];
                if ($dictionary) {
                    $items['s' . mt_rand(0, 1000) . '_' . $i] = $item;
                } else {
                    $items[] = $item;
                }
            }
            $cases[] = ["case $case (" . ($dictionary ? 'dictionary' : 'list') . ", $size items)", $items];
        }
        return $cases;
    }

    /**
     * @return array{0: string, 1: object}[] the two sides over the same items
     */
    private static function sides(array $items): array
    {
        return [['eager', ALinqCollection::from($items)], ['lazy', ALinqLazyCollection::from($items)]];
    }

    /**
     * The key of the oracle: what a consumer writes (`->value` of an enum), or the exception the
     * library documents for an invalid key.
     */
    private static function oracleKey(mixed $key, mixed $sourceKey, string $operation): int|string
    {
        if ($key instanceof BackedEnum) {
            return $key->value;
        }
        if (is_int($key) || is_string($key)) {
            return $key;
        }
        throw new InvalidArgumentException(sprintf(
            '%s() expects the key selector to return an int, a string or a BackedEnum, %s returned for item at key %s',
            $operation,
            get_debug_type($key),
            var_export($sourceKey, true)
        ));
    }

    /**
     * @return array{kind: string, value: mixed}
     */
    private static function outcome(callable $run): array
    {
        try {
            return ['kind' => 'value', 'value' => $run()];
        } catch (Throwable $e) {
            return ['kind' => 'throw', 'value' => $e::class . ': ' . $e->getMessage()];
        }
    }

    public function testToLookupAnswersAsTheHandWrittenGroupingAndAsGroupBy(): void
    {
        $failures = [];
        $checked = 0;
        foreach (self::inputs(true) as [$label, $items]) {
            $oracle = self::outcome(function () use ($items) {
                $groups = [];
                foreach ($items as $sourceKey => $item) {
                    $groups[self::oracleKey($item['k'], $sourceKey, 'toLookup')][] = $item;
                }
                return $groups;
            });
            foreach (self::sides($items) as [$side, $collection]) {
                $lookup = self::outcome(fn() => $collection->toLookup(fn($item) => $item['k'])->toArray());
                $checked++;
                if ($lookup !== $oracle) {
                    $failures[] = "$label, $side: toLookup " . json_encode($lookup) . ' vs oracle ' . json_encode($oracle);
                }
                $grouped = self::outcome(fn() => array_map(fn($g) => $g->toArray(), $collection->groupBy(fn($item) => $item['k'])->toArray()));
                $expected = $oracle['kind'] === 'throw' ? ['kind' => 'throw', 'value' => str_replace('toLookup()', 'groupBy()', $oracle['value'])] : $oracle;
                if ($grouped !== $expected) {
                    $failures[] = "$label, $side: groupBy differs from toLookup";
                }
                if ($oracle['kind'] === 'value') {
                    $missing = $collection->toLookup(fn($item) => $item['k'])->get('never a key');
                    if ($missing->toArray() !== []) {
                        $failures[] = "$label, $side: get() of a missing key is not empty";
                    }
                }
            }
        }
        $this->assertSame(self::CASES * 2, $checked);
        $this->assertSame([], $failures, implode("\n", array_slice($failures, 0, 10)));
    }

    public function testToDictionaryKeepFirstAndKeepLastAnswerAsTheHandWrittenIndex(): void
    {
        $failures = [];
        $checked = 0;
        foreach (self::inputs(true) as [$label, $items]) {
            $first = self::outcome(function () use ($items) {
                $index = [];
                foreach ($items as $sourceKey => $item) {
                    $key = self::oracleKey($item['k'], $sourceKey, 'toDictionary');
                    if (!array_key_exists($key, $index)) { // `??=` would also replace a stored null
                        $index[$key] = $item['v'];
                    }
                }
                return $index;
            });
            $last = self::outcome(function () use ($items) {
                $index = [];
                foreach ($items as $sourceKey => $item) {
                    $index[self::oracleKey($item['k'], $sourceKey, 'toDictionary')] = $item['v'];
                }
                return $index;
            });
            foreach (self::sides($items) as [$side, $collection]) {
                foreach (['KeepFirst' => [DuplicateKeyPolicy::KeepFirst, $first], 'KeepLast' => [DuplicateKeyPolicy::KeepLast, $last]] as $name => [$policy, $oracle]) {
                    $checked++;
                    $answer = self::outcome(fn() => $collection->toDictionary(fn($item) => $item['k'], fn($item) => $item['v'], $policy));
                    if ($answer !== $oracle) {
                        $failures[] = "$label, $side, $name: " . json_encode($answer) . ' vs oracle ' . json_encode($oracle);
                    }
                }
            }
        }
        $this->assertSame(self::CASES * 4, $checked);
        $this->assertSame([], $failures, implode("\n", array_slice($failures, 0, 10)));
    }

    public function testCountByAndAggregateByAnswerAsTheHandWrittenLoops(): void
    {
        $failures = [];
        $checked = 0;
        foreach (self::inputs(true) as [$label, $items]) {
            $counts = self::outcome(function () use ($items) {
                $counts = [];
                foreach ($items as $sourceKey => $item) {
                    $key = self::oracleKey($item['k'], $sourceKey, 'groupBy');
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                }
                return $counts;
            });
            $traces = self::outcome(function () use ($items) {
                $traces = [];
                $positions = [];
                foreach ($items as $sourceKey => $item) {
                    $key = self::oracleKey($item['k'], $sourceKey, 'groupBy');
                    $position = $positions[$key] ?? 0;
                    $traces[$key] = ($traces[$key] ?? '') . "[$position:" . json_encode($item['v']) . ']';
                    $positions[$key] = $position + 1;
                }
                return $traces;
            });
            foreach (self::sides($items) as [$side, $collection]) {
                $checked++;
                $answer = self::outcome(fn() => $collection->countBy(fn($item) => $item['k'])->toArray());
                if ($answer !== $counts) {
                    $failures[] = "$label, $side, countBy: " . json_encode($answer) . ' vs oracle ' . json_encode($counts);
                }
                $checked++;
                $answer = self::outcome(fn() => $collection->aggregateBy(fn($item) => $item['k'], '', fn($acc, $item, $position) => $acc . "[$position:" . json_encode($item['v']) . ']')->toArray());
                if ($answer !== $traces) {
                    $failures[] = "$label, $side, aggregateBy: " . json_encode($answer) . ' vs oracle ' . json_encode($traces);
                }
            }
        }
        $this->assertSame(self::CASES * 4, $checked);
        $this->assertSame([], $failures, implode("\n", array_slice($failures, 0, 10)));
    }

    public function testExceptByKeysAndIntersectByKeysAnswerAsWhereInArrayThenDistinctBy(): void
    {
        $pool = [0, 1, '1', 'a', 'Ética', null, 1.5, PropertyFlag::On, PropertyFlag::Off];
        $failures = [];
        $checked = 0;
        $cases = self::inputs(false);
        mt_srand(self::SEED + 7);
        foreach ($cases as $n => [$label, $items]) {
            // an invalid group key is a valid set key: mix the invalid ones back in
            foreach ($items as &$item) {
                if (mt_rand(0, 9) === 0) {
                    $item['k'] = $pool[mt_rand(5, 6)];
                }
            }
            unset($item);
            $keys = [];
            foreach ($pool as $candidate) {
                if (mt_rand(0, 2) === 0) {
                    $keys[] = $candidate;
                }
            }
            $selector = fn($item) => $item['k'];
            $except = ALinqCollection::from($items)->where(fn($x) => !in_array($selector($x), $keys, true))->distinctBy($selector)->toArray();
            $intersect = ALinqCollection::from($items)->where(fn($x) => in_array($selector($x), $keys, true))->distinctBy($selector)->toArray();

            foreach (self::sides($items) as [$side, $collection]) {
                $sources = ['array' => $keys, 'generator' => (fn() => yield from $keys)(), 'collection' => ALinqCollection::from($keys)];
                $kind = array_keys($sources)[$n % 3];
                $checked++;
                $answer = $collection->exceptByKeys($sources[$kind], $selector)->toArray();
                if ($answer !== $except) {
                    $failures[] = "$label, $side, exceptByKeys ($kind)";
                }
                $checked++;
                $answer = $collection->intersectByKeys(ALinqLazyCollection::from($keys), $selector)->toArray();
                if ($answer !== $intersect) {
                    $failures[] = "$label, $side, intersectByKeys";
                }
            }
        }
        $this->assertSame(self::CASES * 4, $checked);
        $this->assertSame([], $failures, implode("\n", array_slice($failures, 0, 10)));
    }

    /**
     * @return list<string|null>
     */
    private static function texts(): array
    {
        return [null, '', 'a', 'A', 'á', 'Á', 'b', 'B', 'ana', 'Ana', 'ANA', 'Álvaro', 'alvaro', 'Bruno', 'Ética', 'etica', 'ÉTICA', 'Etica',
            'Zeca', 'zeca', '10', '9', '1e1', 'item2', 'item10', 'ß', 'ss', 'Ç', 'c', 'ç', 'résumé', 'resume'];
    }

    /**
     * The laws every comparer obeys: antisymmetry, transitivity on every triple of a sample,
     * compare() in {-1, 0, 1}, equals() ⇔ compare() === 0, equal values have equal hashes.
     *
     * @return list<string>
     */
    private static function comparerLaws(string $name, StringComparer $comparer): array
    {
        $failures = [];
        $texts = self::texts();
        foreach ($texts as $a) {
            foreach ($texts as $b) {
                $ab = $comparer->compare($a, $b);
                if (!in_array($ab, [-1, 0, 1], true) || $ab !== -$comparer->compare($b, $a)) {
                    $failures[] = "$name: antisymmetry " . json_encode([$a, $b]);
                }
                if ($comparer->equals($a, $b) !== ($ab === 0)) {
                    $failures[] = "$name: equals ⇔ compare === 0 " . json_encode([$a, $b]);
                }
                if ($ab === 0 && $comparer->hash($a) !== $comparer->hash($b)) {
                    $failures[] = "$name: equal values with different hashes " . json_encode([$a, $b]);
                }
                foreach ($texts as $c) {
                    if ($ab <= 0 && $comparer->compare($b, $c) <= 0 && $comparer->compare($a, $c) > 0) {
                        $failures[] = "$name: transitivity " . json_encode([$a, $b, $c]);
                    }
                }
            }
        }
        return $failures;
    }

    public function testOrdinalComparersObeyTheLaws(): void
    {
        $failures = array_merge(
            self::comparerLaws('ordinal', StringComparer::ordinal()),
            self::comparerLaws('ordinalIgnoreCase', StringComparer::ordinalIgnoreCase())
        );
        $this->assertSame([], $failures, implode("\n", array_slice($failures, 0, 10)));
    }

    #[RequiresPhpExtension('intl')]
    public function testCultureComparersObeyTheLaws(): void
    {
        $failures = [];
        foreach ([[], ['ignoreCase' => true], ['ignoreAccents' => true], ['ignoreCase' => true, 'ignoreAccents' => true], ['numeric' => true]] as $options) {
            $failures = array_merge($failures, self::comparerLaws('pt_BR ' . json_encode($options), StringComparer::culture('pt_BR', ...$options)));
        }
        $this->assertSame([], $failures, implode("\n", array_slice($failures, 0, 10)));

        $loose = StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true);
        $this->assertTrue($loose->equals('Ética', 'etica'));
        $this->assertTrue($loose->equals('Ética', 'ÉTICA'));
        $this->assertTrue($loose->equals('etica', 'ÉTICA'));
        $this->assertSame($loose->hash('Ética'), $loose->hash('etica'));
        $this->assertSame(-1, $loose->compare('Álvaro', 'Bruno'));
        $this->assertSame(
            ['Álvaro', 'ana', 'Bruno', 'Ética', 'Zeca'],
            ALinqCollection::from(['Zeca', 'Ética', 'Bruno', 'ana', 'Álvaro'])->orderBy(fn($s) => $s, $loose)->toArray()
        );
    }
}
