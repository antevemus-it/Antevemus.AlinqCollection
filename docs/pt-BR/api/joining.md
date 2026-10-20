# Junções e Teoria dos Conjuntos (`JoiningOperations`)

### 3. Junções e Teoria dos Conjuntos (`JoiningOperations`)

Junções relacionais completas e operações matemáticas de conjuntos:

```php
$departments = ALinqCollection::from([
    ['id' => 10, 'name' => 'Engineering'],
    ['id' => 20, 'name' => 'Marketing'],
]);

$employees = ALinqCollection::from([
    ['name' => 'Alex', 'dept_id' => 10],
    ['name' => 'Beatrice', 'dept_id' => 10],
    ['name' => 'Carlos', 'dept_id' => 20],
]);

// 1. Inner Join (Estilo SQL)
$directory = $employees->join(
    inner: $departments->toArray(),
    outerKeySelector: fn($emp) => $emp['dept_id'],
    innerKeySelector: fn($dept) => $dept['id'],
    resultSelector: fn($emp, $dept) => [
        'employee'   => $emp['name'],
        'department' => $dept['name'],
    ]
)->toArray();

// 2. Group Join (Left Outer Join Hierárquico)
$deptRoster = $departments->groupJoin(
    inner: $employees->toArray(),
    outerKeySelector: fn($dept) => $dept['id'],
    innerKeySelector: fn($emp) => $emp['dept_id'],
    resultSelector: fn($dept, ALinqCollection $matchedEmps) => [
        'dept'  => $dept['name'],
        'staff' => $matchedEmps->column('name')->toArray(),
    ]
)->toArray();

// 3. Operações de Conjunto com Seletores de Chave
$datasetA = ALinqCollection::from([['id' => 1], ['id' => 2], ['id' => 3]]);
$datasetB = [['id' => 2], ['id' => 3], ['id' => 4]];

$intersect = $datasetA->intersectBy($datasetB, fn($item) => $item['id']); // [id: 2, id: 3]
$difference = $datasetA->exceptBy($datasetB, fn($item) => $item['id']);   // [id: 1]
$union = $datasetA->unionBy($datasetB, fn($item) => $item['id']);        // [id: 1, id: 2, id: 3, id: 4]
```

#### Junções externas: `leftJoin()`, `rightJoin()`, `fullJoin()` (desde a 1.4.0)

As junções externas do LINQ do .NET 10/11 (`Enumerable.LeftJoin`, `RightJoin`, `FullJoin`), com a assinatura `leftJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null)` (idem `rightJoin`/`fullJoin`). Substituem o `groupJoin()` + `selectMany()` escrito à mão. O seletor de resultado recebe `($outer, $inner)` com `null` no lado sem correspondência; sem seletor, cada resultado é o par `[$outer, $inner]`. O resultado é sempre lista. As chaves casam pela identidade estrita de `join()`/`groupJoin()`, e chave `null` nunca casa: esse item sai sem par.

- `leftJoin()`: todo item do outer, na ordem do outer (uma vez por correspondência, ou uma vez com `null`).
- `rightJoin()`: todo item do inner, na ordem do inner (como no .NET), cada um com os outers correspondentes na ordem do outer, ou com `null`.
- `fullJoin()`: os pares e os outers sem par na ordem do outer, depois os inners sem par na ordem do inner.

```php
$orders = ALinqCollection::from([
    ['id' => 1, 'customer' => 10],
    ['id' => 2, 'customer' => null], // chave null nunca casa
    ['id' => 3, 'customer' => 99],   // cliente inexistente
    ['id' => 4, 'customer' => 10],
]);
$customers = [
    ['id' => 10, 'name' => 'Acme'],
    ['id' => 20, 'name' => 'Globex'], // sem pedidos
];

$orderKey    = fn($o) => $o['customer'];
$customerKey = fn($c) => $c['id'];
$label       = fn(?array $o, ?array $c) => ($o['id'] ?? '-') . ':' . ($c['name'] ?? '-');

$orders->leftJoin($customers, $orderKey, $customerKey, $label)->toArray();
// ['1:Acme', '2:-', '3:-', '4:Acme']
$orders->rightJoin($customers, $orderKey, $customerKey, $label)->toArray();
// ['1:Acme', '4:Acme', '-:Globex']
$orders->fullJoin($customers, $orderKey, $customerKey, $label)->toArray();
// ['1:Acme', '2:-', '3:-', '4:Acme', '-:Globex']

// Sem seletor de resultado: pares [$outer, $inner]
[$order, $customer] = $orders->leftJoin($customers, $orderKey, $customerKey)->toArray()[1];
// $order = ['id' => 2, 'customer' => null], $customer = null
```

