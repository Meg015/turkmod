<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/../includes/src/Engine/Logs/Support/helpers.php';

adminRequirePermission('logs.view', 'Uygulama loglarını görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'Uygulama Logları';

if (!function_exists('applicationLogsLevelBadgeClass')) {
    function applicationLogsLevelBadgeClass(string $level): string
    {
        $normalized = strtolower(trim($level));
        if (in_array($normalized, ['emergency', 'alert', 'critical', 'error'], true)) {
            return 'admin-badge-danger';
        }
        if (in_array($normalized, ['warning', 'warn'], true)) {
            return 'admin-badge-warning';
        }
        if ($normalized === 'notice') {
            return 'admin-badge-info';
        }
        if ($normalized === 'info') {
            return 'admin-badge-success';
        }
        return 'admin-badge-secondary';
    }
}

$normalizeDate = static function ($value): string {
    $date = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if (in_array($postAction, ['clear_old', 'clear_filtered', 'clear_all'], true)) {
        $postSearch = trim((string) ($_POST['q'] ?? ''));
        $postLevel = trim((string) ($_POST['level'] ?? ''));
        $postChannel = trim((string) ($_POST['channel'] ?? ''));
        $postDateFrom = $normalizeDate($_POST['date_from'] ?? '');
        $postDateTo = $normalizeDate($_POST['date_to'] ?? '');

        $redirectParams = array_filter([
            'q' => $postSearch,
            'level' => $postLevel,
            'channel' => $postChannel,
            'date_from' => $postDateFrom,
            'date_to' => $postDateTo,
        ], static fn ($value): bool => $value !== '' && $value !== null);
        $redirectUrl = 'application-logs.php' . ($redirectParams !== [] ? '?' . http_build_query($redirectParams) : '');

        if ($postAction === 'clear_all') {
            adminRunLogCleanup($pdo, [
                'action_type' => 'application_logs_cleared',
                'scope' => 'all',
                'allowed_scopes' => ['all'],
                'permission' => 'logs.manage',
                'permission_message' => 'Uygulama loglarını temizlemek için gerekli izin hesabınıza tanımlanmamış.',
                'redirect_url' => $redirectUrl,
                'source' => 'application_logs',
                'delete' => static fn (PDO $pdo): int => appLogsClearAll($pdo),
                'success_message' => static fn (int $deleted): string => $deleted . ' uygulama logu temizlendi.',
            ]);
        }

        if ($postAction === 'clear_old') {
            $days = max(7, min(3650, (int) ($_POST['days'] ?? 90)));
            adminRunLogCleanup($pdo, [
                'action_type' => 'application_logs_cleared',
                'scope' => 'old',
                'allowed_scopes' => ['old'],
                'permission' => 'logs.manage',
                'permission_message' => 'Uygulama loglarını temizlemek için gerekli izin hesabınıza tanımlanmamış.',
                'redirect_url' => $redirectUrl,
                'source' => 'application_logs',
                'delete' => static fn (PDO $pdo): int => appLogsClearOld($pdo, $days),
                'context' => [
                    'days' => $days,
                ],
                'success_message' => static fn (int $deleted): string => $deleted . ' kayıt silindi (' . $days . ' gün önceki ve daha eski).',
            ]);
        }

        $filters = [
            'q' => $postSearch,
            'level' => $postLevel,
            'channel' => $postChannel,
            'date_from' => $postDateFrom,
            'date_to' => $postDateTo,
        ];
        $hasFilter = implode('', $filters) !== '';
        adminRunLogCleanup($pdo, [
            'action_type' => 'application_logs_cleared',
            'scope' => 'filtered',
            'allowed_scopes' => ['filtered'],
            'permission' => 'logs.manage',
            'permission_message' => 'Uygulama loglarını temizlemek için gerekli izin hesabınıza tanımlanmamış.',
            'redirect_url' => $redirectUrl,
            'source' => 'application_logs',
            'validate' => static fn (): string => $hasFilter ? '' : 'Filtre seçmeden filtreye göre temizleme yapamazsınız.',
            'delete' => static fn (PDO $pdo): int => appLogsClearFiltered($pdo, $postSearch, $postLevel, $postChannel, $postDateFrom, $postDateTo),
            'context' => [
                'filters' => $filters,
            ],
            'success_message' => static fn (int $deleted): string => $deleted . ' filtreli uygulama logu silindi.',
        ]);
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$filterLevel = trim((string) ($_GET['level'] ?? ''));
$filterChannel = trim((string) ($_GET['channel'] ?? ''));
$dateFrom = $normalizeDate($_GET['date_from'] ?? '');
$dateTo = $normalizeDate($_GET['date_to'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = adminPaginationPerPage();

$logs = ['items' => [], 'total' => 0, 'page' => 1, 'perPage' => $perPage];
$stats = ['total' => 0, 'total_24h' => 0, 'total_7d' => 0, 'channels' => 0, 'errors_24h' => 0, 'errors_7d' => 0];
$levels = [];
$channels = [];

if ($pdo) {
    try {
        $logs = appLogsGetList($pdo, $search, $filterLevel, $filterChannel, $page, $perPage, $dateFrom, $dateTo);
        if (!empty($logs['items']) && function_exists('appLogsDecorateItems')) {
            $logs['items'] = appLogsDecorateItems($pdo, $logs['items']);
        }
        $stats = appLogsGetStats($pdo);
        if (!isset($stats['total_24h'])) {
            $stats['total_24h'] = (int) $pdo->query("SELECT COUNT(*) FROM application_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)")->fetchColumn();
        }
        if (!isset($stats['total_7d'])) {
            $stats['total_7d'] = (int) $pdo->query("SELECT COUNT(*) FROM application_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
        }
        $levels = appLogsGetLevels($pdo);
        $channels = appLogsGetChannels($pdo);
    } catch (Throwable $e) {
        flash('error', 'Uygulama loglari yuklenemedi: ' . safeErrorMessage($e));
    }
}

if ($filterLevel !== '' && !in_array($filterLevel, $levels, true)) {
    $levels[] = $filterLevel;
    sort($levels);
}
if ($filterChannel !== '' && !in_array($filterChannel, $channels, true)) {
    $channels[] = $filterChannel;
    sort($channels);
}

$logsPerPage = max(1, (int) ($logs['perPage'] ?? $perPage));
$logsTotalRows = max(0, (int) ($logs['total'] ?? 0));
$logsTotalPages = max(1, (int) ceil($logsTotalRows / $logsPerPage));
if ($pdo && $page > $logsTotalPages) {
    $page = $logsTotalPages;
    try {
        $logs = appLogsGetList($pdo, $search, $filterLevel, $filterChannel, $page, $perPage, $dateFrom, $dateTo);
        if (!empty($logs['items']) && function_exists('appLogsDecorateItems')) {
            $logs['items'] = appLogsDecorateItems($pdo, $logs['items']);
        }
    } catch (Throwable $e) {
        flash('error', 'Uygulama logları yeniden sayfalanamadı: ' . safeErrorMessage($e));
    }
}

$hasFilters = $search !== '' || $filterLevel !== '' || $filterChannel !== '' || $dateFrom !== '' || $dateTo !== '';
$canManageLogs = adminCurrentUserCan('logs.manage');

$successMsg = get_flash('success');
$errorMsg = get_flash('error');

$renderFilterHiddenFields = static function () use ($search, $filterLevel, $filterChannel, $dateFrom, $dateTo): void {
    echo '<input type="hidden" name="q" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="level" value="' . htmlspecialchars($filterLevel, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="channel" value="' . htmlspecialchars($filterChannel, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="date_from" value="' . htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') . '">';
    echo '<input type="hidden" name="date_to" value="' . htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') . '">';
};

require_once __DIR__ . '/header.php';
?>

<?php adminRenderLogsSubtabs('application'); ?>

<div class="logs-page application-logs-page">
    <section class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <span class="ui-admin-kicker"><i class="bi bi-journal-code"></i> Sistem kayıtları</span>
            <h2>Uygulama Logları</h2>
            <p>Sistem, hata ve bakım kayıtlarını tek listede izleyin.</p>
        </div>
    </section>

    <div class="ui-admin-alert ui-admin-alert-info ui-alert">
        <i class="bi bi-info-circle"></i>
        <div>
            <strong>Hata merkezi güncellendi:</strong>
            Sistem kaynaklı hataları tek noktadan takip etmek için
            <a href="<?= htmlspecialchars(rtrim((string) $baseUri, '/') . '/admin/system-health.php?tab=logs', ENT_QUOTES, 'UTF-8') ?>">Sistem Sağlığı > Loglar & Hatalar</a>
            sekmesini kullanın.
        </div>
    </div>
    <div class="admin-stat-grid logs-summary application-logs-summary ui-grid">
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-journal-code"></i></div>
            <div class="stat-content"><span class="stat-label">Toplam Kayıt</span><span class="stat-value"><?= number_format((int) ($stats['total'] ?? 0)) ?></span></div>
        </div>
        <div class="admin-stat-card stat-success logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-clock"></i></div>
            <div class="stat-content"><span class="stat-label">Kayıt (24s)</span><span class="stat-value"><?= number_format((int) ($stats['total_24h'] ?? 0)) ?></span></div>
        </div>
        <div class="admin-stat-card stat-info logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-calendar-week"></i></div>
            <div class="stat-content"><span class="stat-label">Kayıt (7g)</span><span class="stat-value"><?= number_format((int) ($stats['total_7d'] ?? 0)) ?></span></div>
        </div>
        <div class="admin-stat-card stat-success logs-stat ui-card">
            <div class="stat-icon"><i class="bi bi-diagram-3"></i></div>
            <div class="stat-content"><span class="stat-label">Aktif Kanal</span><span class="stat-value"><?= number_format((int) ($stats['channels'] ?? 0)) ?></span></div>
        </div>
    </div>

    <div class="admin-card logs-toolbar-card ui-panel">
        <div class="card-body ui-admin-card-compact ui-panel__body ui-card application-logs-toolbar logs-toolbar-shell">
            <form method="get" action="application-logs.php" class="ui-admin-filter-row logs-filter-form application-logs-filter-form admin-log-filter-form">
                <div class="ui-admin-filter-grow application-logs-search">
                    <label class="ui-admin-form-label">Ara</label>
                    <input type="text" name="q" class="ui-admin-form-control" placeholder="Mesaj, kanal, seviye veya IP ara..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Seviye</label>
                    <select name="level" class="ui-admin-form-select">
                        <option value="">Tüm Seviyeler</option>
                        <?php foreach ($levels as $level): ?>
                            <option value="<?= htmlspecialchars((string) $level, ENT_QUOTES, 'UTF-8') ?>" <?= $filterLevel === (string) $level ? 'selected' : '' ?>>
                                <?= htmlspecialchars(function_exists('appLogsLevelLabel') ? appLogsLevelLabel((string) $level) : strtoupper((string) $level), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Kanal</label>
                    <select name="channel" class="ui-admin-form-select">
                        <option value="">Tüm Kanallar</option>
                        <?php foreach ($channels as $channel): ?>
                            <option value="<?= htmlspecialchars((string) $channel, ENT_QUOTES, 'UTF-8') ?>" <?= $filterChannel === (string) $channel ? 'selected' : '' ?>>
                                <?= htmlspecialchars(function_exists('appLogsChannelLabel') ? appLogsChannelLabel((string) $channel) : (string) $channel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Başlangıç</label>
                    <input type="date" name="date_from" class="ui-admin-form-control" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>" aria-label="Başlangıç tarihi">
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Bitiş</label>
                    <input type="date" name="date_to" class="ui-admin-form-control" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>" aria-label="Bitiş tarihi">
                </div>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                <?php if ($hasFilters): ?>
                    <a href="application-logs.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-circle"></i> Temizle</a>
                <?php endif; ?>
            </form>

            <?php if ($canManageLogs): ?>
                <div class="application-logs-maintenance">
                    <div class="application-logs-maintenance-label"><i class="bi bi-tools"></i> Bakım işlemleri</div>
                    <div class="application-logs-actions logs-toolbar-actions">
                        <button type="button" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-xs" data-clear-logs-open>
                            <i class="bi bi-trash"></i> Günlüğü Temizle
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card logs-list-card ui-panel">
        <div class="card-header logs-list-head ui-admin-card-header-actions ui-panel__head ui-card">
            <div>
                <h3><i class="bi bi-journal-code"></i> Uygulama Logları</h3>
                <span><?= number_format((int) ($logs['total'] ?? 0), 0, ',', '.') ?> kayıt</span>
            </div>
        </div>
        <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
            <?php if (empty($logs['items'])): ?>
                <div class="ui-admin-empty ui-empty admin-log-empty">
                    <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-journal-code"></i></div>
                    <h3 class="ui-admin-empty-title ui-empty">Kayıt bulunamadı</h3>
                    <p class="ui-admin-empty-desc ui-empty"><?= $hasFilters ? 'Seçili filtreyle eşleşen uygulama logu yok.' : 'Henüz uygulama logu oluşmamış.' ?></p>
                </div>
            <?php else: ?>
                <div class="table-wrapper ui-table-wrap ui-surface admin-log-table-wrap">
                    <table class="admin-table admin-log-table">
                        <thead>
                            <tr>
                            <th>Tarih</th>
                            <th>Seviye</th>
                            <th>Kanal</th>
                            <th>Mesaj</th>
                            <th>IP</th>
                            <th>Ayrıntı</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs['items'] as $log): ?>
                            <?php
                            $level = (string) ($log['level'] ?? '');
                            $createdAtRaw = (string) ($log['created_at'] ?? '');
                            $createdAtTs = strtotime($createdAtRaw);
                            $createdLabel = $createdAtTs !== false ? date('d.m.Y H:i', $createdAtTs) : $createdAtRaw;
                            $levelLabel = (string) ($log['level_label'] ?? (function_exists('appLogsLevelLabel') ? appLogsLevelLabel($level) : strtoupper($level !== '' ? $level : 'unknown')));
                            $channelLabel = (string) ($log['channel_label'] ?? (function_exists('appLogsChannelLabel') ? appLogsChannelLabel((string) ($log['channel'] ?? '')) : (string) ($log['channel'] ?? '-')));
                            $humanMessage = trim((string) ($log['human_message'] ?? (string) ($log['message'] ?? '')));
                            $contextSummary = trim((string) ($log['context_summary'] ?? ''));
                            $technicalContext = trim((string) ($log['context_technical'] ?? ''));
                            ?>
                            <tr>
                                <td class="ui-admin-table-cell-date"><?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <span class="admin-badge <?= htmlspecialchars(applicationLogsLevelBadgeClass($level), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($levelLabel !== '' ? $levelLabel : 'Bilinmiyor', ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="ui-admin-table-cell-secondary ui-admin-muted-sm"><?= htmlspecialchars($channelLabel !== '' ? $channelLabel : '-', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="ui-admin-table-cell-desc ui-admin-log-message-cell">
                                    <div class="ui-admin-log-message-title"><?= htmlspecialchars($humanMessage !== '' ? $humanMessage : '-', ENT_QUOTES, 'UTF-8') ?></div>
                                </td>
                                <td class="ui-admin-table-cell-secondary ui-admin-muted-sm"><?= htmlspecialchars((string) (($log['ip_address'] ?? '') !== '' ? $log['ip_address'] : '-'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell">
                                    <div class="ui-admin-log-summary"><?= htmlspecialchars($contextSummary !== '' ? $contextSummary : 'Ek detay yok', ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if ($technicalContext !== ''): ?>
                                        <details class="ui-admin-log-technical">
                                            <summary><i class="bi bi-code-slash"></i> Teknik ayrıntı</summary>
                                            <pre class="ui-admin-log-technical-body"><?= htmlspecialchars($technicalContext, ENT_QUOTES, 'UTF-8') ?></pre>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                $perPage = max(1, (int) ($logs['perPage'] ?? adminPaginationPerPage()));
                $totalRows = max(0, (int) ($logs['total'] ?? 0));
                $totalPages = (int) ceil($totalRows / $perPage);
                if ($totalPages > 1):
                    $pageParams = array_filter([
                        'q' => $search,
                        'level' => $filterLevel,
                        'channel' => $filterChannel,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                    ], static fn ($value): bool => $value !== '' && $value !== null);
                    $pageBase = 'application-logs.php?' . ($pageParams ? http_build_query($pageParams) . '&' : '') . 'page=';
                    echo adminRenderPagination($totalPages, $page, static fn (int $targetPage): string => $pageBase . $targetPage, [
                        'wrapper_class' => 'logs-pagination-wrapper',
                        'aria_label' => 'Uygulama logları sayfalama',
                    ]);
                endif;
                ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canManageLogs): ?>
    <?php
    $applicationClearOptions = [];
    if ($hasFilters) {
        $applicationClearOptions[] = [
            'value' => 'clear_filtered',
            'label' => 'Aktif filtreye uyan kayıtları sil',
            'confirm_title' => 'Kayıtları Temizle',
        ];
    }
    $applicationClearOptions[] = [
        'value' => 'clear_old',
        'label' => 'Belirtilen günden eski kayıtları sil',
        'confirm_title' => 'Kayıtları Temizle',
    ];
    $applicationClearOptions[] = [
        'value' => 'clear_all',
        'label' => 'Tüm uygulama günlüğünü sil (Tehlikeli)',
        'confirm_title' => 'Günlüğü Temizle',
    ];

    $logClearModal = [
        'aria_label' => 'Uygulama günlüğünü temizle',
        'title' => 'Günlüğü Temizle',
        'form_action' => 'application-logs.php',
        'scope_name' => 'action',
        'options' => $applicationClearOptions,
        'extra_hidden_renderer' => static function () use ($renderFilterHiddenFields): void {
            $renderFilterHiddenFields();
        },
        'fields' => [
            [
                'show_for' => 'clear_old',
                'label' => 'Gün sınırı',
                'input' => [
                    'id' => 'application-clear-days',
                    'type' => 'number',
                    'name' => 'days',
                    'min' => '7',
                    'max' => '3650',
                    'step' => '1',
                    'value' => '90',
                    'class' => 'ui-admin-form-control',
                    'aria-label' => 'Gün sayısı',
                ],
            ],
        ],
        'warning' => 'Seçilen kapsam kalıcı olarak silinir. Güvenlik incelemeleri için son logların tutulması önerilir. İşlem geri alınamaz.',
    ];
    include __DIR__ . '/partials/log-clear-modal.php';
    unset($logClearModal, $applicationClearOptions);
    ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
