# Agrupamento e Particionamento (`GroupingOperations`)

### 6. Agrupamento e Particionamento (`GroupingOperations`)

O método `groupBy()` retorna uma coleção contendo instâncias de `ALinqCollection`, permitindo pipelines fluentes aninhados:

```php
$transactions = ALinqCollection::from([
    ['category' => 'Food', 'amount' => 25.50],
    ['category' => 'Transport', 'amount' => 15.00],
    ['category' => 'Food', 'amount' => 64.00],
    ['category' => 'Transport', 'amount' => 30.00],
    ['category' => 'Leisure', 'amount' => 120.00],
]);

$summary = $transactions
    ->groupBy(fn($t) => $t['category'])
    ->select(fn(ALinqCollection $group, string $category) => [
        'category'     => $category,
        'total'        => $group->sum(fn($t) => $t['amount']),
        'transaction_count' => $group->count(),
        'average_spend'     => round($group->average(fn($t) => $t['amount']), 2),
    ])
    ->orderByDescending(fn($row) => $row['total'])
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

#### Comparadores: um grupo para cada grafia (desde a 2.4.0)

`groupBy()`, `countBy()`, `aggregateBy()`, `toDictionary()` e `toLookup()` aceitam um `IALinqEqualityComparer` como último parâmetro, nas duas coleções: as chaves iguais segundo ele formam **um grupo, nomeado pela primeira chave vista** (o primeiro elemento representa o grupo, como no LINQ: `'São Paulo'` antes de `'sao paulo'` dá o grupo `'São Paulo'`; na ordem inversa, `'sao paulo'`). A identidade é por hash (`hash()` separa em baldes, `equals()` decide), então o agrupamento continua linear. O comparador recebe a chave depois da regra de chaves acima (um `BackedEnum` como o seu valor; `null` e os outros tipos continuam lançando), e duas chaves que ele distingue mas que o PHP transforma na mesma chave de array (`1` e `'1'`) lançam `InvalidArgumentException`. O lookup do `toLookup()` guarda o comparador, então `get()`/`contains()` encontram um grupo por qualquer chave igual ao seu nome. `toDictionary()` mantém a primeira chave vista (com `KeepLast` ela guarda o elemento do último item igual a ela), e a mensagem de repetição cita a chave como produzida na segunda vez.

`aggregateBy()` também aceita um seletor de semente, `aggregateBy($keySelector, $seed, $func, $comparer = null, $seedSelector = null)`: um callable `fn($key)` chamado uma vez por grupo, quando o grupo começa, com a chave que o nomeia, cuja resposta é a semente desse grupo (o `seedSelector` do .NET 9): um objeto novo por grupo, ou uma semente que depende da chave. Quando ele é dado, `$seed` é ignorada (passe `null`); sem ele `$seed` é a semente de todo grupo qualquer que seja o tipo, como antes, uma Closure inclusive (ela é o próprio acumulador, nunca chamada).

<!-- tested: 6-comparer -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Comparison\StringComparer;
use Antevemus\ALinq\DuplicateKeyPolicy;

$visits = ALinqCollection::from([
    ['city' => 'São Paulo', 'visits' => 3],
    ['city' => 'Rio', 'visits' => 1],
    ['city' => 'sao paulo', 'visits' => 2],
    ['city' => 'SAO PAULO', 'visits' => 4],
]);
$ptBr = StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true);
$city = fn($v) => $v['city'];

// A primeira grafia vista nomeia o grupo
$visits->countBy($city, $ptBr)->toArray(); // ['São Paulo' => 3, 'Rio' => 1]
$visits->groupBy($city, $ptBr)->select(fn($group) => $group->sum(fn($v) => $v['visits']))->toArray(); // ['São Paulo' => 9, 'Rio' => 1]
$visits->toDictionary($city, fn($v) => $v['visits'], DuplicateKeyPolicy::KeepLast, $ptBr); // ['São Paulo' => 4, 'Rio' => 1]
// O lookup guarda o comparador: qualquer grafia encontra o grupo
$visits->toLookup($city, fn($v) => $v['visits'], $ptBr)->get('SÃO paulo')->toArray(); // [3, 2, 4]
// aggregateBy(): um seletor de semente dá a cada grupo a sua semente
$visits->aggregateBy($city, null, fn($trace, $v) => $trace . ' ' . $v['visits'], $ptBr, fn($name) => $name . ':')->toArray(); // ['São Paulo' => 'São Paulo: 3 2 4', 'Rio' => 'Rio: 1']
// Sem comparador as chaves são chaves de array do PHP, como antes
$visits->countBy($city)->count(); // 4
```
