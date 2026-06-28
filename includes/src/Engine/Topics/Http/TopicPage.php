<?php

declare(strict_types=1);

namespace App\Engine\Topics\Http;

use App\Core\Routing\ScriptBackedHandler;

final class TopicPage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Engine/Topics/Http/topic-page-content.php';
    }
}
