<?php

declare(strict_types=1);

require_once __DIR__ . '/../Legacy/helpers.php';

$pageKey = 'contact';
$pageTitle = 'Iletisim';
$metaDescription = 'Iletisim formu ile mesaj, sikayet, oneri, reklam ve telif basvurusu gonderebilirsiniz.';
$pageCssFiles = ['assets/css/contact.css'];

$currentUserId = (int) ($_SESSION['_auth_user_id'] ?? 0);
$isMember = $isLoggedIn && $currentUserId > 0;
$memberUser = $isMember && $pdo instanceof PDO && function_exists('usersGetById')
    ? usersGetById($pdo, $currentUserId)
    : null;
$memberName = trim((string) ($memberUser['username'] ?? ($_SESSION['_auth_user_name'] ?? '')));
$memberEmail = trim((string) ($memberUser['email'] ?? ($_SESSION['_auth_user_email'] ?? '')));
$memberFallbackName = trim((string) ($_SESSION['_auth_user_name'] ?? ''));
$memberFallbackEmail = trim((string) ($_SESSION['_auth_user_email'] ?? ''));
$memberResolvedName = $memberName !== '' ? $memberName : $memberFallbackName;
$memberResolvedEmail = $memberEmail !== '' ? $memberEmail : $memberFallbackEmail;

$contactCategories = $pdo instanceof PDO ? contactCategories($pdo, true) : [];
$contactCategoryIds = [];
foreach ($contactCategories as $contactCategory) {
    $categoryId = (int) ($contactCategory['id'] ?? 0);
    if ($categoryId > 0) {
        $contactCategoryIds[$categoryId] = true;
    }
}
$oldForm = isset($_SESSION['_contact_form_old']) && is_array($_SESSION['_contact_form_old'])
    ? $_SESSION['_contact_form_old']
    : [];
unset($_SESSION['_contact_form_old']);

$selectedCategoryId = (int) ($oldForm['category_id'] ?? ($_POST['category_id'] ?? ($_GET['category_id'] ?? ($contactCategories[0]['id'] ?? 0))));
if (($selectedCategoryId <= 0 || !isset($contactCategoryIds[$selectedCategoryId])) && $contactCategories !== []) {
    $selectedCategoryId = (int) ($contactCategories[0]['id'] ?? 0);
}

