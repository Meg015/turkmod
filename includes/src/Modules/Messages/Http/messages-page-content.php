<?php

declare(strict_types=1);

use App\Modules\Messages\Services\MessageService;

$messagesBaseUrl = function_exists('routePublicStaticUrl')
    ? routePublicStaticUrl('messages')
    : (rtrim((string) ($baseUri ?? ''), '/') . '/mesajlar');
$pageTitle = 'Mesajlar';
$metaDescription = 'Uyelere ozel birebir mesajlasma alani.';
$userId = (int) ($_SESSION['_auth_user_id'] ?? 0);

if ($userId <= 0) {
    $loginUrl = function_exists('routePublicStaticUrl')
        ? routePublicStaticUrl('login')
        : (rtrim((string) ($baseUri ?? ''), '/') . '/giris');
    $redirectTarget = (string) ($_SERVER['REQUEST_URI'] ?? $messagesBaseUrl);
    header('Location: ' . $loginUrl . '?redirect=' . rawurlencode($redirectTarget));
    exit;
}

$pdo = requireDatabaseConnection($pdo ?? null);
$service = new MessageService();
$messagesReady = $service->isSchemaReady($pdo);
$messagesUnavailableMessage = $service->unavailableMessage();

$errorMessage = function_exists('get_flash') ? (string) (get_flash('error') ?? '') : '';
$successMessage = function_exists('get_flash') ? (string) (get_flash('success') ?? '') : '';
$activeThreadId = max(0, (int) ($_GET['thread'] ?? 0));
$targetUserFromQuery = max(0, (int) ($_GET['with'] ?? 0));

if (!$messagesReady && $errorMessage === '') {
    $errorMessage = $messagesUnavailableMessage;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$messagesReady) {
        $errorMessage = $messagesUnavailableMessage;
    } else {
        $action = trim((string) ($_POST['action'] ?? ''));
        if (!verify_csrf_token((string) ($_POST['_token'] ?? ''))) {
            $errorMessage = 'Guvenlik dogrulamasi basarisiz oldu.';
        } else {
            if ($action === 'send') {
                $postedThreadId = max(0, (int) ($_POST['thread_id'] ?? 0));
                $result = $service->sendMessageToThread(
                    $pdo,
                    $userId,
                    $postedThreadId,
                    (string) ($_POST['body'] ?? ''),
                    rtrim((string) ($baseUri ?? ''), '/'),
                );
                if (!empty($result['success'])) {
                    if (function_exists('flash')) {
                        flash('success', 'Mesaj gonderildi.');
                    }
                    header('Location: ' . $messagesBaseUrl . '?thread=' . (int) ($result['thread_id'] ?? 0));
                    exit;
                }

                $errorMessage = (string) ($result['message'] ?? 'Mesaj gonderilemedi.');
                $activeThreadId = $postedThreadId;
            } elseif ($action === 'start') {
                $targetUserId = max(0, (int) ($_POST['target_user_id'] ?? 0));
                $result = $service->sendMessage(
                    $pdo,
                    $userId,
                    $targetUserId,
                    (string) ($_POST['body'] ?? ''),
                    rtrim((string) ($baseUri ?? ''), '/'),
                );
                if (!empty($result['success'])) {
                    if (function_exists('flash')) {
                        flash('success', 'Yeni sohbet baslatildi.');
                    }
                    header('Location: ' . $messagesBaseUrl . '?thread=' . (int) ($result['thread_id'] ?? 0));
                    exit;
                }

                $errorMessage = (string) ($result['message'] ?? 'Sohbet baslatilamadi.');
            }
        }
    }
}

if ($messagesReady && $targetUserFromQuery > 0) {
    $threadId = $service->getOrCreateThreadByUser($pdo, $userId, $targetUserFromQuery);
    if ($threadId > 0) {
        $activeThreadId = $threadId;
    } elseif ($errorMessage === '') {
        $errorMessage = 'Sohbet acilamadi.';
    }
}

