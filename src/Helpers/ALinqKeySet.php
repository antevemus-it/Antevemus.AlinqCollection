<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Helpers;

use Antevemus\ALinq\Interfaces\IALinqEqualityComparer;

/**
 * ALinqKeySet - A set of keys under an equality comparer
 *
 * Since 2.4.0: the identity behind the comparer parameter of the set operations, of the
 * grouping operators and of the lookup, on both collections. The comparer's hash() is the
 * bucket and its equals() decides inside the bucket, so a lookup costs one hash() and one
 * equals() per distinct key that shares the bucket (expected O(1)), never a comparison with
 * every key. (Without a comparer the operators keep their own arrays keyed by
 * ALinqCallable::hashKey(), as in 2.3.1.)
 *
 * The first key added stays the representative of everything equal to it: find() and intern()
 * answer the key that was kept, not the one asked for.
 *
 * @internal
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq.helpers
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class ALinqKeySet
{
    /**
     * The kept keys by hash: the distinct keys that share it.
     *
     * @var array<string, list<mixed>>
     */
    private array $buckets = [];

    public function __construct(private readonly IALinqEqualityComparer $comparer)
    {
    }

    /**
     * The kept key equal to $key, wrapped in a one-element array (a kept key may be null), or
     * null when no kept key is equal to it.
     *
     * @return array{0: mixed}|null
     */
    public function find(mixed $key): ?array
    {
        return $this->findIn($this->comparer->hash($key), $key);
    }

    /**
     * Whether a kept key is equal to $key.
     */
    public function contains(mixed $key): bool
    {
        return $this->find($key) !== null;
    }

    /**
     * Keeps $key unless a key equal to it is kept already; true when it was added.
     */
    public function add(mixed $key): bool
    {
        return $this->intern($key)[1];
    }

    /**
     * The kept key equal to $key, keeping $key first when there is none: `[$kept, $added]`, where
     * $kept is $key itself when it was added. One hash() per call.
     *
     * @return array{0: mixed, 1: bool}
     */
    public function intern(mixed $key): array
    {
        $hash = $this->comparer->hash($key);
        $found = $this->findIn($hash, $key);
        if ($found !== null) {
            return [$found[0], false];
        }
        $this->buckets[$hash][] = $key;

        return [$key, true];
    }

    /**
     * @return array{0: mixed}|null
     */
    private function findIn(string $hash, mixed $key): ?array
    {
        foreach ($this->buckets[$hash] ?? [] as $kept) {
            if ($this->comparer->equals($kept, $key)) {
                return [$kept];
            }
        }

        return null;
    }
}
