<?php

/**
 * Router of the local `php -S` server used by the fromHttpPages() tests (1.5.0, forward 027).
 *
 * Not a test and not a class: `php -S 127.0.0.1:<port> router.php`. Every request appends
 * "request <path> <pageIndex>" to <ALINQ_HTTP_TEST_LOG_DIR>/<run>.log (the call counter);
 * the abort watcher appends "aborted ..." or "completed ...".
 *
 * Query parameters (all optional):
 * - run        log name (default "default")
 * - pages      number of pages (default 3)
 * - per        items per page (default 3)
 * - delay      milliseconds before answering (default 0)
 * - delayFrom  first 0-based page index that is delayed (default 0)
 * - delayTo    last 0-based page index that is delayed (default: every page from delayFrom on)
 * - fail       0-based page index answered with `status` and a secret body
 * - status     status of the failing page (default 500)
 * - watch      1: delayed pages stream padding while waiting and log "aborted" (client
 *              gone) or "completed"
 *
 * Paths:
 * - /cursor    {"data": [...], "next": "p<N>"|null}, cursor in `after`
 * - /orders    as /cursor with order rows; `limit` items per page (the example of docs/en/api/lazy-streaming.md)
 * - /numbered  {"data": [...], "page": P, "total_pages": T}, 1-based `page`
 * - /indexed   as /numbered with order rows (as /orders) and `per` items per page, and an empty
 *              `data` for a page after the last one (fromHttpPagesByIndex(), 2.4.0)
 * - /linked    a JSON list; `Link: <...>; rel="last", <...>; rel="next"` (absolute on even
 *              page indexes, relative on odd ones), no next on the last page
 * - /endless   as /cursor, the cursor never ends
 * - /redirect  302 to /cursor with the same query string
 * - /headers   one item with the Authorization and X-Trace headers received
 *
 * @version    2.4.0
 * @package    antevemus
 * @subpackage alinq.tests
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */

declare(strict_types=1);

$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$run = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_GET['run'] ?? 'default')) ?: 'default';
$logFile = rtrim((string) getenv('ALINQ_HTTP_TEST_LOG_DIR'), '/') . '/' . $run . '.log';
$log = static function (string $line) use ($logFile): void {
    file_put_contents($logFile, $line . "\n", FILE_APPEND | LOCK_EX);
};

$pages = (int) ($_GET['pages'] ?? 3);
$per = (int) ($_GET['per'] ?? ($_GET['limit'] ?? 3));
$delay = (int) ($_GET['delay'] ?? 0);
$delayFrom = (int) ($_GET['delayFrom'] ?? 0);
$delayTo = isset($_GET['delayTo']) ? (int) $_GET['delayTo'] : PHP_INT_MAX;

$pageIndex = match ($path) {
    '/numbered', '/linked', '/indexed' => max(1, (int) ($_GET['page'] ?? 1)) - 1,
    default => isset($_GET['after']) ? (int) substr((string) $_GET['after'], 1) : 0,
};

$log('request ' . $path . ' ' . $pageIndex);

if ($path === '/redirect') {
    header('Location: /cursor?' . (string) ($_SERVER['QUERY_STRING'] ?? ''), true, 302);
    exit;
}

if (isset($_GET['fail']) && (int) $_GET['fail'] === $pageIndex) {
    http_response_code((int) ($_GET['status'] ?? 500));
    header('Content-Type: text/plain');
    echo 'SECRET-BODY: internal stack trace with token';
    exit;
}

header('Content-Type: application/json');

$watched = false;
if ($delay > 0 && $pageIndex >= $delayFrom && $pageIndex <= $delayTo) {
    if (($_GET['watch'] ?? '') === '1') {
        $watched = true;
        // Stream whitespace (valid before JSON) so that a closed client is detected.
        ignore_user_abort(true);
        while (ob_get_level() > 0) {
            ob_end_flush(); // output_buffering would keep the padding from reaching the socket
        }
        $deadline = microtime(true) + $delay / 1000;
        while (microtime(true) < $deadline) {
            usleep(20_000);
            echo ' ';
            flush();
            if (connection_aborted()) {
                $log('aborted ' . $path . ' ' . $pageIndex);
                exit;
            }
        }
    } else {
        usleep($delay * 1000);
    }
}

$items = [];
for ($i = 0; $i < ($path === '/indexed' && $pageIndex >= $pages ? 0 : $per); $i++) {
    $n = $pageIndex * $per + $i;
    $items[] = $path === '/orders' || $path === '/indexed'
        ? ['id' => $n + 1, 'status' => $n % 3 === 0 ? 'open' : 'paid', 'total' => ($n + 1) * 10]
        : $n;
}
$last = $pageIndex >= $pages - 1;

switch ($path) {
    case '/numbered':
    case '/indexed':
        echo json_encode(['data' => $items, 'page' => $pageIndex + 1, 'total_pages' => $pages]);
        break;

    case '/linked':
        if (!$last) {
            $query = $_GET;
            $query['page'] = $pageIndex + 2;
            $next = '/linked?' . http_build_query($query);
            $query['page'] = $pages;
            $lastLink = 'http://' . $_SERVER['HTTP_HOST'] . '/linked?' . http_build_query($query);
            $target = $pageIndex % 2 === 0 ? 'http://' . $_SERVER['HTTP_HOST'] . $next : ltrim($next, '/');
            header(sprintf('Link: <%s>; rel="last", <%s>; title="a, b; c"; rel="next"', $lastLink, $target));
        }
        echo json_encode($items);
        break;

    case '/headers':
        echo json_encode(['data' => [[
            'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
            'trace' => $_SERVER['HTTP_X_TRACE'] ?? null,
            'method' => $_SERVER['REQUEST_METHOD'],
            'body' => file_get_contents('php://input'),
        ]], 'next' => null]);
        break;

    default: // /cursor, /orders, /endless
        echo json_encode(['data' => $items, 'next' => ($last && $path !== '/endless') ? null : 'p' . ($pageIndex + 1)]);
}

if ($watched) {
    $log('completed ' . $path . ' ' . $pageIndex);
}
