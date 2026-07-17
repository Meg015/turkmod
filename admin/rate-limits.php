<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

adminRequirePermission('rate_limits.view', 'İstek sınırı kayıtlarını görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'İstek Sınırı İzleme';

/**
 * Rate key'ini insan okunabilir hale getirir.
 * Örn: "login_127.0.0.1" → {type: "Giriş", identifier: "127.0.0.1"}
 *       "api_leaderboard_127.0.0.1" → {type: "API (Leaderboard)", identifier: "127.0.0.1"}
 */
function rateLimitFormatKey(string $key): array
{
    $knownTypes = [
            'login'           => 'Giriş',
        'register'        => 'Kayıt',
        'password_reset'  => 'Şifre Sıfırlama',
        'search'          => 'Arama',
        'comment'         => 'Yorum',
        'comment_mention' => 'Mention Arama',
        'comment_edit'    => 'Yorum Düzenle',
        'comment_reaction'=> 'Yorum Reaksiyon',
        'comment_report'  => 'Yorum Şikayet',
        'download'        => 'İndirme',
        'topic_view'      => 'Konu Görüntüleme',
        'api_leaderboard' => 'API (Liderlik)',
        'api_analytics'   => 'API (Analitik)',
        'api_favorite'    => 'API (Favori)',
        'api_topics'      => 'API (Konular)',
        'api_reports'     => 'API (Şikayet)',
        'api_user_reports' => 'API (Kullanıcı Şikayet)',
    ];

    // API anahtarları çok parçalı olabilir: api_leaderboard_127.0.0.1
    if (str_starts_with($key, 'api_')) {
        $parts = explode('_', $key, 3);
        if (count($parts) === 3) {
            return [
                'type'       => $knownTypes[$parts[0] . '_' . $parts[1]] ?? $parts[0] . '_' . $parts[1],
                'identifier' => $parts[2],
            ];
        }
    }

    $parts = explode('_', $key, 2);
    return [
        'type'       => $knownTypes[$parts[0]] ?? $parts[0],
        'identifier' => $parts[1] ?? $key,
    ];
}

function rateLimitDeleteByIds(?PDO $pdo, array $ids): int
{
    if (!$pdo) return 0;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (empty($ids)) return 0;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM request_rate_limits WHERE id IN ({$placeholders})");
    $stmt->execute($ids);
    return $stmt->rowCount();
}

// --- POST işlemleri ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $redirectUrl = 'rate-limits.php';
    $baseCleanupOptions = [
        'action_type' => 'rate_limit_records_deleted',
        'permission' => 'rate_limits.manage',
        'permission_message' => 'İstek sınırı kayıtlarını silmek için gerekli izin hesabınıza tanımlanmamış.',
        'redirect_url' => $redirectUrl,
        'activity_subject' => 'rate_limit',
        'context' => static fn (string $scope): array => [
            'source' => 'rate_limits',
            'action' => $action,
        ],
        'error_prefix' => 'İşlem başarısız: ',
    ];

    if ($action === 'delete_one') {
        adminRunLogCleanup($pdo, $baseCleanupOptions + [
            'scope' => 'single',
            'allowed_scopes' => ['single'],
            'delete' => static fn (PDO $pdo): int => rateLimitDeleteByIds($pdo, [(int)($_POST['id'] ?? 0)]),
            'require_deleted' => true,
            'failure_message' => 'Silinecek kayıt bulunamadı.',
            'success_message' => 'İstek sınırı kaydı silindi.',
        ]);
    } elseif ($action === 'delete_selected') {
        adminRunLogCleanup($pdo, $baseCleanupOptions + [
            'scope' => 'selected',
            'allowed_scopes' => ['selected'],
            'delete' => static fn (PDO $pdo): int => rateLimitDeleteByIds($pdo, (array)($_POST['rate_ids'] ?? [])),
            'require_deleted' => true,
            'failure_message' => 'Lütfen en az bir kayıt seçin.',
            'success_message' => static fn (int $deleted): string => $deleted . ' istek sınırı kaydı silindi.',
        ]);
    } elseif ($action === 'clear_login') {
        adminRunLogCleanup($pdo, $baseCleanupOptions + [
            'scope' => 'login',
            'allowed_scopes' => ['login'],
            'delete' => static function (PDO $pdo): int {
                $stmt = $pdo->prepare("DELETE FROM request_rate_limits WHERE LEFT(rate_key, 6) = 'login_'");
                $stmt->execute();
                return $stmt->rowCount();
            },
            'success_message' => static fn (int $deleted): string => $deleted . ' giriş kilidi kaydı silindi.',
        ]);
    } elseif ($action === 'clear_expired') {
        adminRunLogCleanup($pdo, $baseCleanupOptions + [
            'scope' => 'expired',
            'allowed_scopes' => ['expired'],
            'delete' => static function (PDO $pdo): int {
                $stmt = $pdo->prepare('DELETE FROM request_rate_limits WHERE expires_at <= NOW()');
                $stmt->execute();
                return $stmt->rowCount();
            },
            'app_log' => true,
            'app_log_message' => 'rate_limit_cleanup',
            'success_message' => static fn (int $deleted): string => $deleted . ' süresi dolmuş kayıt silindi.',
        ]);
    } elseif ($action === 'clear_all') {
        adminRunLogCleanup($pdo, $baseCleanupOptions + [
            'scope' => 'all',
            'allowed_scopes' => ['all'],
            'delete' => static function (PDO $pdo): int {
                $stmt = $pdo->prepare('DELETE FROM request_rate_limits');
                $stmt->execute();
                return $stmt->rowCount();
            },
            'success_message' => static fn (int $deleted): string => $deleted . ' istek sınırı kaydı silindi.',
        ]);
    }

    header('Location: rate-limits.php');
    exit;
}

