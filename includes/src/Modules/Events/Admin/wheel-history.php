<?php

declare(strict_types=1);

require_once __DIR__ . '/_shared.php';

global $pdo, $baseUri;
$ready = eventsTablesReady($pdo ?? null);
$errors = [];

// Silme işlemi
if ($ready && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['_events_action'] ?? '');
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: events-wheel.php?tab=history');
        exit;
    }

    try {
        if ($action === 'delete_spin') {
            $spinId = (int)($_POST['spin_id'] ?? 0);
            if ($spinId > 0) {
                // İlişkili user_reward kaydını da sil
                $stmt = $pdo->prepare("SELECT user_reward_id FROM events_wheel_spins WHERE id = ?");
                $stmt->execute([$spinId]);
                $userRewardId = $stmt->fetchColumn();

                if ($userRewardId) {
                    $pdo->prepare("DELETE FROM events_user_rewards WHERE id = ?")->execute([$userRewardId]);
                }

                $stmt = $pdo->prepare("DELETE FROM events_wheel_spins WHERE id = ?");
                $stmt->execute([$spinId]);
                eventsAuditLog($pdo, 'wheel_spin_delete', 'wheel_spin', $spinId, ['spin_id' => $spinId]);
                flash('success', 'Çark kaydı tamamen silindi.');
            }
            header('Location: events-wheel.php?tab=history');
            exit;
        }

        if ($action === 'delete_user_spins') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId > 0) {
                // İlişkili user_reward kayıtlarını da sil
                $stmt = $pdo->prepare("SELECT user_reward_id FROM events_wheel_spins WHERE user_id = ? AND user_reward_id IS NOT NULL");
                $stmt->execute([$userId]);
                $userRewardIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($userRewardIds)) {
                    $placeholders = implode(',', array_fill(0, count($userRewardIds), '?'));
                    $pdo->prepare("DELETE FROM events_user_rewards WHERE id IN ($placeholders)")->execute($userRewardIds);
                }

                $stmt = $pdo->prepare("DELETE FROM events_wheel_spins WHERE user_id = ?");
                $stmt->execute([$userId]);
                eventsAuditLog($pdo, 'wheel_user_spins_delete', 'wheel_spins', null, ['user_id' => $userId]);
                flash('success', 'Kullanıcının tüm çark kayıtları tamamen silindi.');
            }
            header('Location: events-wheel.php?tab=history');
            exit;
        }

        if ($action === 'delete_all_spins') {
            // İlişkili user_reward kayıtlarını da sil
            $stmt = $pdo->prepare("SELECT user_reward_id FROM events_wheel_spins WHERE user_reward_id IS NOT NULL");
            $stmt->execute();
            $userRewardIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($userRewardIds)) {
                $placeholders = implode(',', array_fill(0, count($userRewardIds), '?'));
                $pdo->prepare("DELETE FROM events_user_rewards WHERE id IN ($placeholders)")->execute($userRewardIds);
            }

            $stmt = $pdo->prepare("DELETE FROM events_wheel_spins");
            $stmt->execute();
            eventsAuditLog($pdo, 'wheel_all_spins_delete', 'wheel_spins', null, []);
            flash('success', 'Tüm çark kayıtları tamamen silindi.');
            header('Location: events-wheel.php?tab=history');
            exit;
        }
    } catch (Throwable $e) {
        eventsErrorLog($pdo, 'Wheel history action failed.', ['error' => $e->getMessage(), 'action' => $action], 'ERROR');
        $errors['server'] = safeErrorMessage($e, 'İşlem tamamlanamadı.');
    }
}

eventsAdminStyles($baseUri ?? '');

$spins = [];
$totalSpins = 0;
$userStats = [];

// $ready false olsa bile verileri çekmeye çalış
try {
    $userNameExpr = (function_exists('usersColumnExists') && usersColumnExists($pdo, 'users', 'username'))
        ? "COALESCE(NULLIF(u.username, ''), CONCAT('user-', u.id))"
        : "CONCAT('user-', u.id)";
    // Sayfalama
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Toplam kayıt sayısı
    $countStmt = $pdo->query("SELECT COUNT(*) FROM events_wheel_spins");
    $totalSpins = (int)$countStmt->fetchColumn();

    // Çark geçmişi
    $stmt = $pdo->prepare("
        SELECT
            ws.id,
            ws.user_id,
            ws.reward_id,
            ws.created_at,
            wr.name AS reward_name,
            wr.type AS reward_type,
            wr.value AS reward_value,
            {$userNameExpr} AS username,
            u.email
        FROM events_wheel_spins ws
        JOIN events_wheel_rewards wr ON wr.id = ws.reward_id
        LEFT JOIN users u ON u.id = ws.user_id
        ORDER BY ws.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$perPage, $offset]);
    $spins = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Kullanıcı istatistikleri
    $statsStmt = $pdo->query("
        SELECT
            u.id,
            {$userNameExpr} AS username,
            u.email,
            COUNT(ws.id) as spin_count,
            MAX(ws.created_at) as last_spin
        FROM users u
        LEFT JOIN events_wheel_spins ws ON ws.user_id = u.id
        WHERE ws.id IS NOT NULL
        GROUP BY u.id
        ORDER BY spin_count DESC
        LIMIT 100
    ");
    $userStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    eventsErrorLog($pdo, 'Wheel history list failed.', ['error' => $e->getMessage()], 'WARNING');
    $errors['database'] = 'Veritabanı hatası: ' . $e->getMessage();
}