$threads = $messagesReady
    ? $service->listThreads($pdo, $userId, 80, rtrim((string) ($baseUri ?? ''), '/'))
    : [];
if ($activeThreadId <= 0 && $threads !== []) {
    $activeThreadId = (int) ($threads[0]['thread_id'] ?? 0);
}

$activeThreadPayload = $messagesReady && $activeThreadId > 0
    ? $service->openThread($pdo, $userId, $activeThreadId, rtrim((string) ($baseUri ?? ''), '/'))
    : null;
if ($activeThreadId > 0 && $activeThreadPayload === null && $errorMessage === '') {
    $errorMessage = 'Secilen sohbet bulunamadi.';
}

$threads = $messagesReady
    ? $service->listThreads($pdo, $userId, 80, rtrim((string) ($baseUri ?? ''), '/'))
    : [];
$activeThread = is_array($activeThreadPayload['thread'] ?? null) ? $activeThreadPayload['thread'] : null;
$activeMessages = is_array($activeThreadPayload['messages'] ?? null) ? $activeThreadPayload['messages'] : [];
$unreadTotal = $messagesReady ? $service->unreadCount($pdo, $userId) : 0;

$markedUnreadCount = (int) ($activeThreadPayload['marked_unread_count'] ?? 0);
if ($markedUnreadCount > 0 && $successMessage === '') {
    $successMessage = $markedUnreadCount . ' okunmamis mesaj okundu olarak isaretlendi.';
}

$csrfToken = csrf_token();
$messagesApiUrl = rtrim((string) ($baseUri ?? ''), '/') . '/api/messages.php';
$activeThreadId = $activeThread !== null ? (int) ($activeThread['thread_id'] ?? 0) : 0;

$pageCssFiles = array_values(array_unique(array_merge(
    $pageCssFiles ?? [],
    ['assets/css/messages-page.css'],
)));

require_once $projectRoot . '/includes/public-header.php';
?>

<?php if (!function_exists('usesPublicThemeRenderer') || !usesPublicThemeRenderer()): ?>
<div class="container public-container public-breadcrumb breadcrumb-container breadcrumb-container-spaced ui-container">
    <nav class="breadcrumb" aria-label="Sayfa yolu">
        <a href="<?= htmlspecialchars((string) ($baseUri ?? ''), ENT_QUOTES, 'UTF-8') ?>/index.php"><i class="bi bi-house-door" aria-hidden="true"></i> Ana Sayfa</a>
        <i class="bi bi-chevron-right" aria-hidden="true"></i>
        <span>Mesajlar</span>
    </nav>
</div>
<?php endif; ?>

