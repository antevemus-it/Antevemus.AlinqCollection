<?php

declare(strict_types=1);

namespace Antevemus\ALinq;

/**
 * DuplicateKeyPolicy - What toDictionary() does when the key selector produces a key twice
 *
 * Since 2.2.0 (forward 047, D10). `Throw` is the behaviour of every earlier version and the
 * default: `InvalidArgumentException('toDictionary() key ... produced twice (second time for
 * item at key ...)')`, as `Enumerable.ToDictionary` throws `ArgumentException`. `KeepFirst`
 * keeps the first element and does not run the element selector for the repeated ones.
 * `KeepLast` keeps the last element (the element selector runs for every item); the key stays
 * at the position of its first occurrence, as a PHP array assignment does. In every policy
 * the key selector runs for every item and every key is validated (int, string or BackedEnum).
 *
 * An enum, not a string: a typo is a TypeError at the call, not a branch of validation at
 * runtime.
 *
 * @version    2.2.0
 * @package    antevemus
 * @subpackage alinq
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
enum DuplicateKeyPolicy: string
{
    /** A repeated key throws InvalidArgumentException (the default, as in earlier versions). */
    case Throw = 'throw';

    /** The first element wins; the element selector does not run for the repeated keys. */
    case KeepFirst = 'first';

    /** The last element wins; the key keeps the position of its first occurrence. */
    case KeepLast = 'last';
}
