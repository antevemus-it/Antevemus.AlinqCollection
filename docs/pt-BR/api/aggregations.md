# Agregações e Métricas (`AggregationOperations`)

### 4. Agregações e Métricas (`AggregationOperations`)

Utilizando a velocidade das funções nativas em C do PHP 8.4:

```php
$orders = ALinqCollection::from([
    ['code' => 'ORD-1', 'amount' => 120.50, 'status' => 'COMPLETED'],
    ['code' => 'ORD-2', 'amount' => 45.00,  'status' => 'PENDING'],
    ['code' => 'ORD-3', 'amount' => 310.00, 'status' => 'COMPLETED'],
    ['code' => 'ORD-4', 'amount' => 85.20,  'status' => 'REFUNDED'],
]);

// Quantificadores
$hasPending = $orders->any(fn($o) => $o['status'] === 'PENDING'); // true (via array_any)
$allCompleted = $orders->all(fn($o) => $o['status'] === 'COMPLETED'); // false (via array_all)

// Métricas Aritméticas
$totalSales = $orders->sum(fn($o) => $o['amount']);      // 560.70
$avgTicket  = $orders->average(fn($o) => $o['amount']);  // 140.175
$biggestOrder = $orders->maxBy(fn($o) => $o['amount']); // ORD-3
$smallestOrder = $orders->minBy(fn($o) => $o['amount']); // ORD-2

// Contagem por Categoria
$statusCounts = $orders->countBy(fn($o) => $o['status'])->toArray();
// ['COMPLETED' => 2, 'PENDING' => 1, 'REFUNDED' => 1]

// Reduções Customizadas
$totalBalance = $orders->aggregate(seed: 1000.0, func: fn($acc, $o) => $acc + $o['amount']);

// single(): exatamente um elemento, ou exatamente um que case (Single do LINQ), senão lança (desde a 1.4.0)
$refund = $orders->single(fn($o) => $o['status'] === 'REFUNDED')['code']; // 'ORD-4'
$orders->single(fn($o) => $o['status'] === 'CANCELLED'); // UnderflowException: Sequence contains no matching element.
$orders->single(fn($o) => $o['status'] === 'COMPLETED'); // OverflowException: Sequence contains more than one matching element.
ALinqCollection::from([])->single();                     // UnderflowException: Sequence contains no elements.
$orders->single();                                       // OverflowException: Sequence contains more than one element.
$orders->singleOrDefault(null, fn($o) => $o['status'] === 'CANCELLED'); // null (a variante tolerante)

// minOrDefault() e família: a resposta do irmão, ou um fallback quando não há o que agregar (desde a 2.1.0)
$smallest = $orders->minOrDefault(null, fn($o) => $o['amount']);                                    // 45.0
$cancelled = $orders->where(fn($o) => $o['status'] === 'CANCELLED')->maxOrDefault(0.0, fn($o) => $o['amount']); // 0.0 (max() lançaria)
ALinqCollection::from([])->averageOrDefault();                                                       // null
```

`single()` lança as mesmas classes de exceção, com as mesmas mensagens, na coleção eager e na lazy; `singleOrDefault()` devolve o default quando nada casa e continua lançando `OverflowException` com mais de um resultado.

`minOrDefault($default = null, $seletor)`, `maxOrDefault(...)`, `averageOrDefault(...)`, `minByOrDefault($default, $seletorDeChave)` e `maxByOrDefault(...)` (desde a 2.1.0, nas duas coleções) dão a mesma resposta de `min()`/`max()`/`average()`/`minBy()`/`maxBy()`, mas, quando não há o que agregar (vazio, ou só nulos onde o irmão pula nulos), devolvem `$default` em vez de lançar `UnderflowException`; todo outro erro lança como antes, com a mesma classe e mensagem. Leem a fonte uma vez só, então são seguros em fontes de passada única como `fromCursor()`, onde a guarda `any() ? min() : null` lê a fonte duas vezes (e falha num cursor). `$default` vem primeiro, como no `firstOrDefault()`.
