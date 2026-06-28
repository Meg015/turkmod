<?php

declare(strict_types=1);

namespace App\Modules\Contact\Http;

use App\Core\Routing\ScriptBackedHandler;

final class ContactPage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Modules/Contact/Http/contact-page-content.php';
    }
}
