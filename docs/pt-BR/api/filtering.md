# Filtragem e Fatiamento (`FilteringOperations`)

### 2. Filtragem e Fatiamento (`FilteringOperations`)

```php
$colecao = ALinqCollection::from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

// where(): Filtra elementos pelo predicado; reindexa listas numéricas automaticamente
$pares = $colecao->where(fn($x) => $x % 2 === 0);

// take(n) & skip(n): Paginação e fatiamento
$pagina = $colecao->skip(4)->take(3); // [5, 6, 7]

// distinct() & distinctBy(): Deduplicação
$pessoas = ALinqCollection::from([
    ['id' => 1, 'depto' => 'TI'],
    ['id' => 2, 'depto' => 'RH'],
    ['id' => 3, 'depto' => 'TI'],
]);
$deptosUnicos = $pessoas->distinctBy(fn($p) => $p['depto']); // IDs 1, 2

// Extração de elementos
$primeiro = $colecao->first(fn($x) => $x > 5);        // 6
$primeiroOuNulo = $colecao->firstOrDefault(null, fn($x) => $x > 100); // null
$unico = $colecao->where(fn($x) => $x === 3)->singleOrDefault(); // 3

// chunk(tamanho) & pad(tamanho, valor)
$grupos = $colecao->chunk(3);                        // 4 coleções: [1, 2, 3], [4, 5, 6], [7, 8, 9], [10]
$somas = $grupos->select(fn($grupo) => $grupo->sum()); // [6, 15, 24, 10]
$preenchido = ALinqCollection::from([1, 2])->pad(5, 0); // [1, 2, 0, 0, 0]
```

#### Filtros por campo: `whereIn()`, `whereNotIn()`, `whereBetween()` (desde a 1.4.0)

Atalhos sobre o `where()` que leem um campo pelo `PropertyAccess` compartilhado (§9: chave de array, getter, propriedade, caminho com ponto como `'endereco.cidade'`). `whereIn`/`whereNotIn` comparam por identidade **estrita** (`in_array(..., true)`: `'18'` não é `18`); `whereBetween` é **inclusivo** e um valor `null` nunca está entre. As chaves seguem o contrato: lista sai reindexada, dicionário mantém as chaves.

```php
$equipe = ALinqCollection::from([
    ['nome' => 'Ana',   'depto' => 'TI',     'idade' => 17],
    ['nome' => 'Bruno', 'depto' => 'RH',     'idade' => 18],
    ['nome' => 'Carla', 'depto' => 'Vendas', 'idade' => 65],
    ['nome' => 'Davi',  'depto' => 'TI',     'idade' => null],
]);

$equipe->whereIn('depto', ['TI', 'RH'])->column('nome')->toArray(); // ['Ana', 'Bruno', 'Davi']
$equipe->whereNotIn('depto', ['TI'])->column('nome')->toArray();    // ['Bruno', 'Carla']
$equipe->whereBetween('idade', 18, 65)->column('nome')->toArray();  // ['Bruno', 'Carla'] (limites incluídos, null nunca está entre)
$equipe->whereIn('idade', ['18'])->count();                         // 0 (estrito: '18' não é 18)
```
