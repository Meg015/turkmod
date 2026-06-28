<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

adminRequirePermission('logs.view', 'İşlem günlüğünü görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'Yönetici İşlem Günlüğü';

$actionLabels = [
    'group_change'  => 'Grup Değişimi',
    'status_change' => 'Durum Değişimi',
    'ban'           => 'Yasaklama',
    'unban'         => 'Yasak Kaldırma',
    'restrict'      => 'Kısıtlama',
    'delete'        => 'Silme',
];

$filterAction = trim((string) ($_GET['action_type'] ?? ''));
$filterTargetType = trim((string) ($_GET['target_type'] ?? 'user'));
if (!in_array($filterTargetType, ['user', 'topic', 'settings', 'media', 'leaderboard'], true)) {
    $filterTargetType = 'user';
}
$filterTarget = (int) ($_GET['target_id'] ?? 0);
$filterActor = (int) ($_GET['actor_id'] ?? 0);
$filterState = trim((string) ($_GET['state'] ?? ''));
if (!in_array($filterState, ['', 'active', 'reverted', 'reversible'], true)) {
    $filterState = '';
}
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_from'] ?? '')) === 1 ? (string) $_GET['date_from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_to'] ?? '')) === 1 ? (string) $_GET['date_to'] : '';

// ── POST: Geri Al (undo) & Temizle ──
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $redirectParams = array_filter([
        'action_type' => $filterAction,
        'target_type' => $filterTargetType !== 'user' ? $filterTargetType : '',
        'target_id' => $filterTarget > 0 ? $filterTarget : '',
        'actor_id' => $filterActor > 0 ? $filterActor : '',
        'state' => $filterState,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
    ], static fn ($value): bool => $value !== '' && $value !== null);

    $respond = static function (bool $ok, string $message) use ($isAjax, $redirectParams): void {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $ok, 'message' => $message]);
            exit;
        }
        flash($ok ? 'success' : 'error', $message);
        header('Location: action-log.php' . ($redirectParams ? '?' . http_build_query($redirectParams) : ''));
        exit;
    };

    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        $respond(false, 'Güvenlik doğrulaması başarısız.');
    } elseif (!userHasPermission($pdo, (int) ($_SESSION['_auth_user_id'] ?? 0), 'logs.manage')) {
        $respond(false, 'Bu işlemi yapmak için denetim kayıtları yöneticisi izni (logs.manage) gereklidir.');
    } elseif ((string) ($_POST['action'] ?? '') === 'revert') {
        $err = adminAuditLogger()->revertAction(
            $pdo,
            (int) ($_POST['log_id'] ?? 0),
            (int) ($_SESSION['_auth_user_id'] ?? 0)
        );
        $respond($err === '', $err === '' ? 'İşlem geri alındı.' : $err);
    } elseif ((string) ($_POST['action'] ?? '') === 'clear_action_logs') {
        $scope = (string)($_POST['scope'] ?? '');
        $where = [];
        
        if ($scope === 'older_than_30_days') {
            if (function_exists('adminAuditIsSqlite') && adminAuditIsSqlite($pdo)) {
                $where[] = "created_at < date('now', '-30 days')";
            } else {
                $where[] = "created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            }
        } elseif ($scope === 'all') {
            // Tüm kayıtları temizle
        } else {
            $respond(false, 'Geçersiz temizleme kapsamı.');
        }
        
        if ($scope === 'all') {
            $deletedCount = $pdo->query("SELECT COUNT(*) FROM admin_action_log")->fetchColumn();
            $pdo->exec("TRUNCATE TABLE admin_action_log");
        } else {
            $sql = "DELETE FROM admin_action_log" . (!empty($where) ? " WHERE " . implode(' AND ', $where) : "");
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $deletedCount = $stmt->rowCount();
        }
        
        if (function_exists('logActivity') && $scope !== 'all') {
            $scopeMap = ['all' => 'Tümü', 'older_than_30_days' => '30 Günden Eskiler'];
            $scopeName = $scopeMap[$scope] ?? $scope;
            logActivity($pdo, 'action_logs_cleared', 'system', 0, ['scope' => $scopeName, 'deleted' => $deletedCount]);
        }
        $respond(true, "$deletedCount adet denetim kaydı başarıyla temizlendi.");
    }
    
    // Fallback if no valid action matched
    header('Location: action-log.php' . ($redirectParams ? '?' . http_build_query($redirectParams) : ''));
    exit;
}

