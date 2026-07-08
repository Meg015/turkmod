<?php
/**
 * Tüm Kullanıcılar Sekmesi
 */
?>

<?php $editGroups = $groups ?? []; ?>

<div class="admin-card ui-admin-mb-md ui-panel">
    <div class="card-body ui-admin-card-compact ui-panel__body ui-card">
        <form method="get" action="users.php" class="ui-admin-filter-row">
            <input type="hidden" name="tab" value="users">
            <div class="ui-admin-filter-grow">
                <label class="ui-admin-form-label">Ara</label>
                <input type="text" name="q" class="ui-admin-form-control" placeholder="Kullanici adi, e-posta, ID, IP veya konum..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="ui-admin-filter-sm">
                <label class="ui-admin-form-label">Grup</label>
                <select name="group" class="ui-admin-form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($groups as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= $filterGroup == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ui-admin-filter-sm">
                <label class="ui-admin-form-label">Durum</label>
                <select name="status" class="ui-admin-form-select">
                    <option value="">Tümü</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Aktif</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Pasif</option>
                    <option value="banned" <?= $filterStatus === 'banned' ? 'selected' : '' ?>>Banlı</option>
                    <option value="restricted" <?= $filterStatus === 'restricted' ? 'selected' : '' ?>>Kısıtlı</option>
                </select>
            </div>
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary"><i class="bi bi-search"></i> Filtrele</button>
            <?php if ($search || $filterGroup || $filterStatus): ?>
                <a href="users.php?tab=users" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-x-circle"></i> Temizle</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if (empty($users)): ?>
    <div class="admin-card ui-panel">
        <div class="card-body ui-admin-empty ui-panel__body ui-empty">
            <div class="ui-admin-empty-icon ui-empty"><i class="bi bi-search"></i></div>
            <h3 class="ui-admin-empty-title ui-empty">Kullanıcı bulunamadı</h3>
            <p class="ui-admin-empty-desc ui-empty">Arama ve filtre kriterlerinizi gözden geçirin veya temizleyip tüm kullanıcıları listeleyin.</p>
            <div class="ui-admin-empty-actions ui-empty">
                <a href="users.php?tab=users" class="ui-admin-btn ui-admin-btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Filtreleri Temizle</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="admin-card ui-panel">
        <div class="ui-admin-table-responsive">
            <table class="ui-admin-table">
                <thead>
                    <tr>
                        <th class="ui-admin-table-head-id">#</th>
                        <th class="ui-admin-table-head-user">Kullanıcı</th>
                        <th class="ui-admin-table-head-role">Grup</th>
                        <th class="ui-admin-table-head-status">Durum</th>
                        <th class="ui-admin-table-head-restrictions">Kısıtlamalar</th>
                        <th class="ui-admin-table-head-date">Kayıt</th>
                        <th class="ui-admin-table-actions">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user):
                        $userId = (int) $user['id'];
                        $displayUsername = (string) ($user['username'] ?? '');
                        $isSelf = $userId === $currentUserId;
                        $isBanned = (int) ($user['is_banned'] ?? 0) === 1;
                        $hasRestrictions = !empty($user['restrictions']);
                        $restrictionTypes = $hasRestrictions ? explode(',', (string) $user['restrictions']) : [];
                    ?>
                    <tr>
                        <td><strong><?= $userId ?></strong></td>
                        <td>
                            <div class="ui-admin-user-line-sm">
                                <div class="user-avatar-badge default-avatar">
                                    <?= function_exists('avatarImageHtml') ? avatarImageHtml($displayUsername, (string) ($user['avatar'] ?? ''), ['alt' => '']) : '' ?>
                                </div>
                                <div class="ui-admin-user-copy">
                                    <strong class="ui-admin-user-name"><?= htmlspecialchars($displayUsername) ?></strong>
                                    <span class="ui-admin-user-email"><?= htmlspecialchars((string) $user['email']) ?></span>
                                    <?php if ($isSelf): ?>
                                        <span class="user-self-badge ui-admin-mt-xs"><i class="bi bi-person-fill"></i> Siz</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="ui-admin-badge ui-admin-badge-<?= ((string) ($user['group_slug'] ?? '') === 'admin') ? 'danger' : 'secondary' ?> ui-admin-badge-xs">
                                <?= htmlspecialchars((string) ($user['group_name'] ?? 'Uye')) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($isBanned): ?>
                                <span class="ui-admin-badge ui-admin-badge-danger ui-admin-badge-xs"><i class="bi bi-slash-circle"></i> Banlı</span>
                            <?php elseif ($user['status'] === 'active'): ?>
                                <span class="ui-admin-badge ui-admin-badge-success ui-admin-badge-xs"><i class="bi bi-check-circle"></i> Aktif</span>
                            <?php else: ?>
                                <span class="ui-admin-badge ui-admin-badge-secondary ui-admin-badge-xs"><i class="bi bi-dash-circle"></i> Pasif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($hasRestrictions): ?>
                                <div class="ui-admin-mini-wrap">
                                    <?php
                                    $displayCount = min(2, count($restrictionTypes));
                                    for ($i = 0; $i < $displayCount; $i++):
                                    ?>
                                        <span class="ui-admin-badge ui-admin-badge-warning ui-admin-badge-xxs">
                                            <?= htmlspecialchars(usersGetRestrictionTypeLabel($restrictionTypes[$i])) ?>
                                        </span>
                                    <?php endfor; ?>
                                    <?php if (count($restrictionTypes) > 2): ?>
                                        <span class="ui-admin-badge ui-admin-badge-secondary ui-admin-badge-xxs">
                                            +<?= count($restrictionTypes) - 2 ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="ui-admin-muted-dash">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="ui-admin-muted-sm">
                                <?= date('d.m.Y', strtotime((string) $user['created_at'])) ?>
                            </span>
                        </td>
                        <td class="ui-admin-table-actions">
                            <div class="user-actions-inline">
                                <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" data-user-detail-open data-user-id="<?= $userId ?>" title="360° Detay">
                                    <i class="bi bi-info-circle"></i>
                                </button>
                                <a href="users.php?tab=activity&user_id=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" title="Kullanıcı İzleme">
                                    <i class="bi bi-person-lines-fill"></i>
                                </a>
                                <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" data-admin-note-open
                                    data-user-id="<?= $userId ?>"
                                    data-user-name="<?= htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8') ?>"
                                    title="Admin Notu">
                                    <i class="bi bi-journal-plus"></i>
                                </button>
                                <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" data-user-edit-open
                                    data-user-id="<?= $userId ?>"
                                    data-user-name="<?= htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8') ?>"
                                    data-user-username="<?= htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8') ?>"
                                    data-user-email="<?= htmlspecialchars((string) ($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-user-group="<?= (int) ($user['group_id'] ?? 0) ?>"
                                    data-user-status="<?= htmlspecialchars((string) ($user['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') ?>"
                                    data-user-location="<?= htmlspecialchars((string) ($user['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-user-website="<?= htmlspecialchars((string) ($user['website'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-user-github="<?= htmlspecialchars((string) ($user['social_github'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-user-twitter="<?= htmlspecialchars((string) ($user['social_twitter'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-user-discord="<?= htmlspecialchars((string) ($user['social_discord'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-user-bio="<?= htmlspecialchars((string) ($user['bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    title="Düzenle">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="<?= publicProfileUrl($user) ?>" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" target="_blank" rel="noopener" title="Profili Görüntüle">
                                    <i class="bi bi-person"></i>
                                </a>
                                <?php if ($hasRestrictions): ?>
                                    <a href="users.php?tab=users&view_restrictions=<?= $userId ?>" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" title="Kısıtlamaları Gör">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!$isSelf): ?>
                                    
                                        <?php if ($isBanned): ?>
                                            <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-success" data-user-unban="<?= $userId ?>" title="Ban Kaldır">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-danger" data-user-ban="<?= $userId ?>" data-user-name="<?= htmlspecialchars($displayUsername, ENT_QUOTES) ?>" title="Banla">
                                                <i class="bi bi-slash-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-warning" data-user-restrict="<?= $userId ?>" data-user-name="<?= htmlspecialchars($displayUsername, ENT_QUOTES) ?>" title="Kısıtla">
                                            <i class="bi bi-shield-exclamation"></i>
                                        </button>
                                    
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (($usersTotalPages ?? 1) > 1): ?>
            <?php
            $pageUrl = static function (int $page) use ($search, $filterGroup, $filterStatus): string {
                $params = ['tab' => 'users', 'page' => $page];
                if ($search !== '') {
                    $params['q'] = $search;
                }
                if ($filterGroup !== '') {
                    $params['group'] = $filterGroup;
                }
                if ($filterStatus !== '') {
                    $params['status'] = $filterStatus;
                }
                return 'users.php?' . http_build_query($params);
            };
            ?>
            <nav class="pagination-wrapper user-pagination-wrapper" aria-label="Kullanici sayfalama">
                <div class="pagination user-pagination">
                    <?php if (($usersPage ?? 1) > 1): ?>
                        <a class="page-link" href="<?= htmlspecialchars($pageUrl((int) $usersPage - 1)) ?>" title="Onceki" aria-label="Onceki sayfa"><i class="bi bi-chevron-left"></i></a>
                    <?php endif; ?>

                    <?php for ($i = max(1, (int) ($usersPage ?? 1) - 2); $i <= min((int) ($usersTotalPages ?? 1), (int) ($usersPage ?? 1) + 2); $i++): ?>
                        <a href="<?= htmlspecialchars($pageUrl($i)) ?>" class="page-link <?= $i === (int) ($usersPage ?? 1) ? 'active' : '' ?>"<?= $i === (int) ($usersPage ?? 1) ? ' aria-current="page"' : '' ?>>
                            <?= (int) $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if (($usersPage ?? 1) < ($usersTotalPages ?? 1)): ?>
                        <a class="page-link" href="<?= htmlspecialchars($pageUrl((int) $usersPage + 1)) ?>" title="Sonraki" aria-label="Sonraki sayfa"><i class="bi bi-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </div>
<?php endif; ?>


