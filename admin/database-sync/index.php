<?php

declare(strict_types=1);

require_once __DIR__ . '/../init.php';

adminRequirePermission('system.manage', 'Veritabani senkronizasyonunu calistirmak icin gerekli izin hesabiniza tanimlanmamis.');

/* Yardimci fonksiyonlar */
function dbsBadge(string $status): string
{
    return match (strtolower(trim($status))) {
        'success', 'applied'  => 'success',
        'pending'             => 'warning',
        'up_to_date'          => 'info',
        'failed', 'error'     => 'danger',
        'disabled'            => 'muted',
        default               => 'muted',
    };
}

function dbsIcon(string $status): string
{
    return match (strtolower(trim($status))) {
        'success', 'applied'  => 'bi-check-lg',
        'pending'             => 'bi-hourglass-split',
        'up_to_date'          => 'bi-shield-check',
        'failed', 'error'     => 'bi-x-lg',
        'disabled'            => 'bi-slash-circle',
        default               => 'bi-question-lg',
    };
}

function dbsCount(array $items, string $status): int
{
    $n = 0;
    foreach ($items as $v) { if ((string) ($v['status'] ?? '') === $status) $n++; }
    return $n;
}

function dbsFmt(int $n): string { return number_format($n, 0, ',', '.'); }

function dbsDt(?string $v): string
{
    $v = trim((string) $v);
    if ($v === '') return '-';
    $ts = strtotime($v);
    return $ts === false ? $v : date('d.m.Y H:i:s', $ts);
}

/* POST */
$syncService = new \App\Core\Database\DatabaseSyncService();
$isApply = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_pending_migrations';

if ($isApply) {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('dbs_alert', ['tone' => 'danger', 'title' => 'Güvenlik doğrulaması başarısız.', 'message' => 'Lütfen sayfayı yenileyip tekrar deneyin.']);
        header('Location: index.php');
        exit;
    }

    $report = $syncService->run(true);
    $sum = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $errs = is_array($report['errors'] ?? null) ? array_values($report['errors']) : [];
    $applied = max(0, (int) ($sum['modules_applied'] ?? 0) + (int) ($sum['root_applied'] ?? 0));
    $failed = max((int) ($sum['failed'] ?? 0), count($errs));
    $msg = '';

    if (($report['status'] ?? '') === 'error' || $failed > 0) {
        if ($errs !== []) {
            $first = $errs[0];
            $msg = is_array($first) ? trim((string) ($first['message'] ?? $first['detail'] ?? '')) : trim((string) $first);
        }
        flash('dbs_alert', ['tone' => 'danger', 'title' => 'Senkronizasyon tamamlanamadı.', 'message' => $msg ?: 'Detaylar aşağıdaki hata bölümünde listelenir.']);
    } elseif ($applied > 0) {
        flash('dbs_alert', ['tone' => 'success', 'title' => 'Migrationlar uygulandı.', 'message' => dbsFmt($applied) . ' migration başarıyla uygulandı.']);
    } else {
        flash('dbs_alert', ['tone' => 'warning', 'title' => 'Uygulanacak migration bulunmadı.', 'message' => 'Veritabanı zaten güncel görünüyor.']);
    }

    header('Location: index.php');
    exit;
}

/* GET */
$data = $syncService->run(false);
$summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
$modules = is_array($data['modules'] ?? null) ? $data['modules'] : [];
$rootMigs = is_array($data['root_migrations'] ?? null) ? $data['root_migrations'] : [];
$warnings = array_values(array_unique(array_filter(array_map('strval', is_array($data['warnings'] ?? null) ? $data['warnings'] : []))));
$errors = is_array($data['errors'] ?? null) ? array_values($data['errors']) : [];

$modTotal = 0; $modPending = 0; $modApplied = 0; $modFailed = 0;
foreach ($modules as $m) {
    $migs = is_array($m['migrations'] ?? null) ? $m['migrations'] : [];
    $modTotal += count($migs);
    $modPending += dbsCount($migs, 'pending');
    $modApplied += dbsCount($migs, 'applied');
    $modFailed  += dbsCount($migs, 'failed');
    if ((string) ($m['status'] ?? '') === 'failed') $modFailed++;
}

