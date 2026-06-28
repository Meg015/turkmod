<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Engine\Seo\Http\NotFoundPage;

require_once __DIR__ . '/init.php';

$dispatcher = routeCompatibilityDispatcher();
$dispatcher->emit($dispatcher->dispatch(Request::fromGlobals(), new NotFoundPage(dirname(__DIR__))));
