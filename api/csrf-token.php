<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/ApiResponse.php';

if (function_exists('sendNoStoreHeaders')) {
    sendNoStoreHeaders();
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    sendMethodNotAllowed(['GET', 'HEAD']);
}

$token = csrf_token();
sendSuccess('Güvenlik doğrulaması yenilendi.', [
    '_token' => $token,
    'csrf_token' => $token,
]);
