<?php

declare(strict_types=1);

use App\Core\Database\Migration;

return new class implements Migration
{
    public function name(): string
    {
        return '2026_06_17_0001_create_leaderboard_tables';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `leaderboard_cache` (
              `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` BIGINT(20) UNSIGNED NOT NULL,
              `category` VARCHAR(50) NOT NULL,
              `period` VARCHAR(20) NOT NULL,
              `rank` INT(10) UNSIGNED NOT NULL,
              `score` DECIMAL(15,2) NOT NULL,
              `metadata` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
              `calculated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `period_start` DATE NOT NULL,
              `period_end` DATE NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `unique_user_category_period` (`user_id`,`category`,`period`,`period_start`),
              KEY `idx_category_period_rank` (`category`,`period`,`rank`),
              KEY `idx_user_category` (`user_id`,`category`),
              KEY `idx_calculated_at` (`calculated_at`),
              CONSTRAINT `leaderboard_cache_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `leaderboard_history` (
              `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` BIGINT(20) UNSIGNED NOT NULL,
              `category` VARCHAR(50) NOT NULL,
              `period` VARCHAR(20) NOT NULL,
              `rank` INT(10) UNSIGNED NOT NULL,
              `score` DECIMAL(15,2) NOT NULL,
              `snapshot_date` DATE NOT NULL,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_user_snapshot` (`user_id`,`snapshot_date`),
              KEY `idx_category_snapshot` (`category`,`snapshot_date`),
              CONSTRAINT `leaderboard_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `leaderboard_history`');
        $pdo->exec('DROP TABLE IF EXISTS `leaderboard_cache`');
    }
};