$totalPages = ceil($totalSpins / 10);
?>

<div data-ui-events-wheel-ajax-root>
<div class="ui-events-admin-page ui-events-admin-console ui-section" data-ui-events-admin-page="wheel-history">
    <?php eventsAdminTabs($baseUri ?? ''); ?>
    <?php eventsAdminSetupNotice($ready); ?>
    <?php
    eventsAdminPageHero(
        'Çark Geçmişi',
        'Kullanıcıların çark kullanım geçmişini görüntüleyin ve yönetin.',
        'bi-clock-history'
    );
    ?>

    <section class="ui-panel admin-card ui-events-admin-panel ui-events-wheel-history-shell ui-events-tabs-shell ui-events-admin-tabs-shell ui-section" data-ui-events-tabs-root data-ui-events-admin-component="tabs">
        <div class="ui-events-master-actionbar ui-events-admin-actionbar ui-events-wheel-section-tabs ui-cluster" data-ui-events-admin-component="actionbar">
            <div class="ui-events-app-nav ui-events-app-nav-minimal ui-events-admin-tablist" aria-label="Çark yönetimi menüsü">
                <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-wheel.php?tab=management') ?>" class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" role="tab" aria-selected="false" aria-controls="ui-events-admin-tab-wheel-management" data-ui-events-tab="management" data-ui-events-wheel-ajax-link>
                    <i class="bi bi-sliders"></i> Çark Yönetimi
                </a>
                <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-wheel.php?tab=management&panel=new') ?>" class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" data-ui-events-wheel-ajax-link><i class="bi bi-plus-lg"></i> Yeni Ödül</a>
                <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-wheel.php?tab=management&panel=settings') ?>" class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" data-ui-events-wheel-ajax-link><i class="bi bi-sliders"></i> Çark Ayarları</a>
                <a href="<?= htmlspecialchars(rtrim($baseUri ?? '', '/') . '/admin/events-wheel.php?tab=history') ?>" class="ui-admin-btn ui-admin-btn-primary ui-events-admin-tab" role="tab" aria-selected="true" aria-controls="ui-events-admin-tab-wheel-history" data-ui-events-tab="history" data-ui-events-wheel-ajax-link><i class="bi bi-clock-history"></i> Çark Geçmişi</a>
            </div>
        </div>

        <div class="ui-panel__body ui-events-panel-body ui-events-tabs-body ui-events-admin-tabs-body ui-panel">

            <!-- ÇARK GEÇMİŞİ SEKME -->
            <div class="ui-events-tab-panel ui-events-admin-tab-panel is-active ui-panel" id="ui-events-admin-tab-wheel-history" role="tabpanel" data-ui-events-tab-panel="history">

    <section class="ui-section ui-events-admin-panel-body ui-panel ui-panel__body">
            <?php eventsAdminErrorList($errors); ?>

            <?php if ($totalSpins === 0): ?>
                <div class="ui-events-admin-info-alert ui-alert">
                    <strong>⚠️ Bilgi:</strong> Veritabanında çark çevirme kaydı bulunamadı. Toplam kayıt: <?= $totalSpins ?>, Kullanıcı sayısı: <?= count($userStats) ?>
                </div>
            <?php endif; ?>

            <!-- Sekmeler -->
            <div class="ui-events-admin-tablist ui-events-wheel-history-subtabs ui-events-admin-subtabs" data-ui-events-wheel-history-tabs>
                <button class="ui-admin-btn ui-admin-btn-primary ui-events-admin-tab" type="button" data-ui-events-wheel-history-tab="ui-events-admin-tab-wheel-history-list" aria-controls="ui-events-admin-tab-wheel-history-list" aria-selected="true">
                    <i class="bi bi-list"></i> Geçmiş Kayıtları
                </button>
                <button class="ui-admin-btn ui-admin-btn-outline ui-events-admin-tab" type="button" data-ui-events-wheel-history-tab="ui-events-admin-tab-wheel-history-users" aria-controls="ui-events-admin-tab-wheel-history-users" aria-selected="false">
                    <i class="bi bi-people"></i> Kullanıcı İstatistikleri
                </button>
            </div>

            <!-- Geçmiş Kayıtları Sekmesi -->
            <div id="ui-events-admin-tab-wheel-history-list" class="ui-events-admin-tab-panel is-active ui-panel" data-ui-events-wheel-history-panel>
                <div class="ui-surface admin-card ui-events-admin-panel ui-events-admin-table-panel ui-panel">
                    <div class="ui-panel__body card-body ui-events-table-wrap ui-events-admin-table-wrap ui-table-wrap ui-surface">
                        <div class="ui-events-admin-actionstrip">
                            <form class="ui-events-inline-form" method="post" data-ui-events-confirm="Tüm çark çevirme kayıtları kalıntı bırakmadan silinecektir. Bu işlem geri alınamaz." data-ui-events-confirm-title="Tüm kayıtlar silinsin mi?" data-ui-events-confirm-ok="Tümünü Sil" data-ui-events-confirm-tone="danger">
                                <?= csrf_field() ?>
                                <input type="hidden" name="_events_action" value="delete_all_spins">
                                <button class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm" type="submit">
                                    <i class="bi bi-trash"></i> Tüm Kayıtları Sil
                                </button>
                            </form>
                        </div>

                        <?php if ($spins === []): ?>
                            <?php eventsAdminEmptyState('bi-clock-history', 'Çark Geçmişi Bulunamadı', 'Henüz hiç çark çevirme kaydı bulunmuyor.'); ?>
                        <?php else: ?>
                            <table class="ui-events-table">
                                <thead>
                                    <tr>
                                        <th>Kullanıcı</th>
                                        <th>Ödül</th>
                                        <th>Tür</th>
                                        <th>Tarih</th>
                                        <th>Değer</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($spins as $spin): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($spin['username'] ?? 'Silinmiş Kullanıcı') ?></strong>
                                                <br><span class="ui-events-list-meta"><?= e($spin['email'] ?? '') ?></span>
                                            </td>
                                            <td>
                                                <strong><?= e($spin['reward_name']) ?></strong>
                                                <br><span class="ui-events-list-meta"><?= e($spin['reward_type']) ?></span>
                                            </td>
                                            <td><span class="ui-events-badge ui-events-badge-muted"><?= e($spin['reward_type']) ?></span></td>
                                            <td><?= date('d.m.Y H:i', strtotime($spin['created_at'])) ?></td>
                                            <td><?= e($spin['reward_value']) ?></td>
                                            <td>
                                                <form class="ui-events-inline-form" method="post" data-ui-events-confirm="Bu çark çevirme kaydı kalıntı bırakmadan silinecektir." data-ui-events-confirm-title="Kayıt silinsin mi?" data-ui-events-confirm-ok="Sil" data-ui-events-confirm-tone="danger">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="_events_action" value="delete_spin">
                                                    <input type="hidden" name="spin_id" value="<?= $spin['id'] ?>">
                                                    <button class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger" type="submit" title="Sil">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Sayfalama -->
                            <?php if ($totalPages > 1): ?>
                                <div class="ui-events-admin-pagination">
                                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                        <a href="?tab=history&page=<?= $p ?>" class="ui-admin-btn ui-events-admin-page-number <?= $p === $page ? 'ui-admin-btn-primary' : 'ui-admin-btn-outline' ?>">
                                            <?= $p ?>
                                        </a>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Kullanıcı İstatistikleri Sekmesi -->
            <div id="ui-events-admin-tab-wheel-history-users" class="ui-events-admin-tab-panel ui-panel" data-ui-events-wheel-history-panel hidden>
                <div class="ui-surface admin-card ui-events-admin-panel ui-events-admin-table-panel ui-panel">
                    <div class="ui-panel__body card-body ui-events-table-wrap ui-events-admin-table-wrap ui-table-wrap ui-surface">
                        <?php if ($userStats === []): ?>
                            <?php eventsAdminEmptyState('bi-people', 'Kullanıcı Bulunamadı', 'Henüz hiç çark çeviren kullanıcı bulunmuyor.'); ?>
                        <?php else: ?>
                            <table class="ui-events-table">
                                <thead>
                                    <tr>
                                        <th>Kullanıcı</th>
                                        <th>Çark Çevirme Sayısı</th>
                                        <th>Son Çevirme</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userStats as $user): ?>
                                        <tr>
                                            <td>
                                                <strong><?= e($user['username']) ?></strong>
                                                <br><span class="ui-events-list-meta"><?= e($user['email']) ?></span>
                                            </td>
                                            <td>
                                                <span class="ui-events-badge ui-events-badge-success"><?= $user['spin_count'] ?></span>
                                            </td>
                                            <td><?= date('d.m.Y H:i', strtotime($user['last_spin'])) ?></td>
                                            <td>
                                                <form class="ui-events-inline-form" method="post" data-ui-events-confirm="Bu kullanıcının tüm çark kayıtları kalıntı bırakmadan silinecektir." data-ui-events-confirm-title="Kullanıcı kayıtları silinsin mi?" data-ui-events-confirm-ok="Sil" data-ui-events-confirm-tone="danger">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="_events_action" value="delete_user_spins">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger" type="submit" title="Kullanıcı Kayıtlarını Sil">
                                                        <i class="bi bi-trash"></i> Sil
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            </section>
            </div>
        </div>
    </section>

</div>
</div>
