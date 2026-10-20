# Métodos de Fábrica e Instanciação

### 1. Métodos de Fábrica e Instanciação

| Método | Assinatura | Descrição |
| :--- | :--- | :--- |
| `from()` | `static from(array $items): self` | Cria uma ALinqCollection a partir de um array PHP nativo. |
| `range()` | `static range(int $start, int $end, int $step = 1): self` | Gera uma coleção contendo uma progressão aritmética. |
| `repeat()` | `static repeat(mixed $element, int $count): self` | Gera uma coleção repetindo um mesmo elemento N vezes. |
| `empty()` | `static empty(): self` | Retorna uma instância vazia de ALinqCollection. |
| `toArray()` | `toArray(): array` | Exporta os itens da coleção de volta para um array PHP. |
| `toObject()` | `toObject(): stdClass` | Converte os itens para um objeto `stdClass`. |

```php
$numbers = ALinqCollection::range(10, 50, 10); // [10, 20, 30, 40, 50]
$flags   = ALinqCollection::repeat('PENDING', 3); // ['PENDING', 'PENDING', 'PENDING']
```
