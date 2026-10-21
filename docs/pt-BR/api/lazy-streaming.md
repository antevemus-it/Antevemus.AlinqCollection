# Pipelines de Streaming & Big Data (`ALinqLazyCollection`)

### 10. Pipelines de Streaming & Big Data (`ALinqLazyCollection`)

Inspirado nos reactive streams do Node.js e no modelo de execução postergada `IEnumerable<T>` do .NET LINQ, o `ALinqLazyCollection` oferece processamento de alto rendimento e consumo estritamente controlado de memória utilizando Generators do PHP 8.4.

Processar conjuntos de dados de escala em gigabytes (como logs de acesso, CSVs de transações financeiras ou dumps de banco de dados) com arrays comuns em memória resulta em estouro fatal do limite de memória (`Allowed memory size exhausted`). O `ALinqLazyCollection` garante **consumo de memória constante $O(1)$ (< 100 KB de RAM)** independentemente do volume total de dados, avaliando os elementos sob demanda sob o padrão *pull-based*.

#### Garantias Arquiteturais:
- **Navegação Multi-Pass Rebobinável (Rewindable)**: Ao contrário dos generators padrão do PHP que disparam `Cannot traverse an already closed generator` em uma segunda iteração, o `ALinqLazyCollection` encapsula fábricas de geradores (`Closure(): iterable`). Você pode iterar, filtrar e materializar o mesmo pipeline múltiplas vezes com total segurança.
- **Descarte Determinístico de Recursos**: Fontes como `fromFile()` e `fromCsv()` garantem o fechamento do ponteiro do arquivo no bloco `finally`, mesmo se a iteração for interrompida precocemente (ex: curto-circuito com `first()`, `take(n)`); `fromHttpPages()` e `fromHttpPagesByIndex()` cancelam do mesmo jeito as requisições em andamento.
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

