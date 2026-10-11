<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit\Http;

use Antevemus\ALinq\Http\HttpPageException;
use Antevemus\ALinq\Http\HttpPageOptions;
use Antevemus\ALinq\Http\HttpPageRequest;
use Antevemus\ALinq\Http\HttpPageResponse;
use InvalidArgumentException;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * HttpPageValueObjectsTest - The value objects of fromHttpPages() without a network (1.5.0)
 *
 * HttpPageRequest and HttpPageOptions refuse what would reach the transport unchecked (other
 * schemes, header injection, nonsense timeouts); HttpPageResponse reads headers, JSON and the
 * RFC 8288 Link header; HttpPageException keeps credentials out of URLs and messages.
 *
 * @version    1.5.0
 * @package    antevemus
 * @subpackage alinq.tests
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class HttpPageValueObjectsTest extends TestCase
{
    // ------------------------------------------------------------------ HttpPageRequest

    public function testARequestKeepsWhatItWasGiven(): void
    {
        $request = new HttpPageRequest('https://api.example.com/items?page=2', 'post', ['X-Count' => 3], '{"a":1}', 2.5);

        $this->assertSame('https://api.example.com/items?page=2', $request->url);
        $this->assertSame('POST', $request->method, 'The method is normalized to upper case.');
        $this->assertSame(['X-Count' => '3'], $request->headers, 'A numeric header value becomes a string.');
        $this->assertSame('{"a":1}', $request->body);
        $this->assertSame(2.5, $request->timeout);

        $get = HttpPageRequest::get('http://localhost:8080/');
        $this->assertSame(['GET', [], null, null], [$get->method, $get->headers, $get->body, $get->timeout]);
    }

    /**
     * @return array<string, array{0: callable(): mixed, 1: string}>
     */
    public static function invalidRequests(): array
    {
        return [
            'file scheme' => [static fn() => HttpPageRequest::get('file:///etc/passwd'), 'absolute http or https URL'],
            'ftp scheme' => [static fn() => HttpPageRequest::get('ftp://example.com/list'), 'absolute http or https URL'],
            'relative URL' => [static fn() => HttpPageRequest::get('/items?page=2'), 'absolute http or https URL'],
            'CRLF in URL' => [static fn() => HttpPageRequest::get("https://example.com/a\r\nX-Injected: 1"), 'absolute http or https URL'],
            'space in URL' => [static fn() => HttpPageRequest::get('https://example.com/a b'), 'absolute http or https URL'],
            'method with space' => [static fn() => new HttpPageRequest('https://example.com/', 'GE T'), 'Invalid HTTP method'],
            'method with CRLF' => [static fn() => new HttpPageRequest('https://example.com/', "GET\r\n"), 'Invalid HTTP method'],
            'header name' => [static fn() => HttpPageRequest::get('https://example.com/', ['Bad Name' => 'x']), 'valid header name'],
            'list of headers' => [static fn() => HttpPageRequest::get('https://example.com/', ['Accept: */*']), 'given as name => value'],
            'CRLF in header' => [static fn() => HttpPageRequest::get('https://example.com/', ['X-A' => "1\r\nX-B: 2"]), 'contains CR, LF or NUL'],
            'array header' => [static fn() => HttpPageRequest::get('https://example.com/', ['X-A' => ['1']]), 'must be a string, array given'],
            'zero timeout' => [static fn() => HttpPageRequest::get('https://example.com/', [], 0.0), 'timeout must be greater than zero'],
            'options timeout' => [static fn() => new HttpPageOptions(timeout: -1.0), 'timeout must be greater than zero'],
            'options maxPages' => [static fn() => new HttpPageOptions(maxPages: 0), 'maxPages must be at least 1, 0 given'],
            'options header' => [static fn() => new HttpPageOptions(headers: ['Authorization' => "Bearer x\nX-Evil: 1"]), 'HttpPageOptions header "Authorization" contains CR, LF or NUL'],
        ];
    }

    #[DataProvider('invalidRequests')]
    public function testInvalidRequestsAndOptionsAreRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $build();
    }

    // ------------------------------------------------------------------ HttpPageOptions

    public function testOptionDefaults(): void
    {
        $options = new HttpPageOptions();

        $this->assertSame(30.0, $options->timeout);
        $this->assertSame(10_000, $options->maxPages);
        $this->assertSame([], $options->headers);
        $this->assertFalse($options->followRedirects);
    }

    // ------------------------------------------------------------------ HttpPageResponse

    private static function response(array $headers, string $body = '', string $url = 'https://api.example.com/v1/items?page=1'): HttpPageResponse
    {
        return new HttpPageResponse(200, $headers, $body, $url, 0);
    }

    public function testHeadersAreReadCaseInsensitively(): void
    {
        $response = self::response(['x-total' => ['42'], 'link' => ['<a>; rel="next"', '<b>; rel="last"']]);

        $this->assertSame('42', $response->header('X-Total'));
        $this->assertSame('<a>; rel="next", <b>; rel="last"', $response->header('LINK'), 'Repeated headers are joined with ", ".');
        $this->assertNull($response->header('X-Missing'));
    }

    public function testJsonDecodesObjectsAsArraysAndThrowsOnInvalidJson(): void
    {
        $this->assertSame(['data' => [1, 2], 'next' => null], self::response([], '{"data":[1,2],"next":null}')->json());

        $this->expectException(JsonException::class);
        self::response([], '<html>secret</html>')->json();
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function linkTargets(): array
    {
        return [
            'absolute' => ['<https://other.example.com/x?page=2>; rel="next"', 'https://other.example.com/x?page=2'],
            'root-relative' => ['</v2/items?page=2>; rel="next"', 'https://api.example.com/v2/items?page=2'],
            'path-relative' => ['<items?page=2>; rel="next"', 'https://api.example.com/v1/items?page=2'],
            'query-only' => ['<?page=2>; rel="next"', 'https://api.example.com/v1/items?page=2'],
            'scheme-relative' => ['<//cdn.example.com/p2>; rel="next"', 'https://cdn.example.com/p2'],
            'unquoted rel, other entries first' => ['<https://a/p9>; rel=last, <https://a/p2>; rel=next', 'https://a/p2'],
            'several relation types' => ['<https://a/p2>; rel="prefetch NEXT"', 'https://a/p2'],
            'quoted comma and semicolon' => ['<https://a/p9>; rel="last", <https://a/p2>; title="a, b; c"; rel="next"', 'https://a/p2'],
            'empty reference (the same page)' => ['<>; rel="next"', 'https://api.example.com/v1/items?page=1'],
            'fragment only' => ['<#more>; rel="next"', 'https://api.example.com/v1/items?page=1#more'],
            'no next' => ['<https://a/p1>; rel="prev", <https://a/p1>; rel="first"', null],
            'not a link' => ['garbage', null],
        ];
    }

    #[DataProvider('linkTargets')]
    public function testTheLinkHeaderIsReadAndResolved(string $header, ?string $expected): void
    {
        $this->assertSame($expected, self::response(['link' => [$header]])->link('next'));
    }

    public function testLinkWithoutHeaderAndWithAnotherRelation(): void
    {
        $this->assertNull(self::response([])->link());
        $this->assertSame('https://a/p9', self::response(['link' => ['<https://a/p2>; rel="next", <https://a/p9>; rel="last"']])->link('last'));
    }

    // ------------------------------------------------------------------ HttpPageException

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function urlsToRedact(): array
    {
        return [
            'query and fragment' => ['https://api.example.com/v1/items?token=abc&page=2#top', 'https://api.example.com/v1/items'],
            'user information' => ['https://user:p4ss@api.example.com:8443/items?x=1', 'https://api.example.com:8443/items'],
            'IPv6 host' => ['http://[::1]:8080/items?key=1', 'http://[::1]:8080/items'],
            'no path' => ['HTTPS://Example.com?key=1', 'https://Example.com'],
            'unparseable' => ['http://user:pw@:80/items?key=1', 'http://:80/items'],
        ];
    }

    #[DataProvider('urlsToRedact')]
    public function testUrlsLoseCredentialsQueryAndFragment(string $url, string $expected): void
    {
        $this->assertSame($expected, HttpPageException::redactUrl($url));
    }

    public function testTransportDetailsLoseTheUrlAndTheQuery(): void
    {
        $url = 'https://api.example.com/items?token=abc';

        $this->assertSame(
            'failed for https://api.example.com/items, then [redacted]',
            HttpPageException::redactDetail('failed for ' . $url . ', then token=abc', $url)
        );

        $e = HttpPageException::forTransport('Could not resolve host', 'GET', $url, 4);
        $this->assertSame('HTTP transport error for page index 4: GET https://api.example.com/items: Could not resolve host', $e->getMessage());
        $this->assertSame(0, $e->getCode());
        $this->assertSame([true, false, false], [$e->isTransportError(), $e->isTimeout(), $e->isPageLimit()]);

        $status = HttpPageException::forStatus(404, 'GET', $url, 0);
        $this->assertSame(404, $status->getCode(), 'The status is also the exception code.');
    }
}
