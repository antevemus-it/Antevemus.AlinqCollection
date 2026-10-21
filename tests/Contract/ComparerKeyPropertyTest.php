<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Contract;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Comparison\StringComparer;
use Antevemus\ALinq\DuplicateKeyPolicy;
use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Properties with an independent oracle for the comparer of 2.4.0 in grouping, set operations and
 * joins: no hand-picked example decides. Each property runs over CASES generated inputs (lists and
 * dictionaries of 0-40 items whose keys are texts in several spellings: case, accents, `'10'`;
 * null now and then where null is a valid key), seeded so a failure is reproducible, on the eager
 * and on the lazy collection, which must both answer as the oracle (so eager == lazy for every
 * operator).
 *
 * The oracles are the loops a consumer writes by hand. Grouping: the name of a group is the first
 * key seen with that comparer->hash(), `$groups[$name[$hash]][] = $item`. Sets and joins: every
 * pair compared with comparer->equals(), O(n·m), never the hash index of the library. Two comparers:
 * StringComparer::ordinalIgnoreCase() (both legs) and the accent- and case-insensitive
 * StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true) (the intl leg).
 */
final class ComparerKeyPropertyTest extends TestCase
{
    private const CASES = 150;
    private const SEED = 20261021;

    private const WORDS = ['São Paulo', 'sao paulo', 'SÃO PAULO', 'Sao Paulo', 'Rio', 'rio', 'RIO', 'Río', 'Ética', 'etica', 'ÉTICA', 'Ana', 'ana', 'Ãna', '10', '010', 'x'];

