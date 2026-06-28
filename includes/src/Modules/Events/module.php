<?php

declare(strict_types=1);

return [
    'id' => 'events',
    'name' => 'Etkinlikler',
    'version' => '0.1.0',
    'enabled' => true,
    'requires' => [],
    'requires_modules' => [],
    'routes' => __DIR__ . '/routes.php',
    'admin' => [
        'menu' => [
            'Etkinlikler' => 'admin/events.php',
            'Etkinlik Ayarları' => 'admin/events-settings.php',
            'Çark' => 'admin/events-wheel.php',
            'Çekilişler' => 'admin/events-raffles.php',
            'Görevler' => 'admin/events-tasks.php',
            'Ödüller' => 'admin/events-rewards.php',
            'Bekleyen Ödüller' => 'admin/events-pending.php',
            'Etkinlik Logları' => 'admin/events-audit-log.php',
            'Etkinlik İstatistikleri' => 'admin/events-stats.php',
        ],
    ],
    'permissions' => [
        [
            'key' => 'events.view',
            'label' => 'Etkinlikleri görüntüleme',
            'description' => 'Etkinlik sayfalarını ve admin etkinlik yüzeylerini görüntüleme.',
            'group' => 'events',
            'default' => false,
        ],
        [
            'key' => 'events.manage',
            'label' => 'Etkinlikleri yönetme',
            'description' => 'Etkinlik ayarlarını, ödülleri, çekilişleri ve görevleri yönetme.',
            'group' => 'events',
            'default' => false,
        ],
    ],
    'config' => [
        'events_system_enabled' => [
            'label' => 'Etkinlikler açık',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Etkinlik modülünü genel olarak açar veya kapatır.',
        ],
        'events_system_disabled_message' => [
            'label' => 'Kapalıyken mesaj',
            'type' => 'textarea',
            'default' => 'Etkinlik sistemi şu anda kısa süreliğine kapalı.',
            'tooltip' => 'Etkinlikler kapalıyken kullanıcılara gösterilecek mesaj.',
        ],
        'events_wheel_enabled' => [
            'label' => 'Çark açık',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Kullanıcıların /events/wheel sayfasında çark kullanmasına izin verir.',
        ],
        'events_raffles_enabled' => [
            'label' => 'Çekilişler açık',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Çekiliş listesini ve katılım işlemlerini açar.',
        ],
        'events_tasks_enabled' => [
            'label' => 'Görevler açık',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Görev sayfasını ve görev ödülü alma işlemlerini açar.',
        ],
        'events_rewards_enabled' => [
            'label' => 'Ödüller açık',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Ödül kasası sayfasını ve ödül işlemlerini açar.',
        ],
        'events_activity_points_enabled' => [
            'label' => 'Aktivite puanları açık',
            'type' => 'bool',
            'default' => '1',
            'tooltip' => 'Aktivite hookları üzerinden puan defterine yazmayı açar.',
        ],
        'api_rate_limit_window' => [
            'label' => 'API limit aralığı',
            'type' => 'number',
            'default' => '60',
            'tooltip' => 'API hız sınırının değerlendirileceği süre aralığı.',
        ],
        'api_rate_limit_max' => [
            'label' => 'API maksimum istek',
            'type' => 'number',
            'default' => '45',
            'tooltip' => 'Belirlenen aralıkta izin verilen en yüksek API istek sayısı.',
        ],
    ],
    'events' => [],
    'lifecycle' => \App\Modules\Events\Services\EventsLifecycle::class,
    'migrations' => __DIR__ . '/Database/migrations',
    'lang' => __DIR__ . '/lang',
];
