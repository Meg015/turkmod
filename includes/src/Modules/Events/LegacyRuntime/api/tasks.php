<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
$userId = eventsApiRequireAuth();
eventsApiEnsureReady($pdo, 'tasks');

if (!eventsTasksTablesReady($pdo)) {
    sendError('events_tasks_schema_missing', 'Gorev tablolari hazir degil. database/schema.sql kurulumu tamamlanmali.', 503);
}

$data = eventsGetUserTasks($pdo, $userId);
sendSuccess('Görevler listelendi.', ['data' => $data]);
