<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Http;

use App\Core\Routing\ScriptBackedHandler;

final class CreateTopicPage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Modules/TopicWorkflow/Http/create-topic-page-content.php';
    }
}
