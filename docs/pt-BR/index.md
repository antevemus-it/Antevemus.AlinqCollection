# Antevemus ALinq Collection

A documentação oficial da biblioteca, uma página por tema. As mesmas páginas existem em inglês, em `docs/en`; a Referência da API mantém os números de seção que tinha no README (`### 9.` continua sendo a §9, agora na sua própria página), e todo bloco PHP destas páginas é conferido pela suíte de testes.

> **Framework de Coleções Fluentes no Estilo LINQ para PHP 8.4+**  
> Um motor de manipulação de coleções expressivo, fluente e type-safe que traz a elegância do .NET Language Integrated Query (LINQ) para o PHP moderno. Impulsionado pelas funções nativas de array em C do PHP 8.4 (`array_any`, `array_all`, `array_find`, `array_find_key`), pipelines de streaming baseados em generators (`ALinqLazyCollection`), teoria dos conjuntos avançada, query builders dinâmicos de predicados, resolução profunda de propriedades por notação de ponto e integração nativa com o [`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification).

## Mapa da Documentação

### Primeiros passos

- [Instalação e guia rápido](getting-started/index.md): Composer, o repositório Git VCS e o guia rápido.

### Conceitos

- [Origens e filosofia](concepts/origins.md): Por que LINQ em PHP, e os seis princípios de projeto da biblioteca.
- [Resumo dos recursos](concepts/core-features.md): Todos os operadores da biblioteca numa tabela, por domínio.
- [Visão arquitetural](concepts/architecture.md): A árvore do código: as classes principais, as 11 interfaces e os 8 traits.
- [Sinergia com o ASpecification](concepts/aspecification-synergy.md): Regras do ASpecification avaliadas sobre coleções ALinq pelo `ALinqBridge`.

### Referência da API

- [Fábricas](api/factories.md) (§1): `from()`, `range()`, `repeat()`, `empty()`, `toArray()` e `toObject()`.
- [Filtragem e fatiamento](api/filtering.md) (§2): `where()`, `take()`/`skip()`, `distinct()`, `first()`, `chunk()` e os filtros por campo `whereIn()`, `whereNotIn()`, `whereBetween()`.
- [Junções e teoria dos conjuntos](api/joining.md) (§3): Junções internas e externas, `groupJoin()`, as operações de conjunto e `exceptByKeys()`/`intersectByKeys()`.
- [Agregações e métricas](api/aggregations.md) (§4): `any()`, `all()`, `sum()`, `average()`, `min()`/`max()`, `single()` e as agregações `*OrDefault()`.
- [Projeções e transformações](api/projections.md) (§5): `select()`, `selectMany()`, `column()`, `toDictionary(onDuplicate:)` e `toLookup()`.
- [Agrupamento e particionamento](api/grouping.md) (§6): `groupBy()` em subcoleções, e o `ALinqLookup`, um `groupBy()` materializado.
- [Ordenação e classificação](api/ordering.md) (§7): `orderBy()`/`thenBy()` estáveis, ordenações natural e customizada, e o `StringComparer`.
- [Query builder dinâmico](api/query-builder.md) (§8): `ALinqQueryBuilder`: condições no modo `and`/`or`, seus operadores e o `toPredicate()`.
- [Resolução profunda de propriedades](api/property-access.md) (§9): Caminhos em notação de ponto, a ordem de resolução e a marcação `#[Specifiable]` das propriedades não públicas.
- [Streaming e big data](api/lazy-streaming.md) (§10): `ALinqLazyCollection`: arquivos, CSV, cursores de banco, HTTP paginado, `remember()` e os estágios que acumulam dados.
- [O contrato](api/contract.md) (§11): As regras que as duas coleções obedecem: chaves, igualdade, chaves de grupo, coleções vazias, ordenação, comparadores, argumentos e mais.

### Qualidade

- [Testes](quality/tests.md): O relatório do PHPUnit, a cobertura medida e o teste de mutação.

### Projeto

- [Roadmap](project/roadmap.md): Cada release entregue e o que vem a seguir; a visão por marcos está no ROADMAP.md.
- [Licença e autoria](project/license-and-authors.md): A licença MIT, o autor e a organização.

## Onde Está Cada Coisa

| O quê | Onde |
| :--- | :--- |
| Visão geral, badges e o guia rápido | [README.pt-BR.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/README.pt-BR.md) |
| O que mudou em cada versão | [CHANGELOG.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/CHANGELOG.md) |
| Marcos entregues e planejados | [ROADMAP.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/ROADMAP.md) |
| Atualizando da 1.x | [UPGRADE-2.0.pt-BR.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/UPGRADE-2.0.pt-BR.md) |
| Procedência e licenças de terceiros | [NOTICE.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/NOTICE.md), [THIRD_PARTY_NOTICES.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/THIRD_PARTY_NOTICES.md) |
| Licença | [LICENSE](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/LICENSE) |
