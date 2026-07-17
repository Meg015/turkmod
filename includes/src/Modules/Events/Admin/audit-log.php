<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;

function eventsAuditLogRedirectUrl(): string
{
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = (string) parse_url($requestUri, PHP_URL_PATH);
    if ($path === '' || str_starts_with($path, '//')) {
        $path = (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/events-audit-log.php');
    }

    $query = [];
    $rawQuery = (string) parse_url($requestUri, PHP_URL_QUERY);
    if ($rawQuery !== '') {
        parse_str($rawQuery, $query);
    }
    unset($query['p']);

    return $path . ($query !== [] ? '?' . http_build_query($query) : '');
}

// eventsAdminStyles moved down to avoid HTML output before AJAX response
$ready = eventsTablesReady($pdo ?? null);
if ($ready) {
    eventsEnsureAuditLogTable($pdo ?? null);
}
$auditReady = $ready && eventsTableExists($pdo ?? null, 'events_audit_log');
$rows = [];
$totalRows = 0;
$perPage = function_exists('adminPaginationPerPage') ? adminPaginationPerPage() : 10;
$currentPage = max(1, (int)($_GET['p'] ?? 1));
$filterAction = is_scalar($_GET['action'] ?? null) ? (string)$_GET['action'] : 'all';
$filterDate = is_scalar($_GET['date'] ?? null) ? (string)$_GET['date'] : 'all';

if ($auditReady) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['_events_action'] ?? '') === 'clear_logs') {
        adminRunLogCleanup($pdo, [
            'action_type' => 'events_audit_logs_cleared',
            'scope' => 'all',
            'allowed_scopes' => ['all'],
            'permission' => 'events.manage',
            'permission_message' => 'Etkinlik loglarını temizlemek için gerekli izin hesabınıza tanımlanmamış.',
            'redirect_url' => eventsAuditLogRedirectUrl(),
            'ready' => $auditReady,
            'ready_message' => 'Etkinlik audit log tablosu hazır olmadığı için temizleme yapılamadı.',
            'source' => 'admin_events_audit_log',
            'activity_subject' => 'events',
            'delete' => static function (PDO $pdo): int {
                $count = (int) $pdo->query('SELECT COUNT(*) FROM events_audit_log')->fetchColumn();
                $pdo->exec('DELETE FROM events_audit_log');
                return $count;
            },
            'after' => static function (PDO $pdo): void {
                eventsAuditLog($pdo, 'audit_log_clear', 'events_audit_log', null, ['source' => 'admin_events_audit_log']);
            },
            'success_message' => 'Eski kayıtlar temizlendi.',
            'error_prefix' => 'Etkinlik audit logları temizlenemedi: ',
        ]);
    }

    try {
        $allowedActions = ['all', 'creates', 'updates', 'deletes', 'errors'];
        $allowedDates = ['all', '24h', '7d', '30d'];
        if (!in_array($filterAction, $allowedActions, true)) {
            $filterAction = 'all';
        }
        if (!in_array($filterDate, $allowedDates, true)) {
            $filterDate = 'all';
        }

        $whereSql = "1=1";
        $whereParams = [];

        if ($filterAction !== 'all') {
            if ($filterAction === 'errors') {
                $whereSql .= " AND (l.action LIKE '%fail%' OR l.action LIKE '%error%')";
            } elseif ($filterAction === 'deletes') {
                $whereSql .= " AND (l.action LIKE '%delete%' OR l.action LIKE '%remove%' OR l.action LIKE '%cancel%' OR l.action LIKE '%deactivate%')";
            } elseif ($filterAction === 'creates') {
                $whereSql .= " AND (l.action LIKE '%create%' OR l.action LIKE '%add%' OR l.action LIKE '%join%' OR l.action LIKE '%claim%' OR l.action LIKE '%spin%' OR l.action LIKE '%entry%')";
            } elseif ($filterAction === 'updates') {
                $whereSql .= " AND (l.action LIKE '%update%' OR l.action LIKE '%edit%' OR l.action LIKE '%config%' OR l.action LIKE '%activate%' OR l.action LIKE '%save%')";
            }
        }

        if ($filterDate !== 'all') {
            if ($filterDate === '24h') {
                $whereSql .= " AND l.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
            } elseif ($filterDate === '7d') {
                $whereSql .= " AND l.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            } elseif ($filterDate === '30d') {
                $whereSql .= " AND l.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            }
        }

        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM events_audit_log l WHERE $whereSql");
        $stmtTotal->execute($whereParams);
        $totalRows = (int)$stmtTotal->fetchColumn();

        $offset = ($currentPage - 1) * $perPage;

        $stmtRows = $pdo->prepare("SELECT l.*, u.username AS user_name FROM events_audit_log l LEFT JOIN users u ON u.id = l.user_id WHERE $whereSql ORDER BY l.id DESC LIMIT $perPage OFFSET $offset");
        $stmtRows->execute($whereParams);
        $rows = $stmtRows->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $lookups = [];
        foreach ($rows as $row) {
            $st = $row['subject_type'];
            $sid = $row['subject_id'];
            if ($st && $sid) {
                $lookups[$st][$sid] = true;
            }
        }

        $subjectNames = [];
        foreach ($lookups as $st => $idsArr) {
            $ids = array_keys($idsArr);
            $idList = implode(',', array_map('intval', $ids));
            if (!$idList) continue;

            try {
                if ($st === 'events_raffles' || $st === 'raffle') {
                    $q = $pdo->query("SELECT id, name FROM events_raffles WHERE id IN ($idList)")->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($q as $id => $val) $subjectNames[$st][$id] = $val;
                } elseif ($st === 'events_tasks' || $st === 'task') {
                    $q = $pdo->query("SELECT id, title FROM events_tasks WHERE id IN ($idList)")->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($q as $id => $val) $subjectNames[$st][$id] = $val;
                } elseif ($st === 'activity_rule') {
                    $q = $pdo->query("SELECT id, activity_type FROM events_activity_rules WHERE id IN ($idList)")->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($q as $id => $val) $subjectNames[$st][$id] = $val;
                } elseif ($st === 'events_wheel_rewards' || $st === 'wheel_reward') {
                    $q = $pdo->query("SELECT id, name FROM events_wheel_rewards WHERE id IN ($idList)")->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($q as $id => $val) $subjectNames[$st][$id] = $val;
                } elseif ($st === 'events_pools' || $st === 'pool_item') {
                    $q = $pdo->query("SELECT id, name FROM events_prize_pool_items WHERE id IN ($idList)")->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($q as $id => $val) $subjectNames[$st][$id] = $val;
                } elseif ($st === 'user' || $st === 'users') {
                    $q = $pdo->query("SELECT id, username FROM users WHERE id IN ($idList)")->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($q as $id => $val) $subjectNames[$st][$id] = $val;
                } elseif ($st === 'user_reward') {
                    $q = $pdo->query("SELECT ur.id, u.username FROM events_user_rewards ur LEFT JOIN users u ON u.id = ur.user_id WHERE ur.id IN ($idList)")->fetchAll(PDO::FETCH_KEY_PAIR);
                    foreach ($q as $id => $val) $subjectNames[$st][$id] = $val;
                }
            } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Audit admin list failed.', ['error' => $e->getMessage()], 'WARNING');
    }
}

eventsAdminStyles($baseUri ?? '');
?>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="audit-log">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice($ready); ?>
    <?php
    eventsAdminPageHero(
        'Audit Logları',
        'Etkinlik modülü içinde gerçekleşen yönetim ve kullanıcı işlemlerini işlem, kullanıcı, konu, IP ve tarih bilgisiyle inceleyin.',
        'bi-journal-check'
    );
    ?>
    <div class="admin-card ui-events-admin-panel ui-panel logs-list-card">
        <?php
        echo '<div class="ui-events-audit-toolbar logs-toolbar-shell logs-toolbar-head">';

        // Filter Form
        echo '<form method="get" class="ui-events-audit-filter-form logs-filter-form ui-admin-filter-row admin-log-filter-form">';
        echo '<select name="action" class="ui-events-compact-input ui-events-audit-select">';
        $actions = ['all' => 'Tüm İşlemler', 'creates' => 'Oluşturmalar', 'updates' => 'Güncellemeler', 'deletes' => 'Silmeler', 'errors' => 'Hatalar'];
        foreach ($actions as $k => $v) {
            $sel = (($filterAction ?? '') === $k) ? ' selected' : '';
            echo '<option value="' . e($k) . '"' . $sel . '>' . e($v) . '</option>';
        }
        echo '</select>';

        echo '<select name="date" class="ui-events-compact-input ui-events-audit-select">';
        $dates = ['all' => 'Tüm Zamanlar', '24h' => 'Son 24 Saat', '7d' => 'Son 7 Gün', '30d' => 'Son 30 Gün'];
        foreach ($dates as $k => $v) {
            $sel = (($filterDate ?? '') === $k) ? ' selected' : '';
            echo '<option value="' . e($k) . '"' . $sel . '>' . e($v) . '</option>';
        }
        echo '</select>';

        echo '<button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm">Filtrele</button>';
        if (($filterAction ?? 'all') !== 'all' || ($filterDate ?? 'all') !== 'all') {
            echo '<a href="?" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm ui-events-audit-clear-link">Temizle</a>';
        }
        echo '</form>';

        // Clear Button
        if ($auditReady && ($totalRows > 0 || ($filterAction ?? 'all') === 'all')) {
            echo '<form method="post" data-ui-events-confirm="Tüm işlem kayıtlarını geri dönülemez şekilde silmek istediğinize emin misiniz?">
                <input type="hidden" name="_events_action" value="clear_logs">
                ' . csrf_field() . '
                <button type="submit" class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm"><i class="bi bi-trash"></i> Logları Temizle</button>
            </form>';
        }
        echo '</div>';
        ?>
        <div class="card-body ui-events-table-wrap ui-events-admin-table-wrap ui-panel__body ui-table-wrap ui-surface admin-log-table-wrap">
            <?php if (!$auditReady): ?>
                <?php eventsAdminEmptyState('bi-database-x', 'Audit Log Tablosu Hazır Değil', 'events_audit_log tablosu bulunamadı. Veritabanı şemasını tamamladıktan sonra işlem kayıtları burada görünecek.'); ?>
            <?php elseif ($rows === []): ?>
                <?php eventsAdminEmptyState('bi-journal-x', 'Kayıt Bulunamadı', 'Henüz sistemde bir audit (işlem) kaydı yok.'); ?>
            <?php else: ?>
                <table class="ui-events-table admin-log-table">
                    <thead><tr><th>İşlem</th><th>Kullanıcı</th><th>Konu</th><th>IP</th><th>Tarih</th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php
                        $actionRaw = (string)$row['action'];
                        $iconClass = 'info';
                        $icon = 'bi-journal-text';

                        if (str_contains($actionRaw, 'update') || str_contains($actionRaw, 'edit') || str_contains($actionRaw, 'settings') || str_contains($actionRaw, 'config')) {
                            $iconClass = 'info';
                            $icon = 'bi-pencil';
                        } elseif (str_contains($actionRaw, 'create') || str_contains($actionRaw, 'add') || str_contains($actionRaw, 'join') || str_contains($actionRaw, 'spin') || str_contains($actionRaw, 'claim')) {
                            $iconClass = 'success';
                            $icon = 'bi-check-circle';
                        } elseif (str_contains($actionRaw, 'delete') || str_contains($actionRaw, 'remove') || str_contains($actionRaw, 'reject')) {
                            $iconClass = 'danger';
                            $icon = 'bi-trash';
                        } elseif (str_contains($actionRaw, 'fail') || str_contains($actionRaw, 'error')) {
                            $iconClass = 'danger';
                            $icon = 'bi-exclamation-triangle';
                        }

                        $actionMap = [
                            'wheel_config_update' => 'Çark Ayarları Güncellendi',
                            'wheel_reward_create' => 'Çark Ödülü Oluşturuldu',
                            'wheel_reward_update' => 'Çark Ödülü Güncellendi',
                            'wheel_reward_deactivate' => 'Çark Ödülü Pasifleştirildi',
                            'wheel_spin_delete' => 'Çark Kaydı Silindi',
                            'wheel_user_spins_delete' => 'Kullanıcı Çark Kayıtları Silindi',
                            'wheel_all_spins_delete' => 'Tüm Çark Kayıtları Silindi',
                            'raffle_entry_manual' => 'Çekiliş Katılımı Alındı',
                            'raffle_create' => 'Çekiliş Oluşturuldu',
                            'raffle_update' => 'Çekiliş Güncellendi',
                            'raffle_cancel' => 'Çekiliş İptal Edildi',
                            'raffle_draw' => 'Çekiliş Çekildi',
                            'reward_expired' => 'Ödül Süresi Doldu',
                            'reward_expire_sweep' => 'Süresi Dolan Ödüller Temizlendi',
                            'task_save' => 'Görev Kaydedildi',
                            'task_activate' => 'Görev Aktif Edildi',
                            'task_deactivate' => 'Görev Pasifleştirildi',
                            'task_claim' => 'Görev Ödülü Alındı',
                            'activity_rule_update' => 'Aktivite Kuralı Güncellendi',
                            'activity_record' => 'Aktivite Puanı Yazıldı',
                            'activity_record_skipped_points' => 'Aktivite Puanı Atlandı',
                            'activity_points_reversal' => 'Aktivite Puanı Geri Alındı',
                            'login_streak_reward' => 'Günlük Giriş Serisi Ödülü',
                            'bonus_spin_consumed' => 'Bonus Çark Hakkı Kullanıldı',
                            'extra_spin_purchased' => 'Ekstra Çark Hakkı Alındı',
                            'low_stock_alert' => 'Düşük Stok Uyarısı',
                            'audit_log_clear' => 'Audit Log Temizlendi',
                            'config_update' => 'Ayarlar Güncellendi',
                            'wheel_spin' => 'Çark Çevrildi',
                            'raffle_join' => 'Çekilişe Katılındı',
                            'reward_claim' => 'Ödül Alındı',
                            'reward_cancel' => 'Ödül İptal Edildi',
                            'pool_item_create' => 'Ödül Kataloğa Eklendi',
                            'pool_item_update' => 'Katalog Ödülü Güncellendi',
                            'create' => 'Oluşturuldu',
                            'update' => 'Güncellendi',
                            'delete' => 'Silindi',
                            'edit' => 'Düzenlendi',
                            'add' => 'Eklendi',
                            'remove' => 'Kaldırıldı'
                        ];

                        $wordMap = [
                            'activate' => 'Aktifleştirme', 'deactivate' => 'Pasifleştirme', 'save' => 'Kaydetme',
                            'activity' => 'Aktivite', 'rule' => 'Kural', 'record' => 'Kayıt', 'skipped' => 'Atlandı',
                            'bonus' => 'Bonus', 'extra' => 'Ekstra', 'purchased' => 'Satın Alındı', 'stock' => 'Stok',
                            'alert' => 'Uyarı', 'clear' => 'Temizleme', 'streak' => 'Seri', 'manual' => 'Manuel',
                            'config' => 'Ayar', 'update' => 'Güncelleme', 'create' => 'Oluşturma', 'delete' => 'Silme',
                            'edit' => 'Düzenleme', 'add' => 'Ekleme', 'remove' => 'Kaldırma', 'wheel' => 'Çark',
                            'spin' => 'Çevirme', 'raffle' => 'Çekiliş', 'join' => 'Katılma', 'reward' => 'Ödül',
                            'claim' => 'Talep', 'fail' => 'Başarısız', 'error' => 'Hata', 'user' => 'Kullanıcı',
                            'pool' => 'Havuz', 'task' => 'Görev', 'rewards' => 'Ödülleri', 'raffles' => 'Çekilişler',
                            'pools' => 'Havuzlar', 'tasks' => 'Görevler', 'events' => 'Etkinlik', 'item' => 'Öğe', 'items' => 'Öğeler',
                            'cancel' => 'İptal', 'draw' => 'Çekim', 'entries' => 'Kayıtlar', 'points' => 'Puan', 'history' => 'Geçmiş'
                        ];

                        if (isset($actionMap[$actionRaw])) {
                            $actionTitle = $actionMap[$actionRaw];
                        } else {
                            $words = explode('_', $actionRaw);
                            $translated = array_map(fn($w) => $wordMap[$w] ?? ucfirst($w), $words);
                            $actionTitle = implode(' ', $translated);
                        }

                        $subjectType = (string)($row['subject_type'] ?? '');
                        $subjectId = (string)($row['subject_id'] ?? '');

                        $subjectMap = [
                            'activity_rule' => 'Aktivite Kuralı',
                            'bonus_spin' => 'Bonus Çark Hakkı',
                            'wheel' => 'Çark',
                            'events_config' => 'Etkinlik Ayarları',
                            'events_wheel_rewards' => 'Çark Ödülü',
                            'events_raffles' => 'Çekiliş',
                            'events_pools' => 'Ödül Havuzu',
                            'events_tasks' => 'Görev',
                            'user_reward' => 'Kullanıcı Ödülü',
                            'pool_item' => 'Katalog Ödülü',
                            'events_audit_log' => 'İşlem Kaydı'
                        ];

                        $subjectMap += [
                            'wheel_reward' => "\u{00C7}ark \u{00D6}d\u{00FC}l\u{00FC}",
                            'raffle' => "\u{00C7}ekili\u{015F}",
                            'task' => "G\u{00F6}rev",
                            'wheel_spin' => "\u{00C7}ark Ge\u{00E7}mi\u{015F}i",
                            'wheel_spins' => "\u{00C7}ark Ge\u{00E7}mi\u{015F}i",
                        ];

                        $subjectStr = '';
                        if ($subjectType) {
                            if (isset($subjectMap[$subjectType])) {
                                $subjectName = $subjectMap[$subjectType];
                            } else {
                                $words = explode('_', str_replace('events_', '', $subjectType));
                                $translated = array_map(fn($w) => $wordMap[$w] ?? ucfirst($w), $words);
                                $subjectName = implode(' ', $translated);
                            }

                            $resolvedName = '';
                            if ($subjectId) {
                                $resolvedName = $subjectNames[$subjectType][$subjectId] ?? '';
                            }

                            $subjectStr = '<div class="ui-events-subject-inline">';
                            if ($resolvedName) {
                                $subjectStr .= '<span class="ui-events-badge ui-events-badge-info">' . e($subjectName) . ' <span class="ui-events-subject-separator">&bull;</span> ' . e($resolvedName) . '</span>';
                            } else {
                                $subjectStr .= '<span class="ui-events-badge ui-events-badge-info">' . e($subjectName) . '</span>';
                            }
                            $subjectStr .= '</div>';
                        } else {
                            $subjectStr = '<span class="ui-events-badge ui-events-badge-muted">Sistem Konfigürasyonu</span>';
                        }
                        ?>
                        <tr>
                            <td>
                                <span class="ui-admin-badge ui-admin-badge-<?= $iconClass ?> ui-events-admin-badge-inline">
                                    <i class="bi <?= $icon ?>"></i> <?= e($actionTitle) ?>
                                </span>
                            </td>
                            <td><?= e((string)($row['user_name'] ?? '-')) ?></td>
                            <td><?= $subjectStr ?></td>
                            <td class="ui-events-admin-code-cell"><?= e((string)($row['ip_address'] ?? '-')) ?></td>
                            <td><?= e(eventsFormatDateTime((string)$row['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($totalRows > $perPage): ?>
                    <?php
                    $totalPages = max(1, (int)ceil($totalRows / $perPage));
                    $paginationQuery = $_GET;
                    unset($paginationQuery['p']);
                    $paginationBaseUrl = '?' . (count($paginationQuery) > 0 ? http_build_query($paginationQuery) . '&' : '') . 'p=';
                    echo adminRenderPagination($totalPages, $currentPage, static fn (int $targetPage): string => $paginationBaseUrl . $targetPage, [
                        'wrapper_class' => 'ui-events-audit-pagination',
                        'aria_label' => 'Etkinlik audit log sayfalama',
                    ]);
                    ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
