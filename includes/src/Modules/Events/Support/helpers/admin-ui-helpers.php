<?php
/**
 * Admin UI Helpers for Events Module
 */

/**
 * Render admin header with theme toggle
 */
function eventsAdminHeader($title = '', $subtitle = '', $icon = 'bi-speedometer2') {
    ?>
    <div class="ui-events-admin-header ui-panel__head">
        <div>
            <h1 class="ui-events-admin-header-title">
                <i class="bi <?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
                <?= htmlspecialchars($title) ?>
            </h1>
            <?php if ($subtitle): ?>
                <p class="ui-events-admin-header-subtitle">
                    <?= htmlspecialchars($subtitle) ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="ui-events-admin-header-actions">
            <button id="theme-toggle" class="ui-admin-btn ui-events-admin-icon-button" aria-label="Toggle dark mode" aria-pressed="false" title="Dark mode">
                <i class="bi bi-moon-fill" aria-hidden="true"></i>
            </button>
            <button class="admin-sidebar-toggle" aria-label="Toggle navigation" aria-expanded="false">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <?php
}

/**
 * Render sidebar toggle button
 */
function eventsAdminSidebarToggle() {
    ?>
    <button class="admin-sidebar-toggle ui-events-admin-fixed-toggle" aria-label="Toggle navigation" aria-expanded="false">
        <i class="bi bi-list" aria-hidden="true"></i>
    </button>
    <?php
}

/**
 * Render skip to main content link
 */
function eventsAdminSkipLink() {
    ?>
    <a href="#main-content" class="admin-skip-link">Skip to main content</a>
    <?php
}

/**
 * Render responsive table wrapper
 */
function eventsAdminTableStart($caption = '') {
    ?>
    <div class="table-responsive ui-table-wrap ui-surface">
        <table class="ui-events-table">
            <?php if ($caption): ?>
                <caption><?= htmlspecialchars($caption) ?></caption>
            <?php endif; ?>
    <?php
}

/**
 * Close responsive table wrapper
 */
function eventsAdminTableEnd() {
    ?>
        </table>
    </div>
    <?php
}

/**
 * Render accessible form field
 */
function eventsAdminFormField($name, $label, $type = 'text', $value = '', $required = false, $help = '', $error = '') {
    $id = 'field_' . htmlspecialchars($name);
    $hasError = !empty($error);
    ?>
    <div class="form-group ui-events-admin-form-field <?= $hasError ? 'has-error' : '' ?>">
        <label for="<?= $id ?>" class="form-label">
            <?= htmlspecialchars($label) ?>
            <?php if ($required): ?>
                <span class="required" aria-label="required">*</span>
            <?php endif; ?>
        </label>

        <?php if ($type === 'textarea'): ?>
            <textarea
                id="<?= $id ?>"
                name="<?= htmlspecialchars($name) ?>"
                <?= $required ? 'required' : '' ?>
                class="form-textarea ui-events-admin-control"
            ><?= htmlspecialchars($value) ?></textarea>
        <?php elseif ($type === 'select'): ?>
            <select
                id="<?= $id ?>"
                name="<?= htmlspecialchars($name) ?>"
                <?= $required ? 'required' : '' ?>
                class="form-select ui-events-admin-control"
            >
                <option value="">-- Select --</option>
            </select>
        <?php else: ?>
            <input
                type="<?= htmlspecialchars($type) ?>"
                id="<?= $id ?>"
                name="<?= htmlspecialchars($name) ?>"
                value="<?= htmlspecialchars($value) ?>"
                <?= $required ? 'required' : '' ?>
                class="form-input ui-events-admin-control"
            />
        <?php endif; ?>

        <?php if ($help): ?>
            <small class="form-help">
                <?= htmlspecialchars($help) ?>
            </small>
        <?php endif; ?>

        <?php if ($error): ?>
            <span class="form-error" role="alert">
                <?= htmlspecialchars($error) ?>
            </span>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render status message
 */
function eventsAdminStatusMessage($message, $type = 'info') {
    $classes = [
        'success' => 'status-message success',
        'error' => 'status-message error',
        'warning' => 'status-message warning',
        'info' => 'status-message info'
    ];

    $icons = [
        'success' => 'bi-check-circle',
        'error' => 'bi-exclamation-circle',
        'warning' => 'bi-exclamation-triangle',
        'info' => 'bi-info-circle'
    ];
    ?>
    <div class="<?= $classes[$type] ?? 'status-message info' ?> ui-events-admin-status-message" role="alert">
        <i class="bi <?= $icons[$type] ?? 'bi-info-circle' ?>" aria-hidden="true"></i>
        <span><?= htmlspecialchars($message) ?></span>
    </div>
    <?php
}

/**
 * Render breadcrumb navigation
 */
function eventsAdminBreadcrumb($items = []) {
    ?>
    <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <?php foreach ($items as $index => $item): ?>
            <?php if ($index > 0): ?>
                <span class="admin-breadcrumb-separator" aria-hidden="true">/</span>
            <?php endif; ?>

            <?php if (isset($item['url'])): ?>
                <a href="<?= htmlspecialchars($item['url']) ?>">
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php else: ?>
                <span class="admin-breadcrumb-current" aria-current="page">
                    <?= htmlspecialchars($item['label']) ?>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <?php
}
?>
