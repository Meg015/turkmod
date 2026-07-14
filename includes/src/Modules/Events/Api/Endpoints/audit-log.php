<?php

declare(strict_types=1);

require_once __DIR__ . '/_api.php';
global $pdo, $baseUri;

eventsApiMethod(['GET']);
eventsApiRequireAdmin();
eventsApiEnsureReady($pdo);
if (!eventsTableExists($pdo, 'events_audit_log')) {
    sendError('audit_log_not_ready', 'Audit log tablosu hazir degil.', 503);
}

$pagination = eventsNormalizePagination($_GET);
$count = (int)$pdo->query("SELECT COUNT(*) FROM events_audit_log")->fetchColumn();
$stmt = $pdo->prepare("SELECT * FROM events_audit_log ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue('limit', $pagination['per_page'], PDO::PARAM_INT);
$stmt->bindValue('offset', $pagination['offset'], PDO::PARAM_INT);
$stmt->execute();

sendSuccess('Audit log listelendi.', [
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
    'pagination' => [
        'current_page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $count,
        'last_page' => max(1, (int)ceil($count / $pagination['per_page'])),
    ],
]);
