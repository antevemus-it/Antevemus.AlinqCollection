# Antevemus ALinq Collection

[![Latest Stable Version](https://img.shields.io/badge/release-v1.0.0-blue.svg)](https://github.com/antevemus-it/Antevemus.AlinqCollection/releases)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests Passing](https://img.shields.io/badge/testes-305%20aprovados%20%7C%20485%20asserções-success.svg)](tests/)
[![Architecture](https://img.shields.io/badge/Arquitetura-LINQ%20%7C%20Coleções%20Funcionais-orange)](https://learn.microsoft.com/en-us/dotnet/csharp/linq/)
[![Synergy: ASpecification](https://img.shields.io/badge/Sinergia-Antevemus.ASpecification-purple)](https://github.com/antevemus-it/Antevemus.ASpecification)

> **Framework de Coleções Fluentes no Estilo LINQ para PHP 8.4+**  
> Um motor de manipulação de coleções expressivo, fluente e type-safe que traz a elegância do .NET Language Integrated Query (LINQ) para o PHP moderno. Impulsionado pelas funções nativas de array em C do PHP 8.4 (`array_any`, `array_all`, `array_find`, `array_find_key`), teoria dos conjuntos avançada, query builders dinâmicos de predicados, resolução profunda de propriedades por notação de ponto e integração nativa com o [`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification).

---

[🇺🇸 English](README.md) • [🇧🇷 Português (Brasil)](README.pt-BR.md)

---

## 🏛️ Origens & Filosofia

O desenvolvimento moderno em PHP frequentemente enfrenta códigos repetitivos e verbosos para manipulação de arrays: loops aninhados manuais, variáveis acumuladoras mutáveis e closures prolixas envolvendo funções nativas de array. Enquanto desenvolvedores .NET contam há mais de uma década com o **LINQ (Language Integrated Query)** como padrão declarativo de consulta em memória, os desenvolvedores PHP dependiam de bibliotecas monolíticas pesadas e lentas.

O **Antevemus ALinq Collection** foi projetado do zero para o **PHP 8.4+**:
1. **Primitivas Nativas em C**: Aproveita diretamente as novas funções nativas `array_any()`, `array_all()`, `array_find()` e `array_find_key()` do PHP 8.4 para máxima performance sem sobrecarga no userspace.
2. **Arquitetura Modular Decomposta em Traits**: Elimina monolitos. As operações são distribuídas em 8 traits funcionais especializados (Filtragem, Junções, Agregações, Projeções, Agrupamento, Ordenação, Iteradores e Utilitários).
3. **Teoria dos Conjuntos Enterprise**: Operações completas de diagramas de Venn (`intersectBy`, `exceptBy`, `unionBy`) com seletores de chave e comparadores customizados.
4. **Resolução Profunda por Notação de Ponto**: Consulta estruturas de dados heterogêneas (arrays, objetos, DTOs e entidades de domínio) navegando caminhos aninhados como `user.profile.address.city`.
5. **Sinergia Arquitetural**: Parceiro nativo do **[`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification)**, permitindo que árvores de especificação de Eric Evans e Martin Fowler compilem diretamente para pipelines LINQ em memória via `ALinqBridge` e `ALinqSpecificationVisitor`.

---

## 🌟 Resumo dos Recursos

| Domínio de Operação | Capacidades |
| :--- | :--- |
| **🔍 Filtragem & Fatiamento** | `where`, `take`, `skip`, `distinct`, `distinctBy`, `first`, `firstOrDefault`, `last`, `lastOrDefault`, `singleOrDefault`, `chunk`, `pad`, `shuffle`, `contains` |
| **🔗 Junções & Conjuntos** | `join` (inner), `groupJoin` (left outer), `concat`, `intersect`, `intersectWith`, `intersectBy`, `except`, `exceptWith`, `exceptBy`, `unionBy`, `combine`, `replace`, `replaceRecursive` |
| **📊 Agregações & Métricas** | `any`, `all`, `sum`, `average`, `min`, `minBy`, `max`, `maxBy`, `product`, `countValues`, `aggregate`, `aggregateBy`, `countBy` |
| **🎯 Seleção & Projeção** | `select`, `selectMany` (achatamento de listas aninhadas), `column`, `toDictionary`, `toObject`, `flip`, `toArray` |
| **🗂️ Agrupamento & Partições** | `groupBy` (subcoleções tipadas com encadeamento fluente completo) |
| **⚡ Ordenação & Classificação** | `orderBy`, `orderByDescending`, `orderByNatural` (ordenação alfanumérica humana), `orderByCustom`, `orderByKey`, `reverse` |
| **🧭 Iteração & Navegação** | `each`, `eachRecursive`, `current`, `key`, `next`, `prev`, `reset`, `end`, paridade total com `IteratorAggregate` e `Countable` |
| **🛠️ Query Builder Dinâmico** | `ALinqQueryBuilder` (`create('and'\|'or')`, `where('campo', '>=', $val)`, `toPredicate()`) |
| **🔎 Acesso a Propriedades** | `ALinqPropertyAccess` com dot-notation (`getValue($item, 'empresa.endereco.cep')`) |

---

## 📦 Instalação

Instale via Composer:

```bash
composer require antevemus/alinq-collection
```

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
        "antevemus/alinq-collection": "^1.0"
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
├── ALinqCollection.php          # Fachada principal implementando IALinqCollection, Countable e Traversable
├── ALinqQueryBuilder.php        # Construtor fluente de condições (AND / OR) para consultas dinâmicas
├── Helpers\
│   └── ALinqPropertyAccess.php  # Getter universal com suporte a notação de ponto, arrays e objetos
├── Interfaces\                  # 10 Contratos Segregados
│   ├── IALinqBaseCollection.php
│   ├── IALinqCollection.php
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
use function Antevemus\ASpecification\DSL\isGreaterThan;

// 1. Definir Regras de Negócio com o Padrão Specification (Evans & Fowler)
$clienteElegivel = Spec::and(
    Spec::property('status', is('ATIVO')),
    Spec::property('scoreCredito', isGreaterThan(700))
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
$grupos = $colecao->chunk(3); // [[1, 2, 3], [4, 5, 6], [7, 8, 9], [10]]
$preenchido = ALinqCollection::from([1, 2])->pad(5, 0); // [1, 2, 0, 0, 0]
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
    resultSelector: fn($d, array $membros) => [
        'departamento' => $d['nome'],
        'membros'      => array_column($membros, 'nome'),
    ]
)->toArray();

// 3. Operações de Conjunto com Seletores de Chave
$conjuntoA = ALinqCollection::from([['id' => 1], ['id' => 2], ['id' => 3]]);
$conjuntoB = [['id' => 2], ['id' => 3], ['id' => 4]];

$intersecao = $conjuntoA->intersectBy($conjuntoB, fn($item) => $item['id']); // [id: 2, id: 3]
$diferenca  = $conjuntoA->exceptBy($conjuntoB, fn($item) => $item['id']);    // [id: 1]
$uniao      = $conjuntoA->unionBy($conjuntoB, fn($item) => $item['id']);     // [id: 1, id: 2, id: 3, id: 4]
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
```

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

---

### 7. Ordenação e Classificação (`OrderingOperations`)

```php
$arquivos = ALinqCollection::from([
    'arquivo10.txt', 'arquivo2.txt', 'arquivo1.txt', 'ARQUIVO100.txt'
]);

// Ordenação Alfanumérica Natural (ordenação humana: arquivo1, arquivo2, arquivo10, ARQUIVO100)
$ordenadosNaturalmente = $arquivos->orderByNatural(caseSensitive: false)->toArray();

// Ordenação por múltiplos atributos
$colaboradores = ALinqCollection::from([
    ['depto' => 'Vendas', 'salario' => 5000],
    ['depto' => 'TI',     'salario' => 7000],
    ['depto' => 'TI',     'salario' => 9000],
]);

$ordenados = $colaboradores
    ->orderBy(fn($c) => $c['depto'])
    ->orderByDescending(fn($c) => $c['salario'])
    ->toArray();
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

---

### 9. Resolução Profunda de Propriedades (`ALinqPropertyAccess`)

Consulte com segurança estruturas aninhadas sem se preocupar se as chaves são arrays associativos, propriedades públicas, métodos getters ou híbridos:

```php
use Antevemus\ALinq\Helpers\ALinqPropertyAccess;

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
$cnpj = ALinqPropertyAccess::getValue($payload, 'usuario.perfil.empresa.cnpj');
// Retorna: '12.345.678/0001-90'
```

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

...............................................................  63 / 305 ( 20%)
............................................................... 126 / 305 ( 41%)
............................................................... 189 / 305 ( 61%)
............................................................... 252 / 305 ( 82%)
.....................................................           305 / 305 (100%)

Time: 00:05.734, Memory: 6.00 MB

OK (305 tests, 485 assertions)
====================================================================
 RESULTADO: 100% APROVADO | 0 REGRESSÕES | 0 DEPRECATIONS
====================================================================
```

- **305 Testes Unitários & 485 Asserções** certificando todos os 8 traits funcionais.
- **Compatibilidade Nativa com PHP 8.4**: Validado com `array_any`, `array_all`, `array_find`, `array_find_key`.
- **Zero Dependências Externas**: Biblioteca pura em PHP 8.4 sem nenhuma exigência de terceiros.

---

## 🗺️ Roadmap & Ecossistema

- [x] **v1.0.0**: Release oficial estável de produção com 8 traits centrais, 305 testes e motor nativo PHP 8.4.
- [x] **Sinergia com ASpecification**: Integração direta com `Antevemus.ASpecification` via `ALinqBridge`.
- [ ] **v1.1.0**: Pipeline de avaliação preguiçosa (Lazy Evaluation) baseado em Generators para fluxos de dados de múltiplos gigabytes sem esgotar a memória.
- [ ] **v1.2.0**: Processamento paralelo de coleções utilizando PHP Fibers e workers concorrentes.

---

## 📄 Licença & Autoria

Este projeto é um software livre distribuído sob a **[Licença MIT](LICENSE)**.

- **Autor:** Heliton Junior (CTO) — [`contato@antevemus.com.br`](mailto:contato@antevemus.com.br)
- **Organização:** [Antevemus Soluções Inovadoras em TI Ltda.](https://antevemus.com.br)
- **Copyright:** Copyright (c) 2025–2026 Antevemus Soluções Inovadoras em TI Ltda.
