<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';

adminRequirePermission('system.manage', 'Veritabanı senkronizasyonunu çalıştırmak için gerekli izin hesabınıza tanımlanmamış.');

function dbSyncStatusMeta(string $status): array
{
    $status = strtolower(trim($status));

    return match ($status) {
        'success', 'applied' => ['class' => 'success', 'icon' => 'bi-check-circle', 'label' => 'Uygulandı'],
        'preview' => ['class' => 'warning', 'icon' => 'bi-eye', 'label' => 'Önizleme'],
        'pending' => ['class' => 'warning', 'icon' => 'bi-hourglass-split', 'label' => 'Bekliyor'],
        'up_to_date' => ['class' => 'success', 'icon' => 'bi-shield-check', 'label' => 'Güncel'],
        'disabled' => ['class' => 'muted', 'icon' => 'bi-dash-circle', 'label' => 'Pasif'],
        'failed', 'error' => ['class' => 'danger', 'icon' => 'bi-x-circle', 'label' => 'Hata'],
        'running' => ['class' => 'warning', 'icon' => 'bi-arrow-repeat', 'label' => 'Çalışıyor'],
        default => ['class' => 'muted', 'icon' => 'bi-info-circle', 'label' => ucfirst($status ?: 'bilinmiyor')],
    };
}

function dbSyncCountFiles(array $items, string $status): int
{
    $count = 0;
    foreach ($items as $item) {
        if ((string) ($item['status'] ?? '') === $status) {
            $count++;
        }
    }

    return $count;
}

function dbSyncNumber(int $value): string
{
    return number_format($value, 0, ',', '.');
}

function dbSyncFormatDateTime(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '—';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $value;
    }

    return date('d.m.Y H:i:s', $timestamp);
}

function dbSyncScopeStatus(int $total, int $pending, int $failed): array
{
    if ($failed > 0) {
        return dbSyncStatusMeta('failed');
    }

    if ($total <= 0) {
        return dbSyncStatusMeta('disabled');
    }

    if ($pending > 0) {
        return dbSyncStatusMeta('pending');
    }

    return dbSyncStatusMeta('up_to_date');
}

$previewMode = (string) ($_GET['preview'] ?? '') === '1';
$pageTitle = $previewMode ? 'Veritabanı Senkronizasyonu Önizleme' : 'Veritabanı Senkronizasyonu';

$syncService = new \App\Core\Database\DatabaseSyncService();
$syncReport = $syncService->run(!$previewMode);

if (($syncReport['status'] ?? '') === 'error') {
    http_response_code(500);
}

$reportStatus = dbSyncStatusMeta((string) ($syncReport['status'] ?? 'error'));
$summary = is_array($syncReport['summary'] ?? null) ? $syncReport['summary'] : [];
$modules = is_array($syncReport['modules'] ?? null) ? $syncReport['modules'] : [];
$rootMigrations = is_array($syncReport['root_migrations'] ?? null) ? $syncReport['root_migrations'] : [];
$warnings = array_values(array_unique(array_filter(array_map('strval', is_array($syncReport['warnings'] ?? null) ? $syncReport['warnings'] : []))));
$errors = is_array($syncReport['errors'] ?? null) ? array_values($syncReport['errors']) : [];

$modulesTotal = (int) ($summary['modules_total'] ?? count($modules));
$modulesPending = (int) ($summary['modules_pending'] ?? 0);
$modulesApplied = (int) ($summary['modules_applied'] ?? 0);
$modulesSkipped = (int) ($summary['modules_skipped'] ?? 0);
$rootTotal = (int) ($summary['root_total'] ?? count($rootMigrations));
$rootPending = (int) ($summary['root_pending'] ?? 0);
$rootApplied = (int) ($summary['root_applied'] ?? 0);
$rootSkipped = (int) ($summary['root_skipped'] ?? 0);
$failedCount = max((int) ($summary['failed'] ?? 0), count($errors));
$moduleFiles = 0;
foreach ($modules as $module) {
    $moduleFiles += is_array($module['migrations'] ?? null) ? count($module['migrations']) : 0;
}

