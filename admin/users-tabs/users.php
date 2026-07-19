<?php
/**
 * Tüm Kullanıcılar Sekmesi
 */

$editGroups = $groups ?? [];
$usersSort = in_array((string)($usersSort ?? 'id'), ['id', 'user', 'email', 'group', 'status', 'restrictions', 'activity', 'created'], true) ? (string)$usersSort : 'id';
$usersDir = (string)($usersDir ?? 'asc') === 'desc' ? 'desc' : 'asc';

$usersSortUrl = static function (string $column) use ($search, $filterGroup, $filterStatus, $usersSort, $usersDir): string {
    $params = ['tab' => 'users', 'sort' => $column];
    $params['dir'] = ($usersSort === $column && $usersDir === 'asc') ? 'desc' : 'asc';
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

$usersSortHeader = static function (string $column, string $label, string $class = '') use ($usersSortUrl, $usersSort, $usersDir): array {
    $isActive = $usersSort === $column;
    $icon = $isActive ? ($usersDir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down') : 'bi-arrow-down-up';
    $aria = $isActive ? ($usersDir === 'asc' ? 'ascending' : 'descending') : 'none';
    return [
        'class' => $class,
        'attrs' => ['aria-sort' => $aria],
        'html' => '<a class="users-sort-link ' . ($isActive ? 'is-active' : '') . '" href="' . htmlspecialchars($usersSortUrl($column), ENT_QUOTES, 'UTF-8') . '">'
            . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
            . '<i class="bi ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true"></i>'
            . '</a>',
    ];
};

$usersFormatDateTime = static function ($value, string $fallback = '-'): string {
    $value = trim((string)($value ?? ''));
    if ($value === '') {
        return $fallback;
    }
    $timestamp = strtotime($value);
    return $timestamp ? date('d.m.Y H:i', $timestamp) : $fallback;
};
?>

<?= adminRenderFilterToolbarOpen('', 'ui-admin-mb-md') ?>
        <form method="get" action="users.php" class="ui-admin-filter-row admin-filter-form">
            <input type="hidden" name="tab" value="users">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($usersSort, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($usersDir, ENT_QUOTES, 'UTF-8') ?>">
            <div class="ui-admin-filter-grow">
                <label class="ui-admin-form-label">Ara</label>
                <input type="text" name="q" class="ui-admin-form-control" placeholder="Kullanıcı adı, e-posta, ID, IP veya konum..." value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="ui-admin-filter-sm">
                <label class="ui-admin-form-label">Grup</label>
                <select name="group" class="ui-admin-form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($groups as $r): ?>
                        <option value="<?= (int)$r['id'] ?>" <?= $filterGroup == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$r['name'], ENT_QUOTES, 'UTF-8') ?></option>
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
<?= adminRenderFilterToolbarClose() ?>

<?php if (empty($users)): ?>
    <?= adminRenderPanel(adminRenderEmptyState([
                'icon' => 'bi-search',
                'tone' => 'info',
                'title' => 'Kullanıcı bulunamadı',
                'description' => 'Arama veya filtre kriterlerini değiştirerek tekrar deneyin.',
                'actions' => [
                    ['href' => 'users.php?tab=users', 'label' => 'Filtreleri Temizle', 'icon' => 'bi-arrow-counterclockwise'],
                ],
            ]), ['tag' => 'div']) ?>
<?php else: ?>
    <form method="post" action="users.php?tab=users" id="userBulkForm" class="user-bulk-form" data-user-bulk-form<?= adminConfirmAttrs(['message' => 'Seçili kullanıcılara toplu işlem uygulanacak. Devam edilsin mi?', 'title' => 'Toplu işlem uygulansın mı?', 'ok' => 'Uygula', 'tone' => 'warning']) ?>>
        <input type="hidden" name="_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="action" value="bulk_action">

        <div class="user-bulk-bar admin-bulk-action-bar ui-panel">
            <label class="user-bulk-select">
                <input type="checkbox" data-user-select-all>
                <span>Tümünü seç</span>
            </label>
            <span class="user-bulk-count" data-user-selected-count>0 seçili</span>
            <select name="bulk_action" class="ui-admin-form-select user-bulk-action" data-user-bulk-action required>
                <option value="">Toplu işlem seç...</option>
                <option value="activate">Aktif yap</option>
                <option value="deactivate">Pasif yap</option>
                <option value="change_group">Grup değiştir</option>
                <option value="ban">Banla</option>
                <option value="unban">Ban kaldır</option>
            </select>
            <select name="group_id" class="ui-admin-form-select user-bulk-extra" data-user-bulk-field="change_group" hidden>
                <option value="">Grup seç...</option>
                <?php foreach ($editGroups as $group): ?>
                    <option value="<?= (int)$group['id'] ?>"><?= htmlspecialchars((string)$group['name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="ban_reason" class="ui-admin-form-control user-bulk-extra" data-user-bulk-field="ban" placeholder="Toplu ban gerekçesi" hidden>
            <button type="submit" class="ui-admin-btn ui-admin-btn-primary user-bulk-submit">
                <i class="bi bi-check2-all"></i> Uygula
            </button>
        </div>

        <?= adminRenderPanelOpen(['tag' => 'div', 'class' => 'users-table-panel', 'body_class' => 'ui-admin-card-body-flush']) ?>
                <?= adminRenderTableOpen([
                    ['label' => 'Seç', 'class' => 'ui-admin-table-head-check', 'html' => '<span class="ui-admin-sr-only">Seç</span>'],
                    $usersSortHeader('id', '#', 'ui-admin-table-head-id'),
                    $usersSortHeader('user', 'Kullanıcı', 'ui-admin-table-head-user'),
                    $usersSortHeader('group', 'Grup', 'ui-admin-table-head-role'),
                    $usersSortHeader('status', 'Durum', 'ui-admin-table-head-status'),
                    $usersSortHeader('restrictions', 'Kısıtlamalar', 'ui-admin-table-head-restrictions'),
                    $usersSortHeader('activity', 'Son Aktivite', 'ui-admin-table-head-activity'),
                    $usersSortHeader('created', 'Kayıt', 'ui-admin-table-head-date'),
                    ['label' => 'İşlemler', 'class' => 'ui-admin-table-actions'],
                ], [
                    'class' => 'users-table',
                    'wrap_class' => 'ui-admin-table-responsive',
                    'label' => 'Kullanıcı listesi',
                ]) ?>
                        <?php foreach ($users as $user):
                            $userId = (int)$user['id'];
                            $displayUsername = (string)($user['username'] ?? '');
                            $isSelf = $userId === $currentUserId;
                            $isBanned = (int)($user['is_banned'] ?? 0) === 1;
                            $hasRestrictions = !empty($user['restrictions']);
                            $restrictionTypes = $hasRestrictions ? explode(',', (string)$user['restrictions']) : [];
                            $userAttrName = htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8');
                            $profileUrl = publicProfileUrl($user);
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="user_ids[]" value="<?= $userId ?>" class="user-row-checkbox" data-user-row-checkbox data-user-name="<?= $userAttrName ?>" aria-label="<?= $userId ?> numaralı kullanıcıyı seç">
                            </td>
                            <td><strong><?= $userId ?></strong></td>
                            <td>
                                <div class="ui-admin-user-line-sm">
                                    <div class="user-avatar-badge default-avatar">
                                        <?= function_exists('avatarImageHtml') ? avatarImageHtml($displayUsername, (string)($user['avatar'] ?? ''), ['alt' => '']) : '' ?>
                                    </div>
                                    <div class="ui-admin-user-copy">
                                        <strong class="ui-admin-user-name"><?= htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <span class="ui-admin-user-email"><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($isSelf): ?>
                                            <span class="user-self-badge ui-admin-mt-xs"><i class="bi bi-person-fill"></i> Siz</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="ui-admin-badge ui-admin-badge-<?= ((string)($user['group_slug'] ?? '') === 'admin') ? 'danger' : 'secondary' ?> ui-admin-badge-xs">
                                    <?= htmlspecialchars((string)($user['group_name'] ?? 'Üye'), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isBanned): ?>
                                    <span class="ui-admin-badge ui-admin-badge-danger ui-admin-badge-xs"><i class="bi bi-slash-circle"></i> Banlı</span>
                                <?php elseif (($user['status'] ?? '') === 'active'): ?>
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
                                                <?= htmlspecialchars(usersGetRestrictionTypeLabel($restrictionTypes[$i]), ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php endfor; ?>
                                        <?php if (count($restrictionTypes) > 2): ?>
                                            <span class="ui-admin-badge ui-admin-badge-secondary ui-admin-badge-xxs">+<?= count($restrictionTypes) - 2 ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="ui-admin-muted-dash">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $lastActivity = $usersFormatDateTime($user['computed_last_activity_at'] ?? ($user['last_activity_at'] ?? null)); ?>
                                <span class="ui-admin-muted-sm user-last-activity">
                                    <i class="bi bi-activity" aria-hidden="true"></i>
                                    <?= htmlspecialchars($lastActivity, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <span class="ui-admin-muted-sm">
                                    <?= date('d.m.Y', strtotime((string)$user['created_at'])) ?>
                                </span>
                            </td>
                            <td class="ui-admin-table-actions">
                                <div class="user-actions-compact">
                                    <button type="button" class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-primary" data-user-detail-open data-user-id="<?= $userId ?>" title="Detay">
                                        <i class="bi bi-info-circle"></i> Detay
                                    </button>
                                    <details class="user-row-actions-menu">
                                        <summary class="ui-admin-btn ui-admin-btn-xs ui-admin-btn-outline" title="Diğer işlemler">
                                            <i class="bi bi-three-dots"></i>
                                        </summary>
                                        <div class="user-row-actions-popover">
                                            <a href="users.php?tab=activity&amp;user_id=<?= $userId ?>" class="user-row-action"><i class="bi bi-person-lines-fill"></i> Aktivite</a>
                                            <button type="button" class="user-row-action" data-admin-note-open data-user-id="<?= $userId ?>" data-user-name="<?= $userAttrName ?>"><i class="bi bi-journal-plus"></i> Admin Notu</button>
                                            <button type="button" class="user-row-action" data-user-edit-open
                                                data-user-id="<?= $userId ?>"
                                                data-user-name="<?= $userAttrName ?>"
                                                data-user-username="<?= $userAttrName ?>"
                                                data-user-email="<?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-user-group="<?= (int)($user['group_id'] ?? 0) ?>"
                                                data-user-status="<?= htmlspecialchars((string)($user['status'] ?? 'active'), ENT_QUOTES, 'UTF-8') ?>"
                                                data-user-location="<?= htmlspecialchars((string)($user['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-user-website="<?= htmlspecialchars((string)($user['website'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-user-github="<?= htmlspecialchars((string)($user['social_github'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-user-twitter="<?= htmlspecialchars((string)($user['social_twitter'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-user-discord="<?= htmlspecialchars((string)($user['social_discord'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-user-bio="<?= htmlspecialchars((string)($user['bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-pencil"></i> Düzenle
                                            </button>
                                            <a href="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>" class="user-row-action" target="_blank" rel="noopener"><i class="bi bi-person"></i> Profil</a>
                                            <?php if ($hasRestrictions): ?>
                                                <a href="users.php?tab=users&amp;view_restrictions=<?= $userId ?>" class="user-row-action"><i class="bi bi-eye"></i> Kısıtlamalar</a>
                                            <?php endif; ?>
                                            <?php if (!$isSelf): ?>
                                                <?php if ($isBanned): ?>
                                                    <button type="button" class="user-row-action is-success" data-user-unban="<?= $userId ?>" data-user-name="<?= $userAttrName ?>"><i class="bi bi-check-circle"></i> Ban Kaldır</button>
                                                <?php else: ?>
                                                    <button type="button" class="user-row-action is-danger" data-user-ban="<?= $userId ?>" data-user-name="<?= $userAttrName ?>"><i class="bi bi-slash-circle"></i> Banla</button>
                                                <?php endif; ?>
                                                <button type="button" class="user-row-action is-warning" data-user-restrict="<?= $userId ?>" data-user-name="<?= $userAttrName ?>"><i class="bi bi-shield-exclamation"></i> Kısıtla</button>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                <?= adminRenderTableClose() ?>
            <?php if (($usersTotalPages ?? 1) > 1): ?>
                <?php
                $pageUrl = static function (int $page) use ($search, $filterGroup, $filterStatus, $usersSort, $usersDir): string {
                    $params = ['tab' => 'users', 'page' => $page, 'sort' => $usersSort, 'dir' => $usersDir];
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
                <?= adminRenderPagination((int) ($usersTotalPages ?? 1), (int) ($usersPage ?? 1), $pageUrl, [
                    'wrapper_class' => 'user-pagination-wrapper',
                    'inner_class' => 'user-pagination',
                    'aria_label' => 'Kullanıcı sayfalama',
                ]) ?>
            <?php endif; ?>
        <?= adminRenderPanelClose('div') ?>
    </form>
<?php endif; ?>
