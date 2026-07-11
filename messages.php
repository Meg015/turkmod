<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$target = routePublicStaticUrl('messages');

$query = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
if ($query !== '') {
    $target .= '?' . $query;
}

header('Location: ' . $target, true, 301);
exit;
