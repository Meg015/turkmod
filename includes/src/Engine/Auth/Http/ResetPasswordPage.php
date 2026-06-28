<?php

declare(strict_types=1);

namespace App\Engine\Auth\Http;

use App\Core\Routing\ScriptBackedHandler;

final class ResetPasswordPage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Engine/Auth/Http/reset-password-page-content.php';
    }
}
