<?php

declare(strict_types=1);

$profileSidebar = is_array($profileSidebar ?? null) ? $profileSidebar : [];
$profileSidebarName = (string) ($profileSidebar['name'] ?? '');
$profileSidebarAvatar = (string) ($profileSidebar['avatar'] ?? '');
$profileSidebarAvatarFallback = (string) ($profileSidebar['avatar_fallback'] ?? '');
$profileSidebarGroupBadge = (string) ($profileSidebar['group_badge_html'] ?? '');
$profileSidebarBio = trim((string) ($profileSidebar['bio'] ?? ''));
$profileSidebarCreatedAtRaw = trim((string) ($profileSidebar['created_at'] ?? $profileSidebar['member_since'] ?? ''));
$profileSidebarCreatedAt = $profileSidebarCreatedAtRaw;
$profileSidebarTenure = trim((string) ($profileSidebar['tenure'] ?? ''));
if ($profileSidebarCreatedAtRaw !== '') {
    $profileSidebarCreatedAtTs = strtotime($profileSidebarCreatedAtRaw);
    if ($profileSidebarCreatedAtTs !== false) {
        $profileSidebarCreatedAt = date('d.m.Y H:i', $profileSidebarCreatedAtTs);
        if ($profileSidebarTenure === '') {
            $profileSidebarTenure = function_exists('profileTenureLabel') ? profileTenureLabel($profileSidebarCreatedAtRaw) : '';
        }
    }
}
$profileSidebarLocation = trim((string) ($profileSidebar['location'] ?? ''));
$profileSidebarSocialLinks = array_values(array_filter(
    (array) ($profileSidebar['social_links'] ?? []),
    static fn ($item): bool => is_array($item)
));
$profileSidebarStats = array_values(array_filter(
    (array) ($profileSidebar['stats'] ?? []),
    static fn ($item): bool => is_array($item)
));
$profileSidebarShowReport = !empty($profileSidebar['can_report']);
$profileSidebarShowLeaderboard = !empty($profileSidebar['show_leaderboard']);
$profileSidebarLeaderboardUserId = (int) ($profileSidebar['leaderboard_user_id'] ?? 0);
?>
<aside class="profile-sidebar">
    <div class="profile-sidebar-card profile-sidebar-card--hero ui-card">
        <div class="profile-sidebar-avatar profile-sidebar-avatar--hero">
            <img src="<?= htmlspecialchars($profileSidebarAvatar, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($profileSidebarName, ENT_QUOTES, 'UTF-8') ?>" width="80" height="80" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="<?= htmlspecialchars($profileSidebarAvatarFallback, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <h3 class="profile-sidebar-name"><?= htmlspecialchars($profileSidebarName, ENT_QUOTES, 'UTF-8') ?></h3>
        <div class="profile-sidebar-role profile-sidebar-group profile-sidebar-rank">
            <?= $profileSidebarGroupBadge ?>
        </div>

        <?php if ($profileSidebarBio !== ''): ?>
            <p class="profile-sidebar-bio"><?= nl2br(htmlspecialchars($profileSidebarBio, ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>

        <div class="profile-sidebar-meta">
            <?php if ($profileSidebarCreatedAt !== ''): ?>
                <div class="profile-sidebar-meta-item profile-sidebar-meta-item--joined">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    <span class="profile-sidebar-meta-label">Kayıt Tarihi</span>
                    <strong><?= htmlspecialchars($profileSidebarCreatedAt, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            <?php endif; ?>
            <?php if ($profileSidebarTenure !== ''): ?>
                <div class="profile-sidebar-meta-item profile-sidebar-meta-item--tenure">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    <span class="profile-sidebar-meta-label">Üyelik Süresi</span>
                    <strong><?= htmlspecialchars($profileSidebarTenure, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            <?php endif; ?>
            <?php if ($profileSidebarLocation !== ''): ?>
                <div class="profile-sidebar-meta-item">
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <span class="profile-sidebar-meta-label">Konum</span>
                    <strong><?= htmlspecialchars($profileSidebarLocation, ENT_QUOTES, 'UTF-8') ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($profileSidebarSocialLinks !== [] || $profileSidebarShowReport): ?>
            <div class="profile-sidebar-social">
                <?php foreach ($profileSidebarSocialLinks as $_profileSidebarLink): ?>
                    <?php
                    $_profileSidebarLinkIcon = (string) ($_profileSidebarLink['icon'] ?? '');
                    $_profileSidebarLinkUrl = (string) ($_profileSidebarLink['url'] ?? '#');
                    $_profileSidebarLinkTitle = (string) ($_profileSidebarLink['title'] ?? 'Bağlantı');
                    ?>
                    <a href="<?= htmlspecialchars($_profileSidebarLinkUrl, ENT_QUOTES, 'UTF-8') ?>" class="profile-sidebar-social-link" target="_blank" rel="noopener" title="<?= htmlspecialchars($_profileSidebarLinkTitle, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($_profileSidebarLinkTitle, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi <?= htmlspecialchars($_profileSidebarLinkIcon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                    </a>
                <?php endforeach; ?>
                <?php if ($profileSidebarShowReport): ?>
                    <button type="button" class="profile-sidebar-social-link profile-sidebar-report-action" data-user-report-modal-open title="Kullanıcıyı Şikayet Et" aria-label="Kullanıcıyı Şikayet Et">
                        <i class="bi bi-flag" aria-hidden="true"></i>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($profileSidebarStats !== []): ?>
        <div class="profile-sidebar-card profile-sidebar-card--stats ui-card">
            <h4 class="profile-sidebar-card-title"><i class="bi bi-graph-up" aria-hidden="true"></i> İstatistikler</h4>
            <div class="profile-sidebar-stats">
                <?php foreach ($profileSidebarStats as $_profileSidebarStat): ?>
                    <?php $_profileSidebarStatClass = trim((string) ($_profileSidebarStat['class'] ?? '')); ?>
                    <div class="profile-sidebar-stat">
                        <div class="profile-sidebar-stat-icon<?= $_profileSidebarStatClass !== '' ? ' ' . htmlspecialchars($_profileSidebarStatClass, ENT_QUOTES, 'UTF-8') : '' ?>">
                            <i class="bi <?= htmlspecialchars((string) ($_profileSidebarStat['icon'] ?? 'bi-bar-chart'), ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                        </div>
                        <div class="profile-sidebar-stat-info">
                            <div class="profile-sidebar-stat-value"><?= htmlspecialchars((string) ($_profileSidebarStat['value'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="profile-sidebar-stat-label"><?= htmlspecialchars((string) ($_profileSidebarStat['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($profileSidebarShowLeaderboard && $profileSidebarLeaderboardUserId > 0): ?>
        <?php
        $userId = $profileSidebarLeaderboardUserId;
        include __DIR__ . '/profile-leaderboard-widget.php';
        ?>
    <?php endif; ?>
</aside>
