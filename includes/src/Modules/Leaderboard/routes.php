<?php

declare(strict_types=1);

return [
    'public' => [
        'leaderboard' => [
            'label' => 'Liderlik',
            'target' => \App\Modules\Leaderboard\Http\LeaderboardPage::class,
            'kind' => 'Sayfa',
            'dispatch' => 'handler',
        ],
    ],
    'admin' => [
        'leaderboard' => [
            'label' => 'Leaderboard Admin',
            'target' => \App\Modules\Leaderboard\Admin\LeaderboardAdminPage::class,
            'kind' => 'Admin',
            'dispatch' => 'handler',
        ],
    ],
    'api' => [
        'leaderboard' => [
            'label' => 'Leaderboard API',
            'target' => \App\Modules\Leaderboard\Api\LeaderboardApi::class,
            'kind' => 'API',
            'dispatch' => 'handler',
        ],
    ],
];
