<?php

declare(strict_types=1);

$modalConfig = is_array($logClearModal ?? null) ? $logClearModal : [];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$modalId = (string) ($modalConfig['id'] ?? 'clearLogsModal');
$formAction = (string) ($modalConfig['form_action'] ?? '');
$hiddenFields = is_array($modalConfig['hidden_fields'] ?? null) ? $modalConfig['hidden_fields'] : [];
$includeCsrf = (bool) ($modalConfig['csrf'] ?? true);
$includeScript = (bool) ($modalConfig['include_script'] ?? true);
$baseForAsset = (string) ($modalConfig['base_uri'] ?? ($baseUri ?? ''));

?>
<div class="media-modal-overlay ui-admin-modal-overlay log-clear-overlay" id="<?= $escape($modalId) ?>" role="dialog" aria-modal="true" aria-labelledby="<?= $escape($modalId) ?>Title" aria-describedby="<?= $escape($modalId) ?>Description <?= $escape($modalId) ?>Warning" hidden aria-hidden="true">
    <form class="media-modal ui-admin-modal-shell ui-modal-shell ui-panel log-clear-modal" id="clearLogsForm" data-clear-logs-form method="post" action="<?= $escape($formAction) ?>">
        <?php if ($includeCsrf && function_exists('csrf_field')): ?>
            <?= csrf_field() ?>
        <?php elseif (!empty($modalConfig['csrf_token'])): ?>
            <input type="hidden" name="_token" value="<?= $escape($modalConfig['csrf_token']) ?>">
        <?php endif; ?>
        <input type="hidden" name="scope" value="all">
        <input type="hidden" name="_log_clear_silent" value="1">

        <?php foreach ($hiddenFields as $field): ?>
            <?php
            if (!is_array($field)) {
                continue;
            }
            $fieldName = (string) ($field['name'] ?? '');
            if ($fieldName === '' || in_array($fieldName, ['scope', '_log_clear_silent'], true)) {
                continue;
            }
            ?>
            <input type="hidden" name="<?= $escape($fieldName) ?>" value="<?= $escape($field['value'] ?? '') ?>">
        <?php endforeach; ?>

        <?php if (isset($modalConfig['extra_hidden_renderer']) && is_callable($modalConfig['extra_hidden_renderer'])): ?>
            <?php $modalConfig['extra_hidden_renderer'](); ?>
        <?php endif; ?>

        <div class="media-modal-header ui-modal__head ui-panel__head log-clear-modal__head">
            <div class="log-clear-modal__heading">
                <span class="log-clear-modal__icon" aria-hidden="true"><i class="bi bi-trash3"></i></span>
                <h3 id="<?= $escape($modalId) ?>Title">Günlüğü Temizle</h3>
            </div>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost log-clear-modal__close" aria-label="Kapat" data-ui-modal-close data-clear-logs-close><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
        <div class="media-modal-body ui-modal__body ui-panel__body log-clear-modal__body">
            <p class="log-clear-modal__description" id="<?= $escape($modalId) ?>Description">Bu günlükteki tüm kayıtlar kalıcı olarak silinecek.</p>
            <p class="log-clear-modal__warning" id="<?= $escape($modalId) ?>Warning"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Bu işlem geri alınamaz.</p>
        </div>
        <div class="media-modal-footer ui-modal__foot ui-panel__foot log-clear-modal__foot">
            <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-clear-logs-cancel data-clear-logs-close>Vazgeç</button>
            <button type="submit" class="ui-admin-btn ui-admin-btn-danger"><i class="bi bi-trash3" aria-hidden="true"></i> Tümünü sil</button>
        </div>
    </form>
</div>
<?php if ($includeScript && empty($GLOBALS['adminLogClearModalScriptRendered'])): ?>
    <?php $GLOBALS['adminLogClearModalScriptRendered'] = true; ?>
    <script src="<?= asset_url('admin/assets/logs-clear-modal.js', $baseForAsset) ?>" defer></script>
<?php endif; ?>
