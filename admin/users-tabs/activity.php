<?php

$groupLabels = function_exists('userActivityGroupLabels') ? userActivityGroupLabels() : [];
$groupLabels = array_intersect_key($groupLabels, array_flip(['activity', 'auth', 'security', 'content', 'appeal', 'note']));
$deviceLabels = [
    '' => 'Tüm cihazlar',
    'desktop' => 'Masaüstü',
    'mobile' => 'Mobil',
    'tablet' => 'Tablet',
    'bot' => 'Bot',
];
$activityBaseRoute = (string) ($activityBaseRoute ?? 'users.php');
$activityBaseParams = is_array($activityBaseParams ?? null) ? $activityBaseParams : ['tab' => 'activity'];
$canManageLogs = function_exists('adminCurrentUserCan') && adminCurrentUserCan('logs.manage');
$showTabInput = isset($activityBaseParams['tab']);
$activityBuildUrl = static function (array $extra = []) use ($activityBaseRoute, $activityBaseParams): string {
    $params = array_filter(array_merge($activityBaseParams, $extra), static fn ($value): bool => $value !== null && $value !== '');
    return $activityBaseRoute . ($params !== [] ? '?' . http_build_query($params) : '');
};

$q = sanitizeSearchQuery($_GET['q'] ?? '');
$userId = max(0, (int) ($_GET['user_id'] ?? 0));
$eventGroup = trim((string) ($_GET['group'] ?? ''));
$eventType = trim((string) ($_GET['event_type'] ?? ''));
$ipAddress = trim((string) ($_GET['ip'] ?? ''));
$deviceType = trim((string) ($_GET['device'] ?? ''));
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_from'] ?? '')) === 1 ? (string) $_GET['date_from'] : '';
$dateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date_to'] ?? '')) === 1 ? (string) $_GET['date_to'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = adminPaginationPerPage();
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
    $hiddenEventTypes = function_exists('userActivityHiddenEventTypes') ? userActivityHiddenEventTypes() : [];
    $normalizeEventType = static function (string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '_', $value) ?? $value;
        return trim($value, '_');
    };
    $eventTypes = array_values(array_filter($eventTypes, static function ($type) use ($hiddenEventTypes, $normalizeEventType): bool {
        $eventType = (string) $type;
        $eventTypeKey = $normalizeEventType($eventType);
        return !in_array($eventTypeKey, $hiddenEventTypes, true) && !str_starts_with($eventTypeKey, 'topic_bulk_');
    }));
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
$activityPersonLabel = static function (array $event): string {
    $meta = [];
    if (!empty($event['metadata_json'])) {
        $decoded = json_decode((string) $event['metadata_json'], true);
        $meta = is_array($decoded) ? $decoded : [];
    }

    $candidates = [
        trim((string) ($meta['user_name_snapshot'] ?? '')),
        trim((string) ($meta['actor_name_snapshot'] ?? '')),
        trim((string) ($event['user_name'] ?? '')),
        trim((string) ($event['actor_name'] ?? '')),
        trim((string) ($meta['display_name'] ?? '')),
        trim((string) ($meta['username'] ?? '')),
        trim((string) ($meta['user_name'] ?? '')),
        trim((string) ($meta['name'] ?? '')),
        trim((string) ($meta['email'] ?? '')),
        trim((string) ($meta['user_email_snapshot'] ?? '')),
        trim((string) ($meta['actor_email_snapshot'] ?? '')),
        trim((string) ($event['user_email'] ?? '')),
        trim((string) ($event['actor_email'] ?? '')),
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '') {
            return $candidate;
        }
    }

    $userId = max(0, (int) ($event['user_id'] ?? 0));
    $subjectType = trim((string) ($event['subject_type'] ?? ''));
    $subjectId = max(0, (int) ($event['subject_id'] ?? 0));
    $actorId = max(0, (int) ($event['actor_user_id'] ?? 0));

    if ($userId > 0 || ($subjectType === 'user' && $subjectId > 0) || $actorId > 0) {
        return 'Silinmiş kullanıcı';
    }

    return 'Anonim kullanıcı';
};
$activitySubjectDescriptor = static function (array $event, array $subjectNames = [], array $meta = []): array {
    $subjectTypeKey = trim((string) ($event['subject_type'] ?? ''));
    $subjectTypes = [
        'topic' => 'Konu',
        'comment' => 'Yorum',
        'user' => 'Kullanıcı',
        'restriction' => 'Kısıtlama',
        'report' => 'Şikayet',
        'appeal' => 'İtiraz',
        'note' => 'Not',
        'category' => 'Kategori',
        'security_event' => 'Güvenlik',
    ];
    $subjectLabel = $subjectTypes[$subjectTypeKey] ?? ($subjectTypeKey !== '' ? ucwords(str_replace('_', ' ', $subjectTypeKey)) : '');

    $subjectId = (int) ($event['subject_id'] ?? 0);
    $subjectName = '';
    if ($subjectTypeKey !== '' && isset($subjectNames[$subjectTypeKey]) && is_array($subjectNames[$subjectTypeKey])) {
        $subjectName = trim((string) ($subjectNames[$subjectTypeKey][$subjectId] ?? ''));
    }
    if ($subjectName === '') {
        foreach (['title', 'name', 'username', 'display_name', 'user_name_snapshot', 'actor_name_snapshot'] as $metaKey) {
            if (!empty($meta[$metaKey]) && is_scalar($meta[$metaKey])) {
                $subjectName = trim((string) $meta[$metaKey]);
                break;
            }
        }
    }
    if ($subjectName === '' && !empty($event['title']) && trim((string) $event['title']) !== userActivityEventLabel((string) $event['event_type'])) {
        $subjectName = trim((string) $event['title']);
    }

    $summaryLabel = '';
    if ($subjectLabel !== '' && $subjectName !== '') {
        $summaryLabel = $subjectLabel . ': ' . $subjectName;
    } elseif ($subjectLabel !== '') {
        $summaryLabel = $subjectLabel;
    } elseif ($subjectName !== '') {
        $summaryLabel = $subjectName;
    }

    return [
        'type_key' => $subjectTypeKey,
        'label' => $subjectLabel,
        'id' => $subjectId,
        'name' => $subjectName,
        'summary' => $summaryLabel,
    ];
};
$queryForPage = static function (int $targetPage) use ($activityBuildUrl, $q, $userId, $eventGroup, $eventType, $ipAddress, $deviceType, $dateFrom, $dateTo): string {
    return $activityBuildUrl([
        'q' => $q,
        'user_id' => $userId > 0 ? $userId : null,
        'group' => $eventGroup,
        'event_type' => $eventType,
        'ip' => $ipAddress,
        'device' => $deviceType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'page' => $targetPage,
    ]);
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
            <form method="get" action="<?= htmlspecialchars($activityBuildUrl(), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-filter-row user-activity-filter admin-log-filter-form">
                <?php if ($showTabInput): ?>
                    <input type="hidden" name="tab" value="activity">
                <?php endif; ?>
                <input type="hidden" name="user_id" value="<?= $userId > 0 ? (int) $userId : '' ?>">
                <div class="ui-admin-filter-grow">
                    <label class="ui-admin-form-label">Ara</label>
                    <input type="text" name="q" class="ui-admin-form-control" placeholder="Kullanici adi, e-posta, kullanici ID, admin veya IP..." value="<?= htmlspecialchars($q) ?>">
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
                    <a href="<?= htmlspecialchars($activityBuildUrl(), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-circle"></i> Temizle</a>
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
                            <?= function_exists('avatarImageHtml') ? avatarImageHtml((string) ($selectedUser['username'] ?? ''), (string) ($selectedUser['avatar'] ?? ''), ['alt' => '']) : '' ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars((string) ($selectedUser['username'] ?? 'Kullanici')) ?></strong>
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
        <section class="admin-card user-activity-feed-card logs-list-card ui-panel ui-card">
            <div class="card-header user-activity-card-head logs-list-head ui-admin-card-header-actions ui-panel__head ui-card">
                <div>
                    <h3><i class="bi bi-activity"></i> Hareket Akışı</h3>
                    <span><?= number_format($totalEvents, 0, ',', '.') ?> kayıt</span>
                </div>
                <?php if ($canManageLogs): ?>
                    <div>
                        <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger" data-clear-logs-open>
                            <i class="bi bi-trash"></i> Kayıtları Temizle
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
                <?php if (empty($events)): ?>
                    <div class="ui-admin-empty ui-empty admin-log-empty">
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
                        $userSubjectSelect = (function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'username'))
                            ? "username AS display_name"
                            : "name AS display_name";
                        try {
                            if (!empty($subjectIds['topic'])) {
                                $tPh = implode(',', array_fill(0, count($subjectIds['topic']), '?'));
                                $tStmt = $pdo->prepare("SELECT id, title FROM topics WHERE id IN ($tPh)");
                                $tStmt->execute(array_values($subjectIds['topic']));
                                foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subjectNames['topic'][$row['id']] = $row['title'];
                            }
                            if (!empty($subjectIds['user'])) {
                                $uPh = implode(',', array_fill(0, count($subjectIds['user']), '?'));
                                $uStmt = $pdo->prepare("SELECT id, {$userSubjectSelect} FROM users WHERE id IN ($uPh)");
                                $uStmt->execute(array_values($subjectIds['user']));
                                foreach ($uStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subjectNames['user'][$row['id']] = $row['display_name'];
                            }
                            if (!empty($subjectIds['comment'])) {
                                $cPh = implode(',', array_fill(0, count($subjectIds['comment']), '?'));
                                $cStmt = $pdo->prepare("SELECT id, body FROM comments WHERE id IN ($cPh)");
                                $cStmt->execute(array_values($subjectIds['comment']));
                                foreach ($cStmt->fetchAll(PDO::FETCH_ASSOC) as $row) $subjectNames['comment'][$row['id']] = mb_substr(trim((string) $row['body']), 0, 40) . '...';
                            }
                            if (!empty($subjectIds['category'])) {
                                $catPh = implode(',', array_fill(0, count($subjectIds['category']), '?'));
                                $catStmt = $pdo->prepare("SELECT id, name FROM categories WHERE id IN ($catPh)");
                                $catStmt->execute(array_values($subjectIds['category']));
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
                            $personLabel = $activityPersonLabel($event);
                            $actionLabel = trim((string) ($event['title'] ?? '')) !== '' ? (string) $event['title'] : userActivityEventLabel((string) $event['event_type']);
                            $subjectInfo = $activitySubjectDescriptor($event, $subjectNames, $meta);
                            $summaryLabel = (string) ($subjectInfo['summary'] ?? '');
                            $eventLabel = userActivityEventLabel((string) $event['event_type']);
                            $requestMethod = trim((string) ($event['request_method'] ?? ''));
                            $requestPath = trim((string) ($event['request_path'] ?? ''));
                            $requestSummary = trim(implode(' ', array_values(array_filter([$requestMethod, $requestPath], static fn (string $value): bool => $value !== ''))));
                            if ($requestSummary === '') {
                                $requestSummary = '-';
                            }
                            $deviceSummary = trim((string) ($event['device_type'] ?? ''));
                            $browserSummary = trim((string) ($event['browser'] ?? ''));
                            $platformSummary = trim((string) ($event['platform'] ?? ''));
                            $ipSummary = trim((string) ($event['ip_address'] ?? ''));
                            $subjectSummary = '';
                            if (($subjectInfo['label'] ?? '') !== '') {
                                $subjectSummary = (string) $subjectInfo['label'];
                                if (($subjectInfo['name'] ?? '') !== '') {
                                    $subjectSummary .= ': ' . (string) $subjectInfo['name'];
                                }
                            }
                            $userIdValue = max(0, (int) ($event['user_id'] ?? 0));
                            $actorIdValue = max(0, (int) ($event['actor_user_id'] ?? 0));
                            $actorLabel = trim((string) ($meta['actor_name_snapshot'] ?? '')) ?: trim((string) ($event['actor_name'] ?? ''));
                            if ($actorLabel === '' && $actorIdValue > 0) {
                                $actorLabel = 'Silinmiş kullanıcı';
                            }
                            ?>
                            <article class="user-activity-item">
                                <div class="user-activity-dot tone-<?= htmlspecialchars($tone) ?>"><i class="bi bi-record-circle"></i></div>
                                <div class="user-activity-item-main">
                                    <div class="user-activity-row-top">
                                        <div class="user-activity-headline">
                                            <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($tone) ?>"><?= htmlspecialchars($groupLabels[$group] ?? $group) ?></span>
                                            <strong><?= htmlspecialchars($personLabel) ?></strong>
                                            <span class="user-activity-action"><?= htmlspecialchars($actionLabel) ?></span>
                                        </div>
                                        <time><?= htmlspecialchars($fmtDate((string) $event['created_at'])) ?></time>
                                    </div>
                                    <?php if ($summaryLabel !== '' && $summaryLabel !== $personLabel): ?>
                                        <div class="user-activity-row-meta user-activity-row-meta-simple">
                                            <span><i class="bi bi-bullseye"></i> <?= htmlspecialchars($summaryLabel) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <details class="user-activity-details">
                                        <summary class="user-activity-detail-toggle">
                                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                                            <span>Detay</span>
                                        </summary>
                                        <div class="user-activity-detail-panel">
                                            <div class="user-activity-detail-grid">
                                                <span><b>Olay</b><strong><?= htmlspecialchars($eventLabel) ?></strong></span>
                                                <span><b>Grup</b><strong><?= htmlspecialchars($groupLabels[$group] ?? $group) ?></strong></span>
                                                <span><b>Kullanıcı</b><strong><?= htmlspecialchars($personLabel) ?></strong></span>
                                                <span><b>Kullanıcı ID</b><strong><?= $userIdValue > 0 ? (string) $userIdValue : '-' ?></strong></span>
                                                <span><b>Aktör</b><strong><?= htmlspecialchars($actorLabel !== '' ? $actorLabel : '-') ?></strong></span>
                                                <span><b>Aktör ID</b><strong><?= $actorIdValue > 0 ? (string) $actorIdValue : '-' ?></strong></span>
                                                <span><b>Nesne</b><strong><?= htmlspecialchars($subjectSummary !== '' ? $subjectSummary : '-') ?></strong></span>
                                                <span><b>IP</b><strong><code><?= htmlspecialchars($ipSummary !== '' ? $ipSummary : '-') ?></code></strong></span>
                                                <span><b>Cihaz</b><strong><?= htmlspecialchars($deviceSummary !== '' ? $deviceSummary : '-') ?></strong></span>
                                                <span><b>Tarayıcı</b><strong><?= htmlspecialchars($browserSummary !== '' ? $browserSummary : '-') ?></strong></span>
                                                <span><b>Platform</b><strong><?= htmlspecialchars($platformSummary !== '' ? $platformSummary : '-') ?></strong></span>
                                                <span><b>İstek</b><strong><?= htmlspecialchars($requestSummary) ?></strong></span>
                                                <span><b>Zaman</b><strong><?= htmlspecialchars($fmtDate((string) $event['created_at'])) ?></strong></span>
                                            </div>
                                            <?php if (!empty($meta)): ?>
                                                <pre class="user-activity-json"><?= htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="user-activity-pagination-meta">
                            Sayfa <strong><?= (int) $page ?></strong> / <?= (int) $totalPages ?> · Toplam <?= number_format((int) ($totalEvents ?? 0)) ?> olay
                        </div>
                        <?= adminRenderPagination($totalPages, $page, $queryForPage, [
                            'wrapper_class' => 'user-activity-pagination-wrapper logs-pagination-wrapper',
                            'inner_class' => 'user-activity-pagination',
                            'aria_label' => 'Kullanıcı işlem günlüğü sayfalama',
                        ]) ?>
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
                <div class="card-header user-activity-card-head ui-panel__head ui-card"><h3><i class="bi bi-person-gear"></i> Yönetici İşlemleri</h3></div>
                <div class="card-body ui-panel__body">
                    <?php if (empty($adminRows)): ?>
                        <p class="ui-admin-muted-sm">Yönetici işlem kaydı yok.</p>
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

<?php if ($canManageLogs): ?>
<!-- Clear Logs Modal -->
<?php
$activityClearOptions = [
    [
        'value' => 'older_than_30_days',
        'label' => '30 Günden Eski Kayıtları Sil',
        'confirm_title' => 'Kayıtları Temizle',
    ],
];
if ($userId > 0 && $selectedUser) {
    $activityClearOptions[] = [
        'value' => 'user',
        'label' => 'Sadece Bu Kullanıcının Kayıtlarını Sil (' . (string) ($selectedUser['username'] ?? 'Kullanici') . ')',
        'confirm_title' => 'Kayıtları Temizle',
    ];
}
$activityClearOptions[] = [
    'value' => 'all',
    'label' => 'Tüm Sistemin Loglarını Sil (Tehlikeli)',
    'confirm_title' => 'Günlüğü Temizle',
];

$logClearModal = [
    'aria_label' => 'Kayıtları temizle',
    'title' => 'Kayıtları Temizle',
    'confirm_title' => 'Kayıtları Temizle',
    'form_action' => $activityBuildUrl(),
    'hidden_fields' => [
        ['name' => 'action', 'value' => 'clear_activity_logs'],
        ['name' => 'target_user_id', 'value' => $userId > 0 ? (int) $userId : 0],
    ],
    'scope_name' => 'scope',
    'options' => $activityClearOptions,
    'warning' => 'Bu işlem geri alınamaz. Silinen kayıtlar veritabanından kalıcı olarak silinecektir.',
];
include __DIR__ . '/../partials/log-clear-modal.php';
unset($logClearModal, $activityClearOptions);
?>
<?php endif; ?>
