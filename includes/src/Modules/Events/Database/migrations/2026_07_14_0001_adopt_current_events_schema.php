<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    private const TABLES = [
        'events_activity_rules',
        'events_audit_log',
        'events_config',
        'events_email_queue',
        'events_error_log',
        'events_notifications',
        'events_point_ledger',
        'events_prize_pool_items',
        'events_raffles',
        'events_raffle_draws',
        'events_raffle_entries',
        'events_raffle_items',
        'events_raffle_winners',
        'events_tasks',
        'events_task_claims',
        'events_task_requirements',
        'events_user_bonus_spins',
        'events_user_preferences',
        'events_user_rewards',
        'events_user_streaks',
        'events_user_task_progress',
        'events_wheel_rewards',
        'events_wheel_spins',
    ];

    public function name(): string
    {
        return '2026_07_14_0001_adopt_current_events_schema';
    }

    public function up(PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $missing = [];
        foreach (self::TABLES as $table) {
            $stmt->execute(['table_name' => $table]);
            if ((int) $stmt->fetchColumn() === 0) {
                $missing[] = $table;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Events şeması eksik; Database Synchronization öncesi base schema kurulmalı: ' . implode(', ', $missing));
        }

        require_once dirname(__DIR__) . '/schema-seed.php';
        eventsEnsureSchema($pdo);
    }

    public function down(PDO $pdo): void
    {
        throw new RuntimeException('Mevcut Events verileri için otomatik geri dönüş desteklenmiyor.');
    }
};
