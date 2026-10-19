<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Contract;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\ALinqLookup;
use Antevemus\ALinq\Comparison\StringComparer;
use Antevemus\ALinq\DuplicateKeyPolicy;
use Antevemus\ALinq\Interfaces\IALinqCollection;
use ErrorException;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Eager × lazy parity matrix (contract 1.3.0, RN-28).
 *
 * Every operation both collections share runs over the same inputs on both sides and the
 * results are compared strictly, keys included; when an operation throws, the exception
 * class and, since 1.4.0, its message must be the same on both sides. The 2026-10-08 review
 * measured 114 divergences in 350 comparisons on 1.1.1; the contract allows none.
 *
 * 1.4.0 (forward 021, RN-02): single(), whereIn()/whereNotIn()/whereBetween(), the outer
 * joins and orderBy()/orderByDescending()/thenBy()/thenByDescending() joined the matrix; a
 * second matrix runs the field- and key-based operators over rows (arrays and objects).
 *
 * The only input whose keys differ between the sides is "D" (a generator that yields the
 * keys 0, 1, 0, 1): the eager side cannot hold repeated keys, so it receives the list
 * [1, 2, 3, 4]. Operations that read the key are therefore excluded for "D" only
 * (KEY_DEPENDENT): the source keys are the data, not a divergence.
 *
 * 2.1.0 (forward 036, RN-02): the five aggregates with a default joined the matrix; their
 * own matrix (only nulls, ties, values that are not comparable, a throwing selector, each
 * answer checked against the sibling without default) is AggregateOrDefaultContractTest.
 *
 * 2.2.0 (forward 047, RN-02): groupBy(), countBy(), aggregateBy() (the third argument of $func
 * is the position inside the group on both sides), toLookup() (an ALinqLookup compares by its
 * toArray()), toDictionary() under the three policies, exceptByKeys()/intersectByKeys() (keys
 * as an array, a generator and a collection, null among them), implode(), and the comparer of
 * orderBy*(), distinct*(), min*() and max*() joined the matrix; toLookup(), exceptByKeys() and
 * countBy() joined the row matrix.
 */
final class ParityTest extends TestCase
{
    private const KEY_DEPENDENT = [
        'where(fn(v,k) k!==0 && k!=="a")',
        'select(fn(v,k))',
        'first(fn(v,k) k!==0)',
        'last(fn(v,k) k!==0)',
        'lastOrDefault("d", fn(v,k) k===0)',
        'count(fn(v,k) k!==0)',
        'sum(fn(v,k))',
        'aggregate("", concat k)',
        'each(collect k)',
        'single(fn(v,k) k===1)',
        'orderBy(fn(v,k) -k)',
        'thenByDescending(fn(v,k) k)',
        'minOrDefault("d", fn(v,k))',
        'averageOrDefault("d", fn(v,k))',
        'maxByOrDefault("d", fn(v,k) k)',
        // 2.2.0: the message of a repeated key names the source key of the second item
        'toDictionary(parity)',
        'toDictionary(1 vs "1")',
    ];

    /**
     * @return array<string, array{array, ?\Closure}> [eager input, lazy factory or null (= same array)]
     */
    private static function inputs(): array
    {
        return [
            'A list' => [[1, 2, 3, 4, 5], null],
            'B dictionary' => [['a' => 1, 'b' => 2, 'c' => 3], null],
            'C non-sequential' => [[5 => 'x', 2 => 'y', 9 => 'z'], null],
            'D repeated keys' => [[1, 2, 3, 4], fn() => (function () { yield from [1, 2]; yield from [3, 4]; })()],
            'E empty' => [[], null],
            'F one item' => [[42], null],
            'G falsy values' => [[null, false, 0, '', '0'], null],
        ];
    }

