<?php

declare(strict_types=1);

namespace App\Modules\TopicWorkflow\Http;

use App\Core\Routing\ScriptBackedHandler;

final class DownloadAction extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Modules/TopicWorkflow/Http/download-action-content.php';
    }
}
