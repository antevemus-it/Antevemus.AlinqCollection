<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Http;

use JsonException;

/**
 * HttpPageResponse - The 2xx response of one page of a paginated HTTP API
 *
 * Handed to the `$parsePage` callback of `ALinqLazyCollection::fromHttpPages()` (since
 * 1.5.0), which answers the items of the page and the cursor of the next one. A plain value
 * object with no dependency (no PSR-7): status, headers, body, effective URL (after
 * redirects, when they are followed) and the 0-based index of the page.
 *
 * Helpers for the three usual pagination styles:
 * - `json()` decodes the body (cursor or page number in the body);
 * - `link('next')` reads the RFC 8288 `Link` header (GitHub style), resolved against the
 *   effective URL;
 * - `header($name)` reads any other header case-insensitively.
 *
 * @version    1.5.0
 * @package    antevemus
 * @subpackage alinq.http
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final readonly class HttpPageResponse
{
    /**
     * @param int $status HTTP status (2xx)
     * @param array<string, list<string>> $headers Lower-case name => values, in arrival order
     * @param string $body Response body
     * @param string $url Effective URL
     * @param int $pageIndex 0-based index of the page
     */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        public string $url,
        public int $pageIndex
    ) {
    }

    /**
     * A header by name (case-insensitive); repeated headers are joined with ", ".
     *
     * @param string $name
     * @return string|null Null when absent
     */
    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;

        return $values === null ? null : implode(', ', $values);
    }

    /**
     * The body decoded as JSON, objects as associative arrays.
     *
     * @return mixed
     * @throws JsonException When the body is not valid JSON (the message never quotes the body)
     */
    public function json(): mixed
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * The target of the first `Link` header entry with the given relation (RFC 8288),
     * resolved against the effective URL; null when there is none (the last page).
     *
     * @param string $rel Relation type, case-insensitive (`next` by default)
     * @return string|null
     */
    public function link(string $rel = 'next'): ?string
    {
        $header = $this->header('link');
        if ($header === null) {
            return null;
        }

        // Each entry: <target> followed by `; param` parts; a quoted value may hold ; and ,.
        preg_match_all('/<([^>]*)>((?:\s*;\s*(?:[^;,"]|"[^"]*")*)*)/', $header, $entries, PREG_SET_ORDER);
        foreach ($entries as [, $target, $parameters]) {
            if (preg_match('/;\s*rel\s*=\s*(?:"([^"]*)"|([^\s;,"]+))/i', $parameters, $match) !== 1) {
                continue;
            }
            $relations = preg_split('/\s+/', strtolower(trim(($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? ''))));
            if (in_array(strtolower($rel), $relations ?: [], true)) {
                return self::resolve($this->url, trim($target));
            }
        }

        return null;
    }

    /**
     * Resolve a URI reference against an absolute base URL (RFC 3986, section 5.2, without
     * dot-segment removal: the transport normalizes the path).
     */
    private static function resolve(string $base, string $reference): string
    {
        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $reference) === 1) {
            return $reference;
        }

        $parts = parse_url($base);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $reference;
        }

        $scheme = $parts['scheme'];
        if (str_starts_with($reference, '//')) {
            return $scheme . ':' . $reference;
        }

        $origin = $scheme . '://'
            . (isset($parts['user']) ? $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@' : '')
            . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path = $parts['path'] ?? '/';

        if ($reference === '') {
            return $origin . $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }
        if (str_starts_with($reference, '/')) {
            return $origin . $reference;
        }
        if (str_starts_with($reference, '?')) {
            return $origin . $path . $reference;
        }
        if (str_starts_with($reference, '#')) {
            return $origin . $path . (isset($parts['query']) ? '?' . $parts['query'] : '') . $reference;
        }

        return $origin . substr($path, 0, (int) strrpos($path, '/') + 1) . $reference;
    }
}
