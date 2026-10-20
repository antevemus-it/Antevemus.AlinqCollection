# Filtragem e Fatiamento (`FilteringOperations`)

### 2. Filtragem e Fatiamento (`FilteringOperations`)

```php
$collection = ALinqCollection::from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

// where(): Filtra elementos pelo predicado; reindexa listas numéricas automaticamente
$evens = $collection->where(fn($x) => $x % 2 === 0);

// take(n) & skip(n): Paginação e fatiamento
$page = $collection->skip(4)->take(3); // [5, 6, 7]

// distinct() & distinctBy(): Deduplicação
$people = ALinqCollection::from([
    ['id' => 1, 'dept' => 'IT'],
    ['id' => 2, 'dept' => 'HR'],
    ['id' => 3, 'dept' => 'IT'],
]);
$uniqueDepts = $people->distinctBy(fn($p) => $p['dept']); // IDs 1, 2

// Extração de elementos
$first = $collection->first(fn($x) => $x > 5);        // 6
$firstOrNull = $collection->firstOrDefault(null, fn($x) => $x > 100); // null
$single = $collection->where(fn($x) => $x === 3)->singleOrDefault(); // 3

// chunk(tamanho) & pad(tamanho, valor)
$chunks = $collection->chunk(3);                       // 4 coleções: [1, 2, 3], [4, 5, 6], [7, 8, 9], [10]
$sums = $chunks->select(fn($chunk) => $chunk->sum());  // [6, 15, 24, 10]
$padded = ALinqCollection::from([1, 2])->pad(5, 0); // [1, 2, 0, 0, 0]
```

#### Filtros por campo: `whereIn()`, `whereNotIn()`, `whereBetween()` (desde a 1.4.0)

Atalhos sobre o `where()` que leem um campo pelo `PropertyAccess` compartilhado (§9: chave de array, getter, propriedade, caminho com ponto como `'endereco.cidade'`). `whereIn`/`whereNotIn` comparam por identidade **estrita** (`in_array(..., true)`: `'18'` não é `18`); `whereBetween` é **inclusivo** e um valor `null` nunca está entre. As chaves seguem o contrato: lista sai reindexada, dicionário mantém as chaves.

```php
$staff = ALinqCollection::from([
    ['name' => 'Ana',   'dept' => 'IT',    'age' => 17],
    ['name' => 'Bruno', 'dept' => 'HR',    'age' => 18],
    ['name' => 'Carla', 'dept' => 'Sales', 'age' => 65],
    ['name' => 'Davi',  'dept' => 'IT',    'age' => null],
]);

$staff->whereIn('dept', ['IT', 'HR'])->column('name')->toArray(); // ['Ana', 'Bruno', 'Davi']
$staff->whereNotIn('dept', ['IT'])->column('name')->toArray();    // ['Bruno', 'Carla']
$staff->whereBetween('age', 18, 65)->column('name')->toArray();   // ['Bruno', 'Carla'] (limites incluídos, null nunca está entre)
$staff->whereIn('age', ['18'])->count();                          // 0 (estrito: '18' não é 18)
```
