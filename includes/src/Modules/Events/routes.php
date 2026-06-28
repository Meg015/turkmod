<?php

declare(strict_types=1);

$pageMap = [
    'events' => 'includes/src/Modules/Events/Pages/index.php',
    'events/wheel' => 'includes/src/Modules/Events/Pages/wheel.php',
    'events/raffle' => 'includes/src/Modules/Events/Pages/raffle.php',
    'events/rewards' => 'includes/src/Modules/Events/Pages/rewards.php',
    'events/tasks' => 'includes/src/Modules/Events/Pages/tasks.php',
];

return [
    'bootstrap' => 'includes/src/Modules/Events/init.php',
    'assets_base' => 'includes/src/Modules/Events/assets',
    'api_template' => 'includes/src/Modules/Events/Api/Legacy/%s.php',
    'page_map' => $pageMap,
    'public' => [
        'events' => [
            'label' => 'Events Dashboard',
            'target' => $pageMap['events'],
            'kind' => 'Page',
            'dispatch' => 'file',
        ],
        'events/wheel' => [
            'label' => 'Events Wheel',
            'target' => $pageMap['events/wheel'],
            'kind' => 'Page',
            'dispatch' => 'file',
        ],
        'events/raffle' => [
            'label' => 'Events Raffle',
            'target' => $pageMap['events/raffle'],
            'kind' => 'Page',
            'dispatch' => 'file',
        ],
        'events/rewards' => [
            'label' => 'Events Rewards',
            'target' => $pageMap['events/rewards'],
            'kind' => 'Page',
            'dispatch' => 'file',
        ],
        'events/tasks' => [
            'label' => 'Events Tasks',
            'target' => $pageMap['events/tasks'],
            'kind' => 'Page',
            'dispatch' => 'file',
        ],
    ],
];

