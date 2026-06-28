<?php

declare(strict_types=1);
/**
 * Unified empty-state partial.
 *
 * Usage:
 *   $emptyState = ['title' => '...', 'description' => '...', 'icon' => 'bi-box-seam'];
 *   include __DIR__ . '/includes/partials/empty-state.php';
 *
 * Or via helper:
 *   echo renderEmptyState('Başlık', 'Açıklama', 'bi-box-seam');
 *
 * Accepts:
 *   - $emptyState['title']        Required. Bold one-liner.
 *   - $emptyState['description']  Optional. Smaller subtext (raw text, NOT html).
 *   - $emptyState['icon']         Optional Bootstrap Icons class (defaults to bi-inbox).
 *   - $emptyState['html']         Optional. Pre-escaped HTML used instead of plain title/description.
 *   - $emptyState['actions']      Optional. Array of ['label', 'url', 'icon', 'class'] action links.
 */

$_es = isset($emptyState) && is_array($emptyState) ? $emptyState : [];
$_esTitle = (string) ($_es['title'] ?? '');
$_esDesc = (string) ($_es['description'] ?? '');
$_esIcon = (string) ($_es['icon'] ?? 'bi-inbox');
$_esHtml = (string) ($_es['html'] ?? '');
$_esActions = isset($_es['actions']) && is_array($_es['actions']) ? $_es['actions'] : [];
?>
<div class="topic-empty-state empty-panel ui-empty" role="status">
    <div>
        <?php if ($_esIcon !== ''): ?>
            <i class="bi <?= htmlspecialchars($_esIcon) ?>" aria-hidden="true"></i>
        <?php endif; ?>
        <?php if ($_esHtml !== ''): ?>
            <?= $_esHtml ?>
        <?php else: ?>
            <?php if ($_esTitle !== ''): ?>
                <strong><?= htmlspecialchars($_esTitle) ?></strong>
            <?php endif; ?>
            <?php if ($_esDesc !== ''): ?>
                <span><?= htmlspecialchars($_esDesc) ?></span>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($_esActions)): ?>
            <div class="empty-state-actions">
                <?php foreach ($_esActions as $_esAction): ?>
                    <?php
                    if (!is_array($_esAction)) {
                        continue;
                    }
                    $_esActionLabel = (string) ($_esAction['label'] ?? '');
                    $_esActionUrl = (string) ($_esAction['url'] ?? '#');
                    $_esActionIcon = (string) ($_esAction['icon'] ?? '');
                    $_esActionClass = (string) ($_esAction['class'] ?? '');
                    if ($_esActionLabel === '') {
                        continue;
                    }
                    ?>
                    <a class="empty-state-action <?= htmlspecialchars($_esActionClass) ?>" href="<?= htmlspecialchars($_esActionUrl) ?>">
                        <?php if ($_esActionIcon !== ''): ?><i class="bi <?= htmlspecialchars($_esActionIcon) ?>" aria-hidden="true"></i><?php endif; ?>
                        <span><?= htmlspecialchars($_esActionLabel) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