    /**
     * @return array<string, \Closure>
     */
    private static function operations(): array
    {
        return [
            'where(v!==2 && v!=="y")' => fn($c) => $c->where(fn($v) => $v !== 2 && $v !== 'y'),
            'where(fn(v,k) k!==0 && k!=="a")' => fn($c) => $c->where(fn($v, $k) => $k !== 0 && $k !== 'a'),
            'select(json(v).":")' => fn($c) => $c->select(fn($v) => json_encode($v) . ':'),
            'select(fn(v,k))' => fn($c) => $c->select(fn($v, $k) => "$k=" . json_encode($v)),
            'selectMany([v,v])' => fn($c) => $c->selectMany(fn($v) => [$v, $v]),
            'selectMany(scalar)' => fn($c) => $c->selectMany(fn($v) => $v),
            'take(2)' => fn($c) => $c->take(2),
            'take(-2)' => fn($c) => $c->take(-2),
            'take(0)' => fn($c) => $c->take(0),
            'skip(2)' => fn($c) => $c->skip(2),
            'skip(-2)' => fn($c) => $c->skip(-2),
            'distinct()' => fn($c) => $c->distinct(),
            'distinct(fn)' => fn($c) => $c->distinct(fn($v) => is_int($v) ? $v % 2 : (string) $v),
            'distinctBy(fn)' => fn($c) => $c->distinctBy(fn($v) => is_int($v) ? $v % 2 : (string) $v),
            'chunk(2)' => fn($c) => $c->chunk(2),
            'chunk(0)' => fn($c) => $c->chunk(0),
            'pad(6,"p")' => fn($c) => $c->pad(6, 'p'),
            'pad(-6,"p")' => fn($c) => $c->pad(-6, 'p'),
            'concat([9,8])' => fn($c) => $c->concat([9, 8]),
            'concat(["a"=>9,"z"=>8])' => fn($c) => $c->concat(['a' => 9, 'z' => 8]),
            'first()' => fn($c) => $c->first(),
            'first(v>2)' => fn($c) => $c->first(fn($v) => is_int($v) && $v > 2),
            'first(fn(v,k) k!==0)' => fn($c) => $c->first(fn($v, $k) => $k !== 0),
            'firstOrDefault("d")' => fn($c) => $c->firstOrDefault('d'),
            'firstOrDefault("d", v>2)' => fn($c) => $c->firstOrDefault('d', fn($v) => is_int($v) && $v > 2),
            'last()' => fn($c) => $c->last(),
            'last(v<3)' => fn($c) => $c->last(fn($v) => is_int($v) && $v < 3),
            'last(fn(v,k) k!==0)' => fn($c) => $c->last(fn($v, $k) => $k !== 0),
            'lastOrDefault("d")' => fn($c) => $c->lastOrDefault('d'),
            'lastOrDefault("d", fn(v,k) k===0)' => fn($c) => $c->lastOrDefault('d', fn($v, $k) => $k === 0),
            'singleOrDefault()' => fn($c) => $c->singleOrDefault(),
            'singleOrDefault("d", v===3)' => fn($c) => $c->singleOrDefault('d', fn($v) => $v === 3),
            'any()' => fn($c) => $c->any(),
            'any(v===3)' => fn($c) => $c->any(fn($v) => $v === 3),
            'all()' => fn($c) => $c->all(),
            'all(is_int)' => fn($c) => $c->all('is_int'),
            'contains(3)' => fn($c) => $c->contains(3),
            'contains(3, <=>)' => fn($c) => $c->contains(3, fn($a, $b) => $a <=> $b),
            'contains(3, ===)' => fn($c) => $c->contains(3, fn($a, $b) => $a === $b),
            'count()' => fn($c) => $c->count(),
            'count(is_int)' => fn($c) => $c->count('is_int'),
            'count(fn(v,k) k!==0)' => fn($c) => $c->count(fn($v, $k) => $k !== 0),
            'sum()' => fn($c) => $c->sum(),
            'sum(fn(v,k))' => fn($c) => $c->sum(fn($v, $k) => is_int($k) ? $k : 0),
            'average()' => fn($c) => $c->average(),
            'min()' => fn($c) => $c->min(),
            'max()' => fn($c) => $c->max(),
            'minBy(fn)' => fn($c) => $c->minBy(fn($v) => is_int($v) ? -$v : 0),
            'maxBy(fn)' => fn($c) => $c->maxBy(fn($v) => is_int($v) ? -$v : 0),
            // 2.1.0 (forward 036, RN-02): the aggregates with a default, on the same seven inputs
            'minOrDefault()' => fn($c) => $c->minOrDefault(),
            'minOrDefault("d", fn(v,k))' => fn($c) => $c->minOrDefault('d', fn($v, $k) => is_int($v) ? $v * 10 + (is_int($k) ? $k : 0) : $v),
            'maxOrDefault("d")' => fn($c) => $c->maxOrDefault('d'),
            'averageOrDefault("d")' => fn($c) => $c->averageOrDefault('d'),
            'averageOrDefault("d", fn(v,k))' => fn($c) => $c->averageOrDefault('d', fn($v, $k) => is_int($k) ? $k : null),
            'minByOrDefault("d", fn)' => fn($c) => $c->minByOrDefault('d', fn($v) => is_int($v) ? -$v : 0),
            'maxByOrDefault("d", fn)' => fn($c) => $c->maxByOrDefault('d', fn($v) => is_int($v) ? -$v : 0),
            'maxByOrDefault("d", fn(v,k) k)' => fn($c) => $c->maxByOrDefault('d', fn($v, $k) => $k),
            'aggregate("", concat k)' => fn($c) => $c->aggregate('', fn($acc, $v, $k) => $acc . "[$k=" . json_encode($v) . ']'),
            'each(collect k)' => function ($c) {
                $seen = [];
                $c->each(function ($v, $k) use (&$seen) { $seen[] = [$k, $v]; });
                return $seen;
            },
            // 1.4.0 (forward 021, RN-02): every new operator, on the same seven inputs
            'single()' => fn($c) => $c->single(),
            'single(v===3)' => fn($c) => $c->single(fn($v) => $v === 3),
            'single(is_int)' => fn($c) => $c->single('is_int'),
            'single(fn(v,k) k===1)' => fn($c) => $c->single(fn($v, $k) => $k === 1),
            // the inputs are scalars: the field reads null for every item, which exercises the
            // null rule and the key rule (all kept or none kept)
            'whereIn("x",[null])' => fn($c) => $c->whereIn('x', [null]),
            'whereIn("x",[1])' => fn($c) => $c->whereIn('x', [1]),
            'whereNotIn("x",[null])' => fn($c) => $c->whereNotIn('x', [null]),
            'whereNotIn("x",[1])' => fn($c) => $c->whereNotIn('x', [1]),
            'whereBetween("x",0,9)' => fn($c) => $c->whereBetween('x', 0, 9),
            'leftJoin([2,3,"y",7,3])' => fn($c) => $c->leftJoin([2, 3, 'y', 7, 3], fn($v) => $v, fn($v) => $v),
            'leftJoin(dict, selector)' => fn($c) => $c->leftJoin(['k' => 3, 'm' => null, 'n' => '0'], fn($v) => $v, fn($v) => $v, fn($o, $i) => json_encode([$o, $i])),
            'rightJoin([2,3,"y",7,3])' => fn($c) => $c->rightJoin([2, 3, 'y', 7, 3], fn($v) => $v, fn($v) => $v),
            'rightJoin(dict, selector)' => fn($c) => $c->rightJoin(['k' => 3, 'm' => null, 'n' => '0'], fn($v) => $v, fn($v) => $v, fn($o, $i) => json_encode([$o, $i])),
            'fullJoin([2,3,"y",7,3])' => fn($c) => $c->fullJoin([2, 3, 'y', 7, 3], fn($v) => $v, fn($v) => $v),
            'fullJoin(dict, selector)' => fn($c) => $c->fullJoin(['k' => 3, 'm' => null, 'n' => '0'], fn($v) => $v, fn($v) => $v, fn($o, $i) => json_encode([$o, $i])),
            'orderBy(json)' => fn($c) => $c->orderBy(fn($v) => json_encode($v)),
            'orderBy(fn(v,k) -k)' => fn($c) => $c->orderBy(fn($v, $k) => is_int($k) ? -$k : $k),
            'orderByDescending(parity)' => fn($c) => $c->orderByDescending(fn($v) => is_int($v) ? $v % 2 : -1),
            'thenBy(json)' => fn($c) => $c->orderBy(fn($v) => is_int($v) ? $v % 2 : -1)->thenBy(fn($v) => json_encode($v)),
            'thenBy(json, reversed comparer)' => fn($c) => $c->orderBy(fn($v) => is_int($v) ? $v % 2 : -1)->thenBy(fn($v) => json_encode($v), fn($a, $b) => $b <=> $a),
            'thenByDescending(json)' => fn($c) => $c->orderByDescending(fn($v) => is_int($v) ? $v % 2 : -1)->thenByDescending(fn($v) => json_encode($v)),
            'thenByDescending(fn(v,k) k)' => fn($c) => $c->orderBy(fn($v) => 0)->thenByDescending(fn($v, $k) => $k),
            'thenBy(without orderBy)' => fn($c) => $c->thenBy(fn($v) => $v),
            'thenBy(after where)' => fn($c) => $c->orderBy(fn($v) => json_encode($v))->where(fn($v) => true)->thenBy(fn($v) => $v),
            'toArray()' => fn($c) => $c->toArray(),
            'toObject()' => fn($c) => (array) $c->toObject(),
            'jsonSerialize()' => fn($c) => json_encode($c),
            // 2.2.0 (forward 047, RN-02): every new operator and every new parameter
            'groupBy(parity)' => fn($c) => $c->groupBy(fn($v) => is_int($v) ? $v % 2 : 'other'),
            'groupBy(null key)' => fn($c) => $c->groupBy(fn($v) => null),
            'countBy(parity)' => fn($c) => $c->countBy(fn($v) => is_int($v) ? $v % 2 : 'other'),
            'countBy(float key)' => fn($c) => $c->countBy(fn($v) => 1.5),
            'aggregateBy(parity, position)' => fn($c) => $c->aggregateBy(fn($v) => is_int($v) ? $v % 2 : 'other', '', fn($acc, $v, $p) => $acc . "[$p=" . json_encode($v) . ']'),
            'aggregateBy(bool key)' => fn($c) => $c->aggregateBy(fn($v) => true, 0, fn($acc) => $acc + 1),
            'toLookup(parity)' => fn($c) => $c->toLookup(fn($v) => is_int($v) ? $v % 2 : 'other'),
            'toLookup(parity, json)' => fn($c) => $c->toLookup(fn($v) => is_int($v) ? $v % 2 : 'other', fn($v) => json_encode($v)),
            'toLookup(null key)' => fn($c) => $c->toLookup(fn($v) => null),
            'toLookup(get missing)' => fn($c) => $c->toLookup(fn($v) => 'k')->get('missing'),
            'toDictionary(json)' => fn($c) => $c->toDictionary(fn($v) => json_encode($v)),
            'toDictionary(parity)' => fn($c) => $c->toDictionary(fn($v) => is_int($v) ? $v % 2 : 'other'),
            'toDictionary(parity, KeepFirst)' => fn($c) => $c->toDictionary(fn($v) => is_int($v) ? $v % 2 : 'other', null, DuplicateKeyPolicy::KeepFirst),
            'toDictionary(parity, json, KeepLast)' => fn($c) => $c->toDictionary(fn($v) => is_int($v) ? $v % 2 : 'other', fn($v) => json_encode($v), DuplicateKeyPolicy::KeepLast),
            'toDictionary(1 vs "1")' => fn($c) => $c->toDictionary(fn($v) => is_int($v) ? 1 : '1'),
            'toDictionary(1 vs "1", KeepLast)' => fn($c) => $c->toDictionary(fn($v) => is_int($v) ? 1 : '1', fn($v) => json_encode($v), DuplicateKeyPolicy::KeepLast),
            'toDictionary(float key, KeepFirst)' => fn($c) => $c->toDictionary(fn($v) => 0.5, null, DuplicateKeyPolicy::KeepFirst),
            'exceptByKeys([1,3,null])' => fn($c) => $c->exceptByKeys([1, 3, null], fn($v) => $v),
            'exceptByKeys(generator)' => fn($c) => $c->exceptByKeys((function () { yield 2; yield 'y'; yield '0'; })(), fn($v) => $v),
            'intersectByKeys([2,"0",false])' => fn($c) => $c->intersectByKeys([2, '0', false], fn($v) => $v),
            'intersectByKeys(collection)' => fn($c) => $c->intersectByKeys(ALinqCollection::from([1, 'z', null]), fn($v) => $v),
            'intersectByKeys(lazy, parity)' => fn($c) => $c->intersectByKeys(ALinqLazyCollection::from([1, 'other']), fn($v) => is_int($v) ? $v % 2 : 'other'),
            'orderBy(json, reversed comparer)' => fn($c) => $c->orderBy(fn($v) => json_encode($v), fn($a, $b) => $b <=> $a),
            'orderByDescending(json, ordinal)' => fn($c) => $c->orderByDescending(fn($v) => json_encode($v), StringComparer::ordinal()),
            'orderBy(v, ordinal)' => fn($c) => $c->orderBy(fn($v) => $v, StringComparer::ordinal()),
            'distinct(null, parity comparer)' => fn($c) => $c->distinct(null, new C\ParityComparer()),
            'distinct(json, ordinalIgnoreCase)' => fn($c) => $c->distinct(fn($v) => is_string($v) ? strtoupper($v) : json_encode($v), StringComparer::ordinalIgnoreCase()),
            'distinctBy(json, parity comparer)' => fn($c) => $c->distinctBy(fn($v) => json_encode($v), new C\ParityComparer()),
            'min(null, reversed)' => fn($c) => $c->min(null, fn($a, $b) => $b <=> $a),
            'max(json, ordinal)' => fn($c) => $c->max(fn($v) => json_encode($v), StringComparer::ordinal()),
            'max(v, ordinal)' => fn($c) => $c->max(null, StringComparer::ordinal()),
            'minBy(json, reversed)' => fn($c) => $c->minBy(fn($v) => json_encode($v), fn($a, $b) => $b <=> $a),
            'maxBy(text or null, ordinal)' => fn($c) => $c->maxBy(fn($v) => is_int($v) ? (string) (10 - $v) : null, StringComparer::ordinal()),
            'minOrDefault("d", null, reversed)' => fn($c) => $c->minOrDefault('d', null, fn($a, $b) => $b <=> $a),
            'maxOrDefault("d", json, ordinal)' => fn($c) => $c->maxOrDefault('d', fn($v) => json_encode($v), StringComparer::ordinal()),
            'minByOrDefault("d", json, reversed)' => fn($c) => $c->minByOrDefault('d', fn($v) => json_encode($v), fn($a, $b) => $b <=> $a),
            'maxByOrDefault("d", json, ordinal)' => fn($c) => $c->maxByOrDefault('d', fn($v) => json_encode($v), StringComparer::ordinal()),
            'implode()' => fn($c) => $c->implode(),
            'implode(", ")' => fn($c) => $c->implode(', '),
            'implode("|", json)' => fn($c) => $c->implode('|', fn($v) => json_encode($v)),
        ];
    }