// --- GET: Filtreleme ---
$search = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? 'all'); // Varsayılan: tümü (eski: active)
if (!in_array($status, ['active', 'expired', 'all'], true)) $status = 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = adminPaginationPerPage();
$offset = 0;

$items = [];
$totalFiltered = 0;
$totalPages = 1;
$stats = ['total' => 0, 'active' => 0, 'expired' => 0, 'login_active' => 0];

if ($pdo) {
    try {
        $stats['total'] = (int)$pdo->query('SELECT COUNT(*) FROM request_rate_limits')->fetchColumn();
        $stats['active'] = (int)$pdo->query('SELECT COUNT(*) FROM request_rate_limits WHERE expires_at > NOW()')->fetchColumn();
        $stats['expired'] = (int)$pdo->query('SELECT COUNT(*) FROM request_rate_limits WHERE expires_at <= NOW()')->fetchColumn();
        $stats['login_active'] = (int)$pdo->query("SELECT COUNT(*) FROM request_rate_limits WHERE LEFT(rate_key, 6) = 'login_' AND expires_at > NOW()")->fetchColumn();

        $where = [];
        $params = [];

        if ($status === 'active') {
            $where[] = 'expires_at > NOW()';
        } elseif ($status === 'expired') {
            $where[] = 'expires_at <= NOW()';
        }

        if ($search !== '') {
            $where[] = '(rate_key LIKE :search_key OR scope LIKE :search_scope)';
            $searchTerm = '%' . $search . '%';
            $params['search_key'] = $searchTerm;
            $params['search_scope'] = $searchTerm;
        }

        $sql = 'SELECT id, scope, rate_key, attempt_count, first_attempt_at, last_attempt_at, expires_at, created_at, updated_at
                FROM request_rate_limits';
        if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql = 'SELECT COUNT(*) FROM request_rate_limits' . (!empty($where) ? ' WHERE ' . implode(' AND ', $where) : '');
        $countStmt = $pdo->prepare($countSql);
        foreach ($params as $paramKey => $paramValue) {
            $countStmt->bindValue(':' . $paramKey, $paramValue, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalFiltered = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($totalFiltered / max(1, $perPage)));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $sql .= ' ORDER BY expires_at DESC, updated_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $pdo->prepare($sql);
        foreach ($params as $paramKey => $paramValue) {
            $stmt->bindValue(':' . $paramKey, $paramValue, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();
    } catch (Throwable $e) {
        flash('error', 'İstek sınırı kayıtları yüklenemedi: ' . safeErrorMessage($e));
    }
}

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
$canManageRateLimits = function_exists('adminCurrentUserCan') && adminCurrentUserCan('rate_limits.manage');
require_once __DIR__ . '/header.php';
?>
<?php adminRenderLogsSubtabs('rate_limits'); ?>

<div class="logs-page rate-limit-page">
    <section class="ui-admin-page-hero">
        <div class="ui-admin-page-hero-text">
            <span class="ui-admin-kicker"><i class="bi bi-speedometer2"></i> Erişim sınırları</span>
            <h2>İstek Sınırı İzleme</h2>
            <p>Giriş, kayıt ve API istek sınırlarını tek akışta izleyin; süresi dolan kayıtları topluca temizleyin.</p>
        </div>
    </section>

    <!-- İstatistik Kartları -->
    <section class="rate-limit-hero" aria-label="İstek sınırı özeti ve filtreler">
        <div class="admin-stat-grid logs-summary rate-limit-summary ui-grid">
            <div class="admin-stat-card stat-info logs-stat rate-limit-stat ui-card">
                <div class="stat-icon"><i class="bi bi-collection"></i></div>
                <div class="stat-content"><span class="stat-label">Toplam Kayıt</span><span class="stat-value"><?= number_format($stats['total']) ?></span></div>
            </div>
            <div class="admin-stat-card stat-success logs-stat rate-limit-stat ui-card">
                <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-content"><span class="stat-label">Aktif</span><span class="stat-value"><?= number_format($stats['active']) ?></span></div>
            </div>
            <div class="admin-stat-card stat-warning logs-stat rate-limit-stat ui-card">
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-content"><span class="stat-label">Süresi Dolmuş</span><span class="stat-value"><?= number_format($stats['expired']) ?></span></div>
            </div>
            <div class="admin-stat-card stat-danger logs-stat rate-limit-stat ui-card">
                <div class="stat-icon"><i class="bi bi-unlock-fill"></i></div>
                <div class="stat-content"><span class="stat-label">Giriş Kilidi</span><span class="stat-value"><?= number_format($stats['login_active']) ?></span></div>
            </div>
        </div>
    </section>

    <!-- Filtre ve Araçlar -->
    <div class="admin-card logs-toolbar-card ui-panel">
        <div class="card-body ui-admin-card-compact ui-panel__body ui-card rate-limit-toolbar logs-toolbar-shell">
            <form class="rate-limit-search logs-filter-form ui-admin-filter-row admin-log-filter-form" method="get" action="rate-limits.php">
                <input type="text" name="q" class="ui-admin-form-control" placeholder="Anahtar, IP veya tür ara..." value="<?= htmlspecialchars($search) ?>">
                <select name="status" class="ui-admin-form-select">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tümü (önerilen)</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Sadece aktif</option>
                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Süresi dolmuş</option>
                </select>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                <?php if ($search !== '' || $status !== 'all'): ?>
                    <a href="rate-limits.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-lg"></i> Temizle</a>
                <?php endif; ?>
            </form>
            <div class="rate-limit-actions logs-toolbar-actions">
                <?php if ($canManageRateLimits): ?>
                    <button type="button" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-xs" data-clear-logs-open>
                        <i class="bi bi-trash"></i> Günlüğü Temizle
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kayıt Listesi -->
    <form method="post" action="rate-limits.php" id="rateLimitBulkForm" data-admin-confirm="Seçili istek sınırı kayıtları kalıcı olarak silinecek. Bu işlem geri alınamaz." data-admin-confirm-title="Kayıtları Temizle" data-admin-confirm-ok="Seçilenleri Kalıcı Olarak Sil" data-admin-confirm-cancel="İptal" data-admin-confirm-tone="danger" data-admin-confirm-kind="logs-clear" data-admin-confirm-icon="bi-trash">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_selected">
        <div class="admin-card rate-limit-card logs-list-card ui-panel">
            <div class="card-header rate-limit-list-head ui-panel__head logs-list-head ui-admin-card-header-actions">
                <strong class="rate-limit-list-title"><i class="bi bi-speedometer2"></i> İstek Sınırı Kayıtları</strong>
                <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-xs"><i class="bi bi-trash"></i> Seçilenleri Sil</button>
            </div>
            <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
                <?php if (empty($items)): ?>
                    <div class="ui-admin-empty ui-admin-empty-pro ui-admin-empty-rate-limit ui-empty admin-log-empty" role="status">
                        <div class="ui-admin-empty-icon <?= $search !== '' || $status !== 'all' ? 'tone-info' : 'tone-success' ?> ui-empty">
                            <i class="bi <?= $search !== '' || $status !== 'all' ? 'bi-search' : 'bi-shield-check' ?>"></i>
                        </div>
                        <h3 class="ui-admin-empty-title ui-empty">
                            <?= $search !== '' || $status !== 'all' ? 'Filtreye uyan kayıt yok' : 'Henüz istek sınırı kaydı yok' ?>
                        </h3>
                        <p class="ui-admin-empty-desc ui-empty">
                            <?= $search !== '' || $status !== 'all'
                                ? 'Seçili arama ve durum filtresiyle eşleşen kayıt bulunamadı.'
                                : 'Hiçbir işlem sınıra takılmamış. Kayıt oluştuğunda burada listelenecek.' ?>
                        </p>
                        <?php if ($search !== '' || $status !== 'all'): ?>
                            <div class="ui-admin-empty-actions ui-empty">
                                <a href="rate-limits.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-lg"></i> Filtreleri Temizle</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper rate-limit-table-wrap ui-table-wrap ui-surface admin-log-table-wrap">
                        <table class="admin-table rate-limit-table admin-log-table">
                            <thead>
                                <tr>
                                    <th class="rate-limit-check-cell"><input type="checkbox" id="selectAllRateLimits" aria-label="Tüm kayıtları seç"></th>
                                    <th>Tür</th>
                                    <th>Hedef (IP/Kullanıcı)</th>
                                    <th>Deneme</th>
                                    <th>İlk / Son</th>
                                    <th>Bitiş</th>
                                    <th>Durum</th>
                                    <th class="ui-admin-table-head-actions">İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $keyMeta = rateLimitFormatKey((string)$item['rate_key']);
                                    $expiresAt = strtotime((string)$item['expires_at']);
                                    $isActive = $expiresAt !== false && $expiresAt > time();
                                    $remaining = $isActive ? max(0, $expiresAt - time()) : 0;
                                    ?>
                                    <tr>
                                        <td class="rate-limit-check-cell"><input type="checkbox" name="rate_ids[]" value="<?= (int)$item['id'] ?>" class="rate-limit-check" aria-label="Kaydı seç"></td>
                                        <td>
                                            <div class="rate-limit-key">
                                                <strong title="<?= htmlspecialchars((string)$item['rate_key']) ?>"><?= htmlspecialchars($keyMeta['type']) ?></strong>
                                                <span class="ui-admin-muted-sm"><?= htmlspecialchars((string)$item['scope']) ?></span>
                                            </div>
                                        </td>
                                        <td><code title="<?= htmlspecialchars((string)$item['rate_key']) ?>"><?= htmlspecialchars($keyMeta['identifier']) ?></code></td>
                                        <td><span class="rate-limit-count"><?= (int)$item['attempt_count'] ?>x</span></td>
                                        <td class="rate-limit-time">
                                            <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$item['first_attempt_at']))) ?><br>
                                            <span class="rate-limit-time-muted"><?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$item['last_attempt_at']))) ?></span>
                                        </td>
                                        <td class="rate-limit-time">
                                            <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$item['expires_at']))) ?>
                                            <?php if ($isActive): ?>
                                                <br><span class="rate-limit-remaining">⏱ <?= (int)ceil($remaining / 60) ?> dk kaldı</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="rate-limit-status <?= $isActive ? 'active' : 'expired' ?>">
                                                <i class="bi <?= $isActive ? 'bi-lock' : 'bi-check-circle' ?>"></i>
                                                <?= $isActive ? 'Aktif' : 'Süresi Dolmuş' ?>
                                            </span>
                                        </td>
                                        <td class="ui-admin-table-cell-actions">
                                            <button type="submit" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-danger-outline rate-limit-row-action" form="rate-limit-delete-<?= (int)$item['id'] ?>" title="Sil"><i class="bi bi-trash"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="ui-admin-table-footer ui-panel__foot">
                        <?php
                        $visibleStart = $totalFiltered > 0 ? $offset + 1 : 0;
                        $visibleEnd = $totalFiltered > 0 ? min($offset + count($items), $totalFiltered) : 0;
                        $pageParams = array_filter([
                            'q' => $search,
                            'status' => $status !== 'all' ? $status : '',
                        ], static fn ($value): bool => $value !== '' && $value !== null);
                        $pageBase = 'rate-limits.php?' . ($pageParams ? http_build_query($pageParams) . '&' : '') . 'page=';
                        ?>
                        <span class="ui-admin-muted-sm"><?= number_format($visibleStart, 0, ',', '.') ?>-<?= number_format($visibleEnd, 0, ',', '.') ?> / <?= number_format($totalFiltered, 0, ',', '.') ?> kayıt gösteriliyor.</span>
                        <?php if ($totalPages > 1): ?>
                            <?= adminRenderPagination($totalPages, $page, static fn (int $targetPage): string => $pageBase . $targetPage, [
                                'wrapper_class' => 'logs-pagination-wrapper rate-limit-pagination',
                                'aria_label' => 'İstek sınırı sayfalama',
                            ]) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if ($canManageRateLimits): ?>
        <?php
        $logClearModal = [
            'aria_label' => 'İstek sınırı günlüğünü temizle',
            'title' => 'Günlüğü Temizle',
            'form_action' => 'rate-limits.php',
            'scope_name' => 'action',
            'options' => [
                [
                    'value' => 'clear_expired',
                    'label' => 'Süresi dolmuş kayıtları sil',
                    'confirm_title' => 'Kayıtları Temizle',
                ],
                [
                    'value' => 'clear_login',
                    'label' => 'Tüm giriş kilidi kayıtlarını sil',
                    'confirm_title' => 'Kayıtları Temizle',
                ],
                [
                    'value' => 'clear_all',
                    'label' => 'Tüm istek sınırı günlüğünü sil (Tehlikeli)',
                    'confirm_title' => 'Günlüğü Temizle',
                ],
            ],
            'warning' => 'Seçilen istek sınırı kayıtları kalıcı olarak silinir. Bu işlem geri alınamaz.',
        ];
        include __DIR__ . '/partials/log-clear-modal.php';
        unset($logClearModal);
        ?>
    <?php endif; ?>
</div>

<?php foreach ($items as $item): ?>
<form method="post" action="rate-limits.php" id="rate-limit-delete-<?= (int)$item['id'] ?>" data-admin-confirm="Bu istek sınırı kaydı kalıcı olarak silinecek. Bu işlem geri alınamaz." data-admin-confirm-title="Kayıtları Temizle" data-admin-confirm-ok="Seçilenleri Kalıcı Olarak Sil" data-admin-confirm-cancel="İptal" data-admin-confirm-tone="danger" data-admin-confirm-kind="logs-clear" data-admin-confirm-icon="bi-trash">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_one">
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
</form>
<?php endforeach; ?>

<script src="<?= asset_url('admin/assets/rate-limits-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
