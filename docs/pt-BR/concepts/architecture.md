# 🧩 Visão Arquitetural

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
