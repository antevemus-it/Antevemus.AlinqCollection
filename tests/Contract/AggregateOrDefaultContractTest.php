<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Contract;

use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\ALinqLazyCollection;
use DateTimeImmutable;
use DomainException;
use ErrorException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Throwable;
use UnderflowException;

/**
 * The aggregates with a default (2.1.0, forward 036): minOrDefault(), maxOrDefault(),
 * averageOrDefault(), minByOrDefault() and maxByOrDefault().
 *
 * D2: each answers exactly as its sibling without default, except that the sibling's
 * UnderflowException ("nothing to aggregate": empty, or only nulls where the sibling skips
 * them) becomes the default; every other exception (not comparable, not numeric, a throwing
 * selector) is the same, class and message. RN-02: eager and lazy answer the same, on the
 * cases the forward lists (empty, only nulls, mixed with nulls, one element, a tie, a value
 * that is not comparable, a selector with `($item, $key)`).
 *
 * The oracle is the sibling itself, run on the same input: the test does not write down
 * the answers again, it proves the substitution rule on every method × input, on both
 * sides, and that both sides agree.
 */
final class AggregateOrDefaultContractTest extends TestCase
{
    /**
     * @return array<string, array>
     */
    private static function inputs(): array
    {
        $first = new DateTimeImmutable('2026-01-02 00:00:00');
        $twin = new DateTimeImmutable('2026-01-02 00:00:00');

        return [
            'empty' => [],
            'only nulls' => [null, null, null],
            'mixed with nulls' => [3, null, 1, null, 2],
            'dictionary mixed with nulls' => ['a' => 3, 'b' => null, 'c' => 1],
            'one element' => [42],
            'one null' => [null],
            'tie (values)' => [5, 1, 1, 5],
            'tie (dates, two instances)' => [$first, $twin, new DateTimeImmutable('2026-03-04')],
            'tie (rows)' => [['id' => 1, 'k' => 5], ['id' => 2, 'k' => 5], ['id' => 3, 'k' => 9], ['id' => 4, 'k' => 9]],
            'not comparable' => [1, [2], 3],
            'not comparable after nulls' => [null, new stdClass()],
            'numeric strings and bools' => ['10', true, 2.5, null],
            'not numeric' => [1, 'x', 3],
        ];
    }

    /**
     * [the sibling, the variant with default] for each method and selector shape.
     *
     * @return array<string, array{\Closure, \Closure}>
     */
    private static function pairs(): array
    {
        $value = static fn($v) => is_array($v) ? ($v['k'] ?? null) : $v;
        $withKey = static fn($v, $k) => is_int($v) ? $v * 10 + (is_int($k) ? $k : 0) : $v;
        $throws = static fn($v) => throw new DomainException('selector failed');

        return [
            'min()' => [fn($c) => $c->min(), fn($c, $d) => $c->minOrDefault($d)],
            'min(sel)' => [fn($c) => $c->min($value), fn($c, $d) => $c->minOrDefault($d, $value)],
            'min(fn(v,k))' => [fn($c) => $c->min($withKey), fn($c, $d) => $c->minOrDefault($d, $withKey)],
            'min(throwing)' => [fn($c) => $c->min($throws), fn($c, $d) => $c->minOrDefault($d, $throws)],
            'max()' => [fn($c) => $c->max(), fn($c, $d) => $c->maxOrDefault($d)],
            'max(sel)' => [fn($c) => $c->max($value), fn($c, $d) => $c->maxOrDefault($d, $value)],
            'max(fn(v,k))' => [fn($c) => $c->max($withKey), fn($c, $d) => $c->maxOrDefault($d, $withKey)],
            'max(throwing)' => [fn($c) => $c->max($throws), fn($c, $d) => $c->maxOrDefault($d, $throws)],
            'average()' => [fn($c) => $c->average(), fn($c, $d) => $c->averageOrDefault($d)],
            'average(fn(v,k))' => [fn($c) => $c->average($withKey), fn($c, $d) => $c->averageOrDefault($d, $withKey)],
            'average(throwing)' => [fn($c) => $c->average($throws), fn($c, $d) => $c->averageOrDefault($d, $throws)],
            'minBy(sel)' => [fn($c) => $c->minBy($value), fn($c, $d) => $c->minByOrDefault($d, $value)],
            'minBy(fn(v,k))' => [fn($c) => $c->minBy(fn($v, $k) => is_int($k) ? -$k : $k), fn($c, $d) => $c->minByOrDefault($d, fn($v, $k) => is_int($k) ? -$k : $k)],
            'minBy(throwing)' => [fn($c) => $c->minBy($throws), fn($c, $d) => $c->minByOrDefault($d, $throws)],
            'maxBy(sel)' => [fn($c) => $c->maxBy($value), fn($c, $d) => $c->maxByOrDefault($d, $value)],
            'maxBy(fn(v,k))' => [fn($c) => $c->maxBy(fn($v, $k) => $k), fn($c, $d) => $c->maxByOrDefault($d, fn($v, $k) => $k)],
            'maxBy(throwing)' => [fn($c) => $c->maxBy($throws), fn($c, $d) => $c->maxByOrDefault($d, $throws)],
        ];
    }

