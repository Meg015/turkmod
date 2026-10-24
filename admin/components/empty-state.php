<?php
declare(strict_types=1);

/**
 * Shared admin empty-state markup. Browser-rendered states clone the template
 * below, so PHP pages and asynchronous panels always use the same structure.
 * actions_html is reserved for trusted, server-rendered module action markup.
 *
 * @param array{icon?:string,tone?:string,title?:string,description?:string,size?:string,class?:string,attrs?:array,title_attrs?:array,description_attrs?:array,meta?:array,actions?:array,actions_html?:string,pro?:bool} $options
 */
function adminRenderEmptyState(array $options = []): string
{
    $sizes = ['regular', 'compact', 'inline'];
    $tones = ['neutral', 'info', 'success', 'warning', 'danger', 'muted'];
    $size = in_array($options['size'] ?? '', $sizes, true) ? $options['size'] : 'regular';
    $tone = in_array($options['tone'] ?? '', $tones, true) ? $options['tone'] : 'neutral';
    $icon = adminUiClass((string) ($options['icon'] ?? 'bi-inbox')) ?: 'bi-inbox';
    $title = (string) ($options['title'] ?? 'Kayıt bulunamadı');
    $description = (string) ($options['description'] ?? 'Yeni kayıtlar eklendiğinde burada görünecek.');
    $class = trim('admin-empty ' . adminUiClass((string) ($options['class'] ?? '')));
    $attrs = (array) ($options['attrs'] ?? []);
    $attrs['data-admin-empty-state'] = true;
    $attrs['data-empty-size'] = $size;
    $attrs['data-empty-tone'] = $tone;
    $attrs['role'] = $attrs['role'] ?? 'status';
    $attrs['aria-atomic'] = $attrs['aria-atomic'] ?? 'true';

    $html = '<div class="' . adminUiEscape($class) . '"' . adminUiAttrs($attrs) . '>';
    $html .= '<span class="admin-empty__icon" aria-hidden="true"><i class="bi ' . adminUiEscape($icon) . '"></i></span>';
    $html .= '<div class="admin-empty__content">';
    $html .= '<h3 class="admin-empty__title" data-empty-title' . adminUiAttrs((array) ($options['title_attrs'] ?? [])) . ($title === '' ? ' hidden' : '') . '>' . adminUiEscape($title) . '</h3>';
    $html .= '<p class="admin-empty__description" data-empty-description' . adminUiAttrs((array) ($options['description_attrs'] ?? [])) . ($description === '' ? ' hidden' : '') . '>' . adminUiEscape($description) . '</p>';

    $metaHtml = '';
    foreach ((array) ($options['meta'] ?? []) as $item) {
        $label = (string) ($item['label'] ?? '');
        if ($label === '') {
            continue;
        }
        $metaIcon = adminUiClass((string) ($item['icon'] ?? ''));
        $metaHtml .= '<span>' . ($metaIcon !== '' ? '<i class="bi ' . adminUiEscape($metaIcon) . '" aria-hidden="true"></i>' : '') . adminUiEscape($label) . '</span>';
    }
    $html .= '<div class="admin-empty__meta" data-empty-meta aria-label="Durum bilgisi"' . ($metaHtml === '' ? ' hidden' : '') . '>' . $metaHtml . '</div>';

    $actionsHtml = '';
    foreach ((array) ($options['actions'] ?? []) as $action) {
        $label = (string) ($action['label'] ?? '');
        if ($label === '') {
            continue;
        }
        $href = trim((string) ($action['href'] ?? ''));
        $tag = $href !== '' ? 'a' : 'button';
        $type = in_array($action['type'] ?? '', ['button', 'submit', 'reset'], true) ? $action['type'] : 'button';
        $buttonClass = trim('ui-admin-btn ui-admin-btn-outline ' . adminUiClass((string) ($action['class'] ?? '')));
        $actionIcon = adminUiClass((string) ($action['icon'] ?? ''));
        $actionsHtml .= '<' . $tag . ($href !== '' ? ' href="' . adminUiEscape($href) . '"' : ' type="' . $type . '"')
            . ' class="' . adminUiEscape($buttonClass) . '"' . adminUiAttrs((array) ($action['attrs'] ?? [])) . '>'
            . ($actionIcon !== '' ? '<i class="bi ' . adminUiEscape($actionIcon) . '" aria-hidden="true"></i>' : '')
            . adminUiEscape($label) . '</' . $tag . '>';
    }
    $actionsHtml .= (string) ($options['actions_html'] ?? '');
    $html .= '<div class="admin-empty__actions" data-empty-actions' . ($actionsHtml === '' ? ' hidden' : '') . '>' . $actionsHtml . '</div>';
    return $html . '</div></div>';
}

/** @param array{class?:string,attrs?:array} $rowOptions */
function adminRenderTableEmptyRow(int $columns, array $emptyOptions, array $rowOptions = []): string
{
    $emptyOptions['size'] = $emptyOptions['size'] ?? 'compact';
    $class = trim('admin-empty-row ' . adminUiClass((string) ($rowOptions['class'] ?? '')));
    $attrs = (array) ($rowOptions['attrs'] ?? []);
    $attrs['data-admin-empty-row'] = true;
    return '<tr class="' . adminUiEscape($class) . '"' . adminUiAttrs($attrs) . '><td class="admin-empty-cell" colspan="' . max(1, $columns) . '">'
        . adminRenderEmptyState($emptyOptions) . '</td></tr>';
}

function adminRenderEmptyStateTemplate(): string
{
    return '<template id="admin-empty-state-template">' . adminRenderEmptyState(['title' => '', 'description' => '']) . '</template>';
}
