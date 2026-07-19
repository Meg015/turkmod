<?php

declare(strict_types=1);

require_once __DIR__ . '/init.php';

adminRequirePermission('contact.view', 'İletişim mesajlarını görüntülemek için gerekli izin hesabınıza tanımlanmamış.');

if ($pdo instanceof PDO) {
    contactEnsureCategoriesTable($pdo);
    contactEnsureMessagesTable($pdo);
}

$pageTitle = 'İletişim Yönetimi';
$allowedTabs = ['messages', 'categories'];
$activeTab = trim((string) ($_GET['tab'] ?? 'messages'));
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'messages';
}

function adminContactsUrl(string $tab, array $params = []): string
{
    $tab = in_array($tab, ['messages', 'categories'], true) ? $tab : 'messages';
    $allowed = $tab === 'categories'
        ? ['category_id']
        : ['status', 'q', 'category_id', 'page', 'message_id'];

    $query = ['tab' => $tab];
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $params)) {
            continue;
        }
        $value = $params[$key];
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            continue;
        }
        $query[$key] = $value;
    }

    return 'contacts.php?' . http_build_query($query);
}

function adminContactsStatusMeta(array $labels, string $status): array
{
    return $labels[$status] ?? $labels['new'];
}

function adminContactsEmailStatusMeta(array $labels, string $status): array
{
    return $labels[$status] ?? $labels['pending'];
}

function adminContactsDate(string $value): string
{
    $timestamp = strtotime($value) ?: time();
    return date('d.m.Y H:i', $timestamp);
}

function adminContactsExcerpt(string $value, int $length = 160): string
{
    $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
    if ($value === '') {
        return '—';
    }

    return mb_strlen($value, 'UTF-8') > $length
        ? rtrim(mb_substr($value, 0, max(0, $length - 1), 'UTF-8')) . '…'
        : $value;
}

function adminContactsShortValue(string $value, int $length = 140): string
{
    $value = trim($value);
    if ($value === '') {
        return '—';
    }

    return mb_strlen($value, 'UTF-8') > $length
        ? rtrim(mb_substr($value, 0, max(0, $length - 1), 'UTF-8')) . '…'
        : $value;
}

function adminContactsMessageRowSeenAt(): string
{
    return date('Y-m-d H:i:s');
}

function adminContactsCategoryCounts(PDO $pdo): array
{
    $counts = [];

    try {
        $stmt = $pdo->query('SELECT category_id, COUNT(*) AS total FROM contact_messages GROUP BY category_id');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $categoryId = isset($row['category_id']) ? (int) $row['category_id'] : 0;
            if ($categoryId > 0) {
                $counts[$categoryId] = (int) ($row['total'] ?? 0);
            }
        }
    } catch (Throwable $exception) {
        error_log('[silent-catch] ' . $exception->getMessage());
    }

    return $counts;
}

$statusLabels = contactMessageStatusLabels();
$emailStatusLabels = contactMessageEmailStatusLabels();
$successMsg = get_flash('success');
$errorMsg = get_flash('error');
$infoMsg = get_flash('info');

$messageStats = [
    'total' => 0,
    'new' => 0,
    'replied' => 0,
    'resolved' => 0,
    'unseen' => 0,
];

$categories = [];
$categoryMessageCounts = [];
$selectedMessage = null;
$selectedCategory = null;
$selectedCategoryId = max(0, (int) ($_GET['category_id'] ?? 0));
$requestedMessageId = max(0, (int) ($_GET['message_id'] ?? 0));

$messageFilters = [
    'status' => trim((string) ($_GET['status'] ?? '')),
    'q' => trim((string) ($_GET['q'] ?? '')),
    'category_id' => max(0, (int) ($_GET['category_id'] ?? 0)),
];
$hasMessageFilters = $messageFilters['status'] !== '' || $messageFilters['q'] !== '' || $messageFilters['category_id'] > 0;
$messagePage = max(1, (int) ($_GET['page'] ?? 1));
$messagePerPage = adminPaginationPerPage();

if ($pdo instanceof PDO) {
    $messageStats = contactMessageStats($pdo);
    $categories = contactCategories($pdo, false);
    $categoryMessageCounts = adminContactsCategoryCounts($pdo);
}

$validMessageStatuses = array_keys($statusLabels);
if ($messageFilters['status'] !== '' && !in_array($messageFilters['status'], $validMessageStatuses, true)) {
    $messageFilters['status'] = '';
}

$messageCount = $pdo instanceof PDO ? contactMessageCount($pdo, $messageFilters) : 0;
$totalMessagePages = max(1, (int) ceil(max(1, $messageCount) / max(1, $messagePerPage)));
$messagePage = min($messagePage, $totalMessagePages);
$messageOffset = ($messagePage - 1) * $messagePerPage;
$messages = $pdo instanceof PDO
    ? contactMessages($pdo, array_merge($messageFilters, [
        'limit' => $messagePerPage,
        'offset' => $messageOffset,
    ]))
    : [];
$visibleMessageCount = count($messages);
$visibleMessageStart = $visibleMessageCount > 0 ? $messageOffset + 1 : 0;
$visibleMessageEnd = $visibleMessageCount > 0 ? min($messageOffset + $visibleMessageCount, $messageCount) : 0;

if ($pdo instanceof PDO) {
    $selectedMessage = $requestedMessageId > 0 ? contactMessage($pdo, $requestedMessageId) : null;
    if ($selectedMessage && empty($selectedMessage['seen_at'])) {
        if (contactMarkMessageSeen($pdo, (int) $selectedMessage['id'])) {
            $seenAt = adminContactsMessageRowSeenAt();
            $messageStats['unseen'] = max(0, (int) ($messageStats['unseen'] ?? 0) - 1);
            $selectedMessage['seen_at'] = $seenAt;
            foreach ($messages as &$message) {
                if ((int) ($message['id'] ?? 0) === (int) ($selectedMessage['id'] ?? 0)) {
                    $message['seen_at'] = $seenAt;
                    break;
                }
            }
            unset($message);
        }
    }
}

