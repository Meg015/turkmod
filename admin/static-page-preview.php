<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Modules\StaticPages\Http\StaticPagePage;
use App\Modules\StaticPages\Services\StaticPageService;

require_once __DIR__ . '/init.php';

adminRequirePermission('manage_static_pages', 'Sabit sayfa önizlemesi için gerekli izin hesabınıza tanımlanmamış.');
$service = new StaticPageService($pdo);
$page = $service->find((int) ($_GET['id'] ?? 0));
if (!is_array($page)) {
    http_response_code(404);
    exit('Sayfa bulunamadı.');
}

$GLOBALS['_static_page_route'] = ['type' => 'page', 'page' => $page, 'preview' => true];
$dispatcher = routeCompatibilityDispatcher();
$dispatcher->emit($dispatcher->dispatch(Request::fromGlobals(), StaticPagePage::class));
