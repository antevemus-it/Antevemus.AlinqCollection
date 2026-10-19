# Streaming & Big Data Pipelines (`ALinqLazyCollection`)

### 10. Streaming & Big Data Pipelines (`ALinqLazyCollection`)

Inspired by reactive Node.js streams and .NET `IEnumerable<T>` deferred execution, `ALinqLazyCollection` provides high-throughput, memory-bounded processing using PHP 8.4 generators. 

Processing gigabyte-scale datasets (such as access logs, financial transaction CSVs, or large SQL dumps) with standard in-memory arrays causes fatal memory exhaustion. `ALinqLazyCollection` guarantees **constant $O(1)$ memory consumption (< 100 KB RAM)** regardless of dataset size by evaluating elements strictly on-demand.

#### Key Architectural Guarantees:
- **Rewindable Multi-Pass Traversals**: Unlike standard PHP generators that throw `"Cannot traverse an already closed generator"`, `ALinqLazyCollection` wraps generator factories (`Closure(): iterable`). You can iterate, filter, and materialize the same pipeline multiple times safely.
- **Deterministic Resource Disposal**: Sources like `fromFile()` and `fromCsv()` guarantee file handle closure in `finally` blocks, even during early pipeline termination (e.g. short-circuiting with `first()`, `take(n)`); `fromHttpPages()` cancels the request in flight the same way.
- **Dual Pipeline Integration**: Effortlessly convert between eager collections (`ALinqCollection`) and streaming pipelines (`ALinqLazyCollection`) via `$collection->lazy()` and `$lazy->toCollection()`.

#### Real-World Streaming Example: Multi-Gigabyte Log File

```php
use Antevemus\ALinq\ALinqLazyCollection;

// Stream lines from a 10 GB production server log with < 100 KB RAM footprint
$highSeverityAlerts = ALinqLazyCollection::fromFile('/var/log/app/production.log')
    ->where(fn(string $line) => str_contains($line, '[CRITICAL]'))
    ->select(function (string $line) {
        preg_match('/\[(?<time>[^\]]+)\] \[CRITICAL\] (?<msg>.*)/', $line, $matches);
        return [
            'timestamp' => $matches['time'] ?? 'unknown',
            'message'   => $matches['msg'] ?? trim($line),
        ];
    })
    ->take(50) // Short-circuits immediately: stops reading disk once 50 matches are found!
    ->toArray();
```

#### Real-World CSV Processing with Dynamic Headers

```php
// Automatically maps header line to associative row arrays
$activeEnterpriseUsers = ALinqLazyCollection::fromCsv('/var/data/customers.csv')
    ->where(fn(array $row) => $row['plan'] === 'Enterprise' && $row['status'] === 'active')
    ->select(fn(array $row) => [
        'customerId' => (int)$row['id'],
        'email'      => strtolower(trim($row['email'])),
        'mrr'        => (float)$row['mrr'],
    ])
    ->distinctBy(fn($customer) => $customer['email'])
    ->tap(fn($customer) => syslog(LOG_INFO, "Processing customer {$customer['customerId']}"))
    ->toCollection(); // Materialize filtered results into eager ALinqCollection for further in-memory grouping
```

#### Database Cursor Streaming (Unbuffered PDO)

```php
// Stream millions of database records directly from an unbuffered PDO query
$stmt = $pdo->query('SELECT id, name, salary, department FROM employees');

$topSalariesByDept = ALinqLazyCollection::fromCursor($stmt)
    ->where(fn($row) => (float)$row['salary'] > 50000.0)
    ->take(100)
    ->toCollection()
    ->groupBy(fn($row) => $row['department']);
```

#### Paginated HTTP API with Prefetch (since 1.5.0)

`fromHttpPages()` walks a paginated HTTP API as one lazy sequence of items. As soon as a page is parsed, the request for the next page is started with `curl_multi`, and only then are the items of the current page handed over: while your code processes page *n*, page *n+1* is already downloading. No event loop and no Fibers, in plain PHP-FPM: the effect of `IAsyncEnumerable<T>` with prefetch. Requires `ext-curl` (listed in `suggest`).

