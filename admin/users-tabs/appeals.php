<?php
/**
 * Ban İtirazları Sekmesi
 */
?>

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-body ui-admin-card-compact ui-panel__body ui-card">
        <form method="get" action="users.php" class="ui-admin-filter-row">
            <input type="hidden" name="tab" value="appeals">
            <div class="ui-admin-filter-sm">
                <label class="ui-admin-form-label">Durum</label>
                <select name="appeal_status" class="ui-admin-form-select" data-ui-submit-form>
                    <option value="">Tümü</option>
                    <option value="open" <?= $appealFilter === 'open' ? 'selected' : '' ?>>Açık (<?= $appealStats['open'] ?>)</option>
                    <option value="reviewing" <?= $appealFilter === 'reviewing' ? 'selected' : '' ?>>İnceleniyor (<?= $appealStats['reviewing'] ?>)</option>
                    <option value="accepted" <?= $appealFilter === 'accepted' ? 'selected' : '' ?>>Kabul Edildi (<?= $appealStats['accepted'] ?>)</option>
                    <option value="rejected" <?= $appealFilter === 'rejected' ? 'selected' : '' ?>>Reddedildi (<?= $appealStats['rejected'] ?>)</option>
                </select>
            </div>
            <?php if ($appealFilter): ?>
                <a href="users.php?tab=appeals" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-circle"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($banAppeals)): ?>
    <div class="admin-card ui-panel">
        <div class="card-body ui-admin-empty ui-panel__body ui-empty">
            <div class="ui-admin-empty-icon tone-success ui-empty"><i class="bi bi-inbox"></i></div>
            <h3 class="ui-admin-empty-title ui-empty">Bekleyen itiraz yok 🎉</h3>
            <p class="ui-admin-empty-desc ui-empty">Şu anda inceleme bekleyen ban itirazı bulunmuyor. Yeni bir itiraz geldiğinde burada görünecek.</p>
        </div>
    </div>
