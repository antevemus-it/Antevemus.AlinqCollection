<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Http;

use RuntimeException;
use Throwable;

/**
 * HttpPageException - A page of `ALinqLazyCollection::fromHttpPages()` could not be delivered
 *
 * Thrown at the point of the iteration where the page would have been delivered: the items
 * of the previous pages were already handed over (lazy semantics). Four causes:
 *
 * - a status outside 2xx (`getStatusCode()` answers it);
 * - a transport error (DNS, connection, TLS verification, ...);
 * - the timeout of `HttpPageOptions`/`HttpPageRequest` (`isTimeout()` answers true);
 * - the page limit of `HttpPageOptions::$maxPages` (`isPageLimit()` answers true).
 *
 * Neither the message nor the exception carries the response body or the query string of
 * the URL (both may hold tokens or personal data): the URL is reduced to scheme, host, port
 * and path, without user information, query or fragment. The factories below receive the
 * URL and the transport detail already redacted, so not even the arguments recorded in the
 * stack trace of the exception hold them. There is no retry in the core:
 * wrap the collection, or the callbacks, to retry.
 *
 * @version    1.5.0
 * @package    antevemus
 * @subpackage alinq.http
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class HttpPageException extends RuntimeException
{
    private const string KIND_STATUS = 'status';
    private const string KIND_TRANSPORT = 'transport';
    private const string KIND_TIMEOUT = 'timeout';
    private const string KIND_PAGE_LIMIT = 'page-limit';

    private function __construct(
        string $message,
        private readonly string $kind,
        private readonly ?int $statusCode,
        private readonly string $method,
        private readonly string $url,
        private readonly int $pageIndex,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    /**
     * A response with a status outside 2xx.
     *
     * @internal
     */
    public static function forStatus(int $status, string $method, string $url, int $pageIndex): self
    {
        $safeUrl = self::redactUrl($url);

        return new self(
            sprintf('HTTP %d for page index %d: %s %s', $status, $pageIndex, $method, $safeUrl),
            self::KIND_STATUS,
            $status,
            $method,
            $safeUrl,
            $pageIndex
        );
    }

    /**
     * A transport failure (DNS, connection, TLS, protocol).
     *
     * @internal
     */
    public static function forTransport(string $detail, string $method, string $url, int $pageIndex): self
    {
        $safeUrl = self::redactUrl($url);

        return new self(
            sprintf('HTTP transport error for page index %d: %s %s: %s', $pageIndex, $method, $safeUrl, $detail),
            self::KIND_TRANSPORT,
            null,
            $method,
            $safeUrl,
            $pageIndex
        );
    }

    /**
     * The page did not arrive within the timeout.
     *
     * @internal
     */
    public static function forTimeout(float $timeout, string $method, string $url, int $pageIndex): self
    {
        $safeUrl = self::redactUrl($url);

        return new self(
            sprintf('HTTP request for page index %d timed out after %s s: %s %s', $pageIndex, self::seconds($timeout), $method, $safeUrl),
            self::KIND_TIMEOUT,
            null,
            $method,
            $safeUrl,
            $pageIndex
        );
    }

    /**
     * The API still points to a next page after `maxPages` pages.
     *
     * @internal
     */
    public static function forPageLimit(int $maxPages, string $method, string $url, int $pageIndex): self
    {
        $safeUrl = self::redactUrl($url);

        return new self(
            sprintf(
                'Page limit reached: %d pages were fetched and the API still points to page index %d (%s %s); raise HttpPageOptions maxPages if the API really has more pages',
                $maxPages,
                $pageIndex,
                $method,
                $safeUrl
            ),
            self::KIND_PAGE_LIMIT,
            null,
            $method,
            $safeUrl,
            $pageIndex
        );
    }

    /**
     * The HTTP status of the response, or null when there was no response (transport
     * error, timeout, page limit).
     */
    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * The URL of the page, without user information, query string or fragment.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * The HTTP method of the request.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * The 0-based index of the page (the `$pageIndex` given to `$requestFor`).
     */
    public function getPageIndex(): int
    {
        return $this->pageIndex;
    }

    /**
     * Whether the page did not arrive within the timeout.
     */
    public function isTimeout(): bool
    {
        return $this->kind === self::KIND_TIMEOUT;
    }

    /**
     * Whether the page limit of `HttpPageOptions::$maxPages` was reached.
     */
    public function isPageLimit(): bool
    {
        return $this->kind === self::KIND_PAGE_LIMIT;
    }

    /**
     * Whether the failure happened below HTTP (DNS, connection, TLS, protocol).
     */
    public function isTransportError(): bool
    {
        return $this->kind === self::KIND_TRANSPORT;
    }

    /**
     * Reduce a URL to scheme, host, port and path: user information, query string and
     * fragment may carry credentials and never reach a message.
     *
     * @internal
     */
    public static function redactUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            // Not parseable: keep what precedes the query or the fragment, without user info.
            $cut = (string) preg_replace('/[?#].*$/s', '', $url);
            return (string) preg_replace('#^([a-z][a-z0-9+.-]*://)[^/@]*@#i', '$1', $cut);
        }

        // parse_url() keeps the brackets of an IPv6 host.
        return strtolower($parts['scheme']) . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '');
    }

    /**
     * Remove the full URL and its query string from a transport message, should the
     * transport ever quote them.
     *
     * @internal
     */
    public static function redactDetail(string $detail, string $url): string
    {
        $detail = str_replace($url, self::redactUrl($url), $detail);
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $detail = str_replace($query, '[redacted]', $detail);
        }

        return $detail;
    }

    private static function seconds(float $seconds): string
    {
        return rtrim(rtrim(sprintf('%.3F', $seconds), '0'), '.');
    }
}
