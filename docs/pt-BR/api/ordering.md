# Ordenação e Classificação (`OrderingOperations`)

### 7. Ordenação e Classificação (`OrderingOperations`)

```php
$files = ALinqCollection::from([
    'file10.txt', 'file2.txt', 'file1.txt', 'FILE100.txt'
]);

// Ordenação Alfanumérica Natural (ordenação humana: file1, file2, file10, FILE100)
$naturalSorted = $files->orderByNatural(caseSensitive: false)->toArray();

$employees = ALinqCollection::from([
    ['name' => 'Ana',   'dept' => 'Sales', 'salary' => 5000],
    ['name' => 'Bruno', 'dept' => 'IT',    'salary' => 7000],
    ['name' => 'Carla', 'dept' => 'IT',    'salary' => 9000],
    ['name' => 'Davi',  'dept' => 'IT',    'salary' => 7000],
]);

// Ordenação composta (desde a 1.4.0): orderBy() seguido de quantos thenBy()/thenByDescending() quiser
$sorted = $employees
    ->orderBy(fn($e) => $e['dept'])
    ->thenByDescending(fn($e) => $e['salary'])
    ->column('name')
    ->toArray();
// ['Carla', 'Bruno', 'Davi', 'Ana']: IT 9000, IT 7000, IT 7000, Sales 5000 (Bruno antes de Davi: estável)

// Um comparador recebe as duas chaves: fn($chaveA, $chaveB): int
$byNameLength = $employees
    ->orderBy(fn($e) => $e['dept'])
    ->thenBy(fn($e) => $e['name'], fn($a, $b) => strlen($a) <=> strlen($b))
    ->column('name')
    ->toArray();
// ['Davi', 'Bruno', 'Carla', 'Ana']

// thenBy() só refina uma coleção que vem direto de orderBy*()/thenBy*()
$employees->thenBy(fn($e) => $e['name']);
// LogicException: thenBy() requires a preceding orderBy().
$employees->orderBy(fn($e) => $e['dept'])->where(fn($e) => true)->thenByDescending(fn($e) => $e['salary']);
// LogicException: thenByDescending() requires a preceding orderBy().

// orderByCustom() continua ordenando com uma única comparação sobre os itens inteiros
$sameOrder = $employees
    ->orderByCustom(fn($a, $b) => [$a['dept'], -$a['salary']] <=> [$b['dept'], -$b['salary']])
    ->column('name')
    ->toArray();
// ['Carla', 'Bruno', 'Davi', 'Ana']
```

`orderBy()`/`orderByDescending()` são estáveis e chamam cada seletor de chave uma vez por item. `thenBy()`/`thenByDescending()` acrescentam um critério que só decide entre itens empatados em todos os critérios anteriores; empate total mantém a ordem da origem. O seletor de chave deles recebe `($item, $key)` com a chave da origem, antes da ordenação. Um segundo `orderBy()` começa uma ordenação nova (a chave dele vira a principal), como no LINQ.

#### Comparando texto: `StringComparer` (desde a 2.2.0)

`orderBy()`/`orderByDescending()` passam a aceitar o comparador que o `thenBy()` já aceitava (`fn($chaveA, $chaveB): int`), assim como `min()`, `max()`, `minBy()`, `maxBy()` e as variantes `*OrDefault()`; `distinct()`/`distinctBy()` aceitam um `IALinqEqualityComparer` (um `equals()` e um `hash()` compatível, o `IEqualityComparer<T>` do .NET). `Antevemus\ALinq\Comparison\StringComparer` é os dois, então um objeto serve a todo operador, inclusive `contains()`, `orderByCustom()`, `intersectWith()` e `exceptWith()`, que já aceitavam um callable:

