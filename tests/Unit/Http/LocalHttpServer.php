<?php

declare(strict_types=1);

namespace Antevemus\ALinq\Tests\Unit\Http;

use RuntimeException;

/**
 * LocalHttpServer - A `php -S` server on 127.0.0.1 for the fromHttpPages() tests (1.5.0)
 *
 * Started on a free port with the fixture router (`router.php`), four workers, Xdebug off and
 * a private log directory (the request counter). stop() terminates the server and its
 * workers and removes the logs; it is idempotent and is also registered as a shutdown
 * function, so no server outlives the test run. No traffic leaves the loopback interface.
 *
 * @version    1.5.0
 * @package    antevemus
 * @subpackage alinq.tests
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */
final class LocalHttpServer
{
    /** @var resource|null */
    private $process;

    private function __construct($process, public readonly int $port, private readonly string $logDir)
    {
        $this->process = $process;
        register_shutdown_function($this->stop(...));
    }

    /**
     * Start a server running $script (a router for `php -S`, or any PHP script that takes
     * the port as its first argument when $builtIn is false).
     *
     * @param string $script
     * @param bool $builtIn True: `php -S 127.0.0.1:<port> <script>`; false: `php <script> <port> <logDir>`
     * @return self
     */
    public static function start(string $script, bool $builtIn = true): self
    {
        $port = self::freePort();
        $logDir = sys_get_temp_dir() . '/alinq_http_' . bin2hex(random_bytes(6));
        if (!mkdir($logDir, 0700) && !is_dir($logDir)) {
            throw new RuntimeException('Cannot create the log directory of the local HTTP server.');
        }

        $env = getenv();
        $env['XDEBUG_MODE'] = 'off';
        $env['PHP_CLI_SERVER_WORKERS'] = '4';
        $env['ALINQ_HTTP_TEST_LOG_DIR'] = $logDir;

        $command = $builtIn
            ? [PHP_BINARY, '-S', '127.0.0.1:' . $port, $script]
            : [PHP_BINARY, $script, (string) $port, $logDir];

        $process = proc_open(
            $command,
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $logDir . '/server.out', 'a'], 2 => ['file', $logDir . '/server.out', 'a']],
            $pipes,
            dirname($script),
            $env
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start the local HTTP server.');
        }

        $server = new self($process, $port, $logDir);
        $server->waitUntilListening();

        return $server;
    }

    /**
     * An absolute URL on this server.
     *
     * @param string $path
     * @param array<string, scalar> $query
     * @param string $scheme
     * @return string
     */
    public function url(string $path, array $query = [], string $scheme = 'http'): string
    {
        return $scheme . '://127.0.0.1:' . $this->port . $path . ($query === [] ? '' : '?' . http_build_query($query));
    }

    /**
     * The lines logged by the router for a run, in arrival order.
     *
     * @param string $run
     * @return list<string>
     */
    public function log(string $run): array
    {
        $file = $this->logDir . '/' . $run . '.log';
        if (!is_file($file)) {
            return [];
        }

        return array_values(array_filter(explode("\n", (string) file_get_contents($file)), static fn(string $line): bool => $line !== ''));
    }

    /**
     * The number of requests the router received for a run (the call counter).
     */
    public function requestCount(string $run): int
    {
        return count(array_filter($this->log($run), static fn(string $line): bool => str_starts_with($line, 'request ')));
    }

    /**
     * Terminate the server and its workers; remove the logs. Idempotent.
     */
    public function stop(): void
    {
        if ($this->process === null) {
            return;
        }

        $process = $this->process;
        $this->process = null;

        $status = proc_get_status($process);
        $children = [];
        $childrenFile = sprintf('/proc/%d/task/%d/children', $status['pid'], $status['pid']);
        if (is_readable($childrenFile)) {
            $children = array_map('intval', preg_split('/\s+/', trim((string) file_get_contents($childrenFile))) ?: []);
        }

        proc_terminate($process, 15);
        $deadline = microtime(true) + 3.0;
        while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
            usleep(20_000);
        }
        if (proc_get_status($process)['running']) {
            proc_terminate($process, 9);
        }
        if (function_exists('posix_kill')) {
            foreach ($children as $child) {
                if ($child > 0) {
                    @posix_kill($child, 9);
                }
            }
        }
        proc_close($process);

        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->logDir);
    }

    private function waitUntilListening(): void
    {
        $deadline = microtime(true) + 10.0;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errno, $error, 0.2);
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }
            if (!proc_get_status($this->process)['running']) {
                break;
            }
            usleep(50_000);
        }

        $output = (string) @file_get_contents($this->logDir . '/server.out');
        $this->stop();
        throw new RuntimeException('The local HTTP server did not start: ' . $output);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            throw new RuntimeException('No free port on 127.0.0.1: ' . $error);
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
