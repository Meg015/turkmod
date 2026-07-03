<?php

declare(strict_types=1);
/**
 * Aktivite Logları — Controller
 * İş mantığı: includes/src/Engine/Logs/Legacy/helpers.php
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/Logs/Legacy/helpers.php';
adminRequirePermission('logs.view', 'Loglari goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

$pageTitle = 'Aktivite Logları';

// Log temizleme işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if ($postAction === 'clear_old' || $postAction === 'clear_all') {
        if (!verify_csrf_token($_POST['_token'] ?? '')) {
            flash('error', 'Güvenlik hatası.');
        } elseif (!adminCurrentUserCan('logs.manage')) {
            adminDenyAction('Log temizlemek icin gerekli izin hesabiniza tanimlanmamis.', 'logs.php');
        } else {
            try {
                if ($postAction === 'clear_all') {
                    $deleted = logsClearAll($pdo);
                    flash('success', 'Tebrikler, ' . $deleted . ' adet kayıt hiçbir kalıntı bırakılmadan temizlendi ve sayaçlar sıfırlandı!');
                } else {
                    $days = max(7, (int)($_POST['days'] ?? 90));
                    $deleted = logsClearOld($pdo, $days);
                    logActivity($pdo, 'activity_logs_cleared', 'logs', null, ['action' => $postAction, 'deleted' => $deleted, 'days' => $days]);
                    flash('success', $deleted . ' eski log kaydı silindi.');
                }
            } catch (Throwable $e) {
                flash('error', 'Temizleme hatası: ' . safeErrorMessage($e));
            }
        }
        header('Location: logs.php'); exit;
    }
}

$search = trim($_GET['q'] ?? '');
$filterAction = $_GET['action'] ?? '';
$filterSubject = trim((string)($_GET['subject'] ?? ''));
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_from'] ?? '')) === 1 ? (string)$_GET['date_from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['date_to'] ?? '')) === 1 ? (string)$_GET['date_to'] : '';
$page = max(1, (int)($_GET['page'] ?? 1));

$logs = ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => 50];
$stats = ['total' => 0, 'today' => 0, 'week' => 0, 'error_events' => 0, 'critical_admin_actions' => 0];
$actionTypes = [];
$subjectTypes = [];
$runtimeLogSummary = logsGetRuntimeLogSummary(__DIR__ . '/../storage/logs');

if ($pdo) {
    try {
        $logs = logsGetList($pdo, $search, $filterAction, $page, 50, $filterSubject, $dateFrom, $dateTo);
        $stats = logsGetStats($pdo);
        $actionTypes = logsGetActionTypes($pdo);
        $subjectTypes = logsGetSubjectTypes($pdo);
    } catch (Throwable $e) {
        flash('error', 'Loglar yüklenemedi: ' . safeErrorMessage($e));
    }
}

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
require_once __DIR__ . '/header.php';
?>

<?php adminRenderLogsSubtabs('activity'); ?>

<div class="logs-page">
<?php if (!empty($runtimeLogSummary['latest_at'])): ?>
<div class="ui-admin-alert <?= ((int)$runtimeLogSummary['error_count_24h'] > 0) ? 'ui-admin-alert-error' : 'ui-admin-alert-info' ?> ui-alert">
    <i class="bi bi-activity"></i>
    <div>
        <strong>Çalışma zamanı log özeti:</strong>
        Son hata <?= htmlspecialchars(date('d.m.Y H:i', (int)$runtimeLogSummary['latest_at'])) ?>
        tarihinde <?= htmlspecialchars((string)$runtimeLogSummary['latest_file']) ?> içinde görüldü.
        Son 24 saatte <?= number_format((int)$runtimeLogSummary['error_count_24h']) ?> hata/critical kaydı var.
        <?php if (!empty($runtimeLogSummary['latest_message'])): ?>
            <span class="ui-admin-runtime-message"><?= htmlspecialchars((string)$runtimeLogSummary['latest_message']) ?></span>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
<!-- İstatistikler -->
<div class="admin-stat-grid logs-summary ui-grid">
    <div class="admin-stat-card stat-info logs-stat ui-card">
        <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
        <div class="stat-content"><span class="stat-label">Toplam Log</span><span class="stat-value"><?= number_format($stats['total']) ?></span></div>
    </div>
    <div class="admin-stat-card stat-success logs-stat ui-card">
        <div class="stat-icon"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-content"><span class="stat-label">Bugün Oluşan</span><span class="stat-value"><?= number_format($stats['today']) ?></span></div>
    </div>
    <div class="admin-stat-card stat-warning logs-stat ui-card">
        <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
        <div class="stat-content"><span class="stat-label">Son 7 Gün</span><span class="stat-value"><?= number_format($stats['week']) ?></span></div>
    </div>
    <div class="admin-stat-card stat-danger logs-stat ui-card">
        <div class="stat-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="stat-content"><span class="stat-label">Error Events</span><span class="stat-value"><?= number_format((int)($stats['error_events'] ?? 0)) ?></span></div>
    </div>
    <div class="admin-stat-card stat-info logs-stat ui-card">
        <div class="stat-icon"><i class="bi bi-shield-lock"></i></div>
        <div class="stat-content"><span class="stat-label">Critical Admin</span><span class="stat-value"><?= number_format((int)($stats['critical_admin_actions'] ?? 0)) ?></span></div>
    </div>
</div>

<!-- Filtre + Arama -->
<div class="admin-card logs-toolbar-card ui-panel">
    <div class="card-header logs-toolbar-head ui-panel__head">
        <form method="get" action="logs.php" class="logs-filter-form">
            <input type="text" name="q" class="ui-admin-form-control" placeholder="Kullanıcı, işlem veya tür ara..." value="<?= htmlspecialchars($search) ?>">
            <select name="action" class="ui-admin-form-select">
                <option value="">Tüm İşlemler</option>
                <?php foreach ($actionTypes as $at): ?>
                <option value="<?= htmlspecialchars($at) ?>" <?= $filterAction === $at ? 'selected' : '' ?>><?= htmlspecialchars(logsFormatAction($at)) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="subject" class="ui-admin-form-select">
                <option value="">Tüm Kayıt Türleri</option>
                <?php foreach ($subjectTypes as $subjectType): ?>
                <option value="<?= htmlspecialchars((string)$subjectType) ?>" <?= $filterSubject === (string)$subjectType ? 'selected' : '' ?>><?= htmlspecialchars(logsFormatSubject((string)$subjectType)) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date_from" class="ui-admin-form-control" value="<?= htmlspecialchars($dateFrom) ?>" aria-label="Başlangıç tarihi">
            <input type="date" name="date_to" class="ui-admin-form-control" value="<?= htmlspecialchars($dateTo) ?>" aria-label="Bitiş tarihi">
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
            <?php if ($search !== '' || $filterAction !== '' || $filterSubject !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a href="logs.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm">Temizle</a>
            <?php endif; ?>
        </form>
        <form method="post" action="logs.php" class="logs-clear-form" data-admin-confirm="Tüm aktivite logları kalıcı olarak silinecek. Emin misiniz?" data-admin-confirm-title="Tüm loglar temizlensin mi?" data-admin-confirm-ok="Temizle" data-admin-confirm-tone="danger">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="clear_all">
            <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm"><i class="bi bi-trash"></i> Tüm Logları Temizle</button>
        </form>
    </div>
</div>

<!-- Log Listesi -->
<div class="admin-card logs-list-card ui-panel">
    <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
        <?php if (empty($logs['items'])): ?>
            <div class="ui-admin-empty ui-empty">
                <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-journal-text"></i></div>
                <h3 class="ui-admin-empty-title ui-empty">Henüz log kaydı yok</h3>
                <p class="ui-admin-empty-desc ui-empty">Aktivite oluştuğunda veya filtreleri değiştirdiğinizde kayıtlar burada görünecek.</p>
            </div>
        <?php else: ?>
            <div class="table-wrapper ui-table-wrap ui-surface">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="ui-admin-table-head-narrow">#</th>
                            <th>Tarih</th>
                            <th>Kullanıcı</th>
                            <th>Ne Oldu?</th>
                            <th>İlgili Kayıt</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs['items'] as $log): ?>
                        <tr>
                            <td class="ui-admin-table-cell-id"><?= (int)$log['id'] ?></td>
                            <td class="ui-admin-table-cell-date">
                                <?= date('d.m.Y H:i', strtotime($log['created_at'])) ?>
                            </td>
                            <td class="ui-admin-table-cell-strong">
                                <?= htmlspecialchars($log['actor_name'] ?? 'Sistem') ?>
                            </td>
                            <td>
                                <span class="admin-badge <?= logsActionBadgeClass($log['action']) ?>">
                                    <?= htmlspecialchars(logsFormatAction($log['action'])) ?>
                                </span>
                            </td>
                            <?php
                                $logProps = $log['properties'] ? json_decode($log['properties'], true) : null;
                                $logSubjectTitle = is_array($logProps) ? ($logProps['subject_title'] ?? null) : null;
                                if (!$logSubjectTitle) {
                                    if ($log['subject_type'] === 'topic' && !empty($log['topic_title'])) {
                                        $logSubjectTitle = $log['topic_title'];
                                    } elseif ($log['subject_type'] === 'user') {
                                        if (!empty($log['subject_user_name'])) {
                                            $logSubjectTitle = $log['subject_user_name'];
                                        } elseif ($log['subject_id'] == $log['actor_id'] && !empty($log['actor_name'])) {
                                            $logSubjectTitle = $log['actor_name'];
                                        }
                                    } elseif ($log['subject_type'] === 'category' && !empty($log['subject_category_name'])) {
                                        $logSubjectTitle = $log['subject_category_name'];
                                    } elseif ($log['subject_type'] === 'comment' && is_array($logProps) && isset($logProps['topic_id'])) {
                                        // Yorumlar için konuyu belirtelim
                                        $logSubjectTitle = "Konu #" . $logProps['topic_id'] . " altındaki yorum";
                                    }
                                }
                                
                                $formattedSubject = logsFormatSubject($log['subject_type'] ?? null, $log['subject_id'] ?? null, is_string($logSubjectTitle) ? $logSubjectTitle : null);
                            ?>
                            <td class="ui-admin-table-cell-secondary ui-admin-muted-sm">
                                <?= htmlspecialchars($formattedSubject) ?>
                            </td>
                            <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell">
                                <div class="ui-admin-log-desc-scroll">
                                    <?= nl2br(htmlspecialchars(logsFormatProperties($log['properties'] ?? null))) ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $totalPages = (int)ceil($logs['total'] / $logs['perPage']);
            if ($totalPages > 1): ?>
            <div class="ui-admin-pagination-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php
                    $qs = http_build_query(array_filter(['q' => $search, 'action' => $filterAction, 'subject' => $filterSubject, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'page' => $i]));
                    ?>
                    <a href="logs.php?<?= $qs ?>" class="ui-admin-btn ui-admin-btn-sm <?= $i === $page ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>

