<?php

$groupLabels = function_exists('userActivityGroupLabels') ? userActivityGroupLabels() : [];
$deviceLabels = [
    '' => 'Tüm cihazlar',
    'desktop' => 'Masaüstü',
    'mobile' => 'Mobil',
    'tablet' => 'Tablet',
    'bot' => 'Bot',
];

$q = sanitizeSearchQuery($_GET['q'] ?? '');
$userId = max(0, (int) ($_GET['user_id'] ?? 0));
$eventGroup = trim((string) ($_GET['group'] ?? ''));
$eventType = trim((string) ($_GET['event_type'] ?? ''));
$ipAddress = trim((string) ($_GET['ip'] ?? ''));
$deviceType = trim((string) ($_GET['device'] ?? ''));
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_from'] ?? '')) === 1 ? (string) $_GET['date_from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_to'] ?? '')) === 1 ? (string) $_GET['date_to'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

if ($eventGroup !== '' && !array_key_exists($eventGroup, $groupLabels)) {
    $eventGroup = '';
}
if ($deviceType !== '' && !array_key_exists($deviceType, $deviceLabels)) {
    $deviceType = '';
}

$filters = [
    'q' => $q,
    'user_id' => $userId > 0 ? $userId : null,
    'event_group' => $eventGroup,
    'event_type' => $eventType,
    'ip_address' => $ipAddress,
    'device_type' => $deviceType,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];
$filters = array_filter($filters, static fn ($value): bool => $value !== null && $value !== '');

$events = [];
$stats = [
    'total' => 0,
    'auth_total' => 0,
    'security_total' => 0,
    'content_total' => 0,
    'admin_total' => 0,
    'unique_ips' => 0,
    'unique_sessions' => 0,
    'last_seen_at' => null,
];
$totalEvents = 0;
$securityRows = [];
$adminRows = [];
$contentSummary = null;
$adminNotes = [];
$selectedUser = null;
$eventTypes = [];

try {
    userActivityEnsureSchema($pdo);
    $events = userActivityList($pdo, $filters, $perPage, $offset);
    $totalEvents = userActivityCount($pdo, $filters);
    $stats = userActivityStats($pdo, $filters);
    $securityRows = userActivitySecuritySummary($pdo, $filters, 10);
    $adminRows = userActivityAdminActions($pdo, $filters, 10);
    $dbEventTypes = $pdo->query("SELECT DISTINCT event_type FROM user_activity_events ORDER BY event_type ASC LIMIT 100")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $eventTypes = array_unique(array_merge(array_keys(function_exists('userActivityAllKnownEvents') ? userActivityAllKnownEvents() : []), $dbEventTypes));
    sort($eventTypes);

    if ($userId > 0) {
        $selectedUser = usersGetById($pdo, $userId);
        $contentSummary = userActivityContentSummary($pdo, $userId);
        $adminNotes = usersGetAdminNotes($pdo, $userId, 8);
    }
} catch (Throwable $e) {
    flash('error', safeErrorMessage($e, 'Kullanıcı izleme kayıtları yüklenemedi.'));
}

$totalPages = max(1, (int) ceil(max(0, $totalEvents) / $perPage));
$hasFilters = $q !== '' || $userId > 0 || $eventGroup !== '' || $eventType !== '' || $ipAddress !== '' || $deviceType !== '' || $dateFrom !== '' || $dateTo !== '';

$fmtDate = static function (?string $date): string {
    if (!$date) {
        return '-';
    }
    $time = strtotime($date);
    return $time ? date('d.m.Y H:i', $time) : $date;
};
$eventTone = static function (string $group): string {
    return match ($group) {
        'auth' => 'success',
        'security' => 'danger',
        'content' => 'info',
        'moderation', 'admin' => 'warning',
        'appeal' => 'primary',
        'note' => 'secondary',
        default => 'secondary',
    };
};
$queryForPage = static function (int $targetPage) use ($q, $userId, $eventGroup, $eventType, $ipAddress, $deviceType, $dateFrom, $dateTo): string {
    return 'users.php?' . http_build_query(array_filter([
        'tab' => 'activity',
        'q' => $q,
        'user_id' => $userId > 0 ? $userId : null,
        'group' => $eventGroup,
        'event_type' => $eventType,
        'ip' => $ipAddress,
        'device' => $deviceType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'page' => $targetPage,
    ], static fn ($value): bool => $value !== null && $value !== ''));
};
?>

