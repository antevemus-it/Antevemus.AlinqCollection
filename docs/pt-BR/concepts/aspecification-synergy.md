# 🔮 Sinergia com Antevemus ASpecification

O `Antevemus.AlinqCollection` é o motor de coleções oficial do **[`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification)**. Quando ambos os pacotes estão instalados, regras de negócio encapsuladas no padrão **Specification** podem ser executadas diretamente em coleções ALinq sem necessidade de closures manuais.

### Como Funciona

A classe **`ALinqBridge`** e o visitor **`ALinqSpecificationVisitor`** do `Antevemus.ASpecification` compilam qualquer árvore de especificações (compostas por `And`, `Or`, `Not`, `GreaterThan`, `Regex`, etc.) em predicados funcionais de alta performance:

```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ASpecification\Spec;
use Antevemus\ASpecification\Linq\ALinqBridge;
use function Antevemus\ASpecification\DSL\is;
use function Antevemus\ASpecification\DSL\greaterThan;

// 1. Definir Regras de Negócio com o Padrão Specification (Evans & Fowler)
$clienteElegivel = Spec::allOf(
    Spec::property('status', is('ATIVO')),
    Spec::property('scoreCredito', greaterThan(700))
);

// 2. Consultar qualquer coleção ou repositório em memória via ALinqBridge
$clientes = ALinqCollection::from($dadosClientes);

// O ALinqBridge compila a AST da especificação em predicado LINQ nativo:
$clientesQualificados = ALinqBridge::filter($clientes, $clienteElegivel)
    ->orderByDescending(fn($c) => $c->scoreCredito)
    ->take(10)
    ->select(fn($c) => [
        'id'    => $c->id,
        'nome'  => $c->nome,
        'faixa' => 'PREMIUM',
    ])
    ->toArray();
```