$pendingTotal = $modulesPending + $rootPending;
$appliedTotal = $modulesApplied + $rootApplied;
$skippedTotal = $modulesSkipped + $rootSkipped;
$lockAcquired = !empty($syncReport['lock']['acquired']);
$durationMs = (int) ($syncReport['duration_ms'] ?? 0);
$startedAt = dbSyncFormatDateTime(is_string($syncReport['started_at'] ?? null) ? $syncReport['started_at'] : null);
$finishedAt = dbSyncFormatDateTime(is_string($syncReport['finished_at'] ?? null) ? $syncReport['finished_at'] : null);
$projectRoot = trim((string) ($syncReport['project_root'] ?? ''));
$modeHref = $previewMode ? 'index.php' : 'index.php?preview=1';
$modeLabel = $previewMode ? 'Canlı Çalıştır' : 'Önizleme';
$modeIcon = $previewMode ? 'bi-lightning-charge' : 'bi-eye';
$rerunHref = $previewMode ? 'index.php?preview=1' : 'index.php';
$rerunLabel = $previewMode ? 'Önizlemeyi Yenile' : 'Yeniden Çalıştır';
$rerunIcon = 'bi-arrow-clockwise';
$modeMeta = $previewMode
    ? ['class' => 'warning', 'icon' => 'bi-eye', 'label' => 'Önizleme']
    : ['class' => 'success', 'icon' => 'bi-lightning-charge', 'label' => 'Canlı'];
$moduleScopeMeta = dbSyncScopeStatus($modulesTotal, $modulesPending, 0);
$rootScopeMeta = dbSyncScopeStatus($rootTotal, $rootPending, 0);

foreach ($modules as $module) {
    if ((string) ($module['status'] ?? '') === 'failed') {
        $moduleScopeMeta = dbSyncStatusMeta('failed');
        break;
    }
}

foreach ($rootMigrations as $migration) {
    if ((string) ($migration['status'] ?? '') === 'failed') {
        $rootScopeMeta = dbSyncStatusMeta('failed');
        break;
    }
}

$noticeTitle = 'Güvenli çalışma modu';
$noticeCopy = $previewMode
    ? 'Bu açılış önizleme modunda çalışır ve veritabanına yazmaz. Canlı uygulama için sağdaki butonu kullanın.'
    : 'Sayfa açıldığında bekleyen migration dosyaları otomatik olarak işlenir. Önce önizleme ile kontrol etmek isterseniz sağdaki butonla geçebilirsiniz.';

if (($syncReport['status'] ?? '') === 'error') {
    $noticeTitle = 'Senkronizasyon durdu';
    $noticeCopy = 'Üstteki hata kaydı çözüldükten sonra aynı ekranı yeniden açın. Hata bölümünde teknik ayrıntı da listelenir.';
}

