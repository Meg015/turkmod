<?php

declare(strict_types=1);

$modalConfig = is_array($logClearModal ?? null) ? $logClearModal : [];
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$modalId = (string) ($modalConfig['id'] ?? 'clearLogsModal');
$modalTitle = (string) ($modalConfig['title'] ?? 'Günlüğü Temizle');
$ariaLabel = (string) ($modalConfig['aria_label'] ?? $modalTitle);
$confirmTitle = (string) ($modalConfig['confirm_title'] ?? $modalTitle);
$formAction = (string) ($modalConfig['form_action'] ?? '');
$formMethod = strtoupper((string) ($modalConfig['form_method'] ?? 'post'));
$scopeName = (string) ($modalConfig['scope_name'] ?? 'scope');
$scopeLabel = (string) ($modalConfig['scope_label'] ?? 'Neler Silinsin?');
$options = is_array($modalConfig['options'] ?? null) ? $modalConfig['options'] : [];
$hiddenFields = is_array($modalConfig['hidden_fields'] ?? null) ? $modalConfig['hidden_fields'] : [];
$fields = is_array($modalConfig['fields'] ?? null) ? $modalConfig['fields'] : [];
$warningText = (string) ($modalConfig['warning'] ?? 'Seçilen kayıtlar kalıcı olarak silinir. Bu işlem geri alınamaz.');
$warningTitle = (string) ($modalConfig['warning_title'] ?? 'Uyarı:');
$cancelLabel = (string) ($modalConfig['cancel_label'] ?? 'İptal');
$submitLabel = (string) ($modalConfig['submit_label'] ?? 'Seçilenleri Kalıcı Olarak Sil');
$includeCsrf = (bool) ($modalConfig['csrf'] ?? true);
$includeScript = (bool) ($modalConfig['include_script'] ?? true);
$baseForAsset = (string) ($modalConfig['base_uri'] ?? ($baseUri ?? ''));

$renderAttributes = static function (array $attributes) use ($escape): void {
    foreach ($attributes as $name => $value) {
        if ($value === false || $value === null) {
            continue;
        }

        if ($value === true) {
            echo ' ' . $escape($name);
            continue;
        }

        echo ' ' . $escape($name) . '="' . $escape($value) . '"';
    }
};

?>
<div class="media-modal-overlay" id="<?= $escape($modalId) ?>" role="dialog" aria-modal="true" aria-label="<?= $escape($ariaLabel) ?>" hidden aria-hidden="true" data-confirm-title="<?= $escape($confirmTitle) ?>">
    <div class="media-modal ui-admin-modal-sm ui-panel">
        <div class="media-modal-header ui-panel__head">
            <h3 class="ui-admin-modal-title"><i class="bi bi-trash"></i> <?= $escape($modalTitle) ?></h3>
            <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-ghost" data-ui-modal-close data-clear-logs-close>&times;</button>
        </div>
        <div class="media-modal-body ui-panel__body">
            <form id="clearLogsForm" data-clear-logs-form method="<?= $escape($formMethod) ?>" action="<?= $escape($formAction) ?>">
                <?php if ($includeCsrf && function_exists('csrf_field')): ?>
                    <?= csrf_field() ?>
                <?php elseif (!empty($modalConfig['csrf_token'])): ?>
                    <input type="hidden" name="_token" value="<?= $escape($modalConfig['csrf_token']) ?>">
                <?php endif; ?>

                <?php foreach ($hiddenFields as $field): ?>
                    <?php
                    if (!is_array($field)) {
                        continue;
                    }
                    $fieldName = (string) ($field['name'] ?? '');
                    if ($fieldName === '') {
                        continue;
                    }
                    ?>
                    <input type="hidden" name="<?= $escape($fieldName) ?>" value="<?= $escape($field['value'] ?? '') ?>">
                <?php endforeach; ?>

                <?php if (isset($modalConfig['extra_hidden_renderer']) && is_callable($modalConfig['extra_hidden_renderer'])): ?>
                    <?php $modalConfig['extra_hidden_renderer'](); ?>
                <?php endif; ?>

                <div class="ui-admin-mb-md">
                    <label class="ui-admin-form-label"><?= $escape($scopeLabel) ?></label>
                    <select name="<?= $escape($scopeName) ?>" class="ui-admin-form-select" required data-clear-logs-scope>
                        <?php foreach ($options as $option): ?>
                            <?php
                            if (!is_array($option)) {
                                continue;
                            }
                            $optionValue = (string) ($option['value'] ?? '');
                            $optionLabel = (string) ($option['label'] ?? $optionValue);
                            $optionAttrs = [
                                'value' => $optionValue,
                                'data-confirm-title' => $option['confirm_title'] ?? null,
                                'data-confirm-message' => $option['confirm_message'] ?? null,
                                'data-confirm-ok' => $option['confirm_ok'] ?? null,
                                'selected' => !empty($option['selected']),
                                'disabled' => !empty($option['disabled']),
                            ];
                            ?>
                            <option<?php $renderAttributes($optionAttrs); ?>><?= $escape($optionLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php foreach ($fields as $field): ?>
                    <?php
                    if (!is_array($field)) {
                        continue;
                    }
                    $showFor = (string) ($field['show_for'] ?? '');
                    $fieldLabel = (string) ($field['label'] ?? '');
                    $fieldId = (string) ($field['id'] ?? '');
                    $input = is_array($field['input'] ?? null) ? $field['input'] : [];
                    if ($fieldId === '' && isset($input['id'])) {
                        $fieldId = (string) $input['id'];
                    }
                    ?>
                    <div class="ui-admin-mb-md"<?= $showFor !== '' ? ' data-clear-logs-field="' . $escape($showFor) . '"' : '' ?>>
                        <?php if ($fieldLabel !== ''): ?>
                            <label class="ui-admin-form-label"<?= $fieldId !== '' ? ' for="' . $escape($fieldId) . '"' : '' ?>><?= $escape($fieldLabel) ?></label>
                        <?php endif; ?>

                        <?php if (isset($field['renderer']) && is_callable($field['renderer'])): ?>
                            <?php $field['renderer'](); ?>
                        <?php elseif ($input !== []): ?>
                            <input<?php $renderAttributes($input); ?>>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <div class="ui-admin-alert ui-admin-alert-warning ui-admin-alert-spaced ui-alert ui-alert--warning" data-keep-inline-alert>
                    <strong><?= $escape($warningTitle) ?></strong> <?= $escape($warningText) ?>
                </div>

                <div class="media-modal-footer ui-admin-modal-footer-flush ui-panel__foot">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-clear-logs-close><?= $escape($cancelLabel) ?></button>
                    <button type="submit" class="ui-admin-btn ui-admin-btn-danger"><i class="bi bi-trash"></i> <?= $escape($submitLabel) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php if ($includeScript && empty($GLOBALS['adminLogClearModalScriptRendered'])): ?>
    <?php $GLOBALS['adminLogClearModalScriptRendered'] = true; ?>
    <script src="<?= asset_url('admin/assets/logs-clear-modal.js', $baseForAsset) ?>" defer></script>
<?php endif; ?>
