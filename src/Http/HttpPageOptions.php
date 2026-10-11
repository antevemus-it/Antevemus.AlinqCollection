<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Http;

use InvalidArgumentException;

/**
 * HttpPageOptions - Options of `ALinqLazyCollection::fromHttpPages()` (since 1.5.0)
 *
 * - `timeout` (seconds, default 30): the longest the iteration may block waiting for one
 *   page (connection, request and response). Time the consumer spends on the previous
 *   page while the next one is prefetched does not count: a page that arrived while the
 *   consumer was busy is never reported as timed out. A request may override it.
 * - `maxPages` (default 10 000): guard against a cursor that never ends. When that many
 *   pages were fetched and the API still points to a next one, the iteration throws
 *   `HttpPageException` after delivering the items of the last allowed page.
 * - `headers`: common headers (e.g. `Authorization`) sent with every request; a header of
 *   the request with the same name (case-insensitive) wins.
 * - `followRedirects` (default false): when false a 3xx response is not 2xx and throws;
 *   when true up to 5 redirects are followed, to http(s) only.
 *
 * TLS certificate and host-name verification is always on; there is no option to turn it
 * off, here or on the request.
 *
 * @version    1.5.0
 * @package    antevemus
 * @subpackage alinq.http
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final readonly class HttpPageOptions
{
    /**
     * Default maximum number of pages of one traversal.
     */
    public const int DEFAULT_MAX_PAGES = 10_000;

    /**
     * Seconds the iteration may block waiting for one page.
     */
    public float $timeout;

    /**
     * Maximum number of pages fetched by one traversal.
     */
    public int $maxPages;

    /**
     * Headers sent with every request, `name => value`.
     *
     * @var array<string, string>
     */
    public array $headers;

    /**
     * Whether 3xx responses are followed (up to 5 redirects, http(s) only).
     */
    public bool $followRedirects;

    /**
     * @param float $timeout Seconds, greater than zero
     * @param int $maxPages At least 1
     * @param array<string, string|int|float> $headers Common headers, `name => value`
     * @param bool $followRedirects Follow 3xx responses
     * @throws InvalidArgumentException When the timeout, the page limit or a header is invalid
     */
    public function __construct(
        float $timeout = 30.0,
        int $maxPages = self::DEFAULT_MAX_PAGES,
        array $headers = [],
        bool $followRedirects = false
    ) {
        if (!($timeout > 0.0)) {
            throw new InvalidArgumentException('HttpPageOptions timeout must be greater than zero seconds.');
        }
        if ($maxPages < 1) {
            throw new InvalidArgumentException(sprintf('HttpPageOptions maxPages must be at least 1, %d given.', $maxPages));
        }

        $this->timeout = $timeout;
        $this->maxPages = $maxPages;
        $this->headers = HttpPageRequest::validHeaders($headers, 'HttpPageOptions');
        $this->followRedirects = $followRedirects;
    }
}
