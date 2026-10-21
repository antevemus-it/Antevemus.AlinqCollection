<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit\Http;

use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Http\HttpPageException;
use Antevemus\ALinq\Http\HttpPageOptions;
use Antevemus\ALinq\Http\HttpPageRequest;
use Antevemus\ALinq\Http\HttpPageResponse;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

/**
 * FromHttpPagesByIndexTest - ALinqLazyCollection::fromHttpPagesByIndex() (2.4.0) against the local
 * `php -S` server of the fromHttpPages() tests (the /indexed endpoint: order rows, 1-based `page`,
 * an empty page after the last one)
 *
 * The end of the stream (an empty page, `$until`, a null request), the prefetch depth (the requests in
 * flight, measured on the server, and the overlap, measured on the clock), several transfers completing
 * out of order on one multi handle, early termination cancelling every request in flight, errors and
 * the page limit at the point of delivery, the callback shapes, re-iteration and the documentation
 * example.
 *
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq.tests
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
#[Group('http')]
final class FromHttpPagesByIndexTest extends TestCase
{
    private static ?LocalHttpServer $server = null;

    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required by fromHttpPagesByIndex().');
        }
        self::$server = LocalHttpServer::start(__DIR__ . '/router.php');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
    }

    private static function server(): LocalHttpServer
    {
        return self::$server ?? throw new RuntimeException('The local HTTP server is not running.');
    }

    private static function newRun(): string
    {
        return 'r' . bin2hex(random_bytes(6));
    }

    /**
     * A source over /indexed: the URL of page $page is built from the index alone; the ids of the
     * order rows are the items.
     *
     * @param array<string, scalar> $query
     */
    private static function indexedSource(array $query, int $prefetch = 2, ?callable $until = null, ?HttpPageOptions $options = null): ALinqLazyCollection
    {
        return ALinqLazyCollection::fromHttpPagesByIndex(
            fn(int $page) => self::server()->url('/indexed', $query + ['page' => $page + 1]),
            fn(HttpPageResponse $response) => array_column($response->json()['data'], 'id'),
            $prefetch,
            $until,
            $options
        );
    }

    /**
     * The 0-based page indexes the server received for a run, sorted.
     *
     * @return list<int>
     */
    private static function requestedPages(string $run): array
    {
        $pages = [];
        foreach (self::server()->log($run) as $line) {
            if (str_starts_with($line, 'request /indexed ')) {
                $pages[] = (int) substr($line, strlen('request /indexed '));
            }
        }
        sort($pages);

        return $pages;
    }

    private static function waitForLog(string $run, string $line): void
    {
        $deadline = microtime(true) + 3.0;
        while (!in_array($line, self::server()->log($run), true) && microtime(true) < $deadline) {
            usleep(20_000);
        }
    }

    // ------------------------------------------------------------------ the end of the stream

    public function testAnEmptyPageEndsTheStreamAndAtMostPrefetchPagesAreRequestedForNothing(): void
    {
        $run = self::newRun();
        $lazy = self::indexedSource(['run' => $run, 'pages' => 3, 'per' => 2]);

        $this->assertInstanceOf(IALinqLazyCollection::class, $lazy);
        $this->assertTrue($lazy->isList());
        $this->assertSame([], self::server()->log($run), 'Nothing is requested before the first traversal.');
        $this->assertSame([1, 2, 3, 4, 5, 6], $lazy->toArray(), 'The items of pages 0 to 2, keys renumbered across pages.');

        // Pages 0-2 have items, page 3 is empty and ends the stream; pages 4 and 5 were in flight
        // (prefetch 2) and are cancelled: at most $prefetch pages requested for nothing.
        $requested = self::requestedPages($run);
        $this->assertSame([0, 1, 2, 3], array_slice($requested, 0, 4));
        $this->assertLessThanOrEqual(3 + 1 + 2, count($requested));
        $this->assertSame(array_unique($requested), $requested, 'No page is requested twice.');
    }

    public function testUntilEndsTheStreamAfterThePageItAnswersTrueFor(): void
    {
        $run = self::newRun();
        $calls = [];
        $lazy = self::indexedSource(
            ['run' => $run, 'pages' => 5, 'per' => 2],
            1,
            function (int $page, HttpPageResponse $response) use (&$calls): bool {
                $calls[] = [$page, $response->pageIndex, $response->json()['page']];
                return $page + 1 >= 3;
            }
        );

        $this->assertSame([1, 2, 3, 4, 5, 6], $lazy->toArray(), 'The items of page 2 are delivered, nothing after it.');
        $this->assertSame([[0, 0, 1], [1, 1, 2], [2, 2, 3]], $calls, '$until sees each page once, in order.');
        $this->assertNotContains(4, self::requestedPages($run), 'With prefetch 1, page 4 is never requested.');
    }

    public function testUntilSeesTheLastPageOfTheApiAndNoEmptyPageIsNeeded(): void
    {
        $run = self::newRun();
        $lazy = self::indexedSource(
            ['run' => $run, 'pages' => 2, 'per' => 3],
            1,
            fn(int $page, HttpPageResponse $response): bool => $page + 1 >= $response->json()['total_pages']
        );

        $this->assertSame([1, 2, 3, 4, 5, 6], $lazy->toArray());
        // Page 2 was in flight (prefetch 1) when page 1 said it is the last one: it was cancelled,
        // and may or may not have reached the server by then.
        $requested = self::requestedPages($run);
        $this->assertSame([0, 1], array_slice($requested, 0, 2));
        $this->assertLessThanOrEqual(3, count($requested));
    }

    public function testANullRequestEndsTheStreamBeforeThatPage(): void
    {
        $run = self::newRun();
        $indexes = [];
        $lazy = ALinqLazyCollection::fromHttpPagesByIndex(
            function (int $page) use ($run, &$indexes): ?string {
                $indexes[] = $page;
                return $page < 2 ? self::server()->url('/indexed', ['run' => $run, 'pages' => 9, 'per' => 1, 'page' => $page + 1]) : null;
            },
            fn(HttpPageResponse $response) => array_column($response->json()['data'], 'id'),
        );

        $this->assertSame([1, 2], $lazy->toArray());
        $this->assertSame([0, 1, 2], $indexes, '$requestFor runs once per index, in order, until it answers null.');
        $this->assertSame([0, 1], self::requestedPages($run));
        $this->assertSame([], ALinqLazyCollection::fromHttpPagesByIndex(fn() => null, fn() => [1])->toArray(), 'No first page: an empty stream.');
    }

    // ------------------------------------------------------------------ prefetch depth

    /**
     * @return array<string, array{int}>
     */
    public static function depths(): array
    {
        return ['prefetch 1' => [1], 'prefetch 2 (default)' => [2], 'prefetch 4' => [4]];
    }

    /**
     * While the first item is in the consumer's hands, the page being consumed and $prefetch pages
     * after it have been requested, no more.
     */
    #[DataProvider('depths')]
    public function testPrefetchPagesAreInFlightWhileAPageIsConsumed(int $prefetch): void
    {
        $run = self::newRun();
        $lazy = self::indexedSource(['run' => $run, 'pages' => 10, 'per' => 2], $prefetch);

        foreach ($lazy as $item) {
            $this->assertSame(1, $item);
            usleep(300_000); // the server logs every request that is on the wire
            $this->assertSame(range(0, $prefetch), self::requestedPages($run), "Page 0 and $prefetch pages after it, no more.");
            break;
        }
    }

    /**
     * 4 pages of 600 ms each and a consumer that spends 200 ms per page. One page at a time ahead
     * (the depth of fromHttpPages()) takes about 4 × 600 + 200 = 2.6 s; with every page in flight
     * from the start (prefetch 3) it is about 600 + 4 × 200 = 1.4 s. The bound sits between (2.0 s).
     */
    public function testThePrefetchOverlapsSeveralDownloadsWithTheConsumer(): void
    {
        $run = self::newRun();
        $lazy = self::indexedSource(['run' => $run, 'pages' => 4, 'per' => 2, 'delay' => 600, 'delayTo' => 3], 3);

        $start = hrtime(true);
        $items = $lazy->tap(static fn() => usleep(100_000))->toArray(); // 2 items × 100 ms = 200 ms per page
        $elapsed = (hrtime(true) - $start) / 1e9;

        $this->assertSame(range(1, 8), $items);
        $this->assertGreaterThan(1.35, $elapsed, 'The delays really happened.');
        $this->assertLessThan(2.0, $elapsed, sprintf('With 4 pages in flight the total is near 1.4 s (one ahead: 2.6 s); measured %.3f s.', $elapsed));
    }

    /**
     * Page 0 is slow, pages 1 and 2 answer at once: their completion is read from the multi handle
     * while page 0 is awaited, and must still reach their own transfers (otherwise page 1 would wait
     * for a message already consumed, until the timeout).
     */
    public function testPagesThatCompleteOutOfOrderAreDeliveredInOrder(): void
    {
        $run = self::newRun();
        $lazy = self::indexedSource(['run' => $run, 'pages' => 3, 'per' => 2, 'delay' => 400, 'delayTo' => 0], 3, null, new HttpPageOptions(timeout: 2.0));

        $start = hrtime(true);
        $this->assertSame([1, 2, 3, 4, 5, 6], $lazy->toArray());
        $this->assertLessThan(1.5, (hrtime(true) - $start) / 1e9, 'Nothing waited for a completion message that was already read.');
    }

    // ------------------------------------------------------------------ early termination

    /**
     * @return array<string, array{0: callable(ALinqLazyCollection): mixed, 1: mixed}>
     */
    public static function earlyTerminations(): array
    {
        return [
            'take(3)' => [static fn(ALinqLazyCollection $c) => $c->take(3)->toArray(), [1, 2, 3]],
            'first()' => [static fn(ALinqLazyCollection $c) => $c->first(), 1],
            'any()' => [static fn(ALinqLazyCollection $c) => $c->any(), true],
        ];
    }

    /**
     * Stopping inside page 0 cancels both pages in flight: the server sees the client go away while
     * pages 1 and 2 are still being produced, and nothing waits for them.
     */
    #[DataProvider('earlyTerminations')]
    public function testStoppingEarlyCancelsEveryRequestInFlight(callable $terminate, mixed $expected): void
    {
        $run = self::newRun();
        $lazy = self::indexedSource(['run' => $run, 'pages' => 5, 'per' => 10, 'delay' => 1500, 'delayFrom' => 1, 'watch' => 1], 2);

        $start = hrtime(true);
        $this->assertSame($expected, $terminate($lazy));
        $this->assertLessThan(1.0, (hrtime(true) - $start) / 1e9, 'Nothing waits for pages 1 and 2.');

        self::waitForLog($run, 'aborted /indexed 1');
        self::waitForLog($run, 'aborted /indexed 2');
        $log = self::server()->log($run);
        sort($log);
        $this->assertSame(['aborted /indexed 1', 'aborted /indexed 2', 'request /indexed 0', 'request /indexed 1', 'request /indexed 2'], $log);
    }

    public function testUntilCancelsThePagesInFlightAtOnce(): void
    {
        $run = self::newRun();
        $lazy = self::indexedSource(['run' => $run, 'pages' => 5, 'per' => 2, 'delay' => 1500, 'delayFrom' => 1, 'watch' => 1], 2, fn(int $page) => true);

        $start = hrtime(true);
        $this->assertSame([1, 2], $lazy->toArray());
        $this->assertLessThan(1.0, (hrtime(true) - $start) / 1e9);
        self::waitForLog($run, 'aborted /indexed 1');
        self::waitForLog($run, 'aborted /indexed 2');
        $this->assertContains('aborted /indexed 2', self::server()->log($run));
    }

    // ------------------------------------------------------------------ errors and the page limit

    public function testAFailingPageThrowsAfterTheItemsOfThePreviousPages(): void
    {
        $run = self::newRun();
        $delivered = [];

        try {
            foreach (self::indexedSource(['run' => $run, 'pages' => 4, 'per' => 2, 'fail' => 1]) as $item) {
                $delivered[] = $item;
            }
            $this->fail('Page 1 answers 500.');
        } catch (HttpPageException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertSame(1, $e->getPageIndex());
            $this->assertStringNotContainsString('run=', $e->getMessage(), 'No query string in the message.');
            $this->assertStringNotContainsString('SECRET', $e->getMessage());
        }
        $this->assertSame([1, 2], $delivered);
    }

    public function testThePageLimitThrowsAfterTheLastAllowedPage(): void
    {
        $run = self::newRun();
        $delivered = [];

        try {
            foreach (self::indexedSource(['run' => $run, 'pages' => 5, 'per' => 1], 2, null, new HttpPageOptions(maxPages: 2)) as $item) {
                $delivered[] = $item;
            }
            $this->fail('A third page would be needed.');
        } catch (HttpPageException $e) {
            $this->assertTrue($e->isPageLimit());
            $this->assertSame(2, $e->getPageIndex());
            $this->assertStringStartsWith('Page limit reached: 2 pages were fetched and the API still points to page index 2 (GET http://127.0.0.1:', $e->getMessage());
        }
        $this->assertSame([1, 2], $delivered);
        $this->assertSame([0, 1], self::requestedPages($run), 'Never more than maxPages requests.');

        // An empty page inside the limit ends the stream normally; $until on the last allowed page too.
        $this->assertSame([1, 2], self::indexedSource(['run' => self::newRun(), 'pages' => 2, 'per' => 1], 2, null, new HttpPageOptions(maxPages: 3))->toArray());
        $this->assertSame([1, 2], self::indexedSource(['run' => self::newRun(), 'pages' => 5, 'per' => 1], 2, fn(int $page) => $page === 1, new HttpPageOptions(maxPages: 2))->toArray());
        // A null request where the limit would be reached ends the stream normally.
        $this->assertSame([1, 2], ALinqLazyCollection::fromHttpPagesByIndex(
            fn(int $page) => $page < 2 ? self::server()->url('/indexed', ['run' => self::newRun(), 'pages' => 5, 'per' => 1, 'page' => $page + 1]) : null,
            fn(HttpPageResponse $response) => array_column($response->json()['data'], 'id'),
            options: new HttpPageOptions(maxPages: 2)
        )->toArray());
    }

    // ------------------------------------------------------------------ callback shapes

    public function testThePrefetchMustBeAtLeastOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('fromHttpPagesByIndex() expects prefetch to be at least 1, 0 given');
        ALinqLazyCollection::fromHttpPagesByIndex(fn() => null, fn() => [], 0);
    }

    public function testCallbacksOfTheWrongShapeAreReported(): void
    {
        $url = self::server()->url('/indexed', ['run' => self::newRun(), 'pages' => 1, 'per' => 1]);

        try {
            ALinqLazyCollection::fromHttpPagesByIndex(fn() => 42, fn() => [])->toArray();
            $this->fail('An int is not a request.');
        } catch (UnexpectedValueException $e) {
            $this->assertSame('fromHttpPagesByIndex(): $requestFor must return an HttpPageRequest, a URL or null, int given for page index 0.', $e->getMessage());
        }

        try {
            ALinqLazyCollection::fromHttpPagesByIndex(fn() => $url, fn() => 'items')->toArray();
            $this->fail('A string is not a page of items.');
        } catch (UnexpectedValueException $e) {
            $this->assertSame('fromHttpPagesByIndex(): $parsePage must return the iterable items of the page, string given for page index 0.', $e->getMessage());
        }

        try {
            ALinqLazyCollection::fromHttpPagesByIndex(fn() => $url, fn() => [1], 1, fn() => 1)->toArray();
            $this->fail('1 is not a bool.');
        } catch (UnexpectedValueException $e) {
            $this->assertSame('fromHttpPagesByIndex(): $until must return a bool, int given for page index 0.', $e->getMessage());
        }

        try {
            ALinqLazyCollection::fromHttpPagesByIndex(fn() => 'file:///etc/passwd', fn() => [])->toArray();
            $this->fail('Only http(s) URLs.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringStartsWith('HttpPageRequest requires an absolute http or https URL', $e->getMessage());
        }

        // An HttpPageRequest, a generator of items and callables that are not Closures are fine.
        $parser = new class {
            public function items(HttpPageResponse $response): \Generator
            {
                yield from array_column($response->json()['data'], 'id');
            }

            public function last(int $page, HttpPageResponse $response): bool
            {
                return true;
            }
        };
        $lazy = ALinqLazyCollection::fromHttpPagesByIndex(
            fn(int $page) => HttpPageRequest::get($url, ['X-Trace' => 'p' . $page]),
            [$parser, 'items'],
            2,
            [$parser, 'last']
        );
        $this->assertSame([1], $lazy->toArray());
    }

    // ------------------------------------------------------------------ re-iteration

    public function testEveryTraversalFetchesAgainFromPageZeroAndRememberDoesNot(): void
    {
        $run = self::newRun();
        $lazy = self::indexedSource(['run' => $run, 'pages' => 2, 'per' => 2], 1, fn(int $page) => $page === 1);

        $this->assertSame(4, $lazy->count());
        $this->assertSame([1, 2, 3, 4], $lazy->toArray());
        $counts = array_count_values(self::requestedPages($run));
        $this->assertSame([2, 2], [$counts[0] ?? 0, $counts[1] ?? 0], 'Two traversals, pages 0 and 1 each (page 2, cancelled in flight, aside).');

        $run = self::newRun();
        $remembered = self::indexedSource(['run' => $run, 'pages' => 2, 'per' => 2], 1, fn(int $page) => $page === 1)->remember();
        $this->assertSame(4, $remembered->count());
        $this->assertSame([1, 2, 3, 4], $remembered->toArray());
        $counts = array_count_values(self::requestedPages($run));
        $this->assertSame([1, 1], [$counts[0] ?? 0, $counts[1] ?? 0], 'remember() traverses once and replays from memory.');
    }

    // ----------------------------------------------------------- documentation example

    /**
     * docs/en/api/lazy-streaming.md "Pages addressed by their index" (EN and pt-BR, identical code): the
     * block as written, with the host of the example API pointed at the local server.
     */
    public function testTheDocumentationExampleRunsAsWritten(): void
    {
        // ---- documentation block; only these two lines differ (local server instead of the example API) ----
        $api = self::server()->url('/indexed');
        $token = 'docs-token';

        $openTotal = ALinqLazyCollection::fromHttpPagesByIndex(
            // The URL of page 0, 1, 2, ...: built from the index alone
            fn(int $page) => $api . '?per=100&page=' . ($page + 1),
            // The items of the page; a page without items ends the stream
            fn(HttpPageResponse $response) => $response->json()['data'],
            prefetch: 3, // pages n+1 to n+3 download while page n is consumed
            until: fn(int $page, HttpPageResponse $response) => $page + 1 >= $response->json()['total_pages'],
            options: new HttpPageOptions(timeout: 10.0, headers: ['Authorization' => 'Bearer ' . $token]),
        )
            ->where(fn(array $invoice) => $invoice['status'] === 'open')
            ->sum(fn(array $invoice) => $invoice['total']);
        // ---- end of documentation block ----

        $expected = 0;
        for ($n = 0; $n < 300; $n++) {
            if ($n % 3 === 0) {
                $expected += ($n + 1) * 10;
            }
        }
        $this->assertSame($expected, $openTotal);
    }
}
