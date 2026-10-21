<?php

declare(strict_types=1);

namespace Antevemus\ALinq;

use Antevemus\ALinq\Helpers\ALinqKeySet;
use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;
use ArrayAccess;
use BackedEnum;
use Countable;
use Generator;
use IteratorAggregate;
use JsonSerializable;
use LogicException;

/**
 * ALinqLookup - An immutable one-to-many dictionary (the `ILookup<TKey, TElement>` of .NET)
 *
 * Since 2.2.0 (forward 047, D8/D9), returned by toLookup() of both collections: a groupBy()
 * already materialized. The keys are in the order of their first appearance in the source,
 * the elements of each key in source order; a key follows the rule of groupBy() (an int, a
 * string or a BackedEnum, by its value; `1` and `'1'` are the same key, as PHP array keys).
 *
 * - get($key): the elements of that key as an ALinqCollection list, or an EMPTY collection
 *   when the key does not exist (never throws, as `lookup[key]` in .NET).
 * - contains($key), keys(), count() (the number of keys).
 * - Iteration: `key => ALinqCollection`, the shape of groupBy().
 * - toArray(): `key => list of elements`; json_encode() encodes the same.
 * - Read-only ArrayAccess: `$lookup['x']` is get('x'), `isset($lookup['x'])` is contains('x');
 *   writing or unsetting throws LogicException.
 *
 * Since 2.4.0 toLookup() may take an IALinqEqualityComparer: the keys equal under it are one
 * group, named by the first of them seen, and the lookup keeps the comparer, so get() and
 * contains() find a group by any key equal to its name (`get('SAO PAULO')` finds the group
 * `'São Paulo'` under an accent- and case-insensitive comparer, as `lookup[key]` does in .NET).
 *
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 *
 * @implements IteratorAggregate<array-key, ALinqCollection>
 * @implements ArrayAccess<int|string|BackedEnum, ALinqCollection>
 */
final class ALinqLookup implements Countable, IteratorAggregate, ArrayAccess, JsonSerializable
{
    /**
     * @var array<array-key, list<mixed>>
     */
    private readonly array $groups;

    /**
     * The names of the groups under the comparer given to toLookup() (2.4.0); null without one.
     */
    private readonly ?ALinqKeySet $names;

    /**
     * Built by toLookup(); each group is kept as a list (reindexed).
     *
     * @param array<array-key, array<mixed>> $groups key => elements, keys in first-appearance order
     * @param IALinqEqualityComparer|null $comparer The identity of the keys for get() and contains() (since 2.4.0)
     * @param list<int|string>|null $keys The key of each group as the comparer must see it, in the
     *                                    order of $groups (a numeric string is an int array key);
     *                                    the array keys when null (since 2.4.0)
     */
    public function __construct(array $groups, ?IALinqEqualityComparer $comparer = null, ?array $keys = null)
    {
        $lists = [];
        foreach ($groups as $key => $elements) {
            $lists[$key] = array_values($elements);
        }
        $this->groups = $lists;

        $names = null;
        if ($comparer !== null) {
            $names = new ALinqKeySet($comparer);
            foreach ($keys ?? array_keys($lists) as $key) {
                $names->add($key);
            }
        }
        $this->names = $names;
    }

    /**
     * The elements of a key, in source order, or an empty collection when the key does not exist.
     */
    public function get(int|string|BackedEnum $key): ALinqCollection
    {
        $name = $this->name($key);

        return new ALinqCollection($name === null ? [] : $this->groups[$name[0]]);
    }

    /**
     * Whether the key exists (under the comparer of toLookup(), when it was given one).
     */
    public function contains(int|string|BackedEnum $key): bool
    {
        return $this->name($key) !== null;
    }

    /**
     * The key of the group that $key names, wrapped, or null when there is none: the key itself
     * when it is a group key (a key is equal to itself under any comparer, and `10` finds the
     * group `'10'`, whose array key is the int 10), otherwise the group name equal to it under
     * the comparer, if any.
     *
     * @return array{0: int|string}|null
     */
    private function name(int|string|BackedEnum $key): ?array
    {
        $key = self::arrayKey($key);
        if (array_key_exists($key, $this->groups)) {
            return [$key];
        }

        return $this->names?->find($key);
    }

    /**
     * The keys, in the order of their first appearance in the source.
     *
     * @return list<array-key>
     */
    public function keys(): array
    {
        return array_keys($this->groups);
    }

    /**
     * The number of keys (`ILookup.Count`).
     */
    public function count(): int
    {
        return count($this->groups);
    }

    /**
     * `key => ALinqCollection`, in first-appearance order.
     *
     * @return Generator<array-key, ALinqCollection>
     */
    public function getIterator(): Generator
    {
        foreach ($this->groups as $key => $elements) {
            yield $key => new ALinqCollection($elements);
        }
    }

    /**
     * `key => list of elements`.
     *
     * @return array<array-key, list<mixed>>
     */
    public function toArray(): array
    {
        return $this->groups;
    }

    /**
     * JsonSerializable: the same as toArray().
     *
     * @return array<array-key, list<mixed>>
     */
    public function jsonSerialize(): array
    {
        return $this->groups;
    }

    /**
     * `isset($lookup[$key])`: contains(); false for an offset that cannot be a key.
     */
    public function offsetExists(mixed $offset): bool
    {
        if (!is_int($offset) && !is_string($offset) && !$offset instanceof BackedEnum) {
            return false;
        }

        return $this->contains($offset);
    }

    /**
     * `$lookup[$key]`: get() (an empty collection for a missing key).
     */
    public function offsetGet(mixed $offset): ALinqCollection
    {
        return $this->get($offset);
    }

    /**
     * @throws LogicException Always: a lookup is immutable
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('ALinqLookup is immutable.');
    }

    /**
     * @throws LogicException Always: a lookup is immutable
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('ALinqLookup is immutable.');
    }

    private static function arrayKey(int|string|BackedEnum $key): int|string
    {
        return $key instanceof BackedEnum ? $key->value : $key;
    }
}