    public function testEachVariantIsItsSiblingWithTheDefaultInsteadOfUnderflow(): void
    {
        $default = new stdClass();
        $failures = [];
        $kinds = ['default' => 0, 'value' => 0, 'throw' => 0];
        $total = 0;

        foreach (self::pairs() as $name => [$sibling, $variant]) {
            foreach (self::inputs() as $inputName => $array) {
                $answers = [];
                foreach (['eager' => ALinqCollection::from($array), 'lazy' => ALinqLazyCollection::from($array)] as $side => $collection) {
                    $total++;
                    $expected = self::evaluate(fn() => $sibling($collection));
                    $actual = self::evaluate(fn() => $variant($collection, $default));

                    if ($expected['kind'] === 'throw' && $expected['class'] === UnderflowException::class) {
                        $expected = ['kind' => 'value', 'val' => $default];
                        $kinds['default']++;
                    } else {
                        $kinds[$expected['kind']]++;
                    }

                    if ($actual !== $expected) {
                        $failures[] = sprintf('%s | %s | %s: expected %s, got %s', $name, $inputName, $side, self::fmt($expected), self::fmt($actual));
                    }
                    $answers[$side] = $actual;
                }
                if ($answers['eager'] !== $answers['lazy']) {
                    $failures[] = sprintf('%s | %s: eager %s, lazy %s', $name, $inputName, self::fmt($answers['eager']), self::fmt($answers['lazy']));
                }
            }
        }

        $this->assertSame(17 * 13 * 2, $total);
        $this->assertSame([], $failures, "Aggregate-or-default divergences:\n" . implode("\n", $failures));
        // the matrix exercises the three outcomes, not only one of them
        $this->assertGreaterThan(30, $kinds['default']);
        $this->assertGreaterThan(30, $kinds['value']);
        $this->assertGreaterThan(30, $kinds['throw']);
    }

    public function testTiesKeepTheFirstOnBothSides(): void
    {
        $first = new DateTimeImmutable('2026-01-02 00:00:00');
        $twin = new DateTimeImmutable('2026-01-02 00:00:00');
        $rows = [['id' => 1, 'k' => 5], ['id' => 2, 'k' => 5], ['id' => 3, 'k' => 9], ['id' => 4, 'k' => 9]];

        foreach ([ALinqCollection::class, ALinqLazyCollection::class] as $class) {
            $dates = $class::from([$first, $twin]);
            $this->assertSame($first, $dates->minOrDefault(), $class);
            $this->assertSame($first, $dates->maxOrDefault(), $class);

            $collection = $class::from($rows);
            $this->assertSame(1, $collection->minByOrDefault(null, fn($r) => $r['k'])['id'], $class);
            $this->assertSame(3, $collection->maxByOrDefault(null, fn($r) => $r['k'])['id'], $class);
        }
    }

    /**
     * @return array{kind: string, val?: mixed, class?: string, message?: string}
     */
    private static function evaluate(callable $call): array
    {
        set_error_handler(static function (int $no, string $str): bool {
            throw new ErrorException($str, $no);
        });
        try {
            return ['kind' => 'value', 'val' => $call()];
        } catch (Throwable $e) {
            return ['kind' => 'throw', 'class' => $e::class, 'message' => $e->getMessage()];
        } finally {
            restore_error_handler();
        }
    }

    private static function fmt(array $r): string
    {
        return $r['kind'] === 'throw'
            ? sprintf('THROW %s: %s', $r['class'], $r['message'])
            : (is_object($r['val']) ? $r['val']::class . '#' . spl_object_id($r['val']) : json_encode($r['val']));
    }
}
