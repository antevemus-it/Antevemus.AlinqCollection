# Agregações e Métricas (`AggregationOperations`)

### 4. Agregações e Métricas (`AggregationOperations`)

Utilizando a velocidade das funções nativas em C do PHP 8.4:

```php
$pedidos = ALinqCollection::from([
    ['codigo' => 'PED-1', 'valor' => 120.50, 'status' => 'CONCLUIDO'],
    ['codigo' => 'PED-2', 'valor' => 45.00,  'status' => 'PENDENTE'],
    ['codigo' => 'PED-3', 'valor' => 310.00, 'status' => 'CONCLUIDO'],
    ['codigo' => 'PED-4', 'valor' => 85.20,  'status' => 'ESTORNADO'],
]);

// Quantificadores
$temPendente = $pedidos->any(fn($p) => $p['status'] === 'PENDENTE'); // true (via array_any)
$todosConcluidos = $pedidos->all(fn($p) => $p['status'] === 'CONCLUIDO'); // false (via array_all)

// Métricas Aritméticas
$totalVendas = $pedidos->sum(fn($p) => $p['valor']);      // 560.70
$ticketMedio = $pedidos->average(fn($p) => $p['valor']);  // 140.175
$maiorPedido = $pedidos->maxBy(fn($p) => $p['valor']);    // PED-3
$menorPedido = $pedidos->minBy(fn($p) => $p['valor']);    // PED-2

// Contagem por Categoria
$contagemStatus = $pedidos->countBy(fn($p) => $p['status'])->toArray();
// ['CONCLUIDO' => 2, 'PENDENTE' => 1, 'ESTORNADO' => 1]

// Reduções Customizadas
$saldoTotal = $pedidos->aggregate(seed: 1000.0, func: fn($acc, $p) => $acc + $p['valor']);

// single(): exatamente um elemento, ou exatamente um que case (Single do LINQ), senão lança (desde a 1.4.0)
$estorno = $pedidos->single(fn($p) => $p['status'] === 'ESTORNADO')['codigo']; // 'PED-4'
$pedidos->single(fn($p) => $p['status'] === 'CANCELADO'); // UnderflowException: Sequence contains no matching element.
$pedidos->single(fn($p) => $p['status'] === 'CONCLUIDO'); // OverflowException: Sequence contains more than one matching element.
ALinqCollection::from([])->single();                      // UnderflowException: Sequence contains no elements.
$pedidos->single();                                       // OverflowException: Sequence contains more than one element.
$pedidos->singleOrDefault(null, fn($p) => $p['status'] === 'CANCELADO'); // null (a variante tolerante)

// minOrDefault() e família: a resposta do irmão, ou um fallback quando não há o que agregar (desde a 2.1.0)
$menor = $pedidos->minOrDefault(null, fn($p) => $p['valor']);                                        // 45.0
$cancelados = $pedidos->where(fn($p) => $p['status'] === 'CANCELADO')->maxOrDefault(0.0, fn($p) => $p['valor']); // 0.0 (max() lançaria)
ALinqCollection::from([])->averageOrDefault();                                                       // null
```

`single()` lança as mesmas classes de exceção, com as mesmas mensagens, na coleção eager e na lazy; `singleOrDefault()` devolve o default quando nada casa e continua lançando `OverflowException` com mais de um resultado.

`minOrDefault($default = null, $seletor)`, `maxOrDefault(...)`, `averageOrDefault(...)`, `minByOrDefault($default, $seletorDeChave)` e `maxByOrDefault(...)` (desde a 2.1.0, nas duas coleções) dão a mesma resposta de `min()`/`max()`/`average()`/`minBy()`/`maxBy()`, mas, quando não há o que agregar (vazio, ou só nulos onde o irmão pula nulos), devolvem `$default` em vez de lançar `UnderflowException`; todo outro erro lança como antes, com a mesma classe e mensagem. Leem a fonte uma vez só, então são seguros em fontes de passada única como `fromCursor()`, onde a guarda `any() ? min() : null` lê a fonte duas vezes (e falha num cursor). `$default` vem primeiro, como no `firstOrDefault()`.
