<?php

declare(strict_types=1);

$GLOBALS['_skip_session_bootstrap'] = true;

require_once __DIR__ . '/../includes/init.php';

$request = \App\Core\Http\Request::fromGlobals();
$response = (new \App\Engine\Seo\Http\CspReportEndpoint())->handle($request);
$response->send();
