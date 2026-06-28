<?php

declare(strict_types=1);

namespace App\Engine\Categories\Http;

use App\Core\Routing\ScriptBackedHandler;

final class CategoryPage extends ScriptBackedHandler
{
    protected function contentPath(): string
    {
        return 'includes/src/Engine/Categories/Http/category-page-content.php';
    }
}