<?php else: ?>
    <?php
    $pendingAppealCount = 0;
    foreach ($banAppeals as $a) {
        if (in_array((string) $a['status'], ['open', 'reviewing'], true)) { $pendingAppealCount++; }
    }
    ?>
    <?php if ($pendingAppealCount > 0): ?>
        <form id="bulkAppealsForm" method="post" class="report-bulk-bar ui-admin-mb-md"
              data-admin-confirm="Seçili itirazların tümüne bu işlemi uygulamak istiyor musunuz?"
              data-admin-confirm-tone="warning">
            <input type="hidden" name="_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="bulk_appeal_update">
            <label class="report-bulk-select"><input type="checkbox" id="selectAllAppeals"> Tümünü seç</label>
            <span class="report-bulk-count" id="appealsBulkCount">0 seçili</span>
            <select name="bulk_appeal_status" class="ui-admin-form-select" required>
                <option value="">Durum seç…</option>
                <option value="reviewing">İnceleniyor</option>
                <option value="accepted">Kabul Et (banı kaldırır)</option>
                <option value="rejected">Reddet</option>
            </select>
            <input type="text" name="bulk_admin_note" class="action-input" placeholder="Toplu admin notu (opsiyonel)">
            <button type="submit" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary"><i class="bi bi-check2-all"></i> Uygula</button>
        </form>
    <?php endif; ?>
    <?php foreach ($banAppeals as $appeal):
        $appealId = (int) $appeal['id'];
        $userId = (int) $appeal['user_id'];
        $userName = htmlspecialchars((string) $appeal['user_name']);
        $userEmail = htmlspecialchars((string) $appeal['user_email']);
        $message = htmlspecialchars((string) $appeal['message']);
        $status = (string) $appeal['status'];
        $createdAt = date('d.m.Y H:i', strtotime($appeal['created_at']));
        $adminNote = trim((string) ($appeal['admin_note'] ?? ''));

        $statusBadge = match($status) {
            'open' => '<span class="ui-admin-badge ui-admin-badge-warning">Açık</span>',
            'reviewing' => '<span class="ui-admin-badge ui-admin-badge-primary">İnceleniyor</span>',
            'accepted' => '<span class="ui-admin-badge ui-admin-badge-success">Kabul Edildi</span>',
            'rejected' => '<span class="ui-admin-badge ui-admin-badge-danger">Reddedildi</span>',
            default => '<span class="ui-admin-badge ui-admin-badge-secondary">' . htmlspecialchars($status) . '</span>'
        };
    ?>
    <div class="user-appeal-card ui-card">
        <div class="user-appeal-head ui-panel__head">
            <div class="ui-admin-flex-center-gap">
                <?php if ($status === 'open' || $status === 'reviewing'): ?>
                    <input type="checkbox" class="appeal-row-checkbox" name="bulk_appeal_ids[]" value="<?= $appealId ?>" form="bulkAppealsForm">
                <?php endif; ?>
                <div>
                    <strong><?= $userName ?></strong>
                    <span><?= $userEmail ?> • <?= $createdAt ?></span>
                </div>
            </div>
            <div>
                <?= $statusBadge ?>
            </div>
        </div>
        <div class="user-appeal-body ui-panel__body">
            <div>
                <strong class="ui-admin-label-muted">İtiraz Mesajı:</strong>
                <div class="user-appeal-message"><?= nl2br($message) ?></div>
            </div>

            <?php $appealMessages = usersGetBanAppealMessages($pdo, $appealId); ?>
            <?php if (!empty($appealMessages)): ?>
                <div>
                    <strong class="ui-admin-label-muted">Mesaj Geçmişi:</strong>
                    <div class="user-appeal-thread">
                        <?php foreach ($appealMessages as $msg): ?>
                            <div class="user-appeal-thread-row is-<?= htmlspecialchars((string) $msg['sender_type']) ?>">
                                <span><?= htmlspecialchars(((string) $msg['sender_type'] === 'admin') ? 'Yönetim' : (string)($msg['sender_name'] ?? $userName)) ?> · <?= !empty($msg['created_at']) ? date('d.m.Y H:i', strtotime((string)$msg['created_at'])) : '-' ?></span>
                                <p><?= nl2br(htmlspecialchars((string)$msg['message'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($adminNote): ?>
                <div>
                    <strong class="ui-admin-label-muted">Admin Notu:</strong>
                    <div class="user-appeal-message ui-admin-appeal-message-alt"><?= nl2br(htmlspecialchars($adminNote)) ?></div>
                </div>
            <?php endif; ?>

            <?php if ($status === 'open' || $status === 'reviewing'): ?>
                <form method="post" class="user-appeal-form">
                    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="appeal_update">
                    <input type="hidden" name="appeal_id" value="<?= $appealId ?>">

                    <div>
                        <label class="ui-admin-form-label">Durum</label>
                        <select name="appeal_status" class="ui-admin-form-select" required>
                            <option value="reviewing" <?= $status === 'reviewing' ? 'selected' : '' ?>>İnceleniyor</option>
                            <option value="accepted">Kabul Et</option>
                            <option value="rejected">Reddet</option>
                        </select>
                    </div>

                    <div>
                        <label class="ui-admin-form-label">Admin Notu</label>
                        <textarea name="admin_note" class="ui-admin-form-control" rows="2" placeholder="İtiraz hakkında not..."><?= htmlspecialchars($adminNote) ?></textarea>
                    </div>

                    <div class="user-appeal-actions">
                        <a href="users.php?tab=users&edit=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline">
                            <i class="bi bi-person"></i> Kullanıcıyı Görüntüle
                        </a>
                        <a href="users.php?tab=activity&user_id=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline">
                            <i class="bi bi-person-lines-fill"></i> İzleme
                        </a>
                        <button type="submit" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary">
                            <i class="bi bi-check-circle"></i> Güncelle
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="user-appeal-actions">
                    <a href="users.php?tab=users&edit=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline">
                        <i class="bi bi-person"></i> Kullanıcıyı Görüntüle
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="<?= asset_url('admin/assets/users-appeals-tab.js', $baseUri) ?>" defer></script>
<?php endif; ?>
