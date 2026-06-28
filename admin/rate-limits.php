<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

adminRequirePermission('rate_limits.view', 'Rate limit kayıtlarını görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

$pageTitle = 'Rate Limit Izleme';

function rateLimitFormatKey(string $key): array
{
    $parts = explode('_', $key, 2);
    return [
        'type' => $parts[0] !== '' ? $parts[0] : 'default',
        'identifier' => $parts[1] ?? $key,
    ];
}

function rateLimitDeleteByIds(?PDO $pdo, array $ids): int
{
    if (!$pdo) {
        return 0;
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (empty($ids)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM request_rate_limits WHERE id IN ({$placeholders})");
    $stmt->execute($ids);
    return $stmt->rowCount();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminRequirePermission('rate_limits.manage', 'Rate limit kayıtlarını silmek için gerekli izin hesabınıza tanımlanmamış.');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Guvenlik hatasi.');
        header('Location: rate-limits.php');
        exit;
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'delete_one') {
            $deleted = rateLimitDeleteByIds($pdo, [(int)($_POST['id'] ?? 0)]);
            if ($deleted > 0) {
                logActivity($pdo, 'rate_limit_records_deleted', 'rate_limit', null, ['action' => $action, 'deleted' => $deleted]);
            }
            flash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? 'Rate limit kaydi silindi.' : 'Silinecek kayit bulunamadi.');
        } elseif ($action === 'delete_selected') {
            $deleted = rateLimitDeleteByIds($pdo, (array)($_POST['rate_ids'] ?? []));
            if ($deleted > 0) {
                logActivity($pdo, 'rate_limit_records_deleted', 'rate_limit', null, ['action' => $action, 'deleted' => $deleted]);
            }
            flash($deleted > 0 ? 'success' : 'error', $deleted > 0 ? $deleted . ' rate limit kaydi silindi.' : 'Lutfen en az bir kayit secin.');
        } elseif ($action === 'clear_login') {
            $stmt = $pdo->prepare("DELETE FROM request_rate_limits WHERE LEFT(rate_key, 6) = 'login_'");
            $stmt->execute();
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                logActivity($pdo, 'rate_limit_records_deleted', 'rate_limit', null, ['action' => $action, 'deleted' => $deleted, 'scope' => 'login']);
            }
            flash('success', $deleted . ' login rate limit kaydi silindi.');
        } elseif ($action === 'clear_expired') {
            $stmt = $pdo->prepare('DELETE FROM request_rate_limits WHERE expires_at <= NOW()');
            $stmt->execute();
            $deleted = $stmt->rowCount();
            if (function_exists('appLog')) {
                appLog($pdo, 'info', 'maintenance', 'rate_limit_cleanup', ['action' => 'clear_expired', 'deleted' => $deleted]);
            }
            if ($deleted > 0) {
                logActivity($pdo, 'rate_limit_records_deleted', 'rate_limit', null, ['action' => $action, 'deleted' => $deleted, 'scope' => 'expired']);
            }
            flash('success', $deleted . ' suresi dolmus kayit silindi.');
        } elseif ($action === 'clear_all') {
            $stmt = $pdo->prepare('DELETE FROM request_rate_limits');
            $stmt->execute();
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                logActivity($pdo, 'rate_limit_records_deleted', 'rate_limit', null, ['action' => $action, 'deleted' => $deleted, 'scope' => 'all']);
            }
            flash('success', $deleted . ' rate limit kaydi silindi.');
        }
    } catch (Throwable $e) {
        flash('error', 'Islem basarisiz: ' . safeErrorMessage($e));
    }

    header('Location: rate-limits.php');
    exit;
}

$search = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? 'active');
if (!in_array($status, ['active', 'expired', 'all'], true)) {
    $status = 'active';
}

$items = [];
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
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY expires_at DESC, updated_at DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
    } catch (Throwable $e) {
        flash('error', 'Rate limit kayitlari yuklenemedi: ' . safeErrorMessage($e));
    }
}