```php
use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Http\HttpPageOptions;
use Antevemus\ALinq\Http\HttpPageRequest;
use Antevemus\ALinq\Http\HttpPageResponse;

$api = 'https://api.example.com/orders';
$token = getenv('API_TOKEN');

$paidTotal = ALinqLazyCollection::fromHttpPages(
    // The request of each page; there is no cursor on the first one
    fn(?string $cursor) => HttpPageRequest::get(
        $api . '?limit=100' . ($cursor === null ? '' : '&after=' . urlencode($cursor))
    ),
    // The items of the page and the cursor of the next one (null on the last page)
    function (HttpPageResponse $response): array {
        $page = $response->json();
        return [$page['data'], $page['next']];
    },
    new HttpPageOptions(timeout: 10.0, headers: ['Authorization' => 'Bearer ' . $token]),
)
    ->where(fn(array $order) => $order['status'] === 'paid')
    ->sum(fn(array $order) => $order['total']); // page n+1 downloads while page n is summed
```

- **The two callbacks.** `$requestFor($cursor, $pageIndex)` builds the request of a page: the cursor of the first page is `null`, `$pageIndex` counts from 0, and returning `null` ends the stream. `$parsePage($response)` returns `[items, nextCursor]`, and a `null` cursor marks the last page. A cursor in the body, a page number (`$cursor ?? 1`, next `$page + 1` while `page < total_pages`) and the `Link` header (`[$response->json(), $response->link('next')]`, relative targets resolved) all fit this shape. `HttpPageRequest` and `HttpPageResponse` are plain value objects (no PSR-7): method, http(s) URL, headers, body and timeout; status, headers (`header()`), body (`json()`), effective URL and page index.
- **Errors.** A status outside 2xx, a transport error (TLS included), the timeout or the page limit throw `HttpPageException` at the point where that page would be delivered: the items of the previous pages were already handed over. The message carries the status and the URL without its query string (`HTTP 500 for page index 1: GET https://api.example.com/orders`), never the body. There is no retry in the core.
- **Stopping early** (`take()`, `first()`, `any()`, an exception in your loop) cancels the request in flight: at most one page is downloaded for nothing.
- **Every traversal fetches again from the first page** (`count()` followed by `toArray()` walks the API twice). Call `remember()` to walk it once and replay from memory.
- **`HttpPageOptions`**: `timeout` (30 s by default: the longest the iteration blocks waiting for one page; the time your code spends on the previous page does not count), `maxPages` (10 000 by default, a guard against a cursor that never ends), `headers` (sent with every request; a request header with the same name wins) and `followRedirects` (`false` by default). TLS verification is always on and cannot be turned off.
- **Prefetch depth 1**: the next request needs the cursor of the previous response. On the local test server, 3 pages of 300 ms with a consumer that spends 300 ms per page take 1.2 s instead of 1.8 s (`tests/Unit/Http/FromHttpPagesTest.php`).

#### Caching Intermediate Streams with `remember()`

If an upstream data stream is computationally expensive or involves external I/O, calling `remember()` caches evaluated elements so subsequent iterations pull from memory without re-executing upstream generators:

```php
$cachedStream = ALinqLazyCollection::from(function () {
    // Heavy generator or network stream
    yield from fetchRemoteMetrics();
})->remember();

$totalCount = $cachedStream->count(); // Generator runs once
$average    = $cachedStream->average(); // Reads from local memory cache, no generator rerun
```

#### Ordering and outer joins accumulate data (since 1.4.0)

The lazy collection has `orderBy()`/`orderByDescending()` with `thenBy()`/`thenByDescending()`, and the three outer joins, with the same answers as the eager side. They stay deferred (nothing runs until the first traversal), but they are **not** constant-memory:

- `orderBy*()`/`thenBy*()` buffer the whole upstream when traversed, then sort once with every criterion;
- `leftJoin()`/`fullJoin()` buffer and index the inner side and stream the lazy collection;
- `rightJoin()` buffers and indexes the lazy collection itself and streams the inner side (the result follows the inner order).

```php
$sorted = ALinqLazyCollection::range(1, 6)
    ->orderBy(fn($n) => $n % 3)          // deferred: nothing runs yet
    ->thenByDescending(fn($n) => $n)
    ->toArray();                          // [6, 3, 4, 1, 5, 2]
```

#### Grouping and dictionaries accumulate data (since 2.2.0)

The lazy collection has `groupBy()`, `countBy()` and `aggregateBy()` as **deferred stages**, like `orderBy()`: nothing runs until the first traversal, and each traversal reads the upstream once. They are not all equal in memory:

- `groupBy()` keeps every item (O(n)) and yields `key => ALinqCollection`;
- `countBy()` keeps **one integer per key** and `aggregateBy()` one accumulator per key (O(keys)): `fromCursor($stmt)->countBy(...)` counts millions of rows without holding them;
- `toDictionary()` and `toLookup()` are terminal: one pass (`toLookup()` keeps every element);
- `exceptByKeys()`/`intersectByKeys()` read the keys first, then stream, remembering the keys already emitted;
- `implode()` streams (the memory is the text it builds).