#### Chaves em vez de elementos: `exceptByKeys()`, `intersectByKeys()`; qualquer iterable no `join()` (desde a 2.2.0)

`exceptByKeys(iterable $keys, callable $keySelector)` e `intersectByKeys(...)` são o `ExceptBy`/`IntersectBy` do .NET 6: o segundo argumento é uma sequência de **chaves** (array, gerador, `ALinqCollection`, `ALinqLazyCollection`), lida uma vez; o seletor roda só sobre esta coleção. São operações de conjunto: cada chave sai uma vez (a primeira ocorrência), chaves comparam por identidade estrita (`'2'` não é `2`), lista é reindexada e dicionário mantém as chaves, e chave `null` casa com um `null` em `$keys` (regra de conjunto; em junção `null` nunca casa).

> [!WARNING]
> **Divergência do .NET em `exceptBy()`/`intersectBy()`.** Os `exceptBy(array $second, callable $keySelector)` e `intersectBy(...)` existentes recebem uma sequência de **elementos** do mesmo formato e aplicam o seletor aos **dois** lados (a forma do MoreLINQ, anterior ao .NET 6). Eles não mudam. Chamados do jeito do .NET (`exceptBy([1, 2], fn($r) => $r['id'])`), leem um índice de um inteiro, emitem aviso e respondem errado: use `exceptByKeys()`.

`join()` e `groupJoin()` aceitam qualquer iterable como lado inner desde a 2.2.0 (era `array`): ele é indexado numa leitura só, então gerador ou cursor lazy funcionam, com as mesmas respostas da forma com array.

<!-- tested: 3-keys -->
```php
use Antevemus\ALinq\ALinqCollection;

$documents = ALinqCollection::from([
    ['id' => 1, 'type' => 'ID card'],
    ['id' => 2, 'type' => 'ID card'],
    ['id' => 3, 'type' => 'Proof of address'],
]);
$links = ALinqCollection::from([['document_id' => 2, 'participant_id' => 10]]);

// exceptByKeys(): o segundo argumento é uma sequência de CHAVES (o ExceptBy do .NET 6), identidade estrita
$unlinked = $documents->exceptByKeys($links->select(fn($l) => $l['document_id']), fn($d) => $d['id'])->column('id')->toArray();
$unlinked; // [1, 3]
$documents->intersectByKeys([2, 3], fn($d) => $d['id'])->column('id')->toArray(); // [2, 3]
$documents->exceptByKeys(['2'], fn($d) => $d['id'])->count(); // 3

// exceptBy(): o segundo argumento é uma sequência de ELEMENTOS, o seletor vale para os dois lados
$documents->exceptBy([['id' => 2]], fn($d) => $d['id'])->column('id')->toArray(); // [1, 3]

// join()/groupJoin(): qualquer iterable como lado inner, lido uma vez (gerador, cursor lazy, coleção)
$participants = (function () {
    yield ['id' => 10, 'document_id' => 2, 'name' => 'Ana'];
})();
$documents->join($participants, fn($d) => $d['id'], fn($p) => $p['document_id'], fn($d, $p) => $p['name'] . ': ' . $d['type'])->toArray(); // ['Ana: ID card']
$documents->groupJoin(ALinqCollection::from([]), fn($d) => $d['id'], fn($p) => $p['document_id'], fn($d, $ps) => $ps->count())->toArray(); // [0, 0, 0]
```
