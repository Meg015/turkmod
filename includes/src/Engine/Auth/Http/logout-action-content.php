<?php

declare(strict_types=1);
require_once $projectRoot . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        header('Location: ' . $baseUri . '/index.php');
        exit;
    }

    if (function_exists('logoutUser')) {
        logoutUser($pdo);
    } else {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
    }
}

header('Location: ' . $baseUri . '/index.php');
exit;
