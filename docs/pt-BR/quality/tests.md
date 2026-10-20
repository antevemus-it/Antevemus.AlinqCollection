# 🧪 Qualidade de Código & Cobertura de Testes

A biblioteca possui cobertura de testes unitários com **100% de aprovação** no PHP 8.4:

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

Time: 00:04.179, Memory: 14.00 MB

OK, but some tests were skipped!
Tests: 743, Assertions: 3951, Skipped: 10.
====================================================================
 RESULTADO: 100% APROVADO | 0 REGRESSÕES | 0 DEPRECATIONS
====================================================================
```

- **743 Testes & 3951 Asserções** certificando todos os 8 traits funcionais, o motor de streaming, o contrato eager × lazy e a delegação ao acessor de propriedades compartilhado (`tests/Contract`), as propriedades dos operadores da 2.2.0 contra oráculos escritos à mão (`tests/Contract/ConsumerGapsPropertyTest.php`), os blocos da §9, os da 2.2.0 e o guia rápido das páginas da documentação como estão escritos (o mesmo código nos dois idiomas), a árvore da documentação (`tests/Unit/DocumentationTreeTest.php`: as mesmas páginas, navegação e código nos dois idiomas), e a fonte HTTP paginada contra um servidor `php -S` local em 127.0.0.1 (`tests/Unit/Http`, grupo `http`).
- **Sem `ext-intl` os 10 testes que usam `StringComparer::culture()` são pulados** (os 10 pulados acima, num PHP sem intl); com ela a suíte roda 743 testes e 4034 asserções com 1 pulado (o teste da extensão ausente). O CI roda as duas pernas.
- **Cobertura medida** (pcov, 2.2.0, com `ext-intl`, PHP 8.4.26): 289 de 299 métodos e 1619 de 1637 linhas de `src/`; as 18 linhas descobertas são guardas defensivas ou dependem do ambiente: duas novas no `StringComparer` (ext-mbstring ausente, ext-intl ausente), uma na junção externa compartilhada (a checagem de tipo desconhecido), catorze no caminho HTTP (ext-curl ausente, falhas ao montar o handle do curl, mensagem de transporte ou URL efetiva vazias, falha na espera do socket, URL base ilegível e entrada sem `rel` em `link()`) e a saída antecipada do `zip()` (coberta no PHP 8.4.13). Sem `ext-intl` (PHP 8.4.13): 288 de 299 métodos, 1574 de 1617 linhas (a diferença é o caminho do `culture()`).
- **Teste de mutação** (Infection, desde a 2.2.0, no escopo das linhas alteradas desde a 2.1.0; `infection.json5`, workflow Quality): 415 mutantes, 380 mortos, 24 erros e 1 time-out (detectados), 10 sobreviventes, todos equivalentes (MSI 97,59%).
- **Compatibilidade Nativa com PHP 8.4**: Validado com `array_any`, `array_all`, `array_find`, `array_find_key`.
- **Uma Dependência de Runtime, do Mesmo Autor**: Biblioteca pura em PHP 8.4; a única exigência é o [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (desde a 2.0.0), o acessor de propriedades compartilhado com o ASpecification, que não tem dependências próprias. Nenhum pacote de terceiros.
