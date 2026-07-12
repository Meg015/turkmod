<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$pageTitle = 'Doğrulama E-postasını Yeniden Gönder';
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        $message = 'Güvenlik doğrulaması başarısız.';
    } else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $settings = getAdminSettings($pdo);
        $cooldown = max(1, min(1440, (int) ($settings['account_email_verification_resend_cooldown_minutes'] ?? 10)));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false && $pdo) {
            $stmt = $pdo->prepare('SELECT id, username, email, email_verified_at, email_verification_sent_at FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && empty($user['email_verified_at'])) {
                $lastSent = !empty($user['email_verification_sent_at']) ? strtotime((string) $user['email_verification_sent_at']) : 0;
                if ($lastSent <= time() - ($cooldown * 60)) {
                    try {
                        accountEmailService($pdo)->issueVerification((int) $user['id'], (string) $user['email'], (string) $user['username']);
                    } catch (Throwable $e) {
                        if (function_exists('appLogException')) appLogException($e, ['source' => 'resend_verification']);
                    }
                }
            }
        }
        $message = 'Adres kayıtlı ve doğrulanmamışsa yeni bağlantı gönderildi.';
    }
}

require_once __DIR__ . '/includes/public-header.php';
?>
<main class="auth-page-shell">
    <section class="auth-card" style="max-width:560px;margin:40px auto;padding:30px">
        <h1>Doğrulama E-postasını Yeniden Gönder</h1>
        <?php if ($message !== ''): ?><div class="ui-alert ui-alert-info"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <label for="verification_email">E-posta adresi</label>
            <input id="verification_email" name="email" type="email" required class="ui-form-control">
            <button class="ui-btn ui-btn-primary" type="submit">Bağlantıyı Gönder</button>
        </form>
    </section>
</main>
<?php require_once __DIR__ . '/includes/public-footer.php'; ?>
