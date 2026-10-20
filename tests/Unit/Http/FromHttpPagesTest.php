<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit\Http;

use Antevemus\ALinq\ALinqLazyCollection;
use Antevemus\ALinq\Http\HttpPageException;
use Antevemus\ALinq\Http\HttpPageOptions;
use Antevemus\ALinq\Http\HttpPageRequest;
use Antevemus\ALinq\Http\HttpPageResponse;
use Antevemus\ALinq\Interfaces\IALinqLazyCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UnexpectedValueException;

/**
 * FromHttpPagesTest - ALinqLazyCollection::fromHttpPages() against a local `php -S` server
 *
 * Forward 027 (ALinq 1.5.0), rules RN-02 to RN-05 and decisions D1 to D7: prefetch overlap
 * measured, the three pagination styles, errors without body nor query string, TLS that
 * cannot be turned off, early termination cancelling the prefetch, re-iteration, page limit.
 * The server runs on 127.0.0.1 only and is stopped in tearDownAfterClass() (and by a
 * shutdown function, should the run abort).
 *
 * @version    2.2.2
 * @package    antevemus
 * @subpackage alinq.tests
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
#[Group('http')]
final class FromHttpPagesTest extends TestCase
{
    private static ?LocalHttpServer $server = null;

    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('curl')) {
            self::markTestSkipped('ext-curl is required by fromHttpPages().');
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
     * A source over the /cursor endpoint (cursor in the body, `after` in the query).
     *
     * @param array<string, scalar> $query
     */
    private static function cursorSource(array $query, ?HttpPageOptions $options = null, string $path = '/cursor'): ALinqLazyCollection
    {
        return ALinqLazyCollection::fromHttpPages(
            fn(?string $cursor) => HttpPageRequest::get(self::server()->url($path, $cursor === null ? $query : $query + ['after' => $cursor])),
            function (HttpPageResponse $response): array {
                $page = $response->json();
                return [$page['data'], $page['next']];
            },
            $options
        );
    }

    // ------------------------------------------------------------------ contract (D1, D8)

    public function testItIsALazySourceOfTheInterfaceAndAList(): void
    {
        $run = self::newRun();
        $lazy = self::cursorSource(['run' => $run, 'pages' => 3, 'per' => 2]);

        $this->assertInstanceOf(IALinqLazyCollection::class, $lazy);
        $this->assertSame(0, self::server()->requestCount($run), 'Nothing is requested before the first traversal.');
        $this->assertSame([0, 1, 2, 3, 4, 5], $lazy->toArray(), 'Items of every page, keys renumbered across pages.');
        $this->assertSame(3, self::server()->requestCount($run));
    }

    // ------------------------------------------------------------------ RN-02 overlap

    /**
     * RN-02: 3 pages of 300 ms each and a consumer that spends 300 ms per page. Serially that
     * is 3 × (300 + 300) = 1.8 s; with page n+1 downloading while page n is consumed it is
     * 300 + 3 × 300 = 1.2 s. The bound sits halfway (1.5 s).
     */
    public function testThePrefetchOverlapsTheDownloadOfThenextPageWithTheConsumer(): void
    {
        $run = self::newRun();
        $lazy = self::cursorSource(['run' => $run, 'pages' => 3, 'per' => 3, 'delay' => 300]);

        $start = hrtime(true);
        $items = $lazy->tap(static fn() => usleep(100_000))->toArray(); // 3 items × 100 ms = 300 ms per page
        $elapsed = (hrtime(true) - $start) / 1e9;

        $this->assertSame(range(0, 8), $items);
        $this->assertGreaterThan(1.15, $elapsed, 'The delays really happened.');
        $this->assertLessThan(1.5, $elapsed, sprintf('With prefetch the total is near 1.2 s (serial: 1.8 s); measured %.3f s.', $elapsed));
    }

    // ------------------------------------------------------------------ RN-03 pagination

    public function testCursorInTheBodyPagination(): void
    {
        $run = self::newRun();

        $this->assertSame(range(0, 9), self::cursorSource(['run' => $run, 'pages' => 5, 'per' => 2])->toArray());
        $this->assertSame(['request /cursor 0', 'request /cursor 1', 'request /cursor 2', 'request /cursor 3', 'request /cursor 4'], self::server()->log($run));
    }

    public function testPageNumberPagination(): void
    {
        $run = self::newRun();
        $seen = [];

        $lazy = ALinqLazyCollection::fromHttpPages(
            function (?int $page, int $pageIndex) use ($run, &$seen) {
                $seen[] = [$page, $pageIndex];
                return HttpPageRequest::get(self::server()->url('/numbered', ['run' => $run, 'pages' => 4, 'per' => 2, 'page' => $page ?? 1]));
            },
            function (HttpPageResponse $response): array {
                $body = $response->json();
                return [$body['data'], $body['page'] < $body['total_pages'] ? $body['page'] + 1 : null];
            }
        );

        $this->assertSame(range(0, 7), $lazy->toArray());
        $this->assertSame([[null, 0], [2, 1], [3, 2], [4, 3]], $seen, '$requestFor receives the cursor and the 0-based page index.');
    }

    public function testLinkHeaderPaginationWithAbsoluteAndRelativeTargets(): void
    {
        $run = self::newRun();
        $urls = [];

        $lazy = ALinqLazyCollection::fromHttpPages(
            fn(?string $next) => HttpPageRequest::get($next ?? self::server()->url('/linked', ['run' => $run, 'pages' => 4, 'per' => 2])),
            function (HttpPageResponse $response) use (&$urls): array {
                $urls[] = $response->url;
                return [$response->json(), $response->link('next')];
            }
        );

        $this->assertSame(range(0, 7), $lazy->toArray());
        $this->assertSame(['request /linked 0', 'request /linked 1', 'request /linked 2', 'request /linked 3'], self::server()->log($run));
        $this->assertStringStartsWith(self::server()->url('/linked?'), $urls[2], 'A relative Link target is resolved against the effective URL.');
    }

    public function testANullRequestEndsTheStream(): void
    {
        $run = self::newRun();

        $lazy = ALinqLazyCollection::fromHttpPages(
            fn(?string $cursor, int $pageIndex) => $pageIndex >= 2 ? null : HttpPageRequest::get(
                self::server()->url('/endless', ['run' => $run, 'per' => 2] + ($cursor === null ? [] : ['after' => $cursor]))
            ),
            fn(HttpPageResponse $response) => [$response->json()['data'], $response->json()['next']]
        );

        $this->assertSame([0, 1, 2, 3], $lazy->toArray());
        $this->assertSame(2, self::server()->requestCount($run));
        $this->assertSame([], ALinqLazyCollection::fromHttpPages(fn() => null, fn() => [[], null])->toArray(), 'No first request, no items.');
    }

    public function testThePageLimitThrowsAfterDeliveringTheLastAllowedPage(): void
    {
        $run = self::newRun();
        $lazy = self::cursorSource(['run' => $run, 'per' => 2, 'token' => 'limit-secret'], new HttpPageOptions(maxPages: 3), '/endless');

        $delivered = [];
        try {
            foreach ($lazy as $item) {
                $delivered[] = $item;
            }
            $this->fail('The page limit must throw.');
        } catch (HttpPageException $e) {
            $this->assertTrue($e->isPageLimit());
            $this->assertNull($e->getStatusCode());
            $this->assertSame(3, $e->getPageIndex());
            $this->assertStringStartsWith('Page limit reached: 3 pages were fetched and the API still points to page index 3', $e->getMessage());
            $this->assertStringNotContainsString('limit-secret', $e->getMessage());
        }

        $this->assertSame([0, 1, 2, 3, 4, 5], $delivered, 'The three allowed pages are delivered before the exception.');
        $this->assertSame(3, self::server()->requestCount($run), 'The fourth page is never requested.');
        $this->assertSame(10_000, (new HttpPageOptions())->maxPages, 'Default page limit.');
    }

    // ------------------------------------------------------------------ RN-04 errors

    public function testA500OnPage2ThrowsAfterPage1WithoutBodyNorQueryString(): void
    {
        $run = self::newRun();
        $lazy = self::cursorSource(['run' => $run, 'pages' => 3, 'per' => 2, 'fail' => 1, 'status' => 500, 'token' => 's3cr3t-token']);

        // Record the arguments in stack traces, so that the check below is not vacuous.
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
        $delivered = [];
        try {
            foreach ($lazy as $item) {
                $delivered[] = $item;
            }
            $this->fail('A 500 must throw.');
        } catch (HttpPageException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertSame(1, $e->getPageIndex());
            $this->assertSame('GET', $e->getMethod());
            $this->assertSame(self::server()->url('/cursor'), $e->getUrl());
            $this->assertSame('HTTP 500 for page index 1: GET ' . self::server()->url('/cursor'), $e->getMessage());
            $this->assertFalse($e->isTimeout());
            $this->assertFalse($e->isTransportError());
            $this->assertInstanceOf(RuntimeException::class, $e);

            // Not in the message, the URL, the string form, nor any string argument recorded
            // in the stack trace (whatever zend.exception_ignore_args says).
            $everything = $e->getMessage() . ' ' . $e->getUrl() . ' ' . $e;
            foreach ($e->getTrace() as $frame) {
                foreach ($frame['args'] ?? [] as $argument) {
                    $everything .= is_string($argument) ? ' ' . $argument : '';
                }
            }
            $this->assertStringNotContainsString('SECRET-BODY', $everything);
            $this->assertStringNotContainsString('s3cr3t-token', $everything);
            $this->assertStringNotContainsString('?', $e->getMessage() . $e->getUrl());
        } finally {
            ini_set('zend.exception_ignore_args', (string) $ignoreArgs);
        }

        $this->assertSame([0, 1], $delivered, 'The items of page 1 were delivered before the failure of page 2.');
    }

    public function testATransportErrorThrowsWithoutQueryString(): void
    {
        // A port nobody listens on: connection refused.
        $closed = stream_socket_server('tcp://127.0.0.1:0');
        $name = (string) stream_socket_get_name($closed, false);
        fclose($closed);

        $lazy = ALinqLazyCollection::fromHttpPages(
            fn() => HttpPageRequest::get('http://' . $name . '/items?api_key=never-printed'),
            fn() => [[], null]
        );

        try {
            $lazy->toArray();
            $this->fail('A refused connection must throw.');
        } catch (HttpPageException $e) {
            $this->assertTrue($e->isTransportError());
            $this->assertNull($e->getStatusCode());
            $this->assertSame(0, $e->getPageIndex());
            $this->assertStringStartsWith('HTTP transport error for page index 0: GET http://' . $name . '/items: ', $e->getMessage());
            $this->assertStringNotContainsString('never-printed', $e->getMessage());
        }
    }

    public function testTheTimeoutThrowsAndCountsOnlyTheTimeSpentWaiting(): void
    {
        $run = self::newRun();

        $start = hrtime(true);
        try {
            self::cursorSource(['run' => $run, 'delay' => 1500, 'q' => 'hidden'], new HttpPageOptions(timeout: 0.3))->toArray();
            $this->fail('A page slower than the timeout must throw.');
        } catch (HttpPageException $e) {
            $this->assertTrue($e->isTimeout());
            $this->assertSame('HTTP request for page index 0 timed out after 0.3 s: GET ' . self::server()->url('/cursor'), $e->getMessage());
        }
        $this->assertLessThan(1.0, (hrtime(true) - $start) / 1e9);

        // Page 1 takes 500 ms; the consumer spends 400 ms on page 0 meanwhile, so the source
        // waits about 100 ms: within a 350 ms timeout, although 500 ms of wall clock passed.
        $items = self::cursorSource(['run' => self::newRun(), 'pages' => 2, 'per' => 2, 'delay' => 500, 'delayFrom' => 1], new HttpPageOptions(timeout: 0.35))
            ->tap(static function (int $item): void {
                if ($item < 2) {
                    usleep(200_000);
                }
            })
            ->toArray();
        $this->assertSame([0, 1, 2, 3], $items);

        // A request may override the timeout of the options.
        $lazy = ALinqLazyCollection::fromHttpPages(
            fn() => HttpPageRequest::get(self::server()->url('/cursor', ['run' => self::newRun(), 'pages' => 1, 'delay' => 400]), [], 0.1),
            fn(HttpPageResponse $r) => [$r->json()['data'], null],
            new HttpPageOptions(timeout: 5.0)
        );
        $this->expectException(HttpPageException::class);
        $this->expectExceptionMessage('timed out after 0.1 s');
        $lazy->toArray();
    }

    public function testTheTimeoutCoversAHandshakeThatNeverEnds(): void
    {
        // A socket that listens but never answers: the kernel completes the TCP connection,
        // the TLS handshake never does, so the request is never sent.
        $blackHole = stream_socket_server('tcp://127.0.0.1:0');
        $address = (string) stream_socket_get_name($blackHole, false);

        try {
            $start = hrtime(true);
            ALinqLazyCollection::fromHttpPages(
                fn() => HttpPageRequest::get('https://' . $address . '/items?token=x'),
                fn() => [[], null],
                new HttpPageOptions(timeout: 0.2)
            )->toArray();
            $this->fail('A handshake that never ends must time out.');
        } catch (HttpPageException $e) {
            $this->assertTrue($e->isTimeout());
            $this->assertSame('HTTP request for page index 0 timed out after 0.2 s: GET https://' . $address . '/items', $e->getMessage());
            $this->assertLessThan(1.0, (hrtime(true) - $start) / 1e9);
        } finally {
            fclose($blackHole);
        }
    }

    /**
     * RN-04: TLS cannot be turned off. A server with a self-signed certificate is refused
     * (a client that skipped verification would get three items), and neither the options
     * nor the request expose any TLS or transport switch.
     */
    public function testTlsVerificationIsAlwaysOnAndCannotBeTurnedOff(): void
    {
        foreach ([HttpPageOptions::class, HttpPageRequest::class] as $class) {
            $names = array_map(static fn(\ReflectionParameter $p): string => strtolower($p->getName()), (new \ReflectionMethod($class, '__construct'))->getParameters());
            foreach ($names as $name) {
                $this->assertDoesNotMatchRegularExpression('/verif|tls|ssl|insecure|cert|curl|transport/', $name, $class . ' has no TLS or transport switch.');
            }
        }

        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is needed to start the self-signed TLS server.');
        }

        $tls = LocalHttpServer::start(__DIR__ . '/tls-server.php', false);
        try {
            $lazy = ALinqLazyCollection::fromHttpPages(
                fn() => HttpPageRequest::get($tls->url('/items', ['token' => 'tls-secret'], 'https')),
                fn(HttpPageResponse $r) => [$r->json()['data'], null],
                new HttpPageOptions(timeout: 5.0)
            );

            try {
                $lazy->toArray();
                $this->fail('A self-signed certificate must be refused.');
            } catch (HttpPageException $e) {
                $this->assertTrue($e->isTransportError());
                $this->assertStringContainsString('HTTP transport error for page index 0: GET ' . $tls->url('/items', [], 'https'), $e->getMessage());
                $this->assertMatchesRegularExpression('/certificate|SSL|self.signed/i', $e->getMessage());
                $this->assertStringNotContainsString('tls-secret', $e->getMessage());
            }
        } finally {
            $tls->stop();
        }
    }

    public function testRedirectsAreNotFollowedByDefault(): void
    {
        $query = ['run' => self::newRun(), 'pages' => 1, 'per' => 2, 'token' => 'redirect-secret'];

        try {
            self::cursorSource($query, null, '/redirect')->toArray();
            $this->fail('A 302 is not 2xx.');
        } catch (HttpPageException $e) {
            $this->assertSame(302, $e->getStatusCode());
            $this->assertStringNotContainsString('redirect-secret', $e->getMessage());
        }

        $urls = [];
        $lazy = ALinqLazyCollection::fromHttpPages(
            fn() => HttpPageRequest::get(self::server()->url('/redirect', $query)),
            function (HttpPageResponse $response) use (&$urls): array {
                $urls[] = $response->url;
                return [$response->json()['data'], null];
            },
            new HttpPageOptions(followRedirects: true)
        );
        $this->assertSame([0, 1], $lazy->toArray());
        $this->assertStringStartsWith(self::server()->url('/cursor?'), $urls[0], 'The response carries the effective URL.');
    }

    public function testCommonHeadersAreSentAndARequestHeaderWins(): void
    {
        $source = static fn(array $requestHeaders, string $method = 'GET', ?string $body = null) => ALinqLazyCollection::fromHttpPages(
            fn() => new HttpPageRequest(self::server()->url('/headers'), $method, $requestHeaders, $body),
            fn(HttpPageResponse $r) => [$r->json()['data'], null],
            new HttpPageOptions(headers: ['Authorization' => 'Bearer common', 'X-Trace' => 'options'])
        );

        $this->assertSame(
            [['authorization' => 'Bearer common', 'trace' => 'options', 'method' => 'GET', 'body' => '']],
            $source([])->toArray()
        );
        $this->assertSame(
            [['authorization' => 'Bearer common', 'trace' => 'request', 'method' => 'POST', 'body' => '{"page":1}']],
            $source(['x-trace' => 'request'], 'post', '{"page":1}')->toArray(),
            'Header names compare case-insensitively; method and body reach the server.'
        );

        $head = ALinqLazyCollection::fromHttpPages(
            fn() => new HttpPageRequest(self::server()->url('/headers'), 'HEAD'),
            fn(HttpPageResponse $r) => [[$r->status, $r->body, $r->header('content-type')], null]
        );
        $this->assertSame([200, '', 'application/json'], $head->toArray(), 'HEAD: headers without a body.');
    }

    // ------------------------------------------------------------------ RN-05 early termination

    /**
     * @return array<string, array{0: callable(ALinqLazyCollection): mixed, 1: mixed}>
     */
    public static function earlyTerminations(): array
    {
        return [
            'take(3)' => [static fn(ALinqLazyCollection $c) => $c->take(3)->toArray(), [0, 1, 2]],
            'first()' => [static fn(ALinqLazyCollection $c) => $c->first(), 0],
            'any()' => [static fn(ALinqLazyCollection $c) => $c->any(), true],
        ];
    }

    /**
     * RN-05: over pages of 10 items, stopping early makes at most 2 requests (the first page
     * and the prefetch of the second), and the prefetch is cancelled: the server sees the
     * client go away while page 2 is still being produced.
     */
    #[DataProvider('earlyTerminations')]
    public function testStoppingEarlyCancelsThePrefetch(callable $terminate, mixed $expected): void
    {
        $run = self::newRun();
        $lazy = self::cursorSource(['run' => $run, 'pages' => 5, 'per' => 10, 'delay' => 1500, 'delayFrom' => 1, 'watch' => 1]);

        $start = hrtime(true);
        $this->assertSame($expected, $terminate($lazy));
        $this->assertLessThan(1.0, (hrtime(true) - $start) / 1e9, 'Nothing waits for page 2.');

        $deadline = microtime(true) + 3.0;
        while (!in_array('aborted /cursor 1', self::server()->log($run), true) && microtime(true) < $deadline) {
            usleep(20_000);
        }

        $this->assertSame(['request /cursor 0', 'request /cursor 1', 'aborted /cursor 1'], self::server()->log($run));
        $this->assertLessThanOrEqual(2, self::server()->requestCount($run));
    }

    public function testAnExceptionInTheConsumerCancelsThePrefetch(): void
    {
        $run = self::newRun();
        $lazy = self::cursorSource(['run' => $run, 'pages' => 3, 'per' => 2, 'delay' => 1500, 'delayFrom' => 1, 'watch' => 1]);

        try {
            foreach ($lazy as $item) {
                throw new \DomainException('consumer failed on ' . $item);
            }
        } catch (\DomainException) {
        }
        unset($item);

        $deadline = microtime(true) + 3.0;
        while (!in_array('aborted /cursor 1', self::server()->log($run), true) && microtime(true) < $deadline) {
            usleep(20_000);
        }
        $this->assertSame(['request /cursor 0', 'request /cursor 1', 'aborted /cursor 1'], self::server()->log($run));
    }

    // ------------------------------------------------------------------ D6 re-iteration

    public function testEveryTraversalFetchesAgainFromTheFirstPageAndRememberDoesNot(): void
    {
        $run = self::newRun();
        $lazy = self::cursorSource(['run' => $run, 'pages' => 3, 'per' => 2]);

        $this->assertSame(6, $lazy->count());
        $this->assertSame(range(0, 5), $lazy->toArray());
        $this->assertSame(6, self::server()->requestCount($run), 'Two traversals, three pages each.');
        $this->assertSame(['request /cursor 0', 'request /cursor 1', 'request /cursor 2'], array_slice(self::server()->log($run), 3));

        $run = self::newRun();
        $remembered = self::cursorSource(['run' => $run, 'pages' => 3, 'per' => 2])->remember();
        $this->assertSame(6, $remembered->count());
        $this->assertSame(range(0, 5), $remembered->toArray());
        $this->assertSame(3, self::server()->requestCount($run), 'remember() traverses once and replays from memory.');
    }

    // ------------------------------------------------------------------ callback shape

    public function testCallbacksOfTheWrongShapeAreReported(): void
    {
        $url = self::server()->url('/cursor', ['run' => self::newRun(), 'pages' => 1]);

        try {
            ALinqLazyCollection::fromHttpPages(fn() => $url, fn() => [[], null])->toArray();
            $this->fail('A string is not a request.');
        } catch (UnexpectedValueException $e) {
            $this->assertSame('fromHttpPages(): $requestFor must return an HttpPageRequest or null, string given for page index 0.', $e->getMessage());
        }

        foreach ([['a'], [1, null], 'items', [[1], null, 'extra'], ['items' => [], 'next' => null]] as $answer) {
            try {
                ALinqLazyCollection::fromHttpPages(fn() => HttpPageRequest::get($url), fn() => $answer)->toArray();
                $this->fail('Wrong parser answer accepted: ' . json_encode($answer));
            } catch (UnexpectedValueException $e) {
                $this->assertStringStartsWith('fromHttpPages(): $parsePage must return [iterable $items, mixed $nextCursor]', $e->getMessage());
            }
        }

        // A generator of items and a non-Closure callable are fine.
        $lazy = ALinqLazyCollection::fromHttpPages(
            fn() => HttpPageRequest::get($url),
            [new class {
                public function parse(HttpPageResponse $r): array
                {
                    return [(static fn() => yield from $r->json()['data'])(), null];
                }
            }, 'parse']
        );
        $this->assertSame([0, 1, 2], $lazy->toArray());
    }

    // ----------------------------------------------------------- documentation example

    /**
     * docs/en/api/lazy-streaming.md "Paginated HTTP API with prefetch" (EN and pt-BR, identical code): the block
     * as written, with the host of the example API pointed at the local server.
     */
    public function testTheReadmeExampleRunsAsWritten(): void
    {
        // ---- documentation block; only these two lines differ (local server instead of the example API) ----
        $api = self::server()->url('/orders');
        $token = 'readme-token';

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
        // ---- end of documentation block ----

        $expected = 0;
        for ($n = 0; $n < 300; $n++) {
            if ($n % 3 !== 0) {
                $expected += ($n + 1) * 10;
            }
        }
        $this->assertSame($expected, $paidTotal);
    }
}
