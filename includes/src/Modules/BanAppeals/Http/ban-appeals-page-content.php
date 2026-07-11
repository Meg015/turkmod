<?php

declare(strict_types=1);

requireAuth();

$banAppealsUrl = routePublicStaticUrl('ban_appeals');
$logoutUrl = routePublicStaticUrl('logout');

$userId = (int)($_SESSION['_auth_user_id'] ?? 0);
$restriction = $pdo ? usersGetAccessRestriction($pdo, $userId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: ' . $banAppealsUrl);
        exit;
    }

    $message = (string)($_POST['message'] ?? '');
    $err = $pdo ? usersSubmitBanAppeal($pdo, $userId, $message) : 'Veritabanı bağlantısı yok.';
    flash($err ? 'error' : 'success', $err ?: 'İtiraz kaydınız alındı. Yönetici incelemesini buradan takip edebilirsiniz.');
    header('Location: ' . $banAppealsUrl);
    exit;
}

$appeals = $pdo ? usersGetBanAppealsForUser($pdo, $userId) : [];
$hasActiveAppeal = false;
foreach ($appeals as $appeal) {
    if (in_array((string)($appeal['status'] ?? ''), ['open', 'reviewing'], true)) {
        $hasActiveAppeal = true;
        break;
    }
}
$successMsg = get_flash('success');
$errorMsg = get_flash('error');
if ($restriction && $errorMsg === 'Bu sayfaya erişim yetkiniz yok') {
    $errorMsg = null;
}
$pageTitle = 'Ban İtirazlarım';
$appeal_success = (string) ($successMsg ?? '');
$appeal_error = (string) ($errorMsg ?? '');
$appeal_restriction_message = $restriction
    ? (string) ($restriction['message'] ?? '')
    : 'Hesabinizda aktif bir kisitlama gorunmuyor. Onceki itirazlarinizi yine de takip edebilirsiniz.';
$appeal_has_active = $hasActiveAppeal;
$appeal_csrf_token = csrf_token();
$appeal_items = [];
$statusIcons = [
    'open' => 'bi-hourglass-split',
    'reviewing' => 'bi-hourglass-split',
    'accepted' => 'bi-check-circle',
    'rejected' => 'bi-x-circle',
];
foreach ($appeals as $appeal) {
    if (!is_array($appeal)) {
        continue;
    }
    $status = (string) ($appeal['status'] ?? 'open');
    $appealMessages = $pdo ? usersGetBanAppealMessages($pdo, (int) ($appeal['id'] ?? 0)) : [];
    $thread = [];
    foreach ($appealMessages as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $thread[] = [
            'sender' => (string) ($msg['sender_role'] ?? '') === 'admin' ? 'Yonetim' : 'Siz',
            'date' => !empty($msg['created_at']) ? date('d.m.Y H:i', strtotime((string) $msg['created_at'])) : '-',
            'message' => (string) ($msg['message'] ?? ''),
        ];
    }
    $appeal_items[] = [
        'id' => (string) (int) ($appeal['id'] ?? 0),
        'created_at' => !empty($appeal['created_at']) ? date('d.m.Y H:i', strtotime((string) $appeal['created_at'])) : '-',
        'status' => $status,
        'status_icon' => $statusIcons[$status] ?? 'bi-question-circle',
        'status_label' => usersBanAppealStatusLabel($status),
        'message' => (string) ($appeal['message'] ?? ''),
        'has_thread' => $thread !== [],
        'thread' => $thread,
        'has_admin_note' => !empty($appeal['admin_note']),
        'admin_note' => (string) ($appeal['admin_note'] ?? ''),
    ];
}
$appeal_has_items = $appeal_items !== [];
require_once $projectRoot . '/includes/public-header.php';
if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer()) {
    require_once $projectRoot . '/includes/public-footer.php';
    return;
}
?>

