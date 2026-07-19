<?php
/**
 * Ban itirazlari sekmesi.
 */

$appealStats = is_array($appealStats ?? null) ? $appealStats : [];
$banAppeals = is_array($banAppeals ?? null) ? $banAppeals : [];
$appealFilter = (string)($appealFilter ?? '');
$selectedAppealId = (int)($selectedAppealId ?? 0);
$selectedBanAppeal = isset($selectedBanAppeal) && is_array($selectedBanAppeal) ? $selectedBanAppeal : null;

$appealTotal = (int)($appealStats['total'] ?? array_sum(array_map('intval', $appealStats)));
$appealStatusMeta = [
    '' => ['label' => 'Tum Itirazlar', 'icon' => 'bi-collection', 'count' => $appealTotal],
    'open' => ['label' => 'Acik', 'icon' => 'bi-hourglass-split', 'count' => (int)($appealStats['open'] ?? 0)],
    'reviewing' => ['label' => 'Inceleniyor', 'icon' => 'bi-search', 'count' => (int)($appealStats['reviewing'] ?? 0)],
    'accepted' => ['label' => 'Kabul Edildi', 'icon' => 'bi-check-circle', 'count' => (int)($appealStats['accepted'] ?? 0)],
    'rejected' => ['label' => 'Reddedildi', 'icon' => 'bi-x-circle', 'count' => (int)($appealStats['rejected'] ?? 0)],
];

$h = static fn (mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$formatDate = static function (mixed $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }
    $time = strtotime($value);

    return $time !== false ? date('d.m.Y H:i', $time) : $value;
};
$statusBadge = static function (string $status) use ($h): string {
    if (function_exists('adminRenderStatusBadge')) {
        return adminRenderStatusBadge($status, 'ban_appeal', [
            'label' => usersBanAppealStatusLabel($status),
            'icon' => '',
        ]);
    }

    $badgeClass = match ($status) {
        'open' => 'ui-admin-badge-warning',
        'reviewing' => 'ui-admin-badge-primary',
        'accepted' => 'ui-admin-badge-success',
        'rejected' => 'ui-admin-badge-danger',
        default => 'ui-admin-badge-secondary',
    };

    return '<span class="ui-admin-badge ' . $badgeClass . '">' . $h(usersBanAppealStatusLabel($status)) . '</span>';
};
$statusUrl = static function (string $status): string {
    $url = 'users.php?tab=moderation&moderation=appeals';
    if ($status !== '') {
        $url .= '&appeal_status=' . rawurlencode($status);
    }

    return $url;
};
$detailUrl = static function (int $appealId) use ($appealFilter): string {
    $url = 'users.php?tab=moderation&moderation=appeals&appeal_id=' . $appealId;
    if ($appealFilter !== '') {
        $url .= '&appeal_status=' . rawurlencode($appealFilter);
    }

    return $url;
};
$backToListUrl = $statusUrl($appealFilter);
$excerpt = static function (mixed $message, int $limit = 220): string {
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$message)) ?? '');
    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $limit - 3)) . '...';
};
?>