    public function testEveryOperationAnswersTheSameOnBothSides(): void
    {
        $rows = [];
        $total = 0;
        foreach (self::operations() as $opName => $op) {
            foreach (self::inputs() as $inputName => [$array, $factory]) {
                if ($factory !== null && in_array($opName, self::KEY_DEPENDENT, true)) {
                    continue;
                }
                $total++;
                $eager = self::evaluate($op, ALinqCollection::from($array));
                $lazy = self::evaluate($op, $factory !== null ? ALinqLazyCollection::from($factory) : ALinqLazyCollection::from($array));
                if ($eager !== $lazy) {
                    $rows[] = sprintf('%s | %s | eager %s | lazy %s', $opName, $inputName, self::fmt($eager), self::fmt($lazy));
                }
            }
        }

        $this->assertGreaterThan(300, $total);
        $this->assertSame([], $rows, "Eager × lazy divergences:\n" . implode("\n", $rows));
    }

    /**
     * Row-shaped inputs for the operators that read a field or join on a key (1.4.0).
     *
     * @return array<string, array>
     */
    private static function rowInputs(): array
    {
        return [
            'R list of arrays' => [
                ['id' => 1, 'dept' => 'b', 'age' => 17, 'salary' => 300, 'c' => 10],
                ['id' => 2, 'dept' => 'a', 'age' => 18, 'salary' => 100, 'c' => null],
                ['id' => 3, 'dept' => 'b', 'age' => 65, 'salary' => 300, 'c' => 99],
                ['id' => 4, 'dept' => 'a', 'age' => 66, 'salary' => 200, 'c' => 10],
                ['id' => 5, 'dept' => 'b', 'age' => null, 'salary' => 100, 'c' => 20],
            ],
            'S dictionary of arrays' => [
                'x' => ['id' => 1, 'dept' => 'b', 'age' => '18', 'salary' => 1, 'c' => 20],
                'y' => ['id' => 2, 'dept' => 'a', 'age' => 30, 'salary' => 2, 'c' => 10],
                'z' => ['id' => 3, 'dept' => 'a', 'salary' => 2, 'c' => '10'],
            ],
            'T objects with private fields' => [
                new C\Employee(1, 'b', 40, 10, 10),
                new C\Employee(2, 'a', 18, 20, 30),
                new C\Employee(3, 'a', 70, 20, null),
            ],
            'U empty' => [],
        ];
    }