<div class="user-activity-page ui-admin-activity-page">
    <div class="user-activity-summary">
        <div class="user-activity-stat"><span>Toplam olay</span><strong><?= number_format($stats['total'], 0, ',', '.') ?></strong></div>
        <div class="user-activity-stat"><span>Giriş hareketi</span><strong><?= number_format($stats['auth_total'], 0, ',', '.') ?></strong></div>
        <div class="user-activity-stat"><span>Güvenlik</span><strong><?= number_format($stats['security_total'], 0, ',', '.') ?></strong></div>
        <div class="user-activity-stat"><span>Benzersiz IP</span><strong><?= number_format($stats['unique_ips'], 0, ',', '.') ?></strong></div>
        <div class="user-activity-stat"><span>Son hareket</span><strong><?= htmlspecialchars($fmtDate($stats['last_seen_at'])) ?></strong></div>
    </div>

    <div class="admin-card user-activity-filter-card ui-panel ui-card">
        <div class="card-body ui-admin-card-compact ui-panel__body ui-card">
            <form method="get" action="users.php" class="ui-admin-filter-row user-activity-filter">
                <input type="hidden" name="tab" value="activity">
                <input type="hidden" name="user_id" value="<?= $userId > 0 ? (int) $userId : '' ?>">
                <div class="ui-admin-filter-grow">
                    <label class="ui-admin-form-label">Ara</label>
                    <input type="text" name="q" class="ui-admin-form-control" placeholder="Ad, e-posta, kullanıcı ID, admin veya IP..." value="<?= htmlspecialchars($q) ?>">
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Grup</label>
                    <select name="group" class="ui-admin-form-select">
                        <option value="">Tümü</option>
                        <?php foreach ($groupLabels as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $eventGroup === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Olay</label>
                    <select name="event_type" class="ui-admin-form-select">
                        <option value="">Tümü</option>
                        <?php foreach ($eventTypes as $type): ?>
                            <option value="<?= htmlspecialchars((string) $type) ?>" <?= $eventType === (string) $type ? 'selected' : '' ?>><?= htmlspecialchars(userActivityEventLabel((string) $type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">IP</label>
                    <input type="text" name="ip" class="ui-admin-form-control" value="<?= htmlspecialchars($ipAddress) ?>" placeholder="192.168...">
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Cihaz</label>
                    <select name="device" class="ui-admin-form-select">
                        <?php foreach ($deviceLabels as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $deviceType === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
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
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-search"></i> Filtrele</button>
                <?php if ($hasFilters): ?>
                    <a href="users.php?tab=activity" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-circle"></i> Temizle</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($selectedUser): ?>
        <div class="admin-card user-activity-selected ui-panel">
            <div class="card-body ui-panel__body">
                <div class="user-activity-selected-main">
                    <div class="ui-admin-user-line">
                        <div class="user-avatar-badge default-avatar">
                            <?= function_exists('avatarImageHtml') ? avatarImageHtml((string) $selectedUser['name'], (string) ($selectedUser['avatar'] ?? ''), ['alt' => '']) : '' ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars((string) $selectedUser['name']) ?></strong>
                            <span><?= htmlspecialchars((string) $selectedUser['email']) ?> · #<?= (int) $selectedUser['id'] ?></span>
                        </div>
                    </div>
                    <div class="user-activity-selected-actions">
                        <a class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" href="users.php?tab=users&edit=<?= (int) $selectedUser['id'] ?>"><i class="bi bi-pencil"></i> Düzenle</a>
                        <a class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" href="user-edit.php?id=<?= (int) $selectedUser['id'] ?>"><i class="bi bi-person-vcard"></i> Kart</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="user-activity-layout ui-section">
        <section class="admin-card user-activity-feed-card ui-panel ui-card">
            <div class="card-header user-activity-card-head ui-admin-card-header-actions ui-panel__head ui-card">
                <div>
                    <h3><i class="bi bi-activity"></i> Hareket Akışı</h3>
                    <span><?= number_format($totalEvents, 0, ',', '.') ?> kayıt</span>
                </div>
                <div>
                    <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger" data-clear-logs-open>
                        <i class="bi bi-trash"></i> Kayıtları Temizle
                    </button>
                </div>
            </div>
            <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
                <?php if (empty($events)): ?>
                    <div class="ui-admin-empty ui-empty">
                        <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-search"></i></div>
                        <h3 class="ui-admin-empty-title ui-empty">Kayıt bulunamadı</h3>
                        <p class="ui-admin-empty-desc ui-empty">Filtreleri genişletin veya yeni kullanıcı hareketleri oluşmasını bekleyin.</p>
                    </div>
                <?php else: ?>
                    <div class="user-activity-feed">
                        <?php
                        $subjectIds = ['topic' => [], 'user' => [], 'comment' => [], 'category' => []];
                        foreach ($events as $e) {
                            $sType = (string) ($e['subject_type'] ?? '');
                            $sId = (int) ($e['subject_id'] ?? 0);
                            if ($sType !== '' && $sId > 0 && isset($subjectIds[$sType])) {
                                $subjectIds[$sType][$sId] = $sId;
                            }
                        }
                        $subjectNames = ['topic' => [], 'user' => [], 'comment' => [], 'category' => []];
                        try {
                            if (!empty($subjectIds['topic'])) {
                                $tIds = implode(',', $subjectIds['topic']);
                                $tStmt = $pdo->query("SELECT id, title FROM topics WHERE id IN ($tIds)");
                                foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subjectNames['topic'][$row['id']] = $row['title'];
                            }
                            if (!empty($subjectIds['user'])) {
                                $uIds = implode(',', $subjectIds['user']);
                                $uStmt = $pdo->query("SELECT id, name FROM users WHERE id IN ($uIds)");
                                foreach ($uStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subjectNames['user'][$row['id']] = $row['name'];
                            }
                            if (!empty($subjectIds['comment'])) {
                                $cIds = implode(',', $subjectIds['comment']);
                                $cStmt = $pdo->query("SELECT id, body FROM comments WHERE id IN ($cIds)");
                                foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subjectNames['comment'][$row['id']] = mb_substr(trim((string) $row['body']), 0, 40) . '...';
                            }
                            if (!empty($subjectIds['category'])) {
                                $catIds = implode(',', $subjectIds['category']);
                                $catStmt = $pdo->query("SELECT id, name FROM categories WHERE id IN ($catIds)");
                                foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subjectNames['category'][$row['id']] = $row['name'];
                            }
                        } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
                        ?>
                        <?php foreach ($events as $event): ?>
                            <?php
                            $meta = [];
                            if (!empty($event['metadata_json'])) {
                                $decoded = json_decode((string) $event['metadata_json'], true);
                                $meta = is_array($decoded) ? $decoded : [];
                            }
                            $group = (string) ($event['event_group'] ?? 'activity');
                            $tone = $eventTone($group);
                            ?>
                            <article class="user-activity-item">
                                <div class="user-activity-dot tone-<?= htmlspecialchars($tone) ?>"><i class="bi bi-record-circle"></i></div>
                                <div class="user-activity-item-main">
                                    <div class="user-activity-row-top">
                                        <div>
                                            <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($tone) ?>"><?= htmlspecialchars($groupLabels[$group] ?? $group) ?></span>
                                            <strong><?= htmlspecialchars((string) ($event['title'] ?: userActivityEventLabel((string) $event['event_type']))) ?></strong>
                                        </div>
                                        <time><?= htmlspecialchars($fmtDate((string) $event['created_at'])) ?></time>
                                    </div>
                                    <div class="user-activity-row-meta">
                                        <span><i class="bi bi-person"></i> <?= htmlspecialchars((string) ($event['user_name'] ?? ('#' . ($event['user_id'] ?? '-')))) ?></span>
                                        <?php if (!empty($event['actor_user_id']) && (int) $event['actor_user_id'] !== (int) ($event['user_id'] ?? 0)): ?>
                                            <span><i class="bi bi-person-badge"></i> <?= htmlspecialchars((string) ($event['actor_name'] ?? ('#' . $event['actor_user_id']))) ?></span>
                                        <?php endif; ?>
                                        <span><i class="bi bi-hdd-network"></i> <code><?= htmlspecialchars((string) ($event['ip_address'] ?? '-')) ?></code></span>
                                        <span><i class="bi bi-display"></i> <?= htmlspecialchars(trim((string) ($event['device_type'] ?? 'unknown') . ' · ' . (string) ($event['browser'] ?? ''))) ?></span>
                                        <?php if (!empty($event['request_path'])): ?>
                                            <span><i class="bi bi-signpost-2"></i> <?= htmlspecialchars((string) $event['request_path']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($meta) || !empty($event['subject_type'])): ?>
                                        <details class="user-activity-details">
                                            <summary><i class="bi bi-braces"></i> Detay</summary>
                                            <?php
                                            $subjectTypes = [
                                                'topic' => 'Konu',
                                                'comment' => 'Yorum',
                                                'user' => 'Kullanıcı',
                                                'restriction' => 'Kısıtlama',
                                                'report' => 'Şikayet',
                                                'appeal' => 'İtiraz',
                                                'note' => 'Not',
                                            ];
                                            $subjectTypeKey = (string) ($event['subject_type'] ?? '');
                                            $subjectLabel = $subjectTypes[$subjectTypeKey] ?? ($subjectTypeKey ?: '-');
                                            $subjectName = $subjectNames[$subjectTypeKey][$event['subject_id']] ?? $meta['title'] ?? ($meta['name'] ?? ('#' . ($event['subject_id'] ?? '-')));
                                            ?>
                                            <div class="user-activity-detail-grid ui-grid">
                                                <span><b>Olay</b><?= htmlspecialchars(userActivityEventLabel((string) $event['event_type'])) ?></span>
                                                <span><b>Nesne</b><?= htmlspecialchars($subjectLabel) ?> <?= htmlspecialchars((string) $subjectName) ?></span>
                                                <span><b>Platform</b><?= htmlspecialchars((string) ($event['platform'] ?? '-')) ?></span>
                                                <span><b>Metot</b><?= htmlspecialchars((string) ($event['request_method'] ?? '-')) ?></span>
                                            </div>
                                            <?php if (!empty($meta)): ?>
                                                <pre class="user-activity-json"><?= htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                                            <?php endif; ?>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="notif-pagination-bar">
                            <span><?= number_format((int) ($totalEvents ?? 0)) ?> olay</span>
                            <div>
                                <?php if ($page > 1): ?>
                                    <a class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" href="<?= htmlspecialchars($queryForPage($page - 1)) ?>"><i class="bi bi-chevron-left"></i></a>
                                <?php endif; ?>
                                <strong><?= (int) $page ?> / <?= (int) $totalPages ?></strong>
                                <?php if ($page < $totalPages): ?>
                                    <a class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" href="<?= htmlspecialchars($queryForPage($page + 1)) ?>"><i class="bi bi-chevron-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <aside class="user-activity-side">
            <section class="admin-card ui-panel">
                <div class="card-header user-activity-card-head ui-panel__head ui-card"><h3><i class="bi bi-shield-lock"></i> Güvenlik</h3></div>
                <div class="card-body ui-panel__body">
                    <?php if (empty($securityRows)): ?>
                        <p class="ui-admin-muted-sm">Güvenlik kaydı yok.</p>
                    <?php else: ?>
                        <ul class="user-activity-mini-list">
                            <?php foreach ($securityRows as $row): ?>
                                <li>
                                    <strong><?= htmlspecialchars((string) $row['event_type']) ?></strong>
                                    <span><code><?= htmlspecialchars((string) $row['ip_address']) ?></code> · <?= htmlspecialchars($fmtDate((string) $row['created_at'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <section class="admin-card ui-panel">
                <div class="card-header user-activity-card-head ui-panel__head ui-card"><h3><i class="bi bi-person-gear"></i> Admin İşlemleri</h3></div>
                <div class="card-body ui-panel__body">
                    <?php if (empty($adminRows)): ?>
                        <p class="ui-admin-muted-sm">Admin işlem kaydı yok.</p>
                    <?php else: ?>
                        <ul class="user-activity-mini-list">
                            <?php foreach ($adminRows as $row): ?>
                                <li>
                                    <strong><?= htmlspecialchars(adminAuditLogger()->actionLabel((string) $row['action_type'])) ?></strong>
                                    <span><?= htmlspecialchars((string) ($row['actor_name'] ?? 'Admin')) ?> · <?= htmlspecialchars($fmtDate((string) $row['created_at'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($contentSummary): ?>
                <section class="admin-card ui-panel">
                    <div class="card-header user-activity-card-head ui-panel__head ui-card"><h3><i class="bi bi-collection"></i> İçerikler</h3></div>
                    <div class="card-body ui-panel__body">
                        <div class="user-activity-content-counts">
                            <span><b><?= (int) $contentSummary['counts']['topics'] ?></b> konu</span>
                            <span><b><?= (int) $contentSummary['counts']['comments'] ?></b> yorum</span>
                            <span><b><?= (int) $contentSummary['counts']['reports_made'] ?></b> şikayet</span>
                            <span><b><?= (int) $contentSummary['counts']['reports_about'] ?></b> hakkında</span>
                        </div>
                        <ul class="user-activity-mini-list">
                            <?php foreach (array_slice($contentSummary['topics'], 0, 4) as $topic): ?>
                                <li>
                                    <strong><?= htmlspecialchars((string) $topic['title']) ?></strong>
                                    <span>Konu · <?= htmlspecialchars($fmtDate((string) $topic['created_at'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php foreach (array_slice($contentSummary['comments'], 0, 3) as $comment): ?>
                                <li>
                                    <strong><?= htmlspecialchars(mb_substr(trim((string) $comment['body']), 0, 90)) ?></strong>
                                    <span>Yorum · <?= htmlspecialchars($fmtDate((string) $comment['created_at'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>

                <section class="admin-card ui-panel">
                    <div class="card-header user-activity-card-head ui-panel__head ui-card"><h3><i class="bi bi-journal-text"></i> Admin Notları</h3></div>
                    <div class="card-body ui-panel__body">
                        <?php if (empty($adminNotes)): ?>
                            <p class="ui-admin-muted-sm">Bu kullanıcı için admin notu yok.</p>
                        <?php else: ?>
                            <ul class="user-activity-mini-list user-activity-notes">
                                <?php foreach ($adminNotes as $note): ?>
                                    <li>
                                        <strong><?= nl2br(htmlspecialchars((string) $note['note'])) ?></strong>
                                        <span><?= htmlspecialchars((string) ($note['admin_name'] ?? 'Admin')) ?> · <?= htmlspecialchars($fmtDate((string) $note['created_at'])) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</div>

<!-- Clear Logs Modal -->
<div class="media-modal-overlay" id="clearLogsModal" role="dialog" aria-modal="true" aria-label="Kayitlari temizle" hidden aria-hidden="true">
    <div class="media-modal ui-admin-modal-sm ui-panel">
        <div class="media-modal-header ui-panel__head">
            <h3 class="ui-admin-modal-title"><i class="bi bi-trash"></i> Kayıtları Temizle</h3>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-ui-modal-close data-clear-logs-close>&times;</button>
        </div>
        <div class="media-modal-body ui-panel__body">
            <form id="clearLogsForm" data-clear-logs-form>
                <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="clear_activity_logs">
                <input type="hidden" name="target_user_id" value="<?= $userId > 0 ? (int)$userId : 0 ?>">
                
                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label">Neler Silinsin?</label>
                    <select name="scope" class="ui-admin-form-select" required>
                        <option value="older_than_30_days">30 Günden Eski Kayıtları Sil</option>
                        <?php if ($userId > 0 && $selectedUser): ?>
                            <option value="user">Sadece Bu Kullanıcının Kayıtlarını Sil (<?= htmlspecialchars((string) $selectedUser['name']) ?>)</option>
                        <?php endif; ?>
                        <option value="all">Tüm Sistemin Loglarını Sil (Tehlikeli)</option>
                    </select>
                </div>
                
                <div class="ui-admin-alert ui-admin-alert-warning ui-admin-alert-spaced ui-alert ui-alert--warning">
                    <strong>Uyarı:</strong> Bu işlem geri alınamaz. Silinen kayıtlar veritabanından kalıcı olarak silinecektir.
                </div>
                
                <div class="media-modal-footer ui-admin-modal-footer-flush ui-panel__foot">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-clear-logs-close>İptal</button>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger"><i class="bi bi-trash"></i> Seçilenleri Kalıcı Olarak Sil</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_url('admin/assets/users-activity-tab.js', $baseUri) ?>" defer></script>
