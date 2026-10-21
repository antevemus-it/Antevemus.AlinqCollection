<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Http;

use Closure;
use Generator;
use UnexpectedValueException;

/**
 * HttpPageStream - The paging loops of `ALinqLazyCollection::fromHttpPages()` (since 1.5.0)
 * and `fromHttpPagesByIndex()` (since 2.4.0)
 *
 * One traversal, one curl multi handle (connections are reused between pages). For each
 * page `n` (forward 027, D2, prefetch depth 1):
 *
 * 1. wait for the response of page `n` (`HttpPageException` here, at the point where the
 *    page would be delivered: the items of the previous pages were already yielded, D4);
 * 2. parse it into its items and the cursor of page `n+1`;
 * 3. build the request of page `n+1` and start it (it goes on the wire and the server
 *    works on it from now on), unless the cursor or the request is null (last page) or the
 *    page limit was reached;
 * 4. only then yield the items of page `n`, nudging the transfer of page `n+1` forward
 *    between items (non-blocking) so that a large response keeps flowing;
 * 5. after the last item, throw the page-limit exception if it was armed in step 3.
 *
 * Early termination (`take()`, `first()`, `any()`, an exception in the consumer) destroys the
 * suspended generator; its `finally` cancels the request in flight (D5). Each traversal
 * starts again from the first page (D6): the factory builds a new generator.
 *
 * Items are yielded with consecutive integer keys across pages (the source is a list).
 *
 * itemsByIndex() (2.4.0) is the loop of a page addressed by its index alone: no response is
 * needed to build the next request, so the page being awaited and up to `$prefetch` pages
 * after it are in flight on the same multi handle (several transfers, one handle each). For
 * page `n`: top up the pages in flight, wait for page `n`, parse its items, ask `$until`, yield
 * the items (nudging the transfers between them), then stop on an empty page or on `$until`.
 * The `finally` cancels every request still in flight.
 *
 * @internal
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq.http
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class HttpPageStream
{
    /**
     * Minimum interval, in nanoseconds, between two non-blocking nudges of the prefetch
     * while items are being yielded.
     */
    private const int PUMP_INTERVAL_NS = 2_000_000;

    /**
     * @param Closure(mixed, int): mixed $requestFor fn($cursor, $pageIndex): ?HttpPageRequest
     * @param Closure(HttpPageResponse): mixed $parsePage fn($response): array{0: iterable, 1: mixed}
     * @param HttpPageOptions $options
     * @return Generator<int, mixed>
     * @throws HttpPageException When a page cannot be delivered or the page limit is reached
     * @throws UnexpectedValueException When a callback answers something of the wrong shape
     */
    public static function items(Closure $requestFor, Closure $parsePage, HttpPageOptions $options): Generator
    {
        $request = self::requestFor($requestFor, null, 0);
        if ($request === null) {
            return;
        }

        $multi = curl_multi_init();
        $transfer = new HttpPageTransfer($multi, $request, $options, 0);
        $pageIndex = 0;
        $position = 0;

        try {
            while ($transfer !== null) {
                $response = $transfer->await();
                $transfer = null;

                [$items, $cursor] = self::parse($parsePage, $response);

                $pageLimit = null;
                if ($cursor !== null) {
                    $next = self::requestFor($requestFor, $cursor, $pageIndex + 1);
                    if ($next !== null && $pageIndex + 1 >= $options->maxPages) {
                        $safeUrl = HttpPageException::redactUrl($next->url);
                        $pageLimit = HttpPageException::forPageLimit($options->maxPages, $next->method, $safeUrl, $pageIndex + 1);
                    } elseif ($next !== null) {
                        // Prefetch: page n+1 is on the wire before the items of page n are handed over.
                        $transfer = new HttpPageTransfer($multi, $next, $options, $pageIndex + 1);
                    }
                }

                $lastPump = hrtime(true);
                foreach ($items as $item) {
                    yield $position++ => $item;

                    if ($transfer !== null && hrtime(true) - $lastPump >= self::PUMP_INTERVAL_NS) {
                        $transfer->pump();
                        $lastPump = hrtime(true);
                    }
                }

                if ($pageLimit !== null) {
                    throw $pageLimit;
                }

                $pageIndex++;
            }
        } finally {
            // Early termination or failure: abort the request in flight (D5).
            $transfer?->cancel();
            curl_multi_close($multi);
        }
    }

    /**
     * The loop of fromHttpPagesByIndex() (2.4.0): see the class description.
     *
     * The page limit: when `maxPages` pages were requested, none ended the stream and
     * `$requestFor` still answers a request for the next index, HttpPageException is thrown after
     * the items of the last allowed page (as in items()).
     *
     * @param Closure(int): (HttpPageRequest|string|null) $requestFor
     * @param Closure(HttpPageResponse): iterable $parsePage
     * @param Closure(int, HttpPageResponse): bool|null $until
     * @return Generator<int, mixed>
     * @throws HttpPageException When a page cannot be delivered or the page limit is reached
     * @throws UnexpectedValueException When a callback answers something of the wrong shape
     */
    public static function itemsByIndex(Closure $requestFor, Closure $parsePage, int $prefetch, ?Closure $until, HttpPageOptions $options): Generator
    {
        $multi = curl_multi_init();
        /** @var list<HttpPageTransfer> $inFlight in page order */
        $inFlight = [];
        $nextIndex = 0;
        $exhausted = false;
        $pageLimit = null;
        $position = 0;

        try {
            while (true) {
                // The page awaited next and up to $prefetch pages after it are on the wire.
                while (!$exhausted && count($inFlight) <= $prefetch) {
                    $request = self::requestByIndex($requestFor, $nextIndex);
                    if ($request === null) {
                        $exhausted = true;
                    } elseif ($nextIndex >= $options->maxPages) {
                        $safeUrl = HttpPageException::redactUrl($request->url);
                        $pageLimit = HttpPageException::forPageLimit($options->maxPages, $request->method, $safeUrl, $nextIndex);
                        $exhausted = true;
                    } else {
                        $inFlight[] = new HttpPageTransfer($multi, $request, $options, $nextIndex);
                        $nextIndex++;
                    }
                }

                $transfer = array_shift($inFlight);
                if ($transfer === null) {
                    if ($pageLimit !== null) {
                        throw $pageLimit;
                    }
                    return;
                }

                $response = $transfer->await();
                $items = self::parseItems($parsePage, $response);
                $last = $until !== null && self::isLastPage($until, $response);
                if ($last) {
                    // No page after this one is needed: stop the downloads now, not after its items.
                    foreach ($inFlight as $pending) {
                        $pending->cancel();
                    }
                    $inFlight = [];
                }

                $delivered = false;
                $lastPump = hrtime(true);
                foreach ($items as $item) {
                    yield $position++ => $item;
                    $delivered = true;

                    if ($inFlight !== [] && hrtime(true) - $lastPump >= self::PUMP_INTERVAL_NS) {
                        $inFlight[0]->pump();
                        $lastPump = hrtime(true);
                    }
                }

                if ($last || !$delivered) {
                    return; // the last page, or an empty one: the finally cancels what is in flight
                }
            }
        } finally {
            foreach ($inFlight as $pending) {
                $pending->cancel();
            }
            curl_multi_close($multi);
        }
    }

    /**
     * Call the $requestFor of fromHttpPagesByIndex() and check its answer: a request, a URL
     * (a GET request) or null.
     *
     * @throws UnexpectedValueException When it answers anything else
     */
    private static function requestByIndex(Closure $requestFor, int $pageIndex): ?HttpPageRequest
    {
        $request = $requestFor($pageIndex);
        if (is_string($request)) {
            return HttpPageRequest::get($request);
        }
        if ($request !== null && !$request instanceof HttpPageRequest) {
            throw new UnexpectedValueException(sprintf(
                'fromHttpPagesByIndex(): $requestFor must return an HttpPageRequest, a URL or null, %s given for page index %d.',
                get_debug_type($request),
                $pageIndex
            ));
        }

        return $request;
    }

    /**
     * Call the $parsePage of fromHttpPagesByIndex() and check its answer.
     *
     * @return iterable<mixed>
     * @throws UnexpectedValueException When it does not answer an iterable
     */
    private static function parseItems(Closure $parsePage, HttpPageResponse $response): iterable
    {
        $items = $parsePage($response);
        if (!is_iterable($items)) {
            throw new UnexpectedValueException(sprintf(
                'fromHttpPagesByIndex(): $parsePage must return the iterable items of the page, %s given for page index %d.',
                get_debug_type($items),
                $response->pageIndex
            ));
        }

        return $items;
    }

    /**
     * Call the $until of fromHttpPagesByIndex() and check its answer.
     *
     * @throws UnexpectedValueException When it does not answer a bool
     */
    private static function isLastPage(Closure $until, HttpPageResponse $response): bool
    {
        $last = $until($response->pageIndex, $response);
        if (!is_bool($last)) {
            throw new UnexpectedValueException(sprintf(
                'fromHttpPagesByIndex(): $until must return a bool, %s given for page index %d.',
                get_debug_type($last),
                $response->pageIndex
            ));
        }

        return $last;
    }

    /**
     * Call $requestFor and check its answer.
     *
     * @throws UnexpectedValueException When it answers neither an HttpPageRequest nor null
     */
    private static function requestFor(Closure $requestFor, mixed $cursor, int $pageIndex): ?HttpPageRequest
    {
        $request = $requestFor($cursor, $pageIndex);
        if ($request !== null && !$request instanceof HttpPageRequest) {
            throw new UnexpectedValueException(sprintf(
                'fromHttpPages(): $requestFor must return an HttpPageRequest or null, %s given for page index %d.',
                get_debug_type($request),
                $pageIndex
            ));
        }

        return $request;
    }

    /**
     * Call $parsePage and check its answer.
     *
     * @return array{0: iterable<mixed>, 1: mixed}
     * @throws UnexpectedValueException When it does not answer [iterable $items, mixed $nextCursor]
     */
    private static function parse(Closure $parsePage, HttpPageResponse $response): array
    {
        $parsed = $parsePage($response);
        if (!is_array($parsed) || count($parsed) !== 2 || !array_key_exists(0, $parsed) || !array_key_exists(1, $parsed) || !is_iterable($parsed[0])) {
            throw new UnexpectedValueException(sprintf(
                'fromHttpPages(): $parsePage must return [iterable $items, mixed $nextCursor] (null cursor on the last page), %s given for page index %d.',
                is_array($parsed) ? 'an array of another shape' : get_debug_type($parsed),
                $response->pageIndex
            ));
        }

        return [$parsed[0], $parsed[1]];
    }
}
