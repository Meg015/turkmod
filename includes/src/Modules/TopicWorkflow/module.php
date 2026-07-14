<?php

declare(strict_types=1);

return [
    'id' => 'topic-workflow',
    'name' => 'Topic Workflow',
    'version' => '0.1.0',
    'enabled' => true,
    'requires' => [],
    'requires_modules' => [],
    'routes' => __DIR__ . '/routes.php',
    'events' => [
        'topic.created' => [
            \App\Modules\TopicWorkflow\Listeners\TopicCreatedActivityListener::class,
            \App\Modules\TopicWorkflow\Listeners\TopicCreatedAuditLogListener::class,
        ],
        'topic.updated' => [
            \App\Modules\TopicWorkflow\Listeners\TopicUpdatedAuditLogListener::class,
        ],
    ],
];
