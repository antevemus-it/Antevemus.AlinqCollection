<?php

/**
 * A TLS server on 127.0.0.1 with a freshly generated self-signed certificate, for the test
 * that proves fromHttpPages() verifies certificates (1.5.0, forward 027, RN-04).
 *
 * Not a test: `php tls-server.php <port> <workDir>`. Answers a valid HTTP JSON page to any
 * client that completes the handshake (a client that does not verify certificates would get
 * items; fromHttpPages() must refuse the handshake instead). Runs until it is killed.
 *
 * @version    1.5.0
 * @package    antevemus
 * @subpackage alinq.tests
 * @author     Heliton Junior
 * @copyright  Copyright (c) 2025-2026 Antevemus Soluções Inovadoras em TI Ltda. (https://antevemus.com.br)
 * @license    MIT License
 */

declare(strict_types=1);

$port = (int) ($argv[1] ?? 0);
$workDir = (string) ($argv[2] ?? sys_get_temp_dir());

$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$csr = openssl_csr_new(['commonName' => '127.0.0.1'], $key, ['digest_alg' => 'sha256']);
$certificate = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
openssl_x509_export($certificate, $certificatePem);
openssl_pkey_export($key, $keyPem);
$pem = $workDir . '/self-signed.pem';
file_put_contents($pem, $certificatePem . $keyPem);

$context = stream_context_create(['ssl' => ['local_cert' => $pem, 'verify_peer' => false]]);
$server = stream_socket_server('tls://127.0.0.1:' . $port, $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
if ($server === false) {
    fwrite(STDERR, $error);
    exit(1);
}

while (true) {
    $client = @stream_socket_accept($server, 60);
    if ($client === false) {
        continue; // failed handshake (the verifying client) or plain TCP probe
    }
    fread($client, 8192);
    $body = '{"data":[1,2,3],"next":null}';
    fwrite($client, "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
    fclose($client);
}
