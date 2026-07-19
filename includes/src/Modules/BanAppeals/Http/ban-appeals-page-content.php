<?php

declare(strict_types=1);

requireAuth();

$banAppealsUrl = routePublicStaticUrl('ban_appeals');
$logoutUrl = routePublicStaticUrl('logout');
$appealTabUrl = static function (string $tab) use ($banAppealsUrl): string {
    return $banAppealsUrl . '?tab=' . rawurlencode($tab);
};
$appealOverviewUrl = $appealTabUrl('overview');
$appealNewUrl = $appealTabUrl('new');
$appealHistoryUrl = $appealTabUrl('history');

$userId = (int)($_SESSION['_auth_user_id'] ?? 0);
$restriction = $pdo ? usersGetAccessRestriction($pdo, $userId) : null;
$canSubmitAppeal = $restriction !== null;
$requestedAppealTab = (string)($_GET['tab'] ?? 'overview');
$appealTab = in_array($requestedAppealTab, ['overview', 'new', 'history'], true) ? $requestedAppealTab : 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: ' . $appealNewUrl);
        exit;
    }

    if (!$canSubmitAppeal) {
        flash('error', 'Aktif ban veya kısıtlama bulunmadığı için itiraz gönderilemez.');
        header('Location: ' . $appealOverviewUrl);
        exit;
    }

    $message = (string)($_POST['message'] ?? '');
    $replyingToActiveAppeal = $pdo ? usersGetActiveBanAppealId($pdo, $userId) !== null : false;
    $err = $pdo ? usersSubmitBanAppeal($pdo, $userId, $message) : 'Veritabanı bağlantısı yok.';
    $successMessage = $replyingToActiveAppeal
        ? 'Mesajiniz mevcut acik itiraza eklendi. Yonetici incelemesini Itiraz Gecmisi sekmesinden takip edebilirsiniz.'
        : 'Itiraz kaydiniz alindi. Yonetici incelemesini Itiraz Gecmisi sekmesinden takip edebilirsiniz.';
    flash($err ? 'error' : 'success', $err ?: $successMessage);
    header('Location: ' . ($err ? $appealNewUrl : $appealHistoryUrl));
    exit;
}

