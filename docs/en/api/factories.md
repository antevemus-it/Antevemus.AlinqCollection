# Factory & Instantiation Methods

### 1. Factory & Instantiation Methods

| Method | Signature | Description |
| :--- | :--- | :--- |
| `from()` | `static from(array $items): self` | Creates an ALinqCollection from a raw PHP array. |
| `range()` | `static range(int $start, int $end, int $step = 1): self` | Generates a collection containing an arithmetic progression. |
| `repeat()` | `static repeat(mixed $element, int $count): self` | Generates a collection that contains one repeated value. |
| `empty()` | `static empty(): self` | Returns an empty ALinqCollection instance. |
| `toArray()` | `toArray(): array` | Exports the collection back to a standard PHP array. |
| `toObject()` | `toObject(): stdClass` | Converts the items into a `stdClass` object. |

```php
$numbers = ALinqCollection::range(10, 50, 10); // [10, 20, 30, 40, 50]
$flags   = ALinqCollection::repeat('PENDING', 3); // ['PENDING', 'PENDING', 'PENDING']
```
