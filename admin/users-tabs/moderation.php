<?php
/**
 * Moderasyon çalışma alanı.
 */

$moderationView = in_array((string)($moderationView ?? 'banned'), ['banned', 'restricted', 'appeals'], true)
    ? (string)$moderationView
    : 'banned';

$moderationTabs = [
    'banned' => [
        'label' => 'Banlılar',
        'icon' => 'bi-slash-circle',
        'count' => (int)($stats['banned'] ?? 0),
        'desc' => 'Banlı hesaplar',
    ],
    'restricted' => [
        'label' => 'Kısıtlılar',
        'icon' => 'bi-shield-exclamation',
        'count' => (int)($stats['restricted'] ?? 0),
        'desc' => 'Aktif kısıtlamalar',
    ],
    'appeals' => [
        'label' => 'Ban İtirazları',
        'icon' => 'bi-envelope-exclamation',
        'count' => (int)($appealStats['open'] ?? 0),
        'desc' => 'Açık itirazlar',
    ],
];
?>

<div class="users-moderation-workspace">
    <?php
    $moderationTabItems = [];
    foreach ($moderationTabs as $viewKey => $meta) {
        $moderationTabItems[$viewKey] = [
            'href' => 'users.php?tab=moderation&moderation=' . urlencode((string) $viewKey),
            'icon' => (string) $meta['icon'],
            'label' => (string) $meta['label'],
            'description' => (string) $meta['desc'],
            'badge' => number_format((int) $meta['count']),
            'badge_tone' => (int) $meta['count'] > 0 ? 'warning' : 'muted',
            'badge_class' => 'users-subtab-count',
        ];
    }
    echo adminRenderTabBar($moderationTabItems, $moderationView, [
        'class' => 'users-subtabs',
        'link_class' => 'users-subtab',
        'active_class' => 'is-active',
        'aria_label' => 'Moderasyon sekmeleri',
        'icon_wrap_class' => 'users-subtab-icon',
        'copy_class' => 'users-subtab-copy',
    ]);
    ?>

    <?php
    if ($moderationView === 'restricted') {
        require dirname(__DIR__, 2) . '/' . $tabPartials['restricted'];
    } elseif ($moderationView === 'appeals') {
        require dirname(__DIR__, 2) . '/' . $tabPartials['appeals'];
    } else {
        require dirname(__DIR__, 2) . '/' . $tabPartials['banned'];
    }
    ?>
</div>
