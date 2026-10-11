<?php
// List view: variables are supplied by topics.php.
?>
<form id="bulkTopicsForm" method="post" action="topics.php">
    <?= csrf_field() ?>
</form>

<?php
$hasTopicFilters = $search !== '' || $statusFilter !== '' || $viewFilter === 'deleted' || $sortFilter !== 'newest';
$topicQuery = ['q' => $search, 'sort' => $sortFilter];
$topicQuery = array_filter($topicQuery, static fn ($value): bool => $value !== '');
$topicHref = static function (array $changes = []) use ($topicQuery): string {
    $query = array_merge($topicQuery, $changes);
    $query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== null);
    return 'topics.php' . ($query ? '?' . http_build_query($query) : '');
};
?>
<section class="topics-list-shell" aria-label="Konu listesi">
    <div class="topics-toolbar">
        <form method="get" action="topics.php" class="topics-filter-form">
            <label class="topics-search-field">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span class="topics-sr-only">Konu ara</span>
                <input type="search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Başlık, slug veya kategori ara...">
            </label>
            <input type="hidden" name="view" value="<?= htmlspecialchars($viewFilter, ENT_QUOTES, 'UTF-8') ?>">
            <label class="topics-select-field">
                <span class="topics-sr-only">Durum</span>
                <select name="status">
                    <option value="">Tüm durumlar</option>
                    <?php foreach ($allowedStatuses as $statusOption): ?>
                        <option value="<?= htmlspecialchars($statusOption) ?>" <?= $statusFilter === $statusOption ? 'selected' : '' ?>><?= htmlspecialchars($statusLabels[$statusOption][0]) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="topics-select-field">
                <span class="topics-sr-only">Sıralama</span>
                <select name="sort">
                    <option value="newest" <?= $sortFilter === 'newest' ? 'selected' : '' ?>>En yeni</option>
                    <option value="updated" <?= $sortFilter === 'updated' ? 'selected' : '' ?>>Son güncellenen</option>
                    <option value="oldest" <?= $sortFilter === 'oldest' ? 'selected' : '' ?>>En eski</option>
                </select>
            </label>
            <button type="submit" class="topics-button">Uygula</button>
            <?php if ($hasTopicFilters): ?><a href="topics.php" class="topics-filter-reset">Temizle</a><?php endif; ?>
        </form>
    </div>

    <nav class="topics-status-tabs" aria-label="Konu durumu filtreleri">
        <a href="<?= htmlspecialchars($topicHref(['status' => '', 'view' => 'active', 'page' => '']), ENT_QUOTES, 'UTF-8') ?>" class="<?= $viewFilter !== 'deleted' && $statusFilter === '' ? 'is-active' : '' ?>">Tümü <span><?= number_format((int) $stats['total'], 0, ',', '.') ?></span></a>
        <a href="<?= htmlspecialchars($topicHref(['status' => 'published', 'view' => 'active', 'page' => '']), ENT_QUOTES, 'UTF-8') ?>" class="<?= $viewFilter !== 'deleted' && $statusFilter === 'published' ? 'is-active' : '' ?>">Yayında <span><?= number_format((int) $stats['published'], 0, ',', '.') ?></span></a>
        <a href="<?= htmlspecialchars($topicHref(['status' => 'draft', 'view' => 'active', 'page' => '']), ENT_QUOTES, 'UTF-8') ?>" class="<?= $viewFilter !== 'deleted' && $statusFilter === 'draft' ? 'is-active' : '' ?>">Taslak <span><?= number_format((int) $stats['draft'], 0, ',', '.') ?></span></a>
        <a href="<?= htmlspecialchars($topicHref(['status' => '', 'view' => 'deleted', 'page' => '']), ENT_QUOTES, 'UTF-8') ?>" class="<?= $viewFilter === 'deleted' ? 'is-active is-danger' : '' ?>">Çöp kutusu <span><?= number_format((int) $stats['deleted'], 0, ',', '.') ?></span></a>
    </nav>

    <div class="topics-selection-bar" data-topic-bulk-bar hidden>
        <strong id="topicsBulkTitle" aria-live="polite">Konu seçilmedi</strong>
        <div class="topics-selection-actions">
            <select name="bulk_action" form="bulkTopicsForm" id="bulkTopicAction" aria-label="Toplu işlem">
                <option value="">Toplu işlem seç...</option>
                <?php if ($viewFilter === 'deleted'): ?>
                    <option value="restore">Geri yükle</option>
                    <option value="purge">Kalıcı sil</option>
                <?php else: ?>
                    <option value="publish">Yayına al</option>
                    <option value="draft">Taslak yap</option>
                    <option value="delete">Çöpe taşı</option>
                <?php endif; ?>
            </select>
            <button type="submit" form="bulkTopicsForm" class="topics-button topics-button-primary" id="bulkTopicApply" disabled>Uygula</button>
            <button type="button" class="topics-selection-clear" id="clearTopicSelection" aria-label="Seçimi temizle"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
    </div>

