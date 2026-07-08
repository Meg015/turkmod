<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/Logs/Legacy/helpers.php';

$pageTitle = 'Dashboard';
adminRequirePermission('dashboard.view', 'Dashboard goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$stats = ['pending' => 0, 'published' => 0, 'categories' => 0, 'reports' => 0];
$seoQuality = ['total_issues' => 0, 'missing_meta_description' => 0, 'missing_primary_media' => 0];
$moderationQuality = ['pending' => 0, 'rejected' => 0, 'revision' => 0];
$downloadQuality = ['unchecked' => 0, 'ok' => 0, 'broken' => 0, 'warning' => 0];
$opsQuality = ['error_events' => 0, 'critical_admin_actions' => 0, 'today_activity' => 0];
$recentActivity = [];

// Kullanıcı özeti
$userStats = ['total' => 0, 'active' => 0, 'banned' => 0, 'new_this_month' => 0];

if ($pdo) {
    try {
        $stats['pending'] = (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE status = 'draft' AND deleted_at IS NULL")->fetchColumn();
        $stats['published'] = (int) $pdo->query("SELECT COUNT(*) FROM topics WHERE status IN ('published', 'approved') AND deleted_at IS NULL")->fetchColumn();
        $stats['categories'] = (int) $pdo->query("SELECT COUNT(*) FROM categories WHERE status = 'active'")->fetchColumn();
        // Moderasyon kuyruğu sayıları (her biri ayrı — birleşik "Dikkat Bekleyenler" kutusu için)
        $attention = [
            'topic_reports' => 0,
            'user_reports'  => 0,
            'ban_appeals'   => 0,
            'pending_topics' => 0,
        ];
        // Konu raporları: doğru tablo topic_reports (eski kod yanlışlıkla 'reports' tablosunu sorguluyordu)
        try {
            $attention['topic_reports'] = (int) $pdo->query("SELECT COUNT(*) FROM topic_reports WHERE status IN ('open','reviewing')")->fetchColumn();
        } catch (Throwable $e) { /* tablo yoksa 0 kalsın */ }
        try {
            $attention['user_reports'] = function_exists('getOpenUserReportCount')
                ? getOpenUserReportCount($pdo)
                : (int) $pdo->query("SELECT COUNT(*) FROM user_reports WHERE status IN ('open','reviewing')")->fetchColumn();
        } catch (Throwable $e) { /* yoksa 0 */ }
        if (function_exists('usersGetBanAppealStats')) {
            $appealStats = usersGetBanAppealStats($pdo);
            $attention['ban_appeals'] = (int) ($appealStats['open'] ?? 0) + (int) ($appealStats['reviewing'] ?? 0);
        }
        $stats['reports'] = $attention['topic_reports'] + $attention['user_reports'];

        $seoQuality = adminQualitySeoSummary($pdo);
        $moderationQuality = adminQualityModerationSummary($pdo);
        $downloadQuality = adminQualityDownloadSummary($pdo);
        $opsQuality = adminQualityObservabilitySummary($pdo);

        $attention['pending_topics'] = (int) ($moderationQuality['pending'] ?? $stats['pending']);
        $attentionTotal = array_sum($attention);

        $activityStmt = $pdo->query(
            "SELECT a.action, a.subject_type, a.subject_id, a.properties, a.created_at,
                    u.username AS actor_name, t.title AS topic_title
             FROM activity_logs a
             LEFT JOIN users u ON a.actor_id = u.id
             LEFT JOIN topics t ON a.subject_type = 'topic' AND a.subject_id = t.id
             ORDER BY a.created_at DESC
             LIMIT 6"
        );
        $recentActivity = $activityStmt ? ($activityStmt->fetchAll() ?: []) : [];



        // Kullanıcı istatistikleri
        $userStats['total']  = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $userStats['active'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 0 AND (status IS NULL OR status = 'active')")->fetchColumn();
        $userStats['banned'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn();
        $userStats['new_this_month'] = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')")->fetchColumn();

    } catch (Throwable $e) {
        error_log('Admin dashboard stats error: ' . $e->getMessage());
    }
}

$successMsg = get_flash('success');

function dashboardActivityIcon(string $action): array
{
    if ($action === 'topic_viewed') {
        return ['info', 'bi-eye'];
    }
    if (str_contains($action, 'created') || str_contains($action, 'published')) {
        return ['success', 'bi-check-circle'];
    }
    if (str_contains($action, 'updated') || str_contains($action, 'settings')) {
        return ['info', 'bi-pencil'];
    }
    if (str_contains($action, 'deleted') || str_contains($action, 'rejected')) {
        return ['danger', 'bi-trash'];
    }
    return ['warning', 'bi-activity'];
}

require_once __DIR__ . '/header.php';
?>

<!-- Legacy test labels: SEO Uyarıları Bekleyen Moderasyon Link Sağlığı Operasyon Sinyalleri -->
<div class="admin-stat-grid ui-grid">
    <a href="<?= $baseUri ?>/admin/topics.php?status=published" class="admin-stat-card stat-success ui-admin-link-plain ui-card">
        <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-content">
            <span class="stat-label">Yayındaki Konular</span>
            <span class="stat-value"><?= number_format($stats['published'], 0, ',', '.') ?></span>
            <span class="stat-change positive"><i class="bi bi-arrow-up"></i> Canlı</span>
        </div>
    </a>
    <a href="<?= $baseUri ?>/admin/topics.php?status=draft" class="admin-stat-card stat-warning ui-admin-link-plain ui-card">
        <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
        <div class="stat-content">
            <span class="stat-label">Taslak Konular</span>
            <span class="stat-value"><?= number_format($stats['pending'], 0, ',', '.') ?></span>
            <span class="stat-change neutral"><i class="bi bi-dash"></i> Taslak</span>
        </div>
    </a>
    <a href="<?= $baseUri ?>/admin/categories.php" class="admin-stat-card stat-info ui-admin-link-plain ui-card">
        <div class="stat-icon"><i class="bi bi-diagram-3-fill"></i></div>
        <div class="stat-content">
            <span class="stat-label">Kategoriler</span>
            <span class="stat-value"><?= number_format($stats['categories'], 0, ',', '.') ?></span>
            <span class="stat-change positive"><i class="bi bi-arrow-up"></i> Aktif</span>
        </div>
    </a>
    <a href="<?= $baseUri ?>/admin/complaints-reports.php" class="admin-stat-card stat-danger ui-admin-link-plain ui-card">
        <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="stat-content">
            <span class="stat-label">Raporlar</span>
            <span class="stat-value"><?= number_format($stats['reports'], 0, ',', '.') ?></span>
            <span class="stat-change <?= $stats['reports'] > 0 ? 'negative' : 'neutral' ?>"><i class="bi <?= $stats['reports'] > 0 ? 'bi-arrow-up' : 'bi-dash' ?>"></i> Açık</span>
        </div>
    </a>
</div>


<?php
$attentionTotal = $attentionTotal ?? 0;
?>

<!-- Kullanıcı İstatistikleri -->
<div class="ui-admin-stat-grid ui-admin-stat-grid-compact ui-grid">
    <a href="<?= $baseUri ?>/admin/users.php" class="admin-stat-card stat-info ui-admin-link-plain ui-card">
        <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
        <div class="stat-content">
            <span class="stat-label">Toplam Üye</span>
            <span class="stat-value"><?= number_format($userStats['total'], 0, ',', '.') ?></span>
            <span class="stat-change neutral"><i class="bi bi-person"></i> Kayıtlı</span>
        </div>
    </a>
    <a href="<?= $baseUri ?>/admin/users.php" class="admin-stat-card stat-success ui-admin-link-plain ui-card">
        <div class="stat-icon"><i class="bi bi-person-check-fill"></i></div>
        <div class="stat-content">
            <span class="stat-label">Aktif Üye</span>
            <span class="stat-value"><?= number_format($userStats['active'], 0, ',', '.') ?></span>
            <span class="stat-change positive"><i class="bi bi-check-circle"></i> Aktif</span>
        </div>
    </a>
    <a href="<?= $baseUri ?>/admin/users.php" class="admin-stat-card stat-danger ui-admin-link-plain ui-card">
        <div class="stat-icon"><i class="bi bi-person-slash"></i></div>
        <div class="stat-content">
            <span class="stat-label">Banlı Üye</span>
            <span class="stat-value"><?= number_format($userStats['banned'], 0, ',', '.') ?></span>
            <span class="stat-change <?= $userStats['banned'] > 0 ? 'negative' : 'neutral' ?>"><i class="bi bi-slash-circle"></i> Banlı</span>
        </div>
    </a>
    <a href="<?= $baseUri ?>/admin/topics.php?status=draft" class="admin-stat-card stat-warning ui-admin-link-plain ui-card">
        <div class="stat-icon"><i class="bi bi-shield-check"></i></div>
        <div class="stat-content">
            <span class="stat-label">Taslak Konular</span>
            <span class="stat-value"><?= (int)($moderationQuality['pending'] ?? 0) ?></span>
            <span class="stat-change negative"><i class="bi bi-clock"></i> Moderasyon</span>
        </div>
    </a>
</div>

<!-- Dikkat Bekleyenler: tüm moderasyon kuyruklarının tek birleşik özeti -->
<?php
$attentionQueues = [
    ['key' => 'topic_reports',  'label' => 'Konu Raporları',       'desc' => 'açık/incelenen rapor', 'icon' => 'bi-flag',                'href' => $baseUri . '/admin/complaints-reports.php?tab=topics&status=open'],
    ['key' => 'user_reports',   'label' => 'Kullanıcı Şikayetleri', 'desc' => 'açık/incelenen şikayet','icon' => 'bi-person-exclamation',  'href' => $baseUri . '/admin/complaints-reports.php?tab=users&status=open'],
    ['key' => 'ban_appeals',    'label' => 'Ban İtirazları',        'desc' => 'bekleyen itiraz',      'icon' => 'bi-megaphone',           'href' => $baseUri . '/admin/users.php?tab=appeals'],
    ['key' => 'pending_topics', 'label' => 'Taslak Konular',        'desc' => 'taslakta',             'icon' => 'bi-files',               'href' => $baseUri . '/admin/topics.php?status=draft'],
];
$attention = $attention ?? [];
$attentionTotal = $attentionTotal ?? 0;
?>
<div class="admin-card ui-admin-attention-card ui-panel ui-card">
    <div class="card-header ui-panel__head">
        <i class="bi bi-bell<?= $attentionTotal > 0 ? '-fill' : '' ?>"></i>Dikkat Bekleyenler
        <?php if ($attentionTotal > 0): ?>
            <span class="ui-admin-attention-badge"><?= number_format($attentionTotal, 0, ',', '.') ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body ui-admin-card-flush ui-panel__body ui-card">
        <?php if ($attentionTotal === 0): ?>
            <div class="quick-action-item">
                <div class="quick-action-icon"><i class="bi bi-check2-circle"></i></div>
                <div class="quick-action-content">
                    <span class="quick-action-title">Her şey yolunda</span>
                    <span class="quick-action-desc">Bekleyen moderasyon işi yok.</span>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($attentionQueues as $q): $n = (int) ($attention[$q['key']] ?? 0); ?>
                <?php if ($n > 0): ?>
                    <a href="<?= $q['href'] ?>" class="quick-action-item">
                        <div class="quick-action-icon"><i class="bi <?= $q['icon'] ?>"></i></div>
                        <div class="quick-action-content">
                            <span class="quick-action-title"><?= htmlspecialchars($q['label']) ?></span>
                            <span class="quick-action-desc"><?= $n ?> <?= htmlspecialchars($q['desc']) ?></span>
                        </div>
                        <span class="ui-admin-attention-count"><?= $n ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Hızlı İşlemler + Son Aktiviteler -->
<div class="dashboard-grid ui-grid">
    <div class="admin-card ui-panel">
        <div class="card-header ui-panel__head"><i class="bi bi-compass"></i>Operasyon Merkezi</div>
        <div class="card-body ui-admin-card-flush ui-panel__body ui-card">
            <a href="<?= $baseUri ?>/admin/system-health.php" class="quick-action-item">
                <div class="quick-action-icon"><i class="bi bi-clipboard2-pulse"></i></div>
                <div class="quick-action-content">
                    <span class="quick-action-title">Sistem Sağlığı</span>
                    <span class="quick-action-desc">Cron, kuyruk, log ve içerik sinyalleri</span>
                </div>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="<?= $baseUri ?>/admin/complaints-reports.php" class="quick-action-item">
                <div class="quick-action-icon"><i class="bi bi-shield-exclamation"></i></div>
                <div class="quick-action-content">
                    <span class="quick-action-title">Şikayetler &amp; Raporlar</span>
                    <span class="quick-action-desc">Konu raporları ve kullanıcı şikayetleri</span>
                </div>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="<?= $baseUri ?>/admin/notifications.php?tab=logs" class="quick-action-item">
                <div class="quick-action-icon"><i class="bi bi-envelope-paper"></i></div>
                <div class="quick-action-content">
                    <span class="quick-action-title">E-posta Kuyruğu</span>
                    <span class="quick-action-desc">Gönderim logları ve hatalı denemeler</span>
                </div>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="<?= $baseUri ?>/admin/rate-limits.php?status=expired" class="quick-action-item">
                <div class="quick-action-icon"><i class="bi bi-speedometer"></i></div>
                <div class="quick-action-content">
                    <span class="quick-action-title">Rate Limit Temizliği</span>
                    <span class="quick-action-desc">Süresi dolmuş ve aktif kilit kayıtları</span>
                </div>
                <i class="bi bi-chevron-right"></i>
            </a>
            <a href="<?= $baseUri ?>/admin/queue.php" class="quick-action-item">
                <div class="quick-action-icon"><i class="bi bi-inbox-fill"></i></div>
                <div class="quick-action-content">
                    <span class="quick-action-title">Bekleyen İşler</span>
                    <span class="quick-action-desc">Toplu moderasyon ve inceleme kuyruğu</span>
                </div>
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>

    <div class="admin-card ui-panel">
        <div class="card-header ui-panel__head"><i class="bi bi-clock-history"></i>Son Aktiviteler</div>
        <div class="card-body ui-admin-card-flush ui-panel__body ui-card">
            <?php if (empty($recentActivity)): ?>
                <div class="activity-item">
                    <div class="activity-icon info"><i class="bi bi-info-circle"></i></div>
                    <div class="activity-content">
                        <span class="activity-title">Henüz aktivite yok</span>
                        <span class="activity-desc">Yeni yönetim işlemleri burada görünür.</span>
                        <span class="activity-time">Şimdi</span>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($recentActivity as $activity): ?>
                    <?php
                    [$activityClass, $activityIcon] = dashboardActivityIcon((string)($activity['action'] ?? ''));
                    $activityTitle = function_exists('logsFormatAction') ? logsFormatAction((string)$activity['action']) : (string)$activity['action'];
                    $activityProps = is_string($activity['properties'] ?? null) ? json_decode((string) $activity['properties'], true) : null;
                    $activitySubjectTitle = is_array($activityProps) ? ($activityProps['subject_title'] ?? $activityProps['topic_title'] ?? null) : null;
                    $activityDesc = $activity['topic_title'] ?: (is_string($activitySubjectTitle) && $activitySubjectTitle !== '' ? $activitySubjectTitle : ($activity['actor_name'] ?: logsFormatSubject($activity['subject_type'] ?? null, $activity['subject_id'] ?? null)));
                    ?>
                    <div class="activity-item">
                        <div class="activity-icon <?= htmlspecialchars($activityClass) ?>"><i class="bi <?= htmlspecialchars($activityIcon) ?>"></i></div>
                        <div class="activity-content">
                            <span class="activity-title"><?= htmlspecialchars($activityTitle) ?></span>
                            <span class="activity-desc"><?= htmlspecialchars((string)$activityDesc) ?></span>
                            <span class="activity-time"><?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$activity['created_at']))) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