- `StringComparer::ordinal()`: byte a byte. **Não** é o padrão: sem comparador as chaves passam pelo `<=>` do PHP, que lê strings numéricas como números (`'10' <=> '9'` é `1`, `'1e1' <=> '10'` é `0`).
- `StringComparer::ordinalIgnoreCase()`: dobra de caixa Unicode simples, depois bytes (precisa de `ext-mbstring`). O .NET converte para maiúsculas com a cultura invariante; a igualdade coincide nos casos comuns, a ordem pode diferir em casos raros.
- `StringComparer::culture($locale, ignoreCase: false, ignoreAccents: false, numeric: false)`: a collation de um idioma, pela ICU (precisa de `ext-intl`, listada em `suggest`; sem ela a fábrica lança `LogicException`). Locale que a ICU não conhece lança `InvalidArgumentException`; `pt_XX` cai para `pt`.

`null` vem antes de todo texto e só é igual a `null`; um `Stringable` é comparado pelo seu texto; qualquer outro valor lança `InvalidArgumentException`. Com comparador, `min()`/`max()` continuam pulando `null`, e quem decide o que pode ser comparado é o comparador, não a biblioteca.

<!-- tested: 7-ordinal -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Comparison\StringComparer;

$names = ALinqCollection::from(['Zeca', 'Álvaro', 'ana', 'Bruno', 'Ética']);

// Sem comparador: o <=> do PHP, byte a byte (maiúsculas primeiro, letras acentuadas por último)
$names->orderBy(fn($n) => $n)->toArray(); // ['Bruno', 'Zeca', 'ana', 'Álvaro', 'Ética']

// ordinal(): bytes, sem a leitura numérica de strings numéricas que o <=> faz
ALinqCollection::from(['10', '9'])->orderBy(fn($s) => $s)->toArray(); // ['9', '10']
ALinqCollection::from(['10', '9'])->orderBy(fn($s) => $s, StringComparer::ordinal())->toArray(); // ['10', '9']

// ordinalIgnoreCase(): dobra de caixa Unicode; acentos continuam contando
ALinqCollection::from(['Ana', 'ANA', 'ana', 'Ána'])->distinct(null, StringComparer::ordinalIgnoreCase())->toArray(); // ['Ana', 'Ána']
StringComparer::ordinalIgnoreCase()->equals('ÉTICA', 'ética'); // true
```

Com `ext-intl`:

<!-- tested: 7-culture -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Comparison\StringComparer;

$people = ALinqCollection::from([
    ['name' => 'Zeca', 'team' => 'B'],
    ['name' => 'Álvaro', 'team' => 'A'],
    ['name' => 'ana', 'team' => 'B'],
    ['name' => 'Ética', 'team' => 'A'],
    ['name' => 'Bruno', 'team' => 'A'],
]);
$ptBr = StringComparer::culture('pt_BR', ignoreCase: true, ignoreAccents: true);

$people->orderBy(fn($p) => $p['name'], $ptBr)->column('name')->toArray(); // ['Álvaro', 'ana', 'Bruno', 'Ética', 'Zeca']
$sorted = $people
    ->orderBy(fn($p) => $p['team'])
    ->thenBy(fn($p) => $p['name'], $ptBr)
    ->column('name')
    ->toArray();
$sorted; // ['Álvaro', 'Bruno', 'Ética', 'ana', 'Zeca']
$ptBr->equals('Ética', 'ETICA'); // true
$people->minBy(fn($p) => $p['name'], $ptBr)['name']; // 'Álvaro'

// Agrupar sem acento: o hash é uma chave opaca (não é para exibir)
$people->countBy(fn($p) => $ptBr->hash(mb_substr($p['name'], 0, 1)))->count(); // 4

// numeric: sequências de dígitos comparam pelo valor
ALinqCollection::from(['item10', 'item2'])->orderBy(fn($s) => $s, StringComparer::culture('en_US', numeric: true))->toArray(); // ['item2', 'item10']
```

Desde a 2.4.0 os operadores de agrupamento, as operações de conjunto e as junções aceitam o próprio comparador, e um grupo mantém um nome legível (a primeira grafia vista) em vez de um hash opaco: veja [Agrupamento](grouping.md) e [Junções](joining.md).
