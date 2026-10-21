# 🧪 Quality Assurance & Test Coverage

The framework features extensive unit testing with **100% test pass rate** under PHP 8.4:

```bash
vendor/bin/phpunit
```

```text
====================================================================
 PHPUnit 11.5.42 - ANTEVEMUS ALINQ COLLECTION TEST SUITE
====================================================================

...............................................................  63 / 836 (  7%)
............................................................... 126 / 836 ( 15%)
............................................................... 189 / 836 ( 22%)
............................................................... 252 / 836 ( 30%)
..................................S............................ 315 / 836 ( 37%)
.............S................................................. 378 / 836 ( 45%)
............................................................... 441 / 836 ( 52%)
............................................................... 504 / 836 ( 60%)
............................................................... 567 / 836 ( 67%)
............................................................... 630 / 836 ( 75%)
............................................................... 693 / 836 ( 82%)
..S.S........S.S............................................... 756 / 836 ( 90%)
.SSSSSS........................................................ 819 / 836 ( 97%)
.S.S.S.S.....S...                                               836 / 836 (100%)

Time: 00:14.926, Memory: 26.00 MB

OK, but some tests were skipped!
Tests: 836, Assertions: 4631, Skipped: 17.
====================================================================
 RESULT: 100% SUITE PASS | 0 REGRESSIONS | 0 DEPRECATIONS
====================================================================
```

- **836 Tests & 4631 Assertions** certifying all 8 functional traits, the generator streaming engine, the eager × lazy contract and the delegation to the shared property accessor (`tests/Contract`), the properties of the 2.2.0 operators against hand-written oracles (`tests/Contract/ConsumerGapsPropertyTest.php`) and those of the 2.4.0 comparers in grouping, set operations and joins (`tests/Contract/ComparerKeyPropertyTest.php`), the §9 blocks, the 2.2.0 and 2.4.0 blocks and the Quick Start of the documentation pages as written (the same code in both languages), the documentation tree (`tests/Unit/DocumentationTreeTest.php`: the same pages, navigation and code in both languages), and the paginated HTTP sources against a local `php -S` server on 127.0.0.1 (`tests/Unit/Http`, group `http`). 2.4.0 added 64 tests and 529 assertions (587 with `ext-intl`): the comparers, the lazy `join()`/`groupJoin()` and the seed selector of `aggregateBy()` (`tests/Unit/ComparerKeyOperationsTest.php`, 31 tests, the join of 10 000 × 10 000 rows under a comparer included), their properties (8), `fromHttpPagesByIndex()` (`tests/Unit/Http/FromHttpPagesByIndexTest.php`, 19), and the three new tested blocks in both languages (6).
- **Without `ext-intl` the 17 tests that use `StringComparer::culture()` are skipped** (the 17 skipped above, on a PHP without intl: 10 of 2.2.0 and 7 of 2.4.0, the accent-insensitive grouping, its four properties and the grouping block in both languages); with it the suite runs 836 tests and 4772 assertions with 1 skipped (the test of the missing extension). The CI runs both legs.
- **Measured coverage** (pcov, 2.2.0, with `ext-intl`, PHP 8.4.26): 289 of 299 methods and 1619 of 1637 lines of `src/`; the 18 uncovered lines are defensive guards or depend on the environment: two new in `StringComparer` (ext-mbstring missing, ext-intl missing), one in the shared outer join (the unknown-kind check), fourteen on the HTTP path (ext-curl missing, curl handle setup failures, an empty transport message or effective URL, a failed socket wait, an unparseable base URL and an entry without `rel` in `link()`) and the early exit of `zip()` (covered on PHP 8.4.13). Without `ext-intl` (PHP 8.4.13): 288 of 299 methods, 1574 of 1617 lines (the `culture()` path is the difference).
- **Mutation testing** (Infection, since 2.2.0, scoped to the lines changed since 2.1.0; `infection.json5`, the Quality workflow): 415 mutants, 380 killed, 24 errors and 1 time-out (detected), 10 escaped, all equivalent (MSI 97.59%). The 8 mutants of the lines added in 2.3.0 (`from()` with the declared shape, `isList()`) were all killed (MSI 100 %, measured locally with `--git-diff-base=v2.2.2`). On the lines added or changed in 2.4.0 (measured locally against the 2.3.1 tree, PHP 8.4.13 with pcov): 257 mutants, 232 killed and 22 errors (detected), 3 escaped, all equivalent (`iterator_to_array(..., false)` over a generator that yields without keys, where `true` answers the same list), MSI 98.83 %.
- **PHP 8.4 Native Compatibility**: Verified with native `array_any`, `array_all`, `array_find`, `array_find_key`.
- **One Runtime Dependency, Same Author**: Pure PHP 8.4 library; its only requirement is [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (since 2.0.0), the property accessor shared with ASpecification, which has no dependencies of its own. No third-party package.
