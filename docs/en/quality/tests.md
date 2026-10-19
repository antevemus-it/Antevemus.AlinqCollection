# 🧪 Quality Assurance & Test Coverage

The framework features extensive unit testing with **100% test pass rate** under PHP 8.4:

```bash
vendor/bin/phpunit
```

```text
====================================================================
 PHPUnit 11.5.42 - ANTEVEMUS ALINQ COLLECTION TEST SUITE
====================================================================

...............................................................  63 / 743 (  8%)
............................................................... 126 / 743 ( 16%)
............................................................... 189 / 743 ( 25%)
............................................................... 252 / 743 ( 33%)
.............................................S................. 315 / 743 ( 42%)
............................................................... 378 / 743 ( 50%)
............................................................... 441 / 743 ( 59%)
............................................................... 504 / 743 ( 67%)
............................................................... 567 / 743 ( 76%)
.................................................S.......S..... 630 / 743 ( 84%)
..........................................SSSSSS............... 693 / 743 ( 93%)
..............................................S...              743 / 743 (100%)

Time: 00:03.376, Memory: 14.00 MB

OK, but some tests were skipped!
Tests: 743, Assertions: 3971, Skipped: 10.
====================================================================
 RESULT: 100% SUITE PASS | 0 REGRESSIONS | 0 DEPRECATIONS
====================================================================
```

- **743 Tests & 3971 Assertions** certifying all 8 functional traits, the generator streaming engine, the eager × lazy contract and the delegation to the shared property accessor (`tests/Contract`), the properties of the 2.2.0 operators against hand-written oracles (`tests/Contract/ConsumerGapsPropertyTest.php`), the §9 blocks, the 2.2.0 blocks and the Quick Start of the documentation pages as written (the same code in both languages), the documentation tree (`tests/Unit/DocumentationTreeTest.php`: the same pages, navigation and code in both languages), and the paginated HTTP source against a local `php -S` server on 127.0.0.1 (`tests/Unit/Http`, group `http`).
- **Without `ext-intl` the 10 tests that use `StringComparer::culture()` are skipped** (the 10 skipped above, on a PHP without intl); with it the suite runs 743 tests and 4054 assertions with 1 skipped (the test of the missing extension). The CI runs both legs.
- **Measured coverage** (pcov, 2.2.0, with `ext-intl`, PHP 8.4.26): 289 of 299 methods and 1619 of 1637 lines of `src/`; the 18 uncovered lines are defensive guards or depend on the environment: two new in `StringComparer` (ext-mbstring missing, ext-intl missing), one in the shared outer join (the unknown-kind check), fourteen on the HTTP path (ext-curl missing, curl handle setup failures, an empty transport message or effective URL, a failed socket wait, an unparseable base URL and an entry without `rel` in `link()`) and the early exit of `zip()` (covered on PHP 8.4.13). Without `ext-intl` (PHP 8.4.13): 288 of 299 methods, 1574 of 1617 lines (the `culture()` path is the difference).
- **Mutation testing** (Infection, since 2.2.0, scoped to the lines changed since 2.1.0; `infection.json5`, the Quality workflow): 415 mutants, 380 killed, 24 errors and 1 time-out (detected), 10 escaped, all equivalent (MSI 97.59%).
- **PHP 8.4 Native Compatibility**: Verified with native `array_any`, `array_all`, `array_find`, `array_find_key`.
- **One Runtime Dependency, Same Author**: Pure PHP 8.4 library; its only requirement is [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (since 2.0.0), the property accessor shared with ASpecification, which has no dependencies of its own. No third-party package.
