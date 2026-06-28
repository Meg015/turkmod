<?php

declare(strict_types=1);

return [
    'id' => 'ban_appeals',
    'name' => 'Ban Appeals',
    'version' => '0.1.0',
    'enabled' => true,
    'requires' => [],
    'requires_modules' => ['notifications'],
    'routes' => __DIR__ . '/routes.php',
    'admin' => [
        'menu' => [
            [
                'label' => 'Ban Appeals',
                'route' => 'admin/users.php?tab=appeals',
                'permission' => 'ban_appeals.view',
            ],
        ],
    ],
    'permissions' => [
        [
            'key' => 'ban_appeals.view',
            'label' => 'Ban Appeals View',
            'description' => 'View ban appeal history and moderation queues.',
            'group' => 'ban_appeals',
            'default' => false,
        ],
        [
            'key' => 'ban_appeals.manage',
            'label' => 'Ban Appeals Manage',
            'description' => 'Review, accept, reject, and respond to ban appeals.',
            'group' => 'ban_appeals',
            'default' => false,
        ],
        [
            'key' => 'ban_appeals.create',
            'label' => 'Ban Appeals Create',
            'description' => 'Create and reply to own ban appeals.',
            'group' => 'ban_appeals',
            'default' => true,
        ],
    ],
    'config' => [],
    'events' => [
        'ban_appeal.created' => [],
        'ban_appeal.message_created' => [],
        'ban_appeal.updated' => [],
    ],
    'lifecycle' => \App\Modules\BanAppeals\Services\BanAppealsLifecycle::class,
    'migrations' => __DIR__ . '/Database/migrations',
    'lang' => __DIR__ . '/lang',
];