`fromHttpPages()` percorre uma API HTTP paginada como uma única sequência lazy de itens. Assim que uma página é interpretada, a requisição da próxima é iniciada com `curl_multi`, e só então os itens da página atual são entregues: enquanto o seu código processa a página *n*, a página *n+1* já está sendo baixada. Sem event loop e sem Fibers, em PHP-FPM comum: o efeito do `IAsyncEnumerable<T>` com prefetch. Requer `ext-curl` (listada em `suggest`). Para I/O por item contra clientes que já são não bloqueantes (o cliente HTTP do Amp, SDKs assíncronos), o pacote satélite [`antevemus/alinq-revolt`](https://github.com/antevemus-it/Antevemus.AlinqRevolt) acrescenta `selectAsync()`, `whereAsync()`, `selectManyAsync()` e `eachAsync()` sobre Fibers e o event loop do Revolt, com uma janela ordenada e limitada de itens em voo. Desde a 2.3.0, `ALinqLazyCollection::from($factory, isList: true|false)` declara o formato do fluxo de uma factory geradora (uma lista, ou um dicionário cujas chaves o `toArray()` mantém, chaves inteiras incluídas), que o satélite usa para construir seus estágios; sem ele o formato de um generator é desconhecido e `toArray()` é uma lista sempre que toda chave é inteira; com um array ou outro iterável o parâmetro é recusado, já que o formato deles vem da fonte; e `isList()` responde o formato que uma coleção preguiçosa conhece (`true`, `false`, ou `null` quando desconhecido), para que um invólucro possa repassá-lo.

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
- **Prefetch de profundidade 1**: a próxima requisição depende do cursor da resposta anterior. No servidor local dos testes, 3 páginas de 300 ms com um consumidor que gasta 300 ms por página levam 1,2 s em vez de 1,8 s (`tests/Unit/Http/FromHttpPagesTest.php`). Quando uma página é endereçada só pelo índice, `fromHttpPagesByIndex()` mantém várias páginas em voo (abaixo).

#### Páginas endereçadas pelo índice (desde a 2.4.0)

Quando a URL da página *n* pode ser escrita sem a resposta da página *n − 1* (`?page=N`, `?offset=N`), `fromHttpPagesByIndex()` mantém várias requisições em voo num único handle `curl_multi`: a página sendo consumida e até `$prefetch` páginas depois dela (2 por padrão). Os mesmos requisitos do `fromHttpPages()`: `ext-curl`, sem event loop.

```php
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Http\HttpPageOptions;
use Antevemus\ALinq\Http\HttpPageResponse;

$api = 'https://api.example.com/invoices';
$token = getenv('API_TOKEN');

$openTotal = ALinqLazyCollection::fromHttpPagesByIndex(
    // A URL da página 0, 1, 2, ...: montada só pelo índice
    fn(int $page) => $api . '?per=100&page=' . ($page + 1),
    // Os itens da página; uma página sem itens encerra o fluxo
    fn(HttpPageResponse $response) => $response->json()['data'],
    prefetch: 3, // as páginas n+1 a n+3 baixam enquanto a página n é consumida
    until: fn(int $page, HttpPageResponse $response) => $page + 1 >= $response->json()['total_pages'],
    options: new HttpPageOptions(timeout: 10.0, headers: ['Authorization' => 'Bearer ' . $token]),
)
    ->where(fn(array $invoice) => $invoice['status'] === 'open')
    ->sum(fn(array $invoice) => $invoice['total']);
```

- **Os callbacks.** `$requestFor($pageIndex)` devolve a requisição de uma página pelo índice (0, 1, 2, …): uma URL http(s) absoluta (um GET) ou um `HttpPageRequest`; `null` encerra o fluxo antes dessa página. `$parsePage($response)` devolve os itens da página, qualquer iterável.
- **O fim.** O fluxo termina depois da primeira página **sem itens**, ou depois da página para a qual `$until($pageIndex, $response)` responde `true` (os itens dela são entregues). Passe `$until` quando a API informa o número de páginas, ou responde um erro em vez de uma página vazia depois da última.
- **Parar** (uma página vazia, `$until`, `take()`, `first()`, `any()`, uma exceção no seu laço) cancela toda requisição ainda em voo: no máximo `$prefetch` páginas são baixadas à toa.
- **Erros, timeout, opções e nova iteração** se comportam como no `fromHttpPages()` (toda travessia recomeça da página 0; `remember()` percorre uma vez só). `maxPages` conta as páginas requisitadas: quando essa quantidade veio com itens, nenhuma encerrou o fluxo e `$requestFor` ainda devolve uma requisição para o próximo índice, `HttpPageException` (`isPageLimit()`) é lançada depois dos itens da última página permitida.
- **O ganho**: no servidor local dos testes, 4 páginas de 600 ms com um consumidor que gasta 200 ms por página levam cerca de 1,4 s com `prefetch: 3`, contra 2,6 s com uma página à frente (`tests/Unit/Http/FromHttpPagesByIndexTest.php`).

#### Outros event loops

O gancho do formato, `from($factory, isList: ...)` e `isList()`, não conhece event loop: nenhuma classe deste pacote usa Revolt, Amp ou Fibers, e nenhum deles é exigido. O [`antevemus/alinq-revolt`](https://github.com/antevemus-it/Antevemus.AlinqRevolt) é o pacote satélite para Revolt e Amp, construído sobre esse gancho. A página [Arquitetura](https://github.com/antevemus-it/Antevemus.AlinqRevolt/blob/main/docs/pt-BR/internals/architecture.md) dele explica por que ele roda sobre o Revolt e o Amp, e o que um satélite para outro event loop (ReactPHP, ...) implementa: um invólucro sobre a fonte, operadores que devolvem `ALinqLazyCollection::from($factory, $isList)` com o formato declarado, e a janela ordenada, limitada e preguiçosa com cancelamento na parada antecipada. Um pacote desses não exige mudança aqui.

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
- `leftJoin()`/`fullJoin()` acumulam e indexam o lado inner e fazem streaming da coleção lazy, e `join()`/`groupJoin()` (2.4.0) também;
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

#### Junções internas e comparadores no lado lazy (desde a 2.4.0)

`join()` e `groupJoin()` existem também na coleção lazy, com as respostas do lado eager: estágios postergados que leem o lado inner uma vez por travessia para uma tabela hash e fazem streaming da coleção lazy (uma fonte sem fim funciona com `take()`), um resultado por casamento na ordem do outer e, dentro dele, na ordem do inner; o resultado é uma lista. Toda junção, `groupBy()`, `countBy()`, `aggregateBy()`, `toDictionary()`, `toLookup()`, `exceptByKeys()` e `intersectByKeys()` aceitam um `IALinqEqualityComparer` como último parâmetro, como no lado eager ([Agrupamento](grouping.md), [Junções](joining.md)); `aggregateBy()` aceita também um seletor de semente `fn($key)`.

<!-- tested: 10-joins -->
```php
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Comparison\StringComparer;

$lines = ALinqLazyCollection::from(function () {
    yield ['sku' => 'a-1', 'qty' => 2];
    yield ['sku' => 'B-2', 'qty' => 1];
    yield ['sku' => 'A-1', 'qty' => 5];
});
$products = [['sku' => 'A-1', 'name' => 'Pen'], ['sku' => 'b-2', 'name' => 'Ink']];
$sku = fn($row) => $row['sku'];

$joined = $lines->join($products, $sku, $sku, fn($line, $product) => $product['name'] . ' x' . $line['qty'], StringComparer::ordinalIgnoreCase()); // postergado: nada roda ainda
$joined->toArray(); // ['Pen x2', 'Ink x1', 'Pen x5']
$lines->join($products, $sku, $sku, fn($line, $product) => $line['qty'], StringComparer::ordinalIgnoreCase())->sum(); // 8
$lines->groupJoin($products, $sku, $sku, fn($line, $matches) => $matches->count())->toArray(); // [0, 0, 1]
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
