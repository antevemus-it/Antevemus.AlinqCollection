# 🗺️ Roadmap & Ecossistema

- [x] **v1.0.0**: Release oficial estável de produção com 8 traits centrais, 305 testes e motor nativo PHP 8.4.
- [x] **Sinergia com ASpecification**: Integração direta com `Antevemus.ASpecification` via `ALinqBridge`.
- [x] **v1.1.0**: Pipeline de avaliação preguiçosa (Lazy Evaluation) baseado em Generators (`ALinqLazyCollection`) para fluxos de dados de múltiplos gigabytes sem esgotar a memória.
- [x] **v1.1.1**: Correções do motor de streaming: `remember()` só guarda passagens completas, `fromFile()` preserva linhas longas inteiras, cursores de passagem única falham alto na segunda travessia.
- [x] **v1.1.2**: Materializadores lazy nunca perdem itens (lista reindexada, dicionário preservado), `remember()` resumível, `last()` com a chave real.
- [x] **v1.2.0**: Promessas do README I: grupos de `groupBy()` são coleções, `select()` recebe a chave, todo operador aceita callables nativos, `distinct()` estrito e seguro para arrays/objetos, `selectMany()` achata qualquer iterable, `between`/`notIn`/`isNull` no query builder, dot-notation e resolução de getters no `ALinqPropertyAccess`.
- [x] **v1.3.0**: O contrato: uma regra de chaves, identidade estrita, semântica LINQ em coleções vazias, argumentos e chaves de grupo validados, agregações e query builder cientes de `null`, `count($predicate)`, `JsonSerializable`; paridade eager × lazy garantida por teste (§11).
- [x] **v1.3.2**: Robustez das fontes lazy: linhas irregulares de CSV, BOM, diretórios em `fromFile()`, um statement por coleção em `fromCursor()`.
- [x] **v1.4.0**: `whereIn()` / `whereNotIn()` / `whereBetween()` / `single()`, anunciados nas notas da 0.1.0 e enfim entregues (ver a errata no CHANGELOG).
- [x] **v1.4.0**: `leftJoin()` / `rightJoin()` / `fullJoin()`: junções externas devolvendo pares `[$outer, $inner]` com `null` no lado sem correspondência, como `Enumerable.LeftJoin`/`RightJoin`/`FullJoin` no .NET 10/11 (substituem o `groupJoin()` + `selectMany()` à mão).
- [x] **v1.4.0**: `thenBy()` / `thenByDescending()`: ordenação composta e estável por várias chaves, também na coleção lazy.
- [x] **v1.4.1**: segurança: o último recurso não público do accessor de propriedades passa a ser opt-in com `#[Specifiable]`, e filtros e ordenações sobre um campo privado sem marca deixam de servir de oráculo de leitura.
- [x] **v1.4.2**: `singleOrDefault()` lança com as mesmas mensagens de `single()`, nas duas coleções.
- [x] **v1.4.3**: `suggest` do ASpecification passa a `^1.9.0`; marco 12 do roadmap corrigido (Fiber não é paralelismo).
- [x] **v1.5.0**: `fromHttpPages()`: fonte lazy sobre uma API HTTP paginada que baixa a próxima página com `curl_multi` enquanto a atual é consumida (zero dependência, marco 12 do ROADMAP, parte do núcleo).
- [x] **v1.5.1**: o workflow do GitHub Actions passa a rodar (uma chave de ambiente duplicada fazia o GitHub recusá-lo); `suggest` do ASpecification passa a `^1.10.0`.
- [x] **v2.0.0**: acessor de propriedades compartilhado com o `Antevemus.ASpecification`: as duas bibliotecas resolvem nomes pelo [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (marco 11 do ROADMAP), `ALinqPropertyAccess` vira fachada depreciada até a 3.0; `extract()` (depreciado desde a 1.3.0) removido; `suggest` do ASpecification passa a `^2.0.0`. Veja o [UPGRADE-2.0.pt-BR.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/UPGRADE-2.0.pt-BR.md).
- [x] **v2.1.0**: `minOrDefault()` / `maxOrDefault()` / `averageOrDefault()` / `minByOrDefault()` / `maxByOrDefault()`: o agregado, ou um fallback quando não há o que agregar, numa passada só (seguros em fontes de passada única).
- [x] **v2.2.0**: as lacunas achadas pelo primeiro consumidor em produção: `StringComparer` (ordinal, sem caixa, cultura pela ICU) em `orderBy*()`, `distinct*()`, `min*()`, `max*()`; `toLookup()` / `ALinqLookup`; `toDictionary(onDuplicate:)`; `groupBy()` / `countBy()` / `aggregateBy()` / `toDictionary()` / `toLookup()` na coleção lazy; `exceptByKeys()` / `intersectByKeys()`; `join()` / `groupJoin()` sobre qualquer iterable; `fromCsv(separator: null)`; `implode()` ([marco 14 do ROADMAP](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/ROADMAP.md)).
- [ ] **Futuro**: o restante do marco de I/O concorrente, em pacotes satélites: coleção assíncrona sobre Fibers para SDKs assíncronos e paralelismo de CPU com processos. Fiber sozinha não é paralelismo.

A visão por marcos desta lista, com releases-alvo, vive em [ROADMAP.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/ROADMAP.md).