    /**
     * @return array<string, \Closure>
     */
    private static function rowOperations(): array
    {
        $customers = [['id' => 10, 'name' => 'ten'], ['id' => 20, 'name' => 'twenty'], ['id' => null, 'name' => 'none'], ['id' => 10, 'name' => 'ten again']];
        $pair = fn($o, $i) => [is_object($o) ? $o->describe() : $o, $i];

        return [
            'whereIn(dept,[a])' => fn($c) => $c->whereIn('dept', ['a']),
            'whereIn(age,["18"])' => fn($c) => $c->whereIn('age', ['18']),
            'whereIn(age,[null])' => fn($c) => $c->whereIn('age', [null]),
            'whereNotIn(dept,[a])' => fn($c) => $c->whereNotIn('dept', ['a']),
            'whereNotIn(age,[18])' => fn($c) => $c->whereNotIn('age', [18]),
            'whereBetween(age,18,65)' => fn($c) => $c->whereBetween('age', 18, 65),
            'whereBetween(salary,100,200)' => fn($c) => $c->whereBetween('salary', 100, 200),
            'leftJoin(customers)' => fn($c) => $c->leftJoin($customers, fn($o) => C\Employee::read($o, 'c'), fn($i) => $i['id'], $pair),
            'rightJoin(customers)' => fn($c) => $c->rightJoin($customers, fn($o) => C\Employee::read($o, 'c'), fn($i) => $i['id'], $pair),
            'fullJoin(customers)' => fn($c) => $c->fullJoin($customers, fn($o) => C\Employee::read($o, 'c'), fn($i) => $i['id'], $pair),
            'orderBy(dept)->thenByDescending(salary)' => fn($c) => $c->orderBy(fn($r) => C\Employee::read($r, 'dept'))->thenByDescending(fn($r) => C\Employee::read($r, 'salary')),
            'orderByDescending(salary)->thenBy(dept)->thenBy(id)' => fn($c) => $c->orderByDescending(fn($r) => C\Employee::read($r, 'salary'))->thenBy(fn($r) => C\Employee::read($r, 'dept'))->thenBy(fn($r) => C\Employee::read($r, 'id')),
            'single(id===2)' => fn($c) => $c->single(fn($r) => C\Employee::read($r, 'id') === 2),
            'single(dept===a)' => fn($c) => $c->single(fn($r) => C\Employee::read($r, 'dept') === 'a'),
            // 2.2.0 (forward 047, RN-02)
            'toLookup(dept)' => fn($c) => $c->toLookup(fn($r) => C\Employee::read($r, 'dept')),
            'exceptByKeys([1,3], id)' => fn($c) => $c->exceptByKeys([1, 3], fn($r) => C\Employee::read($r, 'id')),
            'countBy(dept)' => fn($c) => $c->countBy(fn($r) => C\Employee::read($r, 'dept')),
        ];
    }

