<?php

declare(strict_types=1);

return [
    'id' => 'messages',
    'name' => 'Messages',
    'version' => '0.1.0',
    'enabled' => true,
    'requires' => [],
    'requires_modules' => [],
    'routes' => __DIR__ . '/routes.php',
    'permissions' => [],
    'events' => [],
    'lifecycle' => \App\Modules\Messages\Services\MessagesLifecycle::class,
    'migrations' => __DIR__ . '/Database/migrations',
];
