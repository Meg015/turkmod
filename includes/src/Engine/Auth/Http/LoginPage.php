<?php

declare(strict_types=1);

namespace App\Engine\Auth\Http;

use App\Core\Routing\ScriptBackedHandler;

final class LoginPage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Engine/Auth/Http/login-page-content.php';
    }
}
