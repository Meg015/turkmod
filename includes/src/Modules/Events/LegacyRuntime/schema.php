<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/tasks.php';

if (!function_exists('eventsSchemaTableExists')) {
    function eventsSchemaTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);

        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('eventsEnsureSchema')) {
    function eventsEnsureSchema(PDO $pdo): void
    {
        if (eventsSchemaTableExists($pdo, 'events_config')) {
            eventsSeedDefaultConfig($pdo);
        }

        if (eventsSchemaTableExists($pdo, 'events_activity_rules')) {
            eventsSeedDefaultActivityRules($pdo);
        }
    }
}
