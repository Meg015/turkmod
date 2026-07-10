<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/UserActivity/Legacy/helpers.php';

adminRequirePermission('logs.view', 'Kullanıcı işlem günlüğünü görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'Kullanıcı İşlem Günlüğü';
$activityBaseRoute = 'action-log.php';
$activityBaseParams = [];
$csrfToken = csrf_token();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if ($postAction === 'clear_activity_logs') {
        $isAjax = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $scope = trim((string) ($_POST['scope'] ?? ''));
        $targetUserId = max(0, (int) ($_POST['target_user_id'] ?? 0));
        $redirectParams = array_filter($_GET, static fn ($value): bool => $value !== '' && $value !== null);

        $respond = static function (bool $ok, string $message) use ($isAjax, $redirectParams): void {
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
                exit;
            }

            flash($ok ? 'success' : 'error', $message);
            header('Location: action-log.php' . ($redirectParams !== [] ? '?' . http_build_query($redirectParams) : ''));
            exit;
        };

        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            $respond(false, 'Güvenlik doğrulaması başarısız.');
        }
        if (!adminCurrentUserCan('logs.manage')) {
            $respond(false, 'Bu işlemi yapmak için logs.manage izni gereklidir.');
        }
        if (!in_array($scope, ['older_than_30_days', 'user', 'all'], true)) {
            $respond(false, 'Geçersiz temizleme kapsamı.');
        }
        if ($scope === 'user' && $targetUserId <= 0) {
            $respond(false, 'Kullanıcı seçilmedi.');
        }

        $deleted = userActivityClear($pdo, $scope, $targetUserId > 0 ? $targetUserId : null);
        if (function_exists('appLog')) {
            appLog($pdo, 'info', 'maintenance', 'user_activity_events_cleared', [
                'scope' => $scope,
                'deleted' => $deleted,
                'target_user_id' => $targetUserId,
            ]);
        }

        $respond(true, $deleted . ' kullanıcı işlem kaydı temizlendi.');
    }
}

require_once __DIR__ . '/header.php';
?>

<?php adminRenderLogsSubtabs('action'); ?>

<div class="action-log-page logs-page">
    <section class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <span class="ui-admin-kicker"><i class="bi bi-activity"></i> Kullanıcı hareketleri</span>
            <h2>Kullanıcı İşlem Günlüğü</h2>
            <p>Kullanıcı girişleri, konu ve yorum etkileşimleri, profil değişiklikleri ve diğer detaylı hareketleri tek akışta izleyin.</p>
        </div>
    </section>

    <?php require_once __DIR__ . '/users-tabs/activity.php'; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