$perPage = 50;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$filters = [];
if ($filterAction !== '') {
    $filters['action_type'] = $filterAction;
}
if ($filterTarget > 0) {
    $filters['target_type'] = $filterTargetType;
    $filters['target_id'] = $filterTarget;
} elseif ($filterTargetType !== 'user') {
    $filters['target_type'] = $filterTargetType;
}
if ($filterActor > 0) {
    $filters['actor_id'] = $filterActor;
}
if ($filterState !== '') {
    $filters['state'] = $filterState;
}
if ($dateFrom !== '') {
    $filters['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $filters['date_to'] = $dateTo;
}

$logs = adminAuditLogger()->getActionLog($pdo, $filters, $perPage, $offset);
$totalLogs = adminAuditLogger()->countActionLog($pdo, $filters);
$totalPages = max(1, (int) ceil($totalLogs / $perPage));
$visibleReversible = count(array_filter($logs, static fn (array $log): bool => (int)($log['is_reversible'] ?? 0) === 1 && empty($log['reverted_at'])));
$visibleReverted = count(array_filter($logs, static fn (array $log): bool => !empty($log['reverted_at'])));
$visibleCritical = count(array_filter($logs, static fn (array $log): bool => in_array((string)($log['action_type'] ?? ''), ['ban', 'delete', 'restrict', 'group_change'], true)));
$hasFilters = $filterAction !== '' || $filterTarget > 0 || $filterTargetType !== 'user' || $filterActor > 0 || $filterState !== '' || $dateFrom !== '' || $dateTo !== '';

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
$csrfToken = csrf_token();
$actionLogQuery = http_build_query(array_filter([
    'action_type' => $filterAction,
    'target_type' => $filterTargetType !== 'user' ? $filterTargetType : '',
    'target_id' => $filterTarget > 0 ? $filterTarget : '',
    'actor_id' => $filterActor > 0 ? $filterActor : '',
    'state' => $filterState,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
], static fn ($value): bool => $value !== '' && $value !== null));
$actionLogPostAction = 'action-log.php' . ($actionLogQuery !== '' ? '?' . $actionLogQuery : '');

require_once __DIR__ . '/header.php';
?>

<div class="action-log-page">
<section class="ui-admin-page-hero">
    <div class="ui-admin-page-hero-text">
        <span class="ui-admin-kicker"><i class="bi bi-shield-check"></i> Denetim merkezi</span>
        <h2>Yönetici İşlem Günlüğü</h2>
        <p>Grup, durum, kısıtlama ve yasaklama gibi kritik kullanıcı eylemlerini izleyin; geri alınabilir işlemleri kontrollü şekilde iptal edin.</p>
    </div>
</section>

<?php if ($successMsg): ?>
    <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="ui-admin-alert ui-admin-alert-error ui-alert ui-alert--error"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<div class="user-activity-page ui-admin-page-offset-top">

<div class="admin-stat-grid action-log-admin-stats ui-admin-mb-md ui-grid">
    <div class="admin-stat-card stat-info ui-card">
        <div class="stat-icon"><i class="bi bi-journal-text"></i></div>
        <div class="stat-content">
            <span class="stat-label">Toplam kayıt</span>
            <span class="stat-value"><?= number_format($totalLogs, 0, ',', '.') ?></span>
        </div>
    </div>
    <div class="admin-stat-card stat-warning ui-card">
        <div class="stat-icon"><i class="bi bi-clock-history"></i></div>
        <div class="stat-content">
            <span class="stat-label">Geri alınabilir eylem</span>
            <span class="stat-value"><?= number_format($visibleReversible, 0, ',', '.') ?></span>
        </div>
    </div>
    <div class="admin-stat-card stat-success ui-card">
        <div class="stat-icon"><i class="bi bi-arrow-counterclockwise"></i></div>
        <div class="stat-content">
            <span class="stat-label">Geri alınmış</span>
            <span class="stat-value"><?= number_format($visibleReverted, 0, ',', '.') ?></span>
        </div>
    </div>
    <div class="admin-stat-card stat-danger ui-card">
        <div class="stat-icon"><i class="bi bi-shield-exclamation"></i></div>
        <div class="stat-content">
            <span class="stat-label">Kritik eylem</span>
            <span class="stat-value"><?= number_format($visibleCritical, 0, ',', '.') ?></span>
        </div>
    </div>
</div>

<div class="admin-card user-activity-filter-card ui-panel ui-card">
    <div class="card-body ui-admin-card-compact ui-panel__body ui-card">
        <form method="get" action="action-log.php" class="ui-admin-filter-row user-activity-filter">
            <div class="ui-admin-filter-grow">
                <label class="ui-admin-form-label">Eylem Türü</label>
                <select name="action_type" class="ui-admin-form-select" data-ui-submit-form>
                    <option value="">Tüm eylemler</option>
                    <?php foreach ($actionLabels as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filterAction === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ui-admin-filter-sm">
                <label class="ui-admin-form-label">Hedef Nesne</label>
                <select name="target_type" class="ui-admin-form-select">
                    <option value="user" <?= $filterTargetType === 'user' ? 'selected' : '' ?>>Kullanıcı</option>
                    <option value="topic" <?= $filterTargetType === 'topic' ? 'selected' : '' ?>>Konu</option>
                    <option value="settings" <?= $filterTargetType === 'settings' ? 'selected' : '' ?>>Ayarlar</option>
                    <option value="media" <?= $filterTargetType === 'media' ? 'selected' : '' ?>>Medya</option>
                    <option value="leaderboard" <?= $filterTargetType === 'leaderboard' ? 'selected' : '' ?>>Liderlik</option>
                </select>
            </div>
            
            <div class="ui-admin-filter-sm">
                <label class="ui-admin-form-label">Admin ID</label>
                <input type="number" name="actor_id" class="ui-admin-form-control" min="1" placeholder="Örn: 1" value="<?= $filterActor > 0 ? (int)$filterActor : '' ?>">
            </div>
            
            <div class="ui-admin-filter-sm">
                <label class="ui-admin-form-label">İşlem Durumu</label>
                <select name="state" class="ui-admin-form-select">
                    <option value="">Tüm durumlar</option>
                    <option value="active" <?= $filterState === 'active' ? 'selected' : '' ?>>Aktif (Geçerli)</option>
                    <option value="reversible" <?= $filterState === 'reversible' ? 'selected' : '' ?>>Geri Alınabilir</option>
                    <option value="reverted" <?= $filterState === 'reverted' ? 'selected' : '' ?>>Geri Alınmış</option>
                </select>
            </div>
            
            <div class="ui-admin-filter-sm">
                <label class="ui-admin-form-label">Başlangıç</label>
                <input type="date" name="date_from" class="ui-admin-form-control" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            
            <div class="ui-admin-filter-sm">
                <label class="ui-admin-form-label">Bitiş</label>
                <input type="date" name="date_to" class="ui-admin-form-control" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-funnel"></i> Filtrele</button>
            <?php if ($hasFilters): ?>
                <a href="action-log.php" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-circle"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>
</div>

    <section class="admin-card user-activity-feed-card ui-panel ui-card">
        <div class="card-header user-activity-card-head ui-admin-card-header-actions ui-panel__head ui-card">
            <div>
                <h3><i class="bi bi-activity"></i> Yönetici İşlem Günlüğü Akışı</h3>
                <span><?= number_format($totalLogs, 0, ',', '.') ?> kayıt</span>
            </div>
            <div class="ui-admin-action-row ui-admin-action-row-flush">
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger" data-clear-logs-open>
                    <i class="bi bi-trash"></i> Günlüğü Temizle
                </button>
            </div>
        </div>
        <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
            <?php if (empty($logs)): ?>
                <div class="ui-admin-empty ui-admin-empty-pro ui-admin-empty-audit ui-empty">
                    <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-journal-check"></i></div>
                    <h3 class="ui-admin-empty-title ui-empty">İşlem kaydı bulunamadı</h3>
                    <p class="ui-admin-empty-desc ui-empty"><?= $hasFilters ? 'Seçili filtrelerle eşleşen denetim kaydı yok.' : 'Henüz geri alınabilir veya kritik admin işlemi kaydedilmedi.' ?></p>
                    <div class="ui-admin-empty-meta" aria-label="Günlük durumu">
                        <span><i class="bi bi-funnel"></i> <?= $hasFilters ? 'Filtre aktif' : 'Filtre yok' ?></span>
                        <span><i class="bi bi-shield-lock"></i> Kritik kayıt yok</span>
                    </div>
                    <?php if ($hasFilters): ?>
                        <div class="ui-admin-empty-actions ui-empty">
                            <a href="action-log.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-circle"></i> Filtreleri Temizle</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="ui-admin-table-responsive">
                    <table class="ui-admin-table ui-admin-table-striped">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Yönetici</th>
                                <th>Eylem</th>
                                <th>Hedef</th>
                                <th>Gerekçe</th>
                                <th>Değişim Detayları</th>
                                <th>Durum</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                $old = json_decode((string) ($log['old_value'] ?? ''), true) ?: [];
                                $new = json_decode((string) ($log['new_value'] ?? ''), true) ?: [];
                                $isReverted = !empty($log['reverted_at']);
                                $canRevert = ((int) $log['is_reversible'] === 1) && !$isReverted;
                                
                                $actionType = (string) $log['action_type'];
                                $actionLabel = htmlspecialchars($actionLabels[$actionType] ?? adminAuditLogger()->actionLabel($actionType));
                                
                                $keyMap = [
                                    'status' => 'Durum',
                                    'group_id' => 'Grup ID',
                                    'group_ids' => 'Grup IDleri',
                                    'is_banned' => 'Yasaklı',
                                    'ban_reason' => 'Yasaklanma Gerekçesi',
                                    'type' => 'Kısıtlama Türü',
                                    'days' => 'Süre (Gün)'
                                ];
                                
                                $valMap = [
                                    'active' => 'Aktif',
                                    'inactive' => 'Pasif',
                                    'banned' => 'Yasaklı',
                                    'deleted' => 'Silinmiş',
                                    'all' => 'Tümü (Tam Kısıtlama)',
                                    'comments' => 'Sadece Yorumlar',
                                    'topics' => 'Sadece Konular'
                                ];
                                
                                $changeParts = [];
                                foreach ($new as $k => $v) {
                                    $oldV = $old[$k] ?? '—';
                                    $labelK = $keyMap[$k] ?? $k;
                                    
                                    $labelOld = $oldV;
                                    if ($oldV === 0 || $oldV === 1 || $oldV === '0' || $oldV === '1' || is_bool($oldV)) {
                                        if (in_array($k, ['is_banned', 'is_active', 'is_verified'])) {
                                            $labelOld = $oldV ? 'Evet' : 'Hayır';
                                        }
                                    } else {
                                        $labelOld = $valMap[$oldV] ?? $oldV;
                                    }
                                    
                                    $labelNew = $v;
                                    if ($v === 0 || $v === 1 || $v === '0' || $v === '1' || is_bool($v)) {
                                        if (in_array($k, ['is_banned', 'is_active', 'is_verified'])) {
                                            $labelNew = $v ? 'Evet' : 'Hayır';
                                        }
                                    } else {
                                        $labelNew = $valMap[$v] ?? $v;
                                    }
                                    
                                    $changeParts[] = '<div class="ui-admin-change-line"><strong class="ui-admin-text-muted">'.htmlspecialchars((string)$labelK).':</strong> <span class="ui-admin-text-danger">'.htmlspecialchars((string)$labelOld).'</span> <i class="bi bi-arrow-right ui-admin-text-muted"></i> <span class="ui-admin-text-success">'.htmlspecialchars((string)$labelNew).'</span></div>';
                                }
                                
                                $tone = 'info';
                                if ($actionType === 'ban' || $actionType === 'delete') $tone = 'danger';
                                elseif ($actionType === 'restrict' || $actionType === 'status_change') $tone = 'warning';
                                elseif ($actionType === 'unban' || $actionType === 'group_change') $tone = 'success';
                                if ($isReverted) $tone = 'muted';
                                ?>
                                
                                <tr<?= $isReverted ? ' class="ui-admin-row-muted"' : '' ?>>
                                    <td class="ui-admin-muted ui-admin-nowrap"><?= htmlspecialchars((string) $log['created_at']) ?></td>
                                    <td>
                                        <i class="bi bi-person-badge ui-admin-text-muted"></i> 
                                        <?= htmlspecialchars((string) ($log['actor_name'] ?? '#' . $log['actor_id'])) ?>
                                    </td>
                                    <td><span class="ui-admin-badge ui-admin-badge-<?= $tone ?>"><?= $actionLabel ?></span></td>
                                    <td>
                                        <?php if (($log['target_type'] ?? '') === 'user'): ?>
                                            <a href="users.php?edit=<?= (int) $log['target_id'] ?>">
                                                <?= htmlspecialchars((string) ($log['target_name'] ?? '#' . $log['target_id'])) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars((string) ($log['target_type'] ?? 'hedef')) ?> #<?= (int) $log['target_id'] ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($log['reason'] ?? '—')) ?></td>
                                    <td><?= !empty($changeParts) ? implode('', $changeParts) : '<span class="ui-admin-muted">—</span>' ?></td>
                                    <td>
                                        <?php if ($isReverted): ?>
                                            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-arrow-counterclockwise"></i> Geri alındı</span>
                                        <?php else: ?>
                                            <span class="ui-admin-badge ui-admin-badge-success">Aktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($canRevert): ?>
                                            <form method="post" action="<?= htmlspecialchars($actionLogPostAction) ?>" class="ui-admin-inline-form"
                                                  data-admin-confirm="Bu işlemi geri almak istediğinize emin misiniz?"
                                                  data-admin-confirm-tone="warning">
                                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="action" value="revert">
                                                <input type="hidden" name="log_id" value="<?= (int) $log['id'] ?>">
                                                <button type="submit" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-warning">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Geri Al
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="ui-admin-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="notif-pagination-bar">
                        <?php
                        $pageParams = $_GET;
                        unset($pageParams['page']);
                        $pageBase = 'action-log.php?' . ($pageParams ? http_build_query($pageParams) . '&' : '') . 'page=';
                        ?>
                        <div>
                            <!-- Placeholder to balance the flex layout -->
                        </div>
                        <div class="notif-pagination-center">
                            <?php if ($page > 1): ?>
                                <a class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" href="<?= htmlspecialchars($pageBase . ($page - 1)) ?>"><i class="bi bi-chevron-left"></i> Önceki</a>
                            <?php else: ?>
                                <button class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" disabled><i class="bi bi-chevron-left"></i> Önceki</button>
                            <?php endif; ?>
                            
                            <span class="ui-admin-text-muted ui-admin-mx-md">Sayfa <strong><?= $page ?></strong> / <?= $totalPages ?></span>
                            
                            <?php if ($page < $totalPages): ?>
                                <a class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" href="<?= htmlspecialchars($pageBase . ($page + 1)) ?>">Sonraki <i class="bi bi-chevron-right"></i></a>
                            <?php else: ?>
                                <button class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" disabled>Sonraki <i class="bi bi-chevron-right"></i></button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- Clear Logs Modal -->
<div class="media-modal-overlay" id="clearLogsModal" role="dialog" aria-modal="true" aria-label="Yonetici gunlugunu temizle" hidden aria-hidden="true">
    <div class="media-modal ui-admin-modal-sm ui-panel">
        <div class="media-modal-header ui-panel__head">
            <h3 class="ui-admin-modal-title"><i class="bi bi-trash"></i> Yönetici Günlüğünü Temizle</h3>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-ui-modal-close data-clear-logs-close>&times;</button>
        </div>
        <div class="media-modal-body ui-panel__body">
            <form id="clearLogsForm" data-clear-logs-form>
                <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="clear_action_logs">
                
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">Neler Silinsin?</label>
                    <select name="scope" class="ui-admin-form-select" required>
                        <option value="older_than_30_days">30 Günden Eski Kayıtları Sil</option>
                        <option value="all">Tüm Günlüğü Sil (Tehlikeli)</option>
                    </select>
                </div>
                
                <div class="ui-admin-alert ui-admin-alert-warning ui-admin-alert-spaced ui-alert ui-alert--warning" data-keep-inline-alert>
                    <strong>Uyarı:</strong> Bu işlem denetim (audit) kayıtlarını silecektir. Güvenlik incelemeleri için son logların tutulması önerilir. Sadece gerekli olduğunda temizlik yapın. İşlem geri alınamaz.
                </div>
                
                <div class="media-modal-footer ui-admin-modal-footer-flush ui-panel__foot">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-clear-logs-close>İptal</button>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger"><i class="bi bi-trash"></i> Seçilenleri Kalıcı Olarak Sil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_url('admin/assets/action-log-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
