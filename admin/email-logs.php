<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

adminRequirePermission('logs.view', 'E-posta loglarını görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'E-posta Logları';

if (function_exists('emailLogsEnsureSchema')) {
    emailLogsEnsureSchema($pdo);
}

$emailLogsSchemaReady = $pdo instanceof PDO && (!function_exists('emailLogsTableExists') || emailLogsTableExists($pdo));
$pageError = '';
$canManageLogs = adminCurrentUserCan('logs.manage');

$normalizeDate = static function ($value): string {
    $date = trim((string) $value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';
};

$emailLogText = static function ($value, string $fallback = '—'): string {
    if ($value === null || $value === '') {
        return $fallback;
    }

    if (is_bool($value)) {
        return $value ? 'Evet' : 'Hayır';
    }

    if (is_array($value)) {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) && $encoded !== '' ? $encoded : $fallback;
    }

    if (is_object($value)) {
        return '[nesne]';
    }

    return trim((string) $value) !== '' ? trim((string) $value) : $fallback;
};

$emailLogPrettyJson = static function (?string $json) use ($emailLogText): string {
    $raw = trim((string) $json);
    if ($raw === '') {
        return '';
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($pretty) && $pretty !== '') {
            return $pretty;
        }
    }

    return $emailLogText($raw, '');
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');
    if ($postAction === 'clear_all_email_logs') {
        $redirectUrl = 'email-logs.php';
        adminRunLogCleanup($pdo, [
            'action_type' => 'email_logs_cleared',
            'scope' => 'all',
            'allowed_scopes' => ['all'],
            'permission' => 'logs.manage',
            'permission_message' => 'E-posta loglarını temizlemek için gerekli izin hesabınıza tanımlanmamış.',
            'redirect_url' => $redirectUrl,
            'ready' => $emailLogsSchemaReady && function_exists('emailLogsClearAll'),
            'ready_message' => 'E-posta log tablosu hazır olmadığı için temizleme yapılamadı.',
            'source' => 'email_logs',
            'delete' => static fn (PDO $pdo): int => emailLogsClearAll($pdo),
            'success_message' => static fn (int $deleted): string => $deleted . ' e-posta logu tamamen silindi.',
            'error_prefix' => 'E-posta logları temizlenemedi: ',
        ]);
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'all'));
$sourceFilter = trim((string) ($_GET['source'] ?? 'all'));
$driverFilter = trim((string) ($_GET['driver'] ?? 'all'));
$dateFrom = $normalizeDate($_GET['date_from'] ?? '');
$dateTo = $normalizeDate($_GET['date_to'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = adminPaginationPerPage();

$emailStatusOptions = [
    'sent' => 'Gönderildi',
    'failed' => 'Hatalı',
    'queued' => 'Kuyrukta',
    'processing' => 'İşleniyor',
    'skipped' => 'Atlandı',
];
$emailSourceOptions = [
    'auth' => 'Kimlik Doğrulama',
    'contact' => 'İletişim',
    'events' => 'Etkinlikler',
    'notifications' => 'Bildirimler',
    'settings' => 'Ayarlar',
    'system' => 'Sistem',
    'manual' => 'Manuel',
    'cron' => 'Cron',
];
$emailDriverOptions = [
    'smtp' => 'SMTP',
    'sendmail' => 'Sendmail',
    'mail' => 'PHP mail',
];

$emailStats = [
    'total' => 0,
    'total_24h' => 0,
    'total_7d' => 0,
    'sent_24h' => 0,
    'failed_24h' => 0,
];
$emailLogs = [
    'items' => [],
    'total' => 0,
    'page' => $page,
    'perPage' => $perPage,
];

if ($emailLogsSchemaReady && $pdo) {
    try {
        if (function_exists('emailLogsGetStatuses')) {
            foreach (emailLogsGetStatuses($pdo) as $statusValue) {
                $statusValue = trim((string) $statusValue);
                if ($statusValue !== '' && !array_key_exists($statusValue, $emailStatusOptions)) {
                    $emailStatusOptions[$statusValue] = ucwords(str_replace('_', ' ', $statusValue));
                }
            }
        }
        if (function_exists('emailLogsGetSources')) {
            foreach (emailLogsGetSources($pdo) as $sourceValue) {
                $sourceValue = trim((string) $sourceValue);
                if ($sourceValue !== '' && !array_key_exists($sourceValue, $emailSourceOptions)) {
                    $emailSourceOptions[$sourceValue] = ucwords(str_replace(['-', '_'], ' ', $sourceValue));
                }
            }
        }
        if (function_exists('emailLogsGetDrivers')) {
            foreach (emailLogsGetDrivers($pdo) as $driverValue) {
                $driverValue = trim((string) $driverValue);
                if ($driverValue !== '' && !array_key_exists($driverValue, $emailDriverOptions)) {
                    $emailDriverOptions[$driverValue] = ucwords(str_replace(['-', '_'], ' ', $driverValue));
                }
            }
        }

        $emailStats = function_exists('emailLogsGetStats') ? emailLogsGetStats($pdo) : $emailStats;
        $emailLogs = function_exists('emailLogsGetList')
            ? emailLogsGetList(
                $pdo,
                $search,
                $statusFilter !== 'all' ? $statusFilter : '',
                $sourceFilter !== 'all' ? $sourceFilter : '',
                $driverFilter !== 'all' ? $driverFilter : '',
                $page,
                $perPage,
                $dateFrom,
                $dateTo
            )
            : $emailLogs;
    } catch (Throwable $e) {
        $pageError = 'E-posta logları yüklenemedi: ' . safeErrorMessage($e);
    }
}

if ($statusFilter !== 'all' && $statusFilter !== '' && !array_key_exists($statusFilter, $emailStatusOptions)) {
    $emailStatusOptions[$statusFilter] = ucwords(str_replace('_', ' ', $statusFilter));
}
if ($sourceFilter !== 'all' && $sourceFilter !== '' && !array_key_exists($sourceFilter, $emailSourceOptions)) {
    $emailSourceOptions[$sourceFilter] = ucwords(str_replace(['-', '_'], ' ', $sourceFilter));
}
if ($driverFilter !== 'all' && $driverFilter !== '' && !array_key_exists($driverFilter, $emailDriverOptions)) {
    $emailDriverOptions[$driverFilter] = ucwords(str_replace(['-', '_'], ' ', $driverFilter));
}

if ($pageError === '' && !$emailLogsSchemaReady) {
    $pageError = 'E-posta log tablosu henüz hazır değil. Migration uygulanmamış olabilir veya runtime şema güncellemesi kapalı olabilir.';
}

$hasFilters = $search !== '' || $statusFilter !== 'all' || $sourceFilter !== 'all' || $driverFilter !== 'all' || $dateFrom !== '' || $dateTo !== '';
$successMsg = trim((string) (get_flash('success') ?? ''));
$errorMsg = trim((string) (get_flash('error') ?? ''));

$emailLogsPerPage = max(1, (int) ($emailLogs['perPage'] ?? $perPage));
$emailLogsTotal = max(0, (int) ($emailLogs['total'] ?? 0));
$emailLogsTotalPages = (int) ceil($emailLogsTotal / $emailLogsPerPage);
if ($emailLogsSchemaReady && $pdo && $emailLogsTotalPages > 0 && $page > $emailLogsTotalPages && function_exists('emailLogsGetList')) {
    $page = $emailLogsTotalPages;
    try {
        $emailLogs = emailLogsGetList(
            $pdo,
            $search,
            $statusFilter !== 'all' ? $statusFilter : '',
            $sourceFilter !== 'all' ? $sourceFilter : '',
            $driverFilter !== 'all' ? $driverFilter : '',
            $page,
            $perPage,
            $dateFrom,
            $dateTo
        );
        $emailLogsPerPage = max(1, (int) ($emailLogs['perPage'] ?? $perPage));
        $emailLogsTotal = max(0, (int) ($emailLogs['total'] ?? 0));
        $emailLogsTotalPages = (int) ceil($emailLogsTotal / $emailLogsPerPage);
    } catch (Throwable $e) {
        $pageError = 'E-posta logları yeniden sayfalanamadı: ' . safeErrorMessage($e);
    }
}

require_once __DIR__ . '/header.php';
?>

<?php adminRenderLogsSubtabs('email'); ?>

<div class="logs-page email-logs-page">
    <?= adminRenderLogPageHero('bi-envelope-paper', 'Gönderim izi', 'E-posta Logları', 'SMTP, mail() ve kuyruk kaynaklı gönderimlerin teknik izlerini tek ekranda takip edin.') ?>

    <?= adminRenderAlert($successMsg, 'success', ['icon' => 'bi-check2-circle']) ?>
    <?= adminRenderAlert($errorMsg, 'danger', ['icon' => 'bi-exclamation-octagon']) ?>
    <?php if ($pageError !== ''): ?>
        <?= adminRenderAlert($pageError, 'warning', ['icon' => 'bi-database-exclamation', 'title' => 'Yükleme notu:']) ?>
    <?php endif; ?>

    <?= adminRenderLogStatCards([
        ['tone' => 'info', 'icon' => 'bi-journal-code', 'label' => 'Toplam Log', 'value' => number_format((int) ($emailStats['total'] ?? 0), 0, ',', '.')],
        ['tone' => 'success', 'icon' => 'bi-clock', 'label' => '24 Saat', 'value' => number_format((int) ($emailStats['total_24h'] ?? 0), 0, ',', '.')],
        ['tone' => 'info', 'icon' => 'bi-calendar-week', 'label' => '7 Gün', 'value' => number_format((int) ($emailStats['total_7d'] ?? 0), 0, ',', '.')],
        ['tone' => 'success', 'icon' => 'bi-send-check', 'label' => 'Gönderildi 24s', 'value' => number_format((int) ($emailStats['sent_24h'] ?? 0), 0, ',', '.')],
        ['tone' => 'danger', 'icon' => 'bi-exclamation-octagon', 'label' => 'Hatalı 24s', 'value' => number_format((int) ($emailStats['failed_24h'] ?? 0), 0, ',', '.')],
    ], ['class' => 'email-logs-summary', 'aria_label' => 'E-posta logları özeti']) ?>

    <?= adminRenderAlert('', 'info', [
        'icon' => 'bi-shield-check',
        'html' => '<div><strong>Teknik ayrıntılar korunur:</strong> SMTP kodu, sağlayıcı yanıtı, exception bilgisi ve kayıt bağlamı saklanır; parola, token ve cookie gibi hassas alanlar maskeleme ile korunur.</div>',
    ]) ?>

    <?= adminRenderLogToolbarOpen() ?>
            <form method="get" action="email-logs.php" class="logs-filter-form ui-admin-filter-row admin-log-filter-form admin-filter-form">
                <div class="ui-admin-filter-grow-lg">
                    <label class="ui-admin-form-label" for="email-log-q">Ara</label>
                    <input id="email-log-q" type="text" name="q" class="ui-admin-form-control" placeholder="Alıcı, konu, kaynak, hata veya yanıt ara..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label" for="email-log-status">Durum</label>
                    <select id="email-log-status" name="status" class="ui-admin-form-select">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tümü</option>
                        <?php foreach ($emailStatusOptions as $statusValue => $statusLabel): ?>
                            <option value="<?= htmlspecialchars((string) $statusValue, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === (string) $statusValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $statusLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label" for="email-log-source">Kaynak</label>
                    <select id="email-log-source" name="source" class="ui-admin-form-select">
                        <option value="all" <?= $sourceFilter === 'all' ? 'selected' : '' ?>>Tümü</option>
                        <?php foreach ($emailSourceOptions as $sourceValue => $sourceLabel): ?>
                            <option value="<?= htmlspecialchars((string) $sourceValue, ENT_QUOTES, 'UTF-8') ?>" <?= $sourceFilter === (string) $sourceValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $sourceLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label" for="email-log-driver">Sürücü</label>
                    <select id="email-log-driver" name="driver" class="ui-admin-form-select">
                        <option value="all" <?= $driverFilter === 'all' ? 'selected' : '' ?>>Tümü</option>
                        <?php foreach ($emailDriverOptions as $driverValue => $driverLabel): ?>
                            <option value="<?= htmlspecialchars((string) $driverValue, ENT_QUOTES, 'UTF-8') ?>" <?= $driverFilter === (string) $driverValue ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $driverLabel, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label" for="email-log-date-from">Başlangıç</label>
                    <input id="email-log-date-from" type="date" name="date_from" class="ui-admin-form-control" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label" for="email-log-date-to">Bitiş</label>
                    <input id="email-log-date-to" type="date" name="date_to" class="ui-admin-form-control" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
            </form>
            <?php if ($hasFilters || $canManageLogs): ?>
                <div class="logs-toolbar-actions">
                    <?php if ($hasFilters): ?>
                        <a href="email-logs.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-lg"></i> Temizle</a>
                    <?php endif; ?>
                    <?php if ($canManageLogs): ?>
                        <?= adminRenderLogClearTrigger([
                            'label' => 'Günlüğü Temizle',
                            'base_class' => 'ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm',
                        ]) ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
    <?= adminRenderLogToolbarClose() ?>

    <?= adminRenderLogListPanelOpen([
        'tag' => 'div',
        'icon' => 'bi-envelope-paper',
        'title' => 'E-posta Gönderim Kayıtları',
        'count_text' => number_format((int) ($emailLogs['total'] ?? 0), 0, ',', '.') . ' kayıt',
    ]) ?>
            <?php if (empty($emailLogs['items'])): ?>
                <?= adminRenderLogEmptyState([
                    'icon' => 'bi-envelope-paper',
                    'tone' => 'info',
                    'title' => 'Kayıt bulunamadı',
                    'description' => $hasFilters ? 'Seçili filtrelerle eşleşen e-posta kaydı yok.' : 'Henüz e-posta gönderim kaydı oluşmamış.',
                ]) ?>
            <?php else: ?>
                <?= adminRenderLogTableOpen() ?>
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>Durum</th>
                                <th>Kaynak</th>
                                <th>Alıcı</th>
                                <th>Konu</th>
                                <th class="email-logs-tech-head">Teknik</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($emailLogs['items'] as $emailLog): ?>
                                <?php
                                $status = strtolower(trim((string) ($emailLog['status'] ?? '')));
                                $statusMeta = function_exists('emailLogsStatusMeta') ? emailLogsStatusMeta($status) : ['label' => strtoupper($status !== '' ? $status : 'bilinmiyor'), 'class' => 'secondary', 'icon' => 'bi-envelope'];
                                $createdAtRaw = (string) ($emailLog['created_at'] ?? '');
                                $createdAtTs = strtotime($createdAtRaw);
                                $createdLabel = $createdAtTs !== false ? date('d.m.Y H:i:s', $createdAtTs) : $createdAtRaw;
                                $source = trim((string) ($emailLog['source'] ?? 'system'));
                                $sourceLabel = function_exists('emailLogsSourceLabel') ? emailLogsSourceLabel($source) : $source;
                                $sourceKey = trim((string) ($emailLog['source_key'] ?? ''));
                                $recipientEmail = trim((string) ($emailLog['recipient_email'] ?? ''));
                                $recipientName = trim((string) ($emailLog['recipient_name'] ?? ''));
                                $subject = trim((string) ($emailLog['subject'] ?? ''));
                                $driver = trim((string) ($emailLog['driver'] ?? ''));
                                $transport = trim((string) ($emailLog['transport'] ?? ''));
                                $showTransport = $transport !== '' && ($driver === '' || strcasecmp($driver, $transport) !== 0);
                                $providerMessageId = trim((string) ($emailLog['provider_message_id'] ?? ''));
                                $providerResponse = trim((string) ($emailLog['provider_response'] ?? ''));
                                $smtpResponse = trim((string) ($emailLog['smtp_response'] ?? ''));
                                $errorMessage = trim((string) ($emailLog['error_message'] ?? ''));
                                $exceptionClass = trim((string) ($emailLog['exception_class'] ?? ''));
                                $exceptionFile = trim((string) ($emailLog['exception_file'] ?? ''));
                                $exceptionLine = isset($emailLog['exception_line']) && $emailLog['exception_line'] !== '' ? (int) $emailLog['exception_line'] : null;
                                $smtpCode = isset($emailLog['smtp_code']) && $emailLog['smtp_code'] !== '' ? (int) $emailLog['smtp_code'] : null;
                                $contextJson = (string) ($emailLog['context_json'] ?? '');
                                $contextData = json_decode($contextJson, true);
                                if (!is_array($contextData)) {
                                    $contextData = [];
                                }
                                $contextSummary = function_exists('emailLogsFormatContext') ? emailLogsFormatContext($contextJson) : 'Ek detay yok';
                                $contextPretty = $emailLogPrettyJson($contextJson);
                                $notificationIdLabel = $emailLogText($emailLog['notification_id'] ?? null);
                                if (($emailLog['notification_id'] ?? null) !== null && (string) ($emailLog['notification_exists'] ?? '') === '0') {
                                    $notificationIdLabel .= ' (silinmiş)';
                                }
                                $queueIdLabel = $emailLogText($emailLog['queue_id'] ?? null);
                                if (($emailLog['queue_id'] ?? null) !== null && (string) ($emailLog['queue_exists'] ?? '') === '0') {
                                    $queueIdLabel .= ' (silinmiş)';
                                }
                                $detailRows = [
                                    'Kaynak anahtarı' => $emailLogText($contextData['source_key'] ?? $sourceKey),
                                    'Sürücü' => $emailLogText($contextData['driver'] ?? ($driver !== '' ? $driver : null)),
                                    'Aktarım' => $emailLogText($contextData['transport'] ?? ($transport !== '' ? $transport : null)),
                                    'Gönderen adı' => $emailLogText($contextData['from_name'] ?? null),
                                    'Gönderen e-postası' => $emailLogText($contextData['from_address'] ?? null),
                                    'Yanıt adresi' => $emailLogText($contextData['reply_to'] ?? null),
                                    'SMTP sunucusu' => $emailLogText($contextData['smtp_host'] ?? null),
                                    'SMTP portu' => $emailLogText($contextData['smtp_port'] ?? null),
                                    'SMTP şifreleme' => $emailLogText($contextData['smtp_encryption'] ?? null),
                                    'Sağlayıcı ID' => $emailLogText($providerMessageId !== '' ? $providerMessageId : ($contextData['provider_message_id'] ?? null)),
                                    'SMTP kodu' => $emailLogText($smtpCode !== null ? (string) $smtpCode : ($contextData['smtp_code'] ?? null)),
                                    'Bildirim ID' => $notificationIdLabel,
                                    'Kuyruk ID' => $queueIdLabel,
                                    'Kullanıcı ID' => $emailLogText($emailLog['user_id'] ?? null),
                                    'Deneme' => ($emailLog['attempt_no'] ?? null) !== null
                                        ? ((string) ($emailLog['attempt_no'] ?? '') . (($emailLog['max_attempts'] ?? null) !== null && $emailLog['max_attempts'] !== '' ? ' / ' . (string) $emailLog['max_attempts'] : ''))
                                        : ($contextData['attempt_no'] ?? null),
                                    'İstisna' => $emailLogText($contextData['exception_class'] ?? ($exceptionClass !== '' ? $exceptionClass : null)),
                                    'İstisna konumu' => $emailLogText(($exceptionFile !== '' || $exceptionLine !== null) ? trim($exceptionFile . ($exceptionLine !== null ? ':' . $exceptionLine : '')) : null),
                                    'Konu uzunluğu' => $emailLogText($contextData['subject_length'] ?? null),
                                    'Gövde uzunluğu' => $emailLogText($contextData['body_length'] ?? null),
                                ];
                                $detailDriver = trim((string) ($contextData['driver'] ?? $driver));
                                $detailTransport = trim((string) ($contextData['transport'] ?? $transport));
                                if ($detailDriver !== '' && $detailTransport !== '' && strcasecmp($detailDriver, $detailTransport) === 0) {
                                    unset($detailRows['Aktarım']);
                                }
                                ?>
                                <tr>
                                    <td class="ui-admin-table-cell-date"><?= htmlspecialchars($createdLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars((string) ($statusMeta['class'] ?? 'secondary'), ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi <?= htmlspecialchars((string) ($statusMeta['icon'] ?? 'bi-envelope'), ENT_QUOTES, 'UTF-8') ?>"></i>
                                            <?= htmlspecialchars((string) ($statusMeta['label'] ?? strtoupper($status !== '' ? $status : 'bilinmiyor')), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="ui-admin-table-cell-secondary">
                                        <div class="email-logs-source">
                                            <strong><?= htmlspecialchars($sourceLabel !== '' ? $sourceLabel : 'Sistem', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small><?= htmlspecialchars($sourceKey !== '' ? $sourceKey : '—', ENT_QUOTES, 'UTF-8') ?></small>
                                        </div>
                                    </td>
                                    <td class="ui-admin-table-cell-secondary">
                                        <div class="email-logs-recipient">
                                            <strong><?= htmlspecialchars($recipientEmail !== '' ? $recipientEmail : '—', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <small><?= htmlspecialchars($recipientName !== '' ? $recipientName : '—', ENT_QUOTES, 'UTF-8') ?></small>
                                        </div>
                                    </td>
                                    <td class="ui-admin-table-cell-desc ui-admin-log-desc-cell">
                                        <div class="ui-admin-log-desc-scroll email-logs-subject">
                                            <strong><?= htmlspecialchars($subject !== '' ? $subject : '(Başlıksız)', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <?php if ($providerMessageId !== ''): ?>
                                                <small>Sağlayıcı: <?= htmlspecialchars($providerMessageId, ENT_QUOTES, 'UTF-8') ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="ui-admin-table-cell-desc email-logs-tech-cell">
                                        <div class="email-logs-tech-shell">
                                            <div class="email-logs-tech-badges">
                                                <?php if ($driver !== ''): ?>
                                                    <span class="ui-admin-badge ui-admin-badge-secondary"><?= htmlspecialchars(mb_strtoupper($driver, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                                <?php if ($showTransport): ?>
                                                    <span class="ui-admin-badge ui-admin-badge-info"><?= htmlspecialchars(mb_strtoupper($transport, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                                <?php if ($smtpCode !== null): ?>
                                                    <span class="ui-admin-badge ui-admin-badge-warning">SMTP <?= (int) $smtpCode ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <details class="ui-admin-log-technical email-log-details">
                                                <summary><i class="bi bi-chevron-down"></i> Teknik ayrıntılar</summary>
                                                <div class="email-log-details-panel">
                                                    <div class="email-log-section-label"><i class="bi bi-code-slash"></i> Kayıt özeti</div>
                                                    <div class="ui-admin-log-summary email-log-summary"><?= htmlspecialchars($contextSummary, ENT_QUOTES, 'UTF-8') ?></div>

                                                    <div class="email-log-section-label"><i class="bi bi-list-check"></i> Alanlar</div>
                                                    <div class="email-log-detail-grid">
                                                        <?php foreach ($detailRows as $detailLabel => $detailValue): ?>
                                                            <span>
                                                                <b><?= htmlspecialchars((string) $detailLabel, ENT_QUOTES, 'UTF-8') ?></b>
                                                                <strong><?= htmlspecialchars($emailLogText($detailValue), ENT_QUOTES, 'UTF-8') ?></strong>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>

                                                    <?php if ($errorMessage !== ''): ?>
                                                        <div class="email-log-section-label"><i class="bi bi-exclamation-octagon"></i> Hata Mesajı</div>
                                                        <div class="email-log-response is-error"><?= nl2br(htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8')) ?></div>
                                                    <?php endif; ?>

                                                    <?php if ($smtpResponse !== ''): ?>
                                                        <div class="email-log-section-label"><i class="bi bi-hdd-rack"></i> SMTP Yanıtı</div>
                                                        <div class="email-log-response is-info"><?= nl2br(htmlspecialchars($smtpResponse, ENT_QUOTES, 'UTF-8')) ?></div>
                                                    <?php endif; ?>

                                                    <?php if ($providerResponse !== ''): ?>
                                                        <div class="email-log-section-label"><i class="bi bi-cloud-check"></i> Sağlayıcı Yanıtı</div>
                                                        <div class="email-log-response is-muted"><?= nl2br(htmlspecialchars($providerResponse, ENT_QUOTES, 'UTF-8')) ?></div>
                                                    <?php endif; ?>

                                                    <?php if ($contextPretty !== ''): ?>
                                                        <div class="email-log-section-label"><i class="bi bi-braces"></i> Maskeli JSON</div>
                                                        <pre class="email-log-json"><?= htmlspecialchars($contextPretty, ENT_QUOTES, 'UTF-8') ?></pre>
                                                    <?php endif; ?>
                                                </div>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                <?= adminRenderLogTableClose() ?>

                <?php
                if ($emailLogsTotalPages > 1):
                    $pageParams = array_filter([
                        'q' => $search,
                        'status' => $statusFilter !== 'all' ? $statusFilter : '',
                        'source' => $sourceFilter !== 'all' ? $sourceFilter : '',
                        'driver' => $driverFilter !== 'all' ? $driverFilter : '',
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                    ], static fn ($value): bool => $value !== '' && $value !== null);
                    $pageBase = 'email-logs.php?' . ($pageParams ? http_build_query($pageParams) . '&' : '') . 'page=';
                    echo adminRenderLogPagination($emailLogsTotalPages, $page, static fn (int $targetPage): string => $pageBase . $targetPage, [
                        'aria_label' => 'E-posta logları sayfalama',
                    ]);
                endif;
                ?>
            <?php endif; ?>
    <?= adminRenderLogListPanelClose('div') ?>

    <?php if ($canManageLogs): ?>
    <?php
    $logClearModal = [
        'aria_label' => 'E-posta günlüğünü temizle',
        'title' => 'Günlüğü Temizle',
        'form_action' => 'email-logs.php',
        'hidden_fields' => [
            ['name' => 'action', 'value' => 'clear_all_email_logs'],
        ],
        'scope_name' => 'scope',
        'options' => [
            [
                'value' => 'all',
                'label' => 'Tüm e-posta günlüğünü sil (Tehlikeli)',
                'confirm_title' => 'Günlüğü Temizle',
            ],
        ],
        'warning' => 'Tüm e-posta logları kalıcı olarak silinir. SMTP yanıtları ve teknik hata ayrıntıları dahil bu işlem geri alınamaz.',
    ];
    adminRenderLogClearModal($logClearModal);
    unset($logClearModal);
    ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
