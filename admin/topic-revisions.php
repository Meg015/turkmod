<?php

declare(strict_types=1);

$pageTitle = 'Konu Versiyonlari';
require_once __DIR__ . '/init.php';
adminRequirePermission('topics.view', 'Konu revizyonlarini goruntulemek icin gerekli izin hesabiniza tanimlanmamis.');

topicRevisionEnsureSchema($pdo);

$topicId = max(0, (int)($_GET['topic_id'] ?? 0));
$revisionId = max(0, (int)($_GET['revision_id'] ?? 0));

if ($revisionId > 0 && $topicId <= 0) {
    $revisionForTopic = topicRevisionFind($pdo, $revisionId);
    if ($revisionForTopic) {
        $topicId = (int)$revisionForTopic['topic_id'];
    }
}

$topic = null;
if ($topicId > 0) {
    $stmt = $pdo->prepare("SELECT t.*, cat.name AS category_name
                           FROM topics t
                           LEFT JOIN categories cat ON cat.id = t.category_id
                           WHERE t.id = ? AND t.deleted_at IS NULL
                           LIMIT 1");
    $stmt->execute([$topicId]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$topic) {
    flash('error', 'Konu bulunamadi.');
    header('Location: topics.php');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!verify_csrf_token($_POST['_token'] ?? '')) {
        flash('error', 'Guvenlik hatasi.');
        header('Location: topic-revisions.php?topic_id=' . $topicId);
        exit;
    }

    $action = (string)($_POST['action'] ?? '');
    $postRevisionId = max(0, (int)($_POST['revision_id'] ?? 0));
    if ($action === 'restore' && $postRevisionId > 0) {
        try {
            $pdo->beginTransaction();
            $restoredTopicId = topicRevisionRestore($pdo, $postRevisionId, (int)($_SESSION['_auth_user_id'] ?? 0));
            $pdo->commit();
            logActivity($pdo, 'topic_revision_restored', 'topic', $restoredTopicId, ['revision_id' => $postRevisionId]);
            flash('success', 'Konu secilen versiyona geri alindi.');
            header('Location: edit.php?id=' . $restoredTopicId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('error', safeErrorMessage($e, 'Revizyon geri yuklenemedi.'));
            header('Location: topic-revisions.php?topic_id=' . $topicId . '&revision_id=' . $postRevisionId);
            exit;
        }
    }
}

$revisions = topicRevisionList($pdo, $topicId);
$selectedRevision = $revisionId > 0 ? topicRevisionFind($pdo, $revisionId) : ($revisions[0] ?? null);
if ($selectedRevision && (int)$selectedRevision['topic_id'] !== $topicId) {
    $selectedRevision = null;
}

$links = [];
$media = [];
if ($selectedRevision) {
    $decodedLinks = json_decode((string)($selectedRevision['links_json'] ?? '[]'), true);
    $decodedMedia = json_decode((string)($selectedRevision['media_json'] ?? '[]'), true);
    $links = is_array($decodedLinks) ? $decodedLinks : [];
    $media = is_array($decodedMedia) ? $decodedMedia : [];
}

require_once __DIR__ . '/header.php';
?>
<div class="ui-admin-page-actions ui-admin-page-actions-row">
    <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="edit.php?id=<?= (int)$topicId ?>"><i class="bi bi-pencil"></i> Duzenlemeye Don</a>
    <a class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" href="topics.php"><i class="bi bi-files"></i> Konular</a>
</div>

<?= adminRenderPanelOpen([
    'tag' => 'div',
    'class' => 'ui-admin-mb-sm',
    'icon' => 'bi-clock-history',
    'title' => (string) $topic['title'],
]) ?>
        <div class="revision-grid">
            <div class="revision-field"><span>Kategori</span><?= htmlspecialchars((string)($topic['category_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="revision-field"><span>Mevcut durum</span><?= htmlspecialchars((string)$topic['status'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
<?= adminRenderPanelClose('div') ?>

<div class="revision-shell">
    <?= adminRenderPanelOpen(['title' => 'Kayitli Versiyonlar (' . count($revisions) . ')']) ?>
            <?php if (!$revisions): ?>
                <?= adminRenderEmptyState([
                    'icon' => 'bi-clock-history',
                    'tone' => 'info',
                    'title' => 'Versiyon kaydı yok',
                    'description' => 'Bu konu için henüz geçmiş sürüm oluşturulmamış.',
                ]) ?>
            <?php else: ?>
                <div class="revision-list">
                    <?php foreach ($revisions as $revision): ?>
                        <?php $isActive = $selectedRevision && (int)$selectedRevision['id'] === (int)$revision['id']; ?>
                        <a class="revision-item <?= $isActive ? 'active' : '' ?>" href="topic-revisions.php?topic_id=<?= (int)$topicId ?>&revision_id=<?= (int)$revision['id'] ?>">
                            <strong>#<?= (int)$revision['revision_number'] ?> - <?= htmlspecialchars((string)$revision['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <div class="revision-meta">
                                <?= htmlspecialchars((string)($revision['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($revision['actor_name'])): ?>
                                    - <?= htmlspecialchars((string)$revision['actor_name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
    <?= adminRenderPanelClose() ?>

    <?php
    $revisionActionsHtml = '';
    if ($selectedRevision) {
        ob_start();
        ?>
                <form method="post"<?= adminConfirmAttrs(['message' => 'Bu konuyu secilen eski versiyona geri almak istiyor musunuz?', 'title' => 'Eski versiyona dönülsün mü?', 'ok' => 'Geri Al', 'tone' => 'danger']) ?>>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="restore">
                    <input type="hidden" name="revision_id" value="<?= (int)$selectedRevision['id'] ?>">
                    <button class="ui-admin-btn ui-admin-btn-danger ui-admin-btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Bu Versiyona Don</button>
                </form>
        <?php
        $revisionActionsHtml = ob_get_clean();
    }
    ?>
    <?= adminRenderPanelOpen([
        'title' => 'Versiyon Detayi',
        'actions_html' => $revisionActionsHtml,
    ]) ?>
            <?php if (!$selectedRevision): ?>
                <?= adminRenderEmptyState([
                    'icon' => 'bi-file-earmark-text',
                    'tone' => 'info',
                    'title' => 'Görüntülenecek versiyon yok',
                    'description' => 'Bir versiyon seçtiğinizde detaylar burada açılacak.',
                ]) ?>
            <?php else: ?>
                <div class="revision-grid ui-admin-mb-sm">
                    <div class="revision-field"><span>Baslik</span><?= htmlspecialchars((string)$selectedRevision['title'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="revision-field"><span>Slug</span><?= htmlspecialchars((string)$selectedRevision['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="revision-field"><span>Yapimci</span><?= htmlspecialchars((string)($selectedRevision['author_topic'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="revision-field"><span>Surum</span><?= htmlspecialchars((string)($selectedRevision['topic_version'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="revision-field"><span>Durum</span><?= htmlspecialchars((string)$selectedRevision['status'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="revision-field"><span>Kaydeden</span><?= htmlspecialchars((string)($selectedRevision['actor_name'] ?? 'Sistem'), ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <h3 class="ui-admin-section-title-sm">Icerik</h3>
                <div class="revision-content ui-admin-mb-sm">
                    <?= sanitizeTopicHtml((string)($selectedRevision['topic_descriptions'] ?? '')) ?>
                </div>

                <h3 class="ui-admin-section-title-sm">Indirme Baglantilari</h3>
                <?= adminRenderTableOpen(['Ad', 'URL'], [
                    'class' => 'revision-table ui-admin-mb-sm',
                    'wrap_class' => 'revision-table-wrap',
                    'label' => 'Revizyon indirme bağlantıları',
                ]) ?>
                        <?php if (!$links): ?>
                            <?= adminRenderTableEmptyRow(2, [
                                'icon' => 'bi-link-45deg',
                                'tone' => 'info',
                                'title' => 'Kayit yok.',
                                'description' => 'Bu revizyonda indirme bağlantısı bulunmuyor.',
                            ]) ?>
                        <?php else: ?>
                            <?php foreach ($links as $link): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($link['name'] ?? 'Link'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($link['url'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                <?= adminRenderTableClose() ?>

                <h3 class="ui-admin-section-title-sm">Medya Snapshot</h3>
                <?= adminRenderTableOpen(['Tur', 'Yol', 'Birincil'], [
                    'class' => 'revision-table',
                    'wrap_class' => 'revision-table-wrap',
                    'label' => 'Revizyon medya snapshot',
                ]) ?>
                        <?php if (!$media): ?>
                            <?= adminRenderTableEmptyRow(3, [
                                'icon' => 'bi-images',
                                'tone' => 'info',
                                'title' => 'Kayit yok.',
                                'description' => 'Bu revizyonda medya snapshot kaydı bulunmuyor.',
                            ]) ?>
                        <?php else: ?>
                            <?php foreach ($media as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($item['type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($item['path'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= !empty($item['is_primary']) ? 'Evet' : 'Hayir' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                <?= adminRenderTableClose() ?>
            <?php endif; ?>
    <?= adminRenderPanelClose() ?>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
