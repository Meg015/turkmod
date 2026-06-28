<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

$dispatcher = routeCompatibilityDispatcher();
$request = \App\Core\Http\Request::fromGlobals();
$response = $dispatcher->dispatch($request, \App\Modules\Leaderboard\Admin\LeaderboardAdminPage::class);
$dispatcher->emit($response);