$appeals = $pdo ? usersGetBanAppealsForUser($pdo, $userId) : [];
$hasActiveAppeal = false;
$activeAppealId = 0;
foreach ($appeals as $appeal) {
    if (in_array((string)($appeal['status'] ?? ''), ['open', 'reviewing'], true)) {
        $hasActiveAppeal = true;
        $activeAppealId = (int)($appeal['id'] ?? 0);
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
    : 'Hesabinizda aktif bir kisitlama gorunmuyor.';
$formatAppealDate = static function (mixed $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $time = strtotime($value);

    return $time !== false ? date('d.m.Y H:i', $time) : $value;
};
$appeal_has_restriction_details = $restriction !== null;
$appeal_restriction_title = $restriction ? (string) ($restriction['title'] ?? 'Hesap kisitlamasi') : '';
$appeal_restriction_type_label = $restriction ? (string) ($restriction['restriction_label'] ?? $restriction['type'] ?? 'Kisitlama') : '';
$appeal_restriction_status_label = $restriction ? (string) ($restriction['status_label'] ?? $appeal_restriction_title) : '';
$appeal_restriction_reason = $restriction ? trim((string) ($restriction['reason'] ?? '')) : '';
if ($appeal_restriction_reason === '') {
    $appeal_restriction_reason = $restriction ? 'Sebep belirtilmedi.' : '';
}
$appeal_restriction_started_at = $restriction ? $formatAppealDate($restriction['started_at'] ?? '') : '';
$appeal_restriction_ends_at = $restriction ? $formatAppealDate($restriction['ends_at'] ?? '') : '';
if ($appeal_restriction_started_at === '') {
    $appeal_restriction_started_at = '-';
}
if ($appeal_restriction_ends_at === '') {
    $appeal_restriction_ends_at = !empty($restriction['is_permanent']) ? 'Suresiz' : '-';
}
$appeal_restriction_details = [];
if ($appeal_has_restriction_details) {
    $appeal_restriction_details = [
        ['icon' => 'bi-activity', 'label' => 'Durum', 'value' => $appeal_restriction_status_label],
        ['icon' => 'bi-diagram-3', 'label' => 'Kapsam', 'value' => $appeal_restriction_type_label],
        ['icon' => 'bi-calendar-event', 'label' => 'Baslangic', 'value' => $appeal_restriction_started_at],
        ['icon' => 'bi-calendar-x', 'label' => 'Bitis', 'value' => $appeal_restriction_ends_at],
    ];
}
$appeal_tab = $appealTab;
$appeal_tab_overview = $appealTab === 'overview';
$appeal_tab_new = $appealTab === 'new';
$appeal_tab_history = $appealTab === 'history';
$appeal_can_submit = $canSubmitAppeal;
$appeal_has_active = $hasActiveAppeal;
$appeal_active_id = (string) $activeAppealId;
$appeal_form_title = $hasActiveAppeal ? 'Mevcut Itiraza Mesaj Ekle' : 'Ban Itirazi Gonder';
$appeal_form_description = $hasActiveAppeal
    ? 'Acik veya incelenen itiraziniz oldugu icin yeni itiraz acilmaz. Yazdiginiz metin mevcut itiraz gecmisine eklenir.'
    : 'Ban kararinin hatali oldugunu dusunuyorsaniz, lutfen asagidaki formu doldurarak itirazinizi gonderiniz. Yoneticiler tarafindan incelenecektir.';
$appeal_submit_label = $hasActiveAppeal ? 'Mesaji Ekle' : 'Itirazi Gonder';
$appeal_message_label = $hasActiveAppeal ? 'Yeni Mesaj' : 'Itiraz Metni';
$appeal_message_placeholder = $hasActiveAppeal
    ? 'Mevcut itiraziniza eklemek istediginiz yeni aciklamayi yazin...'
    : 'Ban kararina neden itiraz ettigini acik ve net sekilde yaz...';
$appeal_csrf_token = csrf_token();
$appeal_action_url = $banAppealsUrl;
$appeal_logout_url = $logoutUrl;
$appeal_overview_url = $appealOverviewUrl;
$appeal_new_url = $appealNewUrl;
$appeal_history_url = $appealHistoryUrl;
$appeal_overview_tab_class = 'appeal-tab' . ($appeal_tab_overview ? ' is-active' : '');
$appeal_new_tab_class = 'appeal-tab' . ($appeal_tab_new ? ' is-active' : '');
$appeal_history_tab_class = 'appeal-tab' . ($appeal_tab_history ? ' is-active' : '');
$appeal_items = [];
$statusIcons = [
    'open' => 'bi-hourglass-split',
    'reviewing' => 'bi-hourglass-split',
    'accepted' => 'bi-check-circle',
    'rejected' => 'bi-x-circle',
];
$appealStatusSummaries = [
    'open' => [
        'class' => 'is-open',
        'icon' => 'bi-hourglass-split',
        'title' => 'Yonetim incelemesi bekleniyor',
        'text' => 'Itiraziniz kayda alindi. Yonetici ekibi sirasi geldiginde inceleyecek.',
        'date_label' => 'Gonderildi',
    ],
    'reviewing' => [
        'class' => 'is-reviewing',
        'icon' => 'bi-search',
        'title' => 'Yonetim incelemesinde',
        'text' => 'Itiraziniz aktif olarak inceleniyor. Ek cevap gelirse mesaj gecmisinde gorunur.',
        'date_label' => 'Son guncelleme',
    ],
    'accepted' => [
        'class' => 'is-accepted',
        'icon' => 'bi-check-circle',
        'title' => 'Itiraz kabul edildi',
        'text' => 'Yonetim itirazinizi kabul etti ve ban karari kaldirildi veya kaldirilmak uzere isleme alindi.',
        'date_label' => 'Karar tarihi',
    ],
    'rejected' => [
        'class' => 'is-rejected',
        'icon' => 'bi-x-circle',
        'title' => 'Itiraz reddedildi',
        'text' => 'Yonetim itirazinizi inceledi ve ban kararini korudu.',
        'date_label' => 'Karar tarihi',
    ],
];
$buildAppealTimeline = static function (array $appeal, string $status) use ($formatAppealDate): array {
    $createdAt = $formatAppealDate($appeal['created_at'] ?? '');
    $reviewedAt = $formatAppealDate($appeal['reviewed_at'] ?? '');
    $updatedAt = $formatAppealDate($appeal['updated_at'] ?? '');
    $decisionAt = $reviewedAt !== '' ? $reviewedAt : $updatedAt;
    $isClosed = in_array($status, ['accepted', 'rejected'], true);

    return [
        [
            'icon' => 'bi-send-check',
            'label' => 'Gonderildi',
            'text' => $createdAt !== '' ? $createdAt : 'Kayit alindi',
            'class' => 'is-complete',
        ],
        [
            'icon' => 'bi-search',
            'label' => 'Inceleniyor',
            'text' => $status === 'open' ? 'Yonetici incelemesi bekleniyor' : 'Yonetici incelemesinde',
            'class' => $status === 'open' ? 'is-muted' : ($status === 'reviewing' ? 'is-current' : 'is-complete'),
        ],
        [
            'icon' => $status === 'accepted' ? 'bi-check-circle' : ($status === 'rejected' ? 'bi-x-circle' : 'bi-flag'),
            'label' => $status === 'accepted' ? 'Kabul Edildi' : ($status === 'rejected' ? 'Reddedildi' : 'Karar'),
            'text' => $isClosed
                ? ($decisionAt !== '' ? $decisionAt : 'Karar verildi')
                : 'Karar bekleniyor',
            'class' => $isClosed
                ? ('is-complete ' . ($status === 'accepted' ? 'is-accepted' : 'is-rejected'))
                : 'is-muted',
        ],
    ];
};
$latestAppeal = isset($appeals[0]) && is_array($appeals[0]) ? $appeals[0] : null;
$latestAppealId = $latestAppeal ? (int) ($latestAppeal['id'] ?? 0) : 0;
$appeal_result_configs = [
    'open' => [
        'class' => 'is-open',
        'icon' => 'bi-hourglass-split',
        'title' => 'Itiraziniz alindi',
        'text' => 'Yonetim ekibi itirazinizi inceleme sirasina aldi. Yeni bir gelisme oldugunda bu sayfada gorunur.',
        'date_label' => 'Gonderim tarihi',
    ],
    'reviewing' => [
        'class' => 'is-reviewing',
        'icon' => 'bi-search',
        'title' => 'Itiraziniz inceleniyor',
        'text' => 'Yonetim ekibi itiraz uzerinde calisiyor. Gerekirse mesaj gecmisinden ek cevap gorebilirsiniz.',
        'date_label' => 'Son guncelleme',
    ],
    'accepted' => [
        'class' => 'is-accepted',
        'icon' => 'bi-check-circle',
        'title' => 'Itiraziniz kabul edildi',
        'text' => 'Ban karari kaldirildi veya kaldirilmak uzere isleme alindi. Hesabinizi normal sekilde kullanabilirsiniz.',
        'date_label' => 'Karar tarihi',
    ],
    'rejected' => [
        'class' => 'is-rejected',
        'icon' => 'bi-x-circle',
        'title' => 'Itiraziniz reddedildi',
        'text' => 'Yonetim ekibi itirazi degerlendirdi ve ban kararini korudu. Detay varsa yonetici notunda gorunur.',
        'date_label' => 'Karar tarihi',
    ],
];
$appeal_has_result_card = $latestAppeal !== null;
$appeal_result_status = $latestAppeal ? (string) ($latestAppeal['status'] ?? 'open') : '';
$appeal_result_config = $appeal_result_configs[$appeal_result_status] ?? $appeal_result_configs['open'];
$appeal_result_class = (string) ($appeal_result_config['class'] ?? 'is-open');
$appeal_result_icon = (string) ($appeal_result_config['icon'] ?? 'bi-hourglass-split');
$appeal_result_title = (string) ($appeal_result_config['title'] ?? 'Itiraz durumu');
$appeal_result_text = (string) ($appeal_result_config['text'] ?? '');
$appeal_result_status_label = $appeal_result_status !== '' ? usersBanAppealStatusLabel($appeal_result_status) : '';
$appeal_result_date_label = (string) ($appeal_result_config['date_label'] ?? 'Tarih');
$appeal_result_date = '';
$appeal_result_has_admin_note = $latestAppeal !== null && trim((string) ($latestAppeal['admin_note'] ?? '')) !== '';
$appeal_result_admin_note = $latestAppeal ? (string) ($latestAppeal['admin_note'] ?? '') : '';
$appeal_result_has_admin_reply = false;
$appeal_result_admin_reply = '';
$appeal_result_admin_reply_date = '';
$appeal_result_history_url = $appealHistoryUrl;
if ($latestAppeal !== null) {
    $statusDateSource = in_array($appeal_result_status, ['accepted', 'rejected'], true)
        ? (($latestAppeal['reviewed_at'] ?? '') ?: ($latestAppeal['updated_at'] ?? ''))
        : (($latestAppeal['updated_at'] ?? '') ?: ($latestAppeal['created_at'] ?? ''));
    $appeal_result_date = $formatAppealDate($statusDateSource);
    if ($appeal_result_date === '') {
        $appeal_result_date = '-';
    }
}
foreach ($appeals as $appeal) {
    if (!is_array($appeal)) {
        continue;
    }
    $status = (string) ($appeal['status'] ?? 'open');
    $appealId = (int) ($appeal['id'] ?? 0);
    $summary = $appealStatusSummaries[$status] ?? $appealStatusSummaries['open'];
    $summaryDateSource = in_array($status, ['accepted', 'rejected'], true)
        ? (($appeal['reviewed_at'] ?? '') ?: ($appeal['updated_at'] ?? ''))
        : (($appeal['updated_at'] ?? '') ?: ($appeal['created_at'] ?? ''));
    $summaryDate = $formatAppealDate($summaryDateSource);
    $appealMessages = $pdo ? usersGetBanAppealMessages($pdo, (int) ($appeal['id'] ?? 0)) : [];
    $thread = [];
    foreach ($appealMessages as $msg) {
        if (!is_array($msg)) {
            continue;
        }
        $senderRole = (string) ($msg['sender_role'] ?? $msg['sender_type'] ?? 'user');
        $thread[] = [
            'sender' => $senderRole === 'admin' ? 'Yonetim' : 'Siz',
            'date' => !empty($msg['created_at']) ? date('d.m.Y H:i', strtotime((string) $msg['created_at'])) : '-',
            'message' => (string) ($msg['message'] ?? ''),
        ];
        if ($appealId === $latestAppealId && $senderRole === 'admin') {
            $appeal_result_has_admin_reply = true;
            $appeal_result_admin_reply = (string) ($msg['message'] ?? '');
            $appeal_result_admin_reply_date = !empty($msg['created_at']) ? date('d.m.Y H:i', strtotime((string) $msg['created_at'])) : '-';
        }
    }
    $appeal_items[] = [
        'id' => (string) $appealId,
        'created_at' => !empty($appeal['created_at']) ? date('d.m.Y H:i', strtotime((string) $appeal['created_at'])) : '-',
        'status' => $status,
        'status_icon' => $statusIcons[$status] ?? 'bi-question-circle',
        'status_label' => usersBanAppealStatusLabel($status),
        'status_summary_class' => (string) ($summary['class'] ?? 'is-open'),
        'status_summary_icon' => (string) ($summary['icon'] ?? 'bi-hourglass-split'),
        'status_summary_title' => (string) ($summary['title'] ?? usersBanAppealStatusLabel($status)),
        'status_summary_text' => (string) ($summary['text'] ?? ''),
        'status_summary_date_label' => (string) ($summary['date_label'] ?? 'Tarih'),
        'status_summary_date' => $summaryDate !== '' ? $summaryDate : '-',
        'message' => (string) ($appeal['message'] ?? ''),
        'has_timeline' => false,
        'timeline' => $buildAppealTimeline($appeal, $status),
        'has_thread' => $thread !== [],
        'thread' => $thread,
        'has_admin_note' => !empty($appeal['admin_note']),
        'admin_note' => (string) ($appeal['admin_note'] ?? ''),
    ];
}
$appeal_has_items = $appeal_items !== [];
$publicHeaderVars = isset($publicHeaderVars) && is_array($publicHeaderVars) ? $publicHeaderVars : [];
foreach (get_defined_vars() as $key => $value) {
    if (str_starts_with((string) $key, 'appeal_')) {
        $publicHeaderVars[(string) $key] = $value;
    }
}
require_once $projectRoot . '/includes/public-header.php';
if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer()) {
    require_once $projectRoot . '/includes/public-footer.php';
    return;
}
?>

<main class="appeal-shell ui-section">
    <?= uiRenderFlashAlerts($successMsg, $errorMsg) ?>

    <nav class="appeal-tabs" aria-label="Ban itiraz sekmeleri">
        <a href="<?= htmlspecialchars($appealOverviewUrl, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($appeal_overview_tab_class, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-shield-exclamation" aria-hidden="true"></i> Ban Itirazlarim</a>
        <a href="<?= htmlspecialchars($appealNewUrl, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($appeal_new_tab_class, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil-square" aria-hidden="true"></i> Ban Itirazi Gonder</a>
        <a href="<?= htmlspecialchars($appealHistoryUrl, ENT_QUOTES, 'UTF-8') ?>" class="<?= htmlspecialchars($appeal_history_tab_class, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-clock-history" aria-hidden="true"></i> Itiraz Gecmisi</a>
    </nav>

    <?php if ($appealTab === 'overview'): ?>
    <?php if ($appeal_has_restriction_details): ?>
    <section class="appeal-card appeal-ban-summary" aria-label="Ban detaylari">
        <div class="appeal-ban-summary-head">
            <span class="appeal-ban-kicker"><i class="bi bi-shield-lock" aria-hidden="true"></i> Ban Detaylari</span>
            <h2><?= htmlspecialchars($appeal_restriction_title, ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= htmlspecialchars($appeal_restriction_reason, ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="appeal-ban-grid">
            <?php foreach ($appeal_restriction_details as $detail): ?>
            <div class="appeal-ban-detail">
                <span><i class="bi <?= htmlspecialchars((string)($detail['icon'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i><?= htmlspecialchars((string)($detail['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                <strong><?= htmlspecialchars((string)($detail['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($appeal_has_result_card): ?>
    <section class="appeal-card appeal-result-card <?= htmlspecialchars($appeal_result_class, ENT_QUOTES, 'UTF-8') ?>" aria-label="Itiraz son durumu">
        <div class="appeal-result-main">
            <span class="appeal-result-icon"><i class="bi <?= htmlspecialchars($appeal_result_icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
            <div>
                <span class="appeal-result-kicker">Son Durum</span>
                <h2><?= htmlspecialchars($appeal_result_title, ENT_QUOTES, 'UTF-8') ?></h2>
                <p><?= htmlspecialchars($appeal_result_text, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="appeal-result-side">
            <span class="appeal-status <?= htmlspecialchars($appeal_result_status, ENT_QUOTES, 'UTF-8') ?>">
                <i class="bi <?= htmlspecialchars($appeal_result_icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                <?= htmlspecialchars($appeal_result_status_label, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <small><?= htmlspecialchars($appeal_result_date_label, ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($appeal_result_date, ENT_QUOTES, 'UTF-8') ?></small>
        </div>
        <?php if ($appeal_result_has_admin_reply || $appeal_result_has_admin_note): ?>
        <div class="appeal-result-note">
            <?php if ($appeal_result_has_admin_reply): ?>
            <strong><i class="bi bi-reply" aria-hidden="true"></i> Son yonetici cevabi</strong>
            <p><?= nl2br(htmlspecialchars($appeal_result_admin_reply, ENT_QUOTES, 'UTF-8')) ?></p>
            <small><?= htmlspecialchars($appeal_result_admin_reply_date, ENT_QUOTES, 'UTF-8') ?></small>
            <?php elseif ($appeal_result_has_admin_note): ?>
            <strong><i class="bi bi-chat-left-text" aria-hidden="true"></i> Yonetici notu</strong>
            <p><?= nl2br(htmlspecialchars($appeal_result_admin_note, ENT_QUOTES, 'UTF-8')) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <a class="appeal-result-link" href="<?= htmlspecialchars($appeal_result_history_url, ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-clock-history" aria-hidden="true"></i> Gecmisi gor
        </a>
    </section>
    <?php endif; ?>

    <section class="appeal-hero">
        <h1><i class="bi bi-shield-exclamation appeal-icon-muted"></i>Ban İtirazlarım</h1>
        <p><?= htmlspecialchars($appeal_restriction_message, ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <?php endif; ?>

    <?php if ($appealTab === 'new'): ?>
    <section class="appeal-card appeal-form appeal-form-card">
        <h2><i class="bi bi-pencil-square appeal-icon"></i><?= htmlspecialchars($appeal_form_title, ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($appeal_form_description, ENT_QUOTES, 'UTF-8') ?></p>
        <?php if (false): ?>
        <h2><i class="bi bi-pencil-square appeal-icon"></i>Ban İtirazı Gönder</h2>
        <p>Ban kararının hatalı olduğunu düşünüyorsanız, lütfen aşağıdaki formu doldurarak itirazınızı gönderiniz. Yöneticiler tarafından incelenecektir.</p>
        <?php endif; ?>
        <?php if (!$canSubmitAppeal): ?>
            <?= uiRenderAlert('Hesabinizda aktif ban veya kisitlama gorunmedigi icin itiraz formu su anda kullanilamaz.', 'warning', ['icon' => 'bi-info-circle']) ?>
        <?php else: ?>
        <?php if ($hasActiveAppeal): ?>
            <?= uiRenderAlert('Açık veya incelenen bir itirazınız var. Yeni bir mesaj daha gönderebilirsiniz; tüm kayıtlar yönetici paneline düşer.', 'warning', ['icon' => 'bi-info-circle']) ?>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars($appeal_action_url, ENT_QUOTES, 'UTF-8') ?>" class="appeal-form">
            <?= csrf_field() ?>
            <label class="form-label appeal-label" for="message"><?= htmlspecialchars($appeal_message_label, ENT_QUOTES, 'UTF-8') ?> <span class="appeal-required">*</span></label>
            <textarea id="message" name="message" class="ui-admin-form-control" maxlength="3000" required placeholder="<?= htmlspecialchars($appeal_message_placeholder, ENT_QUOTES, 'UTF-8') ?>"></textarea>
            <div class="appeal-help">En az 10, en fazla 3000 karakter olmalidir.</div>
            <?php if (false): ?>
            <label class="form-label appeal-label" for="message">İtiraz Metni <span class="appeal-required">*</span></label>
            <textarea id="message" name="message" class="ui-admin-form-control" maxlength="3000" required placeholder="Ban kararına neden itiraz ettiğini açık ve net şekilde yaz..."></textarea>
            <div class="appeal-help">En az 10, en fazla 3000 karakter olmalıdır.</div>
            <?php endif; ?>
            <div class="appeal-actions">
                <button type="submit" class="appeal-submit"><i class="bi bi-send appeal-icon"></i><?= htmlspecialchars($appeal_submit_label, ENT_QUOTES, 'UTF-8') ?></button>
                <?php if (false): ?>
                <button type="submit" class="appeal-submit"><i class="bi bi-send appeal-icon"></i>İtirazı Gönder</button>
                <?php endif; ?>
            </div>
        </form>
        <form method="post" action="<?= htmlspecialchars($appeal_logout_url, ENT_QUOTES, 'UTF-8') ?>" class="appeal-logout-form">
            <?= csrf_field() ?>
            <button type="submit" class="appeal-logout"><i class="bi bi-box-arrow-right appeal-icon"></i>Çıkış Yap</button>
        </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($appealTab === 'history'): ?>
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
                    <?php
                    $summary = $appealStatusSummaries[$status] ?? $appealStatusSummaries['open'];
                    $summaryDateSource = in_array($status, ['accepted', 'rejected'], true)
                        ? (($appeal['reviewed_at'] ?? '') ?: ($appeal['updated_at'] ?? ''))
                        : (($appeal['updated_at'] ?? '') ?: ($appeal['created_at'] ?? ''));
                    $summaryDate = $formatAppealDate($summaryDateSource);
                    ?>
                    <div class="appeal-history-status <?= htmlspecialchars((string)($summary['class'] ?? 'is-open'), ENT_QUOTES, 'UTF-8') ?>">
                        <span class="appeal-history-status-icon"><i class="bi <?= htmlspecialchars((string)($summary['icon'] ?? 'bi-hourglass-split'), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
                        <div>
                            <strong><?= htmlspecialchars((string)($summary['title'] ?? usersBanAppealStatusLabel($status)), ENT_QUOTES, 'UTF-8') ?></strong>
                            <p><?= htmlspecialchars((string)($summary['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                            <small><?= htmlspecialchars((string)($summary['date_label'] ?? 'Tarih'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($summaryDate !== '' ? $summaryDate : '-', ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
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
    <?php endif; ?>
</main>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>
