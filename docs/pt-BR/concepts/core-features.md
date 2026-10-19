# 🌟 Resumo dos Recursos

| Domínio de Operação | Capacidades |
| :--- | :--- |
| **🌊 Streaming & Big Data** | `ALinqLazyCollection`, `fromFile`, `fromCsv`, `fromCursor`, `fromHttpPages` (API HTTP paginada com prefetch), `where`, `select`, `takeWhile`, `skipWhile`, `zip`, `tap`, `remember`, consumo constante de memória $O(1)$ nos operadores de streaming |
| **🔍 Filtragem & Fatiamento** | `where`, `take`, `skip`, `distinct`, `distinctBy`, `first`, `firstOrDefault`, `last`, `lastOrDefault`, `single`, `singleOrDefault`, `whereIn`, `whereNotIn`, `whereBetween`, `chunk`, `pad`, `shuffle`, `contains` |
| **🔗 Junções & Conjuntos** | `join` (inner), `leftJoin`, `rightJoin`, `fullJoin` (externos, como no .NET 10/11), `groupJoin` (hierárquico), `concat`, `intersect`, `intersectWith`, `intersectBy`, `except`, `exceptWith`, `exceptBy`, `unionBy`, `combine`, `replace`, `replaceRecursive` |
| **📊 Agregações & Métricas** | `any`, `all`, `sum`, `average`, `min`, `minBy`, `max`, `maxBy`, `product`, `countValues`, `aggregate`, `aggregateBy`, `countBy` |
| **🎯 Seleção & Projeção** | `select`, `selectMany` (achatamento de listas aninhadas), `column`, `toDictionary`, `toObject`, `flip`, `toArray` |
| **🗂️ Agrupamento & Partições** | `groupBy` (subcoleções tipadas com encadeamento fluente completo) |
| **⚡ Ordenação & Classificação** | `orderBy`, `orderByDescending`, `thenBy`, `thenByDescending`, `orderByNatural` (ordenação alfanumérica humana), `orderByCustom`, `orderByKey`, `reverse` |
| **🧭 Iteração & Navegação** | `each`, `eachRecursive`, `current`, `key`, `next`, `prev`, `reset`, `end`, paridade total com `IteratorAggregate` e `Countable` |
| **🛠️ Query Builder Dinâmico** | `ALinqQueryBuilder` (`create('and'\|'or')`, `where('campo', '>=', $val)`, `whereIn`, `whereNotIn`, `whereBetween`, `toPredicate()`) |
| **🔎 Acesso a Propriedades** | `PropertyAccess` do pacote compartilhado [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) com dot-notation (`getValue($item, 'empresa.endereco.cep')`), getters e, como último recurso, propriedades não públicas que o autor da classe marcou com `#[Specifiable]` |
