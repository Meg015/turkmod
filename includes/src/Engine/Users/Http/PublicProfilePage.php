<?php

declare(strict_types=1);

namespace App\Engine\Users\Http;

use App\Core\Routing\ScriptBackedHandler;

final class PublicProfilePage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Engine/Users/Http/public-profile-page-content.php';
    }
}
