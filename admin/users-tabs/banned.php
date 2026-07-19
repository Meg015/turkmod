<?php
/**
 * Banlı Kullanıcılar Sekmesi
 */
?>

<?= adminRenderFilterToolbarOpen('', 'ui-admin-mb-md') ?>
        <form method="get" action="users.php" class="ui-admin-filter-row admin-filter-form">
            <input type="hidden" name="tab" value="moderation">
            <input type="hidden" name="moderation" value="banned">
            <div class="ui-admin-filter-grow">
                <label class="ui-admin-form-label">Ara</label>
                <input type="text" name="q" class="ui-admin-form-control" placeholder="Kullanici adi veya e-posta..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-search"></i> Ara</button>
            <?php if ($search): ?>
                <a href="users.php?tab=moderation&amp;moderation=banned" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-circle"></i> Temizle</a>
            <?php endif; ?>
        </form>
<?= adminRenderFilterToolbarClose() ?>

<?php if (empty($bannedUsers)): ?>
    <?= adminRenderPanel(adminRenderEmptyState([
                'icon' => 'bi-emoji-smile',
                'tone' => 'success',
                'title' => 'Banlı kullanıcı bulunmuyor',
                'description' => 'Aktif ban kaydı yok. Yeni bir işlem oluştuğunda bu liste güncellenir.',
            ]), ['tag' => 'div']) ?>
<?php else: ?>
    <?= adminRenderPanelOpen(['tag' => 'div', 'body_class' => 'ui-admin-card-body-flush']) ?>
            <?= adminRenderTableOpen([
                ['label' => '#', 'class' => 'ui-admin-table-head-id'],
                'Kullanıcı',
                'Grup',
                'Ban Sebebi',
                'Ban Tarihi',
                ['label' => 'İşlemler', 'class' => 'ui-admin-table-actions'],
            ], [
                'wrap_class' => 'ui-admin-table-responsive',
                'label' => 'Banlı kullanıcılar',
            ]) ?>
                    <?php foreach ($bannedUsers as $user):
                        $userId = (int) $user['id'];
                        $displayUsername = (string) ($user['username'] ?? '');
                        $banReason = trim((string) ($user['ban_reason'] ?? ''));
                        $bannedAt = $user['banned_at'] ? date('d.m.Y H:i', strtotime($user['banned_at'])) : '—';
                    ?>
                    <tr>
                        <td><?= $userId ?></td>
                        <td>
                            <div class="ui-admin-user-line">
                                <div class="user-avatar-badge default-avatar">
                                    <?= function_exists('avatarImageHtml') ? avatarImageHtml($displayUsername, (string) ($user['avatar'] ?? ''), ['alt' => '']) : '' ?>
                                </div>
                                <div>
                                    <strong class="ui-admin-user-name"><?= htmlspecialchars($displayUsername) ?></strong>
                                    <span class="ui-admin-user-email-md"><?= htmlspecialchars((string) $user['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="ui-admin-badge ui-admin-badge-<?= ((string) ($user['group_slug'] ?? '') === 'admin') ? 'danger' : 'secondary' ?>">
                                <?= htmlspecialchars((string) ($user['group_name'] ?? 'Üye')) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($banReason): ?>
                                <div class="ui-admin-note-box ui-panel">
                                    <?= nl2br(htmlspecialchars($banReason)) ?>
                                </div>
                            <?php else: ?>
                                <span class="ui-admin-muted-sm"><em>Sebep belirtilmemiş</em></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="ui-admin-muted-sm"><?= $bannedAt ?></span>
                        </td>
                        <td class="ui-admin-table-actions">
                            <div class="user-actions-inline">
                                <a href="users.php?tab=activity&user_id=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" title="Kullanıcı İzleme">
                                    <i class="bi bi-person-lines-fill"></i>
                                </a>
                                <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" title="Düzenle" 
                                    data-user-edit-open 
                                    data-user-id="<?= $userId ?>"
                                    data-user-name="<?= htmlspecialchars($displayUsername) ?>"
                                    data-user-username="<?= htmlspecialchars($displayUsername) ?>"
                                    data-user-email="<?= htmlspecialchars((string) $user['email']) ?>"
                                    data-user-group="<?= (int) $user['group_id'] ?>"
                                    data-user-status="<?= htmlspecialchars((string) $user['status']) ?>"
                                    data-user-location="<?= htmlspecialchars((string) ($user['location'] ?? '')) ?>"
                                    data-user-website="<?= htmlspecialchars((string) ($user['website'] ?? '')) ?>"
                                    data-user-github="<?= htmlspecialchars((string) ($user['social_github'] ?? '')) ?>"
                                    data-user-twitter="<?= htmlspecialchars((string) ($user['social_twitter'] ?? '')) ?>"
                                    data-user-discord="<?= htmlspecialchars((string) ($user['social_discord'] ?? '')) ?>"
                                    data-user-bio="<?= htmlspecialchars((string) ($user['bio'] ?? '')) ?>"
                                >
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-success" data-user-unban="<?= $userId ?>" data-user-name="<?= htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8') ?>" title="Ban Kaldır">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
            <?= adminRenderTableClose() ?>
    <?= adminRenderPanelClose('div') ?>
<?php endif; ?>