<section
    class="messages-shell public-container public-content ui-container ui-section"
    aria-labelledby="messages-title"
    data-messages-root
    data-messages-api-url="<?= htmlspecialchars($messagesApiUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-messages-url="<?= htmlspecialchars($messagesBaseUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-messages-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    data-active-thread-id="<?= (int) $activeThreadId ?>"
>
    <section class="messages-hero ui-card">
        <div>
            <span class="messages-kicker"><i class="bi bi-chat-dots" aria-hidden="true"></i> Ozel Mesajlar</span>
            <h1 id="messages-title">Mesajlasma Merkezi</h1>
            <p>Sadece birebir sohbetleri yonetin, okunmamislari tek panelden takip edin ve acilan sohbetlerde otomatik okundu akisini kullanin.</p>
        </div>
        <div class="messages-hero-meta">
            <article class="messages-hero-stat is-threads">
                <span class="messages-hero-stat-icon" aria-hidden="true"><i class="bi bi-chat-dots"></i></span>
                <div class="messages-hero-stat-copy">
                    <span class="messages-hero-stat-label">Toplam Sohbet</span>
                    <strong><?= number_format(count($threads), 0, ',', '.') ?></strong>
                    <small>Tum birebir konusmalar</small>
                </div>
            </article>
            <article class="messages-hero-stat is-unread">
                <span class="messages-hero-stat-icon" aria-hidden="true"><i class="bi bi-envelope-open"></i></span>
                <div class="messages-hero-stat-copy">
                    <span class="messages-hero-stat-label">Bekleyen Mesaj</span>
                    <strong><?= number_format($unreadTotal, 0, ',', '.') ?></strong>
                    <small>Okunmamis iletiler</small>
                </div>
            </article>
        </div>
    </section>

    <?php if ($successMessage !== ''): ?>
        <div class="messages-alert is-success" role="status">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <span><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="messages-alert is-error" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <span><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <div class="messages-workspace">
        <aside class="messages-sidebar ui-card" aria-label="Sohbet listesi">
            <div class="messages-sidebar-head">
                <div class="messages-sidebar-title">
                    <h2>Sohbetler</h2>
                    <p>
                        <?= number_format(count($threads), 0, ',', '.') ?> sohbet
                        <span aria-hidden="true">&middot;</span>
                        <?= number_format($unreadTotal, 0, ',', '.') ?> okunmamis
                    </p>
                </div>
                <a href="<?= htmlspecialchars($messagesBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="messages-refresh-link">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    <span>Yenile</span>
                </a>
            </div>

            <?php if ($threads === []): ?>
                <div class="messages-empty-state">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <p>Henuz sohbet yok. Profil sayfalarindan yeni bir sohbet baslatabilirsiniz.</p>
                </div>
            <?php else: ?>
                <div class="messages-thread-list" data-messages-thread-list>
                    <?php foreach ($threads as $threadItem): ?>
                        <?php
                        $threadItemId = (int) ($threadItem['thread_id'] ?? 0);
                        $isActive = $threadItemId > 0 && $threadItemId === $activeThreadId;
                        $unreadCount = (int) ($threadItem['unread_count'] ?? 0);
                        $threadUrl = (string) ($threadItem['thread_url'] ?? ($messagesBaseUrl . '?thread=' . $threadItemId));
                        ?>
                        <a
                            href="<?= htmlspecialchars($threadUrl, ENT_QUOTES, 'UTF-8') ?>"
                            class="messages-thread-item<?= $isActive ? ' is-active' : '' ?>"
                            data-thread-item
                            data-thread-id="<?= $threadItemId ?>"
                        >
                            <img
                                src="<?= htmlspecialchars((string) ($threadItem['with_user_avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars((string) ($threadItem['with_user_name'] ?? 'Kullanici'), ENT_QUOTES, 'UTF-8') ?>"
                                width="42"
                                height="42"
                                loading="lazy"
                                data-ui-avatar-img
                            >
                            <span class="messages-thread-main">
                                <span class="messages-thread-topline">
                                    <strong><?= htmlspecialchars((string) ($threadItem['with_user_name'] ?? 'Kullanici'), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <time datetime="<?= htmlspecialchars((string) ($threadItem['last_message_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($threadItem['last_message_at_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </time>
                                </span>
                                <span class="messages-thread-preview"><?= htmlspecialchars((string) ($threadItem['last_message_preview'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if (!empty($threadItem['last_message_is_mine'])): ?>
                                    <span class="messages-thread-read-state<?= !empty($threadItem['last_message_read']) ? ' is-read' : '' ?>">
                                        <?= !empty($threadItem['last_message_read']) ? 'Okundu' : 'Gonderildi' ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="messages-thread-unread"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="messages-start-card" aria-label="Yeni sohbet baslat">
                <h3>
                    <i class="bi bi-plus-circle" aria-hidden="true"></i>
                    <span>Yeni Sohbet</span>
                </h3>
                <form method="post" class="messages-start-form" data-messages-start-form>
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="target_user_id" value="" data-messages-target-user-id>
                    <label class="messages-field">
                        <span>Kullanici</span>
                        <input type="text" name="target_user_name" autocomplete="off" placeholder="En az 2 harf yazin" data-messages-user-search>
                        <div class="messages-search-results" data-messages-search-results hidden></div>
                    </label>
                    <label class="messages-field">
                        <span>Mesaj</span>
                        <textarea name="body" rows="3" maxlength="4000" placeholder="Ilk mesajinizi yazin..." required></textarea>
                    </label>
                    <button type="submit" class="messages-submit"<?= $messagesReady ? '' : ' disabled aria-disabled="true"' ?>>
                        <i class="bi bi-send" aria-hidden="true"></i>
                        <span>Sohbeti Baslat</span>
                    </button>
                </form>
            </section>
        </aside>

        <section class="messages-panel ui-card" aria-label="Aktif sohbet">
            <?php if ($activeThread === null): ?>
                <div class="messages-empty-chat">
                    <i class="bi bi-chat-square-text" aria-hidden="true"></i>
                    <h2>Sohbet secin</h2>
                    <p>Sol listeden bir sohbet secerek veya yeni sohbet baslatarak mesajlasmaya devam edebilirsiniz.</p>
                </div>
            <?php else: ?>
                <header class="messages-panel-head">
                    <div class="messages-peer">
                        <img
                            src="<?= htmlspecialchars((string) ($activeThread['with_user_avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars((string) ($activeThread['with_user_name'] ?? 'Kullanici'), ENT_QUOTES, 'UTF-8') ?>"
                            width="46"
                            height="46"
                            loading="lazy"
                            data-ui-avatar-img
                        >
                        <div>
                            <strong><?= htmlspecialchars((string) ($activeThread['with_user_name'] ?? 'Kullanici'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <span>Tekil sohbet</span>
                        </div>
                    </div>
                </header>

                <div class="messages-stream" data-messages-stream>
                    <?php if ($activeMessages === []): ?>
                        <div class="messages-stream-empty">
                            <i class="bi bi-envelope-open" aria-hidden="true"></i>
                            <p>Bu sohbette henuz mesaj yok. Ilk mesaji gonderebilirsiniz.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activeMessages as $messageItem): ?>
                            <?php
                            $isMine = !empty($messageItem['is_mine']);
                            $isReadByRecipient = !empty($messageItem['is_read_by_recipient']);
                            ?>
                            <article class="messages-bubble<?= $isMine ? ' is-mine' : ' is-theirs' ?>" data-message-id="<?= (int) ($messageItem['id'] ?? 0) ?>">
                                <div class="messages-bubble-body">
                                    <?= nl2br(htmlspecialchars((string) ($messageItem['body'] ?? ''), ENT_QUOTES, 'UTF-8')) ?>
                                </div>
                                <div class="messages-bubble-meta">
                                    <time datetime="<?= htmlspecialchars((string) ($messageItem['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) ($messageItem['created_at_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </time>
                                    <?php if ($isMine): ?>
                                        <span class="messages-read-state<?= $isReadByRecipient ? ' is-read' : '' ?>">
                                            <?= $isReadByRecipient ? 'Okundu' : 'Gonderildi' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="post" class="messages-composer" data-messages-send-form>
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="send">
                    <input type="hidden" name="thread_id" value="<?= (int) $activeThreadId ?>" data-messages-thread-id>
                    <label class="messages-field messages-field-composer">
                        <span class="visually-hidden">Mesajiniz</span>
                        <textarea name="body" rows="2" maxlength="4000" placeholder="Mesajinizi yazin..." required></textarea>
                    </label>
                    <button type="submit" class="messages-submit"<?= $messagesReady ? '' : ' disabled aria-disabled="true"' ?>>
                        <i class="bi bi-send" aria-hidden="true"></i>
                        <span>Gonder</span>
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</section>

<script src="<?= asset_url('assets/js/messages-page.js', $baseUri) ?>" defer></script>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>
