# Resolução Profunda de Propriedades (`PropertyAccess`)

### 9. Resolução Profunda de Propriedades (`PropertyAccess`)

Todo nome que o ALinq lê (em `where*()`, no query builder, em `createPropertySelector()`, em `createPropertyComparer()`) é resolvido pelo **[`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess)**, o acessor compartilhado com o Antevemus.ASpecification desde a 2.0.0: as duas bibliotecas leem o mesmo valor do mesmo candidato por construção. Ele vem instalado com o ALinq, e você pode chamá-lo direto. Consulte com segurança estruturas aninhadas sem se preocupar se as chaves são arrays associativos, propriedades públicas, métodos getters ou híbridos:

```php
use Antevemus\ADeepPropertyAccess\PropertyAccess;

$payload = [
    'usuario' => (object)[
        'perfil' => [
            'empresa' => (object)[
                'cnpj' => '12.345.678/0001-90'
            ]
        ]
    ]
];

// Resolve o caminho aninhado automaticamente:
$cnpj = PropertyAccess::getValue($payload, 'usuario.perfil.empresa.cnpj');
// Retorna: '12.345.678/0001-90'
```

Ordem de resolução de cada segmento, vence o primeiro que casar:

1. chave de array, ou offset de `ArrayAccess` (`hasProperty()` segue o `offsetExists()`: uma chave com `null` existe, como num array);
2. getter público (`getX()`, `x()`, `isX()`, `hasX()`) **sem parâmetro obrigatório** (método com parâmetro obrigatório não é getter e é pulado; `__call()` nunca é usado, e nome começado por `__` nunca é chamado);
3. `__get` guardado por `__isset`;
4. propriedade pública (tipada e não inicializada resolve para `null`);
5. como último recurso, uma **propriedade não pública** (privada ou protegida, estática também) declarada pela classe ou por um ancestral, lida por reflexão sem chamar nenhum método, **só quando o autor da classe a marcou com `#[Specifiable]`** (desde a 1.4.1): na própria propriedade ou na classe que a declara. A marca na classe vale só para as propriedades declaradas nela, não para as da classe-mãe nem das subclasses. Marcada e não inicializada, resolve para `null`; não marcada, resolve para `null` e `hasProperty()` responde `false`.

Senão `null`, nunca `Error`. Ponto é sempre caminho. `PropertyAccess::hasProperty($item, 'a.b')` diz se o caminho resolve e segue a mesma ordem; `PropertyAccess::accessor('a.b')` é a mesma leitura como `Closure`.

```php
use Antevemus\ADeepPropertyAccess\PropertyAccess;
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\Attributes\Specifiable;

final class NotaFiscal
{
    public function __construct(
        #[Specifiable] private string $numero,
        private float $total,
        private string $codigoAprovacao = 'X9-SEGREDO',
    ) {}
    public function getTotal(): float { return $this->total; }
}

$nota = new NotaFiscal('NF-001', 99.9);

PropertyAccess::getValue($nota, 'total');              // 99.9 (getter público)
PropertyAccess::getValue($nota, 'numero');             // 'NF-001' (privada, marcada com #[Specifiable]: último recurso)
PropertyAccess::hasProperty($nota, 'numero');          // true
PropertyAccess::getValue($nota, 'codigoAprovacao');    // null (privada, sem marca)
PropertyAccess::hasProperty($nota, 'codigoAprovacao'); // false
PropertyAccess::getValue($nota, 'inexistente');        // null
ALinqCollection::from([$nota])->whereBetween('codigoAprovacao', 'A', 'Z')->count(); // 0
```

Por que opt-in: a biblioteca só recebe um nome em texto (em `where*()`, nos seletores de `orderBy()`, em `select()`, no query builder, em `createPropertySelector()`, em `createPropertyComparer()`) e não distingue o código do autor da entidade de um nome vindo do request. Sem a marca, filtrar ou ordenar por um campo privado o revelaria: `whereBetween('password_hash', 'a', 'b')` responde, faixa após faixa, por onde o valor começa. A 1.4.0 lia toda propriedade não pública; a 1.4.1 lê só as marcadas. `#[Antevemus\ALinq\Attributes\Specifiable]` é a marca a usar no código ALinq; `#[Antevemus\ASpecification\Attributes\Specifiable]` e `#[Antevemus\ADeepPropertyAccess\Attributes\Specifiable]` valem como a mesma marca (reconhecidas pelo nome, sem dependência entre os pacotes). Getters públicos, `__get` guardado por `__isset` e propriedades públicas não precisam de marca.

**`ALinqPropertyAccess` (1.x) é fachada depreciada desde a 2.0.0**, removida na 3.0.0: `getValue()` e `hasProperty()` delegam aos métodos de mesmo nome do `PropertyAccess`, `getPropertyAccessor()` e `getNestedPropertyAccessor()` ao `PropertyAccess::accessor()`. Não emite aviso em tempo de execução. `ALinqPropertyAccess::createPropertyComparer($a, $b)` é do ALinq e fica. Veja o [UPGRADE-2.0.pt-BR.md](https://github.com/antevemus-it/Antevemus.AlinqCollection/blob/main/UPGRADE-2.0.pt-BR.md) para as respostas que mudaram nas bordas.
