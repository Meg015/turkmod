<?php

declare(strict_types=1);

namespace App\Modules\Leaderboard\Http;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;

final class LeaderboardPage implements Handler
{
    public function handle(Request $request): Response
    {
        $originalQuery = isset($_GET) && is_array($_GET) ? $_GET : [];
        $_GET = array_merge($originalQuery, $request->getQuery());

        ob_start();
        try {
            require __DIR__ . '/leaderboard-page-content.php';
            $body = ob_get_clean();
        } finally {
            $_GET = $originalQuery;
        }

        return new Response(is_string($body) ? $body : '');
    }
}
