Antevemus ALinq Collection
Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda.
License: MIT (SPDX: MIT) — see LICENSE.

Website: https://antevemus.com.br
Email: contato@antevemus.com.br

-------------------------------------------------------------------------------
1. SCOPE & PURPOSE
-------------------------------------------------------------------------------

Antevemus ALinq Collection is an enterprise LINQ-style collection manipulation
framework for PHP 8.4+. It provides a fluent, expressive, and type-safe API
leveraging PHP 8.4 native array functions (array_any, array_all, array_find,
array_find_key), relational joins, mathematical set operations, dynamic query
building, and dot-notation property resolution.

-------------------------------------------------------------------------------
2. CONCEPTUAL INSPIRATION — LANGUAGE INTEGRATED QUERY (.NET LINQ)
-------------------------------------------------------------------------------

The API design and method nomenclature of this library are conceptually inspired
by the Language Integrated Query (LINQ) pattern developed by Microsoft Corporation
for the .NET platform and C# language specification.

    Conceptual Origin: .NET Language Integrated Query (LINQ)
    Inspiration: Standard C# / .NET LINQ Enumerable Extensions

THIS LIBRARY DOES NOT CONTAIN, REPRODUCE, OR REDISTRIBUTE C# OR .NET SOURCE CODE.
It is an independent, pure PHP 8.4+ implementation developed from first principles
by Antevemus Soluções Inovadoras em TI Ltda.

-------------------------------------------------------------------------------
2b. CONCEPTUAL INSPIRATION — LARAVEL LAZY COLLECTIONS AND DOCTRINE ITERATION
-------------------------------------------------------------------------------

The design of ALinqLazyCollection (a deferred pipeline over PHP generators, a
Closure factory accepted by the constructor, and the method names remember(),
tap(), pad(), each() and chunk()) is conceptually inspired by the lazy
collections of the Laravel Framework.

    Conceptual Origin: Illuminate\Support\LazyCollection (Laravel Framework)
    Originator: Taylor Otwell
    License: MIT License
    Website: https://github.com/laravel/framework

The idea of walking a database cursor without loading the whole table
(fromCursor()) has a precedent in the Doctrine ORM.

    Conceptual Origin: Doctrine\ORM\Query::toIterable() (Doctrine ORM)
    Originator: Doctrine Project
    License: MIT License
    Website: https://github.com/doctrine/orm

THIS LIBRARY DOES NOT CONTAIN, REPRODUCE, OR DERIVE FROM THE SOURCE CODE OF
THESE PROJECTS. ALinqLazyCollection is an independent implementation with its
own contract: a resumable remember() that buffers key/item pairs, a chunk()
that yields ALinqCollection instances, and a single-pass PDO cursor that fails
loudly on a second traversal. The MIT license texts of these projects are not
redistributed here because no code of theirs is redistributed.

-------------------------------------------------------------------------------
3. THIRD-PARTY COMPONENTS & LICENSES
-------------------------------------------------------------------------------

See the THIRD_PARTY_NOTICES.md file for the complete list and full texts of
applicable third-party software and licenses.
