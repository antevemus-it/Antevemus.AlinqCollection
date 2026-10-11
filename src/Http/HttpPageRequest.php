<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Http;

use InvalidArgumentException;

/**
 * HttpPageRequest - The request of one page of a paginated HTTP API
 *
 * Built by the `$requestFor` callback of `ALinqLazyCollection::fromHttpPages()` (since 1.5.0)
 * for every page. A plain value object with no dependency (no PSR-7): method, absolute
 * http(s) URL, headers, optional body and optional timeout.
 *
 * - The URL must be absolute and use `http` or `https`; any other scheme (`file:`, `ftp:`,
 *   `gopher:`...) is refused here and the transfer is also restricted to HTTP(S) at the
 *   transport level, redirects included.
 * - Headers are given as `name => value`; a header name must be an RFC 9110 token and no
 *   name or value may contain CR, LF or NUL (no header injection). These headers override
 *   the common headers of `HttpPageOptions` with the same name (case-insensitive).
 * - The timeout, when given, replaces `HttpPageOptions::$timeout` for this request.
 * - There is no way to pass transport options: TLS verification is always on.
 *
 * @version    1.5.0
 * @package    antevemus
 * @subpackage alinq.http
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final readonly class HttpPageRequest
{
    /**
     * Absolute http(s) URL of the page.
     */
    public string $url;

    /**
     * HTTP method, upper case (`GET` by default).
     */
    public string $method;

    /**
     * Request headers, `name => value`.
     *
     * @var array<string, string>
     */
    public array $headers;

    /**
     * Request body, or null for none.
     */
    public ?string $body;

    /**
     * Timeout in seconds for this request, or null to use `HttpPageOptions::$timeout`.
     */
    public ?float $timeout;

    /**
     * @param string $url Absolute http(s) URL
     * @param string $method HTTP method (a token; normalized to upper case)
     * @param array<string, string|int|float> $headers `name => value`
     * @param string|null $body Request body
     * @param float|null $timeout Seconds, greater than zero; null uses the options' timeout
     * @throws InvalidArgumentException When the URL, the method, a header or the timeout is invalid
     */
    public function __construct(
        string $url,
        string $method = 'GET',
        array $headers = [],
        ?string $body = null,
        ?float $timeout = null
    ) {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '' || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            throw new InvalidArgumentException('HttpPageRequest requires an absolute http or https URL without whitespace or control characters.');
        }

        if (preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $method) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid HTTP method "%s".', $method));
        }

        if ($timeout !== null && !($timeout > 0.0)) {
            throw new InvalidArgumentException('HttpPageRequest timeout must be greater than zero seconds.');
        }

        $this->url = $url;
        $this->method = strtoupper($method);
        $this->headers = self::validHeaders($headers, 'HttpPageRequest');
        $this->body = $body;
        $this->timeout = $timeout;
    }

    /**
     * A GET request: the common case of a paginated API.
     *
     * @param string $url Absolute http(s) URL
     * @param array<string, string|int|float> $headers `name => value`
     * @param float|null $timeout Seconds; null uses the options' timeout
     * @return self
     */
    public static function get(string $url, array $headers = [], ?float $timeout = null): self
    {
        return new self($url, 'GET', $headers, null, $timeout);
    }

    /**
     * Validate a `name => value` header map (shared with HttpPageOptions).
     *
     * @internal
     * @param array<mixed, mixed> $headers
     * @param string $owner Class named in the error message
     * @return array<string, string>
     * @throws InvalidArgumentException On a name that is not a token or a value with CR, LF or NUL
     */
    public static function validHeaders(array $headers, string $owner): array
    {
        $valid = [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $name) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    '%s headers must be given as name => value with a valid header name, %s given as name.',
                    $owner,
                    is_string($name) ? '"' . addcslashes($name, "\0..\37") . '"' : get_debug_type($name)
                ));
            }
            if (!is_string($value) && !is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException(sprintf('%s header "%s" must be a string, %s given.', $owner, $name, get_debug_type($value)));
            }
            $value = (string) $value;
            if (strpbrk($value, "\r\n\0") !== false) {
                throw new InvalidArgumentException(sprintf('%s header "%s" contains CR, LF or NUL.', $owner, $name));
            }
            $valid[$name] = $value;
        }

        return $valid;
    }
}