The stages re-read the upstream on every traversal, as `orderBy()` does: over `fromCursor()` the second traversal throws; call `remember()` first. Their result is a dictionary keyed by the group key, and operators after them keep those keys (an eager `groupBy()` whose keys happen to be `0, 1, 2…` is a list, which a following `where()` reindexes). The `$position` that `aggregateBy()` hands to `$func` is the position of the item **inside its group** (0, 1, …), on both collections, as the eager `aggregateBy()` always did; every group starts from the same `$seed`.

<!-- tested: 10-grouping -->
```php
use Antevemus\ALinq\ALinqLazyCollection;

$orders = ALinqLazyCollection::from([
    ['id' => 1, 'status' => 'paid', 'total' => 10],
    ['id' => 2, 'status' => 'open', 'total' => 25],
    ['id' => 3, 'status' => 'paid', 'total' => 5],
]);

$counts = $orders->countBy(fn($o) => $o['status']); // deferred: nothing runs yet
$counts->toArray(); // ['paid' => 2, 'open' => 1]
$orders->aggregateBy(fn($o) => $o['status'], 0, fn($sum, $o) => $sum + $o['total'])->toArray(); // ['paid' => 15, 'open' => 25]
$orders->aggregateBy(fn($o) => $o['status'], '', fn($trace, $o, $position) => $trace . $position)->toArray(); // ['paid' => '01', 'open' => '0']
$orders->groupBy(fn($o) => $o['status'])->select(fn($group) => $group->sum(fn($o) => $o['total']))->toArray(); // ['paid' => 15, 'open' => 25]
$orders->toLookup(fn($o) => $o['status'], fn($o) => $o['id'])->get('paid')->toArray(); // [1, 3]
$orders->implode(', ', fn($o) => '#' . $o['id']); // '#1, #2, #3'
```

#### Irregular input (since 1.3.2)

Irregular input fails loudly or is an explicit choice, never a silent reshape:

- `fromCsv()` with a header is **strict by default**: a record whose number of fields differs from the header throws `RuntimeException('CSV row N has K fields, header has H')` when it is read (the previous records were already delivered; `N` counts data records from 1, blank lines excluded). `fromCsv($path, strict: false)` delivers that record as a positional list (the 1.3.1 behaviour, for dirty files). Without a header there is no reference width.
- A blank line is not a record: it is skipped in every mode.
- A UTF-8 BOM at the start of the file is removed before parsing in `fromCsv()` (a quoted first field stays correct) and from the first line in `fromFile()`.
- A directory passed to `fromFile()`/`fromCsv()` throws `RuntimeException('Path is a directory, not a file: "<path>"')` on the first traversal.
- One statement, one collection: a second `fromCursor()` (or `from()`) over the same `PDOStatement` throws `RuntimeException('PDOStatement already bound to another lazy collection')` on its first traversal. To iterate again use `remember()`; to run the query again use a new statement, prepared statements included.

```php
ALinqLazyCollection::fromCsv('clients.csv')->toArray();                // width mismatch → RuntimeException("CSV row 2 has 1 fields, header has 2")
ALinqLazyCollection::fromCsv('clients.csv', strict: false)->toArray(); // 1.3.1 behaviour: the irregular record comes as a list
```

#### Detecting the CSV separator (since 2.2.0)

`fromCsv($path, separator: null)` detects the separator on every traversal, after the BOM, from the first record that is not blank, read with the enclosure rules (a separator between quotes does not count, and a quoted field may span lines), up to 64 KiB. The candidates are `,` `;` tab `|`, and the one that appears **most often outside quotes** wins. A tie between the most frequent throws `RuntimeException('Cannot detect the CSV separator of "<path>": "," and ";" both appear N times in the first record; pass $separator')`; a record with none of them is read with `,` (one field reads the same with any separator); a first record longer than 64 KiB or a stream that cannot seek throws. The default is still `','`: nobody detects without asking.

<!-- tested: 10-csv -->
```php
use Antevemus\ALinq\ALinqLazyCollection;

$path = tempnam(sys_get_temp_dir(), 'index');
file_put_contents($path, "date;value\n2026-01;0,52\n2026-02;0,61\n");

ALinqLazyCollection::fromCsv($path, separator: null)->toArray(); // [['date' => '2026-01', 'value' => '0,52'], ['date' => '2026-02', 'value' => '0,61']]
ALinqLazyCollection::fromCsv($path, ';')->toArray() === ALinqLazyCollection::fromCsv($path, separator: null)->toArray(); // true

unlink($path);
```
