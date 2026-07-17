<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/UserActivity/Support/helpers.php';

adminRequirePermission('logs.view', 'Kullanıcı işlem günlüğünü görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'Kullanıcı İşlem Günlüğü';
$activityBaseRoute = 'action-log.php';
$activityBaseParams = [];
$csrfToken = csrf_token();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if ($postAction === 'clear_activity_logs') {
        $scope = trim((string) ($_POST['scope'] ?? ''));
        $targetUserId = max(0, (int) ($_POST['target_user_id'] ?? 0));
        $redirectParams = array_filter($_GET, static fn ($value): bool => $value !== '' && $value !== null);
        $redirectUrl = 'action-log.php' . ($redirectParams !== [] ? '?' . http_build_query($redirectParams) : '');

        adminRunLogCleanup($pdo, [
            'action_type' => 'activity_logs_cleared',
            'scope' => $scope,
            'allowed_scopes' => ['older_than_30_days', 'user', 'all'],
            'permission' => 'logs.manage',
            'permission_message' => 'Bu işlemi yapmak için logs.manage izni gereklidir.',
            'redirect_url' => $redirectUrl,
            'source' => 'action_log',
            'validate' => static fn (string $scope): string => $scope === 'user' && $targetUserId <= 0 ? 'Kullanıcı seçilmedi.' : '',
            'delete' => static fn (PDO $pdo, string $scope): int => userActivityClear($pdo, $scope, $targetUserId > 0 ? $targetUserId : null),
            'context' => [
                'target_user_id' => $targetUserId > 0 ? $targetUserId : null,
            ],
            'app_log' => true,
            'app_log_message' => 'user_activity_events_cleared',
            'success_message' => static fn (int $deleted): string => $deleted . ' kullanıcı işlem kaydı temizlendi.',
        ]);
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
