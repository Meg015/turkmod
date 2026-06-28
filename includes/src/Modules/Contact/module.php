<?php

declare(strict_types=1);

return [
    'id' => 'contact',
    'name' => 'Contact',
    'version' => '0.1.0',
    'enabled' => true,
    'requires' => [],
    'requires_modules' => [],
    'routes' => __DIR__ . '/routes.php',
    'admin' => [
        'menu' => [
            [
                'label' => 'Iletisim',
                'route' => 'admin/contacts.php',
                'permission' => 'contact.view',
            ],
        ],
    ],
    'permissions' => [
        [
            'key' => 'contact.view',
            'label' => 'Contact View',
            'description' => 'View incoming contact messages and message details.',
            'group' => 'contact',
            'default' => false,
        ],
        [
            'key' => 'contact.manage',
            'label' => 'Contact Manage',
            'description' => 'Reply to contact messages, mark them resolved, and permanently delete them.',
            'group' => 'contact',
            'default' => false,
        ],
        [
            'key' => 'contact.categories.manage',
            'label' => 'Contact Categories Manage',
            'description' => 'Create, edit, activate, and delete contact categories.',
            'group' => 'contact',
            'default' => false,
        ],
    ],
    'events' => [],
    'lifecycle' => \App\Modules\Contact\Services\ContactLifecycle::class,
    'migrations' => __DIR__ . '/Database/migrations',
];
