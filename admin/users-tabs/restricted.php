<?php
/**
 * Kısıtlı Kullanıcılar Sekmesi
 */
?>

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-body ui-admin-card-compact ui-panel__body ui-card">
        <form method="get" action="users.php" class="ui-admin-filter-row">
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
    </div>
</div>

<?php if (empty($restrictedUsers)): ?>
    <div class="admin-card ui-panel">
        <div class="card-body ui-admin-empty ui-panel__body ui-empty">
            <div class="ui-admin-empty-icon tone-success ui-empty"><i class="bi bi-shield-check"></i></div>
            <h3 class="ui-admin-empty-title ui-empty">Kısıtlı kullanıcı bulunmuyor</h3>
            <p class="ui-admin-empty-desc ui-empty">Aktif kısıtlama kaydı yok. Yeni bir kısıtlama eklendiğinde burada listelenir.</p>
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
                        <th>Kısıtlamalar</th>
                        <th>Toplam</th>
                        <th class="ui-admin-table-actions">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
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
                                <form method="post" class="ui-admin-inline-form" data-admin-confirm="Bu kullanıcının tüm kısıtlamalarını kaldırmak istediğinizden emin misiniz?" data-admin-confirm-title="Tüm kısıtlamalar kaldırılsın mı?" data-admin-confirm-ok="Kaldır" data-admin-confirm-tone="danger">
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
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