$rootTotal = count($rootMigs);
$rootPending = dbsCount($rootMigs, 'pending');
$rootApplied = dbsCount($rootMigs, 'applied');
$rootFailed  = dbsCount($rootMigs, 'failed');

$allTotal = $modTotal + $rootTotal;
$allPending = $modPending + $rootPending;
$allApplied = $modApplied + $rootApplied;
$allFailed  = max((int) ($summary['failed'] ?? 0), count($errors), $modFailed + $rootFailed);
$allWarnings = count($warnings);

$overall = 'up_to_date';
if ($allFailed > 0) $overall = 'failed';
elseif ($allPending > 0) $overall = 'pending';

$overallBadgeClass = match($overall) { 'failed'=>'danger', 'pending'=>'warning', default=>'success' };
$overallBadgeText = match($overall) { 'failed'=>'Hata Var', 'pending'=>'Bekleyen Var', default=>'Güncel' };

$flash = null;
if (function_exists('get_flash')) {
    $raw = get_flash('dbs_alert');
    if (is_array($raw)) $flash = $raw;
}

$pageTitle = 'Veritabanı Senkronizasyonu';
require __DIR__ . '/../header.php';
?>
<div class="dbs-page">

    <!-- FLASH -->
    <?php if ($flash): ?>
    <?= adminRenderAlert((string) ($flash['message'] ?? ''), (string) ($flash['tone'] ?? 'info'), [
        'icon' => match ($flash['tone'] ?? 'info') { 'success' => 'bi-check-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', 'danger' => 'bi-x-circle-fill', default => 'bi-info-circle-fill' },
        'title' => (string) ($flash['title'] ?? ''),
        'role' => 'alert',
    ]) ?>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="ui-panel">
        <div class="ui-panel__head ui-admin-card-header-actions dbs-hero-head">
            <div>
                <span class="ui-admin-badge ui-admin-badge-<?= $overallBadgeClass ?>"><i class="bi <?= dbsIcon($overall) ?>"></i> <?= $overallBadgeText ?></span>
                <h1 class="ui-admin-card-title dbs-hero-title">Veritabanı Senkronizasyonu</h1>
                <p class="dbs-hero-copy">Sayfa açıldığında sadece kontrol yapılır. Migration uygulamak için butonu kullanın.</p>
            </div>
            <div class="dbs-hero-actions">
                <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="index.php"><i class="bi bi-arrow-clockwise"></i> Yenile</a>
                <form method="post" action="index.php"<?= adminConfirmAttrs(['message' => 'Bekleyen migrationlar uygulanacak. Devam etmek istiyor musunuz?', 'title' => 'Migrationlar uygulansın mı?', 'ok' => 'Uygula', 'tone' => 'warning']) ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply_pending_migrations">
                    <button class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" type="submit" <?= $allPending <= 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-lightning-charge-fill"></i> Migrationları Uygula
                        <?php if ($allPending > 0): ?><span class="ui-admin-badge ui-admin-badge-light dbs-action-badge"><?= dbsFmt($allPending) ?></span><?php endif; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <?= adminRenderStatCards([
        ['tone' => $overallBadgeClass, 'icon' => dbsIcon($overall), 'label' => 'Genel Durum', 'value' => match($overall) { 'failed'=>'Hata', 'pending'=>'Bekleyen', default=>'Güncel' }],
        ['tone' => 'info', 'icon' => 'bi-files', 'label' => 'Toplam Migration', 'value' => dbsFmt($allTotal)],
        ['tone' => 'success', 'icon' => 'bi-check-circle-fill', 'label' => 'Uygulanmış', 'value' => dbsFmt($allApplied)],
        ['tone' => 'warning', 'icon' => 'bi-hourglass-split', 'label' => 'Bekleyen', 'value' => dbsFmt($allPending)],
        ['tone' => 'danger', 'icon' => 'bi-x-circle', 'label' => 'Hatalı', 'value' => dbsFmt($allFailed)],
        ['tone' => 'info', 'icon' => 'bi-exclamation-triangle', 'label' => 'Uyarı', 'value' => dbsFmt($allWarnings)],
    ], ['class' => 'database-sync-summary', 'aria_label' => 'Veritabanı senkronizasyon özeti']) ?>

    <!-- META -->
    <div class="dbs-meta">
        <span class="dbs-meta-item"><i class="bi bi-play-circle"></i> Başlangıç: <?= htmlspecialchars(dbsDt($data['started_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="dbs-meta-item"><i class="bi bi-check2-circle"></i> Bitiş: <?= htmlspecialchars(dbsDt($data['finished_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="dbs-meta-item"><i class="bi bi-stopwatch"></i> Süre: <?= dbsFmt((int) ($data['duration_ms'] ?? 0)) ?> ms</span>
        <span class="dbs-meta-item"><i class="bi <?= !empty($data['lock']['acquired']) ? 'bi-lock-fill' : 'bi-unlock' ?>"></i> Kilit: <?= !empty($data['lock']['acquired']) ? 'Alındı' : 'Alınamadı' ?></span>
    </div>

    <!-- HATA / UYARI -->
    <?php if ($warnings !== [] || $errors !== []): ?>
    <div class="dbs-alert-grid">
        <?php if ($warnings !== []): ?>
        <div class="ui-panel dbs-alert-panel">
            <strong class="dbs-alert-title"><i class="bi bi-exclamation-triangle dbs-icon-warning"></i> Uyarılar (<?= count($warnings) ?>)</strong>
            <ul class="dbs-alert-list">
                <?php foreach ($warnings as $w): ?><li><?= htmlspecialchars((string) $w, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
        <div class="ui-panel dbs-alert-panel">
            <strong class="dbs-alert-title"><i class="bi bi-x-circle dbs-icon-danger"></i> Hatalar (<?= count($errors) ?>)</strong>
            <ul class="dbs-alert-list">
                <?php foreach ($errors as $e):
                    $msg = is_array($e) ? (string) ($e['message'] ?? 'Bilinmeyen hata') : (string) $e;
                    $det = is_array($e) ? trim((string) ($e['detail'] ?? '')) : '';
                ?>
                <li>
                    <strong><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if ($det !== '' && $det !== $msg): ?>
                    <details class="dbs-error-details"><summary class="dbs-detail-summary">Teknik detay</summary>
                        <code class="dbs-detail-code"><?= htmlspecialchars($det, ENT_QUOTES, 'UTF-8') ?></code>
                    </details>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- MODÜL MIGRATIONLARI -->
    <div class="ui-panel">
        <div class="ui-panel__head dbs-panel-head">
            <div class="dbs-panel-head-row">
                <h2 class="ui-admin-card-title"><i class="bi bi-puzzle"></i> Modül Migrationları</h2>
                <span class="dbs-panel-meta"><?= dbsFmt($modTotal) ?> dosya &middot; <?= dbsFmt($modPending) ?> bekleyen</span>
            </div>
        </div>
        <div class="ui-panel__body">
            <?php if ($modules === []): ?>
            <?= adminRenderEmptyState([
                'icon' => 'bi-puzzle',
                'tone' => 'info',
                'title' => 'Modül bulunamadı',
                'description' => 'Migration dizini olan aktif modül bulunamadı.',
                'class' => 'database-sync-empty',
            ]) ?>
            <?php else: ?>
            <?= adminRenderTableOpen(['Modül', 'Durum', 'Migrationlar'], [
                'wrap_class' => 'ui-admin-table-wrap-x',
                'label' => 'Modül migrationları',
            ]) ?>
                        <?php foreach ($modules as $mod):
                            $modName = (string) ($mod['name'] ?? $mod['id'] ?? '?');
                            $migs = is_array($mod['migrations'] ?? null) ? $mod['migrations'] : [];
                            $mPend = dbsCount($migs, 'pending');
                            $mApp  = dbsCount($migs, 'applied');
                            $mFail = dbsCount($migs, 'failed');
                            $ms = (string) ($mod['status'] ?? '');
                            $pill = match($ms) { 'disabled'=>'muted', 'failed'=>'danger', 'up_to_date','applied'=>'success', default=>'warning' };
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($modName, ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if (!empty($mod['requires_modules'])): ?>
                                <br><span class="dbs-muted-xs">bağımlı: <?= htmlspecialchars(implode(', ', $mod['requires_modules']), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="ui-admin-badge ui-admin-badge-<?= $pill ?>"><?= htmlspecialchars(ucfirst($ms ?: '?'), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <?php if ($migs === []): ?>
                                    <span class="dbs-muted-sm">Dosya yok</span>
                                <?php else: ?>
                                    <div class="dbs-migration-dots">
                                        <?php if ($mApp > 0): ?><span class="dbs-dot applied" title="<?= $mApp ?> uygulanmış"></span><?php endif; ?>
                                        <?php if ($mPend > 0): ?><span class="dbs-dot pending" title="<?= $mPend ?> bekleyen"></span><?php endif; ?>
                                        <?php if ($mFail > 0): ?><span class="dbs-dot failed" title="<?= $mFail ?> hatalı"></span><?php endif; ?>
                                        <span class="dbs-migration-dot-text">
                                            <?php
                                            $parts = [];
                                            if ($mApp > 0) $parts[] = dbsFmt($mApp) . ' uygulanmış';
                                            if ($mPend > 0) $parts[] = dbsFmt($mPend) . ' bekleyen';
                                            if ($mFail > 0) $parts[] = dbsFmt($mFail) . ' hatalı';
                                            echo implode(' · ', $parts);
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($mPend > 0 || $mFail > 0): ?>
                                    <details>
                                        <summary class="dbs-detail-summary">Tüm dosyaları göster (<?= count($migs) ?>)</summary>
                                        <?= adminRenderTableOpen([
                                            'Dosya',
                                            ['label' => 'Tür', 'class' => 'dbs-mini-col-kind'],
                                            ['label' => 'Durum', 'class' => 'dbs-mini-col-status'],
                                        ], [
                                            'class' => 'dbs-mini-table',
                                            'wrap_class' => 'dbs-mini-table-wrap',
                                            'label' => 'Modül migration dosyaları',
                                        ]) ?>
                                                <?php foreach ($migs as $mg):
                                                    $s = (string) ($mg['status'] ?? 'pending');
                                                    $b = dbsBadge($s);
                                                    $i = dbsIcon($s);
                                                ?>
                                                <tr>
                                                    <td><code><?= htmlspecialchars((string) ($mg['path'] ?? $mg['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                                                    <td><span class="ui-admin-badge ui-admin-badge-muted"><?= htmlspecialchars(strtoupper((string) ($mg['kind'] ?? 'file')), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                    <td><span class="ui-admin-badge ui-admin-badge-<?= $b ?>"><i class="bi <?= $i ?>"></i></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                        <?= adminRenderTableClose() ?>
                                    </details>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
            <?= adminRenderTableClose() ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- ROOT MIGRATIONLARI -->
    <div class="ui-panel">
        <div class="ui-panel__head dbs-panel-head">
            <div class="dbs-panel-head-row">
                <h2 class="ui-admin-card-title"><i class="bi bi-database"></i> Root Migrationları</h2>
                <span class="dbs-panel-meta"><?= dbsFmt($rootTotal) ?> dosya &middot; <?= dbsFmt($rootPending) ?> bekleyen</span>
            </div>
        </div>
        <div class="ui-panel__body">
            <?php if ($rootMigs === []): ?>
            <?= adminRenderEmptyState([
                'icon' => 'bi-database',
                'tone' => 'info',
                'title' => 'Root migration bulunamadı',
                'description' => 'database/migrations dizininde dosya görünmüyor.',
                'class' => 'database-sync-empty',
            ]) ?>
            <?php else: ?>
            <?= adminRenderTableOpen(['Dosya', 'Tür', 'Durum'], [
                'wrap_class' => 'ui-admin-table-wrap-x',
                'label' => 'Root migrationları',
            ]) ?>
                        <?php foreach ($rootMigs as $mg):
                            $s = (string) ($mg['status'] ?? 'pending');
                            $b = dbsBadge($s);
                            $i = dbsIcon($s);
                            $lb = match($b) { 'success'=>'Uygulanmış', 'warning'=>'Bekliyor', 'danger'=>'Hata', default=>'?' };
                        ?>
                        <tr>
                            <td><code><?= htmlspecialchars((string) ($mg['path'] ?? $mg['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td><span class="ui-admin-badge ui-admin-badge-muted"><?= htmlspecialchars(strtoupper((string) ($mg['kind'] ?? 'file')), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="ui-admin-badge ui-admin-badge-<?= $b ?>"><i class="bi <?= $i ?>"></i> <?= $lb ?></span></td>
                        </tr>
                        <?php endforeach; ?>
            <?= adminRenderTableClose() ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>
