<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$email = trim((string) ($_GET['email'] ?? ''));
$token = trim((string) ($_GET['token'] ?? ''));
$verifiedUser = accountEmailService($pdo)->verify($email, $token);
$ok = is_array($verifiedUser);
$pageTitle = $ok ? 'E-posta Doğrulandı' : 'Doğrulama Bağlantısı Geçersiz';

require_once __DIR__ . '/includes/public-header.php';
?>
<main class="auth-page-shell">
    <section class="auth-card" style="max-width:620px;margin:40px auto;padding:30px;text-align:center">
        <i class="bi <?= $ok ? 'bi-check-circle-fill text-success' : 'bi-exclamation-triangle-fill text-warning' ?>" style="font-size:48px"></i>
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p><?= $ok ? 'E-posta adresiniz başarıyla doğrulandı. Hesabınızı kullanmaya devam edebilirsiniz.' : 'Bağlantı geçersiz, daha önce kullanılmış veya süresi dolmuş olabilir.' ?></p>
        <a class="ui-btn ui-btn-primary" href="<?= htmlspecialchars(routePublicStaticUrl('login')) ?>">Giriş Sayfasına Git</a>
        <?php if (!$ok): ?><a class="ui-btn ui-btn-outline" href="<?= htmlspecialchars(rtrim((string) ($baseUri ?? ''), '/') . '/resend-verification.php') ?>">Yeni Bağlantı İste</a><?php endif; ?>
    </section>
</main>
<?php require_once __DIR__ . '/includes/public-footer.php'; ?>
