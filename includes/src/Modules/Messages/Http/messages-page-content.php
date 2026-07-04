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

$successMsg = $successMessage;
$errorMsg = $errorMessage;

$csrfToken = csrf_token();
$messagesApiUrl = rtrim((string) ($baseUri ?? ''), '/') . '/api/messages.php';
$activeThreadId = $activeThread !== null ? (int) ($activeThread['thread_id'] ?? 0) : 0;

$pageCssFiles = array_values(array_unique(array_merge(
    $pageCssFiles ?? [],
    ['assets/css/messages-page.css?v=' . time()],
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
    class="messages-shell container ui-container"
    aria-label="Mesajlasma"
    data-messages-root
    data-messages-api-url="<?= htmlspecialchars($messagesApiUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-messages-url="<?= htmlspecialchars($messagesBaseUrl, ENT_QUOTES, 'UTF-8') ?>"
    data-messages-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
    data-active-thread-id="<?= (int) $activeThreadId ?>"
    data-current-user-id="<?= (int) $userId ?>"
>
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
        <aside class="messages-sidebar" aria-label="Sohbet listesi">
            <div class="messages-sidebar-head">
                <div class="messages-sidebar-title">
                    <h2>Sohbetler</h2>
                    <p>
                        <?= number_format(count($threads), 0, ',', '.') ?> sohbet
                        <span aria-hidden="true">&middot;</span>
                        <?= number_format($unreadTotal, 0, ',', '.') ?> okunmamis
                    </p>
                </div>
                <div class="messages-sidebar-actions">
                    <a href="<?= htmlspecialchars($messagesBaseUrl, ENT_QUOTES, 'UTF-8') ?>" class="messages-refresh-link" title="Yenile">
                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    </a>
                    <button class="messages-new-chat-btn" type="button" aria-label="Yeni Sohbet Başlat" data-messages-new-chat-toggle title="Yeni Mesaj">
                        <i class="bi bi-envelope-plus" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div class="messages-sidebar-search">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Sohbetlerde ara..." data-messages-sidebar-search-input>
            </div>

            <?php if ($threads === []): ?>
                <div class="messages-empty-state">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <p>Henuz sohbet yok. Yeni mesaj butonu ile yeni bir sohbet baslatabilirsiniz.</p>
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
        </aside>

        <section class="messages-panel" aria-label="Aktif sohbet">
            <?php if ($activeThread === null): ?>
                <div class="messages-empty-chat">
                    <i class="bi bi-chat-square-text" aria-hidden="true"></i>
                    <h2>Sohbet secin</h2>
                    <p>Sol listeden bir sohbet secerek veya yeni sohbet baslatarak mesajlasmaya devam edebilirsiniz.</p>
                </div>
            <?php else: ?>
                <header class="messages-panel-head">
                    <?php
                    $peerProfileUrl = publicProfileUrl([
                        'id' => (int) ($activeThread['with_user_id'] ?? 0),
                        'name' => (string) ($activeThread['with_user_name'] ?? '')
                    ]);
                    ?>
                    <a href="<?= htmlspecialchars($peerProfileUrl, ENT_QUOTES, 'UTF-8') ?>" class="messages-peer">
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
                            <span>Profilini Gör</span>
                        </div>
                    </a>
                </header>

                <div class="messages-stream" data-messages-stream>
                    <?php if ($activeMessages === []): ?>
                        <div class="messages-stream-empty">
                            <i class="bi bi-envelope-open" aria-hidden="true"></i>
                            <p>Bu sohbette henuz mesaj yok. Ilk mesaji gonderebilirsiniz.</p>
                        </div>
                    <?php else: ?>
                        <?php
                        // Group messages: consecutive messages from same sender
                        $msgCount = count($activeMessages);
                        $prevSenderId = null;
                        $prevDate = null;
                        for ($i = 0; $i < $msgCount; $i++):
                            $msg = $activeMessages[$i];
                            $isMine = !empty($msg['is_mine']);
                            $senderId = (int) ($msg['sender_user_id'] ?? 0);
                            $isRead = !empty($msg['is_read_by_recipient']);

                            // Determine group position
                            $nextSenderId = ($i + 1 < $msgCount)
                                ? (int) ($activeMessages[$i + 1]['sender_user_id'] ?? 0)
                                : null;
                            $isGroupStart = ($prevSenderId === null || $prevSenderId !== $senderId);
                            $isGroupEnd = ($nextSenderId === null || $nextSenderId !== $senderId);

                            // Date separator
                            $msgDate = date('Y-m-d', strtotime((string) ($msg['created_at'] ?? '')));
                            if ($msgDate !== $prevDate && $msgDate !== '1970-01-01'):
                                $label = date('d F Y', strtotime($msgDate));
                                $isToday = $msgDate === date('Y-m-d');
                                $isYesterday = $msgDate === date('Y-m-d', strtotime('-1 day'));
                                if ($isToday) $label = 'Bugun';
                                elseif ($isYesterday) $label = 'Dun';
                        ?>
                            <div class="msg-date-separator">
                                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        <?php endif; $prevDate = $msgDate; ?>

                            <?php
                            // Group classes
                            $groupClasses = 'msg';
                            $groupClasses .= $isMine ? ' is-mine' : ' is-theirs';
                            $showAvatar = !$isMine && ($isGroupEnd || (!$isGroupStart && !$isGroupEnd && $i === $msgCount - 1));
                            if ($isGroupStart) $groupClasses .= ' msg-group-first';
                            if ($isGroupEnd) $groupClasses .= ' msg-group-last';
                            if (!$isGroupStart && !$isGroupEnd) $groupClasses .= ' msg-group-middle';
                            ?>
                            <article class="<?= trim($groupClasses) ?>" data-message-id="<?= (int) ($msg['id'] ?? 0) ?>">
                                <?php if (!$isMine): ?>
                                <div class="msg-avatar">
                                    <?php if ($showAvatar): ?>
                                    <img
                                        src="<?= htmlspecialchars((string) ($activeThread['with_user_avatar'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars((string) ($activeThread['with_user_name'] ?? 'Kullanici'), ENT_QUOTES, 'UTF-8') ?>"
                                        width="32"
                                        height="32"
                                        loading="lazy"
                                        data-ui-avatar-img
                                    >
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <div class="msg-content">
                                    <div class="msg-body"><?= htmlspecialchars((string) ($msg['body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if ($isGroupEnd || $isMine): ?>
                                    <div class="msg-meta">
                                        <time datetime="<?= htmlspecialchars((string) ($msg['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars((string) ($msg['created_at_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </time>
                                        <?php if ($isMine): ?>
                                        <span class="msg-read-state<?= $isRead ? ' is-read' : '' ?>"<?= $isRead && !empty($msg['read_at_label']) ? ' title="Görüldü: ' . htmlspecialchars((string)$msg['read_at_label'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                            <?php if ($isRead): ?>
                                                <i class="bi bi-check-all" aria-hidden="true"></i>
                                            <?php else: ?>
                                                <i class="bi bi-check" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php
                            $prevSenderId = $senderId;
                        endfor;
                        ?>
                    <?php endif; ?>
                </div>

                <form method="post" class="messages-composer" data-messages-send-form>
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="send">
                    <input type="hidden" name="thread_id" value="<?= (int) $activeThreadId ?>" data-messages-thread-id>
                    <div class="messages-composer-inner">
                        <textarea class="messages-composer-textarea" name="body" rows="1" maxlength="4000" placeholder="Bir mesaj yazın..." required></textarea>
                        <button type="submit" class="messages-submit"<?= $messagesReady ? '' : ' disabled aria-disabled="true"' ?> title="Gönder">
                            <i class="bi bi-send-fill" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</section>

<!-- Modal overlay starting new chats -->
<div class="messages-modal" id="newChatModal" hidden aria-hidden="true">
    <div class="messages-modal-backdrop" data-messages-modal-close></div>
    <div class="messages-modal-container">
        <div class="messages-modal-header">
            <h3>Yeni Mesaj</h3>
            <button type="button" class="messages-modal-close" data-messages-modal-close aria-label="Kapat">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="messages-modal-body">
            <form method="post" class="messages-start-form" data-messages-start-form>
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="start">
                <input type="hidden" name="target_user_id" value="" data-messages-target-user-id>

                <div class="messages-modal-field">
                    <label for="newChatSearch">Kullanıcı Ara</label>
                    <div class="messages-modal-search-wrapper">
                        <i class="bi bi-search"></i>
                        <input type="text" id="newChatSearch" name="target_user_name" autocomplete="off" placeholder="En az 2 harf yazın..." data-messages-user-search required>
                    </div>
                    <div class="messages-search-results" data-messages-search-results hidden></div>
                </div>

                <div class="messages-modal-field">
                    <label for="newChatMessage">Mesajınız</label>
                    <textarea id="newChatMessage" name="body" rows="4" maxlength="4000" placeholder="İlk mesajınızı buraya yazın..." required></textarea>
                </div>

                <button type="submit" class="messages-modal-submit"<?= $messagesReady ? '' : ' disabled aria-disabled="true"' ?>>
                    <i class="bi bi-send-fill"></i>
                    <span>Sohbeti Başlat</span>
                </button>
            </form>
        </div>
    </div>
</div>

<script src="<?= asset_url('assets/js/messages-page.js', $baseUri) ?>?v=<?= time() ?>" defer></script>

<?php require_once $projectRoot . '/includes/public-footer.php'; ?>
