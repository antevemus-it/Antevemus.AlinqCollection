# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.3.0] - 2026-10-20

A MINOR release: `ALinqLazyCollection::from()` takes the declared shape of a generator factory's stream, `isList()` answers the shape a lazy collection knows, and the companion package `antevemus/alinq-revolt` 1.0.0 (the async collection of ROADMAP milestone 12), which builds its stages with it, is suggested and documented. Additive for callers; classes that implement `IALinqLazyCollection` on their own must add the parameter and the method.

### Added

- **`ALinqLazyCollection::from(iterable|callable $source, ?bool $isList = null)`**, on the class and on `IALinqLazyCollection`. With a Closure factory, `$isList` declares the shape of the stream it produces, which the collection cannot know otherwise: `true`, a list (`toArray()` is a list whatever keys the generator yields; the operators that renumber a list, such as `where()`, `select()`, `take()`, renumber; the stage itself and `remember()` still stream the keys as yielded); `false`, a dictionary (the keys are kept, so an integer-keyed dictionary `[$id => $row]` keeps them in `toArray()`, and a repeated key appends instead of overwriting, as for an array dictionary); `null` (default), the unknown shape of 2.2 (keys streamed as yielded, `toArray()` a list when every key is an integer). Before → after, a generator of `5 => 'a', 9 => 'b'`: `from($factory)->toArray()` is `['a', 'b']` (unchanged); `from($factory, false)->toArray()` is `[5 => 'a', 9 => 'b']`. With any source but a Closure (an array, a callable array, a `Traversable`, a `Generator` object) a non-null `$isList` throws `InvalidArgumentException('ALinqLazyCollection::from() accepts $isList only with a Closure factory, <type> given: the shape of an iterable is known or inferred from the iterable itself')`, so the declaration can never contradict an array. `@version 2.3.0` on `ALinqLazyCollection` and `IALinqLazyCollection`.
- **`ALinqLazyCollection::isList(): ?bool`**, on the class and on `IALinqLazyCollection`: the shape of the stream as the collection knows it. `true`, a list (the renumbering operators renumber and `toArray()` returns a list); `false`, a dictionary (keys kept); `null`, unknown (a generator-factory source without a declaration, a `Traversable`, a `Generator` object). An array answers `array_is_list()` (so `[]` is a list, as the eager `isList()`); `range()`, `empty()` and the file, CSV, cursor and HTTP sources are lists; each operator answers the shape it produces (`where()`, `select()`, `take()`, `orderBy()`, `remember()` keep their source's; `selectMany()`, `concat()`, `chunk()` are lists; `groupBy()`, `countBy()`, `aggregateBy()` dictionaries). With `from($factory, $isList)` it lets a wrapper pass the shape of a lazy source on (`antevemus/alinq-revolt` does). No class implements both `IALinqCollection` and `IALinqLazyCollection`, so the name shared with the eager `isList(): bool` collides nowhere; the parity guard lists it among the non-query methods, and a test checks that both agree over the same arrays. Classes that implement `IALinqLazyCollection` on their own must add it.
- **`tests/Unit/LazyShapeDeclarationTest.php`** (29 tests, 151 assertions): the three declarations, the refusal for five kinds of iterable, the 12 operators that read the shape (`where`, `whereNot`, `select`, `take`, `skip`, `takeWhile`, `skipWhile`, `distinct`, `pad`, `tap`, `orderByDescending`, `exceptByKeys`) answering over a declared stream as over an array of the same shape (streamed pairs and `toArray()`), `isList()` after each kind of source and after 10 operators over a list, a dictionary and an unknown source, its agreement with the eager `isList()` over six arrays, and the signatures on the interface and the class.

### Documentation

- **`composer.json` `suggest`** lists `antevemus/alinq-revolt` (`^1.0`): `ALinqAsyncCollection` with `selectAsync()`/`whereAsync()`/`selectManyAsync()`/`eachAsync()` over the Revolt event loop (Fibers, Amp futures) and an ordered, bounded window of items in flight, for per-item I/O against asynchronous SDKs. It is a separate package (MIT, same author) that requires this one (`^2.3`, for the parameter above), `revolt/event-loop` and `amphp/amp`; nothing here depends on it.
- **`ROADMAP.md`, milestone 12**: the async collection for async SDKs moves from "companion package, planned" to "companion package shipped", pointing to `antevemus/alinq-revolt` 1.0.0, with the measurement of that package (16 requests of 200 ms: 214 ms with the Amp client at concurrency 16, about 3.2 s with a blocking client); the note that a Fiber only gains time on non-blocking I/O stays. CPU parallelism stays planned.
- **`docs/<language>/api/lazy-streaming.md`** (§10, both languages): after the description of `fromHttpPages()`, one sentence points to the companion package and one describes the `$isList` parameter and `isList()`. Prose only: the PHP blocks are unchanged.
- **`docs/<language>/project/roadmap.md`**: the 2.3.0 entry, and the "Future" line keeps only CPU parallelism.
- The Release badge of both READMEs is v2.3.0; the Tests badge, the Quality lines, `quality/tests.md` (both languages) and `tests/README.md` carry the counts of 2.3.0.

### Notes

- **Suite.** Without `ext-intl` (PHP 8.4.13): 743 → 772 tests, 3951 → 4102 assertions, 10 skipped. With it (PHP 8.4.26, in a container): 743 → 772 tests, 4034 → 4185 assertions, 1 skipped. +29 tests and +151 assertions on both legs, all in `LazyShapeDeclarationTest`; `ParityTest` keeps its counts (`isList` joins the list of non-query methods of its guard). The new links are absolute URLs, which the documentation guard does not count among the relative links, so its counts stay.
- **`src/`**: `ALinqLazyCollection::from()`, the new `ALinqLazyCollection::isList()`, and their interface declarations; no other code changes. The workflows are unchanged.
- **Mutation testing** of the new lines (Infection 0.35.6 in the container of the project, PHP 8.4 with `pcov`, `--git-diff-lines --git-diff-base=v2.2.2`): 8 mutants, 8 killed, MSI 100 %. The Quality workflow keeps its scope (the lines changed since 2.1.0).

## [2.2.2] - 2026-10-20

A PATCH release with documentation only: the Portuguese code blocks of the documentation now carry the code of the English ones, and the last references to README sections point to the documentation pages. No code change in `src/` beyond docblocks.

### Documentation

- **The 20 Portuguese PHP blocks with their own identifiers and strings now have the code of the English blocks**, token by token (everything but comments and whitespace). Their comments stay in Portuguese; a comment that states a result states the result of the English values (9 comments: `ORD-3`, `ORD-2`, the `countBy()` array and `'ORD-4'` in §4, `['PENDING', 'PENDING', 'PENDING']` in §1, the `$order`/`$customer` pair in §3, the file names and `IT 9000, IT 7000, IT 7000, Sales 5000` in §7, the tags in §5). The English blocks are byte-identical to 2.2.1. Every block keeps its structure (the same number of tokens in each block); only identifiers and literals changed, page by page (`docs/pt-BR`):
  - `concepts/aspecification-synergy` (How It Works): variables (`$clienteElegivel` → `$isEligibleCustomer`, `$dadosClientes` → `$rawCustomerData`), the property `scoreCredito` → `creditScore`, `'ATIVO'` → `'ACTIVE'`, the projected keys `'nome'`/`'faixa'` → `'name'`/`'tier'`.
  - `api/factories` (§1): variables and `'PENDENTE'` → `'PENDING'`.
  - `api/filtering` (§2, 2 blocks): variables (`$colecao` → `$collection`, `$equipe` → `$staff`), keys `'nome'`/`'depto'`/`'idade'` → `'name'`/`'dept'`/`'age'`, values `'TI'`/`'RH'`/`'Vendas'` → `'IT'`/`'HR'`/`'Sales'`.
  - `api/joining` (§3, 2 blocks): variables and closure parameters (`$f`/`$d` → `$emp`/`$dept`, `$p` → `$o`), keys (`'depto_id'` → `'dept_id'`, `'cliente'` → `'customer'`, the projected keys), values `'Engenharia'` → `'Engineering'`, `'Beatriz'` → `'Beatrice'`.
  - `api/aggregations` (§4): variables, keys `'codigo'`/`'valor'` → `'code'`/`'amount'`, codes `'PED-n'` → `'ORD-n'`, statuses `'CONCLUIDO'`/`'PENDENTE'`/`'ESTORNADO'`/`'CANCELADO'` → `'COMPLETED'`/`'PENDING'`/`'REFUNDED'`/`'CANCELLED'`.
  - `api/projections` (§5): variables, `'preco'` → `'price'`, the tags in English.
  - `api/grouping` (§6): variables, keys, categories (`'Alimentacao'`/`'Transporte'`/`'Lazer'` → `'Food'`/`'Transport'`/`'Leisure'`) and the projected keys (`'quantidade_itens'`/`'gasto_medio'` → `'transaction_count'`/`'average_spend'`).
  - `api/ordering` (§7): variables, the file names (`arquivoN.txt` → `fileN.txt`), keys and values, the closure parameter `$c` → `$e`.
  - `api/query-builder` (§8, 2 blocks): variables, fields (`'scoreCredito'`, `'idade'`, `'pais'`, `'nome'` → `'creditScore'`, `'age'`, `'country'`, `'name'`), statuses `'APROVADO'`/`'ANALISE'`/`'REPROVADO'` → `'APPROVED'`/`'REVIEW'`/`'REJECTED'`.
  - `api/property-access` (§9, 2 blocks): the path `usuario.perfil.empresa.cnpj` → `user.profile.organization.taxId`, the class `NotaFiscal` → `Invoice` with its properties (`numero`/`codigoAprovacao` → `number`/`approvalCode`), `'X9-SEGREDO'` → `'X9-SECRET'`, `'inexistente'` → `'missing'`. The 8 written answers of each language were already the English values and stay (`// Retorna: '12.345.678/0001-90'`, `99.9`, `'NF-001'`, `true`, `null`, `false`, `null`, `0`); `ReadmePropertyAccessTest` still runs both blocks of each language and checks the 8 answers.
  - `api/lazy-streaming` (§10, 6 blocks): the log example (the regex group `data` → `time`, the keys `'timestamp'`/`'message'`, `'desconhecida'` → `'unknown'`), the CSV example (`/var/data/clientes.csv` → `/var/data/customers.csv`, `'plano'`/`'ativo'`/`'clienteId'` → `'plan'`/`'active'`/`'customerId'`, the `syslog` message in English), the PDO cursor (the SQL in English), `remember()` (`buscarMetricasRemotas()` → `fetchRemoteMetrics()`, `$media` → `$average`), the lazy ordering (`$ordenados` → `$sorted`) and the irregular input (`'clientes.csv'` → `'clients.csv'`).
- **Prose**: no line changes; no Portuguese paragraph names an identifier, literal or value of these blocks.
- **The EN × pt-BR guard has no exceptions**: `DocumentationTreeTest` declares no localized block, so each of the 29 PHP blocks must be equal token by token in both languages (2.2.1 declared these 20 and pinned their tokens by hash).
- **No README section references are left outside the history of this changelog**; each points to its page: the `suggest` of `antevemus/aspecification` in `composer.json` ("README § Synergy" → `docs/en/concepts/aspecification-synergy.md`), `ROADMAP.md` ("README §11" → `docs/en/api/contract.md`, twice; "README §7" → `docs/en/api/ordering.md`), the PHPDoc of `groupJoin()` in `src/Traits/JoiningOperations.php` ("README §3" → `docs/en/api/joining.md`; `@version 2.2.2`), comments in the tests (`JoiningOperationsTest` §3, `ALinqQueryBuilderTest` §8, `ALinqPropertyAccessTest` §9, `GroupingOperationsTest` §6, `FromHttpPagesTest` and `tests/Unit/Http/router.php` §10, `tests/README.md` §11) and the comment of the CI workflow on the culture block (`docs/en/api/ordering.md`).
- `quality/tests.md` (both languages), the README badges and quality lines and `tests/README.md` carry the counts of 2.2.2.

### Notes

- **Suite.** Without `ext-intl` (PHP 8.4.13): 743 tests, 3971 → 3951 assertions, 10 skipped. With it (PHP 8.4, the second CI leg): 743 tests, 4054 → 4034 assertions, 1 skipped. −20 assertions on both legs, all in `DocumentationTreeTest::testEveryPageHasTheSameCodeInBothLanguages` (91 → 71): each of the 20 blocks took two assertions (the languages still differ; the pinned hashes) and now takes one (the tokens are equal). The tests and the skipped tests are the same. In a copy with only the public list, the same counts.
- **No code change in `src/` beyond docblocks** (one file, `src/Traits/JoiningOperations.php`); the CI workflow changes in a comment only.

## [2.2.1] - 2026-10-19

A PATCH release with documentation only: the README becomes an index, and the official documentation moves to `docs/en` and `docs/pt-BR`, one page per topic, in the Docs Kit format. No code change in `src/`.

### Documentation

- **The documentation moved to `docs/en` and `docs/pt-BR`.** Each language has the same 19 pages with the same English kebab-case file names, an `index.md` and a `docs-nav.json` (`{"items": [...]}`: the root `index` first, then one group per folder, `path` without extension), in 5 folders: `getting-started` (`index`: installation, the Git VCS repository and the Quick Start), `concepts` (`origins`, `core-features`, `architecture`, `aspecification-synergy`), `api` (`factories` §1, `filtering` §2, `joining` §3, `aggregations` §4, `projections` §5, `grouping` §6, `ordering` §7, `query-builder` §8, `property-access` §9, `lazy-streaming` §10, `contract` §11), `quality` (`tests`) and `project` (`roadmap`, `license-and-authors`). Every paragraph, table, note and code block of the 2.2.0 README lands in exactly one page (License & Authors also stays in the README, as short as before). Each API page keeps its `### N.` heading and every `####` subsection, so the older entries of this changelog that cite "README §3", "README §9" or "README §11" point to `docs/<language>/api/joining.md`, `api/property-access.md` and `api/contract.md`. The 29 PHP blocks are byte-identical to 2.2.0 in both languages, and the seven `<!-- tested: id -->` markers travel with their blocks.
- **Changed in the moved text:** each section heading of the README is the title of its page (`##` becomes `#`; an API page is titled after its section and keeps the `### N.` heading below the title); links to files of the repository root (`UPGRADE-2.0.md`, `UPGRADE-2.0.pt-BR.md`, `ROADMAP.md`, `LICENSE`) are absolute GitHub URLs on the pages, because the Docs Kit reads only the language folder; the two blockquote notes of the README (the divergence from .NET in `exceptBy()`/`intersectBy()`, §3, and the loose `in`/`notIn` of the query builder, §8) are `> [!WARNING]` and `> [!NOTE]` callouts, their text unchanged; the Quick Start carries the marker `<!-- tested: quick-start -->`; `quality/tests.md` has the PHPUnit report and the counts of 2.2.1, and names the documentation pages and their tree among what the tests certify.
- **New text, and only this:** the `index.md` of each language (the presentation and a map of the pages, one line each) and the two navigations.
- **The README is an index** (EN and pt-BR, same structure): header and badges (Release v2.2.1; the Tests badge, 743 passed and 3971 assertions, now opens `docs/<language>/quality/tests.md`), the presentation, the Quick Start (its only code block, byte-identical to the one of `getting-started/index.md`), installation in one paragraph (`composer require` inline), the documentation index (one line per page, both languages, `§N` next to each API page), quality in six lines, the roadmap (links) and License & Authors. `README.md` 72 307 → 9 893 bytes, `README.pt-BR.md` 75 250 → 10 188 bytes. The PHPUnit report and the long quality section are on `quality/tests.md`, the release list on `project/roadmap.md`.
- **The public line carries `docs/en` and `docs/pt-BR`, and only them**: the rest of `docs/` stays internal. They are in the Composer archive too (no `export-ignore`), so the documentation is available offline.
- **A guard of the tree**, `tests/Unit/DocumentationTreeTest.php`, 8 tests and 960 assertions: (a) the same files in both languages (5 assertions); (b) both navigations parse, have the same shape and list every page, and every `path` exists (253: 25 items per language, 20 of them pages); (c) every relative link of a page or a README resolves (143: 140 links, 38 in the two `index.md` and 102 in the READMEs); (d) every page has the same code fences and the same PHP blocks token by token in both languages, the localized ones pinned (91); (e) the PHP blocks per page are the ones declared, 29 per language (45); (f) each README has exactly one code block, the Quick Start, and stays below 25 000 bytes (8); (g) the Quick Start of each README and of each `getting-started/index.md` is a tested block (9); (h) no page or README cites internal material or links outside the language folders but to the public root files (406: 6 checks on each of the 44 files, plus the 140 links).
- **The EN × pt-BR guard now covers all 29 blocks**; until 2.2.0 only the seven tested blocks had one. 9 are equal token by token: the Quick Start, the paginated HTTP example of §10 and the seven tested blocks. The other 20 (How It Works, §1, §2 ×2, §3 ×2, §4, §5, §6, §7, §8 ×2, §9 ×2, §10 ×6) have had Portuguese identifiers and strings since they were written (`$pedidos` for `$orders`, `NotaFiscal` for `Invoice`); they are declared in the test, must still differ, and the tokens of each language are pinned, so changing one of them in a single language fails until it is declared again. Their code is unchanged.
- **The Quick Start runs**: `ReadmeConsumerGapsTest` executes it from `getting-started/index.md` and from each README with its 4 written answers (the `Result:` list, `true`, `false`, `80.4`). Its reader learned the `// Result: <literal>` line, a scalar answer followed by a remark in parentheses (`// true (via array_any)`), an elided literal as prose (`// ['id' => 1, 'name' => 'Alice'...]`), and a block written as a whole file (`<?php` and `declare(strict_types=1);`); the seven 2.2.0 blocks keep the same answers.
- **The tests that read the README read the pages**: `ReadmeConsumerGapsTest` takes the tested blocks from the API pages (`3-keys` from `api/joining.md`, `5-dictionary` from `api/projections.md`, `6-lookup` from `api/grouping.md`, `7-ordinal` and `7-culture` from `api/ordering.md`, `10-grouping` and `10-csv` from `api/lazy-streaming.md`), and `ReadmePropertyAccessTest` cuts §9 from `api/property-access.md`, to the next `##` heading or the end of the page.

### Notes

- **Suite.** Without `ext-intl` (PHP 8.4.13): 743 tests, 3971 assertions, 10 skipped (2.2.0: 731, 2968, 10). With it (PHP 8.4, the second CI leg): 743 tests, 4054 assertions, 1 skipped (2.2.0: 731, 3051, 1). +12 tests and +1003 assertions on both legs: `DocumentationTreeTest` +8 tests and +960 assertions; `ReadmeConsumerGapsTest` +4 tests (the Quick Start of the two pages and of the two READMEs, 7 assertions each: 28) and +17 assertions in its EN × pt-BR guard (the README check and the markers of each page), +45 in all; `ReadmePropertyAccessTest` −2 (the §10 heading it no longer looks for, once per language). The skipped tests are the same: the 10 `StringComparer::culture()` tests without `ext-intl`, the missing-extension test with it. In a copy with only the public list, the same counts.
- **No code change in `src/`**; the workflows are unchanged. `tests/README.md` lists the new guard and the new counts.

## [2.2.0] - 2026-10-19

Minor release: the gaps found by the audit of the first production consumer of the library (ROADMAP milestone 14, Reversa forward 047). Additive for callers: every answer, exception and message of 2.1.0 stays, except the lazy `max()`/`maxBy()` over values that do not compare both ways, which now answer as the eager side (see Changed). Interface signatures change for classes that implement the interfaces on their own (see the interface note).

### Added

- **`Comparison\StringComparer`**, the `StringComparer` of .NET: `StringComparer::ordinal()` (bytes, no extension), `StringComparer::ordinalIgnoreCase()` (simple Unicode case folding, needs ext-mbstring) and `StringComparer::culture(string $locale, bool $ignoreCase = false, bool $ignoreAccents = false, bool $numeric = false)` (an ICU `Collator`, needs ext-intl: `culture('pt_BR')` sorts `Álvaro` before `Bruno`; `ignoreAccents: true` makes `Ética` equal to `Etica`; `numeric: true` sorts `item2` before `item10`). `compare()` answers -1/0/1, `equals()`, `hash()` (compatible with `equals()`) and `__invoke()` (= `compare()`), so one object is accepted by every `callable $comparer` of the library. `null` comes before every text; a `Stringable` compares by its text; any other value throws `InvalidArgumentException('StringComparer expects strings or null, <type> given')`. `culture()` without ext-intl throws `LogicException('StringComparer::culture() requires the intl extension (ext-intl).')` at the factory; a locale ICU does not know (it would fall back to the root rules) throws `InvalidArgumentException('Unknown locale for StringComparer::culture(): "<locale>"')`; text that is not valid UTF-8 throws with the ICU error, never the text. `ordinal()` is not the default comparison: without a comparer the operators keep using PHP's `<=>`, which reads numeric strings as numbers.
- **`Interfaces\IALinqEqualityComparer`** (`equals(mixed, mixed): bool`, `hash(mixed): string`), the `IEqualityComparer<T>` of .NET; `StringComparer` implements it.
- **A comparer on the ordering and extreme operators**, both collections: `orderBy()`/`orderByDescending(callable $keySelector, ?callable $comparer = null)` (the rule `thenBy()` already had), `min()`/`max(?callable $selector = null, ?callable $comparer = null)`, `minOrDefault()`/`maxOrDefault(mixed $default = null, ?callable $selector = null, ?callable $comparer = null)`, `minBy()`/`maxBy(callable $keySelector, ?callable $comparer = null)`, `minByOrDefault()`/`maxByOrDefault(mixed $default, callable $keySelector, ?callable $comparer = null)` (the `IComparer` overloads of .NET 6). With a comparer `min()`/`max()` still skip `null`, and the comparer, not `comparableValue()`, decides what can be compared.
- **An equality comparer on `distinct(?callable $keySelector = null, ?IALinqEqualityComparer $comparer = null)` and `distinctBy(callable $keySelector, ?IALinqEqualityComparer $comparer = null)`**: `hash()` is the bucket, `equals()` decides inside it; still O(n), first occurrence wins.
- **`toLookup(callable $keySelector, ?callable $elementSelector = null): ALinqLookup`** on both collections and **`ALinqLookup`**, an immutable one-to-many dictionary (the `ILookup<TKey, TElement>` of .NET): `get($key)` gives the elements of a key as an `ALinqCollection`, or an empty collection for a missing key (never throws); `contains()`, `keys()` (first-appearance order), `count()` (number of keys), iteration as `key => ALinqCollection` (the shape of `groupBy()`), `toArray()`, `JsonSerializable`, read-only `ArrayAccess` (writing throws `LogicException('ALinqLookup is immutable.')`).
- **`toDictionary(..., DuplicateKeyPolicy $onDuplicate = DuplicateKeyPolicy::Throw)`** and the enum **`DuplicateKeyPolicy`** (`Throw`, `KeepFirst`, `KeepLast`). `Throw` is the 2.1.0 behaviour, same message. `KeepFirst` keeps the first element and does not run the element selector for the repeats; `KeepLast` keeps the last, at the position of the first occurrence (`$map[$row->id] = $row`). Every key is validated in every policy.
- **Grouping on the lazy collection**: `groupBy()`, `countBy()` and `aggregateBy()` as deferred stages (nothing runs until the first traversal, each traversal reads the upstream once; `groupBy()` keeps every item, `countBy()` one integer per key, `aggregateBy()` one accumulator per key), and `toDictionary()`/`toLookup()` as terminals that read the stream once. The answers, exception classes and messages are the eager ones (`ParityTest`).
- **`exceptByKeys(iterable $keys, callable $keySelector)` and `intersectByKeys(...)`** on both collections: the `ExceptBy`/`IntersectBy` of .NET 6, whose second argument is a sequence of keys (an array, a generator, an `ALinqCollection`, an `ALinqLazyCollection`), read once; the selector runs on this collection only; a set operation by strict identity, a `null` key matches a `null` in `$keys`. On the lazy side a stage that reads the keys first and then streams. Before → after, the consumer's documents without a participant:
  ```php
  $general = $docs->where(fn($d) => !$links->any(fn($l) => $l->id_document == $d->id)); // before: any() per document
  $general = $docs->exceptByKeys($links->select(fn($l) => (int) $l->id_document), fn($d) => (int) $d->id); // after
  ```
- **`fromCsv(?string $separator = ',')`**: `separator: null` detects the separator on every traversal from the first non-blank record, read with the enclosure rules, up to 64 KiB: `,` `;` tab `|`, the most frequent outside quotes wins; a tie throws `RuntimeException('Cannot detect the CSV separator of "<path>": "," and ";" both appear N times in the first record; pass $separator')`; none of them is `,`; a stream that cannot seek or a first record over 64 KiB throws. The default is still `','`.
- **`implode(string $separator = '', ?callable $selector = null): string`** on both collections (the lazy one streams): strings, ints, floats and `Stringable` by their text, `null` as `''` (as `string.Join`); a bool, an array or another object throws `InvalidArgumentException('implode() expects strings, numbers, Stringable or null, <type> found at key <key>')` instead of PHP's `"1"`/`""` or its "Array to string conversion" warning.
- **`suggest`**: `ext-intl` (required by `StringComparer::culture()`) and `ext-mbstring` (required by `StringComparer::ordinalIgnoreCase()`); neither is required.
- **Mutation testing**: `infection/infection` in `require-dev`, `infection.json5` and the scheduled Quality workflow, scoped to the lines changed since 2.1.0 (`--git-diff-lines --git-diff-base=v2.1.0`). Measured (Infection 0.35.6, PHP 8.4.26 with intl and pcov): 415 mutants, 380 killed, 24 errors and 1 time-out (detected), 10 escaped, MSI 97.59%; the 10 escaped are equivalent (a ±1 change of the direction of `orderBy*()`, whose sign is all that counts; `true` → `false` in a set read by `isset()`; the `(string)` cast before a concatenation; the `finally` that closes a CSV handle the generator's destruction closes anyway; a read limit one byte larger). No minimum score is imposed yet.
- **CI**: the test job runs two legs, PHP 8.4 with ext-intl and without it.
- Tests: `StringComparerTest`, `ComparerOperationsTest`, `LookupAndDictionaryTest`, `LazyGroupingTest` (deferred stages, a source that refuses a second opening, `countBy()` over 200 000 items under a 1 MiB peak growth while `groupBy()` crosses it, a SQLite cursor), `KeyedSetAndJoinTest`, `CsvSeparatorDetectionTest`, `ImplodeTest`, `ConsumerExamplesTest` (the consumer's three orderings by an accented name, its hand-written index and grouping, its documents without a participant and its CSV reader, each run against the transcribed original), `ReadmeConsumerGapsTest` (the seven README blocks of 2.2.0 executed as written, the same code in EN and pt-BR) and `tests/Contract/ConsumerGapsPropertyTest.php` (300 generated inputs per property against the hand-written loops: `toLookup` × `$m[$k][] = $x` and `groupBy`, `KeepFirst`/`KeepLast` × the hand-written index, `countBy`/`aggregateBy` × the hand-written counters, `exceptByKeys` × `where(!in_array)->distinctBy()`, and the laws of every comparer); `ParityTest` gained the new operators and parameters (the row matrix goes from 56 to 68 comparisons). Suite **731 tests, 2968 assertions** without ext-intl, 10 skipped (from 598/2321); with ext-intl 731 tests, 3051 assertions, 1 skipped. Measured coverage (pcov, with ext-intl): 289 of 299 methods, 1619 of 1637 lines of `src/`.

### Changed

- **`join(iterable $inner, ...)` and `groupJoin(iterable $inner, ...)`** (were `array $inner`): any iterable, read once; every array keeps its answer.
- **The eager `countBy()` and `aggregateBy()` no longer build the groups** (one counter or accumulator per key): same answers and messages. `$func` of `aggregateBy()` is now called in source order instead of group by group, which only shows through side effects; the message of an invalid key still names `groupBy()`, as since 1.3.0.
- **One extreme comparison for both collections** (`ALinqContract::isMoreExtreme()`). The lazy side compared with `<`/`>` and the eager side with `<=>`; they disagreed on values that do not compare both ways, a violation of the parity contract. Before → after, lazy only: `ALinqLazyCollection::from([NAN, 1, 2])->max()` was `NAN`, now `2` (the eager answer, as .NET's `Max`); `maxBy()` over arrays with different keys (`[['a' => 1], ['b' => 1], ['a' => 0]]`) was `['a' => 1]`, now `['a' => 0]`. Every comparable value answers as before.
- **Interface note:** `Interfaces\IALinqOrderable` (`orderBy*()` gain `$comparer`), `IALinqFilterable` (`distinct*()` gain `$comparer`), `IALinqAggregatable` (`min*()`/`max*()`/`minBy*()`/`maxBy*()` gain `$comparer`; `implode()`), `IALinqSelectable` (`toDictionary()` gains `$onDuplicate`; `toLookup()`), `IALinqJoinable` (`join()`/`groupJoin()` take `iterable`; `exceptByKeys()`, `intersectByKeys()`), `IALinqBaseCollection` (`fromCsv()` takes `?string $separator`) and `IALinqLazyCollection` (all of the above plus `groupBy()`, `countBy()`, `aggregateBy()`) changed, as in 1.4.0 and 2.1.0. Callers do not break; a class that implements these interfaces on its own must add the parameters and the methods.

### Notes

- **`exceptBy()`/`intersectBy()` do not change**: their second argument is a sequence of elements and the selector applies to both sides (the MoreLINQ form). Called the .NET 6 way (`exceptBy([1, 2], fn($r) => $r['id'])`) they emit a warning and answer wrongly; the PHPDoc and README §3 now say so and point to `exceptByKeys()`.
- **Group keys are PHP array keys**, declared in README §11 (behaviour since 1.3.0): in `groupBy`, `countBy`, `aggregateBy`, `toDictionary` and `toLookup`, `1` and `'1'` are the same key, unlike the strict identity of joins and set operations.
- **`toLookup()` refuses a `null` key**, unlike .NET (which accepts it): a lookup answers as `groupBy()` does.
- **The third argument of `aggregateBy()`'s `$func` is the position of the item inside its group** (0, 1, …), not its key in the source, on both collections, as the eager side has always passed; every group starts from the same seed (an object seed is the same instance in every group).
- **The CSV detection differs from the consumer's reader**: that reader chose the separator by presence in the first physical line, `;` before tab before `,`, without looking at quotes (`"a;b",c` would pick `;`); the library counts outside quotes and refuses a tie. For files with a date column and a value column both choose the same; migrating is the consumer's decision.
- **Without ext-intl** the tests that use `StringComparer::culture()` are skipped and the test of the missing extension runs; with it the opposite. The CI runs both.
- **NOTICE**: conceptual inspiration of the lazy pipeline declared (`NOTICE.md` and `THIRD_PARTY_NOTICES.md` §2b: Laravel's `LazyCollection` and Doctrine's `Query::toIterable()`; no code of theirs is contained or redistributed).

## [2.1.0] - 2026-10-12

Minor release: aggregates with a fallback (ROADMAP milestone 13, Reversa forward 036), asked for by the first production consumer of the library. Additive: no answer of 2.0.0 changes.

### Added

- **`minOrDefault()`, `maxOrDefault()`, `averageOrDefault()`, `minByOrDefault()`, `maxByOrDefault()`** on `ALinqCollection` and `ALinqLazyCollection`: `minOrDefault(mixed $default = null, ?callable $selector = null)`, the same for `max`/`average`, and `minByOrDefault(mixed $default, callable $keySelector)`, the same for `maxBy`. Each one answers exactly as its sibling (`min()`, `max()`, `average()`, `minBy()`, `maxBy()`); only the `UnderflowException` of "nothing to aggregate" (an empty collection, or only nulls where the sibling skips them) becomes `$default`. Every other exception keeps its class and its message, the sibling's (a non-comparable value in `minOrDefault()` throws the message of `min()`). `$default` comes first, as in `firstOrDefault()`. `minByOrDefault()`/`maxByOrDefault()` do not skip a `null` key, as `minBy()`/`maxBy()` do not: `[null, null]` gives the first item, not the default.
- **One traversal.** The methods read the source once, so they are safe on single-pass sources (`fromCursor()`, `fromHttpPages()`, a generator), where the usual guard `any() ? min() : null` reads it twice and fails on a cursor (`A PDO cursor is single-pass…`). Before → after:
  ```php
  $dated = $quotes->where(fn($q) => !empty($q->created_at));
  $oldest = $dated->any() ? $dated->min(fn($q) => strtotime($q->created_at)) : null;          // before
  $oldest = $quotes->select(fn($q) => strtotime((string) $q->created_at) ?: null)->minOrDefault();             // after
  ```
  The projection (`select`: quote → timestamp, or nothing) is kept apart from the aggregation (`minOrDefault`: the smallest, or the default), and it is still one traversal. `strtotime()` returns `false`, not `null`, for an empty or unparsable date, and `false` is not skipped as a null: without `?: null` a malformed date wins the minimum. The `(string)` cast avoids passing `null` to `strtotime()`, deprecated since PHP 8.1.
- **Interface note:** the five methods were added to `Interfaces\IALinqAggregatable` and `Interfaces\IALinqLazyCollection`, as `single()` was in 1.4.0. A class that implements these interfaces on its own must add them.

### Changed

- The lazy `min()`, `max()`, `minBy()`, `maxBy()` and `average()` now run on shared private helpers (the eager side already did); answers and messages are unchanged, proved by the 2.0.0 suite running unmodified.
- Tests: `tests/Unit/AggregationOrDefaultTest.php` (single pass on a source that refuses a second opening, the consumer's code, a SQLite cursor) and `tests/Contract/AggregateOrDefaultContractTest.php` (17 sibling/variant pairs × 13 inputs × 2 sides, with the sibling as the oracle); the five methods joined `ParityTest`. Suite **598 tests, 2321 assertions** (from 563/2148). Measured coverage (pcov): 243 of 249 methods, 1380 of 1394 lines of `src/`; the 14 uncovered lines are the same defensive guards.

## [2.0.0] - 2026-10-12

Major release that removes what 1.x announced as deprecated, and nothing else: `extract()` (deprecated since 1.3.0). The property resolution moves to a shared package, `antevemus/adeep-property-access` 1.0.0, which this library now requires (ROADMAP milestone 11): one dependency, from the same author, with no dependencies of its own; Antevemus.ASpecification 2.0 resolves names through the same package, so both libraries read the same value from the same candidate by construction. PHP 8.4 is still the minimum. **Read [UPGRADE-2.0.md](UPGRADE-2.0.md) ([pt-BR](UPGRADE-2.0.pt-BR.md)) before upgrading**; there is no data to convert.

### Removed

- **`extract()`** (deprecated 1.3.0), from `ALinqCollection` (`Traits\UtilityOperations`) and from `Interfaces\IALinqUtility` → PHP's own `extract($collection->toArray())` in the caller's scope, or `count()` when only the count was used. A method cannot export variables into the scope of its caller, so it never did what its name says: since 1.3.0 it raised `E_USER_DEPRECATED` and returned the count. Calling it now is `Error: Call to undefined method`. A class that implements `IALinqUtility` no longer needs to declare it.

### Changed

- **New dependency `antevemus/adeep-property-access` `^1.0.0`**, the property accessor shared with ASpecification (`Antevemus\ADeepPropertyAccess\PropertyAccess`). Every internal reader calls it directly: `ALinqQueryBuilder::where()` and its shortcuts, `createPropertySelector()`, `whereIn()`/`whereNotIn()`/`whereBetween()` on both collections (`ALinqContract::fieldPredicate()`) and `ALinqPropertyAccess::createPropertyComparer()`. ALinq keeps no copy of the resolution. The resolution order is the one of 1.x; the answers that changed, all at the edges (measured 1.5.1 × 2.0.0 on the 57 cases of the package's contract battery plus the package's own marker: 10 differ, on every reader of ALinq alike):
  - **a public method with a required parameter is not a getter.** Before: `getName(string $x)` was called without arguments when reading `name`, so `getValue()`, the query builder, `whereIn()` and the selector threw `ArgumentCountError`, while `hasProperty()` answered `true`. After: it is skipped and the next step answers (a public property `$name` → its value; `isReady()` after a `getReady(int $timeout)` → `true`), else `null` and `hasProperty()` `false`. Optional and variadic parameters still make a getter;
  - **`__call()` is never reached.** Before: a private or protected getter of a class with `__call()` passed the `method_exists()` + `is_callable()` check, so `__call('getName', [])` ran and its result was read (`'__call:getName'`), and `hasProperty()` answered `true`. After: only public methods are called: `null`, `false`;
  - **a name starting with `__` is never called.** Before: the bare name was the `x()` candidate, so reading `'__construct'` ran the constructor again (and `hasProperty()` answered `true`). After: `null`, `false`;
  - **an empty name names no method.** Before: the candidates were the bare prefixes, so `''` called a public `get()` (or `is()`, `has()`) and read its result, and `hasProperty()` answered `true`. After: `null`, `false`. (An empty name on an object without such a method already yielded `null`.);
  - **a key of an `ArrayObject`/`ArrayIterator` holding `null` exists.** Before: `hasProperty()` answered `false` (`isset()`), also at the end of a path (`'box.name'`). After: `true` (`offsetExists()`), as a key of an array holding `null`; a missing key is still `false`, and `getValue()` is `null` in both;
  - **`#[Antevemus\ADeepPropertyAccess\Attributes\Specifiable]` is accepted** as the opt-in marker, next to ALinq's and ASpecification's. Before: a private property marked with it read as `null`/`false`. After: its value/`true`.

  Three edge answers where the two 1.x copies disagreed keep the ALinq answer (ASpecification changed in its 2.0): an empty name on an object yields `null`; an `ArrayAccess` object answers through its offsets only (its public properties are not seen); an unset public property with `__get()` and no `__isset()` yields `null`.
- `suggest` of `antevemus/aspecification`: `^1.10.0` → **`^2.0.0`**, the latest ASpecification release (2.0 resolves names through the same package and accepts ALinq `^1.5.1 || ^2.0`).
- `Attributes\Specifiable` stays, and is still the marker to use in ALinq code; only its docblock changed (it names the shared accessor and the two equivalent markers).

### Deprecated

- **`Helpers\ALinqPropertyAccess::getValue()`, `hasProperty()`, `getPropertyAccessor()`, `getNestedPropertyAccessor()`** → `Antevemus\ADeepPropertyAccess\PropertyAccess::getValue()`, `hasProperty()`, `accessor()` (both accessor factories map to `accessor()`). A thin facade with the same public API (the class is not made final), `@deprecated 2.0.0`, removed in 3.0.0; no runtime notice (it sits on the hot path of every query that names a field). `createPropertyComparer()` belongs to ALinq and is not deprecated.

### Added

- **`UPGRADE-2.0.md` and `UPGRADE-2.0.pt-BR.md`**: the replacement of `extract()`, the facade mapping and the answers that changed, case by case, with the 1.5.1 answer next to the 2.0.0 one.
- Tests: `tests/Contract/AccessorContractTest.php` proves the delegation instead of carrying the battery (the battery, with the expected answers written down, lives in the package): the facade answers exactly as the package on the 53 cases (the 14 of the ALinq contract plus 39 edge cases of the package battery) without any notice; the five internal entry points (selector, comparer, query builder, eager and lazy `whereIn()`) agree with the package on every case; the 14 ALinq cases keep their written answers; the changed answers reach the internal readers (with the 1.5.1 answer in each message); the four facade methods are `@deprecated 2.0.0` and the comparer is not; no file of `src/` keeps a copy of the resolution (`method_exists()`, `property_exists()`, `ReflectionProperty`, `get_object_vars()`, a getter name built with `ucfirst()`) or calls the facade. `tests/Unit/ReadmePropertyAccessTest.php` runs the two code blocks of README §9 (EN and pt-BR) as written and checks every commented answer. `UtilityOperationsTest`: the two `extract()` tests became one that proves the removal. Suite **563 tests, 2148 assertions** (from 558/1835). Measured coverage (pcov): 229 of 235 methods, 1377 of 1391 lines of `src/`; the 14 uncovered lines are defensive guards (one in the shared outer join, thirteen on the HTTP path; the five of the old accessor left with it).

### Documentation

- README (EN and pt-BR): the dependency ("one, from the same author, with no dependencies of its own") in the installation and quality sections; §9 shows `PropertyAccess` of the package (a test reads both blocks from the README and runs them as written), the getter rule (no required parameter, never `__call()` nor a `__` name), `offsetExists()` and the three equivalent markers, and the facade; Origins, Core Features, directory tree (the facade) and a note under it, installation constraint `^2.0` with a pointer to `UPGRADE-2.0.md`, release badge v2.0.0, test counts and coverage, "One Runtime Dependency, Same Author" instead of "Zero External Runtime Dependencies"; roadmap list: v2.0.0 (shared property accessor, `extract()` removed).
- ROADMAP: milestone 11 (shared property accessor with ASpecification) shipped in v2.0.0; the v2.0+ section keeps milestone 12, whose companion packages are no longer tied to the 2.0.0 release (they are separate packages; not scheduled).
- `tests/README.md`: counts, the removal of `extract()`, the new contract tests and the README test.
- `THIRD_PARTY_NOTICES.md`: the runtime dependency (first-party, MIT, with its own NOTICE). The resolution code that followed Domian `ReflectionUtils.getFieldByName()` left this repository with the accessor.

## [1.5.1] - 2026-10-11

Patch: continuous integration and package metadata only. No code changes.

### Fixed
- **The GitHub Actions workflow of 1.5.0 never ran.** It declared the proxy exclusion twice (`NO_PROXY` and `no_proxy`); GitHub treats environment keys case-insensitively and refuses the whole workflow file. Declared once now, and the file passes `actionlint`. `actions/checkout` moves from v4 (Node 20, deprecated on the runners) to v7.

### Changed
- **`suggest` for `antevemus/aspecification`: `^1.9.0` → `^1.10.0`**, the latest ASpecification release.

## [1.5.0] - 2026-10-11

Concurrent I/O, core part (ROADMAP milestone 12): a lazy source over a paginated HTTP API that downloads the next page while the current one is consumed, with `curl_multi` and no dependency. Additive (MINOR): nothing existing changes. Specified before code (Reversa forward 027, decisions D1 to D8, rules RN-01 to RN-06).

### Added
- **`ALinqLazyCollection::fromHttpPages(callable $requestFor, callable $parsePage, ?HttpPageOptions $options = null)`**, also on `IALinqLazyCollection` (where the other lazy sources are). `$requestFor($cursor, $pageIndex)` builds the request of a page (the cursor of the first page is `null`, `$pageIndex` counts from 0, `null` ends the stream; a callback that accepts one parameter receives the cursor alone); `$parsePage($response)` answers `[iterable $items, mixed $nextCursor]` (`null` cursor on the last page). Cursor in the body, page number and `Link` header pagination fit this shape. Items come out with consecutive keys across pages (a list). Before: paging an API lazily meant a hand-written generator that downloaded each page only after the previous one was consumed. After:
  - **Prefetch depth 1**: as soon as page *n* is parsed, the request of page *n+1* is started on a `curl_multi` handle and put on the wire, and only then are the items of page *n* yielded; between items the transfer is nudged forward without blocking. No event loop, no Fiber, plain PHP-FPM. Measured on the local test server (3 pages of 300 ms, a consumer spending 300 ms per page): 1.2 s instead of 1.8 s serially.
  - **Errors** (`HttpPageException`, a `RuntimeException`): a status outside 2xx, a transport error (TLS included), the timeout or the page limit throw at the point of the iteration where that page would be delivered, after the items of the previous pages. The message carries the status, the method and the URL reduced to scheme, host, port and path (no user information, query string or fragment), never the body; the URL and the transport detail are redacted before the exception is built, so not even the arguments in its stack trace hold them. `getStatusCode()`, `getUrl()`, `getMethod()`, `getPageIndex()`, `isTimeout()`, `isTransportError()`, `isPageLimit()`. No retry in the core.
  - **Early termination** (`take()`, `first()`, `any()`, an exception in the consumer) cancels the request in flight (`curl_multi_remove_handle()` and release of the easy handle, in the generator's `finally`): at most one page is downloaded for nothing.
  - **Re-iteration refetches** from the first page (a GET is idempotent; unlike the single-pass PDO cursor of 1.3.2). `remember()` traverses once and replays from memory.
- **`Antevemus\ALinq\Http\HttpPageRequest`** (method, absolute http(s) URL, headers, body, timeout; `HttpPageRequest::get()`), **`HttpPageResponse`** (status, headers, body, effective URL, page index; `header()`, `json()`, `link($rel = 'next')` reading RFC 8288 `Link` headers with relative targets resolved) and **`HttpPageOptions`** (`timeout` 30 s, `maxPages` 10 000, common `headers`, `followRedirects` false): plain value objects, no PSR-7. A request refuses other schemes, header names that are not tokens and CR/LF/NUL in values (no header injection); the transfer is limited to HTTP(S), redirects included. The timeout bounds the time the iteration blocks waiting for one page: time the consumer spends on the previous page does not count, so a page that arrived meanwhile is never reported as timed out. TLS peer and host verification is always on; there is no option to turn it off.
- `ext-curl` in `suggest` (required by `fromHttpPages()` only; the rest of the library still has zero runtime requirements).
- Tests: `tests/Unit/Http/FromHttpPagesTest.php` runs against a `php -S` server started on a free port of 127.0.0.1 and stopped after the class (overlap timing, the three pagination styles, the page limit, a 500 on page 2, transport errors, timeouts, a self-signed TLS server refused, redirects, headers, early termination seen as an abort by the server, re-iteration, the README example); `HttpPageValueObjectsTest.php` covers the value objects without a network. Group `http` (`--exclude-group http` skips them). Suite **558 tests, 1835 assertions** (from 499/1664). Measured coverage (pcov): 234 of 243 methods, 1458 of 1477 lines of `src/`; the 19 uncovered lines are defensive guards (13 on the HTTP path).

### Documentation

- **Continuous integration on GitHub Actions** (`.github/workflows/tests.yml`): PHPUnit on PHP 8.4, the HTTP tests included; CI badge in the README.
- README (EN and pt-BR) §10 "Paginated HTTP API with Prefetch": the example (identical code in both, transcribed into a test against the local server), the callbacks, errors, early termination, re-iteration and options. ROADMAP milestone 12: the HTTP prefetch part shipped in 1.5.0; the async collection on Fibers and worker-process parallelism stay planned for companion packages.

## [1.4.3] - 2026-10-11

Patch: package metadata and roadmap only. No code changes.

### Changed
- **`suggest` for `antevemus/aspecification`: `^1.4` → `^1.9.0`.** `^1.4` admitted ASpecification 1.4.4 to 1.6.0, whose property accessor read any non-public property by name; this package reads one only when marked `#[Specifiable]` (since 1.4.1), the rule ASpecification follows since 1.6.1. The floor is the latest ASpecification release, the policy for cross-package constraints from now on.

### Documentation
- **ROADMAP milestone 12 corrected** ("Parallel Collection Processing with PHP Fibers" → "Concurrent I/O Sources and Parallel Workers"): a Fiber runs on a single thread and is not parallelism. The milestone now separates HTTP page prefetch with `curl_multi` (core, zero dependency), an async collection on Fibers for async SDKs and CPU parallelism with worker processes (companion packages). README roadmap lines updated accordingly.

## [1.4.2] - 2026-10-10

Patch: `singleOrDefault()` speaks the same language as `single()`. No signature changes.

### Changed
- **`singleOrDefault()` throws with the messages of `single()`**, on both collections. **Before → after** for more than one element: without a predicate, `'Collection contains more than one matching element.'` → `'Sequence contains more than one element.'`; with a predicate, `'Collection contains more than one matching element.'` → `'Sequence contains more than one matching element.'`. The exception class (`OverflowException`) is unchanged. Code that compared the old text must switch; code that catches the class is not affected.

## [1.4.1] - 2026-10-10

Security patch: the non-public last resort that 1.4.0 added to `ALinqPropertyAccess` becomes opt-in by the author of the class. No signature changes; a non-public property without the mark reads as it did in 1.3.x.

### Security
- **Filtering and ordering over a private field no longer work as a read oracle.** The library receives a property only as a name in text (`where*()`, the `orderBy()` selectors built with `createPropertySelector()`, `select()`, `ALinqQueryBuilder`, `createPropertyComparer()`) and cannot tell code written by the author of the entity from a name that came from a request. Before (1.4.0): every private or protected property declared by the class or an ancestor was readable by name, so a request-supplied name could probe any field: `whereBetween('password_hash', 'a', 'b')` answers, range after range, what the value starts with, and an ordering by it leaks its relative order. After: the last step of the resolution order reads a non-public property only when the author marked it `#[Specifiable]`, on the property itself or on the class that declares it; a class-level mark covers the properties declared by that class only (neither its parents' nor its subclasses'). An unmarked non-public property resolves to `null` in `getValue()` and to `false` in `hasProperty()`, as in 1.3.x, on every entry point and on the eager and the lazy collection alike. Steps 1 to 4 (array/`ArrayAccess`, public getter, `__get` guarded by `__isset`, public initialized property) are unchanged and need no mark. Migration: an entity that relied on 1.4.0 reading a private field by name adds `#[Specifiable]` to that property (or to its class), or exposes a public getter. `tests/Contract/AccessorContractTest.php`: the case "private without getter" expects `null` again and a new case, the same property marked `#[Specifiable]`, expects the value.

### Added
- **`Antevemus\ALinq\Attributes\Specifiable`**, `#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]`: the opt-in marker above. `#[Antevemus\ASpecification\Attributes\Specifiable]` (Antevemus.ASpecification 1.6.1, same opt-in on its `PropertyAccessor`) is accepted as the same mark; attributes are compared by name and never instantiated, so ALinq does not depend on that package. Tests for the mark (property, class, hierarchy in both directions, the ASpecification name, a look-alike attribute that is not accepted) and for the oracle (`whereBetween()`, the query builder and `orderBy()`/`orderByDescending()` over an unmarked private field, eager and lazy). Suite **499 tests, 1656 assertions** (from 494/1606). Measured coverage (pcov): 201 of 205 methods, 1171 of 1177 lines of `src/`; the six uncovered lines are defensive guards.

## [1.4.0] - 2026-10-09

The announced-but-unshipped backlog of the 0.1.0 notes, the outer joins of .NET 10/11 LINQ and composite ordering (ROADMAP milestones 8, 9 and 10), on both collections with the same answers, keys and exceptions; plus one step added to the property accessor for parity with Antevemus.ASpecification 1.4.4. Additive (MINOR): no existing signature changes; one result changes in the accessor, declared under *Changed*.

### Added
- **`single(?callable $predicate = null)`** on both collections (`IALinqAggregatable`), the LINQ `Single`: exactly one element, or exactly one that matches. Before: only `singleOrDefault()` existed (the 0.1.0 notes announced `single()`). After: an empty sequence throws `UnderflowException('Sequence contains no elements.')`, no match throws `UnderflowException('Sequence contains no matching element.')`, more than one throws `OverflowException('Sequence contains more than one element.')` / `('Sequence contains more than one matching element.')`; same classes and messages on the eager and the lazy side. The predicate receives `($item, $key)` when it accepts two parameters.
- **`whereIn()`, `whereNotIn()`, `whereBetween(string $field, mixed $min, mixed $max)`** on both collections (`IALinqFilterable`, lazy interface). Before: `where()` with a hand-written closure (the 0.1.0 notes announced the three). After: sugar over `where()` that reads the field through `ALinqPropertyAccess` (dot-notation paths allowed); `whereIn`/`whereNotIn` compare by strict identity (`in_array(..., true)`, so `'18'` is not in `[18]`); `whereBetween` is inclusive and a `null` value is never between; a list comes out reindexed and a dictionary keeps its keys.
- **`ALinqQueryBuilder::whereIn()`, `whereNotIn()`, `whereBetween()`**. Before: `where($field, 'in'|'notIn'|'between', ...)`. After: the same calls under a shortcut, with the builder's semantics unchanged: `in`/`notIn` compare loosely (strictly only when the value read is `null`), `between` is inclusive and `null` is never between. The collection methods of the same names are strict on purpose (they are sugar over the collection's `where()`, not over the builder).
- **`leftJoin()`, `rightJoin()`, `fullJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null)`** on both collections (`IALinqJoinable`), as `Enumerable.LeftJoin`/`RightJoin`/`FullJoin` in .NET 10/11. Before: an outer join was `groupJoin()` plus `selectMany()` by hand, and there was no right or full join. After: the result selector receives `($outer, $inner)` with `null` on the side without a match; without a selector each result is the pair `[$outer, $inner]`; the result is always a list; keys match by the strict identity of `join()`/`groupJoin()` and a `null` key never matches (that item comes out without a partner). Order: `leftJoin` follows the outer order; `rightJoin` follows the inner order, as in .NET, each inner item with its matching outer items in outer order; `fullJoin` yields the pairs and the unmatched outer items in outer order, then the unmatched inner items in inner order.
- **`thenBy(callable $keySelector, ?callable $comparer = null)`, `thenByDescending(...)`** on both collections (`IALinqOrderable`). Before: a composite key needed `orderByCustom()` with a composite comparer, and chaining a second `orderBy()` started a new ordering. After: `orderBy()`/`orderByDescending()` return the sorted collection with the ordering pending on that instance; `thenBy()`/`thenByDescending()` add a criterion that decides only between items tied on every previous one; the sort is stable (a full tie keeps the source order) and every key selector runs once per item. The key selector receives `($item, $key)` with the key of the source; the optional comparer receives the two keys, `fn($keyA, $keyB): int`. Called on anything that does not come straight from `orderBy*()`/`thenBy*()`, they throw `LogicException('thenBy() requires a preceding orderBy().')` (`thenByDescending() ...` respectively). `orderByCustom()` is unchanged.
- **The lazy collection gained `orderBy()`/`orderByDescending()`** (with `thenBy*()`) and the three outer joins. They stay deferred but accumulate data: the ordering buffers the whole upstream on traversal; `leftJoin()`/`fullJoin()` buffer and index the inner side and stream the lazy collection; `rightJoin()` buffers and indexes the lazy collection itself and streams the inner side.
- `tests/Contract/ParityTest.php` compares the exception **message** as well as the class (before: class only); the new operators joined the eager × lazy matrix, and a second matrix runs the field- and key-based operators over rows given as arrays and as entities with private fields. Suite **494 tests, 1606 assertions** (from 454/1454). Measured coverage (pcov): 199 of 203 methods, 1159 of 1165 lines of `src/`; the six uncovered lines are defensive guards.

### Changed
- **`ALinqPropertyAccess` reads a non-public property as the last resort (breaking of result).** The resolution order gains a final step after array/`ArrayAccess`, public getter, `__get` guarded by `__isset` and public initialized property: a private or protected property (static too) declared by the class or one of its ancestors, read by reflection without calling any method; an uninitialized one resolves to `null`. Before: `getValue()` on a private property without a getter returned `null` and `hasProperty()` answered `false`. After: `getValue()` returns the value and `hasProperty()` answers `true`. Reason: parity with the `PropertyAccessor` of Antevemus.ASpecification 1.4.4, which gained the same step; the two accessors keep one resolution order (`tests/Contract/AccessorContractTest.php`: the case "private without getter" now expects the value, the other twelve are unchanged). Reach: every entry point that reads a property, namely `ALinqQueryBuilder::where()` and its shortcuts, the collections' `whereIn()`/`whereNotIn()`/`whereBetween()`, `createPropertySelector()` and `createPropertyComparer()`. Code that relied on a private field reading as `null` (for example `where('secret', '=', null)` over entities) now sees the field's value.

## [1.3.2] - 2026-10-09

Lazy sources hardening (ROADMAP milestone 7). Irregular input now fails loudly or is an explicit choice, never a silent reshape. No signature breaks; inputs that used to pass silently now throw.

### Fixed

- **`fromCsv()` width mismatch.** A record with a number of fields different from the header was delivered as a positional list in the middle of a stream of dictionaries. It now throws `RuntimeException('CSV row N has K fields, header has H')` at the moment it is read (the previous records were already delivered; `N` counts data records from 1, blank lines excluded). The new optional named parameter `strict` (last in the signature, also on the `ALinqCollection::fromCsv()` facade) keeps the old behaviour: `fromCsv($path, strict: false)`. Without a header nothing changes.
- **`fromCsv()` blank line.** A blank line was delivered as `[null]`; it is now skipped in every mode, with or without header.
- **UTF-8 BOM.** The BOM stayed in the name of the first column (`"\xEF\xBB\xBFid"`; a quoted first field `BOM"id"` became the key `'"id"'`) and in the first line of `fromFile()`. It is now consumed from the stream before parsing (a non-seekable stream falls back to stripping it from the first field); later lines are untouched.
- **Directory passed to `fromFile()`/`fromCsv()`.** It produced an empty stream with a read notice; it now throws `RuntimeException('Path is a directory, not a file: "<path>"')` on the first traversal. A missing or unreadable file still throws "Unable to open ...".
- **Shared `PDOStatement`.** Two `fromCursor()` (or `from()`) over the same statement shared the cursor, so the second read the remainder of the first. The statement is now bound to one lazy collection (a `WeakMap`, nothing kept alive); the second collection throws `RuntimeException('PDOStatement already bound to another lazy collection')` on its first traversal. Iterate again with `remember()`; run the query again on a new statement, prepared statements included. The second-pass message of a single-use cursor now says "or run the query again on a new statement".

## [1.3.1] - 2026-10-09

Process hygiene, no contract change: what the 2026-10-08 review listed under "process and distribution".

### Added
- `ROADMAP.md`: the milestone view of the README checklist (shipped, short-term, medium-term, long-term), including the outer joins returning pairs (`leftJoin`/`rightJoin`/`fullJoin`, as in .NET 10/11 LINQ), `thenBy()`, the lazy-sources hardening and the 0.1.0 erratum backlog.
- `suggest` of `antevemus/aspecification` in `composer.json` (the reciprocal of the ASpecification `suggest`).
- Tests for what had none: the eager `fromCsv()`/`fromCursor()` facades, the lazy constructor with a raw `Generator` and with a non-`Generator` `Traversable`, `fromFile()`/`fromCsv()` failing loudly (buffer size, missing file) and the irregular CSV row, `skipWhile()`/`tap()` over a dictionary, `ALinqCallable` (variadic callbacks, `hashKey()` buckets) and `hasProperty()` on a non-public property. Suite **449 tests, 1418 assertions** (from 442/1381). Measured coverage (pcov): 164 of 166 methods, 950 of 953 lines; the three uncovered lines are defensive `catch` blocks in `ALinqPropertyAccess`.

### Changed
- `declare(strict_types=1)` in every file of `src/` (25/25; it was 13/25: `ALinqCollection`, `IteratorOperations` and the ten interfaces had none).
- `phpunit.xml.dist`: the empty "Trait Tests" suite is gone and so is the `<logging>` block that wrote `coverage/testdox.*` on every run, including `--no-coverage`.
- Copyright years in the file headers (`2025-2026`, as in `LICENSE`); `@version 1.3.1` on every file of `src/` (all were touched).
- No residual Portuguese in the tree (`.gitignore` comments); `NULL` → `null` in `any()`.

## [1.3.0] - 2026-10-08

The contract release: the eager `ALinqCollection` and the lazy `ALinqLazyCollection` now obey one written contract (README §11), enforced by a parity test that runs every shared operation over the same inputs on both sides with zero divergences allowed (the 2026-10-08 review measured 114 divergences in 350 comparisons on 1.1.1). Several behaviours change on purpose; each is listed below with the previous and the new answer.

### Changed (breaking, by contract)
- **Empty collections throw, as in LINQ.** Eager `first()`, `last()`, `min()`, `max()`, `minBy()`, `maxBy()`, `average()` throw `UnderflowException` on an empty collection or when no element matches the predicate (before: `null`, and `average()` returned `0`). `firstOrDefault()`/`lastOrDefault()`/`singleOrDefault()` keep returning the default. `product()` of an empty collection is `1` (before: `0`). `singleOrDefault()` with more than one match throws `OverflowException` on both sides (the eager side threw `RuntimeException`; `OverflowException` extends it).
- **Keys follow one rule on both sides: a list is reindexed, a dictionary keeps its keys.** Eager `take`, `skip`, `reverse`, `pad`, `chunk` (inside each chunk), `distinct`, `distinctBy`, `shuffle`, `orderByNatural`, `orderByCustom`, `intersect`, `except`, `intersectBy`, `exceptBy`, `unionBy`, `random(n)` now keep string **and** non-sequential integer keys of a dictionary (before: integer keys were dropped, and the set operations dropped every key). The lazy collection knows whether its source is a list (`from(array)`, `fromFile`, `fromCsv`, `fromCursor`, `range`, `repeat`) and streams a list with sequential keys after `where`/`skip`/`take`/`distinct` (before: the original keys, with gaps); a raw generator keeps its keys while streaming and `toArray()` applies the 1.1.2 rule.
- **Strict, type-aware identity in every set operation and join.** `intersect()`, `except()`, `intersectBy()`, `exceptBy()`, `unionBy()`, `join()`, `groupJoin()` compare with `ALinqCallable::hashKey()` (before: `(string)` casts, `spl_object_hash`, array-key coercion, or `===` depending on the method). `1`, `'1'`, `1.0` and `true` are distinct; arrays compare by value without warnings; `except()` now deduplicates like `intersect()` (set semantics). A `groupJoin()` whose keys arrive as `int` on one side and `string` on the other no longer matches: cast on the caller side. A `null` key never matches in `join()`/`groupJoin()`, as in LINQ and SQL (before: an outer row with a `null` key matched every inner row with a `null` key).
- **Group keys are validated.** `groupBy()`, `countBy()`, `aggregateBy()` and `toDictionary()` accept `int`, `string` and `BackedEnum` keys and throw `InvalidArgumentException` for `null`, `bool`, `float`, arrays and objects (before: PHP coercion with a deprecation, or a raw `TypeError`). `toDictionary()` throws `InvalidArgumentException` on a repeated key (before: last value won silently).
- **`concat()` is a sequence operation**: the result is always a reindexed list on both sides (before: eager `array_merge`, which overwrote string keys). Use `replace()`, `replaceRecursive()` or `unionBy()` to merge by key.
- **`chunk()` returns collections of collections** on both sides (before: native arrays, which lose the collection API; LINQ's `Chunk` returns `T[]` because a .NET array is still enumerable, a PHP array is not). `chunk(100)->select(fn($c) => $c->sum())` and `foreach ($rows->chunk(500) as $batch) { $batch->count(); }` now work; inside each chunk a list is reindexed and a dictionary keeps its keys. **Breaking for** code that handed a chunk to an array function: use `$chunk->toArray()` (no copy). The lazy side yields eager `ALinqCollection` chunks (a chunk is materialized by nature).
- **`random()` of an empty collection throws `UnderflowException`**, like `first()` (before: `null`, which neither LINQ's `First()` nor PHP's `array_rand()` would answer); `random(n > 1)` of an empty collection returns an empty collection, like `take(n)` (before: `null`, which broke the pipeline).
- **Numeric aggregations ignore `null` and refuse non-numbers.** `sum`, `average`, `min`, `max`, `product` skip `null` (`average([1, null, 2])` is `1.5`, `min([3, null, 1])` is `1`; before: `null` counted in the denominator and `min` returned `null`); a collection of only `null` counts as empty. A non-numeric value throws `InvalidArgumentException` in `sum`/`average`/`product` on both sides (before: eager `Warning` and `0`, lazy `TypeError`); `min`/`max` accept any scalar or `DateTimeInterface` and throw for arrays and other objects.
- **`all()`**: `all($predicate)` on an empty collection is `true` on both sides (the lazy side answered `false`); `all()` without a predicate means "every item is truthy" (before: always `false`). `any()` is unchanged.
- **Invalid arguments throw** `InvalidArgumentException` on both sides: `take`/`skip` with a negative count (before: eager sliced from the tail, lazy ignored), `pad` with a negative size (before: eager padded on the left, lazy ignored), `chunk(0)` (before: `ValueError`), `random(0)`.
- **`each()` / `eachRecursive()` no longer mutate the collection**: they iterate over a copy and a by-reference callback has no effect (before: `array_walk` on the internal array). Use `select()` to transform.
- **Custom comparers answer `bool` or `<=>`.** `contains($value, $comparer)`, `intersectWith()`, `exceptWith()` accept a comparer that returns `true` for equal or `0` in the `<=>` convention, on both sides (before: eager required `0`, lazy required truthy; `intersectWith`/`exceptWith` delegated to `array_uintersect`/`array_udiff`, which deprecate boolean comparers).
- **`ALinqLazyCollection::from()` treats only a `Closure` as a re-iterable factory**; an array callable such as `[$object, 'method']` is data (before: it was invoked as a factory and threw `ArgumentCountError`), and a string or invokable object that is not iterable throws `InvalidArgumentException` pointing to `Closure::fromCallable()`. `unionBy()` always returns a list (it is a distinct concatenation, like `concat()`).
- **Lazy `selectMany()`** throws `UnexpectedValueException` when the selector returns a non-iterable (before: the scalar was emitted as an item), matching the eager side.
- **Query builder `null` semantics.** When the property resolves to `null`, `>`, `>=`, `<`, `<=`, `between`, `notBetween`, `contains`, `startsWith`, `endsWith` are `false` (before: `null < 18` was `true`, so an item without the property passed `where('age', '<', 18)`); `=`/`==` and `!=`/`<>` compare strictly when either side is `null` (`where('x', '=', null)` means "is null"; before it also matched `0`, `''`, `false`, `[]`); `in`/`notIn` compare strictly when the value is `null`.
- **`ALinqQueryBuilder::create($mode)`** accepts `and`/`or` in any case, trimmed, and throws `InvalidArgumentException` for anything else (before: any other string silently meant OR).
- **`ALinqQueryBuilder::toPredicate()` is a snapshot**: `where()` calls made after it do not change the predicate already returned (before: the closure read the live condition list).

### Added
- `count(?callable $predicate = null): int` on both collections (`IALinqBaseCollection`); the argument used to be silently discarded.
- `JsonSerializable` on both collections: `json_encode($collection)` gives a JSON array for a list and an object for a dictionary (before: `{}`).
- `Helpers\ALinqContract`: the shared rules (`shapeLike`, `groupKey`, `equality`, `numericValue`, `comparableValue`, `requireAtLeast`).
- `IALinqLazyCollection::fromCursor()` and the `lazy()`/`fromFile()`/`fromCsv()`/`fromCursor()` facades declared in the eager interface.
- `tests/Contract/ParityTest.php` (eager × lazy matrix) and `tests/Contract/AccessorContractTest.php` (`ALinqPropertyAccess` is the single property resolver behind `getPropertyAccessor`, `getNestedPropertyAccessor`, `createPropertyComparer`, `createPropertySelector` and `ALinqQueryBuilder::where`).
- `@throws` on every interface method that throws.
- Regression tests for everything above (suite **442 tests, 1381 assertions**, from 387/1050).

### Deprecated
- `extract()`: a method cannot export variables into its caller's scope, so it never did what its name says. It still returns the count, now with an `E_USER_DEPRECATED` notice; removal planned for 2.0.

### Fixed
- `join()` indexes the inner side by key (O(n + m), as `groupJoin()` already did) instead of a nested loop: 10k × 10k went from seconds to milliseconds in the review's measurement.
- Eager `first()`/`last()` no longer move the internal array pointer; `firstOrDefault()`/`lastOrDefault()` distinguish an element that is `null` from "no element".
- Tests that hid key behaviour behind `array_values()` now assert the real keys.

## [1.2.0] - 2026-10-08

README promises I: every code block of the README runs as written (15/15; five did not on 1.1.2).

### Changed
- **`groupBy()` groups are `ALinqCollection` instances**, as the README always said, so `->select(fn(ALinqCollection $group, string $key) => ...)`, nested pipelines and `$group->count()`/`->average()` work. **Breaking for** code that indexed a group as an array (`$group[0]`, `array_column($group, ...)`): use `$group->toArray()` or the collection API. `aggregateBy()`/`countBy()` keep their results.
- **Callbacks follow one arity rule in every operator of both collections** (`Helpers\ALinqCallable::withKey()`): a callback receives `($item, $key)` when it accepts two parameters and the item alone otherwise. Native one-argument functions (`is_int`, `strtoupper`, `strlen(...)`) now work in `where`, `first`, `last`, `firstOrDefault`, `lastOrDefault`, `singleOrDefault`, `findKey`, `any`, `all` (eager) and in `select`, `selectMany`, `where`, `sum`, `min`, `max` and friends (lazy), where they threw `ArgumentCountError`; two-parameter closures now receive the key in `select`, `sum`, `average`, `min`, `max`, `product`, `minBy`, `maxBy`, `orderBy`, `orderByDescending`, `groupBy`, `distinct`, `distinctBy`, `toDictionary`, `join` and `groupJoin`, where the eager side passed the item alone. Internal functions with an optional second argument (`intval`) keep receiving the item only.
- **`select()` preserves keys** and hands the key to a two-parameter selector (it was `array_map`, value only).
- **`distinct()` compares by strict, type-aware identity** (`ALinqCallable::hashKey()`): `1`, `1`, `true` and `1.0` stay distinct, arrays are compared by value, objects by identity, every `NAN` falls in one bucket, no warning is emitted, and both forms are O(1) per item (the selector form was O(n²)). The lazy `distinct()` uses the same identity (it compared scalars as strings). **Breaking for** code that relied on `array_unique()` merging `1`/`1`/`true`, or on arrays collapsing to one.
- **`ALinqPropertyAccess` resolves properties in the same order as Antevemus.ASpecification's `PropertyAccessor`** (array/`ArrayAccess` by key, then a public getter `getX`/`x`/`isX`/`hasX`, then `__get` guarded by `__isset`, then a public initialized property via reflection, else `null`) and never throws `Error`: a private or protected property with a getter, a typed uninitialized property and a private getter now resolve or yield `null` instead of crashing `QueryBuilder::where()`, `orderBy($c->createPropertySelector(...))` and the nested accessor. `getValue()` resolves dot-notation (`'user.profile.organization.taxId'`), so the README §9 example returns the value instead of `null`, and `QueryBuilder->where('a.b.c', ...)` works. **Breaking for** code that relied on a public property winning over its getter (`public $status` + `getStatus()` now reads the getter), on a literal array key containing a dot (a dot is always a path now), or on the `Error` thrown for non-public properties (use `hasProperty()`). The 13 divergences between the two accessors measured by the review are gone (43/43 cases converge).
- **`orderBy()` / `orderByDescending()`** call the key selector once per item (it was twice per comparison), sort stably on the precomputed key, and follow the key policy of 1.1.2: a list comes out reindexed, a dictionary keeps its keys. **Breaking for** code that expected string keys to be dropped.

### Fixed
- **`selectMany()` no longer discards non-array results silently.** Any iterable returned by the selector (`ALinqCollection`, `Traversable`, generator) is flattened; a scalar now throws `UnexpectedValueException` naming the key, instead of producing an empty collection.
- **README §3 `groupJoin()` example** typed the matched group as `array` while the method hands an `ALinqCollection`; the example now types it correctly and uses `->column(name)->toArray()`.
- **README Synergy example** used `Spec::and()` and `DSL\isGreaterThan()`, which do not exist in Antevemus.ASpecification; it now uses `Spec::allOf()` and `DSL\greaterThan()` and runs against ASpecification 1.4.3.
- **README §7 multi-attribute ordering** chained `orderBy()->orderByDescending()`, which only sorts by the last key; the example now uses `orderByCustom()` with a composite key and says so. `thenBy()`/`thenByDescending()` are in the roadmap.
- **README §6 and Quick Start** examples run (see `groupBy()` above).

### Added
- **Query builder operators `between`, `notBetween`, `notIn`, `isNull`, `isNotNull`** (the README §8 example with `between` now runs; `between` takes an inclusive `[min, max]` pair and refuses anything else). Operator names are case- and separator-insensitive (`BETWEEN`, `Not In`, `is_null`). `ALinqQueryBuilder::operatorPredicate(string $operator, mixed $value = null): Closure` and `::supportedOperators(): array` expose the single evaluator; `UtilityOperations::createPredicate()` delegates to it, so the two operator tables that used to diverge are one (`contains`/`startsWith`/`endsWith` on a non-string value now return `false` instead of casting). An unknown operator lists the supported ones in its message.
- `ALinqPropertyAccess::hasProperty(mixed $item, string $property): bool`, with the ASpecification semantics (a `null` array value exists, a private property without getter does not, dot-notation fails on any missing segment).
- `Helpers\ALinqCallable` (`withKey()`, `acceptsKey()`, `hashKey()`), the shared callback and identity rules.
- Roadmap entries in the README for `thenBy()`/`thenByDescending()` and for `whereIn()`/`whereNotIn()`/`whereBetween()`/`single()`, announced in the 0.1.0 notes and never shipped (see the erratum notes in the 0.1.0 section below).
- Regression tests for everything above (suite **387 tests, 1050 assertions**, from 342/614).

### Documentation
- Erratum notes in the 0.1.0 section (method counts, coverage claim, methods announced and never shipped). `declare(strict_types=1)` added to the files changed in this release.

## [1.1.2] - 2026-10-08

### Fixed
- **Lazy materializers no longer lose items.** `toArray()`, `toCollection()` and `toObject()` on an `ALinqLazyCollection` stored each item under its source key, so a source whose keys repeat (two `yield from`, a `where()`/`skip()` pipeline over a plain list, any generator restarting its counter) reported `count()` = 4 and returned two items. The materializers now follow the key policy of the eager collection: a stream whose keys are all integers is a list and comes out reindexed (`where()` over `[1..6]` gives `[2, 4, 6]`, not `[1 => 2, 3 => 4, 5 => 6]`); a stream with string keys is a dictionary and keeps its keys, appending an item whose key collides instead of overwriting it. **Breaking for** code that relied on the gaps left by the lazy `where()`/`skip()`/`take()` over a list.
- **`chunk()` on a lazy stream** keyed each chunk by the source key: repeated keys overwrote items and the chunk never reached its size (six items with key 0 came out as one chunk of one). Chunks are now reindexed lists, as `array_chunk()` does on the eager side.
- **`pad()` on a lazy stream** keyed the padding by its counter, which collided with the keys that `where()`/`skip()` leave behind and overwrote real items (`['a','b','c']->where(v != 'a')->pad(5, 'p')` gave `b, p, p, p`). Padding is now appended after the last integer key, as `array_pad()` does.
- **`remember()` with repeated keys** collapsed the cache on the key, so the second pass saw two items where the first saw four.
- **`last()` / `lastOrDefault()` with a key-aware predicate** reversed the list without preserving keys, so the predicate saw the keys backwards (`fn($v, $k) => $k !== 0` on `[1..5]` answered 4). The real keys are now passed.

### Changed
- **`remember()` is resumable.** A short-circuited pass (`first()`, `any()`, `take(n)`) now caches what it consumed and the next pass replays the cache and keeps pulling from where the upstream stopped: the upstream runs once whatever the shape of the passes (1.1.1 discarded the partial buffer and re-ran it), and a single-pass PDO cursor wrapped in `remember()` can be followed by `first()` and then `toArray()` instead of throwing.

### Added
- Regression tests for the above (suite now **342 tests, 614 assertions**); two pre-existing tests that encoded the old key gaps and the 1.1.1 re-run of the upstream were updated to the new contract.

### Documentation
- Class DocBlock `@version` tags now state the package version in which each file was last changed (derived from the repository history; 19 files `0.1.0`, `IALinqLazyCollection` `1.1.1`, `ALinqCollection` `1.1.0`, the two files changed here `1.1.2`). This one-off correction of the tags does not itself count as a change to the files. CHANGELOG footer links rewritten for every version against the GitHub repository, and an `[Unreleased]` section added.

## [1.1.1] - 2026-10-07

### Fixed
- **`remember()` cache poisoning on partial traversal**: the cache is now promoted only after a complete pass. A short-circuited first traversal (`first()`, `any()`, `take(n)`, `zip()`) no longer leaves a truncated cache that later passes would serve as the whole stream.
- **`fromFile()` splitting long lines**: lines longer than `bufferSize - 1` bytes (4095 by default) were emitted as several items. Lines are now read to their end regardless of length; `$bufferSize` is kept as the stream read-chunk hint and must be greater than zero.

### Changed
- **Single-pass PDO cursors fail loudly**: a second traversal of `fromCursor()` or `from(PDOStatement)` used to yield an empty stream silently. It now throws a `RuntimeException` pointing to `->remember()` (with a complete first pass) as the way to re-traverse the stream.
- **DocBlock headers of `ALinqLazyCollection` and `IALinqLazyCollection`** aligned with the header standard used by every other class and interface of the library.

### Added
- Reproduction and regression tests for the fixes above in `ALinqLazyCollectionTest` (suite now **338 tests, 581 assertions**).

## [1.1.0] - 2026-10-05

### Added
- **Generator-Based Streaming Engine (`ALinqLazyCollection`)**: Implementation of deferred, pull-based streaming pipeline enabling processing of multi-gigabyte files, datasets, and unbuffered SQL cursors with constant $O(1)$ memory consumption (< 100 KB RAM).
- **Contract `IALinqLazyCollection`**: Formal interface contract segregating 35+ lazy transformation and terminal reduction operations.
- **Rewindable Multi-Pass Traversals**: Solved PHP's native `"Cannot traverse an already closed generator"` limitation by wrapping generator factories (`Closure(): iterable`), allowing infinite re-iteration of pipelines.
- **Deterministic Resource Disposal**: `fromFile()` and `fromCsv()` guarantee instant file handle closure via `try ... finally` blocks even during short-circuited termination (`take()`, `first()`).
- **Dynamic CSV Ingestion**: `ALinqLazyCollection::fromCsv()` automatically maps the first header line to associative row arrays with configurable delimiter, enclosure, and escape characters.
- **Unbuffered Database Cursor Streaming**: `ALinqLazyCollection::fromCursor()` streams PDO statements row-by-row with optional row projection mapper.
- **Intermediate Stream Caching**: `remember()` operator caches evaluated items during first traversal for lightning-fast subsequent passes without re-executing expensive upstream I/O.
- **Dual Pipeline Interoperability**: Seamless transitions between eager in-memory collections and lazy streams via `$collection->lazy()` and `$lazyCollection->toCollection()`.
- **Expanded Test Suite**: Added 30 new unit tests (76 assertions) in `ALinqLazyCollectionTest`, bringing total suite to **335 tests, 561 assertions** with 100% pass rate.

## [1.0.0] - 2026-10-04

### Added
- **Official Enterprise Release (v1.0.0)**: Production-ready certification for PHP 8.4+.
- **ASpecification Synergy**: Full compatibility and interoperability with [`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification) via `ALinqBridge` and `ALinqSpecificationVisitor`.
- **Bilingual Documentation**: Comprehensive technical documentation with English default ([`README.md`](README.md)) and Brazilian Portuguese ([`README.pt-BR.md`](README.pt-BR.md)).
- **Composer Corporate Alignment**: Standardized corporate metadata, support links (`source`, `issues`, `docs`), and author credentials for Packagist.org distribution.

### Verified
- **PHP 8.4 Runtime Certification**: 100% test pass rate across 305 tests and 485 assertions with zero deprecations and zero regressions.

## [0.1.1] - 2025-11-20

### Fixed
- **`where()` array reindexing**: Lists (sequential numeric arrays) are now reindexed starting from 0 after filtering, while associative arrays preserve their keys. This fixes issues when using `first()` or array index access after `where()` operations.
- **`firstOrDefault()` key independence**: Now uses `reset()` instead of `$this->items[0]` to get the first element, making it work correctly with any array key structure.

### Changed
- Simplified `firstOrDefault()` implementation removing redundant conditions
- Updated tests to reflect the new reindexing behavior for lists

## [0.1.0] - 2025-10-23

### Added
- Initial release of ALinq Collection library
- Core `ALinqCollection` class implementing fluent LINQ-style API
- `ALinqQueryBuilder` for building complex predicates
- `ALinqPropertyAccess` helper for property access patterns
- Comprehensive trait-based architecture:
  - **FilteringOperations** (17 methods): where, take, skip, distinct, first, last, chunk, pad, shuffle, contains *(Erratum, 2026-10-08: the trait has 16 methods)*
  - **JoiningOperations** (13 methods): join, groupJoin, concat, intersect, except, combine, replace, exceptBy, intersectBy, unionBy
  - **AggregationOperations** (13 methods): any, all, sum, average, min, max, product, aggregate, aggregateBy, countBy, maxBy, minBy, countValues
  - **SelectionOperations** (6 methods): select, selectMany, column, toDictionary, toObject, flip
  - **GroupingOperations** (1 method): groupBy
  - **OrderingOperations** (6 methods): orderBy, orderByDescending, orderByNatural, orderByCustom, orderByKey, reverse
  - **IteratorOperations** (6 methods): current, key, next, prev, reset, end
  - **UtilityOperations** (7 methods): isList, each, eachRecursive, random, extract, createPredicate, createPropertySelector
- Factory methods: `from()`, `range()`, `empty()`, `repeat()`
- Full PHP 8.4 compatibility using native array functions (array_any, array_all, array_find, array_find_key)
- Comprehensive test suite with 305 tests and 485 assertions
- 100% code coverage (338/338 lines, 84/84 methods, 11/11 classes) *(Erratum, 2026-10-08: measured at 93.20 % of lines on 1.1.1; `ALinqCollection::fromCsv`/`fromCursor` and the lazy `max`/`minBy`/`toObject` had no test)*
- PSR-4 autoloading with `Antevemus\ALinq` namespace
- Complete PHPDoc documentation
- MIT License

### Fixed
- Callable parameter validation using `$predicate === null` instead of `empty($predicate)` in `any()` and `all()` methods
- `intersect()` now properly removes duplicates using `array_unique()` for correct set theory behavior

### Changed
- Refactored monolithic 1,020-line class into modular trait-based architecture (89% size reduction to 112 lines)
- Method `all()` signature changed from `all(callable $predicate)` to `all(?callable $predicate = null)` for consistency

### Security
- Type-safe constructor rejecting null parameters
- Strict type hints throughout the codebase
- No external runtime dependencies (requires only PHP 8.4+)

## Release Notes

### v0.1.0 - Initial Release

This is the first public release of ALinq Collection, a comprehensive LINQ-style collection library for PHP 8.4+.

**Highlights:**
- 🎯 **69 LINQ-style methods** covering filtering, joining, aggregation, selection, grouping, ordering, and utilities *(Erratum, 2026-10-08: 68)*
- 🧪 **100% test coverage** with 305 comprehensive tests
- 🏗️ **Trait-based architecture** for clean separation of concerns
- ⚡ **PHP 8.4 native functions** for optimal performance
- 📦 **Zero runtime dependencies**
- 📚 **Complete documentation** with examples

**What's Included:**

**Filtering & Querying:**
- where, whereIn, whereNotIn, whereBetween *(Erratum, 2026-10-08: only `where()` shipped; `whereIn`/`whereNotIn`/`whereBetween` never existed and are listed in the README roadmap; the query builder offers `in`, `notIn`, `between` and `notBetween` since 1.2.0)*
- take, skip, distinct, distinctBy
- first, firstOrDefault, last, lastOrDefault
- single, singleOrDefault *(Erratum, 2026-10-08: only `singleOrDefault()` shipped; `single()` is in the README roadmap)*
- chunk, pad, shuffle, contains

**Joining & Set Operations:**
- join, groupJoin
- concat, intersect, except
- intersectBy, exceptBy, unionBy
- combine, replace, replaceRecursive

**Aggregation & Statistics:**
- any, all, count, countValues
- sum, average, min, max, product
- aggregate, aggregateBy, countBy
- minBy, maxBy

**Selection & Projection:**
- select, selectMany
- column, toDictionary, toObject
- flip

**Grouping & Ordering:**
- groupBy
- orderBy, orderByDescending
- orderByNatural, orderByCustom
- orderByKey, reverse

**Utilities:**
- each, eachRecursive
- random, extract
- isList
- Fluent query builder

**PHP Compatibility:**
Requires PHP 8.4.0 or higher for native array functions support.

**Installation:**
```bash
composer require antevemus/alinq-collection
```

**Quick Example:**
```php
use Antevemus\ALinq\ALinqCollection;

$users = ALinqCollection::from([
    ['name' => 'Alice', 'age' => 25, 'city' => 'NYC'],
    ['name' => 'Bob', 'age' => 30, 'city' => 'LA'],
    ['name' => 'Charlie', 'age' => 25, 'city' => 'NYC']
]);

$result = $users
    ->where(fn($u) => $u['age'] === 25)
    ->orderBy(fn($u) => $u['name'])
    ->select(fn($u) => $u['name'])
    ->toArray();

// Result: ['Alice', 'Charlie']
```

For complete documentation, see [README.md](README.md).

---

[Unreleased]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.5.1...v2.0.0
[1.5.1]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.4.3...v1.5.0
[1.4.3]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.4.2...v1.4.3
[1.4.2]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.4.1...v1.4.2
[1.4.1]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.3.2...v1.4.0
[1.3.2]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v0.1.1...v1.0.0
[0.1.1]: https://github.com/antevemus-it/Antevemus.AlinqCollection/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/antevemus-it/Antevemus.AlinqCollection/releases/tag/v0.1.0
