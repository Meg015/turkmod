<?php

declare(strict_types=1);

namespace App\Modules\BanAppeals\Http;

use App\Core\Routing\ScriptBackedHandler;

final class BanAppealsPage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Modules/BanAppeals/Http/ban-appeals-page-content.php';
    }
}
