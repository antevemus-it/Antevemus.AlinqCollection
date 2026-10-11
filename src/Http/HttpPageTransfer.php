<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Http;

use Closure;
use CurlHandle;
use CurlMultiHandle;

/**
 * HttpPageTransfer - One page request in flight on a curl multi handle
 *
 * Internal to `ALinqLazyCollection::fromHttpPages()` (since 1.5.0). The constructor adds the
 * request to the multi handle and drives it until the request is on the wire (or failed),
 * then returns: from then on the server works and the response lands in the socket buffer
 * while the caller does something else, with no thread and no event loop. `pump()` moves the
 * transfer forward without blocking; `await()` blocks until the response is complete and
 * answers it or throws `HttpPageException`; `cancel()` (also run by the destructor) removes
 * the handle from the multi handle, which aborts the connection.
 *
 * Timeout: only the time this object spends blocked (sending in the constructor, waiting in
 * `await()`) counts against the budget, so a response that arrived while the consumer was
 * busy is never reported as timed out. That is why curl's own CURLOPT_TIMEOUT is not used:
 * it measures wall-clock time since the transfer started, consumer time included.
 *
 * Fixed transport rules: HTTP and HTTPS only (redirects too), TLS peer and host verification
 * always on, at most 5 redirects when they are followed, no `Expect: 100-continue`.
 *
 * @internal
 * @version    1.5.0
 * @package    antevemus
 * @subpackage alinq.http
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class HttpPageTransfer
{
    /**
     * Upper bound of one blocking wait slice, in seconds.
     */
    private const float SELECT_SLICE = 0.25;

    private const int MAX_REDIRECTS = 5;

    private ?CurlHandle $handle = null;

    /**
     * Response headers of the last response of a redirect chain, lower-case name => values.
     *
     * @var array<string, list<string>>
     */
    private array $responseHeaders = [];

    /**
     * curl result code once the transfer is over; null while it is running.
     */
    private ?int $result = null;

    /**
     * Error detail when the transfer could not even be set up.
     */
    private ?string $setupError = null;

    /**
     * Seconds spent blocked on this transfer.
     */
    private float $waited = 0.0;

    private readonly float $budget;

    public function __construct(
        private readonly CurlMultiHandle $multi,
        private readonly HttpPageRequest $request,
        HttpPageOptions $options,
        private readonly int $pageIndex
    ) {
        $this->budget = $request->timeout ?? $options->timeout;

        $handle = curl_init();
        if ($handle === false) {
            $this->setupError = 'curl_init() failed';
            return;
        }

        if (!curl_setopt_array($handle, $this->curlOptions($options))) {
            $this->setupError = curl_error($handle) ?: 'invalid transfer options';
            return;
        }

        $added = curl_multi_add_handle($this->multi, $handle);
        if ($added !== CURLM_OK) {
            $this->setupError = curl_multi_strerror($added) ?? 'curl_multi_add_handle() failed';
            return;
        }

        $this->handle = $handle;
        $this->driveUntilSent();
    }

    public function __destruct()
    {
        $this->cancel();
    }

    /**
     * Move the transfer forward without blocking (reads what already arrived).
     */
    public function pump(): void
    {
        if ($this->handle !== null && $this->result === null) {
            curl_multi_exec($this->multi, $running);
            $this->collect();
        }
    }

    /**
     * Block until the response is complete.
     *
     * @return HttpPageResponse The 2xx response
     * @throws HttpPageException On a status outside 2xx, a transport error or the timeout
     */
    public function await(): HttpPageResponse
    {
        $method = $this->request->method;
        // Redacted before any exception is built: the stack trace records the arguments.
        $url = HttpPageException::redactUrl($this->request->url);

        if ($this->setupError !== null) {
            throw HttpPageException::forTransport($this->setupError, $method, $url, $this->pageIndex);
        }
        if ($this->handle === null) {
            throw HttpPageException::forTransport('the transfer was cancelled', $method, $url, $this->pageIndex);
        }

        while (true) {
            $this->pump();
            if ($this->result !== null) {
                break;
            }
            $remaining = $this->budget - $this->waited;
            if ($remaining <= 0.0) {
                $this->cancel();
                throw HttpPageException::forTimeout($this->budget, $method, $url, $this->pageIndex);
            }
            $this->block(min($remaining, self::SELECT_SLICE));
        }

        $handle = $this->handle;
        if ($this->result !== CURLE_OK) {
            $detail = curl_error($handle);
            if ($detail === '') {
                $detail = curl_strerror($this->result) ?? sprintf('curl error %d', $this->result);
            }
            $detail = HttpPageException::redactDetail($detail, $this->request->url);
            $this->cancel();
            throw HttpPageException::forTransport($detail, $method, $url, $this->pageIndex);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $effectiveUrl = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
        if ($effectiveUrl === '') {
            $effectiveUrl = $this->request->url;
        }
        $body = (string) curl_multi_getcontent($handle);
        $this->cancel();

        if ($status < 200 || $status > 299) {
            $safeUrl = HttpPageException::redactUrl($effectiveUrl);
            throw HttpPageException::forStatus($status, $method, $safeUrl, $this->pageIndex);
        }

        return new HttpPageResponse($status, $this->responseHeaders, $body, $effectiveUrl, $this->pageIndex);
    }

    /**
     * Abort the transfer: remove the handle from the multi handle and release it (the
     * connection is closed). Idempotent.
     */
    public function cancel(): void
    {
        if ($this->handle !== null) {
            curl_multi_remove_handle($this->multi, $this->handle);
            // Releasing the last reference frees the easy handle (curl_close() is a no-op
            // since PHP 8.0 and deprecated in 8.5).
            $this->handle = null;
        }
    }

    /**
     * Drive the transfer until the request has been sent, the transfer is over or the
     * budget is spent, so that the server works while the caller does something else.
     */
    private function driveUntilSent(): void
    {
        while (true) {
            $this->pump();
            if ($this->result !== null || curl_getinfo($this->handle, CURLINFO_REQUEST_SIZE) > 0) {
                return;
            }
            $remaining = $this->budget - $this->waited;
            if ($remaining <= 0.0) {
                return; // await() reports the timeout at the point of delivery
            }
            $this->block(min($remaining, self::SELECT_SLICE));
        }
    }

    /**
     * Wait for activity on the transfer's sockets, at most $seconds, and account the time.
     */
    private function block(float $seconds): void
    {
        $start = hrtime(true);
        if (curl_multi_select($this->multi, $seconds) === -1) {
            usleep(1000);
        }
        $this->waited += (hrtime(true) - $start) / 1e9;
    }

    /**
     * Read the completion message of this transfer, if any.
     */
    private function collect(): void
    {
        while (($info = curl_multi_info_read($this->multi)) !== false) {
            if ($info['handle'] === $this->handle) {
                $this->result = $info['result'];
            }
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function curlOptions(HttpPageOptions $options): array
    {
        $request = $this->request;

        // Common headers first; a request header with the same name (case-insensitive) wins.
        $merged = [];
        foreach ([$options->headers, $request->headers] as $headers) {
            foreach ($headers as $name => $value) {
                $merged[strtolower($name)] = $name . ': ' . $value;
            }
        }
        $merged['expect'] = 'Expect:';

        $curl = [
            CURLOPT_URL => $request->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => array_values($merged),
            CURLOPT_HEADERFUNCTION => self::headerCollector($this->responseHeaders),
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => $options->followRedirects,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOSIGNAL => true,
        ];

        if ($request->method === 'GET' && $request->body === null) {
            $curl[CURLOPT_HTTPGET] = true;
        } elseif ($request->method === 'HEAD') {
            $curl[CURLOPT_NOBODY] = true;
        } else {
            $curl[CURLOPT_CUSTOMREQUEST] = $request->method;
            if ($request->body !== null || in_array($request->method, ['POST', 'PUT', 'PATCH'], true)) {
                $curl[CURLOPT_POSTFIELDS] = $request->body ?? '';
            }
        }

        return $curl;
    }

    /**
     * CURLOPT_HEADERFUNCTION: collect the headers of the last response of the chain into
     * $headers. A static closure over a reference, so the easy handle never holds the
     * transfer object (no reference cycle; the destructor runs as soon as it is released).
     *
     * @param array<string, list<string>> $headers
     */
    private static function headerCollector(array &$headers): Closure
    {
        return static function (CurlHandle $handle, string $line) use (&$headers): int {
            $length = strlen($line);
            $line = rtrim($line, "\r\n");

            if (str_starts_with($line, 'HTTP/')) {
                // A new status line (redirect, 100 Continue): the previous headers are not the final ones.
                $headers = [];
            } elseif (($colon = strpos($line, ':')) !== false) {
                $headers[strtolower(trim(substr($line, 0, $colon)))][] = trim(substr($line, $colon + 1));
            }

            return $length;
        };
    }
}
