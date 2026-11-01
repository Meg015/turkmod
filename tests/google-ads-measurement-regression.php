<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/includes/autoloader.php';
require_once dirname(__DIR__) . '/includes/PublicThemeRenderer.php';
require_once dirname(__DIR__) . '/includes/src/Core/Security/SecurityHeadersMiddleware.php';

function appCspNonceAttr(): string { return ' nonce="test-nonce"'; }
function appCspNonce(): string { return 'test-nonce'; }

$checks = 0;
function measurementAssert(bool $condition, string $message): void {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
$render = new ReflectionMethod(PublicThemeRenderer::class, 'googleTagHead');
$context = [
    'settings' => ['google_analytics_id' => 'AW-753258530'],
    'env_config' => ['GOOGLE_ADS_CONVERSION_SEND_TO' => 'AW-753258530/7ALTCPDyu4gZEKKgl-cC'],
    'current_request_uri' => '/',
];
$home = $render->invoke(null, $context);
measurementAssert(substr_count($home, 'googletagmanager.com/gtag/js') === 1, 'Google library must load once');
measurementAssert(substr_count($home, 'data-ga-conversion=') === 1, 'Homepage must include one conversion destination');
measurementAssert(substr_count($home, 'nonce="test-nonce"') === 2, 'Scripts must retain the nonce');
foreach (['', 'AW-12" onload="alert(1)', 'https://example.com', 'AW-1/evil'] as $invalid) {
    $test = $context;
    $test['settings']['google_analytics_id'] = $invalid;
    measurementAssert($render->invoke(null, $test) === '', 'Invalid tag ID emitted markup');
}
foreach (['/kategori/ets-2', '/konu/test', '/giris', '/missing'] as $path) {
    $test = array_replace($context, ['current_request_uri' => $path]);
    $html = $render->invoke(null, $test);
    measurementAssert(str_contains($html, 'data-ga-id=') && !str_contains($html, 'data-ga-conversion='), 'Non-homepage emitted conversion');
}
foreach (['/?campaign=test', '/index.php', '/index.php?test=1'] as $path) {
    measurementAssert(str_contains($render->invoke(null, array_replace($context, ['current_request_uri' => $path])), 'data-ga-conversion='), 'Homepage query was not supported');
}
$test = array_replace($context, ['base_uri' => '/portal', 'current_request_uri' => '/portal/?test=1']);
measurementAssert(str_contains($render->invoke(null, $test), 'data-ga-conversion='), 'Subdirectory homepage was not supported');
foreach (['', 'AW-999/7ALTCPDyu4gZEKKgl-cC', 'AW-753258530/bad"label'] as $destination) {
    $test = $context;
    $test['env_config']['GOOGLE_ADS_CONVERSION_SEND_TO'] = $destination;
    measurementAssert(!str_contains($render->invoke(null, $test), 'data-ga-conversion='), 'Invalid or unrelated destination was accepted');
}
$test = $context;
$test['settings']['google_analytics_id'] = 'G-TEST123';
measurementAssert(!str_contains($render->invoke(null, $test), 'data-ga-conversion='), 'Analytics property emitted Ads conversion');
$headers = App\Core\Security\SecurityHeadersMiddleware::fromEnvConfig(['APP_ENV' => 'production'])->buildHeaders();
$csp = implode("\n", $headers);
foreach (['https://www.googletagmanager.com', 'https://www.googleadservices.com', 'https://www.google.com', "'nonce-test-nonce'"] as $source) {
    measurementAssert(str_contains($csp, $source), 'Required Google source or nonce is missing');
}
echo "PASS: $checks measurement checks\n";
