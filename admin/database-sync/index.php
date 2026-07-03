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
<style>
.dbs-meta { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.25rem; }
.dbs-meta-item { display:inline-flex; align-items:center; gap:.3rem; font-size:.78rem; color:var(--ui-admin-text-secondary); background:var(--ui-admin-surface-alt); padding:.2rem .55rem; border-radius:6px; border:1px solid var(--ui-admin-border); }
.dbs-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:.15rem; vertical-align:middle; }
.dbs-dot.applied { background:#10b981; }
.dbs-dot.pending { background:#f59e0b; }
.dbs-dot.failed  { background:#ef4444; }
.dbs-card .admin-stat-card { min-height:96px; }
</style>

<div style="display:grid;gap:1rem">

    <!-- FLASH -->
    <?php if ($flash): ?>
    <div class="ui-admin-alert ui-admin-alert-<?= $flash['tone'] ?? 'info' ?>" role="alert">
        <i class="bi <?= match($flash['tone'] ?? 'info') { 'success'=>'bi-check-circle-fill', 'warning'=>'bi-exclamation-triangle-fill', 'danger'=>'bi-x-circle-fill', default=>'bi-info-circle-fill' } ?>"></i>
        <div>
            <?php if (!empty($flash['title'])): ?><strong><?= htmlspecialchars($flash['title'], ENT_QUOTES, 'UTF-8') ?></strong><br><?php endif; ?>
            <span><?= htmlspecialchars($flash['message'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="ui-panel">
        <div class="ui-panel__head ui-admin-card-header-actions" style="border:none;padding-bottom:0">
            <div>
                <span class="ui-admin-badge ui-admin-badge-<?= $overallBadgeClass ?>"><i class="bi <?= dbsIcon($overall) ?>"></i> <?= $overallBadgeText ?></span>
                <h1 class="ui-admin-card-title" style="margin-top:.35rem">Veritabanı Senkronizasyonu</h1>
                <p style="color:var(--ui-admin-text-secondary);font-size:.85rem;margin:.2rem 0 0;max-width:65ch">Sayfa açıldığında sadece kontrol yapılır. Migration uygulamak için butonu kullanın.</p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
                <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="index.php"><i class="bi bi-arrow-clockwise"></i> Yenile</a>
                <form method="post" action="index.php" onsubmit="return confirm('Bekleyen migrationlar uygulanacak. Devam etmek istiyor musunuz?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply_pending_migrations">
                    <button class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" type="submit" <?= $allPending <= 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-lightning-charge-fill"></i> Migrationları Uygula
                        <?php if ($allPending > 0): ?><span class="ui-admin-badge ui-admin-badge-light" style="margin-left:.3rem"><?= dbsFmt($allPending) ?></span><?php endif; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- STATS -->
    <div class="admin-stat-grid ui-grid">
        <div class="admin-stat-card stat-<?= $overallBadgeClass ?> ui-card">
            <div class="stat-icon"><i class="bi <?= dbsIcon($overall) ?>"></i></div>
            <div class="stat-content">
                <span class="stat-label">Genel Durum</span>
                <strong class="stat-value"><?= match($overall) { 'failed'=>'Hata', 'pending'=>'Bekleyen', default=>'Güncel' } ?></strong>
            </div>
        </div>
        <div class="admin-stat-card stat-info ui-card">
            <div class="stat-icon"><i class="bi bi-files"></i></div>
            <div class="stat-content">
                <span class="stat-label">Toplam Migration</span>
                <strong class="stat-value"><?= dbsFmt($allTotal) ?></strong>
            </div>
        </div>
        <div class="admin-stat-card stat-success ui-card">
            <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-content">
                <span class="stat-label">Uygulanmış</span>
                <strong class="stat-value"><?= dbsFmt($allApplied) ?></strong>
            </div>
        </div>
        <div class="admin-stat-card stat-warning ui-card">
            <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-content">
                <span class="stat-label">Bekleyen</span>
                <strong class="stat-value"><?= dbsFmt($allPending) ?></strong>
            </div>
        </div>
        <div class="admin-stat-card stat-danger ui-card">
            <div class="stat-icon"><i class="bi bi-x-circle"></i></div>
            <div class="stat-content">
                <span class="stat-label">Hatalı</span>
                <strong class="stat-value"><?= dbsFmt($allFailed) ?></strong>
            </div>
        </div>
        <div class="admin-stat-card stat-info ui-card">
            <div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-content">
                <span class="stat-label">Uyarı</span>
                <strong class="stat-value"><?= dbsFmt($allWarnings) ?></strong>
            </div>
        </div>
    </div>

    <!-- META -->
    <div class="dbs-meta">
        <span class="dbs-meta-item"><i class="bi bi-play-circle"></i> Başlangıç: <?= htmlspecialchars(dbsDt($data['started_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="dbs-meta-item"><i class="bi bi-check2-circle"></i> Bitiş: <?= htmlspecialchars(dbsDt($data['finished_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></span>
        <span class="dbs-meta-item"><i class="bi bi-stopwatch"></i> Süre: <?= dbsFmt((int) ($data['duration_ms'] ?? 0)) ?> ms</span>
        <span class="dbs-meta-item"><i class="bi <?= !empty($data['lock']['acquired']) ? 'bi-lock-fill' : 'bi-unlock' ?>"></i> Kilit: <?= !empty($data['lock']['acquired']) ? 'Alındı' : 'Alınamadı' ?></span>
    </div>

    <!-- HATA / UYARI -->
    <?php if ($warnings !== [] || $errors !== []): ?>
    <div style="display:grid;gap:.6rem;grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
        <?php if ($warnings !== []): ?>
        <div class="ui-panel" style="padding:1rem">
            <strong style="display:block;margin-bottom:.5rem"><i class="bi bi-exclamation-triangle" style="color:#d97706"></i> Uyarılar (<?= count($warnings) ?>)</strong>
            <ul style="margin:0;padding-left:1.2rem;display:grid;gap:.3rem;font-size:.85rem;color:var(--ui-admin-text-secondary)">
                <?php foreach ($warnings as $w): ?><li><?= htmlspecialchars((string) $w, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
        <div class="ui-panel" style="padding:1rem">
            <strong style="display:block;margin-bottom:.5rem"><i class="bi bi-x-circle" style="color:#dc2626"></i> Hatalar (<?= count($errors) ?>)</strong>
            <ul style="margin:0;padding-left:1.2rem;display:grid;gap:.3rem;font-size:.85rem;color:var(--ui-admin-text-secondary)">
                <?php foreach ($errors as $e):
                    $msg = is_array($e) ? (string) ($e['message'] ?? 'Bilinmeyen hata') : (string) $e;
                    $det = is_array($e) ? trim((string) ($e['detail'] ?? '')) : '';
                ?>
                <li>
                    <strong><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if ($det !== '' && $det !== $msg): ?>
                    <details style="margin-top:.2rem"><summary style="cursor:pointer;font-size:.78rem;color:var(--ui-admin-text-muted)">Teknik detay</summary>
                        <code style="display:block;margin-top:.25rem;padding:.4rem .5rem;background:var(--ui-admin-surface-alt);border:1px solid var(--ui-admin-border);border-radius:6px;font-size:.78rem;white-space:pre-wrap;word-break:break-word"><?= htmlspecialchars($det, ENT_QUOTES, 'UTF-8') ?></code>
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
        <div class="ui-panel__head" style="padding-bottom:0">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
                <h2 class="ui-admin-card-title"><i class="bi bi-puzzle"></i> Modül Migrationları</h2>
                <span style="font-size:.78rem;color:var(--ui-admin-text-muted)"><?= dbsFmt($modTotal) ?> dosya &middot; <?= dbsFmt($modPending) ?> bekleyen</span>
            </div>
        </div>
        <div class="ui-panel__body">
            <?php if ($modules === []): ?>
            <div class="ui-admin-empty" style="padding:1.5rem;text-align:center">
                <div style="font-size:1.8rem;margin-bottom:.4rem"><i class="bi bi-puzzle"></i></div>
                <h3 style="font-size:.95rem;margin:0;font-weight:600;color:var(--ui-admin-text-secondary)">Modül bulunamadı</h3>
                <p style="font-size:.82rem;margin:.2rem 0 0;color:var(--ui-admin-text-muted)">Migration dizini olan aktif modül bulunamadı.</p>
            </div>
            <?php else: ?>
            <div class="ui-admin-table-wrap-x">
                <table class="ui-admin-table">
                    <thead>
                        <tr><th>Modül</th><th>Durum</th><th>Migrationlar</th></tr>
                    </thead>
                    <tbody>
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
                                <br><span style="font-size:.7rem;color:var(--ui-admin-text-muted)">bağımlı: <?= htmlspecialchars(implode(', ', $mod['requires_modules']), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="ui-admin-badge ui-admin-badge-<?= $pill ?>"><?= htmlspecialchars(ucfirst($ms ?: '?'), ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <?php if ($migs === []): ?>
                                    <span style="color:var(--ui-admin-text-muted);font-size:.8rem">Dosya yok</span>
                                <?php else: ?>
                                    <div style="margin-bottom:.25rem">
                                        <?php if ($mApp > 0): ?><span class="dbs-dot applied" title="<?= $mApp ?> uygulanmış"></span><?php endif; ?>
                                        <?php if ($mPend > 0): ?><span class="dbs-dot pending" title="<?= $mPend ?> bekleyen"></span><?php endif; ?>
                                        <?php if ($mFail > 0): ?><span class="dbs-dot failed" title="<?= $mFail ?> hatalı"></span><?php endif; ?>
                                        <span style="font-size:.75rem;color:var(--ui-admin-text-muted);margin-left:.2rem">
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
                                        <summary style="font-size:.75rem;color:var(--ui-admin-text-muted);cursor:pointer">Tüm dosyaları göster (<?= count($migs) ?>)</summary>
                                        <table style="width:100%;font-size:.78rem;margin-top:.35rem;border-collapse:collapse">
                                            <thead>
                                                <tr style="border-bottom:1px solid var(--ui-admin-border)">
                                                    <th style="padding:.25rem .4rem;text-align:left;font-weight:700;color:var(--ui-admin-text-muted)">Dosya</th>
                                                    <th style="padding:.25rem .4rem;text-align:left;font-weight:700;color:var(--ui-admin-text-muted);width:70px">Tür</th>
                                                    <th style="padding:.25rem .4rem;text-align:left;font-weight:700;color:var(--ui-admin-text-muted);width:90px">Durum</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($migs as $mg):
                                                    $s = (string) ($mg['status'] ?? 'pending');
                                                    $b = dbsBadge($s);
                                                    $i = dbsIcon($s);
                                                ?>
                                                <tr style="border-bottom:1px solid var(--ui-admin-border)">
                                                    <td style="padding:.3rem .4rem"><code><?= htmlspecialchars((string) ($mg['path'] ?? $mg['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></code></td>
                                                    <td style="padding:.3rem .4rem"><span class="ui-admin-badge ui-admin-badge-muted"><?= htmlspecialchars(strtoupper((string) ($mg['kind'] ?? 'file')), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                    <td style="padding:.3rem .4rem"><span class="ui-admin-badge ui-admin-badge-<?= $b ?>"><i class="bi <?= $i ?>"></i></span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </details>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ROOT MIGRATIONLARI -->
    <div class="ui-panel">
        <div class="ui-panel__head" style="padding-bottom:0">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap">
                <h2 class="ui-admin-card-title"><i class="bi bi-database"></i> Root Migrationları</h2>
                <span style="font-size:.78rem;color:var(--ui-admin-text-muted)"><?= dbsFmt($rootTotal) ?> dosya &middot; <?= dbsFmt($rootPending) ?> bekleyen</span>
            </div>
        </div>
        <div class="ui-panel__body">
            <?php if ($rootMigs === []): ?>
            <div class="ui-admin-empty" style="padding:1.5rem;text-align:center">
                <div style="font-size:1.8rem;margin-bottom:.4rem"><i class="bi bi-database"></i></div>
                <h3 style="font-size:.95rem;margin:0;font-weight:600;color:var(--ui-admin-text-secondary)">Root migration bulunamadı</h3>
                <p style="font-size:.82rem;margin:.2rem 0 0;color:var(--ui-admin-text-muted)">database/migrations dizininde dosya görünmüyor.</p>
            </div>
            <?php else: ?>
            <div class="ui-admin-table-wrap-x">
                <table class="ui-admin-table">
                    <thead>
                        <tr><th>Dosya</th><th>Tür</th><th>Durum</th></tr>
                    </thead>
                    <tbody>
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
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../footer.php'; ?>