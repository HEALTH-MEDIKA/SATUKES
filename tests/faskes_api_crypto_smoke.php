<?php
define('BASEPATH', __DIR__);
require dirname(__DIR__).'/application/libraries/FaskesApiClient.php';

$client = new FaskesApiClient();
$reflection = new ReflectionClass($client);
$keysMethod = $reflection->getMethod('keys');
$envelopeMethod = $reflection->getMethod('envelope');
$decryptMethod = $reflection->getMethod('decryptEnvelope');
$keysMethod->setAccessible(TRUE); $envelopeMethod->setAccessible(TRUE); $decryptMethod->setAccessible(TRUE);

$secret = 'test-secret-that-is-at-least-thirty-two-bytes-long';
$consId = 'faskes-test-001';
$keys = $keysMethod->invoke($client, $secret, $consId);
$aad = '1|'.$consId.'|1788842400|00000000-0000-4000-8000-000000000001|00000000-0000-4000-8000-000000000002';
$payload = array('date_from'=>'2026-09-01','date_to'=>'2026-09-07');
$envelope = $envelopeMethod->invoke($client, $payload, $keys['encryption'], $aad);
$decrypted = $decryptMethod->invoke($client, $envelope, $keys['encryption'], $aad);
if ($decrypted !== $payload) { fwrite(STDERR, "Crypto round-trip gagal\n"); exit(1); }
$envelope['tag'][0] = $envelope['tag'][0] === 'A' ? 'B' : 'A';
try { $decryptMethod->invoke($client, $envelope, $keys['encryption'], $aad); fwrite(STDERR, "Tampered tag tidak ditolak\n"); exit(1); }
catch (ReflectionException $e) { throw $e; }
catch (Throwable $e) { /* expected */ }
echo "Faskes API crypto smoke test passed\n";
