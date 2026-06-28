<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http;

use App\Core\Routing\ScriptBackedHandler;

final class NotificationsPage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Modules/Notifications/Http/notifications-page-content.php';
    }
}