<main class="appeal-shell ui-section">
    <?php if ($successMsg): ?>
    <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success">
        <i class="bi bi-check-circle-fill"></i>
        <div><?= htmlspecialchars((string)$successMsg) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
    <div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><?= htmlspecialchars((string)$errorMsg) ?></div>
    </div>
    <?php endif; ?>

    <section class="appeal-hero">
        <h1><i class="bi bi-shield-exclamation appeal-icon-muted"></i>Ban İtirazlarım</h1>
        <p><?= $restriction ? htmlspecialchars((string)$restriction['message']) : 'Hesabınızda aktif bir kısıtlama görünmüyor. Önceki itirazlarınızı yine de takip edebilirsiniz.' ?></p>
    </section>

    <section class="appeal-card appeal-form appeal-form-card">
        <h2><i class="bi bi-pencil-square appeal-icon"></i>Ban İtirazı Gönder</h2>
        <p>Ban kararının hatalı olduğunu düşünüyorsanız, lütfen aşağıdaki formu doldurarak itirazınızı gönderiniz. Yöneticiler tarafından incelenecektir.</p>
        <?php if ($hasActiveAppeal): ?>
            <div class="ui-admin-alert ui-admin-alert-warning ui-alert ui-alert--warning">
                <i class="bi bi-info-circle"></i>
                <div>Açık veya incelenen bir itirazınız var. Yeni bir mesaj daha gönderebilirsiniz; tüm kayıtlar yönetici paneline düşer.</div>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars($banAppealsUrl, ENT_QUOTES, 'UTF-8') ?>" class="appeal-form">
            <?= csrf_field() ?>
            <label class="form-label appeal-label" for="message">İtiraz Metni <span class="appeal-required">*</span></label>
            <textarea id="message" name="message" class="ui-admin-form-control" maxlength="3000" required placeholder="Ban kararına neden itiraz ettiğini açık ve net şekilde yaz..."></textarea>
            <div class="appeal-help">En az 10, en fazla 3000 karakter olmalıdır.</div>
            <div class="appeal-actions">
                <button type="submit" class="appeal-submit"><i class="bi bi-send appeal-icon"></i>İtirazı Gönder</button>
            </div>
        </form>
        <form method="post" action="<?= htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') ?>" class="appeal-logout-form">
            <?= csrf_field() ?>
            <button type="submit" class="appeal-logout"><i class="bi bi-box-arrow-right appeal-icon"></i>Çıkış Yap</button>
        </form>
    </section>

    <section class="appeal-card">
        <h2><i class="bi bi-clock-history appeal-icon"></i>İtiraz Geçmişi</h2>
        <div class="appeal-list">
            <?php if (empty($appeals)): ?>
                <div class="appeal-empty">
                    <i class="bi bi-inbox"></i>
                    <p>Henüz itiraz kaydı yok.</p>
                </div>
            <?php endif; ?>
            <?php foreach ($appeals as $appeal): ?>
                <?php $status = (string)($appeal['status'] ?? 'open'); ?>
                <article class="appeal-item">
                    <div class="appeal-meta">
                        <div class="appeal-meta-left">
                            <span class="appeal-id">#<?= (int)$appeal['id'] ?></span>
                            <span><i class="bi bi-calendar3 appeal-icon-tight"></i><?= !empty($appeal['created_at']) ? date('d.m.Y H:i', strtotime((string)$appeal['created_at'])) : '-' ?></span>
                        </div>
                        <span class="appeal-status <?= htmlspecialchars($status) ?>">
                            <?php
                            $statusIcons = [
                                'open' => 'bi-hourglass-split',
                                'reviewing' => 'bi-hourglass-split',
                                'accepted' => 'bi-check-circle',
                                'rejected' => 'bi-x-circle',
                            ];
                            $icon = $statusIcons[$status] ?? 'bi-question-circle';
                            ?>
                            <i class="bi <?= $icon ?>"></i>
                            <?= htmlspecialchars(usersBanAppealStatusLabel($status)) ?>
                        </span>
                    </div>
                    <div class="appeal-message"><?= nl2br(htmlspecialchars((string)$appeal['message'])) ?></div>
                    <?php $appealMessages = usersGetBanAppealMessages($pdo, (int)$appeal['id']); ?>
                    <?php if (!empty($appealMessages)): ?>
                        <div class="appeal-admin-note">
                            <strong><i class="bi bi-chat-dots appeal-icon-tight"></i>Mesaj Geçmişi:</strong><br>
                            <?php foreach ($appealMessages as $msg): ?>
                                <div class="appeal-thread-line">
                                    <span><?= htmlspecialchars(((string)$msg['sender_role'] === 'admin') ? 'Yönetim' : 'Siz') ?> · <?= !empty($msg['created_at']) ? date('d.m.Y H:i', strtotime((string)$msg['created_at'])) : '-' ?></span>
                                    <p><?= nl2br(htmlspecialchars((string)$msg['message'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($appeal['admin_note'])): ?>
                        <div class="appeal-admin-note">
                            <strong><i class="bi bi-chat-left-text appeal-icon-tight"></i>Yönetici Notu:</strong><br>
                            <?= nl2br(htmlspecialchars((string)$appeal['admin_note'])) ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>

