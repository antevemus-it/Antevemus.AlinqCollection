# 🧩 Architectural Deep Dive

`Antevemus.AlinqCollection` is engineered for high maintainability, strict typing, and zero circular dependencies. Rather than a bloated 2,000-line monolith, the main class delegates every functional domain into targeted traits:

```text
Antevemus\ALinq\
├── ALinqCollection.php          # Main facade implementing IALinqCollection (In-Memory Engine)
├── ALinqLazyCollection.php      # Generator-based Streaming Pipeline (O(1) Memory Engine)
├── ALinqQueryBuilder.php        # Fluent condition builder (AND / OR modes) for complex queries
├── Helpers\
│   └── ALinqPropertyAccess.php  # Deprecated facade over antevemus/adeep-property-access (until 3.0) + createPropertyComparer()
├── Interfaces\                  # 11 Segregated Contracts
│   ├── IALinqBaseCollection.php
│   ├── IALinqCollection.php
│   ├── IALinqLazyCollection.php # Streaming collection contract (Generators, files, cursors)
│   ├── IALinqFilterable.php
│   ├── IALinqJoinable.php
│   ├── IALinqAggregatable.php
│   ├── IALinqSelectable.php
│   ├── IALinqGroupable.php
│   ├── IALinqOrderable.php
│   ├── IALinqIterator.php
│   └── IALinqUtility.php
└── Traits\                      # 8 Isolated Trait Implementations
    ├── FilteringOperations.php
    ├── JoiningOperations.php
    ├── AggregationOperations.php
    ├── SelectionOperations.php
    ├── GroupingOperations.php
    ├── OrderingOperations.php
    ├── IteratorOperations.php
    └── UtilityOperations.php
```

Property names and dot-notation paths are resolved by `Antevemus\ADeepPropertyAccess\PropertyAccess` ([`antevemus/adeep-property-access`](https://github.com/antevemus-it/Antevemus.ADeepPropertyAccess)), the accessor shared with ASpecification (see §9).
