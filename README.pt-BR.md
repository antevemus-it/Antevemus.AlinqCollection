# Antevemus ALinq Collection

[![CI](https://github.com/antevemus-it/Antevemus.AlinqCollection/actions/workflows/tests.yml/badge.svg)](https://github.com/antevemus-it/Antevemus.AlinqCollection/actions/workflows/tests.yml)
[![Latest Stable Version](https://img.shields.io/badge/release-v2.3.0-blue.svg)](https://github.com/antevemus-it/Antevemus.AlinqCollection/releases)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![Tests Passing](https://img.shields.io/badge/testes-772%20aprovados%20%7C%204102%20asserções-success.svg)](docs/pt-BR/quality/tests.md)
[![Architecture](https://img.shields.io/badge/Arquitetura-LINQ%20%7C%20Coleções%20Funcionais-orange)](https://learn.microsoft.com/en-us/dotnet/csharp/linq/)
[![Synergy: ASpecification](https://img.shields.io/badge/Sinergia-Antevemus.ASpecification-purple)](https://github.com/antevemus-it/Antevemus.ASpecification)

> **Framework de Coleções Fluentes no Estilo LINQ para PHP 8.4+**  
> Um motor de manipulação de coleções expressivo, fluente e type-safe que traz a elegância do .NET Language Integrated Query (LINQ) para o PHP moderno. Impulsionado pelas funções nativas de array em C do PHP 8.4 (`array_any`, `array_all`, `array_find`, `array_find_key`), pipelines de streaming baseados em generators (`ALinqLazyCollection`), teoria dos conjuntos avançada, query builders dinâmicos de predicados, resolução profunda de propriedades por notação de ponto e integração nativa com o [`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification).

---

[🇺🇸 English](README.md) • [🇧🇷 Português (Brasil)](README.pt-BR.md)

---

A documentação vive em [`docs/pt-BR`](docs/pt-BR/index.md) (português do Brasil) e [`docs/en`](docs/en/index.md) (inglês), uma página por tema, no formato do Docs Kit (um `docs-nav.json` por idioma). A Referência da API mantém os números de seção que tinha neste README (a §9 continua `### 9.`, agora na sua própria página), e todo bloco PHP das páginas é conferido pela suíte de testes.

---

## 🚀 Guia Rápido

O mesmo bloco abre os [Primeiros Passos](docs/pt-BR/getting-started/index.md), e a suíte de testes o executa com as respostas escritas nos comentários:

<!-- tested: quick-start -->
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

## 📦 Instalação

Instale via Composer: `composer require antevemus/alinq-collection`. Requisitos: PHP 8.4 ou superior, e uma dependência, instalada junto: [`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess) (do mesmo autor, sem dependências próprias), o acessor de propriedades compartilhado com o Antevemus.ASpecification. Atualizando da 1.x: veja o [UPGRADE-2.0.pt-BR.md](UPGRADE-2.0.pt-BR.md). Instalação pelo repositório Git VCS: [Primeiros Passos](docs/pt-BR/getting-started/index.md).

---

## 📚 Documentação

Uma linha por página, agrupadas como na navegação; §N é a seção da Referência da API que a página hospeda, numerada como no README anterior.

**Primeiros passos**

- [Instalação e guia rápido](docs/pt-BR/getting-started/index.md) · [en](docs/en/getting-started/index.md): Composer, o repositório Git VCS e o guia rápido.

**Conceitos**

- [Origens e filosofia](docs/pt-BR/concepts/origins.md) · [en](docs/en/concepts/origins.md): Por que LINQ em PHP, e os seis princípios de projeto da biblioteca.
- [Resumo dos recursos](docs/pt-BR/concepts/core-features.md) · [en](docs/en/concepts/core-features.md): Todos os operadores da biblioteca numa tabela, por domínio.
- [Visão arquitetural](docs/pt-BR/concepts/architecture.md) · [en](docs/en/concepts/architecture.md): A árvore do código: as classes principais, as 11 interfaces e os 8 traits.
- [Sinergia com o ASpecification](docs/pt-BR/concepts/aspecification-synergy.md) · [en](docs/en/concepts/aspecification-synergy.md): Regras do ASpecification avaliadas sobre coleções ALinq pelo `ALinqBridge`.

**Referência da API**

- [Fábricas](docs/pt-BR/api/factories.md) (§1) · [en](docs/en/api/factories.md): `from()`, `range()`, `repeat()`, `empty()`, `toArray()` e `toObject()`.
- [Filtragem e fatiamento](docs/pt-BR/api/filtering.md) (§2) · [en](docs/en/api/filtering.md): `where()`, `take()`/`skip()`, `distinct()`, `first()`, `chunk()` e os filtros por campo `whereIn()`, `whereNotIn()`, `whereBetween()`.
- [Junções e teoria dos conjuntos](docs/pt-BR/api/joining.md) (§3) · [en](docs/en/api/joining.md): Junções internas e externas, `groupJoin()`, as operações de conjunto e `exceptByKeys()`/`intersectByKeys()`.
- [Agregações e métricas](docs/pt-BR/api/aggregations.md) (§4) · [en](docs/en/api/aggregations.md): `any()`, `all()`, `sum()`, `average()`, `min()`/`max()`, `single()` e as agregações `*OrDefault()`.
- [Projeções e transformações](docs/pt-BR/api/projections.md) (§5) · [en](docs/en/api/projections.md): `select()`, `selectMany()`, `column()`, `toDictionary(onDuplicate:)` e `toLookup()`.
- [Agrupamento e particionamento](docs/pt-BR/api/grouping.md) (§6) · [en](docs/en/api/grouping.md): `groupBy()` em subcoleções, e o `ALinqLookup`, um `groupBy()` materializado.
- [Ordenação e classificação](docs/pt-BR/api/ordering.md) (§7) · [en](docs/en/api/ordering.md): `orderBy()`/`thenBy()` estáveis, ordenações natural e customizada, e o `StringComparer`.
- [Query builder dinâmico](docs/pt-BR/api/query-builder.md) (§8) · [en](docs/en/api/query-builder.md): `ALinqQueryBuilder`: condições no modo `and`/`or`, seus operadores e o `toPredicate()`.
- [Resolução profunda de propriedades](docs/pt-BR/api/property-access.md) (§9) · [en](docs/en/api/property-access.md): Caminhos em notação de ponto, a ordem de resolução e a marcação `#[Specifiable]` das propriedades não públicas.
- [Streaming e big data](docs/pt-BR/api/lazy-streaming.md) (§10) · [en](docs/en/api/lazy-streaming.md): `ALinqLazyCollection`: arquivos, CSV, cursores de banco, HTTP paginado, `remember()` e os estágios que acumulam dados.
- [O contrato](docs/pt-BR/api/contract.md) (§11) · [en](docs/en/api/contract.md): As regras que as duas coleções obedecem: chaves, igualdade, chaves de grupo, coleções vazias, ordenação, comparadores, argumentos e mais.

**Qualidade**

- [Testes](docs/pt-BR/quality/tests.md) · [en](docs/en/quality/tests.md): O relatório do PHPUnit, a cobertura medida e o teste de mutação.

**Projeto**

- [Roadmap](docs/pt-BR/project/roadmap.md) · [en](docs/en/project/roadmap.md): Cada release entregue e o que vem a seguir; a visão por marcos está no ROADMAP.md.
- [Licença e autoria](docs/pt-BR/project/license-and-authors.md) · [en](docs/en/project/license-and-authors.md): A licença MIT, o autor e a organização.

---

## 🧪 Qualidade

- **Testes:** 772 testes PHPUnit (`vendor/bin/phpunit`) sobre os 8 traits, o motor de streaming, o contrato eager × lazy e os blocos de código da documentação, 100% de aprovação no PHP 8.4.
- **Asserções:** 4102 sem `ext-intl` (os 10 testes do `StringComparer::culture()` pulados) e 4185 com ela (1 pulado, o teste da extensão ausente); o CI roda as duas pernas.
- **Cobertura** (pcov, 2.2.0, com `ext-intl`): 289 de 299 métodos e 1619 de 1637 linhas de `src/`; as 18 linhas descobertas são guardas defensivas ou dependem do ambiente.
- **Teste de mutação:** Infection nas linhas alteradas desde a 2.1.0, MSI 97,59% (10 mutantes sobreviventes, todos equivalentes).
- **Paridade do contrato:** toda operação compartilhada roda sobre as mesmas entradas na coleção eager e na lazy, sem nenhuma divergência permitida (`tests/Contract/ParityTest.php`; as regras: [O contrato](docs/pt-BR/api/contract.md)).
- **O relatório**, os detalhes da cobertura e a rodada de mutação: [Testes](docs/pt-BR/quality/tests.md).

---

## 🗺️ Roadmap & Ecossistema

Cada release entregue e o que vem a seguir: [Roadmap](docs/pt-BR/project/roadmap.md). A visão por marcos, com releases-alvo: 👉 **[ROADMAP.md](ROADMAP.md)**.

---

## 📄 Licença & Autoria

Este projeto é um software livre distribuído sob a **[Licença MIT](LICENSE)**.

- **Autor:** Heliton Junior (CTO) — [`contato@antevemus.com.br`](mailto:contato@antevemus.com.br)
- **Organização:** [Antevemus Soluções Inovadoras em TI Ltda.](https://antevemus.com.br)
- **Copyright:** Copyright (c) 2025–2026 Antevemus Soluções Inovadoras em TI Ltda.