if ($selectedCategoryId > 0) {
    foreach ($categories as $category) {
        if ((int) ($category['id'] ?? 0) === $selectedCategoryId) {
            $selectedCategory = $category;
            break;
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token((string) ($_POST['_token'] ?? ''))) {
        flash('error', 'Güvenlik doğrulaması başarısız.');
        header('Location: ' . adminContactsUrl($activeTab, $messageFilters + ['page' => $messagePage, 'message_id' => $requestedMessageId, 'category_id' => $selectedCategoryId]));
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $actorId = (int) ($_SESSION['_auth_user_id'] ?? 0);
    $actorName = trim((string) ($_SESSION['_auth_user_name'] ?? 'Yönetim'));

    try {
        switch ($action) {
            case 'reply':
                adminRequirePermission('contact.manage', 'Mesaj yanıtlamak için gerekli izin hesabınıza tanımlanmamış.');
                $messageId = max(0, (int) ($_POST['message_id'] ?? 0));
                $replyBody = trim((string) ($_POST['reply_body'] ?? ''));
                if ($messageId <= 0 || $replyBody === '') {
                    throw new RuntimeException('Yanıt metni boş olamaz.');
                }
                $result = contactReplyToMessage($pdo, $messageId, $replyBody, $actorId, $actorName);
                if (!empty($result['success'])) {
                    flash(!empty($result['mail_sent']) ? 'success' : 'info', (string) ($result['message'] ?? 'Yanıt kaydedildi.'));
                } else {
                    flash('error', (string) ($result['message'] ?? 'Yanıt kaydedilemedi.'));
                }
                header('Location: ' . adminContactsUrl('messages', [
                    'status' => trim((string) ($_POST['status'] ?? $messageFilters['status'])),
                    'q' => trim((string) ($_POST['q'] ?? $messageFilters['q'])),
                    'category_id' => max(0, (int) ($_POST['category_id'] ?? $messageFilters['category_id'])),
                    'page' => max(1, (int) ($_POST['page'] ?? $messagePage)),
                    'message_id' => $messageId,
                ]));
                exit;

            case 'resolve':
                adminRequirePermission('contact.manage', 'Mesajı çözmek için gerekli izin hesabınıza tanımlanmamış.');
                $messageId = max(0, (int) ($_POST['message_id'] ?? 0));
                if ($messageId <= 0) {
                    throw new RuntimeException('Mesaj bulunamadı.');
                }
                if (!contactResolveMessage($pdo, $messageId)) {
                    throw new RuntimeException('Mesaj çözülemedi.');
                }
                flash('success', 'Mesaj çözüldü.');
                header('Location: ' . adminContactsUrl('messages', [
                    'status' => trim((string) ($_POST['status'] ?? $messageFilters['status'])),
                    'q' => trim((string) ($_POST['q'] ?? $messageFilters['q'])),
                    'category_id' => max(0, (int) ($_POST['category_id'] ?? $messageFilters['category_id'])),
                    'page' => max(1, (int) ($_POST['page'] ?? $messagePage)),
                    'message_id' => $messageId,
                ]));
                exit;

            case 'delete_message':
                adminRequirePermission('contact.manage', 'Mesaj silmek için gerekli izin hesabınıza tanımlanmamış.');
                $messageId = max(0, (int) ($_POST['message_id'] ?? 0));
                if ($messageId <= 0) {
                    throw new RuntimeException('Mesaj bulunamadı.');
                }
                if (!contactDeleteMessage($pdo, $messageId)) {
                    throw new RuntimeException('Mesaj kalıcı olarak silinemedi.');
                }
                flash('success', 'Mesaj kalıcı olarak silindi.');
                header('Location: ' . adminContactsUrl('messages', [
                    'status' => trim((string) ($_POST['status'] ?? $messageFilters['status'])),
                    'q' => trim((string) ($_POST['q'] ?? $messageFilters['q'])),
                    'category_id' => max(0, (int) ($_POST['category_id'] ?? $messageFilters['category_id'])),
                    'page' => max(1, (int) ($_POST['page'] ?? $messagePage)),
                ]));
                exit;

            case 'save_category':
                adminRequirePermission('contact.categories.manage', 'Kategori kaydetmek için gerekli izin hesabınıza tanımlanmamış.');
                $categoryId = max(0, (int) ($_POST['category_id'] ?? 0));
                $categoryInput = [
                    'name' => trim((string) ($_POST['name'] ?? '')),
                    'slug' => trim((string) ($_POST['slug'] ?? '')),
                    'icon' => trim((string) ($_POST['icon'] ?? 'bi-envelope-paper')),
                    'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 0)),
                    'is_active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                $result = contactSaveCategory($pdo, $categoryInput, $categoryId > 0 ? $categoryId : null);
                if (!empty($result['success'])) {
                    flash('success', (string) ($result['message'] ?? 'Kategori kaydedildi.'));
                    $savedId = (int) ($result['id'] ?? 0);
                    header('Location: ' . adminContactsUrl('categories', $savedId > 0 ? ['category_id' => $savedId] : []));
                    exit;
                }
                throw new RuntimeException((string) ($result['message'] ?? 'Kategori kaydedilemedi.'));

            case 'toggle_category':
                adminRequirePermission('contact.categories.manage', 'Kategori durumu değiştirmek için gerekli izin hesabınıza tanımlanmamış.');
                $categoryId = max(0, (int) ($_POST['category_id'] ?? 0));
                $active = max(0, (int) ($_POST['is_active'] ?? 0)) === 1;
                if ($categoryId <= 0 || !contactToggleCategory($pdo, $categoryId, $active)) {
                    throw new RuntimeException('Kategori durumu değiştirilemedi.');
                }
                flash('success', $active ? 'Kategori aktif edildi.' : 'Kategori pasif edildi.');
                header('Location: ' . adminContactsUrl('categories', ['category_id' => $categoryId]));
                exit;

            case 'delete_category':
                adminRequirePermission('contact.categories.manage', 'Kategori silmek için gerekli izin hesabınıza tanımlanmamış.');
                $categoryId = max(0, (int) ($_POST['category_id'] ?? 0));
                if ($categoryId <= 0 || !contactDeleteCategory($pdo, $categoryId)) {
                    throw new RuntimeException('Kategori silinemedi.');
                }
                flash('success', 'Kategori kalıcı olarak silindi.');
                header('Location: ' . adminContactsUrl('categories'));
                exit;

            default:
                throw new RuntimeException('Bilinmeyen işlem.');
        }
    } catch (Throwable $exception) {
        flash('error', safeErrorMessage($exception, 'İşlem sırasında bir hata oluştu.'));
        $redirectTab = in_array((string) ($_POST['tab'] ?? $activeTab), $allowedTabs, true) ? (string) ($_POST['tab'] ?? $activeTab) : $activeTab;
        header('Location: ' . adminContactsUrl($redirectTab, [
            'status' => trim((string) ($_POST['status'] ?? $messageFilters['status'])),
            'q' => trim((string) ($_POST['q'] ?? $messageFilters['q'])),
            'category_id' => max(0, (int) ($_POST['category_id'] ?? $messageFilters['category_id'])),
            'page' => max(1, (int) ($_POST['page'] ?? $messagePage)),
            'message_id' => max(0, (int) ($_POST['message_id'] ?? $requestedMessageId)),
        ]));
        exit;
    }
}

$selectedMessageStatusMeta = $selectedMessage ? adminContactsStatusMeta($statusLabels, (string) ($selectedMessage['status'] ?? 'new')) : $statusLabels['new'];
$selectedMessageEmailMeta = $selectedMessage ? adminContactsEmailStatusMeta($emailStatusLabels, (string) ($selectedMessage['admin_reply_email_status'] ?? 'pending')) : $emailStatusLabels['pending'];
$selectedMessageIsUnread = $selectedMessage ? empty($selectedMessage['seen_at']) : false;
$activeMessageCount = (int) ($messageStats['new'] ?? 0);
$categoryStats = [
    'total' => count($categories),
    'active' => count(array_filter($categories, static fn (array $category): bool => (int) ($category['is_active'] ?? 0) === 1)),
    'inactive' => count(array_filter($categories, static fn (array $category): bool => (int) ($category['is_active'] ?? 0) !== 1)),
    'used' => count(array_filter($categories, static fn (array $category): bool => ((int) ($categoryMessageCounts[(int) ($category['id'] ?? 0)] ?? 0)) > 0)),
];

require_once __DIR__ . '/header.php';
?>

<div class="contacts-page">
    <?= adminRenderAlert((string) $successMsg, 'success') ?>
    <?= adminRenderAlert((string) $infoMsg, 'info') ?>
    <?= adminRenderAlert((string) $errorMsg, 'danger') ?>

    <?= adminRenderPageHero('bi-envelope-paper', 'İletişim merkezi', 'İletişim Yönetimi', 'Tek seferlik şikayet ve iletişim mesajlarını, kategori bazlı şekilde takip edin. Yanıtlar e-posta olarak gider, konuşma geçmişi tutulmaz.', [], [
        'actions_html' => '<span class="ui-admin-badge ui-admin-badge-warning"><i class="bi bi-inbox"></i> ' . number_format($activeMessageCount, 0, ',', '.') . ' yeni mesaj</span>'
            . '<span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-envelope-check"></i> ' . number_format((int) ($messageStats['replied'] ?? 0), 0, ',', '.') . ' yanıtlandı</span>',
    ]) ?>

    <div class="ui-admin-tabs ui-admin-tabs-spaced">
        <a class="ui-admin-tab <?= $activeTab === 'messages' ? 'is-active' : '' ?>" href="<?= htmlspecialchars(adminContactsUrl('messages', $messageFilters + ['page' => $messagePage, 'message_id' => $requestedMessageId]), ENT_QUOTES, 'UTF-8') ?>"<?= $activeTab === 'messages' ? ' aria-current="page"' : '' ?>><i class="bi bi-inbox"></i> Mesajlar <span class="ui-admin-badge ui-admin-badge-danger ui-admin-badge-xs"><?= number_format($activeMessageCount, 0, ',', '.') ?></span></a>
        <a class="ui-admin-tab <?= $activeTab === 'categories' ? 'is-active' : '' ?>" href="<?= htmlspecialchars(adminContactsUrl('categories', $selectedCategoryId > 0 ? ['category_id' => $selectedCategoryId] : []), ENT_QUOTES, 'UTF-8') ?>"<?= $activeTab === 'categories' ? ' aria-current="page"' : '' ?>><i class="bi bi-tags"></i> Kategoriler <span class="ui-admin-badge ui-admin-badge-muted ui-admin-badge-xs"><?= number_format($categoryStats['total'], 0, ',', '.') ?></span></a>
    </div>

    <?php if ($activeTab === 'messages'): ?>
        <?= adminRenderStatCards([
            ['tone' => 'info', 'icon' => 'bi-envelope-paper', 'label' => 'Toplam', 'value' => number_format((int) ($messageStats['total'] ?? 0), 0, ',', '.')],
            ['tone' => 'danger', 'icon' => 'bi-inbox', 'label' => 'Yeni', 'value' => number_format((int) ($messageStats['new'] ?? 0), 0, ',', '.')],
            ['tone' => 'warning', 'icon' => 'bi-reply', 'label' => 'Yanıtlandı', 'value' => number_format((int) ($messageStats['replied'] ?? 0), 0, ',', '.')],
            ['tone' => 'success', 'icon' => 'bi-check2-circle', 'label' => 'Çözüldü', 'value' => number_format((int) ($messageStats['resolved'] ?? 0), 0, ',', '.')],
            ['tone' => 'info', 'icon' => 'bi-eye-slash', 'label' => 'Görülmedi', 'value' => number_format((int) ($messageStats['unseen'] ?? 0), 0, ',', '.')],
        ], ['class' => 'contacts-summary contacts-message-summary', 'aria_label' => 'İletişim mesaj özeti']) ?>

        <section class="ui-admin-premium-card contacts-message-list-panel ui-card">
            <div class="ui-admin-premium-card-header ui-panel__head ui-card contacts-message-panel-head">
                <div class="contacts-message-panel-head-copy">
                    <span class="contacts-message-kicker"><i class="bi bi-funnel"></i> Mesaj kuyruğu</span>
                    <strong>Mesajlar</strong>
                    <span>Arama, durum ve kategori filtreleriyle kayıtları daraltın.</span>
                </div>
                <div class="contacts-message-panel-head-summary">
                    <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-list"></i> <?= number_format($messageCount, 0, ',', '.') ?> kayıt</span>
                    <span class="ui-admin-badge ui-admin-badge-warning"><i class="bi bi-eye-slash"></i> <?= number_format((int) ($messageStats['unseen'] ?? 0), 0, ',', '.') ?> görülmemiş</span>
                    <span class="ui-admin-badge ui-admin-badge-info"><i class="bi bi-reply"></i> <?= number_format((int) ($messageStats['replied'] ?? 0), 0, ',', '.') ?> yanıtlanmış</span>
                </div>
            </div>
            <div class="ui-admin-premium-card-body ui-panel__body ui-card contacts-message-panel-body">
                <form method="get" action="contacts.php" class="contacts-message-filter-form admin-filter-form">
                    <input type="hidden" name="tab" value="messages">
                    <label class="contacts-message-filter-field contacts-message-filter-search" for="contactSearch">
                        <span>Ara</span>
                        <div class="contacts-message-filter-control">
                            <i class="bi bi-search"></i>
                            <input id="contactSearch" type="text" name="q" class="ui-admin-form-control" value="<?= htmlspecialchars($messageFilters['q'], ENT_QUOTES, 'UTF-8') ?>" placeholder="Ad, e-posta, konu veya mesaj">
                        </div>
                    </label>
                    <label class="contacts-message-filter-field" for="contactStatusFilter">
                        <span>Durum</span>
                        <select id="contactStatusFilter" name="status" class="ui-admin-form-control">
                            <option value="">Tümü</option>
                            <?php foreach ($statusLabels as $statusKey => $statusMeta): ?>
                                <option value="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"<?= $messageFilters['status'] === $statusKey ? ' selected' : '' ?>><?= htmlspecialchars((string) $statusMeta['label'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="contacts-message-filter-field" for="contactCategoryFilter">
                        <span>Kategori</span>
                        <select id="contactCategoryFilter" name="category_id" class="ui-admin-form-control">
                            <option value="0">Tümü</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"<?= (int) $messageFilters['category_id'] === (int) $category['id'] ? ' selected' : '' ?>>
                                    <?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="contacts-message-filter-actions">
                        <button type="submit" class="ui-admin-btn ui-admin-btn-primary ui-admin-btn-sm"><i class="bi bi-search"></i> Filtrele</button>
                        <a href="<?= htmlspecialchars(adminContactsUrl('messages'), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-x-lg"></i> Temizle</a>
                    </div>
                </form>

                <div class="contacts-message-results-bar">
                    <div class="contacts-message-results-copy">
                        <span class="contacts-message-results-kicker">Sonuç özeti</span>
                        <strong><?= $visibleMessageCount > 0 ? number_format($visibleMessageStart, 0, ',', '.') . ' - ' . number_format($visibleMessageEnd, 0, ',', '.') : '0' ?> / <?= number_format($messageCount, 0, ',', '.') ?> kayıt gösteriliyor</strong>
                        <span><?= $hasMessageFilters ? 'Filtreler aktif. Liste yalnızca eşleşen kayıtları gösteriyor.' : 'Tüm kayıtlar listeleniyor.' ?></span>
                    </div>
                    <div class="contacts-message-results-meta">
                        <?php if ($messageFilters['status'] !== ''): ?>
                            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi <?= htmlspecialchars((string) ($statusLabels[$messageFilters['status']]['icon'] ?? 'bi-funnel'), ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) ($statusLabels[$messageFilters['status']]['label'] ?? $messageFilters['status']), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($messageFilters['category_id'] > 0 && $selectedCategory): ?>
                            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi <?= htmlspecialchars((string) ($selectedCategory['icon'] ?? 'bi-tags'), ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) ($selectedCategory['name'] ?? 'Kategori'), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <?php if ($messageFilters['q'] !== ''): ?>
                            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi bi-search"></i> Arama aktif</span>
                        <?php endif; ?>
                        <?php if ($hasMessageFilters): ?>
                            <span class="ui-admin-badge ui-admin-badge-warning"><i class="bi bi-funnel"></i> Filtre aktif</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($messages === []): ?>
                    <?= adminRenderEmptyState([
                        'icon' => 'bi-inbox',
                        'tone' => 'info',
                        'title' => $hasMessageFilters ? 'Filtreye uyan mesaj yok' : 'Henüz mesaj yok',
                        'description' => $hasMessageFilters ? 'Seçili filtrelerle eşleşen bir kayıt bulamadım. Filtreleri sıfırlayıp yeniden deneyin.' : 'Yeni iletişim talepleri burada listelenecek. Şu anda gelen kutusu boş görünüyor.',
                        'pro' => true,
                        'class' => 'contacts-empty-state contacts-empty-state-list',
                        'meta' => array_values(array_filter([
                            ['icon' => 'bi-eye-slash', 'label' => number_format((int) ($messageStats['unseen'] ?? 0), 0, ',', '.') . ' görülmemiş'],
                            ['icon' => 'bi-reply', 'label' => number_format((int) ($messageStats['replied'] ?? 0), 0, ',', '.') . ' yanıtlanmış'],
                            $hasMessageFilters ? ['icon' => 'bi-funnel', 'label' => 'Filtre aktif'] : null,
                        ])),
                        'actions' => array_values(array_filter([
                            $hasMessageFilters ? ['href' => 'contacts.php?tab=messages', 'label' => 'Filtreleri temizle', 'icon' => 'bi-x-lg', 'class' => 'ui-admin-btn-primary ui-admin-btn-sm'] : null,
                            ['href' => adminContactsUrl('categories', $selectedCategoryId > 0 ? ['category_id' => $selectedCategoryId] : []), 'label' => 'Kategoriler', 'icon' => 'bi-tags', 'class' => 'ui-admin-btn-sm'],
                        ])),
                    ]) ?>
                <?php else: ?>
                    <?= adminRenderTableOpen([
                        'Durum',
                        'Gönderen',
                        'Konu',
                        'Kategori',
                        'Tarih',
                        ['label' => 'İşlem', 'class' => 'ui-admin-table-head-actions'],
                    ], [
                        'wrap_class' => 'ui-admin-table-wrap-x',
                        'label' => 'İletişim mesajları',
                    ]) ?>
                                <?php foreach ($messages as $message): ?>
                                    <?php
                                        $messageId = (int) ($message['id'] ?? 0);
                                        $messageStatusMeta = adminContactsStatusMeta($statusLabels, (string) ($message['status'] ?? 'new'));
                                        $messageEmailMeta = adminContactsEmailStatusMeta($emailStatusLabels, (string) ($message['admin_reply_email_status'] ?? 'pending'));
                                        $messageUrl = adminContactsUrl('messages', $messageFilters + [
                                            'page' => $messagePage,
                                            'message_id' => $messageId,
                                        ]);
                                    ?>
                                    <tr<?= $selectedMessage && (int) ($selectedMessage['id'] ?? 0) === $messageId ? ' class="contacts-message-row-selected"' : '' ?>>
                                        <td>
                                            <div class="ui-admin-stack ui-admin-stack-sm">
                                                <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars((string) $messageStatusMeta['class'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi <?= htmlspecialchars((string) $messageStatusMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) $messageStatusMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars((string) $messageEmailMeta['class'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi <?= htmlspecialchars((string) $messageEmailMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) $messageEmailMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (empty($message['seen_at'])): ?>
                                                    <span class="ui-admin-badge ui-admin-badge-danger ui-admin-badge-xs"><i class="bi bi-eye-slash"></i> Görülmedi</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="ui-admin-stack ui-admin-stack-sm">
                                                <strong><?= htmlspecialchars((string) ($message['sender_name_display'] ?? 'Anonim'), ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="ui-admin-table-cell-secondary"><?= htmlspecialchars((string) ($message['sender_email_display'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php if (!empty($message['is_member'])): ?>
                                                    <span class="ui-admin-badge ui-admin-badge-muted ui-admin-badge-xs"><i class="bi bi-person-check"></i> Üye</span>
                                                <?php else: ?>
                                                    <span class="ui-admin-badge ui-admin-badge-muted ui-admin-badge-xs"><i class="bi bi-person"></i> Misafir</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="<?= htmlspecialchars($messageUrl, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-table-cell-strong"><?= htmlspecialchars((string) ($message['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                                            <div class="ui-admin-table-cell-desc"><?= htmlspecialchars(adminContactsExcerpt((string) ($message['message'] ?? ''), 110), ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                        <td>
                                            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi <?= htmlspecialchars((string) ($message['category_icon_display'] ?? 'bi-envelope'), ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) ($message['category_name_display'] ?? 'Kategorisiz'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </td>
                                        <td class="ui-admin-table-cell-date"><?= htmlspecialchars(adminContactsDate((string) ($message['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="ui-admin-table-cell-actions">
                                            <div class="ui-admin-actions-inline">
                                                <a href="<?= htmlspecialchars($messageUrl, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-eye"></i> Aç</a>
                                                <?php if (function_exists('adminCurrentUserCan') && adminCurrentUserCan('contact.manage')): ?>
                                                <form method="post" action="<?= htmlspecialchars($messageUrl, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-inline-form"<?= adminConfirmAttrs(['message' => 'Bu mesaj kalıcı olarak silinecek. Devam edilsin mi?', 'title' => 'Mesaj silinsin mi?', 'ok' => 'Sil', 'tone' => 'danger']) ?>>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="delete_message">
                                                    <input type="hidden" name="tab" value="messages">
                                                    <input type="hidden" name="message_id" value="<?= (int) $messageId ?>">
                                                    <input type="hidden" name="status" value="<?= htmlspecialchars($messageFilters['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="q" value="<?= htmlspecialchars($messageFilters['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="category_id" value="<?= (int) ($messageFilters['category_id'] ?? 0) ?>">
                                                    <input type="hidden" name="page" value="<?= $messagePage ?>">
                                                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm"><i class="bi bi-trash"></i> Sil</button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                    <?= adminRenderTableClose() ?>

                    <?php if ($totalMessagePages > 1): ?>
                        <?php
                        echo adminRenderPagination($totalMessagePages, $messagePage, static function (int $targetPage) use ($messageFilters, $requestedMessageId): string {
                            $params = array_merge($messageFilters, ['page' => $targetPage]);
                            if ((int) $requestedMessageId > 0) {
                                $params['message_id'] = (int) $requestedMessageId;
                            }

                            return adminContactsUrl('messages', $params);
                        }, [
                            'wrapper_class' => 'ui-admin-pagination-center contacts-pagination',
                            'inner_class' => 'contacts-pagination-list',
                            'aria_label' => 'İletişim mesajları sayfalama',
                        ]);
                        ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($selectedMessage): ?>
            <div class="ui-admin-detail-overlay is-open" id="contactMessageModal" role="dialog" aria-modal="true" aria-labelledby="contactMessageModalTitle">
                <div class="ui-admin-detail-modal contacts-message-modal contacts-message-modal-shell">
                    <div class="ui-admin-detail-modal-head contacts-message-modal-head">
                        <h3 id="contactMessageModalTitle"><i class="bi bi-card-text"></i> Mesaj Detayı #<?= (int) $selectedMessage['id'] ?></h3>
                        <a href="<?= htmlspecialchars(adminContactsUrl('messages', $messageFilters + ['page' => $messagePage]), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-detail-close" data-ui-modal-close aria-label="Kapat"><i class="bi bi-x-lg"></i></a>
                    </div>
                    <div class="ui-admin-detail-modal-body contacts-message-modal-body">
                        <div class="ui-admin-stack ui-admin-stack-md">
                            <div class="ui-admin-stack ui-admin-stack-sm contacts-message-summary">
                                <div class="contacts-message-badges">
                                    <span class="ui-admin-badge ui-admin-badge-muted">#<?= (int) $selectedMessage['id'] ?></span>
                                    <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars((string) $selectedMessageStatusMeta['class'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi <?= htmlspecialchars((string) $selectedMessageStatusMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) $selectedMessageStatusMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars((string) $selectedMessageEmailMeta['class'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi <?= htmlspecialchars((string) $selectedMessageEmailMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) $selectedMessageEmailMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($selectedMessageIsUnread): ?>
                                        <span class="ui-admin-badge ui-admin-badge-danger"><i class="bi bi-eye-slash"></i> Görülmedi</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="contacts-message-title"><?= htmlspecialchars((string) ($selectedMessage['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="ui-admin-table-cell-desc contacts-message-note">Konu snapshot’ı üzerinden tek seferlik destek/şikayet kaydı.</p>
                            </div>

                            <div class="ui-admin-two-col contacts-message-meta-grid">
                                <div class="ui-admin-premium-card contacts-message-inline-card">
                                    <div class="ui-admin-premium-card-header ui-panel__head">
                                        <i class="bi bi-person"></i> Gönderen
                                    </div>
                                    <div class="ui-admin-premium-card-body ui-panel__body">
                                        <div class="ui-admin-stack ui-admin-stack-sm">
                                            <strong><?= htmlspecialchars((string) ($selectedMessage['sender_name_display'] ?? 'Anonim'), ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars((string) ($selectedMessage['sender_email_display'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="ui-admin-badge ui-admin-badge-muted ui-admin-badge-xs"><?= !empty($selectedMessage['is_member']) ? 'Üye' : 'Misafir' ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="ui-admin-premium-card contacts-message-inline-card">
                                    <div class="ui-admin-premium-card-header ui-panel__head">
                                        <i class="bi bi-tags"></i> Kategori
                                    </div>
                                    <div class="ui-admin-premium-card-body ui-panel__body">
                                        <div class="ui-admin-stack ui-admin-stack-sm">
                                            <span class="ui-admin-badge ui-admin-badge-muted"><i class="bi <?= htmlspecialchars((string) ($selectedMessage['category_icon_display'] ?? 'bi-envelope'), ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) ($selectedMessage['category_name_display'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="ui-admin-table-cell-secondary"><?= htmlspecialchars((string) ($selectedMessage['category_name_snapshot'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="ui-admin-stack ui-admin-stack-sm contacts-message-summary">
                                <strong>Mesaj</strong>
                                <div class="ui-admin-table-cell-desc contacts-message-body-text"><?= htmlspecialchars((string) ($selectedMessage['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>

                            <div class="ui-admin-form-grid-2 ui-grid">
                                <div class="ui-admin-field">
                                    <label class="ui-admin-form-label">Tarih</label>
                                    <div class="ui-admin-table-cell-secondary"><?= htmlspecialchars(adminContactsDate((string) ($selectedMessage['created_at'] ?? 'now')), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="ui-admin-field">
                                    <label class="ui-admin-form-label">Görüldü</label>
                                    <div class="ui-admin-table-cell-secondary"><?= !empty($selectedMessage['seen_at']) ? htmlspecialchars(adminContactsDate((string) $selectedMessage['seen_at']), ENT_QUOTES, 'UTF-8') : 'Henüz görülmedi' ?></div>
                                </div>
                                <div class="ui-admin-field">
                                    <label class="ui-admin-form-label">IP</label>
                                    <div class="ui-admin-table-cell-secondary"><?= htmlspecialchars((string) ($selectedMessage['submitted_ip'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <div class="ui-admin-field">
                                    <label class="ui-admin-form-label">Cihaz</label>
                                    <div class="ui-admin-table-cell-secondary"><?= htmlspecialchars(adminContactsShortValue((string) ($selectedMessage['submitted_user_agent'] ?? '—'), 120), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>

                            <div class="ui-admin-stack ui-admin-stack-sm">
                                <strong>Yönetici Yanıtı</strong>
                                <?php if (!empty($selectedMessage['admin_reply_body'])): ?>
                                    <div class="ui-admin-table-cell-desc contacts-message-body-text"><?= htmlspecialchars((string) $selectedMessage['admin_reply_body'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="ui-admin-stack ui-admin-stack-sm">
                                        <span class="ui-admin-badge ui-admin-badge-<?= htmlspecialchars((string) $selectedMessageEmailMeta['class'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi <?= htmlspecialchars((string) $selectedMessageEmailMeta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars((string) $selectedMessageEmailMeta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if (!empty($selectedMessage['admin_reply_sent_at'])): ?>
                                            <span class="ui-admin-table-cell-secondary"><?= htmlspecialchars(adminContactsDate((string) $selectedMessage['admin_reply_sent_at']), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                        <?php
                                        $replyAdminName = trim((string) ($selectedMessage['reply_admin_name_display'] ?? ''));
                                        $replyAdminId = (int) ($selectedMessage['admin_reply_admin_id'] ?? 0);
                                        ?>
                                        <?php if ($replyAdminName !== '' || $replyAdminId > 0): ?>
                                            <span class="ui-admin-table-cell-secondary">
                                                Yanıtlayan: <?= htmlspecialchars($replyAdminName !== '' ? $replyAdminName : ('ID #' . $replyAdminId), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($selectedMessage['admin_reply_email_error'])): ?>
                                            <div class="contacts-message-reply-alert">
                                                <i class="bi bi-exclamation-octagon"></i>
                                                <?= htmlspecialchars((string) $selectedMessage['admin_reply_email_error'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?= adminRenderEmptyState([
                                        'icon' => 'bi-reply',
                                        'tone' => 'info',
                                        'title' => 'Henüz yanıt yok',
                                        'description' => 'İsterseniz aşağıdan tek bir e-posta yanıtı gönderebilirsiniz.',
                                        'pro' => true,
                                        'class' => 'contacts-empty-state contacts-empty-state-compact',
                                    ]) ?>
                                <?php endif; ?>
                            </div>

                            <?php if (adminCurrentUserCan('contact.manage')): ?>
                                <form method="post" action="<?= htmlspecialchars(adminContactsUrl('messages', $messageFilters + ['page' => $messagePage, 'message_id' => (int) $selectedMessage['id']]), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-stack ui-admin-stack-md">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reply">
                                    <input type="hidden" name="tab" value="messages">
                                    <input type="hidden" name="message_id" value="<?= (int) $selectedMessage['id'] ?>">
                                    <input type="hidden" name="status" value="<?= htmlspecialchars($messageFilters['status'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="q" value="<?= htmlspecialchars($messageFilters['q'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="category_id" value="<?= (int) $messageFilters['category_id'] ?>">
                                    <input type="hidden" name="page" value="<?= $messagePage ?>">

                                    <div class="ui-admin-field">
                                        <label class="ui-admin-form-label" for="replyBody">Yanıt metni</label>
                                        <textarea id="replyBody" name="reply_body" class="ui-admin-form-control" rows="8" maxlength="5000" placeholder="Tek seferlik e-posta yanıtı..."><?= htmlspecialchars((string) ($selectedMessage['admin_reply_body'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>

                                    <div class="contacts-message-actions">
                                        <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-envelope-paper"></i> Yanıtı Gönder</button>
                                    </div>
                                </form>

                                <div class="contacts-message-actions contacts-message-actions-spaced">
                                    <form method="post" action="<?= htmlspecialchars(adminContactsUrl('messages', $messageFilters + ['page' => $messagePage, 'message_id' => (int) $selectedMessage['id']]), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="resolve">
                                        <input type="hidden" name="tab" value="messages">
                                        <input type="hidden" name="message_id" value="<?= (int) $selectedMessage['id'] ?>">
                                        <input type="hidden" name="status" value="<?= htmlspecialchars($messageFilters['status'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="q" value="<?= htmlspecialchars($messageFilters['q'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="category_id" value="<?= (int) $messageFilters['category_id'] ?>">
                                        <input type="hidden" name="page" value="<?= $messagePage ?>">
                                        <button type="submit" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-check2-circle"></i> Çözüldü</button>
                                    </form>

                                    <form method="post" action="<?= htmlspecialchars(adminContactsUrl('messages', $messageFilters + ['page' => $messagePage, 'message_id' => (int) $selectedMessage['id']]), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-inline-form"<?= adminConfirmAttrs(['message' => 'Bu mesaj kalıcı olarak silinecek. Devam edilsin mi?', 'title' => 'Mesaj silinsin mi?', 'ok' => 'Sil', 'tone' => 'danger']) ?>>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_message">
                                        <input type="hidden" name="tab" value="messages">
                                        <input type="hidden" name="message_id" value="<?= (int) $selectedMessage['id'] ?>">
                                        <input type="hidden" name="status" value="<?= htmlspecialchars($messageFilters['status'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="q" value="<?= htmlspecialchars($messageFilters['q'], ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="category_id" value="<?= (int) $messageFilters['category_id'] ?>">
                                        <input type="hidden" name="page" value="<?= $messagePage ?>">
                                        <button type="submit" class="ui-admin-btn ui-admin-btn-danger"><i class="bi bi-trash"></i> Kalıcı Sil</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <?= adminRenderAlert('Sadece görüntüleme yetkiniz var. Yanıt ve silme işlemleri kapalı.', 'info', ['icon' => 'bi-info-circle']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <?= adminRenderStatCards([
            ['tone' => 'info', 'icon' => 'bi-tags', 'label' => 'Toplam', 'value' => number_format($categoryStats['total'], 0, ',', '.')],
            ['tone' => 'success', 'icon' => 'bi-check-circle-fill', 'label' => 'Aktif', 'value' => number_format($categoryStats['active'], 0, ',', '.')],
            ['tone' => 'warning', 'icon' => 'bi-pause-circle', 'label' => 'Pasif', 'value' => number_format($categoryStats['inactive'], 0, ',', '.')],
            ['tone' => 'info', 'icon' => 'bi-chat-left-text', 'label' => 'Kullanılan', 'value' => number_format($categoryStats['used'], 0, ',', '.')],
        ], ['class' => 'contacts-summary contacts-category-summary', 'aria_label' => 'İletişim kategori özeti']) ?>

        <div class="ui-admin-two-col">
            <section class="ui-admin-premium-card ui-card">
                <div class="ui-admin-premium-card-header ui-panel__head ui-card">
                    <i class="bi bi-tags"></i> Kategoriler
                </div>
                <div class="ui-admin-premium-card-body ui-panel__body ui-card">
                    <p class="ui-admin-table-cell-desc contacts-section-copy">Kategoriler public formda listelenir. Silinen kayıtların adı ve ikonu mesajların içinde snapshot olarak kalır.</p>

                    <?php if ($categories === []): ?>
                        <?= adminRenderEmptyState([
                            'icon' => 'bi-tags',
                            'tone' => 'info',
                            'title' => 'Henüz kategori yok',
                            'description' => 'Mesajları gruplamak için ilk kategoriyi oluşturun. İkon, sıra ve aktif/pasif durumu bu panelden yönetilir.',
                            'pro' => true,
                            'class' => 'contacts-empty-state contacts-empty-state-list',
                            'meta' => [
                                ['icon' => 'bi-diagram-3', 'label' => 'Sıralı yapı'],
                                ['icon' => 'bi-icons', 'label' => 'Bootstrap ikonları'],
                            ],
                            'actions' => adminCurrentUserCan('contact.categories.manage')
                                ? [['href' => '#categoryName', 'label' => 'Forma git', 'icon' => 'bi-arrow-down', 'class' => 'ui-admin-btn-primary ui-admin-btn-sm']]
                                : [],
                        ]) ?>
                    <?php else: ?>
                        <?= adminRenderTableOpen([
                            'İkon',
                            'Ad',
                            'Slug',
                            'Sıra',
                            'Durum',
                            'Mesaj',
                            ['label' => 'İşlem', 'class' => 'ui-admin-table-head-actions'],
                        ], [
                            'wrap_class' => 'ui-admin-table-wrap-x',
                            'label' => 'İletişim kategorileri',
                        ]) ?>
                                    <?php foreach ($categories as $category): ?>
                                        <?php
                                            $categoryId = (int) ($category['id'] ?? 0);
                                            $categoryUrl = adminContactsUrl('categories', ['category_id' => $categoryId]);
                                            $messageCount = (int) ($categoryMessageCounts[$categoryId] ?? 0);
                                        ?>
                                        <tr<?= $selectedCategory && $selectedCategoryId === $categoryId ? ' class="ui-admin-row-pending"' : '' ?>>
                                            <td><span class="ui-admin-badge ui-admin-badge-muted"><i class="bi <?= htmlspecialchars((string) ($category['icon'] ?? 'bi-envelope-paper'), ENT_QUOTES, 'UTF-8') ?>"></i></span></td>
                                            <td class="ui-admin-table-cell-strong"><?= htmlspecialchars((string) ($category['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="ui-admin-table-cell-secondary"><?= htmlspecialchars((string) ($category['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= (int) ($category['sort_order'] ?? 0) ?></td>
                                            <td>
                                                <span class="ui-admin-badge <?= (int) ($category['is_active'] ?? 0) === 1 ? 'ui-admin-badge-success' : 'ui-admin-badge-muted' ?>">
                                                    <?= (int) ($category['is_active'] ?? 0) === 1 ? 'Aktif' : 'Pasif' ?>
                                                </span>
                                            </td>
                                            <td><span class="ui-admin-count-pill"><?= number_format($messageCount, 0, ',', '.') ?></span></td>
                                            <td class="ui-admin-table-cell-actions">
                                                <?php if (adminCurrentUserCan('contact.categories.manage')): ?>
                                                    <div class="ui-admin-actions-inline">
                                                        <a href="<?= htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi bi-pencil"></i> Düzenle</a>
                                                        <form method="post" action="<?= htmlspecialchars(adminContactsUrl('categories', ['category_id' => $categoryId]), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-inline-form">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="toggle_category">
                                                            <input type="hidden" name="tab" value="categories">
                                                            <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                                                            <input type="hidden" name="is_active" value="<?= (int) ($category['is_active'] ?? 0) === 1 ? 0 : 1 ?>">
                                                            <button type="submit" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm"><i class="bi <?= (int) ($category['is_active'] ?? 0) === 1 ? 'bi-pause-circle' : 'bi-play-circle' ?>"></i> <?= (int) ($category['is_active'] ?? 0) === 1 ? 'Pasif' : 'Aktif' ?></button>
                                                        </form>
                                                        <form method="post" action="<?= htmlspecialchars(adminContactsUrl('categories'), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-inline-form"<?= adminConfirmAttrs(['message' => 'Bu kategori kalıcı olarak silinecek. Mesajlardaki kategori adı ve ikonu korunur. Devam edilsin mi?', 'title' => 'Kategori silinsin mi?', 'ok' => 'Sil', 'tone' => 'danger']) ?>>
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="delete_category">
                                                            <input type="hidden" name="tab" value="categories">
                                                            <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                                                            <button type="submit" class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="ui-admin-badge ui-admin-badge-muted ui-admin-badge-xs"><i class="bi bi-eye"></i> Görüntüleme</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                        <?= adminRenderTableClose() ?>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="ui-admin-premium-card ui-admin-sticky-panel ui-card ui-panel">
                <div class="ui-admin-premium-card-header ui-panel__head ui-card">
                    <i class="bi bi-sliders"></i> Kategori Formu
                </div>
                <div class="ui-admin-premium-card-body ui-panel__body ui-card">
                    <?php if (!adminCurrentUserCan('contact.categories.manage')): ?>
                        <?= adminRenderEmptyState([
                            'icon' => 'bi-lock',
                            'tone' => 'info',
                            'title' => 'Yetki yok',
                            'description' => 'Kategori düzenleme ve silme işlemleri kapalı.',
                            'pro' => true,
                            'class' => 'contacts-empty-state contacts-empty-state-compact',
                        ]) ?>
                    <?php else: ?>
                        <form method="post" action="<?= htmlspecialchars(adminContactsUrl('categories', $selectedCategoryId > 0 ? ['category_id' => $selectedCategoryId] : []), ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-form ui-stack">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="save_category">
                            <input type="hidden" name="tab" value="categories">
                            <input type="hidden" name="category_id" value="<?= $selectedCategoryId > 0 ? $selectedCategoryId : 0 ?>">

                            <div class="ui-admin-field">
                                <label class="ui-admin-form-label" for="categoryName">Ad</label>
                                <input id="categoryName" type="text" name="name" class="ui-admin-form-control" maxlength="160" required value="<?= htmlspecialchars((string) ($selectedCategory['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Destek, Şikayet, DMCA...">
                            </div>

                            <div class="ui-admin-field">
                                <label class="ui-admin-form-label" for="categorySlug">Slug</label>
                                <input id="categorySlug" type="text" name="slug" class="ui-admin-form-control" maxlength="160" value="<?= htmlspecialchars((string) ($selectedCategory['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="destek">
                            </div>

                            <div class="ui-admin-field">
                                <label class="ui-admin-form-label" for="categoryIcon">İkon sınıfı</label>
                                <input id="categoryIcon" type="text" name="icon" class="ui-admin-form-control" maxlength="80" value="<?= htmlspecialchars((string) ($selectedCategory['icon'] ?? 'bi-envelope-paper'), ENT_QUOTES, 'UTF-8') ?>" placeholder="bi-headset">
                                <div class="ui-admin-table-cell-secondary">Bootstrap icon sınıfı kullanın. Örnek: <code>bi-headset</code>, <code>bi-megaphone</code>, <code>bi-shield-lock</code>.</div>
                            </div>

                            <div class="ui-admin-form-grid-2 ui-grid">
                                <div class="ui-admin-field">
                                    <label class="ui-admin-form-label" for="categorySort">Sıra</label>
                                    <input id="categorySort" type="number" name="sort_order" class="ui-admin-form-control" min="0" value="<?= htmlspecialchars((string) ($selectedCategory['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="ui-admin-field">
                                    <label class="ui-admin-form-label">Durum</label>
                                    <label class="ui-admin-switch">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) ($selectedCategory['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                                        <span class="ui-admin-switch-label">Aktif</span>
                                    </label>
                                </div>
                            </div>

                            <div class="contacts-message-actions">
                                <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-save"></i> <?= $selectedCategoryId > 0 ? 'Güncelle' : 'Kategori Ekle' ?></button>
                                <a href="contacts.php?tab=categories" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-eraser"></i> Temizle</a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