$successMsg = get_flash('success');
$errorMsg = get_flash('error');
require_once __DIR__ . '/header.php';
?>
<div class="rate-limit-page">
    <section class="rate-limit-hero" aria-label="Rate limit ozeti ve filtreler">
        <div class="admin-stat-grid rate-limit-summary ui-grid">
            <div class="admin-stat-card stat-info rate-limit-stat ui-card">
                <div class="stat-icon"><i class="bi bi-collection"></i></div>
                <div class="stat-content"><span class="stat-label">Toplam</span><span class="stat-value"><?= number_format($stats['total']) ?></span></div>
            </div>
            <div class="admin-stat-card stat-success rate-limit-stat ui-card">
                <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
                <div class="stat-content"><span class="stat-label">Aktif</span><span class="stat-value"><?= number_format($stats['active']) ?></span></div>
            </div>
            <div class="admin-stat-card stat-warning rate-limit-stat ui-card">
                <div class="stat-icon"><i class="bi bi-hourglass-split"></i></div>
                <div class="stat-content"><span class="stat-label">Suresi Dolmus</span><span class="stat-value"><?= number_format($stats['expired']) ?></span></div>
            </div>
            <div class="admin-stat-card stat-danger rate-limit-stat ui-card">
                <div class="stat-icon"><i class="bi bi-unlock-fill"></i></div>
                <div class="stat-content"><span class="stat-label">Login Kilidi</span><span class="stat-value"><?= number_format($stats['login_active']) ?></span></div>
            </div>
        </div>

        <div class="rate-limit-toolbar">
            <form class="rate-limit-search" method="get" action="rate-limits.php">
                <input type="text" name="q" class="ui-admin-form-control" placeholder="Anahtar, IP veya scope ara..." value="<?= htmlspecialchars($search) ?>">
                <select name="status" class="ui-admin-form-select">
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Aktif kayitlar</option>
                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Suresi dolmus</option>
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Tumu</option>
                </select>
                <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                <?php if ($search !== '' || $status !== 'active'): ?>
                    <a href="rate-limits.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-lg"></i> Temizle</a>
                <?php endif; ?>
            </form>
            <div class="rate-limit-actions">
                <form method="post" action="rate-limits.php" data-admin-confirm="Suresi dolmus kayitlar silinsin mi?" data-admin-confirm-title="Dolan kayıtları sil" data-admin-confirm-ok="Sil" data-admin-confirm-tone="warning">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_expired">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-hourglass-split"></i> Dolanlari Sil</button>
                </form>
                <form method="post" action="rate-limits.php" data-admin-confirm="Tum login rate limit kayitlari silinsin mi?" data-admin-confirm-title="Login kilitlerini temizle" data-admin-confirm-ok="Temizle" data-admin-confirm-tone="warning">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_login">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-warning ui-admin-btn-sm"><i class="bi bi-unlock"></i> Login Kilitleri</button>
                </form>
                <form method="post" action="rate-limits.php" data-admin-confirm="Tum rate limit kayitlari silinsin mi?" data-admin-confirm-title="Tüm kayıtları sil" data-admin-confirm-ok="Tümünü Sil" data-admin-confirm-tone="danger">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear_all">
                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm"><i class="bi bi-trash"></i> Tumunu Sil</button>
                </form>
            </div>
        </div>
    </section>

    <form method="post" action="rate-limits.php" id="rateLimitBulkForm" data-admin-confirm="Secili kayitlar silinsin mi?" data-admin-confirm-title="Seçili kayıtları sil" data-admin-confirm-ok="Sil" data-admin-confirm-tone="danger">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_selected">
        <div class="admin-card rate-limit-card ui-panel">
            <div class="card-header rate-limit-list-head ui-panel__head">
                <strong class="rate-limit-list-title"><i class="bi bi-speedometer2"></i> Rate limit kayitlari</strong>
                <button type="submit" class="ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-sm"><i class="bi bi-trash"></i> Secilileri Sil</button>
            </div>
            <div class="card-body ui-admin-card-body-flush ui-panel__body ui-card">
                <?php if (empty($items)): ?>
                    <div class="rate-limit-empty ui-admin-empty ui-admin-empty-pro ui-admin-empty-rate-limit ui-empty" role="status">
                        <div class="ui-admin-empty-icon <?= $search !== '' || $status !== 'active' ? 'tone-info' : 'tone-success' ?> ui-empty"><i class="bi <?= $search !== '' || $status !== 'active' ? 'bi-search' : 'bi-shield-check' ?>"></i></div>
                        <h3 class="ui-admin-empty-title ui-empty"><?= $search !== '' || $status !== 'active' ? 'Filtreye uyan kayıt yok' : 'Rate limit kaydı yok' ?></h3>
                        <p class="ui-admin-empty-desc ui-empty">
                            <?= $search !== '' || $status !== 'active'
                                ? 'Seçili arama ve durum filtresiyle eşleşen rate limit kaydı bulunamadı.'
                                : 'Aktif kilit veya bekleyen rate limit kaydı yok. Yeni denemeler oluştuğunda burada listelenecek.' ?>
                        </p>
                        <div class="ui-admin-empty-meta" aria-label="Rate limit durumu">
                            <span><i class="bi bi-speedometer2"></i> <?= $status === 'active' ? 'Aktif liste' : htmlspecialchars($status) ?></span>
                            <span><i class="bi bi-search"></i> <?= $search !== '' ? 'Arama uygulanıyor' : 'Arama yok' ?></span>
                        </div>
                        <?php if ($search !== '' || $status !== 'active'): ?>
                            <div class="ui-admin-empty-actions ui-empty">
                                <a href="rate-limits.php" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-lg"></i> Filtreleri Temizle</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-wrapper rate-limit-table-wrap ui-table-wrap ui-surface">
                        <table class="admin-table rate-limit-table">
                            <thead>
                                <tr>
                                    <th class="rate-limit-check-cell"><input type="checkbox" id="selectAllRateLimits" aria-label="Tum kayitlari sec"></th>
                                    <th>Anahtar</th>
                                    <th>Deneme</th>
                                    <th>Ilk / Son</th>
                                    <th>Bitis</th>
                                    <th>Durum</th>
                                    <th class="ui-admin-table-head-actions">Islem</th>
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
                                        <td class="rate-limit-check-cell"><input type="checkbox" name="rate_ids[]" value="<?= (int)$item['id'] ?>" class="rate-limit-check" aria-label="Kaydi sec"></td>
                                        <td>
                                            <div class="rate-limit-key">
                                                <strong title="<?= htmlspecialchars((string)$item['rate_key']) ?>"><?= htmlspecialchars((string)$item['rate_key']) ?></strong>
                                                <span title="<?= htmlspecialchars((string)$item['scope']) ?> / <?= htmlspecialchars($keyMeta['type']) ?> / <?= htmlspecialchars($keyMeta['identifier']) ?>">
                                                    <?= htmlspecialchars((string)$item['scope']) ?> / <?= htmlspecialchars($keyMeta['type']) ?> / <?= htmlspecialchars($keyMeta['identifier']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td><span class="rate-limit-count"><?= (int)$item['attempt_count'] ?></span></td>
                                        <td class="rate-limit-time">
                                            <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$item['first_attempt_at']))) ?><br>
                                            <span class="rate-limit-time-muted"><?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$item['last_attempt_at']))) ?></span>
                                        </td>
                                        <td class="rate-limit-time">
                                            <?= htmlspecialchars(date('d.m.Y H:i', strtotime((string)$item['expires_at']))) ?>
                                            <?php if ($isActive): ?>
                                                <br><span class="rate-limit-remaining"><?= (int)ceil($remaining / 60) ?> dk kaldi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="rate-limit-status <?= $isActive ? 'active' : 'expired' ?>">
                                                <i class="bi <?= $isActive ? 'bi-lock' : 'bi-check-circle' ?>"></i>
                                                <?= $isActive ? 'Aktif' : 'Dolmus' ?>
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
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<?php foreach ($items as $item): ?>
<form method="post" action="rate-limits.php" id="rate-limit-delete-<?= (int)$item['id'] ?>" data-admin-confirm="Bu rate limit kaydi silinsin mi?" data-admin-confirm-title="Rate limit kaydı sil" data-admin-confirm-ok="Sil" data-admin-confirm-tone="danger">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_one">
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
</form>
<?php endforeach; ?>

<script src="<?= asset_url('admin/assets/rate-limits-page.js', $baseUri) ?>" defer></script>

<?php require_once __DIR__ . '/footer.php'; ?>