    public function testRowOperationsAnswerTheSameOnBothSides(): void
    {
        $rows = [];
        $total = 0;
        foreach (self::rowOperations() as $opName => $op) {
            foreach (self::rowInputs() as $inputName => $array) {
                $total++;
                $eager = self::evaluate($op, ALinqCollection::from($array));
                $lazy = self::evaluate($op, ALinqLazyCollection::from($array));
                if (self::deepObjects($eager) !== self::deepObjects($lazy)) {
                    $rows[] = sprintf('%s | %s | eager %s | lazy %s', $opName, $inputName, self::fmt($eager), self::fmt($lazy));
                }
            }
        }

        // 14 row operations of 1.4.0 + 3 of 2.2.0 (toLookup, exceptByKeys, countBy), × 4 inputs
        $this->assertSame((14 + 3) * 4, $total);
        $this->assertSame([], $rows, "Eager × lazy divergences on rows:\n" . implode("\n", $rows));
    }

    /**
     * Replaces each Employee by its description, so two results holding the same objects
     * compare equal (and print).
     */
    private static function deepObjects(mixed $value): mixed
    {
        if ($value instanceof C\Employee) {
            return $value->describe();
        }
        if (is_array($value)) {
            return array_map(self::deepObjects(...), $value);
        }
        return $value;
    }