<div class="appeals-admin-page">
    <?php
    $appealSummaryCards = [];
    foreach ($appealStatusMeta as $statusKey => $meta) {
        $tone = match ((string) $statusKey) {
            'open', 'reviewing' => 'warning',
            'accepted' => 'success',
            'rejected' => 'danger',
            default => 'info',
        };
        $appealSummaryCards[] = [
            'href' => $statusUrl((string) $statusKey),
            'tone' => $tone,
            'icon' => (string) $meta['icon'],
            'label' => (string) $meta['label'],
            'value' => number_format((int) $meta['count'], 0, ',', '.'),
            'class' => $appealFilter === (string) $statusKey && $selectedAppealId <= 0 ? 'is-active' : '',
        ];
    }
    echo adminRenderStatCards($appealSummaryCards, [
        'class' => 'appeals-admin-summary',
        'aria_label' => 'Ban itiraz ozetleri',
    ]);
    ?>

    <?= adminRenderFilterToolbarOpen('', 'ui-admin-mb-md appeals-admin-toolbar') ?>
            <form method="get" action="users.php" class="appeals-admin-filter admin-filter-form">
                <input type="hidden" name="tab" value="moderation">
                <input type="hidden" name="moderation" value="appeals">
                <div class="ui-admin-filter-sm">
                    <label class="ui-admin-form-label">Durum</label>
                    <select name="appeal_status" class="ui-admin-form-select" data-ui-submit-form>
                        <option value="">Tumu</option>
                        <option value="open" <?= $appealFilter === 'open' ? 'selected' : '' ?>>Acik (<?= (int)($appealStats['open'] ?? 0) ?>)</option>
                        <option value="reviewing" <?= $appealFilter === 'reviewing' ? 'selected' : '' ?>>Inceleniyor (<?= (int)($appealStats['reviewing'] ?? 0) ?>)</option>
                        <option value="accepted" <?= $appealFilter === 'accepted' ? 'selected' : '' ?>>Kabul Edildi (<?= (int)($appealStats['accepted'] ?? 0) ?>)</option>
                        <option value="rejected" <?= $appealFilter === 'rejected' ? 'selected' : '' ?>>Reddedildi (<?= (int)($appealStats['rejected'] ?? 0) ?>)</option>
                    </select>
                </div>
                <div class="appeals-admin-toolbar-actions">
                    <?php if ($appealFilter !== ''): ?>
                        <a href="users.php?tab=moderation&amp;moderation=appeals" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-circle"></i> Temizle</a>
                    <?php endif; ?>
                    <?php if ($selectedAppealId > 0): ?>
                        <a href="<?= $h($backToListUrl) ?>" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-arrow-left"></i> Listeye Don</a>
                    <?php endif; ?>
                </div>
            </form>
    <?= adminRenderFilterToolbarClose() ?>

    <?php if ($selectedAppealId > 0): ?>
        <?php if (!$selectedBanAppeal): ?>
            <?= adminRenderPanel(adminRenderEmptyState([
                        'icon' => 'bi-exclamation-triangle',
                        'tone' => 'warning',
                        'title' => 'Itiraz bulunamadi',
                        'description' => 'Secilen itiraz silinmis veya goruntuleme yetkiniz disinda olabilir.',
                        'actions' => [
                            ['href' => $backToListUrl, 'label' => 'Listeye Don', 'icon' => 'bi-arrow-left', 'class' => 'ui-admin-btn-primary'],
                        ],
                    ]), ['tag' => 'div']) ?>
        <?php else: ?>
            <?php
            $appealId = (int)$selectedBanAppeal['id'];
            $userId = (int)$selectedBanAppeal['user_id'];
            $status = (string)($selectedBanAppeal['status'] ?? 'open');
            $isPending = in_array($status, ['open', 'reviewing'], true);
            $adminNote = trim((string)($selectedBanAppeal['admin_note'] ?? ''));
            $selectedMessages = usersGetBanAppealMessages($pdo, $appealId);
            $reviewedAt = $formatDate($selectedBanAppeal['reviewed_at'] ?? '');
            $updatedAt = $formatDate($selectedBanAppeal['updated_at'] ?? '');
            $reviewerName = trim((string)($selectedBanAppeal['reviewer_name'] ?? ''));
            $userBanReason = trim((string)($selectedBanAppeal['user_ban_reason'] ?? ''));
            ?>
            <section class="appeals-admin-detail ui-panel">
                <header class="appeals-admin-detail-head">
                    <div class="appeals-admin-detail-title">
                        <a href="<?= $h($backToListUrl) ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" title="Listeye don">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <div>
                            <span class="appeals-admin-kicker">Ban itirazi #<?= $appealId ?></span>
                            <h2><?= $h($selectedBanAppeal['user_name'] ?? ('Kullanici #' . $userId)) ?></h2>
                            <p><?= $h($selectedBanAppeal['user_email'] ?? '') ?></p>
                        </div>
                    </div>
                    <div class="appeals-admin-detail-status">
                        <?= $statusBadge($status) ?>
                        <span><i class="bi bi-calendar3"></i> <?= $h($formatDate($selectedBanAppeal['created_at'] ?? '')) ?></span>
                    </div>
                </header>

                <div class="appeals-admin-detail-grid">
                    <div class="appeals-admin-detail-main">
                        <section class="appeals-admin-box">
                            <div class="appeals-admin-box-head">
                                <h3><i class="bi bi-chat-square-text"></i> Itiraz Mesaji</h3>
                                <span><?= $h(usersBanAppealStatusLabel($status)) ?></span>
                            </div>
                            <div class="appeals-admin-message"><?= nl2br($h($selectedBanAppeal['message'] ?? '')) ?></div>
                        </section>

                        <section class="appeals-admin-box">
                            <div class="appeals-admin-box-head">
                                <h3><i class="bi bi-chat-dots"></i> Mesaj Gecmisi</h3>
                                <span><?= count($selectedMessages) ?> kayit</span>
                            </div>
                            <?php if (empty($selectedMessages)): ?>
                                <div class="appeals-admin-empty-inline">Mesaj gecmisi yok.</div>
                            <?php else: ?>
                                <div class="user-appeal-thread appeals-admin-thread">
                                    <?php foreach ($selectedMessages as $msg): ?>
                                        <?php
                                        $senderRole = (string)($msg['sender_role'] ?? $msg['sender_type'] ?? 'user');
                                        $senderName = $senderRole === 'admin'
                                            ? ('Yonetim' . (!empty($msg['sender_name']) ? ' - ' . (string)$msg['sender_name'] : ''))
                                            : (string)($msg['sender_name'] ?? $selectedBanAppeal['user_name'] ?? 'Kullanici');
                                        ?>
                                        <div class="user-appeal-thread-row is-<?= $h($senderRole) ?>">
                                            <span><?= $h($senderName) ?> - <?= $h($formatDate($msg['created_at'] ?? '')) ?></span>
                                            <p><?= nl2br($h($msg['message'] ?? '')) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>

                    <aside class="appeals-admin-detail-side">
                        <section class="appeals-admin-box">
                            <div class="appeals-admin-box-head">
                                <h3><i class="bi bi-sliders"></i> Yonetim Islemleri</h3>
                            </div>
                            <?php if ($isPending): ?>
                                <form method="post" class="user-appeal-reply-form appeals-admin-reply-form">
                                    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="appeal_reply">
                                    <input type="hidden" name="appeal_id" value="<?= $appealId ?>">
                                    <label class="ui-admin-form-label">Yonetici Cevabi</label>
                                    <textarea name="appeal_reply" class="ui-admin-form-control" rows="4" maxlength="3000" required placeholder="Kullaniciya gorunecek cevabi yazin..."></textarea>
                                    <button type="submit" class="ui-admin-btn ui-admin-btn-primary">
                                        <i class="bi bi-reply"></i> Cevap Gonder
                                    </button>
                                </form>

                                <form method="post" class="appeals-admin-decision-form">
                                    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="appeal_update">
                                    <input type="hidden" name="appeal_id" value="<?= $appealId ?>">
                                    <div>
                                        <label class="ui-admin-form-label">Karar</label>
                                        <select name="appeal_status" class="ui-admin-form-select" required>
                                            <option value="reviewing" <?= $status === 'reviewing' ? 'selected' : '' ?>>Inceleniyor</option>
                                            <option value="accepted">Kabul Et</option>
                                            <option value="rejected">Reddet</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="ui-admin-form-label">Yonetici Notu</label>
                                        <textarea name="admin_note" class="ui-admin-form-control" rows="4" placeholder="Karar notu kullanici gecmisinde gorunur..."><?= $h($adminNote) ?></textarea>
                                    </div>
                                    <button type="submit" class="ui-admin-btn ui-admin-btn-success">
                                        <i class="bi bi-check-circle"></i> Karari Kaydet
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="appeals-admin-closed-state">
                                    <i class="bi bi-lock"></i>
                                    <strong>Itiraz kapandi</strong>
                                    <span>Kapali itirazlara yeni cevap eklenemez.</span>
                                </div>
                                <?php if ($adminNote !== ''): ?>
                                    <div class="appeals-admin-message appeals-admin-message-muted"><?= nl2br($h($adminNote)) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </section>

                        <section class="appeals-admin-box">
                            <div class="appeals-admin-box-head">
                                <h3><i class="bi bi-person-badge"></i> Kullanici Ozeti</h3>
                            </div>
                            <dl class="appeals-admin-facts">
                                <div><dt>Kullanici ID</dt><dd>#<?= $userId ?></dd></div>
                                <div><dt>Hesap durumu</dt><dd><?= $h($selectedBanAppeal['user_status'] ?? '-') ?></dd></div>
                                <div><dt>Ban durumu</dt><dd><?= ((int)($selectedBanAppeal['user_is_banned'] ?? 0) === 1) ? 'Banli' : 'Banli degil' ?></dd></div>
                                <div><dt>Son guncelleme</dt><dd><?= $h($updatedAt) ?></dd></div>
                                <div><dt>Inceleyen</dt><dd><?= $h($reviewerName !== '' ? $reviewerName : '-') ?></dd></div>
                                <div><dt>Karar tarihi</dt><dd><?= $h($reviewedAt) ?></dd></div>
                            </dl>
                            <?php if ($userBanReason !== ''): ?>
                                <div class="appeals-admin-ban-reason">
                                    <strong>Ban sebebi</strong>
                                    <p><?= nl2br($h($userBanReason)) ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="appeals-admin-side-actions">
                                <a href="users.php?tab=users&edit=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline">
                                    <i class="bi bi-person"></i> Kullanici
                                </a>
                                <a href="users.php?tab=activity&user_id=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline">
                                    <i class="bi bi-person-lines-fill"></i> Izleme
                                </a>
                            </div>
                        </section>
                    </aside>
                </div>
            </section>
        <?php endif; ?>
    <?php else: ?>
        <?php if (empty($banAppeals)): ?>
            <?= adminRenderPanel(adminRenderEmptyState([
                        'icon' => 'bi-inbox',
                        'tone' => 'success',
                        'title' => 'Bekleyen itiraz yok',
                        'description' => 'Secili filtrede ban itirazi bulunmuyor. Yeni bir itiraz geldiginde burada gorunecek.',
                    ]), ['tag' => 'div']) ?>
        <?php else: ?>
            <?php
            $pendingAppealCount = 0;
            foreach ($banAppeals as $a) {
                if (in_array((string)($a['status'] ?? ''), ['open', 'reviewing'], true)) {
                    $pendingAppealCount++;
                }
            }
            ?>
            <?php if ($pendingAppealCount > 0): ?>
                <form id="bulkAppealsForm" method="post" class="report-bulk-bar appeals-admin-bulk admin-bulk-action-bar"<?= adminConfirmAttrs(['message' => 'Secili itirazlarin tumune bu islemi uygulamak istiyor musunuz?', 'tone' => 'warning']) ?>>
                    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="action" value="bulk_appeal_update">
                    <div class="appeals-admin-bulk-selection">
                        <label class="report-bulk-select appeals-admin-bulk-check">
                            <input type="checkbox" id="selectAllAppeals">
                            <span>Tumunu sec</span>
                        </label>
                        <span class="report-bulk-count appeals-admin-bulk-count" id="appealsBulkCount">0 secili</span>
                    </div>
                    <div class="appeals-admin-bulk-field appeals-admin-bulk-status">
                        <label for="bulkAppealStatus">Karar</label>
                        <select id="bulkAppealStatus" name="bulk_appeal_status" class="ui-admin-form-select" required>
                            <option value="">Durum sec...</option>
                            <option value="reviewing">Inceleniyor</option>
                            <option value="accepted">Kabul Et (bani kaldirir)</option>
                            <option value="rejected">Reddet</option>
                        </select>
                    </div>
                    <div class="appeals-admin-bulk-field appeals-admin-bulk-note">
                        <label for="bulkAppealNote">Not</label>
                        <input id="bulkAppealNote" type="text" name="bulk_admin_note" class="action-input" placeholder="Toplu admin notu (opsiyonel)">
                    </div>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary appeals-admin-bulk-submit">
                        <i class="bi bi-check2-all"></i> Uygula
                    </button>
                </form>
            <?php endif; ?>

            <div class="appeals-admin-list">
                <?php foreach ($banAppeals as $appeal): ?>
                    <?php
                    $appealId = (int)($appeal['id'] ?? 0);
                    $userId = (int)($appeal['user_id'] ?? 0);
                    $status = (string)($appeal['status'] ?? 'open');
                    $isPending = in_array($status, ['open', 'reviewing'], true);
                    ?>
                    <article class="appeals-admin-row is-<?= $h($status) ?>">
                        <div class="appeals-admin-row-check">
                            <?php if ($isPending): ?>
                                <input type="checkbox" class="appeal-row-checkbox" name="bulk_appeal_ids[]" value="<?= $appealId ?>" form="bulkAppealsForm" aria-label="Itiraz #<?= $appealId ?> sec">
                            <?php endif; ?>
                        </div>
                        <div class="appeals-admin-row-main">
                            <div class="appeals-admin-row-top">
                                <div class="appeals-admin-user">
                                    <strong><?= $h($appeal['user_name'] ?? ('Kullanici #' . $userId)) ?></strong>
                                    <span><?= $h($appeal['user_email'] ?? '') ?></span>
                                </div>
                                <?= $statusBadge($status) ?>
                            </div>
                            <p><?= $h($excerpt($appeal['message'] ?? '')) ?></p>
                            <div class="appeals-admin-row-meta">
                                <span>#<?= $appealId ?></span>
                                <span><i class="bi bi-calendar3"></i> <?= $h($formatDate($appeal['created_at'] ?? '')) ?></span>
                                <?php if (!empty($appeal['updated_at'])): ?>
                                    <span><i class="bi bi-arrow-repeat"></i> <?= $h($formatDate($appeal['updated_at'])) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="appeals-admin-row-actions">
                            <a href="<?= $h($detailUrl($appealId)) ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary">
                                <i class="bi bi-layout-text-sidebar-reverse"></i> Detay
                            </a>
                            <a href="users.php?tab=users&edit=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" title="Kullaniciyi goruntule">
                                <i class="bi bi-person"></i>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <script src="<?= asset_url('admin/assets/users-appeals-tab.js', $baseUri) ?>" defer></script>
    <?php endif; ?>
</div>
