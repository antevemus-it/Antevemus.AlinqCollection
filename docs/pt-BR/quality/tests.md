# 🧪 Qualidade de Código & Cobertura de Testes

A biblioteca possui cobertura de testes unitários com **100% de aprovação** no PHP 8.4:

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
 RESULTADO: 100% APROVADO | 0 REGRESSÕES | 0 DEPRECATIONS
====================================================================
```

- **772 Testes & 4102 Asserções** certificando todos os 8 traits funcionais, o motor de streaming, o contrato eager × lazy e a delegação ao acessor de propriedades compartilhado (`tests/Contract`), as propriedades dos operadores da 2.2.0 contra oráculos escritos à mão (`tests/Contract/ConsumerGapsPropertyTest.php`), os blocos da §9, os da 2.2.0 e o guia rápido das páginas da documentação como estão escritos (o mesmo código nos dois idiomas), a árvore da documentação (`tests/Unit/DocumentationTreeTest.php`: as mesmas páginas, navegação e código nos dois idiomas), e a fonte HTTP paginada contra um servidor `php -S` local em 127.0.0.1 (`tests/Unit/Http`, grupo `http`).
- **Sem `ext-intl` os 10 testes que usam `StringComparer::culture()` são pulados** (os 10 pulados acima, num PHP sem intl); com ela a suíte roda 772 testes e 4185 asserções com 1 pulado (o teste da extensão ausente). O CI roda as duas pernas.
- **Cobertura medida** (pcov, 2.2.0, com `ext-intl`, PHP 8.4.26): 289 de 299 métodos e 1619 de 1637 linhas de `src/`; as 18 linhas descobertas são guardas defensivas ou dependem do ambiente: duas novas no `StringComparer` (ext-mbstring ausente, ext-intl ausente), uma na junção externa compartilhada (a checagem de tipo desconhecido), catorze no caminho HTTP (ext-curl ausente, falhas ao montar o handle do curl, mensagem de transporte ou URL efetiva vazias, falha na espera do socket, URL base ilegível e entrada sem `rel` em `link()`) e a saída antecipada do `zip()` (coberta no PHP 8.4.13). Sem `ext-intl` (PHP 8.4.13): 288 de 299 métodos, 1574 de 1617 linhas (a diferença é o caminho do `culture()`).
- **Teste de mutação** (Infection, desde a 2.2.0, no escopo das linhas alteradas desde a 2.1.0; `infection.json5`, workflow Quality): 415 mutantes, 380 mortos, 24 erros e 1 time-out (detectados), 10 sobreviventes, todos equivalentes (MSI 97,59%). Os 8 mutantes das linhas acrescentadas na 2.3.0 (`from()` com a forma declarada, `isList()`) foram todos mortos (MSI 100 %, medido localmente com `--git-diff-base=v2.2.2`).
- **Compatibilidade Nativa com PHP 8.4**: Validado com `array_any`, `array_all`, `array_find`, `array_find_key`.
- **Uma Dependência de Runtime, do Mesmo Autor**: Biblioteca pura em PHP 8.4; a única exigência é o [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (desde a 2.0.0), o acessor de propriedades compartilhado com o ASpecification, que não tem dependências próprias. Nenhum pacote de terceiros.
