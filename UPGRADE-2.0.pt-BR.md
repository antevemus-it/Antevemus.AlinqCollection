# Atualizando do ALinq 1.x para o 2.0

<p align="center">
  <a href="UPGRADE-2.0.md">🇺🇸 English</a> &nbsp;|&nbsp; <strong>🇧🇷 Português (Brasil)</strong>
</p>

A versão 2.0.0 remove o que a 1.x anunciou como depreciado, e nada mais: o `extract()` (depreciado desde a 1.3.0). Ela também leva a resolução de propriedades para um pacote compartilhado, `antevemus/adeep-property-access`, mantendo `Helpers\ALinqPropertyAccess` como fachada depreciada até a 3.0. Não há dado nem formato de arquivo envolvido, então não há nada a converter antes da atualização. O [CHANGELOG](CHANGELOG.md) lista cada mudança; este guia é o procedimento.

## Checklist

1. Remova as chamadas a `extract()` ([seção 1](#1-extract)).
2. Opcionalmente, troque `ALinqPropertyAccess` pelo `PropertyAccess` do pacote ([seção 2](#2-acessor-de-propriedades)); a fachada continua funcionando até a 3.0.
3. Confira os casos de borda cuja resposta mudou ([seção 3](#3-respostas-que-mudaram)), se suas entidades têm getters com parâmetro obrigatório, getters não públicos atrás de `__call()` ou chaves de `ArrayObject`/`ArrayIterator` com `null`.
4. `composer require antevemus/alinq-collection:^2.0`. Ele traz uma dependência, do mesmo autor, sem dependências próprias: `antevemus/adeep-property-access`. O PHP 8.4 continua sendo o mínimo.

## 1. `extract()`

`ALinqCollection::extract()` e sua declaração em `IALinqUtility` saíram. Um método não consegue exportar variáveis para o escopo de quem o chama, então desde a 1.3.0 ele só emitia `E_USER_DEPRECATED` e devolvia a contagem de variáveis que teria extraído; chamá-lo agora é `Error: Call to undefined method`. Troque pelo que ele pretendia fazer, no seu próprio escopo:

```php
// 1.x (não exportava nada; devolvia a contagem)
$count = $collection->extract();

// 2.0
extract($collection->toArray());          // o extract() do PHP, no escopo de quem chama
$count = $collection->count();            // se só a contagem era usada
```

Uma classe sua que implemente `IALinqUtility` (ou `IALinqCollection`) não precisa mais declarar `extract()`; mantê-lo não faz mal.

## 2. Acessor de propriedades

Todo nome que o ALinq lê (o query builder, `whereIn()`/`whereNotIn()`/`whereBetween()` nas duas coleções, `createPropertySelector()`, `createPropertyComparer()`) é resolvido pelo `Antevemus\ADeepPropertyAccess\PropertyAccess`, o pacote compartilhado com o ASpecification 2.0: as duas bibliotecas leem o mesmo valor do mesmo candidato por construção. A ordem de resolução da 1.x não mudou.

| 1.x (`Antevemus\ALinq\Helpers\ALinqPropertyAccess`) | 2.0 (`Antevemus\ADeepPropertyAccess\PropertyAccess`) |
| :--- | :--- |
| `ALinqPropertyAccess::getValue($item, 'a.b')` | `PropertyAccess::getValue($item, 'a.b')` |
| `ALinqPropertyAccess::hasProperty($item, 'a.b')` | `PropertyAccess::hasProperty($item, 'a.b')` |
| `ALinqPropertyAccess::getPropertyAccessor('a.b')` | `PropertyAccess::accessor('a.b')` |
| `ALinqPropertyAccess::getNestedPropertyAccessor('a.b')` | `PropertyAccess::accessor('a.b')` |
| `ALinqPropertyAccess::createPropertyComparer('a', 'b')` | sem mudança: é do ALinq e não é depreciado |

- Os quatro métodos depreciados são uma fachada fina (`@deprecated 2.0.0`, removidos na 3.0.0) e não emitem aviso em tempo de execução, porque estão no caminho quente de toda consulta que nomeia um campo. Analisadores estáticos (PHPStan, Psalm, IDEs) os apontam.
- **`#[Antevemus\ALinq\Attributes\Specifiable]` fica** e continua sendo a marca a usar no código ALinq. `#[Antevemus\ASpecification\Attributes\Specifiable]` e `#[Antevemus\ADeepPropertyAccess\Attributes\Specifiable]` valem como a mesma marca (a última é nova para o ALinq).

## 3. Respostas que mudaram

Todas nas bordas; os casos vêm da bateria de contrato do pacote. A coluna do ALinq é o que a 1.5.1 respondia.

| Caso | ALinq 1.5.1 | ALinq 2.0.0 |
| :--- | :--- | :--- |
| Getter público com parâmetro obrigatório, `getName(string $x)`, lido como `name` | `getValue()` lançava `ArgumentCountError` (e também `where('name', ...)`, `whereIn('name', ...)`, o seletor), `hasProperty()` respondia `true` | não é getter: é pulado e o passo seguinte responde (uma propriedade pública `$name`, `isName()`...), senão `null`/`false`; parâmetro opcional ou variádico continua getter |
| Getter privado ou protegido numa classe com `__call()` | `__call('getName', [])` rodava e o resultado era lido; `hasProperty()` `true` | `__call()` nunca é chamado: `null`/`false` |
| Nome começado por `__` (`'__construct'`) | o método rodava (o construtor rodava de novo); `hasProperty()` `true` | nunca é chamado: `null`/`false` |
| Nome vazio `''` num objeto com `get()`, `is()` ou `has()` público | esse método rodava e o resultado era lido; `hasProperty()` `true` | nome vazio não nomeia método: `null`/`false` |
| `hasProperty()` de uma chave de `ArrayObject`/`ArrayIterator` com `null` (também no fim de um caminho) | `false` (`isset()`) | `true` (`offsetExists()`, como uma chave de array com `null`); chave ausente continua `false`; `getValue()` é `null` nos dois |
| Propriedade privada marcada com `#[Antevemus\ADeepPropertyAccess\Attributes\Specifiable]` | não reconhecida: `null`/`false` | lida, como as marcas do ALinq e do ASpecification |

Três respostas de borda que o ALinq já dava na 1.5.1 continuam como estavam (o ASpecification 1.x respondia diferente e mudou na 2.0 dele): nome vazio num objeto dá `null`; um objeto `ArrayAccess` responde só pelos offsets (suas propriedades públicas não são vistas); propriedade pública com `unset` e `__get()` sem `__isset()` dá `null`.

## 4. ASpecification

O `suggest` de `antevemus/aspecification` é `^2.0.0`. O ASpecification 2.0 resolve nomes de propriedade pelo mesmo pacote, então uma especificação avaliada pelo `ALinqBridge` e o mesmo nome lido pelo ALinq respondem igual. O ASpecification 2.0 aceita o ALinq `^1.5.1 || ^2.0`; a ponte dele não usa nem o `extract()` nem o `ALinqPropertyAccess`.
