<?php
declare(strict_types=1);

/**
 * Compatibility-only patches for the locked Ratchet 0.4.4 / RFC6455 0.3.1.
 * Their stable releases still use implicitly nullable parameters (PHP 8.4)
 * and deprecated SplObjectStorage aliases/non-canonical casts (PHP 8.5). Composer reapplies these
 * small, idempotent changes after every install/update; no protocol behavior
 * or dependency constraint is changed. Review/remove them on package upgrades.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$vendor = dirname(__DIR__) . '/vendor/';
$nullableRequest = [' RequestInterface $request = null' => ' ?RequestInterface $request = null'];
$storageAliases = [
    '->attach(' => '->offsetSet(',
    '->contains(' => '->offsetExists(',
    '->detach(' => '->offsetUnset(',
];
$patches = [
    'cboden/ratchet/src/Ratchet/App.php' => [' LoopInterface $loop = null' => ' ?LoopInterface $loop = null'],
    'cboden/ratchet/src/Ratchet/Server/IoServer.php' => [' LoopInterface $loop = null' => ' ?LoopInterface $loop = null'],
    'cboden/ratchet/src/Ratchet/Http/HttpServerInterface.php' => $nullableRequest,
    'cboden/ratchet/src/Ratchet/Http/NoOpHttpServerController.php' => $nullableRequest,
    'cboden/ratchet/src/Ratchet/Http/OriginCheck.php' => $nullableRequest,
    'cboden/ratchet/src/Ratchet/Http/Router.php' => $nullableRequest,
    'cboden/ratchet/src/Ratchet/Http/HttpRequestParser.php' => ['(boolean)' => '(bool)'],
    'cboden/ratchet/src/Ratchet/Server/FlashPolicy.php' => ['(boolean)' => '(bool)'],
    'cboden/ratchet/src/Ratchet/Session/SessionProvider.php' => $nullableRequest + [' HandlerInterface $serializer = null' => ' ?HandlerInterface $serializer = null'],
    'cboden/ratchet/src/Ratchet/WebSocket/WsServer.php' => $nullableRequest + $storageAliases,
    'cboden/ratchet/src/Ratchet/Wamp/Topic.php' => $storageAliases,
    'cboden/ratchet/src/Ratchet/Wamp/ServerProtocol.php' => ['->attach(' => '->offsetSet(', '->detach(' => '->offsetUnset(', '(boolean)' => '(bool)'],
    'cboden/ratchet/src/Ratchet/Wamp/TopicManager.php' => $storageAliases,
    'ratchet/rfc6455/src/Handshake/ClientNegotiator.php' => ['__construct(PermessageDeflateOptions $perMessageDeflateOptions = null)' => '__construct(?PermessageDeflateOptions $perMessageDeflateOptions = null)'],
    'ratchet/rfc6455/src/Handshake/ServerNegotiator.php' => ['(boolean)' => '(bool)'],
    'ratchet/rfc6455/src/Handshake/RequestVerifier.php' => ['(double)' => '(float)'],
    'ratchet/rfc6455/src/Messaging/Frame.php' => [' callable $ufExceptionFactory = null' => ' ?callable $ufExceptionFactory = null'],
    'ratchet/rfc6455/src/Messaging/MessageBuffer.php' => [
        '        callable $onControl = null' => '        ?callable $onControl = null',
        '        callable $sender = null' => '        ?callable $sender = null',
        '        PermessageDeflateOptions $permessageDeflateOptions = null' => '        ?PermessageDeflateOptions $permessageDeflateOptions = null',
    ],
];

// Validate every expected fragment before writing any files. A changed upstream
// layout must fail visibly instead of silently deploying an unpatched version.
$updates = [];
foreach ($patches as $relativePath => $replacements) {
    $path = $vendor . $relativePath;
    $source = is_file($path) ? file_get_contents($path) : false;
    if ($source === false) {
        fwrite(STDERR, "Missing dependency: {$relativePath}. Run composer install.\n");
        exit(1);
    }
    $patched = $source;
    foreach ($replacements as $before => $after) {
        if (str_contains($patched, $before)) {
            $patched = str_replace($before, $after, $patched);
        } elseif (!str_contains($patched, $after)) {
            fwrite(STDERR, "Review the PHP compatibility patch for {$relativePath}: upstream source changed.\n");
            exit(1);
        }
    }
    if ($patched !== $source) $updates[$path] = $patched;
}
foreach ($updates as $path => $source) {
    if (file_put_contents($path, $source, LOCK_EX) !== strlen($source)) {
        fwrite(STDERR, "Could not patch {$path}. Check vendor directory permissions.\n");
        exit(1);
    }
}
fwrite(STDOUT, 'PHP compatibility patches ready (' . count($updates) . " dependency files updated).\n");
