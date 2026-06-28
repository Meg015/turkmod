<?php

declare(strict_types=1);
require_once __DIR__ . '/init.php';

adminRequirePermission('legacy_redirects.view', 'SEO yönlendirmelerini görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    adminRequirePermission('legacy_redirects.manage', 'SEO yönlendirmelerini yönetmek için gerekli izin hesabınıza tanımlanmamış.');
}

$pageTitle = 'SEO Yönlendirmeleri';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $pdo) {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: legacy-redirects.php');
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $redirectTab = (string) ($_POST['_tab'] ?? 'overview');
    try {
        if ($action === 'settings') {
            $redirectTab = 'settings';
            legacyRedirectSaveSettings($pdo, $_POST);
            flash('success', 'Yönlendirme ayarları kaydedildi.');
        } elseif ($action === 'rule') {
            $redirectTab = 'rules';
            legacyRedirectUpdateRule($pdo, (int) ($_POST['id'] ?? 0), (string) ($_POST['status'] ?? 'pending'), (string) ($_POST['target_url'] ?? ''));
            flash('success', 'Yönlendirme kaydı güncellendi.');
        } elseif ($action === 'test') {
            $redirectTab = 'overview';
            $testUrl = trim((string) ($_POST['test_url'] ?? ''));
            if ($testUrl !== '') {
                $testResult = legacyRedirectResolve($pdo, $testUrl);
                if (empty($testResult['redirect'])) {
                    $testResult = legacyRedirectResolveMissingRoutedPath($pdo, $testUrl);
                }
                if (!empty($testResult['redirect']) && !empty($testResult['target_url'])) {
                    flash('success', 'Test sonucu: ' . $testUrl . ' -> ' . (string) $testResult['target_url']);
                } else {
                    flash('error', 'Test sonucu yönlendirme üretmedi: ' . (string) ($testResult['result'] ?? 'not_found'));
                }
            }
        } elseif ($action === 'import_prefixes') {
            $redirectTab = 'migrate';
            $created = legacyRedirectCreatePrefixImportRules(
                $pdo,
                (string) ($_POST['import_type'] ?? 'topic'),
                (string) ($_POST['from_prefix'] ?? '')
            );
            flash('success', $created . ' adet prefix ice aktarma kurali hazirlandi.');
        }
    } catch (Throwable $e) {
        flash('error', 'İşlem başarısız: ' . safeErrorMessage($e));
    }

    $allowedTabs = ['overview', 'settings', 'migrate', 'health', 'rules'];
    if (!in_array($redirectTab, $allowedTabs, true)) {
        $redirectTab = 'overview';
    }
    $redirectParams = ['tab' => $redirectTab];
    if ($redirectTab === 'rules') {
        $returnPage = max(1, (int) ($_POST['_page'] ?? 1));
        $returnSort = trim((string) ($_POST['_sort'] ?? 'last_hit'));
        $returnDir = strtolower(trim((string) ($_POST['_dir'] ?? 'desc')));
        $returnType = trim((string) ($_POST['_type'] ?? ''));
        $returnStatus = trim((string) ($_POST['_status'] ?? ''));
        $returnSearch = trim((string) ($_POST['_q'] ?? ''));
        $allowedRuleSortsForRedirect = ['source', 'type', 'score', 'status', 'hits', 'target', 'last_hit'];

        if ($returnPage > 1) {
            $redirectParams['page'] = $returnPage;
        }
        if (in_array($returnSort, $allowedRuleSortsForRedirect, true)) {
            $redirectParams['sort'] = $returnSort;
        }
        if (in_array($returnDir, ['asc', 'desc'], true)) {
            $redirectParams['dir'] = $returnDir;
        }
        if (in_array($returnType, ['topic', 'category'], true)) {
            $redirectParams['type'] = $returnType;
        }
        if (in_array($returnStatus, ['active', 'pending', 'ignored'], true)) {
            $redirectParams['status'] = $returnStatus;
        }
        if ($returnSearch !== '') {
            $redirectParams['q'] = $returnSearch;
        }
    }
    header('Location: legacy-redirects.php?' . http_build_query($redirectParams));
    exit;
}

