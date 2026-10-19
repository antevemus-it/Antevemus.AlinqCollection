# Query Builder Dinâmico (`ALinqQueryBuilder`)

### 8. Query Builder Dinâmico (`ALinqQueryBuilder`)

Crie filtros dinâmicos sem escrever lógica booleana manual ou closures repetitivas:

```php
use Antevemus\ALinq\ALinqQueryBuilder;

// Constrói uma consulta dinâmica a partir de filtros de tela
$query = ALinqQueryBuilder::create('and')
    ->where('status', '=', 'APROVADO')
    ->where('scoreCredito', '>=', 650)
    ->where('idade', 'between', [21, 65]);

// Converte diretamente em um predicado chamável
$predicado = $query->toPredicate();

$candidatosAprovados = $candidatos->where($predicado);
```

Operadores (sem diferenciar caixa nem separador): `=`, `==`, `===`, `!=`, `<>`, `!==`, `>`, `>=`, `<`, `<=`, `in`, `notIn`, `between`, `notBetween`, `isNull`, `isNotNull`, `contains`, `startsWith`, `endsWith`. `between` recebe um par inclusivo `[min, max]`. O mesmo avaliador está exposto como `ALinqQueryBuilder::operatorPredicate($operador, $valor)` e sustenta o `createPredicate()`.

Atalhos (desde a 1.4.0): `whereIn($campo, $valores)`, `whereNotIn($campo, $valores)` e `whereBetween($campo, $min, $max)` são exatamente `where($campo, 'in' | 'notIn' | 'between', ...)`:

```php
$candidatos = ALinqCollection::from([
    ['nome' => 'Ana',   'status' => 'APROVADO',  'pais' => 'BR', 'idade' => 30],
    ['nome' => 'Bruno', 'status' => 'ANALISE',   'pais' => 'XX', 'idade' => 40],
    ['nome' => 'Carla', 'status' => 'REPROVADO', 'pais' => 'BR', 'idade' => 25],
    ['nome' => 'Davi',  'status' => 'APROVADO',  'pais' => 'PT', 'idade' => 70],
]);

$query = ALinqQueryBuilder::create('and')
    ->whereIn('status', ['APROVADO', 'ANALISE'])
    ->whereNotIn('pais', ['XX'])
    ->whereBetween('idade', 21, 65);

$candidatos->where($query->toPredicate())->column('nome')->toArray(); // ['Ana']
```

> [!NOTE]
> **Nota:** no builder, `in`/`notIn` comparam de forma **frouxa** (`'18'` está em `[18]`), estrita só quando o valor lido é `null`. Os métodos de mesmo nome das coleções (§2) comparam de forma **estrita**: são açúcar sobre o `where()` da coleção, não sobre o builder.
