<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$dispatcher = routeCompatibilityDispatcher();
$request = \App\Core\Http\Request::fromGlobals();
$response = $dispatcher->dispatch($request, \App\Modules\Leaderboard\Api\LeaderboardApi::class);
$dispatcher->emit($response);
