<?php

declare(strict_types=1);

namespace App\Modules\Messages\Http;

use App\Core\Routing\ScriptBackedHandler;

final class MessagesPage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Modules/Messages/Http/messages-page-content.php';
    }
}

