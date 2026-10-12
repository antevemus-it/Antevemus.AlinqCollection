# Upgrading from ALinq 1.x to 2.0

<p align="center">
  <strong>🇺🇸 English</strong> &nbsp;|&nbsp; <a href="UPGRADE-2.0.pt-BR.md">🇧🇷 Português (Brasil)</a>
</p>

Version 2.0.0 removes what 1.x announced as deprecated, and nothing else: `extract()` (deprecated since 1.3.0). It also moves the property resolution to a shared package, `antevemus/adeep-property-access`, keeping `Helpers\ALinqPropertyAccess` as a deprecated facade until 3.0. No data or file format is involved, so there is nothing to convert before upgrading. The [CHANGELOG](CHANGELOG.md) lists every change; this guide is the procedure.

## Checklist

1. Remove the calls to `extract()` ([section 1](#1-extract)).
2. Optionally, replace `ALinqPropertyAccess` with `PropertyAccess` of the package ([section 2](#2-property-accessor)); the facade keeps working until 3.0.
3. Check the edge cases whose answer changed ([section 3](#3-answers-that-changed)), if your entities have getters with required parameters, private getters behind `__call()`, or `ArrayObject`/`ArrayIterator` keys holding `null`.
4. `composer require antevemus/alinq-collection:^2.0`. It brings one dependency, from the same author, with no dependencies of its own: `antevemus/adeep-property-access`. PHP 8.4 is still the minimum.

## 1. `extract()`

`ALinqCollection::extract()` and its declaration in `IALinqUtility` are gone. A method cannot export variables into the scope of its caller, so since 1.3.0 it only raised `E_USER_DEPRECATED` and returned the count of variables it would have extracted; calling it now is `Error: Call to undefined method`. Replace it with what it was meant to do, in your own scope:

```php
// 1.x (exported nothing; returned the count)
$count = $collection->extract();

// 2.0
extract($collection->toArray());          // PHP's extract(), in the caller's scope
$count = $collection->count();            // if only the count was used
```

A class of yours that implements `IALinqUtility` (or `IALinqCollection`) no longer needs to declare `extract()`; keeping it does no harm.

## 2. Property accessor

Every name ALinq reads (the query builder, `whereIn()`/`whereNotIn()`/`whereBetween()` on both collections, `createPropertySelector()`, `createPropertyComparer()`) is resolved by `Antevemus\ADeepPropertyAccess\PropertyAccess`, the package shared with ASpecification 2.0: both libraries read the same value from the same candidate by construction. The resolution order of 1.x is unchanged.

| 1.x (`Antevemus\ALinq\Helpers\ALinqPropertyAccess`) | 2.0 (`Antevemus\ADeepPropertyAccess\PropertyAccess`) |
| :--- | :--- |
| `ALinqPropertyAccess::getValue($item, 'a.b')` | `PropertyAccess::getValue($item, 'a.b')` |
| `ALinqPropertyAccess::hasProperty($item, 'a.b')` | `PropertyAccess::hasProperty($item, 'a.b')` |
| `ALinqPropertyAccess::getPropertyAccessor('a.b')` | `PropertyAccess::accessor('a.b')` |
| `ALinqPropertyAccess::getNestedPropertyAccessor('a.b')` | `PropertyAccess::accessor('a.b')` |
| `ALinqPropertyAccess::createPropertyComparer('a', 'b')` | unchanged: it belongs to ALinq and is not deprecated |

- The four deprecated methods are a thin facade (`@deprecated 2.0.0`, removed in 3.0.0) and raise no runtime notice, because they sit on the hot path of every query that names a field. Static analysers (PHPStan, Psalm, IDEs) report them.
- **`#[Antevemus\ALinq\Attributes\Specifiable]` stays** and is still the one to use in ALinq code. `#[Antevemus\ASpecification\Attributes\Specifiable]` and `#[Antevemus\ADeepPropertyAccess\Attributes\Specifiable]` are accepted as the same marker (the last one is new for ALinq).

## 3. Answers that changed

All at the edges; the cases come from the contract battery of the package. The ALinq column is what 1.5.1 answered.

| Case | ALinq 1.5.1 | ALinq 2.0.0 |
| :--- | :--- | :--- |
| Public getter with a required parameter, `getName(string $x)`, read as `name` | `getValue()` threw `ArgumentCountError` (so did `where('name', ...)`, `whereIn('name', ...)`, the selector), `hasProperty()` answered `true` | not a getter: skipped, the next step answers (a public property `$name`, `isName()`...), else `null`/`false`; optional and variadic parameters still make a getter |
| Private or protected getter in a class with `__call()` | `__call('getName', [])` ran and its result was read; `hasProperty()` `true` | `__call()` is never called: `null`/`false` |
| A name starting with `__` (`'__construct'`) | the method ran (the constructor ran again); `hasProperty()` `true` | never called: `null`/`false` |
| Empty name `''` on an object with a public `get()`, `is()` or `has()` | that method ran and its result was read; `hasProperty()` `true` | an empty name names no method: `null`/`false` |
| `hasProperty()` of an `ArrayObject`/`ArrayIterator` key holding `null` (also at the end of a path) | `false` (`isset()`) | `true` (`offsetExists()`, as a key of an array holding `null`); a missing key is still `false`; `getValue()` is `null` in both |
| Private property marked `#[Antevemus\ADeepPropertyAccess\Attributes\Specifiable]` | not recognized: `null`/`false` | read, as the ALinq and ASpecification markers |

Three edge answers ALinq already gave in 1.5.1 stay as they were (ASpecification 1.x answered differently and changed in its 2.0): an empty name on an object yields `null`; an `ArrayAccess` object answers through its offsets only (its public properties are not seen); an unset public property with `__get()` and no `__isset()` yields `null`.

## 4. ASpecification

The `suggest` of `antevemus/aspecification` is `^2.0.0`. ASpecification 2.0 resolves property names through the same package, so a specification evaluated through `ALinqBridge` and the same name read by ALinq answer alike. ASpecification 2.0 accepts ALinq `^1.5.1 || ^2.0`; its bridge uses neither `extract()` nor `ALinqPropertyAccess`.