    /**
     * @return array<string, array{\Closure(): IALinqEqualityComparer}>
     */
    public static function comparers(): array
    {
        return [
            'ordinalIgnoreCase' => [static fn() => StringComparer::ordinalIgnoreCase()],
            'culture pt_BR, ignoring case and accents' => [static fn() => StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true)],
        ];
    }

    private static function comparer(\Closure $make): IALinqEqualityComparer
    {
        if (!extension_loaded('intl')) {
            try {
                return $make();
            } catch (\LogicException) {
                self::markTestSkipped('StringComparer::culture() needs ext-intl');
            }
        }

        return $make();
    }

    /**
     * @return list<array{0: string, 1: array}> [label, items]
     */
    private static function inputs(int $salt, bool $withNullKeys, int $maxSize = 40): array
    {
        mt_srand(self::SEED + $salt);
        $cases = [];
        for ($case = 0; $case < self::CASES; $case++) {
            $size = mt_rand(0, $maxSize);
            $dictionary = mt_rand(0, 2) === 0;
            $items = [];
            for ($i = 0; $i < $size; $i++) {
                $key = $withNullKeys && mt_rand(0, 12) === 0 ? null : self::WORDS[mt_rand(0, count(self::WORDS) - 1)];
                $item = ['k' => $key, 'v' => $i];
                if ($dictionary) {
                    $items['s' . $i . '_' . mt_rand(0, 99)] = $item;
                } else {
                    $items[] = $item;
                }
            }
            $cases[] = ["case $case (" . ($dictionary ? 'dictionary' : 'list') . ", $size items)", $items];
        }

        return $cases;
    }

    /**
     * @return list<array{0: string, 1: object}>
     */
    private static function sides(array $items): array
    {
        return [['eager', ALinqCollection::from($items)], ['lazy', ALinqLazyCollection::from($items)]];
    }

    private static function outcome(callable $run): array
    {
        try {
            return ['value', self::plain($run())];
        } catch (Throwable $e) {
            return ['throw', $e::class . ': ' . $e->getMessage()];
        }
    }

    private static function plain(mixed $value): mixed
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            return self::plain($value->toArray());
        }
        if (is_array($value)) {
            return array_map(self::plain(...), $value);
        }
        return $value;
    }

    private static function anyEqual(IALinqEqualityComparer $comparer, array $keys, mixed $key): bool
    {
        foreach ($keys as $kept) {
            if ($comparer->equals($kept, $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, \Closure(array): mixed> $properties name => oracle over the items
     * @param array<string, \Closure(object): mixed> $operators name => the operator on a collection
     */
    private function check(array $inputs, array $properties, array $operators): void
    {
        $failures = [];
        $checked = 0;
        foreach ($inputs as [$label, $items]) {
            foreach ($properties as $name => $oracle) {
                $expected = self::outcome(fn() => $oracle($items));
                foreach (self::sides($items) as [$side, $collection]) {
                    $actual = self::outcome(fn() => $operators[$name]($collection));
                    $checked++;
                    if ($actual !== $expected) {
                        $failures[] = sprintf('%s | %s | %s | expected %s | got %s', $name, $side, $label, json_encode($expected), json_encode($actual));
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $checked);
        $this->assertSame([], array_slice($failures, 0, 5), count($failures) . ' failures');
    }

    // ------------------------------------------------------------------ grouping: the first key seen

    #[DataProvider('comparers')]
    public function testGroupingUnderTheComparerIsGroupingByItsHashNamedByTheFirstKeySeen(\Closure $make): void
    {
        $comparer = self::comparer($make);
        $byKey = fn($item) => $item['k'];
        // the oracle: one group per hash, named by the first key that had it
        $groups = static function (array $items) use ($comparer): array {
            $names = [];
            $groups = [];
            foreach ($items as $item) {
                $name = $names[$comparer->hash($item['k'])] ??= $item['k'];
                $groups[$name][] = $item;
            }
            return $groups;
        };

        $this->check(self::inputs(1, false), [
            'groupBy' => $groups,
            'toLookup' => $groups,
            'countBy' => fn(array $items) => array_map('count', $groups($items)),
            'aggregateBy(seedSelector)' => fn(array $items) => array_map(
                fn(array $group) => array_reduce($group, fn($trace, $item) => $trace . ',' . $item['v'], '<' . $group[0]['k'] . '>'),
                $groups($items)
            ),
            'toDictionary(KeepFirst)' => fn(array $items) => array_map(fn(array $group) => $group[0], $groups($items)),
            'toDictionary(KeepLast)' => fn(array $items) => array_map(fn(array $group) => end($group), $groups($items)),
        ], [
            'groupBy' => fn($c) => $c->groupBy($byKey, $comparer),
            'toLookup' => fn($c) => $c->toLookup($byKey, null, $comparer),
            'countBy' => fn($c) => $c->countBy($byKey, $comparer),
            'aggregateBy(seedSelector)' => fn($c) => $c->aggregateBy($byKey, null, fn($trace, $item) => $trace . ',' . $item['v'], $comparer, fn($key) => "<$key>"),
            'toDictionary(KeepFirst)' => fn($c) => $c->toDictionary($byKey, null, DuplicateKeyPolicy::KeepFirst, $comparer),
            'toDictionary(KeepLast)' => fn($c) => $c->toDictionary($byKey, null, DuplicateKeyPolicy::KeepLast, $comparer),
        ]);
    }

    #[DataProvider('comparers')]
    public function testALookupFindsAGroupByAnyKeyEqualToItsName(\Closure $make): void
    {
        $comparer = self::comparer($make);
        $failures = [];
        $checked = 0;
        foreach (self::inputs(2, false) as [$label, $items]) {
            foreach (self::sides($items) as [$side, $collection]) {
                $lookup = $collection->toLookup(fn($item) => $item['k'], fn($item) => $item['v'], $comparer);
                foreach (self::WORDS as $word) {
                    $expected = [];
                    foreach ($items as $item) {
                        if ($comparer->equals($item['k'], $word)) {
                            $expected[] = $item['v'];
                        }
                    }
                    $checked++;
                    if ($lookup->get($word)->toArray() !== $expected || $lookup->contains($word) !== ($expected !== [])) {
                        $failures[] = "$side | $label | get('$word')";
                    }
                }
            }
        }

        $this->assertSame(self::CASES * 2 * count(self::WORDS), $checked);
        $this->assertSame([], array_slice($failures, 0, 5), count($failures) . ' failures');
    }

    // ------------------------------------------------------------------ set operations

    #[DataProvider('comparers')]
    public function testTheSetOperationsKeepTheFirstItemOfEachKeyAsTheOracle(\Closure $make): void
    {
        $comparer = self::comparer($make);
        mt_srand(self::SEED + 3);
        $keySets = [];
        for ($i = 0; $i < 6; $i++) {
            $keySets[] = array_map(fn() => mt_rand(0, 9) === 0 ? null : self::WORDS[mt_rand(0, count(self::WORDS) - 1)], range(1, mt_rand(0, 6)));
        }
        $byKey = fn($item) => $item['k'];
        $shape = static fn(array $origin, array $result): array => array_is_list($origin) ? array_values($result) : $result;
        $set = static function (array $items, array $keys, bool $keep) use ($comparer, $shape): array {
            $seen = [];
            $result = [];
            foreach ($items as $itemKey => $item) {
                if (self::anyEqual($comparer, $seen, $item['k']) || self::anyEqual($comparer, $keys, $item['k']) !== $keep) {
                    continue;
                }
                $seen[] = $item['k'];
                $result[$itemKey] = $item;
            }
            return $shape($items, $result);
        };

        foreach ($keySets as $n => $keys) {
            $this->check(self::inputs(10 + $n, true, 25), [
                'exceptByKeys' => fn(array $items) => $set($items, $keys, false),
                'intersectByKeys' => fn(array $items) => $set($items, $keys, true),
            ], [
                'exceptByKeys' => fn($c) => $c->exceptByKeys($keys, $byKey, $comparer),
                'intersectByKeys' => fn($c) => $c->intersectByKeys($keys, $byKey, $comparer),
            ]);

            // the element forms of the eager side: the selector on both sides
            $second = array_map(fn($key) => ['k' => $key, 'v' => -1], $keys);
            $failures = [];
            foreach (self::inputs(20 + $n, true, 25) as [$label, $items]) {
                $eager = ALinqCollection::from($items);
                if ($set($items, $keys, true) !== $eager->intersectBy($second, $byKey, $comparer)->toArray()) {
                    $failures[] = "intersectBy | $label";
                }
                if ($set($items, $keys, false) !== $eager->exceptBy($second, $byKey, $comparer)->toArray()) {
                    $failures[] = "exceptBy | $label";
                }
                $union = [];
                $seen = [];
                foreach ([$items, $second] as $sequence) {
                    foreach ($sequence as $item) {
                        if (!self::anyEqual($comparer, $seen, $item['k'])) {
                            $seen[] = $item['k'];
                            $union[] = $item;
                        }
                    }
                }
                if ($union !== $eager->unionBy($second, $byKey, $comparer)->toArray()) {
                    $failures[] = "unionBy | $label";
                }
            }
            $this->assertSame([], array_slice($failures, 0, 5), count($failures) . ' failures of the element forms');
        }
    }

    // ------------------------------------------------------------------ joins

    #[DataProvider('comparers')]
    public function testEveryJoinAnswersAsTheNestedLoopOverEquals(\Closure $make): void
    {
        $comparer = self::comparer($make);
        mt_srand(self::SEED + 4);
        $inners = [];
        for ($i = 0; $i < 5; $i++) {
            $inners[] = array_map(fn($n) => ['k' => mt_rand(0, 8) === 0 ? null : self::WORDS[mt_rand(0, count(self::WORDS) - 1)], 'w' => $n], range(1, mt_rand(0, 12)));
        }
        $matches = static fn($a, $b): bool => $a !== null && $b !== null && $comparer->equals($a, $b);
        $pair = static fn(?array $o, ?array $i): string => ($o['v'] ?? '-') . ':' . ($i['w'] ?? '-');
        $byKey = fn($row) => $row['k'];

        foreach ($inners as $n => $inner) {
            $left = static function (array $outer, bool $full) use ($inner, $matches, $pair): array {
                $result = [];
                $matched = [];
                foreach ($outer as $o) {
                    $found = false;
                    foreach ($inner as $p => $i) {
                        if ($matches($o['k'], $i['k'])) {
                            $result[] = $pair($o, $i);
                            $matched[$p] = true;
                            $found = true;
                        }
                    }
                    if (!$found) {
                        $result[] = $pair($o, null);
                    }
                }
                foreach ($full ? $inner : [] as $p => $i) {
                    if (!isset($matched[$p])) {
                        $result[] = $pair(null, $i);
                    }
                }
                return $result;
            };

            $this->check(self::inputs(30 + $n, true, 25), [
                'join' => static function (array $outer) use ($inner, $matches, $pair): array {
                    $result = [];
                    foreach ($outer as $o) {
                        foreach ($inner as $i) {
                            if ($matches($o['k'], $i['k'])) {
                                $result[] = $pair($o, $i);
                            }
                        }
                    }
                    return $result;
                },
                'groupJoin' => static function (array $outer) use ($inner, $matches): array {
                    $result = [];
                    foreach ($outer as $o) {
                        $result[] = [$o['v'], array_values(array_filter($inner, fn($i) => $matches($o['k'], $i['k'])))];
                    }
                    return $result;
                },
                'leftJoin' => fn(array $outer) => $left($outer, false),
                'fullJoin' => fn(array $outer) => $left($outer, true),
                'rightJoin' => static function (array $outer) use ($inner, $matches, $pair): array {
                    $result = [];
                    foreach ($inner as $i) {
                        $found = false;
                        foreach ($outer as $o) {
                            if ($matches($o['k'], $i['k'])) {
                                $result[] = $pair($o, $i);
                                $found = true;
                            }
                        }
                        if (!$found) {
                            $result[] = $pair(null, $i);
                        }
                    }
                    return $result;
                },
            ], [
                'join' => fn($c) => $c->join($inner, $byKey, $byKey, $pair, $comparer),
                'groupJoin' => fn($c) => $c->groupJoin($inner, $byKey, $byKey, fn($o, $is) => [$o['v'], $is->toArray()], $comparer),
                'leftJoin' => fn($c) => $c->leftJoin($inner, $byKey, $byKey, $pair, $comparer),
                'fullJoin' => fn($c) => $c->fullJoin($inner, $byKey, $byKey, $pair, $comparer),
                'rightJoin' => fn($c) => $c->rightJoin($inner, $byKey, $byKey, $pair, $comparer),
            ]);
        }
    }
}
