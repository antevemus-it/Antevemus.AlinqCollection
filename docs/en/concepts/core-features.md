# 🌟 Core Features at a Glance

| Feature Domain | Capabilities |
| :--- | :--- |
| **🌊 Streaming & Big Data** | `ALinqLazyCollection`, `fromFile`, `fromCsv`, `fromCursor`, `fromHttpPages` (paginated HTTP API with prefetch), `where`, `select`, `takeWhile`, `skipWhile`, `zip`, `tap`, `remember`, constant $O(1)$ RAM for the streaming operators |
| **🔍 Filtering & Slicing** | `where`, `take`, `skip`, `distinct`, `distinctBy`, `first`, `firstOrDefault`, `last`, `lastOrDefault`, `single`, `singleOrDefault`, `whereIn`, `whereNotIn`, `whereBetween`, `chunk`, `pad`, `shuffle`, `contains` |
| **🔗 Joining & Set Theory** | `join` (inner), `leftJoin`, `rightJoin`, `fullJoin` (outer, as .NET 10/11), `groupJoin` (hierarchical), `concat`, `intersect`, `intersectWith`, `intersectBy`, `except`, `exceptWith`, `exceptBy`, `unionBy`, `combine`, `replace`, `replaceRecursive` |
| **📊 Aggregation & Metrics** | `any`, `all`, `sum`, `average`, `min`, `minBy`, `max`, `maxBy`, `product`, `countValues`, `aggregate`, `aggregateBy`, `countBy` |
| **🎯 Selection & Projection** | `select`, `selectMany` (flattening), `column`, `toDictionary`, `toObject`, `flip`, `toArray` |
| **🗂️ Grouping & Bucketing** | `groupBy` (keyed sub-collections with full fluent chaining capabilities) |
| **⚡ Ordering & Sorting** | `orderBy`, `orderByDescending`, `thenBy`, `thenByDescending`, `orderByNatural` (human alphanumeric sort), `orderByCustom`, `orderByKey`, `reverse` |
| **🧭 Iteration & Traversal** | `each`, `eachRecursive`, `current`, `key`, `next`, `prev`, `reset`, `end`, full `IteratorAggregate` and `Countable` parity |
| **🛠️ Dynamic Query Builder** | `ALinqQueryBuilder` (`create('and'\|'or')`, `where('field', '>=', $val)`, `whereIn`, `whereNotIn`, `whereBetween`, `toPredicate()`) |
| **🔎 Deep Property Access** | `PropertyAccess` of the shared [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) with dot-notation (`getValue($item, 'company.address.zip')`), getters and, as the last resort, non-public properties the class author marked `#[Specifiable]` |