$formValues = [
    'name' => $isMember ? $memberResolvedName : trim((string) ($oldForm['name'] ?? '')),
    'email' => $isMember ? $memberResolvedEmail : trim((string) ($oldForm['email'] ?? '')),
    'subject' => trim((string) ($oldForm['subject'] ?? '')),
    'message' => trim((string) ($oldForm['message'] ?? '')),
    'company' => '',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $returnUrl = routePublicStaticUrl('contact');
    $postCategoryId = (int) ($_POST['category_id'] ?? 0);
    if (!isset($contactCategoryIds[$postCategoryId]) && $contactCategories !== []) {
        $postCategoryId = (int) ($contactCategories[0]['id'] ?? 0);
    }
    $postNameInput = trim((string) ($_POST['name'] ?? ''));
    $postEmailInput = trim((string) ($_POST['email'] ?? ''));
    $postName = $isMember ? $memberResolvedName : $postNameInput;
    $postEmail = $isMember ? $memberResolvedEmail : $postEmailInput;
    $postSubject = trim((string) ($_POST['subject'] ?? ''));
    $postMessage = trim((string) ($_POST['message'] ?? ''));
    $postHoneypot = trim((string) ($_POST['company'] ?? ''));

    $_SESSION['_contact_form_old'] = [
        'category_id' => $postCategoryId,
        'name' => $postName,
        'email' => $postEmail,
        'subject' => $postSubject,
        'message' => $postMessage,
    ];

    if (!verify_csrf_token((string) ($_POST['_token'] ?? ''))) {
        flash('error', 'Guvenlik dogrulamasi basarisiz.');
        header('Location: ' . $returnUrl);
        exit;
    }

    if ($postHoneypot !== '') {
        flash('error', 'Islem tamamlanamadi.');
        header('Location: ' . $returnUrl);
        exit;
    }

    $rateIp = function_exists('getRealIp') ? getRealIp() : (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $rateEmail = $isMember ? $memberResolvedEmail : $postEmail;
    $rateUser = $isMember ? $currentUserId : 0;

    $rateChecks = [
        ['key' => 'contact_submit_ip_' . $rateIp, 'limit' => 4, 'window' => 15],
    ];
    if ($rateEmail !== '') {
        $rateChecks[] = ['key' => 'contact_submit_email_' . strtolower($rateEmail), 'limit' => 2, 'window' => 30];
    }
    if ($rateUser > 0) {
        $rateChecks[] = ['key' => 'contact_submit_user_' . $rateUser, 'limit' => 4, 'window' => 30];
    }

    foreach ($rateChecks as $check) {
        if (!checkRateLimit((string) $check['key'], (int) $check['limit'], (int) $check['window'])) {
            flash('error', 'Cok hizli mesaj gonderiyorsunuz. Lutfen biraz sonra tekrar deneyin.');
            header('Location: ' . $returnUrl);
            exit;
        }
    }

    $submitResult = $pdo instanceof PDO
        ? contactSubmitMessage($pdo, [
            'category_id' => $postCategoryId,
            'name' => $postName,
            'email' => $postEmail,
            'subject' => $postSubject,
            'message' => $postMessage,
            'submitted_ip' => $rateIp,
            'submitted_user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'company' => $postHoneypot,
        ], $isMember ? $currentUserId : null)
        : ['success' => false, 'message' => 'Veritabani baglantisi yok.', 'mail_sent' => false, 'mail_error' => '', 'status' => 'new'];

    if (!($submitResult['success'] ?? false)) {
        flash('error', (string) ($submitResult['message'] ?? 'Mesaj kaydedilemedi.'));
        header('Location: ' . $returnUrl);
        exit;
    }

    foreach ($rateChecks as $check) {
        incrementRateLimit((string) $check['key'], (int) $check['window']);
    }

    unset($_SESSION['_contact_form_old']);
    if (!empty($submitResult['mail_sent'])) {
        flash('success', (string) ($submitResult['message'] ?? 'Mesajiniz alindi.'));
    } else {
        flash('info', (string) ($submitResult['message'] ?? 'Mesajiniz alindi.'));
    }
    header('Location: ' . $returnUrl);
    exit;
}

$contactHasCategories = $contactCategories !== [];
$contactCategoryCount = count($contactCategories);
$contactIsReadonly = $isMember;
$contactFormName = $contactIsReadonly ? $memberResolvedName : $formValues['name'];
$contactFormEmail = $contactIsReadonly ? $memberResolvedEmail : $formValues['email'];
$contactFormSubject = $formValues['subject'];
$contactFormMessage = $formValues['message'];
$contactIntroNote = $isMember
    ? 'Uye hesabi algilandi. Ad ve e-posta alanlari otomatik dolduruldu ve kilitlendi.'
    : 'Misafir olarak mesaj gonderiyorsunuz. Ad ve e-posta alanlarini doldurmaniz gerekiyor.';

require_once $projectRoot . '/includes/public-header.php';
?>

<main class="contact-shell ui-section">
    <div class="ui-container contact-container">
        <section class="contact-frame">
            <div class="contact-hero ui-stack">
                <div class="contact-hero-copy">
                    <h1>Iletisim formu</h1>
                    <p>Sikayet, oneri, reklam ve DMCA taleplerinizi tek form uzerinden gonderin. Mesajiniz admin paneline duser ve gerekli durumda e-posta ile yanitlanir.</p>
                </div>
                <div class="contact-hero-meta ui-cluster">
                    <span class="contact-meta-pill"><i class="bi bi-shield-check" aria-hidden="true"></i> Spam korumali</span>
                    <span class="contact-meta-pill"><i class="bi bi-envelope-paper" aria-hidden="true"></i> E-posta ile takip</span>
                    <span class="contact-meta-pill"><i class="bi bi-list-check" aria-hidden="true"></i> Kategori bazli</span>
                    <?php if ($contactHasCategories): ?>
                        <span class="contact-meta-pill"><i class="bi bi-collection" aria-hidden="true"></i> <?= (int) $contactCategoryCount ?> aktif kategori</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="contact-layout">
                <section class="ui-card contact-form-card">
                    <div class="ui-stack">
                        <div class="ui-alert ui-alert--info contact-note" role="status">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <div><?= htmlspecialchars($contactIntroNote, ENT_QUOTES, 'UTF-8') ?></div>
                        </div>

                        <?php if (!$contactHasCategories): ?>
                            <div class="ui-alert ui-alert--warning" role="status">
                                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                                <div>Aktif kategori bulunamadi. Lutfen admin tarafinda kategori tanimlayin.</div>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?= htmlspecialchars(routePublicStaticUrl('contact'), ENT_QUOTES, 'UTF-8') ?>" class="contact-form ui-stack" novalidate>
                            <?= csrf_field() ?>
                            <div class="contact-honeypot" aria-hidden="true" inert>
                                <label for="company" aria-hidden="true">Company</label>
                                <input type="text" id="company" name="company" value="<?= htmlspecialchars((string) $formValues['company'], ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" autocomplete="off" aria-hidden="true" inputmode="none">
                            </div>

                            <div class="contact-form-grid">
                                <div class="contact-field<?= $contactIsReadonly ? ' contact-field--locked' : '' ?>">
                                    <label class="ui-label" for="contactName">Ad</label>
                                    <input type="text" id="contactName" name="name" class="ui-input" value="<?= htmlspecialchars((string) $contactFormName, ENT_QUOTES, 'UTF-8') ?>" maxlength="160"<?= $contactIsReadonly ? ' readonly aria-readonly="true" aria-disabled="true"' : ' required' ?> autocomplete="name">
                                </div>
                                <div class="contact-field<?= $contactIsReadonly ? ' contact-field--locked' : '' ?>">
                                    <label class="ui-label" for="contactEmail">E-posta</label>
                                    <input type="email" id="contactEmail" name="email" class="ui-input" value="<?= htmlspecialchars((string) $contactFormEmail, ENT_QUOTES, 'UTF-8') ?>" maxlength="255"<?= $contactIsReadonly ? ' readonly aria-readonly="true" aria-disabled="true"' : ' required' ?> autocomplete="email">
                                </div>
                            </div>

                            <div class="contact-field">
                                <label class="ui-label" for="contactSubject">Konu</label>
                                <input type="text" id="contactSubject" name="subject" class="ui-input" value="<?= htmlspecialchars((string) $contactFormSubject, ENT_QUOTES, 'UTF-8') ?>" maxlength="190" required autocomplete="off" placeholder="Mesajinizin kisa basligi">
                            </div>

                            <div class="contact-field contact-category-field">
                                <label class="ui-label" for="contactCategory">Kategori</label>
                                <div class="contact-select-shell">
                                    <select id="contactCategory" name="category_id" class="ui-select contact-category-select"<?= $contactHasCategories ? '' : ' disabled' ?> required aria-describedby="contactCategoryHelp">
                                        <?php if ($contactHasCategories): ?>
                                            <?php foreach ($contactCategories as $contactCategory): ?>
                                                <?php
                                                $categoryId = (int) ($contactCategory['id'] ?? 0);
                                                $isSelected = $categoryId === $selectedCategoryId;
                                                ?>
                                                <option value="<?= $categoryId ?>"<?= $isSelected ? ' selected' : '' ?>>
                                                    <?= htmlspecialchars((string) ($contactCategory['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">Aktif kategori yok</option>
                                        <?php endif; ?>
                                    </select>
                                    <i class="bi bi-chevron-down contact-select-icon" aria-hidden="true"></i>
                                </div>
                                <p class="ui-help contact-field-help" id="contactCategoryHelp">Mesajiniz secilen kategoriye gore admin panelinde etiketlenir.</p>
                            </div>

                            <div class="contact-field">
                                <label class="ui-label" for="contactMessage">Mesaj</label>
                                <textarea id="contactMessage" name="message" class="ui-textarea" rows="8" maxlength="5000" required placeholder="Mesajinizi ayrintili sekilde yazin."><?= htmlspecialchars((string) $contactFormMessage, ENT_QUOTES, 'UTF-8') ?></textarea>
                            </div>

                            <div class="contact-submit-row">
                                <button type="submit" class="ui-button"<?= $contactHasCategories ? '' : ' disabled' ?>>
                                    <i class="bi bi-send" aria-hidden="true"></i>
                                    <span>Mesaj Gonder</span>
                                </button>
                                <p class="ui-help">Gonderilen mesajlar public tarafta yayinlanmaz. Sadece admin panelinde takip edilir.</p>
                            </div>
                        </form>
                    </div>
                </section>

                <aside class="ui-card contact-side-card">
                    <div class="ui-stack">
                        <div>
                            <h2 class="contact-side-title">Yonlendirme notlari</h2>
                            <p class="contact-side-text">Mesajiniz secilen kategoriyle birlikte saklanir ve admin panelinde hizli filtrelenir.</p>
                        </div>

                        <div class="contact-side-list">
                            <div class="contact-side-item">
                                <i class="bi bi-shield-check" aria-hidden="true"></i>
                                <div>
                                    <strong>Spam korumasi</strong>
                                    <span>CSRF, honeypot ve rate limit birlikte calisir.</span>
                                </div>
                            </div>
                            <div class="contact-side-item">
                                <i class="bi bi-envelope-open" aria-hidden="true"></i>
                                <div>
                                    <strong>E-posta takip</strong>
                                    <span>Yanitlar dogrudan gonderen adresine gider.</span>
                                </div>
                            </div>
                            <div class="contact-side-item">
                                <i class="bi bi-eye-slash" aria-hidden="true"></i>
                                <div>
                                    <strong>Gizli akis</strong>
                                    <span>Public tarafta mesaj gecmisi gorunmez.</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</main>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>
