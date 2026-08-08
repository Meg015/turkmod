<?php

declare(strict_types=1);

return [
    'id' => 'static_pages',
    'name' => 'Static Pages',
    'version' => '0.1.0',
    'enabled' => true,
    'requires' => [],
    'requires_modules' => [],
    'routes' => __DIR__ . '/routes.php',
    'admin' => [
        'menu' => [
            [
                'label' => 'Sabit Sayfalar',
                'route' => 'admin/static-pages.php',
                'permission' => 'manage_static_pages',
            ],
        ],
    ],
    'permissions' => [
        [
            'key' => 'manage_static_pages',
            'label' => 'Sabit Sayfaları Yönet',
            'description' => 'Sabit sayfaları görüntüleme, oluşturma, düzenleme, yayınlama ve arşivleme izni.',
            'group' => 'static_pages',
            'default' => false,
        ],
    ],
    'events' => [],
    'lifecycle' => \App\Modules\StaticPages\Services\StaticPagesLifecycle::class,
    'migrations' => __DIR__ . '/Database/migrations',
];
