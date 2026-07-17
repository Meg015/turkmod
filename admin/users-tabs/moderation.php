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
    <nav class="users-subtabs" aria-label="Moderasyon sekmeleri">
        <?php foreach ($moderationTabs as $viewKey => $meta): ?>
            <a href="users.php?tab=moderation&amp;moderation=<?= htmlspecialchars($viewKey, ENT_QUOTES, 'UTF-8') ?>"
               class="users-subtab <?= $moderationView === $viewKey ? 'is-active' : '' ?>"
               <?= $moderationView === $viewKey ? 'aria-current="page"' : '' ?>>
                <span class="users-subtab-icon"><i class="bi <?= htmlspecialchars($meta['icon'], ENT_QUOTES, 'UTF-8') ?>"></i></span>
                <span class="users-subtab-copy">
                    <strong><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($meta['desc'], ENT_QUOTES, 'UTF-8') ?></small>
                </span>
                <span class="users-subtab-count"><?= number_format((int)$meta['count']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

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