$settings = legacyRedirectGetSettings($pdo);
$stats = legacyRedirectStats($pdo);
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$typeFilter = trim((string) ($_GET['type'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$hitResultFilter = trim((string) ($_GET['hit_result'] ?? ''));
$hitTypeFilter = trim((string) ($_GET['hit_type'] ?? ''));
$hitSearch = trim((string) ($_GET['hit_q'] ?? ''));
$ruleSort = trim((string) ($_GET['sort'] ?? 'last_hit'));
$ruleDir = strtolower(trim((string) ($_GET['dir'] ?? 'desc')));
$hitSort = trim((string) ($_GET['hit_sort'] ?? 'date'));
$hitDir = strtolower(trim((string) ($_GET['hit_dir'] ?? 'desc')));
$allowedRuleSorts = ['source', 'type', 'score', 'status', 'hits', 'target', 'last_hit'];
if (!in_array($ruleSort, $allowedRuleSorts, true)) {
    $ruleSort = 'last_hit';
}
if (!in_array($ruleDir, ['asc', 'desc'], true)) {
    $ruleDir = 'desc';
}
$allowedHitSorts = ['source', 'result', 'type', 'score', 'target', 'date'];
if (!in_array($hitSort, $allowedHitSorts, true)) {
    $hitSort = 'date';
}
if (!in_array($hitDir, ['asc', 'desc'], true)) {
    $hitDir = 'desc';
}
$hasRuleFilters = $statusFilter !== '' || $typeFilter !== '' || $search !== '';
$hasHitFilters = $hitResultFilter !== '' || $hitTypeFilter !== '' || $hitSearch !== '';
$defaultTab = $hasHitFilters ? 'health' : ($hasRuleFilters ? 'rules' : 'overview');
$activeTab = trim((string) ($_GET['tab'] ?? $defaultTab));
if (!in_array($activeTab, ['overview', 'settings', 'migrate', 'health', 'rules'], true)) {
    $activeTab = 'overview';
}
$rulePerPage = 10;
$rulePage = max(1, (int) ($_GET['page'] ?? 1));
$ruleTotal = legacyRedirectCountRules($pdo, $statusFilter, $typeFilter, $search);
$ruleTotalPages = max(1, (int) ceil($ruleTotal / $rulePerPage));
if ($rulePage > $ruleTotalPages) {
    $rulePage = $ruleTotalPages;
}
$ruleOffset = ($rulePage - 1) * $rulePerPage;
$rules = legacyRedirectListRules($pdo, $statusFilter, $typeFilter, $search, $ruleSort, $ruleDir, $rulePerPage, $ruleOffset);
$recentHits = legacyRedirectRecentHits($pdo, 100, $hitResultFilter, $hitTypeFilter, $hitSearch, $hitSort, $hitDir);
$ruleSortUrl = static function (string $key) use ($statusFilter, $typeFilter, $search, $ruleSort, $ruleDir): string {
    $params = [
        'tab' => 'rules',
        'sort' => $key,
        'dir' => ($ruleSort === $key && $ruleDir === 'desc') ? 'asc' : 'desc',
    ];
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($typeFilter !== '') {
        $params['type'] = $typeFilter;
    }
    if ($statusFilter !== '') {
        $params['status'] = $statusFilter;
    }

    return 'legacy-redirects.php?' . http_build_query($params);
};
$rulePageUrl = static function (int $page) use ($statusFilter, $typeFilter, $search, $ruleSort, $ruleDir): string {
    $params = [
        'tab' => 'rules',
        'page' => max(1, $page),
        'sort' => $ruleSort,
        'dir' => $ruleDir,
    ];
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($typeFilter !== '') {
        $params['type'] = $typeFilter;
    }
    if ($statusFilter !== '') {
        $params['status'] = $statusFilter;
    }

    return 'legacy-redirects.php?' . http_build_query($params);
};
$ruleSortHeader = static function (string $key, string $label) use ($ruleSort, $ruleDir, $ruleSortUrl): string {
    $isActive = $ruleSort === $key;
    $ariaSort = $isActive ? ($ruleDir === 'asc' ? 'ascending' : 'descending') : 'none';
    $icon = $isActive ? ($ruleDir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up';
    $class = 'ui-admin-sort-link' . ($isActive ? ' is-active' : '');

    return '<th aria-sort="' . $ariaSort . '"><a class="' . $class . '" href="' . htmlspecialchars($ruleSortUrl($key)) . '">' . htmlspecialchars($label) . '<i class="bi ' . $icon . '"></i></a></th>';
};
$hitSortUrl = static function (string $key) use ($hitResultFilter, $hitTypeFilter, $hitSearch, $hitSort, $hitDir): string {
    $params = [
        'tab' => 'health',
        'hit_sort' => $key,
        'hit_dir' => ($hitSort === $key && $hitDir === 'desc') ? 'asc' : 'desc',
    ];
    if ($hitSearch !== '') {
        $params['hit_q'] = $hitSearch;
    }
    if ($hitTypeFilter !== '') {
        $params['hit_type'] = $hitTypeFilter;
    }
    if ($hitResultFilter !== '') {
        $params['hit_result'] = $hitResultFilter;
    }

    return 'legacy-redirects.php?' . http_build_query($params);
};
$hitSortHeader = static function (string $key, string $label) use ($hitSort, $hitDir, $hitSortUrl): string {
    $isActive = $hitSort === $key;
    $ariaSort = $isActive ? ($hitDir === 'asc' ? 'ascending' : 'descending') : 'none';
    $icon = $isActive ? ($hitDir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up';
    $class = 'ui-admin-sort-link' . ($isActive ? ' is-active' : '');

    return '<th aria-sort="' . $ariaSort . '"><a class="' . $class . '" href="' . htmlspecialchars($hitSortUrl($key)) . '">' . htmlspecialchars($label) . '<i class="bi ' . $icon . '"></i></a></th>';
};

$successMsg = get_flash('success');
$errorMsg = get_flash('error');

require_once __DIR__ . '/header.php';
?>

<?php if ($successMsg): ?>
<div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success">
    <i class="bi bi-check-circle-fill"></i>
    <?= htmlspecialchars($successMsg) ?>
    <button type="button" class="ui-admin-alert-close">&times;</button>
</div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="ui-admin-alert ui-admin-alert-error ui-alert ui-alert--error">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <?= htmlspecialchars($errorMsg) ?>
    <button type="button" class="ui-admin-alert-close">&times;</button>
</div>
<?php endif; ?>
<div class="redirect-workspace">
<nav class="redirect-tabs" aria-label="SEO yonlendirme sekmeleri">
    <a id="redirect-tab-overview" href="legacy-redirects.php?tab=overview" class="redirect-tab-link <?= $activeTab === 'overview' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i><span><strong>Genel</strong><small>Ozet metrikler</small></span></a>
    <a id="redirect-tab-settings" href="legacy-redirects.php?tab=settings" class="redirect-tab-link <?= $activeTab === 'settings' ? 'active' : '' ?>"><i class="bi bi-sliders"></i><span><strong>Ayarlar</strong><small>Davranis ve test</small></span></a>
    <a id="redirect-tab-migrate" href="legacy-redirects.php?tab=migrate" class="redirect-tab-link <?= $activeTab === 'migrate' ? 'active' : '' ?>"><i class="bi bi-shuffle"></i><span><strong>Ice Aktarma</strong><small>Toplu kural</small></span></a>
    <a id="redirect-tab-health" href="legacy-redirects.php?tab=health" class="redirect-tab-link <?= $activeTab === 'health' ? 'active' : '' ?>"><i class="bi bi-activity"></i><span><strong>Saglik Kayitlari</strong><small>Hit ve 404</small></span></a>
    <a id="redirect-tab-rules" href="legacy-redirects.php?tab=rules" class="redirect-tab-link <?= $activeTab === 'rules' ? 'active' : '' ?>"><i class="bi bi-signpost-split"></i><span><strong>Kurallar</strong><small>Kayit listesi</small></span></a>
</nav>

<section class="redirect-tab-panel <?= $activeTab === 'overview' ? 'active' : '' ?>" aria-labelledby="redirect-tab-overview">
<div class="admin-stat-grid redirect-summary ui-grid">
    <div class="admin-stat-card stat-info redirect-stat ui-card">
        <div class="stat-icon"><i class="bi bi-signpost-split"></i></div>
        <div class="stat-content"><span class="stat-label">Toplam</span><span class="stat-value"><?= (int) $stats['total'] ?></span></div>
    </div>
    <div class="admin-stat-card stat-success redirect-stat ui-card">
        <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-content"><span class="stat-label">Aktif</span><span class="stat-value"><?= (int) $stats['active'] ?></span></div>
    </div>
    <div class="admin-stat-card stat-warning redirect-stat ui-card">
        <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
        <div class="stat-content"><span class="stat-label">İnceleme</span><span class="stat-value"><?= (int) $stats['pending'] ?></span></div>
    </div>
    <div class="admin-stat-card stat-danger redirect-stat ui-card">
        <div class="stat-icon"><i class="bi bi-eye-slash"></i></div>
        <div class="stat-content"><span class="stat-label">Yok Sayılan</span><span class="stat-value"><?= (int) $stats['ignored'] ?></span></div>
    </div>
    <div class="admin-stat-card stat-info redirect-stat ui-card">
        <div class="stat-icon"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="stat-content"><span class="stat-label">Hit</span><span class="stat-value"><?= (int) $stats['hits'] ?></span></div>
    </div>
</div>

</section>
<section class="redirect-tab-panel <?= $activeTab === 'migrate' ? 'active' : '' ?>" aria-labelledby="redirect-tab-migrate">

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-header ui-panel__head"><i class="bi bi-shuffle me-2"></i>Toplu Route Ice Aktarma</div>
    <div class="card-body ui-panel__body">
        <form method="post" action="legacy-redirects.php" class="ui-admin-filter-row">
            <?= csrf_field() ?>
            <input type="hidden" name="_tab" value="migrate">
            <input type="hidden" name="action" value="import_prefixes">
            <div>
                <label class="ui-admin-form-label">Tur</label>
                <select name="import_type" class="ui-admin-form-select">
                    <option value="topic">Konu</option>
                    <option value="category">Kategori</option>
                    <option value="profile">Profil</option>
                </select>
            </div>
            <div class="ui-admin-filter-grow">
                <label class="ui-admin-form-label">Eski On Ek</label>
                <input type="text" name="from_prefix" class="ui-admin-form-control" placeholder="konu, kategori, user, mod">
            </div>
            <button type="submit" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-magic"></i> Kurallari Olustur</button>
        </form>
    </div>
</div>

</section>
<section class="redirect-tab-panel <?= $activeTab === 'settings' ? 'active' : '' ?>" aria-labelledby="redirect-tab-settings">

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-header ui-panel__head"><i class="bi bi-sliders me-2"></i>Yönlendirme Ayarları</div>
    <div class="card-body ui-panel__body">
        <form method="post" action="legacy-redirects.php" class="ui-admin-filter-grid ui-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="_tab" value="settings">
            <input type="hidden" name="action" value="settings">
            <label class="ui-admin-switch">
                <input type="checkbox" name="enabled" value="1" <?= ($settings['enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span class="ui-admin-switch-label">Otomatik yönlendirme aktif</span>
            </label>
            <div>
                <label class="ui-admin-form-label">Minimum otomatik skor</label>
                <input type="number" min="0" max="100" name="minimum_score" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($settings['minimum_score'] ?? '75')) ?>">
            </div>
            <div>
                <label class="ui-admin-form-label">Düşük skor davranışı</label>
                <select name="low_score_mode" class="ui-admin-form-select">
                    <option value="redirect" <?= ($settings['low_score_mode'] ?? 'redirect') === 'redirect' ? 'selected' : '' ?>>Her skorda en iyi eşleşmeye yönlendir</option>
                    <option value="review" <?= ($settings['low_score_mode'] ?? 'redirect') === 'review' ? 'selected' : '' ?>>Düşük skoru incelemeye bırak</option>
                </select>
            </div>
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i> Kaydet</button>
        </form>
    </div>
</div>

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-header ui-panel__head"><i class="bi bi-search me-2"></i>Eski URL Testi</div>
    <div class="card-body ui-panel__body">
        <form method="post" action="legacy-redirects.php" class="ui-admin-filter-row">
            <?= csrf_field() ?>
            <input type="hidden" name="_tab" value="overview">
            <input type="hidden" name="action" value="test">
            <div class="ui-admin-filter-grow-lg">
                <label class="ui-admin-form-label">Eski URL</label>
                <input type="text" name="test_url" class="ui-admin-form-control" placeholder="/konu/ets2-bartoland-harita-modu-1-57.24171/">
            </div>
            <button type="submit" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-play-circle"></i> Test Et</button>
        </form>
    </div>
</div>

</section>
<section class="redirect-tab-panel <?= $activeTab === 'rules' ? 'active' : '' ?>" aria-labelledby="redirect-tab-rules">

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-body ui-panel__body">
        <form method="get" action="legacy-redirects.php" class="ui-admin-filter-row">
            <input type="hidden" name="tab" value="rules">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($ruleSort) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($ruleDir) ?>">
            <div class="ui-admin-filter-grow">
                <label class="ui-admin-form-label">Ara</label>
                <input type="text" name="q" class="ui-admin-form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Eski URL, slug veya hedef...">
            </div>
            <div>
                <label class="ui-admin-form-label">Tür</label>
                <select name="type" class="ui-admin-form-select">
                    <option value="">Tümü</option>
                    <option value="topic" <?= $typeFilter === 'topic' ? 'selected' : '' ?>>Konu</option>
                    <option value="category" <?= $typeFilter === 'category' ? 'selected' : '' ?>>Kategori</option>
                </select>
            </div>
            <div>
                <label class="ui-admin-form-label">Durum</label>
                <select name="status" class="ui-admin-form-select">
                    <option value="">Tümü</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>İnceleme</option>
                    <option value="ignored" <?= $statusFilter === 'ignored' ? 'selected' : '' ?>>Yok say</option>
                </select>
            </div>
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-funnel"></i> Filtrele</button>
            <a href="legacy-redirects.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-lg"></i></a>
        </form>
    </div>
</div>

</section>
<section class="redirect-tab-panel <?= $activeTab === 'health' ? 'active' : '' ?>" aria-labelledby="redirect-tab-health">

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-body ui-panel__body">
        <form method="get" action="legacy-redirects.php" class="ui-admin-filter-row">
            <input type="hidden" name="tab" value="health">
            <input type="hidden" name="hit_sort" value="<?= htmlspecialchars($hitSort) ?>">
            <input type="hidden" name="hit_dir" value="<?= htmlspecialchars($hitDir) ?>">
            <div class="ui-admin-filter-grow">
                <label class="ui-admin-form-label">Ara</label>
                <input type="text" name="hit_q" class="ui-admin-form-control" value="<?= htmlspecialchars($hitSearch) ?>" placeholder="URL veya hedef...">
            </div>
            <div>
                <label class="ui-admin-form-label">Tür</label>
                <select name="hit_type" class="ui-admin-form-select">
                    <option value="">Tümü</option>
                    <option value="topic" <?= $hitTypeFilter === 'topic' ? 'selected' : '' ?>>Konu</option>
                    <option value="category" <?= $hitTypeFilter === 'category' ? 'selected' : '' ?>>Kategori</option>
                </select>
            </div>
            <div>
                <label class="ui-admin-form-label">Sonuç</label>
                <select name="hit_result" class="ui-admin-form-select">
                    <option value="">Tümü</option>
                    <option value="redirected" <?= $hitResultFilter === 'redirected' ? 'selected' : '' ?>>Yönlendirildi</option>
                    <option value="pending" <?= $hitResultFilter === 'pending' ? 'selected' : '' ?>>İnceleme</option>
                    <option value="ignored" <?= $hitResultFilter === 'ignored' ? 'selected' : '' ?>>Yok sayıldı</option>
                    <option value="not_found" <?= $hitResultFilter === 'not_found' ? 'selected' : '' ?>>Bulunamadı</option>
                    <option value="disabled" <?= $hitResultFilter === 'disabled' ? 'selected' : '' ?>>Kapalı</option>
                </select>
            </div>
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-funnel"></i> Filtrele</button>
            <a href="legacy-redirects.php?tab=health" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-lg"></i></a>
        </form>
    </div>
</div>

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-header ui-panel__head"><i class="bi bi-activity me-2"></i>Son Yönlendirme / 404 Kayıtları</div>
    <div class="card-body ui-admin-card-flush ui-panel__body ui-card">
        <div class="table-wrapper ui-table-wrap ui-surface">
            <table class="admin-table">
                <thead>
                    <tr>
                        <?= $hitSortHeader('source', 'URL') ?>
                        <?= $hitSortHeader('result', 'Sonuç') ?>
                        <?= $hitSortHeader('type', 'Tür') ?>
                        <?= $hitSortHeader('score', 'Skor') ?>
                        <?= $hitSortHeader('target', 'Hedef') ?>
                        <?= $hitSortHeader('date', 'Tarih') ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentHits)): ?>
                        <tr><td colspan="6" class="ui-admin-empty-row-sm ui-empty">Henuz hit kaydi yok.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentHits as $hit): ?>
                            <tr>
                                <td class="ui-admin-min-source"><?= htmlspecialchars((string) $hit['source_path']) ?></td>
                                <td><span class="admin-badge admin-badge-<?= ($hit['result'] ?? '') === 'redirected' ? 'success' : (($hit['result'] ?? '') === 'pending' ? 'warning' : 'secondary') ?>"><?= htmlspecialchars((string) $hit['result']) ?></span></td>
                                <td><?= htmlspecialchars((string) ($hit['source_type'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($hit['match_score'] ?? '-')) ?></td>
                                <td class="ui-admin-min-target"><?= htmlspecialchars((string) ($hit['target_url'] ?? '-')) ?></td>
                                <td class="ui-admin-nowrap-secondary"><?= !empty($hit['created_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $hit['created_at']))) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</section>
<section class="redirect-tab-panel <?= $activeTab === 'rules' ? 'active' : '' ?>" aria-labelledby="redirect-tab-rules">

<div class="admin-card ui-panel">
    <div class="card-header ui-panel__head"><i class="bi bi-signpost-split me-2"></i>Yönlendirme Kayıtları (<?= (int) $ruleTotal ?>)</div>
    <div class="card-body ui-admin-card-flush ui-panel__body ui-card">
        <div class="table-wrapper ui-table-wrap ui-surface">
            <table class="admin-table">
                <thead>
                    <tr>
                        <?= $ruleSortHeader('source', 'Eski URL') ?>
                        <?= $ruleSortHeader('type', 'Tür') ?>
                        <?= $ruleSortHeader('score', 'Skor') ?>
                        <?= $ruleSortHeader('status', 'Durum') ?>
                        <?= $ruleSortHeader('hits', 'Hit') ?>
                        <?= $ruleSortHeader('target', 'Hedef') ?>
                        <?= $ruleSortHeader('last_hit', 'Son Hit') ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rules)): ?>
                        <tr><td colspan="7" class="ui-admin-empty-row ui-empty">Henüz yönlendirme kaydı yok.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rules as $rule): ?>
                            <tr>
                                <td class="ui-admin-min-target">
                                    <div class="ui-admin-rule-source"><?= htmlspecialchars((string) $rule['source_path']) ?></div>
                                    <div class="ui-admin-rule-meta">Eski ID: <?= htmlspecialchars((string) ($rule['legacy_id'] ?? '-')) ?></div>
                                </td>
                                <td><span class="admin-badge admin-badge-secondary"><?= $rule['source_type'] === 'category' ? 'Kategori' : 'Konu' ?></span></td>
                                <td><strong><?= (int) ($rule['match_score'] ?? 0) ?></strong></td>
                                <td>
                                    <?php $badge = ['active' => 'success', 'pending' => 'warning', 'ignored' => 'secondary'][(string) $rule['status']] ?? 'secondary'; ?>
                                    <span class="admin-badge admin-badge-<?= htmlspecialchars($badge) ?>"><?= htmlspecialchars((string) $rule['status']) ?></span>
                                </td>
                                <td><?= (int) ($rule['hit_count'] ?? 0) ?></td>
                                <td class="ui-admin-min-wide">
                                    <form method="post" action="legacy-redirects.php" class="ui-admin-form-row">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="_tab" value="rules">
                                        <input type="hidden" name="action" value="rule">
                                        <input type="hidden" name="id" value="<?= (int) $rule['id'] ?>">
                                        <input type="hidden" name="_page" value="<?= (int) $rulePage ?>">
                                        <input type="hidden" name="_sort" value="<?= htmlspecialchars($ruleSort) ?>">
                                        <input type="hidden" name="_dir" value="<?= htmlspecialchars($ruleDir) ?>">
                                        <input type="hidden" name="_q" value="<?= htmlspecialchars($search) ?>">
                                        <input type="hidden" name="_type" value="<?= htmlspecialchars($typeFilter) ?>">
                                        <input type="hidden" name="_status" value="<?= htmlspecialchars($statusFilter) ?>">
                                        <input type="text" name="target_url" class="ui-admin-form-control" value="<?= htmlspecialchars((string) ($rule['target_url'] ?? '')) ?>" placeholder="/konu/yeni-slug">
                                        <select name="status" class="ui-admin-form-select ui-admin-form-select-sm ui-admin-select-xs">
                                            <option value="active" <?= $rule['status'] === 'active' ? 'selected' : '' ?>>Aktif</option>
                                            <option value="pending" <?= $rule['status'] === 'pending' ? 'selected' : '' ?>>İncele</option>
                                            <option value="ignored" <?= $rule['status'] === 'ignored' ? 'selected' : '' ?>>Yok say</option>
                                        </select>
                                        <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm" title="Kaydet"><i class="bi bi-check2"></i></button>
                                    </form>
                                </td>
                                <td class="ui-admin-nowrap-secondary"><?= !empty($rule['last_hit_at']) ? htmlspecialchars(date('d.m.Y H:i', strtotime((string) $rule['last_hit_at']))) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($ruleTotalPages > 1): ?>
            <nav class="ui-pagination ui-admin-mt-md redirect-rules-pagination" aria-label="Yönlendirme kayıtları sayfalama">
                <?php if ($rulePage > 1): ?>
                    <a href="<?= htmlspecialchars($rulePageUrl($rulePage - 1)) ?>" class="ui-pagination__page" aria-label="Önceki sayfa"><i class="bi bi-chevron-left"></i></a>
                <?php endif; ?>

                <?php for ($i = max(1, $rulePage - 2); $i <= min($ruleTotalPages, $rulePage + 2); $i++): ?>
                    <a href="<?= htmlspecialchars($rulePageUrl($i)) ?>"
                       class="ui-pagination__page <?= $i === $rulePage ? 'is-active' : '' ?>"<?= $i === $rulePage ? ' aria-current="page"' : '' ?>>
                        <?= (int) $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($rulePage < $ruleTotalPages): ?>
                    <a href="<?= htmlspecialchars($rulePageUrl($rulePage + 1)) ?>" class="ui-pagination__page" aria-label="Sonraki sayfa"><i class="bi bi-chevron-right"></i></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>

</section>

</div>

<?php require_once __DIR__ . '/footer.php'; ?>
