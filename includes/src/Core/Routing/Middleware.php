<?php

declare(strict_types=1);

namespace App\Core\Routing;

use App\Core\Http\Request;
use App\Core\Http\Response;

interface Middleware
{
    public function process(Request $request, Handler $next): Response;
}
