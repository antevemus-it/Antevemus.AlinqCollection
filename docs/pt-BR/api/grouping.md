# Agrupamento e Particionamento (`GroupingOperations`)

### 6. Agrupamento e Particionamento (`GroupingOperations`)

O método `groupBy()` retorna uma coleção contendo instâncias de `ALinqCollection`, permitindo pipelines fluentes aninhados:

```php
$transacoes = ALinqCollection::from([
    ['categoria' => 'Alimentacao', 'valor' => 25.50],
    ['categoria' => 'Transporte',  'valor' => 15.00],
    ['categoria' => 'Alimentacao', 'valor' => 64.00],
    ['categoria' => 'Transporte',  'valor' => 30.00],
    ['categoria' => 'Lazer',       'valor' => 120.00],
]);

$resumo = $transacoes
    ->groupBy(fn($t) => $t['categoria'])
    ->select(fn(ALinqCollection $grupo, string $categoria) => [
        'categoria'          => $categoria,
        'total'              => $grupo->sum(fn($t) => $t['valor']),
        'quantidade_itens'   => $grupo->count(),
        'gasto_medio'        => round($grupo->average(fn($t) => $t['valor']), 2),
    ])
    ->orderByDescending(fn($linha) => $linha['total'])
    ->toArray();
```

#### `ALinqLookup`: um `groupBy()` materializado (desde a 2.2.0)

`toLookup()` devolve um `ALinqLookup` (o `ILookup<TKey, TElement>` do .NET): chaves na ordem da primeira aparição, os elementos de cada chave na ordem da fonte, `get($key)` (uma `ALinqCollection`, vazia para chave ausente), `contains($key)`, `keys()`, `count()` (número de chaves), iteração como `chave => ALinqCollection` (o formato do `groupBy()`), `toArray()` e `json_encode()`. `ArrayAccess` só de leitura: `$lookup['x']` é `get('x')`, `isset()` é `contains()`, e escrever lança. A chave segue o `groupBy()`: `int`, `string` ou `BackedEnum` (o valor); `null` lança, diferente do .NET, para que um lookup responda como o `groupBy()`.

<!-- tested: 6-lookup -->
```php
use Antevemus\ALinq\ALinqCollection;

$lookup = ALinqCollection::from([3, 1, 4, 1, 5, 9, 2, 6])->toLookup(fn($n) => $n % 3);
$lookup->toArray(); // [0 => [3, 9, 6], 1 => [1, 4, 1], 2 => [5, 2]]
count($lookup); // 3
$lookup->contains(1); // true
$lookup[7]->toArray(); // []
json_encode($lookup); // '[[3,9,6],[1,4,1],[5,2]]'
$lookup[7] = ALinqCollection::from([]); // throws LogicException: ALinqLookup is immutable.

// Chaves de grupo são chaves de array do PHP: 1 e '1' são uma chave só, '01' é outra
ALinqCollection::from([['k' => 1], ['k' => '1'], ['k' => '01']])->toLookup(fn($r) => $r['k'])->keys(); // [1, '01']
```
