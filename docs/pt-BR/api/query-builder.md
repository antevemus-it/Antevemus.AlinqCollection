# Query Builder Dinâmico (`ALinqQueryBuilder`)

### 8. Query Builder Dinâmico (`ALinqQueryBuilder`)

Crie filtros dinâmicos sem escrever lógica booleana manual ou closures repetitivas:

```php
use Antevemus\ALinq\ALinqQueryBuilder;

// Constrói uma consulta dinâmica a partir de filtros de tela
$query = ALinqQueryBuilder::create('and')
    ->where('status', '=', 'APPROVED')
    ->where('creditScore', '>=', 650)
    ->where('age', 'between', [21, 65]);

// Converte diretamente em um predicado chamável
$predicate = $query->toPredicate();

$approvedApplicants = $applicants->where($predicate);
```

Operadores (sem diferenciar caixa nem separador): `=`, `==`, `===`, `!=`, `<>`, `!==`, `>`, `>=`, `<`, `<=`, `in`, `notIn`, `between`, `notBetween`, `isNull`, `isNotNull`, `contains`, `startsWith`, `endsWith`. `between` recebe um par inclusivo `[min, max]`. O mesmo avaliador está exposto como `ALinqQueryBuilder::operatorPredicate($operador, $valor)` e sustenta o `createPredicate()`.

Atalhos (desde a 1.4.0): `whereIn($campo, $valores)`, `whereNotIn($campo, $valores)` e `whereBetween($campo, $min, $max)` são exatamente `where($campo, 'in' | 'notIn' | 'between', ...)`:

```php
$applicants = ALinqCollection::from([
    ['name' => 'Ana',   'status' => 'APPROVED', 'country' => 'BR', 'age' => 30],
    ['name' => 'Bruno', 'status' => 'REVIEW',   'country' => 'XX', 'age' => 40],
    ['name' => 'Carla', 'status' => 'REJECTED', 'country' => 'BR', 'age' => 25],
    ['name' => 'Davi',  'status' => 'APPROVED', 'country' => 'PT', 'age' => 70],
]);

$query = ALinqQueryBuilder::create('and')
    ->whereIn('status', ['APPROVED', 'REVIEW'])
    ->whereNotIn('country', ['XX'])
    ->whereBetween('age', 21, 65);

$applicants->where($query->toPredicate())->column('name')->toArray(); // ['Ana']
```

> [!NOTE]
> **Nota:** no builder, `in`/`notIn` comparam de forma **frouxa** (`'18'` está em `[18]`), estrita só quando o valor lido é `null`. Os métodos de mesmo nome das coleções (§2) comparam de forma **estrita**: são açúcar sobre o `where()` da coleção, não sobre o builder.
