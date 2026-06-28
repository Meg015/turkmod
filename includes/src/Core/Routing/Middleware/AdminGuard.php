<?php

declare(strict_types=1);

namespace App\Core\Routing\Middleware;

use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Routing\Handler;
use App\Core\Routing\Middleware;
use PDO;

final class AdminGuard implements Middleware
{
    public function process(Request $request, Handler $next): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        global $pdo, $baseUri;
        $base = rtrim((string) ($baseUri ?? ''), '/');
        $loginUrl = function_exists('routePublicStaticUrl')
            ? routePublicStaticUrl('login')
            : $base . '/giris';
        $indexUrl = $base . '/index.php';
        $userId = (int) ($_SESSION['_auth_user_id'] ?? 0);

        if ($userId <= 0) {
            return new RedirectResponse($loginUrl . '?redirect=' . rawurlencode($request->getUri()));
        }

        if (function_exists('refreshAuthenticatedSession') && !refreshAuthenticatedSession($pdo ?? null)) {
            if (function_exists('logoutUser')) {
                logoutUser($pdo ?? null);
            }

            return new RedirectResponse($loginUrl);
        }

        $hasAdminAccess = function_exists('userHasPermission')
            && userHasPermission($pdo ?? null, $userId, 'admin.access');

        if (!$hasAdminAccess) {
            if (function_exists('flash')) {
                flash('error', 'Bu sayfaya erisim yetkiniz yok');
            }

            if (($pdo ?? null) instanceof PDO && function_exists('logUnauthorizedAccess')) {
                logUnauthorizedAccess($pdo, $request->getPath(), 'admin_guard_denied');
            }

            return new RedirectResponse($indexUrl);
        }

        return $next->handle($request);
    }
}
