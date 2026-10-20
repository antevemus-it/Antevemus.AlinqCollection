# Pipelines de Streaming & Big Data (`ALinqLazyCollection`)

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
$highSeverityAlerts = ALinqLazyCollection::fromFile('/var/log/app/production.log')
    ->where(fn(string $line) => str_contains($line, '[CRITICAL]'))
    ->select(function (string $line) {
        preg_match('/\[(?<time>[^\]]+)\] \[CRITICAL\] (?<msg>.*)/', $line, $matches);
        return [
            'timestamp' => $matches['time'] ?? 'unknown',
            'message'   => $matches['msg'] ?? trim($line),
        ];
    })
    ->take(50) // Interrompe imediatamente: para de ler o disco assim que encontrar os primeiros 50 registros!
    ->toArray();
```

#### Processamento de Arquivos CSV com Cabeçalhos Dinâmicos

```php
// Mapeia automaticamente o cabeçalho para arrays associativos em cada linha
$activeEnterpriseUsers = ALinqLazyCollection::fromCsv('/var/data/customers.csv')
    ->where(fn(array $row) => $row['plan'] === 'Enterprise' && $row['status'] === 'active')
    ->select(fn(array $row) => [
        'customerId' => (int)$row['id'],
        'email'      => strtolower(trim($row['email'])),
        'mrr'        => (float)$row['mrr'],
    ])
    ->distinctBy(fn($customer) => $customer['email'])
    ->tap(fn($customer) => syslog(LOG_INFO, "Processing customer {$customer['customerId']}"))
    ->toCollection(); // Converte o resultado filtrado para ALinqCollection e prossegue com agrupamentos em memória
```

#### Streaming de Cursor de Banco de Dados (PDO Não Bufferizado)

```php
// Faz o streaming de milhões de registros diretamente de uma consulta PDO não bufferizada
$stmt = $pdo->query('SELECT id, name, salary, department FROM employees');

$topSalariesByDept = ALinqLazyCollection::fromCursor($stmt)
    ->where(fn($row) => (float)$row['salary'] > 50000.0)
    ->take(100)
    ->toCollection()
    ->groupBy(fn($row) => $row['department']);
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
$cachedStream = ALinqLazyCollection::from(function () {
    // Generator pesado ou chamada de API externa
    yield from fetchRemoteMetrics();
})->remember();

$totalCount = $cachedStream->count(); // O generator roda uma única vez
$average    = $cachedStream->average(); // Lê do cache local em memória, sem reexecutar o generator
```

#### Ordenação e junções externas acumulam dados (desde a 1.4.0)

A coleção lazy tem `orderBy()`/`orderByDescending()` com `thenBy()`/`thenByDescending()`, e as três junções externas, com as mesmas respostas do lado eager. Continuam postergadas (nada roda até a primeira travessia), mas **não** têm memória constante:

- `orderBy*()`/`thenBy*()` acumulam todo o upstream ao serem percorridos e ordenam uma vez com todos os critérios;
- `leftJoin()`/`fullJoin()` acumulam e indexam o lado inner e fazem streaming da coleção lazy;
- `rightJoin()` acumula e indexa a própria coleção lazy e faz streaming do inner (o resultado segue a ordem do inner).

```php
$sorted = ALinqLazyCollection::range(1, 6)
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
ALinqLazyCollection::fromCsv('clients.csv')->toArray();                // largura diferente → RuntimeException("CSV row 2 has 1 fields, header has 2")
ALinqLazyCollection::fromCsv('clients.csv', strict: false)->toArray(); // comportamento 1.3.1: o registro irregular vem como lista
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