    public function testTheSharedOperationListIsComplete(): void
    {
        // Every public method both classes share must appear in the matrix, so a new
        // operator cannot be added to one side with a different contract unnoticed.
        $covered = [];
        foreach (array_keys(self::operations()) as $name) {
            $covered[] = substr($name, 0, (int) strpos($name, '('));
        }
        $covered = array_unique($covered);

        $eager = array_map(fn($m) => $m->name, (new \ReflectionClass(ALinqCollection::class))->getMethods(\ReflectionMethod::IS_PUBLIC));
        $lazy = array_map(fn($m) => $m->name, (new \ReflectionClass(ALinqLazyCollection::class))->getMethods(\ReflectionMethod::IS_PUBLIC));
        $shared = array_values(array_intersect($eager, $lazy));

        $notQueryOperations = ['__construct', 'getIterator', 'from', 'empty', 'range', 'repeat', 'fromFile', 'fromCsv', 'fromCursor', 'fromHttpPages', 'toCollection', 'lazy'];
        $missing = array_diff($shared, $covered, $notQueryOperations);
        sort($missing);

        $this->assertSame([], $missing, 'Shared operations missing from the parity matrix: ' . implode(', ', $missing));
    }

    /**
     * @return array{kind: string, val: mixed}
     */
    private static function evaluate(callable $op, object $collection): array
    {
        set_error_handler(static function (int $no, string $str): bool {
            throw new ErrorException($str, $no);
        });
        try {
            $result = $op($collection);
            if ($result instanceof IALinqCollection || $result instanceof ALinqLazyCollection) {
                return ['kind' => 'collection', 'val' => self::deep($result->toArray())];
            }
            if ($result instanceof ALinqLookup) {
                return ['kind' => 'lookup', 'val' => self::deep($result->toArray())];
            }
            return ['kind' => 'value', 'val' => self::deep($result)];
        } catch (Throwable $e) {
            return ['kind' => 'throw', 'val' => $e::class . ': ' . $e->getMessage()];
        } finally {
            restore_error_handler();
        }
    }

