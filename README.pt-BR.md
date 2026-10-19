# Antevemus ALinq Collection

[![CI](https://github.com/antevemus-it/Antevemus.AlinqCollection/actions/workflows/tests.yml/badge.svg)](https://github.com/antevemus-it/Antevemus.AlinqCollection/actions/workflows/tests.yml)
[![Latest Stable Version](https://img.shields.io/badge/release-v2.2.0-blue.svg)](https://github.com/antevemus-it/Antevemus.AlinqCollection/releases)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests Passing](https://img.shields.io/badge/testes-731%20aprovados%20%7C%202968%20asserções-success.svg)](tests/)
[![Architecture](https://img.shields.io/badge/Arquitetura-LINQ%20%7C%20Coleções%20Funcionais-orange)](https://learn.microsoft.com/en-us/dotnet/csharp/linq/)
[![Synergy: ASpecification](https://img.shields.io/badge/Sinergia-Antevemus.ASpecification-purple)](https://github.com/antevemus-it/Antevemus.ASpecification)

> **Framework de Coleções Fluentes no Estilo LINQ para PHP 8.4+**  
> Um motor de manipulação de coleções expressivo, fluente e type-safe que traz a elegância do .NET Language Integrated Query (LINQ) para o PHP moderno. Impulsionado pelas funções nativas de array em C do PHP 8.4 (`array_any`, `array_all`, `array_find`, `array_find_key`), pipelines de streaming baseados em generators (`ALinqLazyCollection`), teoria dos conjuntos avançada, query builders dinâmicos de predicados, resolução profunda de propriedades por notação de ponto e integração nativa com o [`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification).

---

[🇺🇸 English](README.md) • [🇧🇷 Português (Brasil)](README.pt-BR.md)

---

## 🏛️ Origens & Filosofia

O desenvolvimento moderno em PHP frequentemente enfrenta códigos repetitivos e verbosos para manipulação de arrays: loops aninhados manuais, variáveis acumuladoras mutáveis e closures prolixas envolvendo funções nativas de array. Enquanto desenvolvedores .NET contam há mais de uma década com o **LINQ (Language Integrated Query)** como padrão declarativo de consulta em memória, os desenvolvedores PHP dependiam de bibliotecas monolíticas pesadas e lentas.

O **Antevemus ALinq Collection** foi projetado do zero para o **PHP 8.4+**:
1. **Primitivas Nativas em C**: Aproveita diretamente as novas funções nativas `array_any()`, `array_all()`, `array_find()` e `array_find_key()` do PHP 8.4 para máxima performance sem sobrecarga no userspace.
2. **Arquitetura Modular Decomposta em Traits**: Elimina monolitos. As operações são distribuídas em 8 traits funcionais especializados (Filtragem, Junções, Agregações, Projeções, Agrupamento, Ordenação, Iteradores e Utilitários).
3. **Pipelines de Streaming Baseados em Generators (O(1) de RAM)**: `ALinqLazyCollection` processa arquivos de log de múltiplos gigabytes, CSVs massivos e cursores PDO não bufferizados linha a linha, sem inflação de memória.
4. **Teoria dos Conjuntos Enterprise**: Operações completas de diagramas de Venn (`intersectBy`, `exceptBy`, `unionBy`) com seletores de chave e comparadores customizados.
5. **Resolução Profunda por Notação de Ponto**: Consulta estruturas de dados heterogêneas (arrays, objetos, DTOs e entidades de domínio) navegando caminhos aninhados como `user.profile.address.city`, resolvidos pelo acessor compartilhado com o ASpecification ([`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess), desde a 2.0.0).
6. **Sinergia Arquitetural**: Parceiro nativo do **[`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification)**, permitindo que árvores de especificação de Eric Evans e Martin Fowler compilem diretamente para pipelines LINQ em memória via `ALinqBridge` e `ALinqSpecificationVisitor`.

---

## 🌟 Resumo dos Recursos

| Domínio de Operação | Capacidades |
| :--- | :--- |
| **🌊 Streaming & Big Data** | `ALinqLazyCollection`, `fromFile`, `fromCsv`, `fromCursor`, `fromHttpPages` (API HTTP paginada com prefetch), `where`, `select`, `takeWhile`, `skipWhile`, `zip`, `tap`, `remember`, consumo constante de memória $O(1)$ nos operadores de streaming |
| **🔍 Filtragem & Fatiamento** | `where`, `take`, `skip`, `distinct`, `distinctBy`, `first`, `firstOrDefault`, `last`, `lastOrDefault`, `single`, `singleOrDefault`, `whereIn`, `whereNotIn`, `whereBetween`, `chunk`, `pad`, `shuffle`, `contains` |
| **🔗 Junções & Conjuntos** | `join` (inner), `leftJoin`, `rightJoin`, `fullJoin` (externos, como no .NET 10/11), `groupJoin` (hierárquico), `concat`, `intersect`, `intersectWith`, `intersectBy`, `except`, `exceptWith`, `exceptBy`, `unionBy`, `combine`, `replace`, `replaceRecursive` |
| **📊 Agregações & Métricas** | `any`, `all`, `sum`, `average`, `min`, `minBy`, `max`, `maxBy`, `product`, `countValues`, `aggregate`, `aggregateBy`, `countBy` |
| **🎯 Seleção & Projeção** | `select`, `selectMany` (achatamento de listas aninhadas), `column`, `toDictionary`, `toObject`, `flip`, `toArray` |
| **🗂️ Agrupamento & Partições** | `groupBy` (subcoleções tipadas com encadeamento fluente completo) |
| **⚡ Ordenação & Classificação** | `orderBy`, `orderByDescending`, `thenBy`, `thenByDescending`, `orderByNatural` (ordenação alfanumérica humana), `orderByCustom`, `orderByKey`, `reverse` |
| **🧭 Iteração & Navegação** | `each`, `eachRecursive`, `current`, `key`, `next`, `prev`, `reset`, `end`, paridade total com `IteratorAggregate` e `Countable` |
| **🛠️ Query Builder Dinâmico** | `ALinqQueryBuilder` (`create('and'\|'or')`, `where('campo', '>=', $val)`, `whereIn`, `whereNotIn`, `whereBetween`, `toPredicate()`) |
| **🔎 Acesso a Propriedades** | `PropertyAccess` do pacote compartilhado [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) com dot-notation (`getValue($item, 'empresa.endereco.cep')`), getters e, como último recurso, propriedades não públicas que o autor da classe marcou com `#[Specifiable]` |

---

## 📦 Instalação

Instale via Composer:

```bash
composer require antevemus/alinq-collection
```

Requisitos: PHP 8.4 ou superior, e uma dependência, instalada junto: [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (do mesmo autor, sem dependências próprias), o acessor de propriedades compartilhado com o Antevemus.ASpecification. Atualizando da 1.x: veja o [UPGRADE-2.0.pt-BR.md](UPGRADE-2.0.pt-BR.md) (`extract()` removido, `ALinqPropertyAccess` depreciado).

### Repositório Git VCS (Ambientes de Desenvolvimento ou Privados)

Para consumir diretamente via GitHub antes ou em paralelo ao Packagist:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/antevemus-it/Antevemus.AlinqCollection.git"
        }
    ],
    "require": {
        "antevemus/alinq-collection": "^2.0"
    }
}
```

---

## 🚀 Guia Rápido

```php
<?php

declare(strict_types=1);

use Antevemus\ALinq\ALinqCollection;

$users = ALinqCollection::from([
    ['id' => 1, 'name' => 'Alice', 'role' => 'admin', 'score' => 95, 'active' => true],
    ['id' => 2, 'name' => 'Bob', 'role' => 'editor', 'score' => 82, 'active' => true],
    ['id' => 3, 'name' => 'Charlie', 'role' => 'viewer', 'score' => 60, 'active' => false],
    ['id' => 4, 'name' => 'Diana', 'role' => 'admin', 'score' => 91, 'active' => true],
    ['id' => 5, 'name' => 'Evan', 'role' => 'editor', 'score' => 74, 'active' => true],
]);

// 1. Pipeline de Consulta Fluente
$topActiveAdmins = $users
    ->where(fn($u) => $u['active'] && $u['role'] === 'admin')
    ->orderByDescending(fn($u) => $u['score'])
    ->select(fn($u) => sprintf('%s (%d pts)', $u['name'], $u['score']))
    ->toArray();

// Resultado: ['Alice (95 pts)', 'Diana (91 pts)']

// 2. Agregações de Alta Performance (PHP 8.4 Nativo)
$hasSuperUser = $users->any(fn($u) => $u['score'] > 90);      // true (via array_any)
$allActive    = $users->all(fn($u) => $u['active']);          // false (via array_all)
$avgScore     = $users->average(fn($u) => $u['score']);       // 80.4
$bestUser     = $users->maxBy(fn($u) => $u['score']);         // ['id' => 1, 'name' => 'Alice'...]

// 3. Agrupamento & Estatísticas por Categoria
$roleStats = $users
    ->groupBy(fn($u) => $u['role'])
    ->select(fn(ALinqCollection $group, string $role) => [
        'role'        => $role,
        'count'       => $group->count(),
        'avg_score'   => $group->average(fn($u) => $u['score']),
        'top_performer' => $group->maxBy(fn($u) => $u['score'])['name'],
    ])
    ->toArray();
```

---

## 🧩 Visão Arquitetural

O `Antevemus.AlinqCollection` foi desenvolvido com foco em alta manutenibilidade, tipagem estrita e ausência de acoplamento circular. Em vez de um monolito de milhares de linhas, a classe principal delega as operações para traits especializados:

```text
Antevemus\ALinq\
├── ALinqCollection.php          # Fachada principal implementando IALinqCollection (Motor em Memória)
├── ALinqLazyCollection.php      # Pipeline de Streaming Baseado em Generators (Motor O(1) de RAM)
├── ALinqQueryBuilder.php        # Construtor fluente de condições (AND / OR) para consultas dinâmicas
├── Helpers\
│   └── ALinqPropertyAccess.php  # Fachada depreciada sobre o antevemus/adeep-property-access (até a 3.0) + createPropertyComparer()
├── Interfaces\                  # 11 Contratos Segregados
│   ├── IALinqBaseCollection.php
│   ├── IALinqCollection.php
│   ├── IALinqLazyCollection.php # Contrato de coleções de streaming (Generators, arquivos, cursores)
│   ├── IALinqFilterable.php
│   ├── IALinqJoinable.php
│   ├── IALinqAggregatable.php
│   ├── IALinqSelectable.php
│   ├── IALinqGroupable.php
│   ├── IALinqOrderable.php
│   ├── IALinqIterator.php
│   └── IALinqUtility.php
└── Traits\                      # 8 Implementações Especializadas de Traits
    ├── FilteringOperations.php
    ├── JoiningOperations.php
    ├── AggregationOperations.php
    ├── SelectionOperations.php
    ├── GroupingOperations.php
    ├── OrderingOperations.php
    ├── IteratorOperations.php
    └── UtilityOperations.php
```

Nomes de propriedade e caminhos com ponto são resolvidos pelo `Antevemus\ADeepPropertyAccess\PropertyAccess` ([`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess)), o acessor compartilhado com o ASpecification (veja a §9).

---

## 🔮 Sinergia com Antevemus ASpecification

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

---

## 📖 Referência Completa da API

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
$numeros = ALinqCollection::range(10, 50, 10); // [10, 20, 30, 40, 50]
$status  = ALinqCollection::repeat('PENDENTE', 3); // ['PENDENTE', 'PENDENTE', 'PENDENTE']
```

---

### 2. Filtragem e Fatiamento (`FilteringOperations`)

```php
$colecao = ALinqCollection::from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);

// where(): Filtra elementos pelo predicado; reindexa listas numéricas automaticamente
$pares = $colecao->where(fn($x) => $x % 2 === 0);

// take(n) & skip(n): Paginação e fatiamento
$pagina = $colecao->skip(4)->take(3); // [5, 6, 7]

// distinct() & distinctBy(): Deduplicação
$pessoas = ALinqCollection::from([
    ['id' => 1, 'depto' => 'TI'],
    ['id' => 2, 'depto' => 'RH'],
    ['id' => 3, 'depto' => 'TI'],
]);
$deptosUnicos = $pessoas->distinctBy(fn($p) => $p['depto']); // IDs 1, 2

// Extração de elementos
$primeiro = $colecao->first(fn($x) => $x > 5);        // 6
$primeiroOuNulo = $colecao->firstOrDefault(null, fn($x) => $x > 100); // null
$unico = $colecao->where(fn($x) => $x === 3)->singleOrDefault(); // 3

// chunk(tamanho) & pad(tamanho, valor)
$grupos = $colecao->chunk(3);                        // 4 coleções: [1, 2, 3], [4, 5, 6], [7, 8, 9], [10]
$somas = $grupos->select(fn($grupo) => $grupo->sum()); // [6, 15, 24, 10]
$preenchido = ALinqCollection::from([1, 2])->pad(5, 0); // [1, 2, 0, 0, 0]
```

#### Filtros por campo: `whereIn()`, `whereNotIn()`, `whereBetween()` (desde a 1.4.0)

Atalhos sobre o `where()` que leem um campo pelo `PropertyAccess` compartilhado (§9: chave de array, getter, propriedade, caminho com ponto como `'endereco.cidade'`). `whereIn`/`whereNotIn` comparam por identidade **estrita** (`in_array(..., true)`: `'18'` não é `18`); `whereBetween` é **inclusivo** e um valor `null` nunca está entre. As chaves seguem o contrato: lista sai reindexada, dicionário mantém as chaves.

```php
$equipe = ALinqCollection::from([
    ['nome' => 'Ana',   'depto' => 'TI',     'idade' => 17],
    ['nome' => 'Bruno', 'depto' => 'RH',     'idade' => 18],
    ['nome' => 'Carla', 'depto' => 'Vendas', 'idade' => 65],
    ['nome' => 'Davi',  'depto' => 'TI',     'idade' => null],
]);

$equipe->whereIn('depto', ['TI', 'RH'])->column('nome')->toArray(); // ['Ana', 'Bruno', 'Davi']
$equipe->whereNotIn('depto', ['TI'])->column('nome')->toArray();    // ['Bruno', 'Carla']
$equipe->whereBetween('idade', 18, 65)->column('nome')->toArray();  // ['Bruno', 'Carla'] (limites incluídos, null nunca está entre)
$equipe->whereIn('idade', ['18'])->count();                         // 0 (estrito: '18' não é 18)
```

---

### 3. Junções e Teoria dos Conjuntos (`JoiningOperations`)

Junções relacionais completas e operações matemáticas de conjuntos:

```php
$departamentos = ALinqCollection::from([
    ['id' => 10, 'nome' => 'Engenharia'],
    ['id' => 20, 'nome' => 'Marketing'],
]);

$funcionarios = ALinqCollection::from([
    ['nome' => 'Alex', 'depto_id' => 10],
    ['nome' => 'Beatriz', 'depto_id' => 10],
    ['nome' => 'Carlos', 'depto_id' => 20],
]);

// 1. Inner Join (Estilo SQL)
$relatorio = $funcionarios->join(
    inner: $departamentos->toArray(),
    outerKeySelector: fn($f) => $f['depto_id'],
    innerKeySelector: fn($d) => $d['id'],
    resultSelector: fn($f, $d) => [
        'funcionario'   => $f['nome'],
        'departamento' => $d['nome'],
    ]
)->toArray();

// 2. Group Join (Left Outer Join Hierárquico)
$equipes = $departamentos->groupJoin(
    inner: $funcionarios->toArray(),
    outerKeySelector: fn($d) => $d['id'],
    innerKeySelector: fn($f) => $f['depto_id'],
    resultSelector: fn($d, ALinqCollection $membros) => [
        'departamento' => $d['nome'],
        'membros'      => $membros->column('nome')->toArray(),
    ]
)->toArray();

// 3. Operações de Conjunto com Seletores de Chave
$conjuntoA = ALinqCollection::from([['id' => 1], ['id' => 2], ['id' => 3]]);
$conjuntoB = [['id' => 2], ['id' => 3], ['id' => 4]];

$intersecao = $conjuntoA->intersectBy($conjuntoB, fn($item) => $item['id']); // [id: 2, id: 3]
$diferenca  = $conjuntoA->exceptBy($conjuntoB, fn($item) => $item['id']);    // [id: 1]
$uniao      = $conjuntoA->unionBy($conjuntoB, fn($item) => $item['id']);     // [id: 1, id: 2, id: 3, id: 4]
```

#### Junções externas: `leftJoin()`, `rightJoin()`, `fullJoin()` (desde a 1.4.0)

As junções externas do LINQ do .NET 10/11 (`Enumerable.LeftJoin`, `RightJoin`, `FullJoin`), com a assinatura `leftJoin(iterable $inner, callable $outerKeySelector, callable $innerKeySelector, ?callable $resultSelector = null)` (idem `rightJoin`/`fullJoin`). Substituem o `groupJoin()` + `selectMany()` escrito à mão. O seletor de resultado recebe `($outer, $inner)` com `null` no lado sem correspondência; sem seletor, cada resultado é o par `[$outer, $inner]`. O resultado é sempre lista. As chaves casam pela identidade estrita de `join()`/`groupJoin()`, e chave `null` nunca casa: esse item sai sem par.

- `leftJoin()`: todo item do outer, na ordem do outer (uma vez por correspondência, ou uma vez com `null`).
- `rightJoin()`: todo item do inner, na ordem do inner (como no .NET), cada um com os outers correspondentes na ordem do outer, ou com `null`.
- `fullJoin()`: os pares e os outers sem par na ordem do outer, depois os inners sem par na ordem do inner.

```php
$pedidos = ALinqCollection::from([
    ['id' => 1, 'cliente' => 10],
    ['id' => 2, 'cliente' => null], // chave null nunca casa
    ['id' => 3, 'cliente' => 99],   // cliente inexistente
    ['id' => 4, 'cliente' => 10],
]);
$clientes = [
    ['id' => 10, 'nome' => 'Acme'],
    ['id' => 20, 'nome' => 'Globex'], // sem pedidos
];

$chavePedido  = fn($p) => $p['cliente'];
$chaveCliente = fn($c) => $c['id'];
$rotulo       = fn(?array $p, ?array $c) => ($p['id'] ?? '-') . ':' . ($c['nome'] ?? '-');

$pedidos->leftJoin($clientes, $chavePedido, $chaveCliente, $rotulo)->toArray();
// ['1:Acme', '2:-', '3:-', '4:Acme']
$pedidos->rightJoin($clientes, $chavePedido, $chaveCliente, $rotulo)->toArray();
// ['1:Acme', '4:Acme', '-:Globex']
$pedidos->fullJoin($clientes, $chavePedido, $chaveCliente, $rotulo)->toArray();
// ['1:Acme', '2:-', '3:-', '4:Acme', '-:Globex']

// Sem seletor de resultado: pares [$outer, $inner]
[$pedido, $cliente] = $pedidos->leftJoin($clientes, $chavePedido, $chaveCliente)->toArray()[1];
// $pedido = ['id' => 2, 'cliente' => null], $cliente = null
```

#### Chaves em vez de elementos: `exceptByKeys()`, `intersectByKeys()`; qualquer iterable no `join()` (desde a 2.2.0)

`exceptByKeys(iterable $keys, callable $keySelector)` e `intersectByKeys(...)` são o `ExceptBy`/`IntersectBy` do .NET 6: o segundo argumento é uma sequência de **chaves** (array, gerador, `ALinqCollection`, `ALinqLazyCollection`), lida uma vez; o seletor roda só sobre esta coleção. São operações de conjunto: cada chave sai uma vez (a primeira ocorrência), chaves comparam por identidade estrita (`'2'` não é `2`), lista é reindexada e dicionário mantém as chaves, e chave `null` casa com um `null` em `$keys` (regra de conjunto; em junção `null` nunca casa).

> **Divergência do .NET em `exceptBy()`/`intersectBy()`.** Os `exceptBy(array $second, callable $keySelector)` e `intersectBy(...)` existentes recebem uma sequência de **elementos** do mesmo formato e aplicam o seletor aos **dois** lados (a forma do MoreLINQ, anterior ao .NET 6). Eles não mudam. Chamados do jeito do .NET (`exceptBy([1, 2], fn($r) => $r['id'])`), leem um índice de um inteiro, emitem aviso e respondem errado: use `exceptByKeys()`.

`join()` e `groupJoin()` aceitam qualquer iterable como lado inner desde a 2.2.0 (era `array`): ele é indexado numa leitura só, então gerador ou cursor lazy funcionam, com as mesmas respostas da forma com array.

<!-- tested: 3-keys -->
```php
use Antevemus\ALinq\ALinqCollection;

$documents = ALinqCollection::from([
    ['id' => 1, 'type' => 'ID card'],
    ['id' => 2, 'type' => 'ID card'],
    ['id' => 3, 'type' => 'Proof of address'],
]);
$links = ALinqCollection::from([['document_id' => 2, 'participant_id' => 10]]);

// exceptByKeys(): o segundo argumento é uma sequência de CHAVES (o ExceptBy do .NET 6), identidade estrita
$unlinked = $documents->exceptByKeys($links->select(fn($l) => $l['document_id']), fn($d) => $d['id'])->column('id')->toArray();
$unlinked; // [1, 3]
$documents->intersectByKeys([2, 3], fn($d) => $d['id'])->column('id')->toArray(); // [2, 3]
$documents->exceptByKeys(['2'], fn($d) => $d['id'])->count(); // 3

// exceptBy(): o segundo argumento é uma sequência de ELEMENTOS, o seletor vale para os dois lados
$documents->exceptBy([['id' => 2]], fn($d) => $d['id'])->column('id')->toArray(); // [1, 3]

// join()/groupJoin(): qualquer iterable como lado inner, lido uma vez (gerador, cursor lazy, coleção)
$participants = (function () {
    yield ['id' => 10, 'document_id' => 2, 'name' => 'Ana'];
})();
$documents->join($participants, fn($d) => $d['id'], fn($p) => $p['document_id'], fn($d, $p) => $p['name'] . ': ' . $d['type'])->toArray(); // ['Ana: ID card']
$documents->groupJoin(ALinqCollection::from([]), fn($d) => $d['id'], fn($p) => $p['document_id'], fn($d, $ps) => $ps->count())->toArray(); // [0, 0, 0]
```

---

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

---

### 5. Projeções e Transformações (`SelectionOperations`)

```php
$catalogo = ALinqCollection::from([
    ['sku' => 'A1', 'tags' => ['eletronicos', 'celular'], 'preco' => 799],
    ['sku' => 'A2', 'tags' => ['celular', 'acessorios'], 'preco' => 29],
]);

// select(): Mapeamento 1 para 1
$listaSku = $catalogo->select(fn($item) => $item['sku'])->toArray(); // ['A1', 'A2']

// selectMany(): Achatamento de coleções aninhadas 1 para N
$todasTags = $catalogo
    ->selectMany(fn($item) => $item['tags'])
    ->distinct()
    ->toArray(); // ['eletronicos', 'celular', 'acessorios']

// toDictionary(): Transformação em mapa chave-valor associativo
$tabelaPrecos = $catalogo->toDictionary(
    keySelector: fn($item) => $item['sku'],
    elementSelector: fn($item) => $item['preco']
); // ['A1' => 799, 'A2' => 29]
```

#### Chaves repetidas e lookups: `toDictionary(onDuplicate:)`, `toLookup()` (desde a 2.2.0)

`toDictionary($keySelector, $elementSelector = null, DuplicateKeyPolicy $onDuplicate = DuplicateKeyPolicy::Throw)`: `Throw` é o comportamento de todas as versões anteriores (a mesma mensagem); `KeepFirst` mantém o primeiro elemento e não roda o seletor de elemento para os repetidos; `KeepLast` mantém o último, na posição da primeira ocorrência, como `$mapa[$linha->id] = $linha`. O seletor de chave roda para todos os itens e toda chave é validada em todas as políticas. `toLookup($keySelector, $elementSelector = null)` devolve um `ALinqLookup` imutável (§6): `get()` de chave ausente é coleção vazia, nunca índice indefinido. Os dois existem também na coleção lazy, como operações terminais que leem o fluxo uma vez.

<!-- tested: 5-dictionary -->
```php
use Antevemus\ALinq\ALinqCollection;
use Antevemus\ALinq\DuplicateKeyPolicy;

$quotes = ALinqCollection::from([
    ['id' => 7, 'unit' => 'North', 'status' => 'draft'],
    ['id' => 8, 'unit' => 'South', 'status' => 'sent'],
    ['id' => 7, 'unit' => 'North', 'status' => 'approved'], // uma versão posterior da cotação 7
]);

// toDictionary(): chave repetida lança por padrão; uma política pode manter a primeira ou a última
$quotes->toDictionary(fn($q) => $q['id'], fn($q) => $q['status']); // throws InvalidArgumentException: toDictionary() key 7 produced twice (second time for item at key 2)
$quotes->toDictionary(fn($q) => $q['id'], fn($q) => $q['status'], DuplicateKeyPolicy::KeepFirst); // [7 => 'draft', 8 => 'sent']
$quotes->toDictionary(fn($q) => $q['id'], fn($q) => $q['status'], DuplicateKeyPolicy::KeepLast); // [7 => 'approved', 8 => 'sent']

// toLookup(): uma chave, vários elementos; chave ausente é coleção vazia
$byUnit = $quotes->toLookup(fn($q) => $q['unit'], fn($q) => $q['id']);
$byUnit->get('North')->toArray(); // [7, 7]
$byUnit['South']->toArray(); // [8]
$byUnit->get('East')->count(); // 0
$byUnit->keys(); // ['North', 'South']
```

---

### 6. Agrupamento e Particionamento (`GroupingOperations`)

O método `groupBy()` retorna uma coleção contendo instâncias de `ALinqCollection`, permitindo pipelines fluentes aninhados:

```php
$transacoes = ALinqCollection::from([
    ['categoria' => 'Alimentacao', 'valor' => 25.50],
    ['categoria' => 'Transporte',  'valor' => 15.00],
    ['categoria' => 'Alimentacao', 'valor' => 64.00],
    ['categoria' => 'Transporte',  'valor' => 30.00],
    ['categoria' => 'Lazer',       'valor' => 120.00],
]);

$resumo = $transacoes
    ->groupBy(fn($t) => $t['categoria'])
    ->select(fn(ALinqCollection $grupo, string $categoria) => [
        'categoria'          => $categoria,
        'total'              => $grupo->sum(fn($t) => $t['valor']),
        'quantidade_itens'   => $grupo->count(),
        'gasto_medio'        => round($grupo->average(fn($t) => $t['valor']), 2),
    ])
    ->orderByDescending(fn($linha) => $linha['total'])
    ->toArray();
```

#### `ALinqLookup`: um `groupBy()` materializado (desde a 2.2.0)

`toLookup()` devolve um `ALinqLookup` (o `ILookup<TKey, TElement>` do .NET): chaves na ordem da primeira aparição, os elementos de cada chave na ordem da fonte, `get($key)` (uma `ALinqCollection`, vazia para chave ausente), `contains($key)`, `keys()`, `count()` (número de chaves), iteração como `chave => ALinqCollection` (o formato do `groupBy()`), `toArray()` e `json_encode()`. `ArrayAccess` só de leitura: `$lookup['x']` é `get('x')`, `isset()` é `contains()`, e escrever lança. A chave segue o `groupBy()`: `int`, `string` ou `BackedEnum` (o valor); `null` lança, diferente do .NET, para que um lookup responda como o `groupBy()`.

<!-- tested: 6-lookup -->
```php
use Antevemus\ALinq\ALinqCollection;

$lookup = ALinqCollection::from([3, 1, 4, 1, 5, 9, 2, 6])->toLookup(fn($n) => $n % 3);
$lookup->toArray(); // [0 => [3, 9, 6], 1 => [1, 4, 1], 2 => [5, 2]]
count($lookup); // 3
$lookup->contains(1); // true
$lookup[7]->toArray(); // []
json_encode($lookup); // '[[3,9,6],[1,4,1],[5,2]]'
$lookup[7] = ALinqCollection::from([]); // throws LogicException: ALinqLookup is immutable.

// Chaves de grupo são chaves de array do PHP: 1 e '1' são uma chave só, '01' é outra
ALinqCollection::from([['k' => 1], ['k' => '1'], ['k' => '01']])->toLookup(fn($r) => $r['k'])->keys(); // [1, '01']
```

---

### 7. Ordenação e Classificação (`OrderingOperations`)

```php
$arquivos = ALinqCollection::from([
    'arquivo10.txt', 'arquivo2.txt', 'arquivo1.txt', 'ARQUIVO100.txt'
]);

// Ordenação Alfanumérica Natural (ordenação humana: arquivo1, arquivo2, arquivo10, ARQUIVO100)
$ordenadosNaturalmente = $arquivos->orderByNatural(caseSensitive: false)->toArray();

$colaboradores = ALinqCollection::from([
    ['nome' => 'Ana',   'depto' => 'Vendas', 'salario' => 5000],
    ['nome' => 'Bruno', 'depto' => 'TI',     'salario' => 7000],
    ['nome' => 'Carla', 'depto' => 'TI',     'salario' => 9000],
    ['nome' => 'Davi',  'depto' => 'TI',     'salario' => 7000],
]);

// Ordenação composta (desde a 1.4.0): orderBy() seguido de quantos thenBy()/thenByDescending() quiser
$ordenados = $colaboradores
    ->orderBy(fn($c) => $c['depto'])
    ->thenByDescending(fn($c) => $c['salario'])
    ->column('nome')
    ->toArray();
// ['Carla', 'Bruno', 'Davi', 'Ana']: TI 9000, TI 7000, TI 7000, Vendas 5000 (Bruno antes de Davi: estável)

// Um comparador recebe as duas chaves: fn($chaveA, $chaveB): int
$porTamanhoDoNome = $colaboradores
    ->orderBy(fn($c) => $c['depto'])
    ->thenBy(fn($c) => $c['nome'], fn($a, $b) => strlen($a) <=> strlen($b))
    ->column('nome')
    ->toArray();
// ['Davi', 'Bruno', 'Carla', 'Ana']

// thenBy() só refina uma coleção que vem direto de orderBy*()/thenBy*()
$colaboradores->thenBy(fn($c) => $c['nome']);
// LogicException: thenBy() requires a preceding orderBy().
$colaboradores->orderBy(fn($c) => $c['depto'])->where(fn($c) => true)->thenByDescending(fn($c) => $c['salario']);
// LogicException: thenByDescending() requires a preceding orderBy().

// orderByCustom() continua ordenando com uma única comparação sobre os itens inteiros
$mesmaOrdem = $colaboradores
    ->orderByCustom(fn($a, $b) => [$a['depto'], -$a['salario']] <=> [$b['depto'], -$b['salario']])
    ->column('nome')
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

---

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

> **Nota:** no builder, `in`/`notIn` comparam de forma **frouxa** (`'18'` está em `[18]`), estrita só quando o valor lido é `null`. Os métodos de mesmo nome das coleções (§2) comparam de forma **estrita**: são açúcar sobre o `where()` da coleção, não sobre o builder.

---

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

**`ALinqPropertyAccess` (1.x) é fachada depreciada desde a 2.0.0**, removida na 3.0.0: `getValue()` e `hasProperty()` delegam aos métodos de mesmo nome do `PropertyAccess`, `getPropertyAccessor()` e `getNestedPropertyAccessor()` ao `PropertyAccess::accessor()`. Não emite aviso em tempo de execução. `ALinqPropertyAccess::createPropertyComparer($a, $b)` é do ALinq e fica. Veja o [UPGRADE-2.0.pt-BR.md](UPGRADE-2.0.pt-BR.md) para as respostas que mudaram nas bordas.

---

### 10. Pipelines de Streaming & Big Data (`ALinqLazyCollection`)

Inspirado nos reactive streams do Node.js e no modelo de execução postergada `IEnumerable<T>` do .NET LINQ, o `ALinqLazyCollection` oferece processamento de alto rendimento e consumo estritamente controlado de memória utilizando Generators do PHP 8.4.

Processar conjuntos de dados de escala em gigabytes (como logs de acesso, CSVs de transações financeiras ou dumps de banco de dados) com arrays comuns em memória resulta em estouro fatal do limite de memória (`Allowed memory size exhausted`). O `ALinqLazyCollection` garante **consumo de memória constante $O(1)$ (< 100 KB de RAM)** independentemente do volume total de dados, avaliando os elementos sob demanda sob o padrão *pull-based*.

#### Garantias Arquiteturais:
- **Navegação Multi-Pass Rebobinável (Rewindable)**: Ao contrário dos generators padrão do PHP que disparam `Cannot traverse an already closed generator` em uma segunda iteração, o `ALinqLazyCollection` encapsula fábricas de geradores (`Closure(): iterable`). Você pode iterar, filtrar e materializar o mesmo pipeline múltiplas vezes com total segurança.
- **Descarte Determinístico de Recursos**: Fontes como `fromFile()` e `fromCsv()` garantem o fechamento do ponteiro do arquivo no bloco `finally`, mesmo se a iteração for interrompida precocemente (ex: curto-circuito com `first()`, `take(n)`); `fromHttpPages()` cancela do mesmo jeito a requisição em andamento.
- **Integração Bidirecional com a Coleção em Memória**: Alterne com naturalidade entre coleções imediatas (`ALinqCollection`) e pipelines de streaming (`ALinqLazyCollection`) através de `$collection->lazy()` e `$lazy->toCollection()`.

#### Exemplo Prático: Streaming de Arquivo de Log com Múltiplos Gigabytes

```php
use Antevemus\ALinq\ALinqLazyCollection;

// Processa um log de produção de 10 GB mantendo menos de 100 KB de RAM
$alertasCriticos = ALinqLazyCollection::fromFile('/var/log/app/production.log')
    ->where(fn(string $linha) => str_contains($linha, '[CRITICAL]'))
    ->select(function (string $linha) {
        preg_match('/\[(?<data>[^\]]+)\] \[CRITICAL\] (?<msg>.*)/', $linha, $matches);
        return [
            'data'     => $matches['data'] ?? 'desconhecida',
            'mensagem' => $matches['msg'] ?? trim($linha),
        ];
    })
    ->take(50) // Interrompe imediatamente: para de ler o disco assim que encontrar os primeiros 50 registros!
    ->toArray();
```

#### Processamento de Arquivos CSV com Cabeçalhos Dinâmicos

```php
// Mapeia automaticamente o cabeçalho para arrays associativos em cada linha
$clientesCorporativosAtivos = ALinqLazyCollection::fromCsv('/var/data/clientes.csv')
    ->where(fn(array $linha) => $linha['plano'] === 'Enterprise' && $linha['status'] === 'ativo')
    ->select(fn(array $linha) => [
        'clienteId' => (int)$linha['id'],
        'email'     => strtolower(trim($linha['email'])),
        'mrr'       => (float)$linha['mrr'],
    ])
    ->distinctBy(fn($cliente) => $cliente['email'])
    ->tap(fn($cliente) => syslog(LOG_INFO, "Processando cliente {$cliente['clienteId']}"))
    ->toCollection(); // Converte o resultado filtrado para ALinqCollection e prossegue com agrupamentos em memória
```

#### Streaming de Cursor de Banco de Dados (PDO Não Bufferizado)

```php
// Faz o streaming de milhões de registros diretamente de uma consulta PDO não bufferizada
$stmt = $pdo->query('SELECT id, nome, salario, departamento FROM funcionarios');

$maioresSalariosPorDept = ALinqLazyCollection::fromCursor($stmt)
    ->where(fn($linha) => (float)$linha['salario'] > 50000.0)
    ->take(100)
    ->toCollection()
    ->groupBy(fn($linha) => $linha['departamento']);
```

#### API HTTP Paginada com Prefetch (desde a 1.5.0)

`fromHttpPages()` percorre uma API HTTP paginada como uma única sequência lazy de itens. Assim que uma página é interpretada, a requisição da próxima é iniciada com `curl_multi`, e só então os itens da página atual são entregues: enquanto o seu código processa a página *n*, a página *n+1* já está sendo baixada. Sem event loop e sem Fibers, em PHP-FPM comum: o efeito do `IAsyncEnumerable<T>` com prefetch. Requer `ext-curl` (listada em `suggest`).

```php
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Http\HttpPageOptions;
use Antevemus\ALinq\Http\HttpPageRequest;
use Antevemus\ALinq\Http\HttpPageResponse;

$api = 'https://api.example.com/orders';
$token = getenv('API_TOKEN');

$paidTotal = ALinqLazyCollection::fromHttpPages(
    // A requisição de cada página; a primeira não tem cursor
    fn(?string $cursor) => HttpPageRequest::get(
        $api . '?limit=100' . ($cursor === null ? '' : '&after=' . urlencode($cursor))
    ),
    // Os itens da página e o cursor da próxima (null na última página)
    function (HttpPageResponse $response): array {
        $page = $response->json();
        return [$page['data'], $page['next']];
    },
    new HttpPageOptions(timeout: 10.0, headers: ['Authorization' => 'Bearer ' . $token]),
)
    ->where(fn(array $order) => $order['status'] === 'paid')
    ->sum(fn(array $order) => $order['total']); // a página n+1 baixa enquanto a página n é somada
```

- **Os dois callbacks.** `$requestFor($cursor, $pageIndex)` monta a requisição de uma página: o cursor da primeira é `null`, `$pageIndex` conta a partir de 0, e devolver `null` encerra o stream. `$parsePage($response)` devolve `[itens, próximoCursor]`, e um cursor `null` marca a última página. Cursor no corpo, número de página (`$cursor ?? 1`, próxima `$page + 1` enquanto `page < total_pages`) e o cabeçalho `Link` (`[$response->json(), $response->link('next')]`, alvos relativos resolvidos) cabem todos nesse formato. `HttpPageRequest` e `HttpPageResponse` são value objects simples (sem PSR-7): método, URL http(s), cabeçalhos, corpo e tempo-limite; status, cabeçalhos (`header()`), corpo (`json()`), URL efetiva e índice da página.
- **Erros.** Status fora de 2xx, erro de transporte (TLS inclusive), tempo-limite ou limite de páginas lançam `HttpPageException` no ponto em que aquela página seria entregue: os itens das páginas anteriores já foram entregues. A mensagem traz o status e a URL sem a query string (`HTTP 500 for page index 1: GET https://api.example.com/orders`), nunca o corpo. Não há retry no núcleo.
- **Parar cedo** (`take()`, `first()`, `any()`, uma exceção no seu laço) cancela a requisição em andamento: no máximo uma página é baixada à toa.
- **Cada percurso refaz as requisições desde a primeira página** (`count()` seguido de `toArray()` percorre a API duas vezes). Chame `remember()` para percorrer uma vez e reler da memória.
- **`HttpPageOptions`**: `timeout` (30 s por padrão: o máximo que a iteração fica bloqueada esperando uma página; o tempo que o seu código gasta na página anterior não conta), `maxPages` (10 000 por padrão, proteção contra um cursor que nunca termina), `headers` (enviados em toda requisição; um cabeçalho de mesmo nome na requisição prevalece) e `followRedirects` (`false` por padrão). A verificação TLS está sempre ligada e não pode ser desligada.
- **Prefetch de profundidade 1**: a próxima requisição depende do cursor da resposta anterior. No servidor local dos testes, 3 páginas de 300 ms com um consumidor que gasta 300 ms por página levam 1,2 s em vez de 1,8 s (`tests/Unit/Http/FromHttpPagesTest.php`).

#### Cache de Streams Intermediários com `remember()`

Se a fonte upstream for computacionalmente pesada ou envolver I/O de rede, o método `remember()` memoriza os itens avaliados na primeira passagem para que iterações subsequentes leiam do cache local sem reexecutar os generators de origem:

```php
$streamComCache = ALinqLazyCollection::from(function () {
    // Generator pesado ou chamada de API externa
    yield from buscarMetricasRemotas();
})->remember();

$totalCount = $streamComCache->count(); // O generator roda uma única vez
$media      = $streamComCache->average(); // Lê do cache local em memória, sem reexecutar o generator
```

#### Ordenação e junções externas acumulam dados (desde a 1.4.0)

A coleção lazy tem `orderBy()`/`orderByDescending()` com `thenBy()`/`thenByDescending()`, e as três junções externas, com as mesmas respostas do lado eager. Continuam postergadas (nada roda até a primeira travessia), mas **não** têm memória constante:

- `orderBy*()`/`thenBy*()` acumulam todo o upstream ao serem percorridos e ordenam uma vez com todos os critérios;
- `leftJoin()`/`fullJoin()` acumulam e indexam o lado inner e fazem streaming da coleção lazy;
- `rightJoin()` acumula e indexa a própria coleção lazy e faz streaming do inner (o resultado segue a ordem do inner).

```php
$ordenados = ALinqLazyCollection::range(1, 6)
    ->orderBy(fn($n) => $n % 3)          // postergado: nada roda ainda
    ->thenByDescending(fn($n) => $n)
    ->toArray();                          // [6, 3, 4, 1, 5, 2]
```

#### Agrupamentos e dicionários acumulam dados (desde a 2.2.0)

A coleção lazy tem `groupBy()`, `countBy()` e `aggregateBy()` como **estágios postergados**, como o `orderBy()`: nada roda até a primeira travessia, e cada travessia lê o upstream uma vez. Não são iguais em memória:

- `groupBy()` guarda todos os itens (O(n)) e entrega `chave => ALinqCollection`;
- `countBy()` guarda **um inteiro por chave** e `aggregateBy()` um acumulador por chave (O(chaves)): `fromCursor($stmt)->countBy(...)` conta milhões de linhas sem guardá-las;
- `toDictionary()` e `toLookup()` são terminais: uma passada (`toLookup()` guarda todos os elementos);
- `exceptByKeys()`/`intersectByKeys()` leem as chaves primeiro e depois fazem streaming, lembrando as chaves já emitidas;
- `implode()` faz streaming (a memória é o texto que ele monta).

Os estágios releem o upstream a cada travessia, como o `orderBy()`: sobre `fromCursor()` a segunda travessia lança; chame `remember()` antes. O resultado deles é um dicionário indexado pela chave do grupo, e os operadores seguintes mantêm essas chaves (um `groupBy()` eager cujas chaves por acaso são `0, 1, 2…` é uma lista, que um `where()` seguinte reindexa). A `$position` que o `aggregateBy()` entrega a `$func` é a posição do item **dentro do seu grupo** (0, 1, …), nas duas coleções, como o `aggregateBy()` eager sempre fez; todo grupo parte da mesma `$seed`.

<!-- tested: 10-grouping -->
```php
use Antevemus\ALinq\ALinqLazyCollection;

$orders = ALinqLazyCollection::from([
    ['id' => 1, 'status' => 'paid', 'total' => 10],
    ['id' => 2, 'status' => 'open', 'total' => 25],
    ['id' => 3, 'status' => 'paid', 'total' => 5],
]);

$counts = $orders->countBy(fn($o) => $o['status']); // postergado: nada roda ainda
$counts->toArray(); // ['paid' => 2, 'open' => 1]
$orders->aggregateBy(fn($o) => $o['status'], 0, fn($sum, $o) => $sum + $o['total'])->toArray(); // ['paid' => 15, 'open' => 25]
$orders->aggregateBy(fn($o) => $o['status'], '', fn($trace, $o, $position) => $trace . $position)->toArray(); // ['paid' => '01', 'open' => '0']
$orders->groupBy(fn($o) => $o['status'])->select(fn($group) => $group->sum(fn($o) => $o['total']))->toArray(); // ['paid' => 15, 'open' => 25]
$orders->toLookup(fn($o) => $o['status'], fn($o) => $o['id'])->get('paid')->toArray(); // [1, 3]
$orders->implode(', ', fn($o) => '#' . $o['id']); // '#1, #2, #3'
```

#### Entrada irregular (desde a 1.3.2)

Entrada irregular falha alto ou é uma escolha explícita, nunca uma remodelagem silenciosa:

- `fromCsv()` com cabeçalho é **estrito por padrão**: um registro com número de campos diferente do cabeçalho lança `RuntimeException('CSV row N has K fields, header has H')` quando é lido (os registros anteriores já foram entregues; `N` conta registros de dados a partir de 1, sem linhas vazias). `fromCsv($path, strict: false)` entrega esse registro como lista posicional (comportamento da 1.3.1, para arquivos sujos). Sem cabeçalho não há largura de referência.
- Linha vazia não é registro: é pulada em todos os modos.
- O BOM UTF-8 no início do arquivo é removido antes do parse em `fromCsv()` (um primeiro campo entre aspas continua correto) e da primeira linha em `fromFile()`.
- Diretório em `fromFile()`/`fromCsv()` lança `RuntimeException('Path is a directory, not a file: "<path>"')` na primeira travessia.
- Um statement, uma coleção: um segundo `fromCursor()` (ou `from()`) sobre o mesmo `PDOStatement` lança `RuntimeException('PDOStatement already bound to another lazy collection')` na primeira travessia. Para reiterar, use `remember()`; para rodar a consulta de novo, use um statement novo, inclusive no caso de statement preparado.

```php
ALinqLazyCollection::fromCsv('clientes.csv')->toArray();                // largura diferente → RuntimeException("CSV row 2 has 1 fields, header has 2")
ALinqLazyCollection::fromCsv('clientes.csv', strict: false)->toArray(); // comportamento 1.3.1: o registro irregular vem como lista
```

#### Detecção do separador do CSV (desde a 2.2.0)

`fromCsv($path, separator: null)` detecta o separador a cada travessia, depois do BOM, a partir do primeiro registro não vazio, lido com as regras de aspas (separador entre aspas não conta, e um campo entre aspas pode atravessar linhas), até 64 KiB. Os candidatos são `,` `;` tab `|`, e vence o que aparece **mais vezes fora das aspas**. Empate entre os mais frequentes lança `RuntimeException('Cannot detect the CSV separator of "<path>": "," and ";" both appear N times in the first record; pass $separator')`; registro sem nenhum deles é lido com `,` (um campo só lê igual com qualquer separador); primeiro registro acima de 64 KiB ou stream que não aceita `fseek` lançam. O padrão continua `','`: ninguém passa a detectar sem pedir.

<!-- tested: 10-csv -->
```php
use Antevemus\ALinq\ALinqLazyCollection;

$path = tempnam(sys_get_temp_dir(), 'index');
file_put_contents($path, "date;value\n2026-01;0,52\n2026-02;0,61\n");

ALinqLazyCollection::fromCsv($path, separator: null)->toArray(); // [['date' => '2026-01', 'value' => '0,52'], ['date' => '2026-02', 'value' => '0,61']]
ALinqLazyCollection::fromCsv($path, ';')->toArray() === ALinqLazyCollection::fromCsv($path, separator: null)->toArray(); // true

unlink($path);
```

### 11. O Contrato: Chaves, Igualdade, Coleções Vazias (desde a 1.3.0)

As duas coleções obedecem a um único contrato escrito, garantido por um teste de paridade que executa toda operação compartilhada sobre as mesmas entradas no lado eager e no lado lazy (`tests/Contract/ParityTest.php`, zero divergências permitidas). Quando uma operação lança, a classe da exceção e, desde a 1.4.0, a mensagem precisam ser as mesmas nos dois lados; uma segunda matriz roda os operadores baseados em campo e chave (`whereIn`, `whereBetween`, as junções externas, `thenBy`, `single` e, desde a 2.2.0, `toLookup`, `exceptByKeys`, `countBy`) sobre linhas dadas como arrays e como entidades com campos privados. Desde a 2.2.0 os operadores de agrupamento (`groupBy`, `countBy`, `aggregateBy`, `toDictionary`, `toLookup`), `exceptByKeys`/`intersectByKeys`, `implode` e todo parâmetro de comparador estão na matriz, e propriedades com oráculo independente (`tests/Contract/ConsumerGapsPropertyTest.php`) os conferem sobre entradas geradas.

| Regra | Comportamento |
|---|---|
| **Chaves** | Uma **lista** (`array_is_list()`) sai **reindexada** de toda operação de filtragem, fatiamento, conjunto ou ordenação; um **dicionário** (qualquer outro formato de chaves, inclusive `[10 => 'a', 20 => 'b']`) **mantém as chaves**. `select()` sempre preserva as chaves. `concat()`, `selectMany()`, `join()`, `groupJoin()`, `leftJoin()`, `rightJoin()`, `fullJoin()`, `zip()`, `unionBy()` sempre produzem lista. `chunk()` é uma lista de coleções (cada pedaço segue a regra da sua origem). A coleção lazy sabe se a fonte é lista (`from(array)`, `fromFile()`, `fromCsv()`, `fromCursor()`, `fromHttpPages()`, `range()`, `repeat()`); um generator cru é "desconhecido": mantém as chaves durante o streaming e `toArray()` só reindexa quando todas as chaves são inteiras. A materialização nunca perde item: chave repetida é anexada. |
| **Igualdade** | Elementos e chaves são comparados por **identidade estrita e tipada** em toda parte: `distinct`, `distinctBy`, `intersect`, `except`, `intersectBy`, `exceptBy`, `unionBy`, `join`, `groupJoin`, `leftJoin`, `rightJoin`, `fullJoin`, `exceptByKeys`, `intersectByKeys` (2.2.0), `contains`, e o `whereIn`/`whereNotIn` das coleções. `1`, `'1'`, `1.0` e `true` são quatro valores diferentes; arrays comparam por valor; objetos por identidade. Chave `null` nunca casa em `join`/`groupJoin` nem nas junções externas (semântica do LINQ e do SQL; numa junção externa esse item sai sem par); numa operação de conjunto (`intersectBy`, `exceptBy`, `exceptByKeys`, `intersectByKeys`, `distinct`) `null` é um valor como outro qualquer e casa com `null`. Uma coluna de banco que chega como string não casa com uma chave inteira: faça o cast do seu lado. Um comparador customizado (`contains($v, $cmp)`, `intersectWith`, `exceptWith`) pode devolver `bool` (`true` = iguais) ou um inteiro no estilo `<=>` (`0` = iguais). |
| **Chaves de grupo** | `groupBy`, `countBy`, `aggregateBy`, `toDictionary` e `toLookup` (2.2.0) aceitam chaves `int`, `string` e `BackedEnum` (o seu valor); `null`, `bool`, `float`, arrays e objetos lançam `InvalidArgumentException` em vez de serem coagidos em silêncio (`toLookup()` também, diferente do .NET, que aceita chave `null`). A chave de grupo é então uma **chave de array do PHP**, não identidade estrita: `1` e `'1'` são a mesma chave (`'01'` é outra), ao contrário da linha **Igualdade** acima. `toDictionary()` lança em chave repetida, a menos que se passe `DuplicateKeyPolicy::KeepFirst`/`KeepLast` (2.2.0). `aggregateBy()` entrega a `$func` a posição do item dentro do grupo (0, 1, …), não a chave da fonte; todo grupo parte da mesma semente. Na coleção lazy `groupBy`, `countBy` e `aggregateBy` são estágios postergados cujo resultado é um dicionário indexado pela chave do grupo (2.2.0). |
| **Coleções vazias** | Como no LINQ: `first()`, `last()`, `min()`, `max()`, `minBy()`, `maxBy()`, `average()` lançam `UnderflowException` em coleção vazia ou quando nenhum elemento casa; `firstOrDefault()`, `lastOrDefault()`, `singleOrDefault()` devolvem o default, assim como `minOrDefault()`, `maxOrDefault()`, `averageOrDefault()`, `minByOrDefault()`, `maxByOrDefault()` (2.1.0) onde o irmão lança por não haver o que agregar; `singleOrDefault()` lança `OverflowException` com mais de um resultado; `single()` (1.4.0) lança `UnderflowException('Sequence contains no elements.')` / `('Sequence contains no matching element.')` e `OverflowException('Sequence contains more than one element.')` / `('Sequence contains more than one matching element.')`; `random()` lança `UnderflowException` como `first()` e `random(n > 1)` devolve coleção vazia como `take(n)`; `sum()` de nada é `0`, `product()` de nada é `1`; `any()` significa "tem ao menos um item"; `all($p)` de nada é `true`; `all()` sem predicado significa "todo item é truthy". |
| **Ordenação** (1.4.0) | `orderBy()`/`orderByDescending()` são estáveis; `thenBy()`/`thenByDescending()` refinam uma coleção que vem direto de `orderBy*()`/`thenBy*()` e lançam `LogicException('thenBy() requires a preceding orderBy().')` (ou `thenByDescending() ...`) depois de qualquer outra operação; empate total mantém a ordem da origem; seletores de chave recebem `($item, $key)` com a chave da origem; um comparador recebe as duas chaves, `fn($chaveA, $chaveB): int` (também no `orderBy*()` desde a 2.2.0). |
| **Comparadores** (2.2.0) | Sem comparador, a ordenação e `min`/`max`/`minBy`/`maxBy` usam o `<=>` do PHP (strings numéricas comparam como números), igual nas duas coleções. Um comparador de ordem é `fn($a, $b): int` na convenção do `<=>` (`orderBy*`, `thenBy*`, `min*`, `max*`, `minBy*`, `maxBy*`, `orderByCustom`); um comparador de igualdade (`contains`, `intersectWith`, `exceptWith`) pode responder `bool` ou `<=>`; `distinct`/`distinctBy` aceitam um `IALinqEqualityComparer` (`hash()` separa em baldes, `equals()` decide). `StringComparer` (`ordinal()`, `ordinalIgnoreCase()`, `culture()`) é dos dois tipos. Com comparador, `min`/`max` continuam pulando `null` e o comparador decide o que é comparável. Não há comparador em agrupamento, operações de conjunto nem junções: agrupe por `$comparador->hash($texto)` para agrupar sem acento. |
| **Argumentos** | `take`/`skip`/`pad` com contagem negativa, `chunk(0)`, `random(0)`, `range()` com passo não positivo lançam `InvalidArgumentException` (nada fatia pela cauda nem é ignorado em silêncio). |
| **Números** | `sum`, `average`, `min`, `max`, `product` **ignoram `null`** (`average([1, null, 2])` é `1.5`); quando só resta `null`, a coleção conta como vazia. Valor não numérico (string, array, objeto) lança `InvalidArgumentException` em `sum`/`average`/`product`; `min`/`max` comparam qualquer escalar ou `DateTimeInterface`. |
| **Callbacks** | Um callback recebe `($item, $key)` quando aceita dois parâmetros e só o item caso contrário, então `where('is_int')`, `select('trim')` e `fn($v, $k) => ...` funcionam em todo operador das duas coleções. |
| **Imutabilidade** | Nenhuma operação de consulta altera a coleção de origem. `each()` itera sobre uma cópia: callback por referência não altera a coleção (use `select()` para transformar). |
| **Query builder** | `toPredicate()` é um snapshot: chamadas posteriores a `where()` não alteram um predicado já devolvido. `create($mode)` aceita `and`/`or` (qualquer caixa) e lança para o resto. Quando a propriedade resolve para `null`, `>`, `>=`, `<`, `<=`, `between`, `notBetween`, `contains`, `startsWith`, `endsWith` são `false`; `=`/`==` e `!=`/`<>` comparam estritamente com `null` (`where('x', '=', null)` significa "é nulo"); propriedade ausente resolve para `null`. |
| **JSON** | As duas coleções implementam `JsonSerializable`: lista vira array JSON, dicionário vira objeto JSON. |

---

## 🧪 Qualidade de Código & Cobertura de Testes

A biblioteca possui cobertura de testes unitários com **100% de aprovação** no PHP 8.4:

```bash
vendor/bin/phpunit
```

```text
====================================================================
 PHPUnit 11.5.42 - ANTEVEMUS ALINQ COLLECTION TEST SUITE
====================================================================

...............................................................  63 / 731 (  8%)
............................................................... 126 / 731 ( 17%)
............................................................... 189 / 731 ( 25%)
............................................................... 252 / 731 ( 34%)
............................................................... 315 / 731 ( 43%)
............................................................... 378 / 731 ( 51%)
............................................................... 441 / 731 ( 60%)
............................................................... 504 / 731 ( 68%)
............................................................... 567 / 731 ( 77%)
............................................................... 630 / 731 ( 86%)
............................................................... 693 / 731 ( 94%)
......................................                          731 / 731 (100%)

Time: 00:03.181, Memory: 14.00 MB

OK, but some tests were skipped!
Tests: 731, Assertions: 2968, Skipped: 10.
====================================================================
 RESULTADO: 100% APROVADO | 0 REGRESSÕES | 0 DEPRECATIONS
====================================================================
```

- **731 Testes & 2968 Asserções** certificando todos os 8 traits funcionais, o motor de streaming, o contrato eager × lazy e a delegação ao acessor de propriedades compartilhado (`tests/Contract`), as propriedades dos operadores da 2.2.0 contra oráculos escritos à mão (`tests/Contract/ConsumerGapsPropertyTest.php`), os blocos da §9 do README e os da 2.2.0 como estão escritos (o mesmo código nos dois idiomas), e a fonte HTTP paginada contra um servidor `php -S` local em 127.0.0.1 (`tests/Unit/Http`, grupo `http`).
- **Sem `ext-intl` os 10 testes que usam `StringComparer::culture()` são pulados** (os 10 pulados acima, num PHP sem intl); com ela a suíte roda 731 testes e 3051 asserções com 1 pulado (o teste da extensão ausente). O CI roda as duas pernas.
- **Cobertura medida** (pcov, 2.2.0, com `ext-intl`, PHP 8.4.26): 289 de 299 métodos e 1619 de 1637 linhas de `src/`; as 18 linhas descobertas são guardas defensivas ou dependem do ambiente: duas novas no `StringComparer` (ext-mbstring ausente, ext-intl ausente), uma na junção externa compartilhada (a checagem de tipo desconhecido), catorze no caminho HTTP (ext-curl ausente, falhas ao montar o handle do curl, mensagem de transporte ou URL efetiva vazias, falha na espera do socket, URL base ilegível e entrada sem `rel` em `link()`) e a saída antecipada do `zip()` (coberta no PHP 8.4.13). Sem `ext-intl` (PHP 8.4.13): 288 de 299 métodos, 1574 de 1617 linhas (a diferença é o caminho do `culture()`).
- **Teste de mutação** (Infection, desde a 2.2.0, no escopo das linhas alteradas desde a 2.1.0; `infection.json5`, workflow Quality): 415 mutantes, 380 mortos, 24 erros e 1 time-out (detectados), 10 sobreviventes, todos equivalentes (MSI 97,59%).
- **Compatibilidade Nativa com PHP 8.4**: Validado com `array_any`, `array_all`, `array_find`, `array_find_key`.
- **Uma Dependência de Runtime, do Mesmo Autor**: Biblioteca pura em PHP 8.4; a única exigência é o [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (desde a 2.0.0), o acessor de propriedades compartilhado com o ASpecification, que não tem dependências próprias. Nenhum pacote de terceiros.

---

## 🗺️ Roadmap & Ecossistema

- [x] **v1.0.0**: Release oficial estável de produção com 8 traits centrais, 305 testes e motor nativo PHP 8.4.
- [x] **Sinergia com ASpecification**: Integração direta com `Antevemus.ASpecification` via `ALinqBridge`.
- [x] **v1.1.0**: Pipeline de avaliação preguiçosa (Lazy Evaluation) baseado em Generators (`ALinqLazyCollection`) para fluxos de dados de múltiplos gigabytes sem esgotar a memória.
- [x] **v1.1.1**: Correções do motor de streaming: `remember()` só guarda passagens completas, `fromFile()` preserva linhas longas inteiras, cursores de passagem única falham alto na segunda travessia.
- [x] **v1.1.2**: Materializadores lazy nunca perdem itens (lista reindexada, dicionário preservado), `remember()` resumível, `last()` com a chave real.
- [x] **v1.2.0**: Promessas do README I: grupos de `groupBy()` são coleções, `select()` recebe a chave, todo operador aceita callables nativos, `distinct()` estrito e seguro para arrays/objetos, `selectMany()` achata qualquer iterable, `between`/`notIn`/`isNull` no query builder, dot-notation e resolução de getters no `ALinqPropertyAccess`.
- [x] **v1.3.0**: O contrato: uma regra de chaves, identidade estrita, semântica LINQ em coleções vazias, argumentos e chaves de grupo validados, agregações e query builder cientes de `null`, `count($predicate)`, `JsonSerializable`; paridade eager × lazy garantida por teste (§11).
- [x] **v1.3.2**: Robustez das fontes lazy: linhas irregulares de CSV, BOM, diretórios em `fromFile()`, um statement por coleção em `fromCursor()`.
- [x] **v1.4.0**: `whereIn()` / `whereNotIn()` / `whereBetween()` / `single()`, anunciados nas notas da 0.1.0 e enfim entregues (ver a errata no CHANGELOG).
- [x] **v1.4.0**: `leftJoin()` / `rightJoin()` / `fullJoin()`: junções externas devolvendo pares `[$outer, $inner]` com `null` no lado sem correspondência, como `Enumerable.LeftJoin`/`RightJoin`/`FullJoin` no .NET 10/11 (substituem o `groupJoin()` + `selectMany()` à mão).
- [x] **v1.4.0**: `thenBy()` / `thenByDescending()`: ordenação composta e estável por várias chaves, também na coleção lazy.
- [x] **v1.4.1**: segurança: o último recurso não público do accessor de propriedades passa a ser opt-in com `#[Specifiable]`, e filtros e ordenações sobre um campo privado sem marca deixam de servir de oráculo de leitura.
- [x] **v1.4.2**: `singleOrDefault()` lança com as mesmas mensagens de `single()`, nas duas coleções.
- [x] **v1.4.3**: `suggest` do ASpecification passa a `^1.9.0`; marco 12 do roadmap corrigido (Fiber não é paralelismo).
- [x] **v1.5.0**: `fromHttpPages()`: fonte lazy sobre uma API HTTP paginada que baixa a próxima página com `curl_multi` enquanto a atual é consumida (zero dependência, marco 12 do ROADMAP, parte do núcleo).
- [x] **v1.5.1**: o workflow do GitHub Actions passa a rodar (uma chave de ambiente duplicada fazia o GitHub recusá-lo); `suggest` do ASpecification passa a `^1.10.0`.
- [x] **v2.0.0**: acessor de propriedades compartilhado com o `Antevemus.ASpecification`: as duas bibliotecas resolvem nomes pelo [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (marco 11 do ROADMAP), `ALinqPropertyAccess` vira fachada depreciada até a 3.0; `extract()` (depreciado desde a 1.3.0) removido; `suggest` do ASpecification passa a `^2.0.0`. Veja o [UPGRADE-2.0.pt-BR.md](UPGRADE-2.0.pt-BR.md).
- [x] **v2.1.0**: `minOrDefault()` / `maxOrDefault()` / `averageOrDefault()` / `minByOrDefault()` / `maxByOrDefault()`: o agregado, ou um fallback quando não há o que agregar, numa passada só (seguros em fontes de passada única).
- [x] **v2.2.0**: as lacunas achadas pelo primeiro consumidor em produção: `StringComparer` (ordinal, sem caixa, cultura pela ICU) em `orderBy*()`, `distinct*()`, `min*()`, `max*()`; `toLookup()` / `ALinqLookup`; `toDictionary(onDuplicate:)`; `groupBy()` / `countBy()` / `aggregateBy()` / `toDictionary()` / `toLookup()` na coleção lazy; `exceptByKeys()` / `intersectByKeys()`; `join()` / `groupJoin()` sobre qualquer iterable; `fromCsv(separator: null)`; `implode()` ([marco 14 do ROADMAP](ROADMAP.md)).
- [ ] **Futuro**: o restante do marco de I/O concorrente, em pacotes satélites: coleção assíncrona sobre Fibers para SDKs assíncronos e paralelismo de CPU com processos. Fiber sozinha não é paralelismo.

A visão por marcos desta lista, com releases-alvo, vive em [ROADMAP.md](ROADMAP.md).

---

## 📄 Licença & Autoria

Este projeto é um software livre distribuído sob a **[Licença MIT](LICENSE)**.

- **Autor:** Heliton Junior (CTO) — [`contato@antevemus.com.br`](mailto:contato@antevemus.com.br)
- **Organização:** [Antevemus Soluções Inovadoras em TI Ltda.](https://antevemus.com.br)
- **Copyright:** Copyright (c) 2025–2026 Antevemus Soluções Inovadoras em TI Ltda.
