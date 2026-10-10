<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Attributes;

use Attribute;

/**
 * Specifiable - Opt-in marker that lets ALinq read a non-public property by name
 *
 * ALinqPropertyAccess resolves a name in this order: array/ArrayAccess key, public getter
 * (`getX`/`x`/`isX`/`hasX`), `__get` guarded by `__isset`, public initialized property and,
 * as the last resort, a private or protected property read by reflection. Since 1.4.1 that
 * last step only applies to a property the author of the class marked with this attribute:
 *
 * - on a property, it makes that private or protected property readable by name;
 * - on a class, it makes readable every non-public property DECLARED by that class. It does
 *   not reach the properties of a parent class nor of a subclass: each class in the
 *   hierarchy opts in for the properties it declares.
 *
 * An unmarked non-public property resolves to `null` and `hasProperty()` answers `false`, as
 * in 1.3.x. Reason: the library only receives a name as text (in `where*()`, `orderBy()`
 * selectors, `select()`, the query builder, `createPropertySelector()`, the comparer) and
 * cannot tell code written by the author of the entity from a name that came from a request;
 * without the opt-in, filtering and ordering over a private field (for example
 * `whereBetween('password_hash', 'a', 'b')`) work as a read oracle for its value.
 *
 * `#[Antevemus\ASpecification\Attributes\Specifiable]` (ASpecification 1.6.1) is accepted as
 * the same marker; it is recognized by name, so ALinq does not depend on that package.
 *
 * @version    1.4.1
 * @package    antevemus
 * @subpackage alinq.attributes
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
final class Specifiable
{
}
