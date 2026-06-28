<?php
/**
 * Database Optimization for Events Module
 */

/**
 * Optimize database tables
 */
function eventsOptimizeTables($pdo) {
    $tables = [
        'events_users',
        'events_tasks',
        'events_task_progress',
        'events_rewards',
        'events_raffles',
        'events_raffle_entries',
        'events_wheel_spins',
        'events_activity_log',
        'events_notifications',
        'events_email_queue'
    ];

    $results = [];
    foreach ($tables as $table) {
        try {
            $pdo->exec("OPTIMIZE TABLE $table");
            $results[$table] = 'optimized';
        } catch (Throwable $e) {
            $results[$table] = 'error: ' . $e->getMessage();
        }
    }

    return $results;
}

/**
 * Analyze tables for query optimization
 */
function eventsAnalyzeTables($pdo) {
    $tables = [
        'events_users',
        'events_tasks',
        'events_task_progress',
        'events_rewards',
        'events_raffles',
        'events_raffle_entries',
        'events_wheel_spins',
        'events_activity_log'
    ];

    $results = [];
    foreach ($tables as $table) {
        try {
            $pdo->exec("ANALYZE TABLE $table");
            $results[$table] = 'analyzed';
        } catch (Throwable $e) {
            $results[$table] = 'error: ' . $e->getMessage();
        }
    }

    return $results;
}

/**
 * Create recommended indexes
 */
function eventsCreateIndexes($pdo) {
    if (function_exists('runtimeSchemaUpdatesAllowed') && !runtimeSchemaUpdatesAllowed()) {
        return ['schema_updates' => 'disabled'];
    }

    $indexes = [
        // User queries
        "ALTER TABLE events_users ADD INDEX idx_user_id (user_id)" => "User ID index",
        "ALTER TABLE events_users ADD INDEX idx_created_at (created_at)" => "Created at index",

        // Task queries
        "ALTER TABLE events_tasks ADD INDEX idx_is_active (is_active)" => "Active tasks index",
        "ALTER TABLE events_tasks ADD INDEX idx_period (period_key)" => "Period index",

        // Task progress queries
        "ALTER TABLE events_task_progress ADD INDEX idx_user_task (user_id, task_id)" => "User-task index",
        "ALTER TABLE events_task_progress ADD INDEX idx_is_completed (is_completed)" => "Completed index",

        // Reward queries
        "ALTER TABLE events_rewards ADD INDEX idx_user_status (user_id, status)" => "User-status index",
        "ALTER TABLE events_rewards ADD INDEX idx_created_at (created_at)" => "Created at index",

        // Raffle queries
        "ALTER TABLE events_raffles ADD INDEX idx_status (status)" => "Raffle status index",
        "ALTER TABLE events_raffles ADD INDEX idx_end_date (end_date)" => "End date index",

        // Raffle entries
        "ALTER TABLE events_raffle_entries ADD INDEX idx_user_raffle (user_id, raffle_id)" => "User-raffle index",

        // Wheel spins
        "ALTER TABLE events_wheel_spins ADD INDEX idx_user_date (user_id, created_at)" => "User-date index",

        // Activity log
        "ALTER TABLE events_activity_log ADD INDEX idx_user_type (user_id, activity_type)" => "User-type index",
        "ALTER TABLE events_activity_log ADD INDEX idx_created_at (created_at)" => "Created at index",

        // Notifications
        "ALTER TABLE events_notifications ADD INDEX idx_user_read (user_id, is_read)" => "User-read index",
        "ALTER TABLE events_notifications ADD INDEX idx_created_at (created_at)" => "Created at index"
    ];

    $results = [];
    foreach ($indexes as $sql => $description) {
        try {
            $pdo->exec($sql);
            $results[$description] = 'created';
        } catch (Throwable $e) {
            // Index might already exist
            $results[$description] = 'exists or error';
        }
    }

    return $results;
}

/**
 * Archive old data
 */
function eventsArchiveOldData($pdo, $daysOld = 90) {
    $results = [];

    try {
        // Archive old activity logs
        $stmt = $pdo->prepare("
            INSERT INTO events_activity_log_archive
            SELECT * FROM events_activity_log
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$daysOld]);
        $results['activity_log_archived'] = $stmt->rowCount();

        // Delete archived logs
        $stmt = $pdo->prepare("
            DELETE FROM events_activity_log
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$daysOld]);
        $results['activity_log_deleted'] = $stmt->rowCount();

        // Archive old wheel spins
        $stmt = $pdo->prepare("
            INSERT INTO events_wheel_spins_archive
            SELECT * FROM events_wheel_spins
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$daysOld]);
        $results['wheel_spins_archived'] = $stmt->rowCount();

        // Delete archived spins
        $stmt = $pdo->prepare("
            DELETE FROM events_wheel_spins
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$daysOld]);
        $results['wheel_spins_deleted'] = $stmt->rowCount();

    } catch (Throwable $e) {
        $results['error'] = $e->getMessage();
    }

    return $results;
}

/**
 * Get database statistics
 */
function eventsGetDatabaseStats($pdo) {
    $stats = [];

    try {
        // Table sizes
        $stmt = $pdo->query("
            SELECT
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
            AND table_name LIKE 'events_%'
            ORDER BY (data_length + index_length) DESC
        ");

        $stats['table_sizes'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Row counts
        $stmt = $pdo->query("
            SELECT
                table_name,
                table_rows
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
            AND table_name LIKE 'events_%'
            ORDER BY table_rows DESC
        ");

        $stats['row_counts'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } catch (Throwable $e) {
        $stats['error'] = $e->getMessage();
    }

    return $stats;
}

/**
 * Enable query caching
 */
function eventsEnableQueryCache($pdo) {
    try {
        // Set cache expiration for common queries
        $pdo->exec("SET SESSION query_cache_type = ON");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
?>
