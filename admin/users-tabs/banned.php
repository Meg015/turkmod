<?php
/**
 * Banlı Kullanıcılar Sekmesi
 */
?>

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-body ui-admin-card-compact ui-panel__body ui-card">
        <form method="get" action="users.php" class="ui-admin-filter-row">
            <input type="hidden" name="tab" value="banned">
            <div class="ui-admin-filter-grow">
                <label class="ui-admin-form-label">Ara</label>
                <input type="text" name="q" class="ui-admin-form-control" placeholder="Ad veya e-posta..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-search"></i> Ara</button>
            <?php if ($search): ?>
                <a href="users.php?tab=banned" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-circle"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($bannedUsers)): ?>
    <div class="admin-card ui-panel">
        <div class="card-body ui-admin-empty ui-panel__body ui-empty">
            <div class="ui-admin-empty-icon tone-success ui-empty"><i class="bi bi-emoji-smile"></i></div>
            <h3 class="ui-admin-empty-title ui-empty">Banlı kullanıcı yok 🎉</h3>
            <p class="ui-admin-empty-desc ui-empty">Şu an tüm kullanıcılar aktif durumda. Topluluk sağlıklı görünüyor!</p>
        </div>
    </div>
<?php else: ?>
    <div class="admin-card ui-panel">
        <div class="ui-admin-table-responsive">
            <table class="ui-admin-table">
                <thead>
                    <tr>
                        <th class="ui-admin-table-head-id">#</th>
                        <th>Kullanıcı</th>
                        <th>Grup</th>
                        <th>Ban Sebebi</th>
                        <th>Ban Tarihi</th>
                        <th class="ui-admin-table-actions">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bannedUsers as $user):
                        $userId = (int) $user['id'];
                        $banReason = trim((string) ($user['ban_reason'] ?? ''));
                        $bannedAt = $user['banned_at'] ? date('d.m.Y H:i', strtotime($user['banned_at'])) : '—';
                    ?>
                    <tr>
                        <td><?= $userId ?></td>
                        <td>
                            <div class="ui-admin-user-line">
                                <div class="user-avatar-badge default-avatar">
                                    <?= function_exists('avatarImageHtml') ? avatarImageHtml((string) $user['name'], (string) ($user['avatar'] ?? ''), ['alt' => '']) : '' ?>
                                </div>
                                <div>
                                    <strong class="ui-admin-user-name"><?= htmlspecialchars((string) $user['name']) ?></strong>
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
                                    data-user-name="<?= htmlspecialchars((string) $user['name']) ?>"
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
                                <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-success" data-user-unban="<?= $userId ?>" title="Ban Kaldır">
                                    <i class="bi bi-check-circle"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>


