<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$params = [];
parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $params);
unset($params['tab']);

$target = 'complaints-reports.php?tab=users';
if ($params !== []) {
    $target .= '&' . http_build_query($params);
}

header('Location: ' . $target, true, 302);
exit;
