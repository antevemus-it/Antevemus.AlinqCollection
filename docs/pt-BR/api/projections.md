# Projeções e Transformações (`SelectionOperations`)

### 5. Projeções e Transformações (`SelectionOperations`)

```php
$catalog = ALinqCollection::from([
    ['sku' => 'A1', 'tags' => ['electronics', 'mobile'], 'price' => 799],
    ['sku' => 'A2', 'tags' => ['mobile', 'accessories'], 'price' => 29],
]);

// select(): Mapeamento 1 para 1
$skuList = $catalog->select(fn($item) => $item['sku'])->toArray(); // ['A1', 'A2']

// selectMany(): Achatamento de coleções aninhadas 1 para N
$allTags = $catalog
    ->selectMany(fn($item) => $item['tags'])
    ->distinct()
    ->toArray(); // ['electronics', 'mobile', 'accessories']

// toDictionary(): Transformação em mapa chave-valor associativo
$priceMap = $catalog->toDictionary(
    keySelector: fn($item) => $item['sku'],
    elementSelector: fn($item) => $item['price']
); // ['A1' => 799, 'A2' => 29]
```

#### Chaves repetidas e lookups: `toDictionary(onDuplicate:)`, `toLookup()` (desde a 2.2.0)

`toDictionary($keySelector, $elementSelector = null, DuplicateKeyPolicy $onDuplicate = DuplicateKeyPolicy::Throw)`: `Throw` é o comportamento de todas as versões anteriores (a mesma mensagem); `KeepFirst` mantém o primeiro elemento e não roda o seletor de elemento para os repetidos; `KeepLast` mantém o último, na posição da primeira ocorrência, como `$mapa[$linha->id] = $linha`. O seletor de chave roda para todos os itens e toda chave é validada em todas as políticas. `toLookup($keySelector, $elementSelector = null)` devolve um `ALinqLookup` imutável (§6): `get()` de chave ausente é coleção vazia, nunca índice indefinido. Os dois existem também na coleção lazy, como operações terminais que leem o fluxo uma vez.

<!-- tested: 5-dictionary -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\DuplicateKeyPolicy;

$quotes = ALinqCollection::from([
    ['id' => 7, 'unit' => 'North', 'status' => 'draft'],
    ['id' => 8, 'unit' => 'South', 'status' => 'sent'],
    ['id' => 7, 'unit' => 'North', 'status' => 'approved'], // uma versão posterior da cotação 7
]);

// toDictionary(): chave repetida lança por padrão; uma política pode manter a primeira ou a última
$quotes->toDictionary(fn($q) => $q['id'], fn($q) => $q['status']); // throws InvalidArgumentException: toDictionary() key 7 produced twice (second time for item at key 2)
$quotes->toDictionary(fn($q) => $q['id'], fn($q) => $q['status'], DuplicateKeyPolicy::KeepFirst); // [7 => 'draft', 8 => 'sent']
$quotes->toDictionary(fn($q) => $q['id'], fn($q) => $q['status'], DuplicateKeyPolicy::KeepLast); // [7 => 'approved', 8 => 'sent']

// toLookup(): uma chave, vários elementos; chave ausente é coleção vazia
$byUnit = $quotes->toLookup(fn($q) => $q['unit'], fn($q) => $q['id']);
$byUnit->get('North')->toArray(); // [7, 7]
$byUnit['South']->toArray(); // [8]
$byUnit->get('East')->count(); // 0
$byUnit->keys(); // ['North', 'South']
```