require __DIR__ . '/../header.php';
?>
<style>
.db-sync-shell { display: grid; gap: 1.25rem; }
.db-sync-hero-copy { display: grid; gap: .6rem; }
.db-sync-hero-copy h1 { margin: 0; }
.db-sync-hero-copy p { margin: 0; max-width: 72ch; color: var(--ui-admin-text-secondary); }
.db-sync-hero-actions { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: flex-end; align-items: center; }
.db-sync-notice { display: flex; gap: .85rem; align-items: flex-start; padding: 1rem 1.1rem; border: 1px solid var(--ui-admin-border); border-radius: 14px; background: color-mix(in srgb, var(--ui-admin-surface-alt) 72%, var(--ui-admin-surface) 28%); }
.db-sync-notice i { margin-top: .05rem; font-size: 1.05rem; color: var(--ui-admin-primary); }
.db-sync-notice strong { display: block; margin-bottom: .2rem; }
.db-sync-notice p { margin: 0; }
.db-sync-meta-grid { display: grid; gap: .85rem 1rem; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
.db-sync-meta-item { display: grid; gap: .2rem; min-width: 0; }
.db-sync-meta-label { font-size: .75rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--ui-admin-text-muted); }
.db-sync-meta-value { min-width: 0; word-break: break-word; }
.db-sync-meta-value code { word-break: break-word; }
.db-sync-summary-grid { margin-bottom: 0; }
.db-sync-section { display: grid; gap: 1rem; }
.db-sync-section + .db-sync-section { margin-top: .25rem; }
.db-sync-section-head { display: grid; gap: .35rem; }
.db-sync-section-head h2 { margin: 0; }
.db-sync-section-head p { margin: 0; color: var(--ui-admin-text-secondary); }
.db-sync-scope-list { display: grid; gap: .85rem; }
.db-sync-scope-details { overflow: hidden; border: 1px solid var(--ui-admin-border); border-radius: 14px; background: var(--ui-admin-surface); box-shadow: var(--shadow-sm); }
.db-sync-scope-details[open] { box-shadow: var(--shadow-md); }
.db-sync-scope-details > summary { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: 1rem 1.1rem; cursor: pointer; list-style: none; }
.db-sync-scope-details > summary::-webkit-details-marker { display: none; }
.db-sync-scope-summary { display: grid; gap: .35rem; min-width: 0; }
.db-sync-scope-summary strong { font-size: 1rem; }
.db-sync-scope-path { color: var(--ui-admin-text-secondary); font-size: .85rem; word-break: break-word; }
.db-sync-scope-badges { display: flex; flex-wrap: wrap; gap: .35rem; }
.db-sync-scope-body { display: grid; gap: 1rem; padding: 1rem 1.1rem 1.1rem; border-top: 1px solid var(--ui-admin-border); }
.db-sync-scope-note { margin: 0; color: var(--ui-admin-text-secondary); }
.db-sync-callout-grid { display: grid; gap: .85rem; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
.db-sync-callout { display: grid; gap: .85rem; padding: 1rem 1.1rem; border: 1px solid var(--ui-admin-border); border-radius: 14px; background: var(--ui-admin-surface); }
.db-sync-callout--warning { border-left: 4px solid #f59e0b; }
.db-sync-callout--danger { border-left: 4px solid #dc2626; }
.db-sync-callout-head { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
.db-sync-callout-head strong { font-size: .98rem; }
.db-sync-callout-list { margin: 0; padding-left: 1.2rem; display: grid; gap: .45rem; }
.db-sync-callout-list li { word-break: break-word; }
.db-sync-error-detail { margin-top: .4rem; }
.db-sync-error-detail summary { cursor: pointer; color: var(--ui-admin-text-secondary); }
.db-sync-error-detail code { display: block; margin-top: .5rem; padding: .75rem .85rem; border: 1px solid var(--ui-admin-border); border-radius: 10px; background: var(--ui-admin-surface-alt); white-space: pre-wrap; word-break: break-word; }
.db-sync-empty { padding: 1.1rem; }
@media (max-width: 767.98px) {
    .db-sync-hero-actions { justify-content: flex-start; }
    .db-sync-scope-details > summary { flex-direction: column; }
}
</style>

<div class="db-sync-shell">
    <section class="ui-panel" aria-labelledby="db-sync-title">
        <div class="ui-panel__head ui-admin-card-header-actions">
            <div class="db-sync-hero-copy">
                <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($reportStatus['class'], ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi <?= htmlspecialchars($reportStatus['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    <?= htmlspecialchars($reportStatus['label'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <h1 id="db-sync-title">Veritabanı Senkronizasyonu<?= $previewMode ? ' Önizleme' : '' ?></h1>
                <p>Bu ekran proje kökündeki migration dosyalarını tarar, bekleyen değişiklikleri sıralar ve açıldığında otomatik olarak uygular. Canlı uygulamadan önce isterseniz önizleme modunda kontrol edebilirsiniz.</p>
            </div>
            <div class="db-sync-hero-actions">
                <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="<?= htmlspecialchars($rerunHref, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi <?= htmlspecialchars($rerunIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
                    <?= htmlspecialchars($rerunLabel, ENT_QUOTES, 'UTF-8') ?>
                </a>
                <a class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" href="<?= htmlspecialchars($modeHref, ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi <?= htmlspecialchars($modeIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
                    <?= htmlspecialchars($modeLabel, ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </div>
        <div class="ui-panel__body db-sync-section">
            <div class="db-sync-notice">
                <i class="bi <?= htmlspecialchars($reportStatus['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                <div>
                    <strong><?= htmlspecialchars($noticeTitle, ENT_QUOTES, 'UTF-8') ?></strong>
                    <p><?= htmlspecialchars($noticeCopy, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>

            <div class="ui-admin-stat-grid ui-admin-stat-grid-compact db-sync-summary-grid">
                <div class="ui-admin-stat-tile">
                    <span class="ui-admin-stat-tile-label">Çalışma modu</span>
                    <span class="ui-admin-stat-tile-value"><?= htmlspecialchars($modeMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="ui-admin-muted-sm">Açılışta <?= $previewMode ? 'sadece kontrol' : 'otomatik uygulama' ?> yapılır.</span>
                </div>
                <div class="ui-admin-stat-tile">
                    <span class="ui-admin-stat-tile-label">Genel durum</span>
                    <span class="ui-admin-stat-tile-value"><?= htmlspecialchars((string) ($reportStatus['label'] ?? 'Bilinmiyor'), ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="ui-admin-muted-sm">İşlem akışının son sonucu.</span>
                </div>
                <div class="ui-admin-stat-tile">
                    <span class="ui-admin-stat-tile-label">Bekleyen</span>
                    <span class="ui-admin-stat-tile-value"><?= dbSyncNumber($pendingTotal) ?></span>
                    <span class="ui-admin-muted-sm">Modül ve root toplamı.</span>
                </div>
                <div class="ui-admin-stat-tile">
                    <span class="ui-admin-stat-tile-label">Uygulanan</span>
                    <span class="ui-admin-stat-tile-value"><?= dbSyncNumber($appliedTotal) ?></span>
                    <span class="ui-admin-muted-sm">Başarıyla işlenen kayıtlar.</span>
                </div>
                <div class="ui-admin-stat-tile">
                    <span class="ui-admin-stat-tile-label">Atlanan</span>
                    <span class="ui-admin-stat-tile-value"><?= dbSyncNumber($skippedTotal) ?></span>
                    <span class="ui-admin-muted-sm">Pasif, güncel veya tekrar eden kayıtlar.</span>
                </div>
                <div class="ui-admin-stat-tile">
                    <span class="ui-admin-stat-tile-label">Hata</span>
                    <span class="ui-admin-stat-tile-value"><?= dbSyncNumber($failedCount) ?></span>
                    <span class="ui-admin-muted-sm">Teknik müdahale gerektiren durumlar.</span>
                </div>
            </div>

            <div class="db-sync-meta-grid">
                <div class="db-sync-meta-item">
                    <span class="db-sync-meta-label">Başlangıç</span>
                    <span class="db-sync-meta-value"><?= htmlspecialchars($startedAt, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="db-sync-meta-item">
                    <span class="db-sync-meta-label">Bitiş</span>
                    <span class="db-sync-meta-value"><?= htmlspecialchars($finishedAt, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="db-sync-meta-item">
                    <span class="db-sync-meta-label">Süre</span>
                    <span class="db-sync-meta-value"><?= htmlspecialchars(dbSyncNumber($durationMs) . ' ms', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="db-sync-meta-item">
                    <span class="db-sync-meta-label">Kilitleme</span>
                    <span class="db-sync-meta-value">
                        <span class="ui-admin-badge ui-admin-badge-<?= $lockAcquired ? 'success' : 'danger' ?>">
                            <i class="bi <?= $lockAcquired ? 'bi-lock-fill' : 'bi-unlock' ?>"></i>
                            <?= $lockAcquired ? 'Alındı' : 'Alınamadı' ?>
                        </span>
                    </span>
                </div>
                <div class="db-sync-meta-item">
                    <span class="db-sync-meta-label">Modül grupları</span>
                    <span class="db-sync-meta-value"><?= htmlspecialchars(dbSyncNumber($modulesTotal), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="db-sync-meta-item">
                    <span class="db-sync-meta-label">Root dosyaları</span>
                    <span class="db-sync-meta-value"><?= htmlspecialchars(dbSyncNumber($rootTotal), ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <div class="db-sync-meta-item">
                    <span class="db-sync-meta-label">Proje kökü</span>
                    <span class="db-sync-meta-value"><code><?= htmlspecialchars($projectRoot !== '' ? $projectRoot : '-', ENT_QUOTES, 'UTF-8') ?></code></span>
                </div>
            </div>
        </div>
    </section>

    <section class="ui-panel db-sync-section" aria-labelledby="db-sync-overview-title">
        <div class="ui-panel__head ui-admin-card-header-actions">
            <div class="db-sync-section-head">
                <h2 id="db-sync-overview-title" class="ui-admin-card-title">Kapsam Özeti</h2>
                <p>Bu tablo modül grupları ve root migration dosyalarının son durumunu toplu halde gösterir.</p>
            </div>
            <span class="ui-admin-badge ui-admin-badge-muted">
                <i class="bi bi-list-check"></i>
                Toplam <?= htmlspecialchars(dbSyncNumber($modulesTotal + $rootTotal), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <div class="ui-panel__body ui-admin-table-wrap-x">
            <table class="ui-admin-table">
                <thead>
                    <tr>
                        <th>Kapsam</th>
                        <th>Kayıt</th>
                        <th>Bekleyen</th>
                        <th>Uygulanan</th>
                        <th>Atlanan</th>
                        <th>Durum</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Modüller</strong>
                            <div class="ui-admin-muted-sm"><?= htmlspecialchars(dbSyncNumber($modulesTotal), ENT_QUOTES, 'UTF-8') ?> grup · <?= htmlspecialchars(dbSyncNumber($moduleFiles), ENT_QUOTES, 'UTF-8') ?> dosya</div>
                        </td>
                        <td><?= htmlspecialchars(dbSyncNumber($modulesTotal), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(dbSyncNumber($modulesPending), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(dbSyncNumber($modulesApplied), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(dbSyncNumber($modulesSkipped), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($moduleScopeMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi <?= htmlspecialchars($moduleScopeMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                <?= htmlspecialchars($moduleScopeMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <strong>Root migrationlar</strong>
                            <div class="ui-admin-muted-sm"><?= htmlspecialchars(dbSyncNumber($rootTotal), ENT_QUOTES, 'UTF-8') ?> dosya</div>
                        </td>
                        <td><?= htmlspecialchars(dbSyncNumber($rootTotal), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(dbSyncNumber($rootPending), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(dbSyncNumber($rootApplied), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(dbSyncNumber($rootSkipped), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($rootScopeMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi <?= htmlspecialchars($rootScopeMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                <?= htmlspecialchars($rootScopeMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="ui-admin-muted-sm db-sync-scope-note" style="margin-top:.75rem;">Atlanan sayısı pasif, güncel veya tekrar eden kayıtları da içerir. Bu nedenle toplamla bire bir eşleşmeyebilir; bu normaldir.</p>
        </div>
    </section>

    <section class="ui-panel db-sync-section" aria-labelledby="db-sync-warning-title">
        <div class="ui-panel__head ui-admin-card-header-actions">
            <div class="db-sync-section-head">
                <h2 id="db-sync-warning-title" class="ui-admin-card-title">Uyarılar ve Hatalar</h2>
                <p>Çalışma sırasında çıkan uyarı ve hata kayıtları burada tutulur.</p>
            </div>
            <span class="ui-admin-badge ui-admin-badge-<?= !empty($errors) ? 'danger' : (!empty($warnings) ? 'warning' : 'success') ?>">
                <i class="bi <?= !empty($errors) ? 'bi-x-circle' : (!empty($warnings) ? 'bi-exclamation-triangle' : 'bi-check-circle') ?>"></i>
                <?= !empty($errors) ? dbSyncNumber(count($errors)) . ' hata' : (!empty($warnings) ? dbSyncNumber(count($warnings)) . ' uyarı' : 'Temiz') ?>
            </span>
        </div>
        <div class="ui-panel__body">
            <div class="db-sync-callout-grid">
                <?php if (!empty($warnings)): ?>
                    <article class="db-sync-callout db-sync-callout--warning">
                        <div class="db-sync-callout-head">
                            <span class="ui-admin-badge ui-admin-badge-warning"><i class="bi bi-exclamation-triangle"></i> Uyarılar</span>
                            <strong>İşlemi durdurmayan kayıtlar</strong>
                        </div>
                        <ul class="db-sync-callout-list">
                            <?php foreach ($warnings as $warning): ?>
                                <li><?= htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <article class="db-sync-callout db-sync-callout--danger">
                        <div class="db-sync-callout-head">
                            <span class="ui-admin-badge ui-admin-badge-danger"><i class="bi bi-x-circle"></i> Hatalar</span>
                            <strong>Senkronizasyonu durduran kayıtlar</strong>
                        </div>
                        <ul class="db-sync-callout-list">
                            <?php foreach ($errors as $error): ?>
                                <?php
                                $errorMessage = is_array($error)
                                    ? (string) ($error['message'] ?? 'Bilinmeyen hata')
                                    : (string) $error;
                                $errorDetail = is_array($error)
                                    ? trim((string) ($error['detail'] ?? ''))
                                    : '';
                                ?>
                                <li>
                                    <strong><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php if ($errorDetail !== '' && $errorDetail !== $errorMessage): ?>
                                        <details class="db-sync-error-detail">
                                            <summary>Teknik detay</summary>
                                            <code><?= htmlspecialchars($errorDetail, ENT_QUOTES, 'UTF-8') ?></code>
                                        </details>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endif; ?>

                <?php if (empty($warnings) && empty($errors)): ?>
                    <div class="ui-admin-empty ui-empty db-sync-empty">
                        <div class="ui-admin-empty-icon tone-success ui-empty"><i class="bi bi-shield-check"></i></div>
                        <h3 class="ui-admin-empty-title ui-empty">Ek uyarı veya hata yok</h3>
                        <p class="ui-admin-empty-desc ui-empty">Bu çalıştırmada sorun görülmedi. Kayıtlar düzgün işlendi.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="ui-panel db-sync-section" aria-labelledby="db-sync-modules-title">
        <div class="ui-panel__head ui-admin-card-header-actions">
            <div class="db-sync-section-head">
                <h2 id="db-sync-modules-title" class="ui-admin-card-title">Modül Migrationları</h2>
                <p>Her modül grubunun durumunu açıp tek tek dosya seviyesinde inceleyebilirsiniz.</p>
            </div>
            <span class="ui-admin-badge ui-admin-badge-muted">
                <i class="bi bi-folder2-open"></i>
                <?= htmlspecialchars(dbSyncNumber($modulesTotal), ENT_QUOTES, 'UTF-8') ?> grup
            </span>
        </div>
        <div class="ui-panel__body">
            <?php if (empty($modules)): ?>
                <div class="ui-admin-empty ui-empty db-sync-empty">
                    <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-folder2-open"></i></div>
                    <h3 class="ui-admin-empty-title ui-empty">Modül bulunamadı</h3>
                    <p class="ui-admin-empty-desc ui-empty">İlgili modüller veya migration klasörleri şu anda tespit edilmedi.</p>
                </div>
            <?php else: ?>
                <div class="db-sync-scope-list">
                    <?php foreach ($modules as $module): ?>
                        <?php
                        $moduleStatus = (string) ($module['status'] ?? 'up_to_date');
                        $moduleMeta = dbSyncStatusMeta($moduleStatus);
                        $migrationCount = is_array($module['migrations'] ?? null) ? count($module['migrations']) : 0;
                        $pendingModuleMigrations = dbSyncCountFiles((array) ($module['migrations'] ?? []), 'pending');
                        $hasAttention = in_array($moduleStatus, ['pending', 'failed'], true) || $pendingModuleMigrations > 0;
                        $requiresModules = array_values(array_filter(array_map('trim', array_map('strval', is_array($module['requires_modules'] ?? null) ? $module['requires_modules'] : []))));
                        ?>
                        <details class="db-sync-scope-details"<?= $hasAttention ? ' open' : '' ?>>
                            <summary>
                                <div class="db-sync-scope-summary">
                                    <strong><?= htmlspecialchars((string) ($module['name'] ?? $module['id'] ?? 'Bilinmiyor'), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span class="db-sync-scope-path"><?= htmlspecialchars((string) ($module['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) ($module['migration_table'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="db-sync-scope-badges">
                                        <span class="ui-admin-badge ui-admin-badge-muted"><?= htmlspecialchars(dbSyncNumber($migrationCount), ENT_QUOTES, 'UTF-8') ?> dosya</span>
                                        <?php if ($pendingModuleMigrations > 0): ?>
                                            <span class="ui-admin-badge ui-admin-badge-warning"><?= htmlspecialchars(dbSyncNumber($pendingModuleMigrations), ENT_QUOTES, 'UTF-8') ?> bekleyen</span>
                                        <?php endif; ?>
                                        <?php if (!empty($requiresModules)): ?>
                                            <span class="ui-admin-badge ui-admin-badge-muted">Bağımlılık: <?= htmlspecialchars(implode(', ', $requiresModules), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($moduleMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi <?= htmlspecialchars($moduleMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                    <?= htmlspecialchars($moduleMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </summary>
                            <div class="db-sync-scope-body">
                                <?php if (!empty($module['message'])): ?>
                                    <p class="db-sync-scope-note"><?= htmlspecialchars((string) $module['message'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>

                                <?php if (!empty($module['legacy_seed'])): ?>
                                    <p class="db-sync-scope-note">Legacy seed durumu: <strong><?= htmlspecialchars((string) $module['legacy_seed'], ENT_QUOTES, 'UTF-8') ?></strong></p>
                                <?php endif; ?>

                                <?php if (!empty($module['migrations']) && is_array($module['migrations'])): ?>
                                    <div class="ui-admin-table-wrap-x">
                                        <table class="ui-admin-table">
                                            <thead>
                                                <tr>
                                                    <th>Dosya</th>
                                                    <th>Tür</th>
                                                    <th>Durum</th>
                                                    <th>Mesaj</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($module['migrations'] as $migration): ?>
                                                    <?php $migrationMeta = dbSyncStatusMeta((string) ($migration['status'] ?? 'pending')); ?>
                                                    <tr>
                                                        <td>
                                                            <code><?= htmlspecialchars((string) ($migration['path'] ?? $migration['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                                                        </td>
                                                        <td><span class="ui-admin-badge ui-admin-badge-muted"><?= htmlspecialchars(strtoupper((string) ($migration['kind'] ?? 'file')), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                        <td>
                                                            <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($migrationMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <i class="bi <?= htmlspecialchars($migrationMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                                                <?= htmlspecialchars($migrationMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        </td>
                                                        <td class="ui-admin-muted-sm"><?= htmlspecialchars((string) ($migration['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="ui-admin-empty ui-empty db-sync-empty">
                                        <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-file-earmark"></i></div>
                                        <h3 class="ui-admin-empty-title ui-empty">Migration dosyası yok</h3>
                                        <p class="ui-admin-empty-desc ui-empty">Bu modül için izlenen migration dizininde dosya bulunamadı.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="ui-panel db-sync-section" aria-labelledby="db-sync-root-title">
        <div class="ui-panel__head ui-admin-card-header-actions">
            <div class="db-sync-section-head">
                <h2 id="db-sync-root-title" class="ui-admin-card-title">Root Migration Dosyaları</h2>
                <p>Proje kökündeki bağımsız SQL/PHP migration dosyaları burada listelenir.</p>
            </div>
            <span class="ui-admin-badge ui-admin-badge-muted">
                <i class="bi bi-database"></i>
                <?= htmlspecialchars(dbSyncNumber($rootTotal), ENT_QUOTES, 'UTF-8') ?> dosya
            </span>
        </div>
        <div class="ui-panel__body">
            <?php if (empty($rootMigrations)): ?>
                <div class="ui-admin-empty ui-empty db-sync-empty">
                    <div class="ui-admin-empty-icon tone-info ui-empty"><i class="bi bi-database"></i></div>
                    <h3 class="ui-admin-empty-title ui-empty">Root migration bulunamadı</h3>
                    <p class="ui-admin-empty-desc ui-empty">database/migrations klasöründe izlenen dosya görünmüyor.</p>
                </div>
            <?php else: ?>
                <div class="ui-admin-table-wrap-x">
                    <table class="ui-admin-table">
                        <thead>
                            <tr>
                                <th>Dosya</th>
                                <th>Tür</th>
                                <th>Durum</th>
                                <th>Mesaj</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rootMigrations as $migration): ?>
                                <?php $migrationMeta = dbSyncStatusMeta((string) ($migration['status'] ?? 'pending')); ?>
                                <tr>
                                    <td>
                                        <code><?= htmlspecialchars((string) ($migration['path'] ?? $migration['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code>
                                    </td>
                                    <td><span class="ui-admin-badge ui-admin-badge-muted"><?= htmlspecialchars(strtoupper((string) ($migration['kind'] ?? 'file')), ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td>
                                        <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars($migrationMeta['class'], ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi <?= htmlspecialchars($migrationMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                                            <?= htmlspecialchars($migrationMeta['label'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td class="ui-admin-muted-sm"><?= htmlspecialchars((string) ($migration['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