    private static function deep(mixed $value): mixed
    {
        if ($value instanceof IALinqCollection || $value instanceof ALinqLazyCollection || $value instanceof ALinqLookup) {
            return self::deep($value->toArray());
        }
        if (is_array($value)) {
            return array_map(self::deep(...), $value);
        }
        return $value;
    }

    private static function fmt(array $r): string
    {
        return $r['kind'] === 'throw' ? 'THROW ' . $r['val'] : json_encode(self::deepObjects($r['val']), JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
}

namespace Antevemus\ALinq\Tests\Contract\C;

use Antevemus\ALinq\Attributes\Specifiable;
use Antevemus\ADeepPropertyAccess\PropertyAccess;

/**
 * A row as an entity: private fields without getters, read by the shared accessor through
 * the non-public last resort (1.4.0), which since 1.4.1 requires the class-level opt-in.
 */
#[Specifiable]
final class Employee
{
    public function __construct(
        private int $id,
        private string $dept,
        private ?int $age,
        private int $salary,
        private ?int $c
    ) {
    }

    public function describe(): string
    {
        return sprintf('E%d', $this->id);
    }

    public static function read(mixed $row, string $field): mixed
    {
        return PropertyAccess::getValue($row, $field);
    }
}

/**
 * An equality comparer for the matrix (2.2.0): integers are equal when they have the same
 * parity, any other value is equal only to itself (by its JSON text). The hash is coarser than
 * the equality for strings (every string shares one bucket), so distinct() must ask equals().
 */
final class ParityComparer implements \Antevemus\ALinq\Interfaces\IALinqEqualityComparer
{
    public function equals(mixed $a, mixed $b): bool
    {
        return self::identity($a) === self::identity($b);
    }

    public function hash(mixed $value): string
    {
        return is_string($value) ? 'string' : self::identity($value);
    }

    private static function identity(mixed $value): string
    {
        return is_int($value) ? 'parity' . ($value % 2) : (string) json_encode($value);
    }
}