<div class="topics-list-table-wrap" data-ui-table="topics">
<table class="topics-list-table" aria-label="Konu listesi">
    <colgroup><col class="topics-col-check"><col class="topics-col-topic"><col class="topics-col-status"><col class="topics-col-author"><col class="topics-col-date"><col class="topics-col-actions"></colgroup>
    <thead><tr>
        <th scope="col" class="topics-cell-check"><input type="checkbox" id="selectAllTopics" class="chk-styled" aria-label="Görünen tüm konuları seç" <?= empty($topics) ? 'disabled' : '' ?>></th>
        <th scope="col">Konu</th><th scope="col" class="topics-cell-status">Durum</th><th scope="col" class="topics-cell-author">Yazar</th><th scope="col" class="topics-cell-date">Tarih</th><th scope="col" class="topics-cell-actions">İşlemler</th>
    </tr></thead>
    <tbody>
            <?php if (empty($topics)): ?>
                <?= adminRenderTableEmptyRow(6, [
                        'icon' => 'bi-search',
                        'tone' => 'info',
                        'title' => 'Konu bulunamadı',
                        'description' => 'Seçili filtrelerle eşleşen konu yok. Filtreleri temizleyip tekrar deneyebilirsiniz.',
                        'actions' => [
                            ['href' => 'topics.php', 'label' => 'Filtreleri Temizle', 'icon' => 'bi-x-circle'],
                            ['href' => $baseUri . '/admin/create.php', 'label' => 'Yeni Konu', 'icon' => 'bi-plus-circle', 'class' => 'ui-admin-btn-primary'],
                        ],
                    ]) ?>
            <?php else: ?>
                <?php foreach ($topics as $t): ?>
                    <?php
                    $rawStatusKey = (string) ($t['status'] ?? 'draft');
                    $statusKey = match ($rawStatusKey) {
                        'pending' => 'draft',
                        'archived' => 'published',
                        default => $rawStatusKey,
                    };
                    $statusMeta = function_exists('adminStatusMeta')
                        ? adminStatusMeta($statusKey, 'topic')
                        : [
                            'label' => $statusLabels[$statusKey][0] ?? ucfirst($statusKey),
                            'tone' => $statusLabels[$statusKey][1] ?? 'secondary',
                            'icon' => '',
                        ];
                    $isDeleted = !empty($t['deleted_at']);
                    $displayDate = $t['published_at'] ?: ($t['updated_at'] ?: ($t['created_at'] ?? null));
                    $coverPath = adminSafeImageUrl((string) ($t['cover_image'] ?? ''), $baseUri);
                    $authorName = $t['author_name'] ?? 'Sistem';
                    $authorInitial = mb_substr($authorName, 0, 1, 'UTF-8');
                    $badgeLabel = $isDeleted ? 'Silinmiş' : (string) ($statusLabels[$statusKey][0] ?? $statusMeta['label'] ?? ucfirst($statusKey));
                    $badgeTone = $isDeleted ? 'muted' : (string) ($statusMeta['tone'] ?? 'muted');
                    $statusBadge = '<span class="topics-status-badge is-' . adminUiEscape($badgeTone) . '">' . adminUiEscape($badgeLabel) . '</span>';
                    $moderationNote = '';
                    if (!empty($t['moderation_flags'])) {
                        $moderationFlags = json_decode((string)$t['moderation_flags'], true);
                        if (is_array($moderationFlags)) {
                            $moderationNote = trim((string)($moderationFlags['note'] ?? ''));
                        }
                    }
                    ?>
                    <tr class="topics-editable-row<?= $isDeleted ? ' is-deleted' : '' ?>" data-topic-edit-url="<?= $baseUri ?>/admin/edit.php?id=<?= (int) $t['id'] ?>" tabindex="0" title="Düzenleme sayfasına git">
                        <td class="topics-cell-check">
                            <input type="checkbox" name="topic_ids[]" form="bulkTopicsForm" value="<?= (int) $t['id'] ?>" class="topic-row-checkbox chk-styled" aria-label="<?= htmlspecialchars((string) ($t['title'] ?? 'Başlıksız konu') . ' konusunu seç', ENT_QUOTES, 'UTF-8') ?>">
                        </td>
                        <td>
                            <div class="topic-meta-wrap">
                                <?php if($coverPath): ?>
                                    <img src="<?= htmlspecialchars($coverPath) ?>" alt="" class="topic-thumbnail" width="48" height="48">
                                <?php else: ?>
                                    <?= adminRenderImagePlaceholder('topic-thumbnail topic-thumbnail-empty') ?>
                                <?php endif; ?>
                                <div class="topics-title-copy">
                                    <a class="topic-title-link" href="<?= $baseUri ?>/admin/edit.php?id=<?= (int) $t['id'] ?>"><?= htmlspecialchars((string) ($t['title'] ?? 'Başlıksız konu')) ?></a>
                                    <span class="topic-category"><i class="bi bi-folder2-open" aria-hidden="true"></i><?= htmlspecialchars((string) ($t['category'] ?? 'Genel')) ?></span>
                                    <span class="topics-mobile-status"><?= $statusBadge ?></span>
                                    <?php if ($moderationNote !== ''): ?>
                                        <button type="button" class="topics-note-link" data-moderation-note-open data-moderation-note="<?= htmlspecialchars($moderationNote, ENT_QUOTES, 'UTF-8') ?>" data-moderation-topic="<?= htmlspecialchars((string) ($t['title'] ?? 'Başlıksız konu'), ENT_QUOTES, 'UTF-8') ?>" aria-label="Son moderasyon notunu görüntüle">
                                            <i class="bi bi-chat-left-text"></i>
                                            <span>Son moderasyon notu</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="topics-cell-status"><?= $statusBadge ?></td>
                        <td class="topics-cell-author">
                            <div class="topics-author">
                                <div class="topics-author-avatar" aria-hidden="true">
                                    <?= htmlspecialchars(mb_strtoupper($authorInitial, 'UTF-8')) ?>
                                </div>
                                <span class="topics-author-name"><?= htmlspecialchars($authorName) ?></span>
                            </div>
                        </td>
                        <td class="topics-cell-date">
                            <?= $displayDate ? htmlspecialchars(date('d.m.Y', strtotime((string) $displayDate))) : '-' ?>
                            <?php if ($displayDate): ?><span><?= htmlspecialchars(date('H:i', strtotime((string) $displayDate))) ?></span><?php endif; ?>
                        </td>
                        <td class="topics-cell-actions">
                            <div class="topic-row-actions">
                                <a href="<?= $baseUri ?>/admin/edit.php?id=<?= (int) $t['id'] ?>" class="btn-icon-minimal edit" title="Düzenle" aria-label="Düzenle"><i class="bi bi-pencil"></i></a>
                                <button type="button" class="btn-icon-minimal" popovertarget="topicActions<?= (int) $t['id'] ?>" aria-label="<?= htmlspecialchars((string) $t['title'] . ': diğer işlemler', ENT_QUOTES, 'UTF-8') ?>" title="Diğer işlemler"><i class="bi bi-three-dots"></i></button>
                                <div class="topic-actions-popover" id="topicActions<?= (int) $t['id'] ?>" popover="auto" aria-label="Konu işlemleri">
                                <?php if (!$isDeleted): ?>
                                    <?php if ($statusKey !== 'published'): ?>
                                        <form action="topics.php" method="post" class="ui-admin-inline-form-block">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="moderation_action">
                                            <input type="hidden" name="topic_id" value="<?= (int) $t['id'] ?>">
                                            <input type="hidden" name="decision" value="approve">
                                            <button type="submit" class="topic-action-item"><i class="bi bi-check2" aria-hidden="true"></i>Onayla ve yayınla</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="topics.php" method="post" class="ui-admin-inline-form-block">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="moderation_action">
                                            <input type="hidden" name="topic_id" value="<?= (int) $t['id'] ?>">
                                            <input type="hidden" name="decision" value="draft">
                                            <button type="submit" class="topic-action-item"><i class="bi bi-pause-circle" aria-hidden="true"></i>Taslağa al</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($statusKey !== 'rejected' && $statusKey !== 'published'): ?>
                                        <form action="topics.php" method="post" class="ui-admin-inline-form-block" data-moderation-note-form data-moderation-note-title="Reddetme notu" data-moderation-note-required="1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="moderation_action">
                                            <input type="hidden" name="topic_id" value="<?= (int) $t['id'] ?>">
                                            <input type="hidden" name="decision" value="reject">
                                            <input type="hidden" name="moderation_note" value="">
                                            <button type="submit" class="topic-action-item"><i class="bi bi-x-lg" aria-hidden="true"></i>Reddet</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($statusKey !== 'revision'): ?>
                                        <form action="topics.php" method="post" class="ui-admin-inline-form-block" data-moderation-note-form data-moderation-note-title="Revizyon notu" data-moderation-note-required="1">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="moderation_action">
                                            <input type="hidden" name="topic_id" value="<?= (int) $t['id'] ?>">
                                            <input type="hidden" name="decision" value="revision">
                                            <input type="hidden" name="moderation_note" value="">
                                            <button type="submit" class="topic-action-item"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>Revizyon iste</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                <?php else: ?>
                                    <form action="topics.php" method="post" class="ui-admin-inline-form-block">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="bulk_action" value="restore">
                                        <input type="hidden" name="topic_ids[]" value="<?= (int) $t['id'] ?>">
                                        <button type="submit" class="topic-action-item"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Geri yükle</button>
                                    </form>
                                <?php endif; ?>
                                    </div>
                                <form action="<?= $baseUri ?>/admin/delete.php" method="post" class="ui-admin-inline-form-block">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <?php if ($isDeleted): ?><input type="hidden" name="permanent" value="1"><?php endif; ?>
                                    <button type="submit" class="btn-icon-minimal danger" title="<?= $isDeleted ? 'Kalıcı sil' : 'Çöpe taşı' ?>" aria-label="<?= $isDeleted ? 'Kalıcı sil' : 'Çöpe taşı' ?>"><i class="bi <?= $isDeleted ? 'bi-fire' : 'bi-trash' ?>"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
    </tbody>
</table>
</div>

<footer class="topics-list-footer">
    <span><?= $totalFiltered > 0 ? number_format(($page - 1) * $perPage + 1, 0, ',', '.') . '–' . number_format(min($page * $perPage, $totalFiltered), 0, ',', '.') : '0' ?> / <?= number_format($totalFiltered, 0, ',', '.') ?> konu</span>
<?php if ($totalPages > 1): ?>
    <?php
    $qs = $_GET;
    unset($qs['page']);
    $baseUrl = 'topics.php?' . (count($qs) > 0 ? http_build_query($qs) . '&' : '') . 'page=';
    echo adminRenderPagination($totalPages, $page, static fn (int $targetPage): string => $baseUrl . $targetPage, [
        'aria_label' => 'Konu sayfalama',
        'window' => 1,
    ]);
    ?>
<?php endif; ?>
</footer>
</section>
