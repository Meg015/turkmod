<?php
/**
 * Kısıtlı Kullanıcılar Sekmesi
 */
?>

<?= adminRenderFilterToolbarOpen('', 'ui-admin-mb-md') ?>
        <form method="get" action="users.php" class="ui-admin-filter-row admin-filter-form">
            <input type="hidden" name="tab" value="moderation">
            <input type="hidden" name="moderation" value="restricted">
            <div class="ui-admin-filter-grow">
                <label class="ui-admin-form-label">Ara</label>
                <input type="text" name="q" class="ui-admin-form-control" placeholder="Kullanici adi veya e-posta..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-search"></i> Ara</button>
            <?php if ($search): ?>
                <a href="users.php?tab=moderation&amp;moderation=restricted" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-circle"></i> Temizle</a>
            <?php endif; ?>
        </form>
<?= adminRenderFilterToolbarClose() ?>

<?php if (empty($restrictedUsers)): ?>
    <?= adminRenderPanel(adminRenderEmptyState([
                'icon' => 'bi-shield-check',
                'tone' => 'success',
                'title' => 'Kısıtlı kullanıcı bulunmuyor',
                'description' => 'Aktif kısıtlama kaydı yok. Yeni bir kısıtlama eklendiğinde burada listelenir.',
            ]), ['tag' => 'div']) ?>
<?php else: ?>
    <?= adminRenderPanelOpen(['tag' => 'div', 'body_class' => 'ui-admin-card-body-flush']) ?>
            <?= adminRenderTableOpen([
                ['label' => '#', 'class' => 'ui-admin-table-head-id'],
                'Kullanıcı',
                'Grup',
                'Kısıtlamalar',
                'Toplam',
                ['label' => 'İşlemler', 'class' => 'ui-admin-table-actions'],
            ], [
                'wrap_class' => 'ui-admin-table-responsive',
                'label' => 'Kısıtlı kullanıcılar',
            ]) ?>
                    <?php foreach ($restrictedUsers as $user):
                        $userId = (int) $user['id'];
                        $displayUsername = (string) ($user['username'] ?? '');
                        $restrictions = $restrictedUserRestrictionsMap[$userId] ?? [];
                        $restrictionCount = count($restrictions);
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
                            <div class="ui-admin-restriction-list">
                                <?php foreach (array_slice($restrictions, 0, 3) as $restriction): ?>
                                    <div class="ui-admin-restriction-row">
                                        <span class="restriction-type restriction-type-<?= htmlspecialchars($restriction['restriction_type']) ?> ui-admin-restriction-type-fixed">
                                            <?= htmlspecialchars(usersGetRestrictionTypeLabel($restriction['restriction_type'])) ?>
                                        </span>
                                        <span class="ui-admin-restriction-meta">
                                            <?php if ($restriction['expires_at']): ?>
                                                <i class="bi bi-clock"></i> <?= date('d.m.Y', strtotime($restriction['expires_at'])) ?>
                                            <?php else: ?>
                                                <i class="bi bi-infinity"></i> Süresiz
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if ($restrictionCount > 3): ?>
                                    <span class="ui-admin-restriction-offset">
                                        +<?= $restrictionCount - 3 ?> daha...
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="ui-admin-badge ui-admin-badge-warning ui-admin-muted-sm">
                                <?= $restrictionCount ?>
                            </span>
                        </td>
                        <td class="ui-admin-table-actions">
                            <div class="user-actions-inline">
                                <a href="users.php?tab=activity&user_id=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" title="Kullanıcı İzleme">
                                    <i class="bi bi-person-lines-fill"></i>
                                </a>
                                <a href="users.php?tab=moderation&amp;moderation=restricted&amp;view_restrictions=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" title="Kısıtlama Detayları">
                                    <i class="bi bi-eye"></i>
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
                                <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-warning" data-user-restrict="<?= $userId ?>" data-user-name="<?= htmlspecialchars($displayUsername, ENT_QUOTES) ?>" title="Kısıtlama Ekle">
                                    <i class="bi bi-plus-circle"></i>
                                </button>
                                <form method="post" class="ui-admin-inline-form"<?= adminConfirmAttrs(['message' => 'Bu kullanıcının tüm kısıtlamalarını kaldırmak istediğinizden emin misiniz?', 'title' => 'Tüm kısıtlamalar kaldırılsın mı?', 'ok' => 'Kaldır', 'tone' => 'danger']) ?>>
                                    <input type="hidden" name="_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="action" value="remove_all_restrictions">
                                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                                    <button type="submit" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-danger" title="Tüm Kısıtlamaları Kaldır">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
            <?= adminRenderTableClose() ?>
    <?= adminRenderPanelClose('div') ?>
<?php endif; ?>
