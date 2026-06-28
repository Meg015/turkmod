<?php

declare(strict_types=1);

/**
 * Backward-compatible trigger hooks.
 *
 * The leaderboard now reads counts directly from source tables:
 * - activity_logs.user_login
 * - published topics
 * - approved comments
 *
 * These functions remain so older call sites do not break, but they no longer
 * maintain separate point/stat columns on users.
 */

function leaderboardTriggerDownload(?PDO $pdo, int $userId): void
{
}

function leaderboardTriggerTopicCreated(?PDO $pdo, int $userId): void
{
}

function leaderboardTriggerComment(?PDO $pdo, int $userId): void
{
}

function leaderboardTriggerHelpful(?PDO $pdo, int $userId): void
{
}

function leaderboardTriggerHelpfulRemoved(?PDO $pdo, int $userId): void
{
}
