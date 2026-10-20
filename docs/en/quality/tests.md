# 🧪 Quality Assurance & Test Coverage

The framework features extensive unit testing with **100% test pass rate** under PHP 8.4:

```bash
vendor/bin/phpunit
```

```text
====================================================================
 PHPUnit 11.5.42 - ANTEVEMUS ALINQ COLLECTION TEST SUITE
====================================================================

...............................................................  63 / 772 (  8%)
............................................................... 126 / 772 ( 16%)
............................................................... 189 / 772 ( 24%)
............................................................... 252 / 772 ( 32%)
.............................................S................. 315 / 772 ( 40%)
............................................................... 378 / 772 ( 48%)
............................................................... 441 / 772 ( 57%)
............................................................... 504 / 772 ( 65%)
............................................................... 567 / 772 ( 73%)
............................................................... 630 / 772 ( 81%)
...............S.......S....................................... 693 / 772 ( 89%)
........SSSSSS................................................. 756 / 772 ( 97%)
............S...                                                772 / 772 (100%)

Time: 00:05.551, Memory: 14.00 MB

OK, but some tests were skipped!
Tests: 772, Assertions: 4102, Skipped: 10.
====================================================================
 RESULT: 100% SUITE PASS | 0 REGRESSIONS | 0 DEPRECATIONS
====================================================================
```

- **772 Tests & 4102 Assertions** certifying all 8 functional traits, the generator streaming engine, the eager × lazy contract and the delegation to the shared property accessor (`tests/Contract`), the properties of the 2.2.0 operators against hand-written oracles (`tests/Contract/ConsumerGapsPropertyTest.php`), the §9 blocks, the 2.2.0 blocks and the Quick Start of the documentation pages as written (the same code in both languages), the documentation tree (`tests/Unit/DocumentationTreeTest.php`: the same pages, navigation and code in both languages), and the paginated HTTP source against a local `php -S` server on 127.0.0.1 (`tests/Unit/Http`, group `http`).
- **Without `ext-intl` the 10 tests that use `StringComparer::culture()` are skipped** (the 10 skipped above, on a PHP without intl); with it the suite runs 772 tests and 4185 assertions with 1 skipped (the test of the missing extension). The CI runs both legs.
- **Measured coverage** (pcov, 2.2.0, with `ext-intl`, PHP 8.4.26): 289 of 299 methods and 1619 of 1637 lines of `src/`; the 18 uncovered lines are defensive guards or depend on the environment: two new in `StringComparer` (ext-mbstring missing, ext-intl missing), one in the shared outer join (the unknown-kind check), fourteen on the HTTP path (ext-curl missing, curl handle setup failures, an empty transport message or effective URL, a failed socket wait, an unparseable base URL and an entry without `rel` in `link()`) and the early exit of `zip()` (covered on PHP 8.4.13). Without `ext-intl` (PHP 8.4.13): 288 of 299 methods, 1574 of 1617 lines (the `culture()` path is the difference).
- **Mutation testing** (Infection, since 2.2.0, scoped to the lines changed since 2.1.0; `infection.json5`, the Quality workflow): 415 mutants, 380 killed, 24 errors and 1 time-out (detected), 10 escaped, all equivalent (MSI 97.59%). The 8 mutants of the lines added in 2.3.0 (`from()` with the declared shape, `isList()`) were all killed (MSI 100 %, measured locally with `--git-diff-base=v2.2.2`).
- **PHP 8.4 Native Compatibility**: Verified with native `array_any`, `array_all`, `array_find`, `array_find_key`.
- **One Runtime Dependency, Same Author**: Pure PHP 8.4 library; its only requirement is [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (since 2.0.0), the property accessor shared with ASpecification, which has no dependencies of its own. No third-party package.
