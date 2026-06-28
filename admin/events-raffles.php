<?php

declare(strict_types=1);

$tab = (string)($_GET['tab'] ?? 'management');
if (!in_array($tab, ['management', 'draw'], true)) {
    $tab = 'management';
}

$pageTitle = $tab === 'draw' ? 'Çekiliş Çekimi' : 'Çekiliş Yönetimi';
require_once __DIR__ . '/init.php';

adminRequirePermission('events.view', 'Etkinlikleri görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequirePermission('events.manage', 'Etkinlikleri yönetmek için gerekli izin hesabınıza tanımlanmamış.');
}
ob_start();
if ($tab === 'management') {
    require_once __DIR__ . '/../includes/src/Modules/Events/Admin/raffles.php';
} elseif ($tab === 'draw') {
    require_once __DIR__ . '/../includes/src/Modules/Events/Admin/draw.php';
}
$eventsContent = (string)ob_get_clean();
require_once __DIR__ . '/header.php';
echo $eventsContent;
require_once __DIR__ . '/footer.php';


