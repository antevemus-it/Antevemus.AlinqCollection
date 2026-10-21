# 🧪 Qualidade de Código & Cobertura de Testes

A biblioteca possui cobertura de testes unitários com **100% de aprovação** no PHP 8.4:

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
 RESULTADO: 100% APROVADO | 0 REGRESSÕES | 0 DEPRECATIONS
====================================================================
```

- **836 Testes & 4631 Asserções** certificando todos os 8 traits funcionais, o motor de streaming, o contrato eager × lazy e a delegação ao acessor de propriedades compartilhado (`tests/Contract`), as propriedades dos operadores da 2.2.0 contra oráculos escritos à mão (`tests/Contract/ConsumerGapsPropertyTest.php`) e as dos comparadores da 2.4.0 em agrupamento, operações de conjunto e junções (`tests/Contract/ComparerKeyPropertyTest.php`), os blocos da §9, os da 2.2.0 e da 2.4.0 e o guia rápido das páginas da documentação como estão escritos (o mesmo código nos dois idiomas), a árvore da documentação (`tests/Unit/DocumentationTreeTest.php`: as mesmas páginas, navegação e código nos dois idiomas), e as fontes HTTP paginadas contra um servidor `php -S` local em 127.0.0.1 (`tests/Unit/Http`, grupo `http`). A 2.4.0 acrescentou 64 testes e 529 asserções (587 com `ext-intl`): os comparadores, o `join()`/`groupJoin()` lazy e o seletor de semente do `aggregateBy()` (`tests/Unit/ComparerKeyOperationsTest.php`, 31 testes, a junção de 10 000 × 10 000 linhas com comparador inclusa), as propriedades deles (8), o `fromHttpPagesByIndex()` (`tests/Unit/Http/FromHttpPagesByIndexTest.php`, 19) e os três novos blocos testados nos dois idiomas (6).
- **Sem `ext-intl` os 17 testes que usam `StringComparer::culture()` são pulados** (os 17 pulados acima, num PHP sem intl: 10 da 2.2.0 e 7 da 2.4.0, o agrupamento sem acento, as suas quatro propriedades e o bloco de agrupamento nos dois idiomas); com ela a suíte roda 836 testes e 4772 asserções com 1 pulado (o teste da extensão ausente). O CI roda as duas pernas.
- **Cobertura medida** (pcov, 2.2.0, com `ext-intl`, PHP 8.4.26): 289 de 299 métodos e 1619 de 1637 linhas de `src/`; as 18 linhas descobertas são guardas defensivas ou dependem do ambiente: duas novas no `StringComparer` (ext-mbstring ausente, ext-intl ausente), uma na junção externa compartilhada (a checagem de tipo desconhecido), catorze no caminho HTTP (ext-curl ausente, falhas ao montar o handle do curl, mensagem de transporte ou URL efetiva vazias, falha na espera do socket, URL base ilegível e entrada sem `rel` em `link()`) e a saída antecipada do `zip()` (coberta no PHP 8.4.13). Sem `ext-intl` (PHP 8.4.13): 288 de 299 métodos, 1574 de 1617 linhas (a diferença é o caminho do `culture()`).
- **Teste de mutação** (Infection, desde a 2.2.0, no escopo das linhas alteradas desde a 2.1.0; `infection.json5`, workflow Quality): 415 mutantes, 380 mortos, 24 erros e 1 time-out (detectados), 10 sobreviventes, todos equivalentes (MSI 97,59%). Os 8 mutantes das linhas acrescentadas na 2.3.0 (`from()` com a forma declarada, `isList()`) foram todos mortos (MSI 100 %, medido localmente com `--git-diff-base=v2.2.2`). Nas linhas acrescentadas ou alteradas na 2.4.0 (medido localmente contra a árvore da 2.3.1, PHP 8.4.13 com pcov): 257 mutantes, 232 mortos e 22 erros (detectados), 3 sobreviventes, todos equivalentes (`iterator_to_array(..., false)` sobre um generator que produz sem chaves, onde `true` devolve a mesma lista), MSI 98,83 %.
- **Compatibilidade Nativa com PHP 8.4**: Validado com `array_any`, `array_all`, `array_find`, `array_find_key`.
- **Uma Dependência de Runtime, do Mesmo Autor**: Biblioteca pura em PHP 8.4; a única exigência é o [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (desde a 2.0.0), o acessor de propriedades compartilhado com o ASpecification, que não tem dependências próprias. Nenhum pacote de terceiros.
