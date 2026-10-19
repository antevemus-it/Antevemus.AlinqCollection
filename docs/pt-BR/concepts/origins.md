# 🏛️ Origens & Filosofia

O desenvolvimento moderno em PHP frequentemente enfrenta códigos repetitivos e verbosos para manipulação de arrays: loops aninhados manuais, variáveis acumuladoras mutáveis e closures prolixas envolvendo funções nativas de array. Enquanto desenvolvedores .NET contam há mais de uma década com o **LINQ (Language Integrated Query)** como padrão declarativo de consulta em memória, os desenvolvedores PHP dependiam de bibliotecas monolíticas pesadas e lentas.

O **Antevemus ALinq Collection** foi projetado do zero para o **PHP 8.4+**:
1. **Primitivas Nativas em C**: Aproveita diretamente as novas funções nativas `array_any()`, `array_all()`, `array_find()` e `array_find_key()` do PHP 8.4 para máxima performance sem sobrecarga no userspace.
2. **Arquitetura Modular Decomposta em Traits**: Elimina monolitos. As operações são distribuídas em 8 traits funcionais especializados (Filtragem, Junções, Agregações, Projeções, Agrupamento, Ordenação, Iteradores e Utilitários).
3. **Pipelines de Streaming Baseados em Generators (O(1) de RAM)**: `ALinqLazyCollection` processa arquivos de log de múltiplos gigabytes, CSVs massivos e cursores PDO não bufferizados linha a linha, sem inflação de memória.
4. **Teoria dos Conjuntos Enterprise**: Operações completas de diagramas de Venn (`intersectBy`, `exceptBy`, `unionBy`) com seletores de chave e comparadores customizados.
5. **Resolução Profunda por Notação de Ponto**: Consulta estruturas de dados heterogêneas (arrays, objetos, DTOs e entidades de domínio) navegando caminhos aninhados como `user.profile.address.city`, resolvidos pelo acessor compartilhado com o ASpecification ([`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess), desde a 2.0.0).
6. **Sinergia Arquitetural**: Parceiro nativo do **[`Antevemus.ASpecification`](https://github.com/antevemus-it/Antevemus.ASpecification)**, permitindo que árvores de especificação de Eric Evans e Martin Fowler compilem diretamente para pipelines LINQ em memória via `ALinqBridge` e `ALinqSpecificationVisitor`.
