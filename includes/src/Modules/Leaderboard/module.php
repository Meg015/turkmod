<?php

declare(strict_types=1);

return [
    'id' => 'leaderboard',
    'name' => 'Leaderboard',
    'version' => '0.1.0',
    'enabled' => true,
    'requires' => [],
    'requires_modules' => [],
    'routes' => __DIR__ . '/routes.php',
    'admin' => [],
    'permissions' => [
        [
            'key' => 'leaderboard.admin',
            'label' => 'Leaderboard Admin',
            'description' => 'Manage leaderboard settings, cache, and recalculation controls.',
            'group' => 'leaderboard',
            'default' => false,
        ],
    ],
    'config' => [
        'leaderboard_enabled' => [
            'label' => 'Leaderboard Enabled',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Enable or disable public leaderboard rendering.',
        ],
        'leaderboard_disabled_message' => [
            'label' => 'Disabled Message',
            'type' => 'textarea',
            'default' => 'Liderlik tablosu su anda kapali. Lutfen daha sonra tekrar kontrol edin.',
            'tooltip' => 'Public message displayed when leaderboard is disabled.',
        ],
        'leaderboard_cache_ttl_daily' => [
            'label' => 'Daily Cache TTL (seconds)',
            'type' => 'number',
            'default' => '900',
            'tooltip' => 'Cache lifetime for daily leaderboard snapshots.',
        ],
        'leaderboard_cache_ttl_weekly' => [
            'label' => 'Weekly Cache TTL (seconds)',
            'type' => 'number',
            'default' => '3600',
            'tooltip' => 'Cache lifetime for weekly leaderboard snapshots.',
        ],
        'leaderboard_cache_ttl_monthly' => [
            'label' => 'Monthly Cache TTL (seconds)',
            'type' => 'number',
            'default' => '21600',
            'tooltip' => 'Cache lifetime for monthly leaderboard snapshots.',
        ],
        'leaderboard_cache_ttl_quarterly' => [
            'label' => 'Quarterly Cache TTL (seconds)',
            'type' => 'number',
            'default' => '43200',
            'tooltip' => 'Cache lifetime for quarterly leaderboard snapshots.',
        ],
        'leaderboard_cache_ttl_yearly' => [
            'label' => 'Yearly Cache TTL (seconds)',
            'type' => 'number',
            'default' => '86400',
            'tooltip' => 'Cache lifetime for yearly leaderboard snapshots.',
        ],
        'leaderboard_cache_ttl_all_time' => [
            'label' => 'All Time Cache TTL (seconds)',
            'type' => 'number',
            'default' => '86400',
            'tooltip' => 'Cache lifetime for all-time leaderboard snapshots.',
        ],
        'leaderboard_exclude_admins' => [
            'label' => 'Exclude Admin Users',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Exclude users with admin permissions from leaderboard rankings.',
        ],
        'leaderboard_show_sidebar' => [
            'label' => 'Show Sidebar Widget',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Enable leaderboard widget visibility in sidebar surfaces.',
        ],
        'leaderboard_sidebar_limit' => [
            'label' => 'Sidebar Item Limit',
            'type' => 'number',
            'default' => '5',
            'tooltip' => 'Maximum number of leaderboard users shown in sidebar widget.',
        ],
        'leaderboard_show_profile' => [
            'label' => 'Show Profile Widget',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Enable the profile leaderboard summary widget.',
        ],
        'leaderboard_profile_limit' => [
            'label' => 'Profile Item Limit',
            'type' => 'number',
            'default' => '10',
            'tooltip' => 'Maximum number of leaderboard categories shown on profiles.',
        ],
        'leaderboard_exclude_banned' => [
            'label' => 'Exclude Banned Users',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Exclude banned users from leaderboard rankings.',
        ],
    ],
    'events' => [
        'topic.published' => [
            \App\Modules\Leaderboard\Services\LeaderboardCacheInvalidator::class,
        ],
    ],
    'lifecycle' => \App\Modules\Leaderboard\Services\LeaderboardLifecycle::class,
    'migrations' => __DIR__ . '/Database/migrations',
    'lang' => __DIR__ . '/lang',
];
