<?php

declare(strict_types=1);

$tab = (string)($_GET['tab'] ?? 'catalog');
if (!in_array($tab, ['catalog', 'distributed', 'manual'], true)) {
    $tab = 'catalog';
}

$pageTitle = 'Etkinlik Ödülleri';
require_once __DIR__ . '/init.php';

adminRequirePermission('events.view', 'Etkinlikleri görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequirePermission('events.manage', 'Etkinlikleri yönetmek için gerekli izin hesabınıza tanımlanmamış.');
}
ob_start();
require_once __DIR__ . '/../includes/src/Modules/Events/Admin/rewards.php';
$eventsContent = (string)ob_get_clean();
require_once __DIR__ . '/header.php';
echo $eventsContent;
require_once __DIR__ . '/footer.php';

