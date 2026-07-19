<?php
declare(strict_types=1);

/**
 * Admin panel ic yardimci fonksiyonlari.
 *
 * Tek dosyada toplanan cesitli domain yardimcilari: admin ayarlari (getAdminSettings,
 * adminSettingDefinitions), izin/audit (adminRequirePermission, adminShouldAudit),
 * rapor/siralama/duzenleme yardimcilari vb.
 *
 * Standartlar acisindan bu dosya alan bazli alt dosyalara bolunmeli:
 *   admin/helpers/admin-settings.php
 *   admin/helpers/permissions.php
 *   admin/helpers/audit.php
 *   admin/helpers/... vb.
 * Bu bolunme sirasiyla gerceklestirilmelidir (init.php`deki require_once sirasina
 * dikkat). Bu dokumantasyon, sonraki bakimci icin yol isaretidir.
 */

function adminRenderForbiddenPage(string $message = 'Bu sayfaya erişim yetkiniz yok.'): void
{
    global $pdo, $baseUri;

    http_response_code(403);

    $pageTitle = 'Erişim Engellendi';
    $forbiddenMessage = $message;

    require __DIR__ . '/header.php';
    ?>
    <section class="ui-empty ui-admin-forbidden-empty" aria-labelledby="admin-forbidden-title" data-admin-forbidden-page data-admin-forbidden-message="<?= htmlspecialchars($forbiddenMessage, ENT_QUOTES, 'UTF-8') ?>">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
        <h2 id="admin-forbidden-title">Bu alan için yetkiniz yok</h2>
        <p><?= htmlspecialchars($forbiddenMessage, ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

function adminCurrentUserId(): int
{
    return (int) ($_SESSION['_auth_user_id'] ?? 0);
}

function adminPermissionList(array|string $permissions): array
{
    $items = is_array($permissions) ? $permissions : [$permissions];
    return array_values(array_filter(array_map(static function ($permission): string {
        return trim((string) $permission);
    }, $items), static fn(string $permission): bool => $permission !== ''));
}

function adminUserCanAny(?PDO $pdo, int $userId, array|string $permissions): bool
{
    if (!$pdo || $userId <= 0) {
        return false;
    }

    foreach (adminPermissionList($permissions) as $permission) {
        if (function_exists('userHasPermission') && userHasPermission($pdo, $userId, $permission)) {
            return true;
        }
    }

    return false;
}

function adminCurrentUserCan(array|string $permissions): bool
{
    global $pdo;

    return adminUserCanAny($pdo instanceof PDO ? $pdo : null, adminCurrentUserId(), $permissions);
}

function adminRequirePermission(array|string $permissions, string $message = 'Bu sayfaya erisim yetkiniz yok.'): void
{
    if (!adminCurrentUserCan($permissions)) {
        adminRenderForbiddenPage($message);
    }
}

function adminIsAjaxRequest(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
        || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
}

function adminDenyAction(string $message = 'Bu islemi yapma yetkiniz yok.', string $redirect = ''): void
{
    if (adminIsAjaxRequest()) {
        sendJsonResponse(403, false, $message, [
            'error' => $message,
        ], 'forbidden');
    }

    flash('error', $message);
    if ($redirect !== '') {
        $redirect = trim($redirect);
        if ($redirect !== '' && (preg_match('/[\x00-\x1F\x7F]/', $redirect) === 1 || preg_match('~^[a-z][a-z0-9+.-]*:~i', $redirect) === 1 || str_starts_with($redirect, '//') || str_contains($redirect, '\\'))) {
            $redirect = '';
        }
    }
    if ($redirect !== '') {
        header('Location: ' . $redirect);
        exit;
    }

    adminRenderForbiddenPage($message);
}

if (!defined('ADMIN_PAGINATION_PER_PAGE')) {
    define('ADMIN_PAGINATION_PER_PAGE', 10);
}

function adminPaginationPerPage(): int
{
    return (int) ADMIN_PAGINATION_PER_PAGE;
}

/**
 * Render the standard admin pagination control.
 *
 * @param callable(int): string $urlForPage
 * @param array<string, mixed> $options
 */
function adminRenderPagination(int $totalPages, int $currentPage, callable $urlForPage, array $options = []): string
{
    $totalPages = max(1, $totalPages);
    if ($totalPages <= 1) {
        return '';
    }

    $currentPage = max(1, min($currentPage, $totalPages));
    $window = max(1, (int) ($options['window'] ?? 2));
    $wrapperClass = trim('pagination-wrapper admin-ui-pagination ' . (string) ($options['wrapper_class'] ?? ''));
    $innerClass = trim('pagination admin-ui-pagination__inner ' . (string) ($options['inner_class'] ?? ''));
    $ariaLabel = (string) ($options['aria_label'] ?? 'Sayfa gezinme');
    $showEdges = (bool) ($options['show_edges'] ?? true);

    $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $pageLink = static function (int $targetPage, string $label, string $extraClass = '', string $ariaLabel = '') use ($urlForPage, $escape, $currentPage): string {
        $isActive = $targetPage === $currentPage;
        $class = trim('page-link ' . $extraClass . ($isActive ? ' active' : ''));
        $ariaCurrent = $isActive ? ' aria-current="page"' : '';
        $aria = $ariaLabel !== '' ? ' aria-label="' . $escape($ariaLabel) . '"' : '';

        return '<a href="' . $escape((string) $urlForPage($targetPage)) . '" class="' . $escape($class) . '"' . $aria . $ariaCurrent . '>' . $label . '</a>';
    };
    $gap = '<span class="page-link pagination-ellipsis" aria-hidden="true">...</span>';

    $items = [];
    if ($currentPage > 1) {
        $items[] = $pageLink($currentPage - 1, '<i class="bi bi-chevron-left" aria-hidden="true"></i>', '', 'Önceki sayfa');
    }

    $start = max(1, $currentPage - $window);
    $end = min($totalPages, $currentPage + $window);

    if ($showEdges && $start > 1) {
        $items[] = $pageLink(1, '1');
        if ($start > 2) {
            $items[] = $gap;
        }
    }

    for ($page = $start; $page <= $end; $page++) {
        $items[] = $pageLink($page, (string) $page);
    }

    if ($showEdges && $end < $totalPages) {
        if ($end < $totalPages - 1) {
            $items[] = $gap;
        }
        $items[] = $pageLink($totalPages, (string) $totalPages);
    }

    if ($currentPage < $totalPages) {
        $items[] = $pageLink($currentPage + 1, '<i class="bi bi-chevron-right" aria-hidden="true"></i>', '', 'Sonraki sayfa');
    }

    return '<nav class="' . $escape($wrapperClass) . '" aria-label="' . $escape($ariaLabel) . '"><div class="' . $escape($innerClass) . '">' . implode('', $items) . '</div></nav>';
}

function adminUiEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function adminUiClass(string $value): string
{
    $value = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $value) ?? '';
    $parts = preg_split('/\s+/', trim($value)) ?: [];
    $unique = [];
    foreach ($parts as $part) {
        if ($part === '' || isset($unique[$part])) {
            continue;
        }
        $unique[$part] = true;
    }

    return implode(' ', array_keys($unique));
}

function adminUiAttrName(string $name): string
{
    $name = trim($name);
    if ($name === '' || preg_match('/^[a-zA-Z_:][a-zA-Z0-9_:\-\.]*$/', $name) !== 1) {
        return '';
    }

    return $name;
}

/**
 * @param array<string,string|int|bool|null> $attrs
 */
function adminUiAttrs(array $attrs): string
{
    $html = '';
    foreach ($attrs as $name => $value) {
        if ($value === null || $value === false) {
            continue;
        }
        $attrName = adminUiAttrName((string) $name);
        if ($attrName === '') {
            continue;
        }
        $html .= ' ' . adminUiEscape($attrName);
        if ($value !== true) {
            $html .= '="' . adminUiEscape($value) . '"';
        }
    }

    return $html;
}

function adminUiPanelTag(string $tag, string $default = 'section'): string
{
    $allowed = ['section' => true, 'div' => true, 'article' => true, 'aside' => true, 'form' => true];
    $default = strtolower(trim($default));
    $default = isset($allowed[$default]) ? $default : 'section';
    $tag = strtolower(trim($tag));

    return isset($allowed[$tag]) ? $tag : $default;
}

function adminSettingListValues(mixed $value, bool $normalizeExemptionTokens = false): array
{
    $rawItems = is_array($value)
        ? $value
        : (preg_split('/[\r\n,;]+/u', (string) $value) ?: []);
    $items = [];

    foreach ($rawItems as $item) {
        $item = trim((string) $item);
        if ($item === '') {
            continue;
        }
        if ($normalizeExemptionTokens && function_exists('commentSpamNormalizeExemptionToken')) {
            $item = commentSpamNormalizeExemptionToken($item);
        }
        if ($item === '') {
            continue;
        }

        $items[$item] = $item;
    }

    return array_values($items);
}

function adminNormalizeTone(string $tone): string
{
    $tone = strtolower(trim($tone));
    $map = [
        'ok' => 'success',
        'active' => 'success',
        'enabled' => 'success',
        'published' => 'success',
        'approved' => 'success',
        'sent' => 'success',
        'read' => 'success',
        'success' => 'success',
        'warn' => 'warning',
        'pending' => 'warning',
        'queued' => 'warning',
        'revision' => 'warning',
        'warning' => 'warning',
        'error' => 'danger',
        'failed' => 'danger',
        'fail' => 'danger',
        'rejected' => 'danger',
        'danger' => 'danger',
        'notice' => 'info',
        'processing' => 'info',
        'info' => 'info',
        'primary' => 'primary',
        'accent' => 'accent',
        'inactive' => 'muted',
        'disabled' => 'muted',
        'expired' => 'muted',
        'skipped' => 'muted',
        'none' => 'muted',
        'neutral' => 'muted',
        'secondary' => 'muted',
        'muted' => 'muted',
    ];

    return $map[$tone] ?? 'muted';
}

function adminToneBadgeClass(string $tone, string $prefix = 'ui-admin-badge-'): string
{
    return $prefix . adminNormalizeTone($tone);
}

/**
 * @return array{label:string,tone:string,icon:string}
 */
function adminStatusMeta(string $status, string $domain = 'generic'): array
{
    $key = strtolower(trim($status));
    $domain = strtolower(trim($domain));
    $fallbackLabel = $key !== '' ? ucwords(str_replace(['_', '-'], ' ', $key)) : 'Durum yok';

    $maps = [
        'log_level' => [
            'emergency' => ['label' => 'Acil', 'tone' => 'danger', 'icon' => 'bi-exclamation-octagon'],
            'alert' => ['label' => 'Alarm', 'tone' => 'danger', 'icon' => 'bi-exclamation-octagon'],
            'critical' => ['label' => 'Kritik', 'tone' => 'danger', 'icon' => 'bi-bug'],
            'error' => ['label' => 'Hata', 'tone' => 'danger', 'icon' => 'bi-x-circle'],
            'warning' => ['label' => 'Uyari', 'tone' => 'warning', 'icon' => 'bi-exclamation-triangle'],
            'warn' => ['label' => 'Uyari', 'tone' => 'warning', 'icon' => 'bi-exclamation-triangle'],
            'notice' => ['label' => 'Bildirim', 'tone' => 'info', 'icon' => 'bi-info-circle'],
            'info' => ['label' => 'Bilgi', 'tone' => 'success', 'icon' => 'bi-info-circle'],
            'debug' => ['label' => 'Debug', 'tone' => 'muted', 'icon' => 'bi-terminal'],
        ],
        'cron' => [
            'success' => ['label' => 'Basarili', 'tone' => 'success', 'icon' => 'bi-check2-circle'],
            'warning' => ['label' => 'Uyari', 'tone' => 'warning', 'icon' => 'bi-exclamation-triangle'],
            'error' => ['label' => 'Hata', 'tone' => 'danger', 'icon' => 'bi-bug'],
            'skipped' => ['label' => 'Atlandi', 'tone' => 'muted', 'icon' => 'bi-skip-forward'],
            'missing' => ['label' => 'Yok', 'tone' => 'muted', 'icon' => 'bi-dash-circle'],
        ],
        'topic' => [
            'draft' => ['label' => 'Taslak', 'tone' => 'warning', 'icon' => 'bi-pencil-square'],
            'published' => ['label' => 'Yayinda', 'tone' => 'success', 'icon' => 'bi-check-circle'],
            'approved' => ['label' => 'Onayli', 'tone' => 'success', 'icon' => 'bi-check2-circle'],
            'rejected' => ['label' => 'Reddedildi', 'tone' => 'danger', 'icon' => 'bi-x-circle'],
            'revision' => ['label' => 'Revizyon', 'tone' => 'warning', 'icon' => 'bi-arrow-repeat'],
            'pending' => ['label' => 'Taslak', 'tone' => 'warning', 'icon' => 'bi-hourglass-split'],
        ],
        'read' => [
            'read' => ['label' => 'Okundu', 'tone' => 'success', 'icon' => 'bi-check2-circle'],
            'unread' => ['label' => 'Okunmadi', 'tone' => 'warning', 'icon' => 'bi-circle-fill'],
        ],
        'enabled' => [
            '1' => ['label' => 'Aktif', 'tone' => 'success', 'icon' => 'bi-check-circle'],
            '0' => ['label' => 'Pasif', 'tone' => 'muted', 'icon' => 'bi-dash-circle'],
            'active' => ['label' => 'Aktif', 'tone' => 'success', 'icon' => 'bi-check-circle'],
            'inactive' => ['label' => 'Pasif', 'tone' => 'muted', 'icon' => 'bi-dash-circle'],
        ],
        'ban_appeal' => [
            'open' => ['label' => 'Acik', 'tone' => 'warning', 'icon' => 'bi-hourglass-split'],
            'reviewing' => ['label' => 'Inceleniyor', 'tone' => 'primary', 'icon' => 'bi-search'],
            'accepted' => ['label' => 'Kabul Edildi', 'tone' => 'success', 'icon' => 'bi-check-circle'],
            'rejected' => ['label' => 'Reddedildi', 'tone' => 'danger', 'icon' => 'bi-x-circle'],
        ],
        'report' => [
            'open' => ['label' => 'Acik', 'tone' => 'danger', 'icon' => 'bi-exclamation-circle-fill'],
            'reviewing' => ['label' => 'Inceleniyor', 'tone' => 'warning', 'icon' => 'bi-hourglass-split'],
            'resolved' => ['label' => 'Cozuldu', 'tone' => 'success', 'icon' => 'bi-check2-circle'],
            'rejected' => ['label' => 'Reddedildi', 'tone' => 'muted', 'icon' => 'bi-x-circle'],
        ],
        'email_delivery' => [
            'sent' => ['label' => 'Gonderildi', 'tone' => 'success', 'icon' => 'bi-check2-circle'],
            'failed' => ['label' => 'Hatali', 'tone' => 'danger', 'icon' => 'bi-exclamation-octagon'],
            'queued' => ['label' => 'Kuyrukta', 'tone' => 'warning', 'icon' => 'bi-hourglass-split'],
            'processing' => ['label' => 'Isleniyor', 'tone' => 'info', 'icon' => 'bi-arrow-repeat'],
            'skipped' => ['label' => 'Atlandi', 'tone' => 'muted', 'icon' => 'bi-dash-circle'],
        ],
    ];

    $meta = $maps[$domain][$key] ?? null;
    if ($meta === null && $domain !== 'generic') {
        $meta = $maps['enabled'][$key] ?? null;
    }
    if ($meta === null) {
        $meta = ['label' => $fallbackLabel, 'tone' => adminNormalizeTone($key), 'icon' => 'bi-circle'];
    }

    $meta['tone'] = adminNormalizeTone((string) ($meta['tone'] ?? 'muted'));
    return $meta;
}

/**
 * @param array{label?:string,tone?:string,icon?:string,class?:string,size?:string,attrs?:array<string,string|int|bool|null>,title?:string} $options
 */
function adminRenderStatusBadge(string $status, string $domain = 'generic', array $options = []): string
{
    $meta = adminStatusMeta($status, $domain);
    $label = (string) ($options['label'] ?? ($meta['label'] ?? $status));
    $badgeOptions = $options;
    unset($badgeOptions['label']);
    $badgeOptions['tone'] = (string) ($options['tone'] ?? ($meta['tone'] ?? 'muted'));
    $badgeOptions['icon'] = (string) ($options['icon'] ?? ($meta['icon'] ?? ''));

    return adminRenderBadge($label, $badgeOptions);
}

/**
 * @param array{tone?:string,icon?:string,class?:string,size?:string,attrs?:array<string,string|int|bool|null>,title?:string} $options
 */
function adminRenderBadge(string $label, array $options = []): string
{
    $label = trim($label);
    if ($label === '') {
        return '';
    }

    $tone = adminNormalizeTone((string) ($options['tone'] ?? 'muted'));
    $size = adminUiClass((string) ($options['size'] ?? ''));
    $sizeClass = $size !== '' ? 'ui-admin-badge-' . $size : '';
    $class = trim(adminUiClass('ui-admin-badge admin-badge ' . adminToneBadgeClass($tone) . ' admin-badge-' . $tone . ' ' . $sizeClass . ' ' . (string) ($options['class'] ?? '')));
    $attrs = (array) ($options['attrs'] ?? []);
    if (!empty($options['title'])) {
        $attrs['title'] = (string) $options['title'];
    }

    $icon = adminUiClass((string) ($options['icon'] ?? ''));
    $html = '<span class="' . adminUiEscape($class) . '"' . adminUiAttrs($attrs) . '>';
    if ($icon !== '') {
        $html .= '<i class="bi ' . adminUiEscape($icon) . '"></i> ';
    }
    $html .= adminUiEscape($label) . '</span>';

    return $html;
}

/**
 * @param array{icon?:string,title?:string,html?:string,class?:string,attrs?:array<string,string|int|bool|null>,role?:string,closable?:bool,close_label?:string,close_class?:string} $options
 */
function adminRenderAlert(string $message, string $tone = 'info', array $options = []): string
{
    return uiRenderAlert($message, $tone, $options);
}

function adminRenderFlashAlerts(?string $successMessage = null, ?string $errorMessage = null, array $options = []): string
{
    return uiRenderFlashAlerts($successMessage, $errorMessage, $options);
}

/**
 * @param array<int,array{href?:string,label:string,icon?:string,class?:string,attrs?:array<string,string|int|bool|null>,type?:string}> $actions
 * @param array{class?:string,attrs?:array<string,string|int|bool|null>} $options
 */
function adminRenderActionButtons(array $actions, array $options = []): string
{
    $class = trim(adminUiClass('admin-ui-action-row ' . (string) ($options['class'] ?? '')));
    $html = '<div class="' . adminUiEscape($class) . '"' . adminUiAttrs((array) ($options['attrs'] ?? [])) . '>';

    foreach ($actions as $action) {
        $label = (string) ($action['label'] ?? '');
        if ($label === '') {
            continue;
        }

        $href = trim((string) ($action['href'] ?? ''));
        $type = trim((string) ($action['type'] ?? 'button'));
        if (!in_array($type, ['button', 'submit', 'reset'], true)) {
            $type = 'button';
        }

        $buttonClass = trim('ui-admin-btn ' . adminUiClass((string) ($action['class'] ?? '')));
        $iconHtml = trim((string) ($action['icon'] ?? '')) !== ''
            ? '<i class="bi ' . adminUiEscape(adminUiClass((string) $action['icon'])) . '"></i> '
            : '';
        $tagName = $href !== '' ? 'a' : 'button';
        $html .= '<' . $tagName
            . ($href !== '' ? ' href="' . adminUiEscape($href) . '"' : ' type="' . adminUiEscape($type) . '"')
            . ' class="' . adminUiEscape($buttonClass) . '"'
            . adminUiAttrs((array) ($action['attrs'] ?? [])) . '>'
            . $iconHtml . adminUiEscape($label)
            . '</' . $tagName . '>';
    }

    $html .= '</div>';
    return $html;
}

function adminSafeImageUrl(string $path, string $baseUri = '', bool $allowRemote = false): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return '';
    }

    if (preg_match('~^(?:https?:)?//~i', $path) === 1) {
        return $allowRemote ? $path : '';
    }

    if (str_starts_with($path, 'data:')) {
        return '';
    }

    if (str_starts_with($path, '/')) {
        return $path;
    }

    $baseUri = rtrim($baseUri !== '' ? $baseUri : (function_exists('base_uri') ? base_uri() : ''), '/');
    return ($baseUri !== '' ? $baseUri : '') . '/' . ltrim($path, '/');
}

function adminRenderImagePlaceholder(string $class = '', string $icon = 'bi-image', string $label = 'Gorsel yok'): string
{
    $class = trim(adminUiClass('admin-ui-image-placeholder ' . $class));
    $icon = adminUiClass($icon) ?: 'bi-image';

    return '<div class="' . adminUiEscape($class) . '" role="img" aria-label="' . adminUiEscape($label) . '"><i class="bi ' . adminUiEscape($icon) . '"></i></div>';
}

/**
 * @param array<int,string|array{label?:string,html?:string,class?:string,attrs?:array<string,string|int|bool|null>}> $headers
 * @param array{class?:string,wrap_class?:string,wrap_attrs?:array<string,string|int|bool|null>,attrs?:array<string,string|int|bool|null>,tbody_attrs?:array<string,string|int|bool|null>,colgroup_html?:string,label?:string} $options
 */
function adminRenderTableOpen(array $headers, array $options = []): string
{
    $wrapClass = trim(adminUiClass('ui-admin-table-wrap-x admin-ui-table-wrap ' . (string) ($options['wrap_class'] ?? '')));
    $tableClass = trim(adminUiClass('ui-admin-table admin-ui-table ' . (string) ($options['class'] ?? '')));
    $label = trim((string) ($options['label'] ?? 'Admin tablo'));

    $html = '<div class="' . adminUiEscape($wrapClass) . '"' . adminUiAttrs((array) ($options['wrap_attrs'] ?? [])) . '><table class="' . adminUiEscape($tableClass) . '" aria-label="' . adminUiEscape($label) . '"' . adminUiAttrs((array) ($options['attrs'] ?? [])) . '>';
    $colgroupHtml = trim((string) ($options['colgroup_html'] ?? ''));
    if ($colgroupHtml !== '') {
        $html .= $colgroupHtml;
    }
    $html .= '<thead><tr>';
    foreach ($headers as $header) {
        if (is_array($header)) {
            $text = (string) ($header['label'] ?? '');
            $headerHtml = (string) ($header['html'] ?? '');
            $class = trim(adminUiClass((string) ($header['class'] ?? '')));
            $attrs = adminUiAttrs((array) ($header['attrs'] ?? []));
        } else {
            $text = (string) $header;
            $headerHtml = '';
            $class = '';
            $attrs = '';
        }
        $html .= '<th' . ($class !== '' ? ' class="' . adminUiEscape($class) . '"' : '') . $attrs . '>' . ($headerHtml !== '' ? $headerHtml : adminUiEscape($text)) . '</th>';
    }
    $html .= '</tr></thead><tbody' . adminUiAttrs((array) ($options['tbody_attrs'] ?? [])) . '>';

    return $html;
}

function adminRenderTableClose(): string
{
    return '</tbody></table></div>';
}

function adminRenderTableEmptyRow(int $columns, array $emptyOptions): string
{
    return '<tr><td colspan="' . max(1, $columns) . '">' . adminRenderEmptyState($emptyOptions) . '</td></tr>';
}

/**
 * @param array{
 *   type?:string,name:string,id?:string,label?:string,value?:mixed,options?:array<string,string>,
 *   placeholder?:string,help?:string,class?:string,control_class?:string,attrs?:array<string,string|int|bool|null>,
 *   required?:bool,disabled?:bool,readonly?:bool,rows?:int
 * } $options
 */
function adminRenderFormField(array $options): string
{
    $name = trim((string) ($options['name'] ?? ''));
    if ($name === '') {
        return '';
    }

    $type = trim((string) ($options['type'] ?? 'text'));
    $id = trim((string) ($options['id'] ?? preg_replace('/[^a-zA-Z0-9_-]/', '_', $name)));
    $label = (string) ($options['label'] ?? '');
    $value = (string) ($options['value'] ?? '');
    $fieldClass = trim(adminUiClass('admin-ui-field ' . (string) ($options['class'] ?? '')));
    $controlClass = trim(adminUiClass((string) ($options['control_class'] ?? '')));
    $attrs = (array) ($options['attrs'] ?? []);
    $attrs['id'] = $id;
    $attrs['name'] = $name;
    if (!empty($options['required'])) {
        $attrs['required'] = true;
    }
    if (!empty($options['disabled'])) {
        $attrs['disabled'] = true;
        $attrs['aria-disabled'] = 'true';
    }
    if (!empty($options['readonly'])) {
        $attrs['readonly'] = true;
    }
    if (isset($options['placeholder'])) {
        $attrs['placeholder'] = (string) $options['placeholder'];
    }

    $html = '<div class="' . adminUiEscape($fieldClass) . '">';
    if ($label !== '' && $type !== 'checkbox' && $type !== 'switch') {
        $html .= '<label class="ui-admin-form-label admin-ui-field__label" for="' . adminUiEscape($id) . '">' . adminUiEscape($label) . '</label>';
    }

    if ($type === 'textarea') {
        $attrs['rows'] = max(2, (int) ($options['rows'] ?? 3));
        $class = trim('ui-admin-form-control admin-ui-field__control ' . $controlClass);
        $html .= '<textarea class="' . adminUiEscape($class) . '"' . adminUiAttrs($attrs) . '>' . adminUiEscape($value) . '</textarea>';
    } elseif ($type === 'select') {
        $class = trim('ui-admin-form-select admin-ui-field__control ' . $controlClass);
        $html .= '<select class="' . adminUiEscape($class) . '"' . adminUiAttrs($attrs) . '>';
        foreach ((array) ($options['options'] ?? []) as $optionValue => $optionLabel) {
            $selected = (string) $optionValue === $value ? ' selected' : '';
            $html .= '<option value="' . adminUiEscape((string) $optionValue) . '"' . $selected . '>' . adminUiEscape((string) $optionLabel) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'checkbox' || $type === 'switch') {
        $attrs['type'] = 'checkbox';
        $attrs['value'] = (string) ($options['checkbox_value'] ?? '1');
        if ($value === (string) $attrs['value'] || $value === '1') {
            $attrs['checked'] = true;
        }
        $html .= '<label class="ui-admin-switch admin-ui-field__switch"><input' . adminUiAttrs($attrs) . '><span class="ui-admin-switch-label">' . adminUiEscape($label !== '' ? $label : $name) . '</span></label>';
    } else {
        $attrs['type'] = $type === 'number' ? 'number' : ($type !== '' ? $type : 'text');
        $attrs['value'] = $value;
        $class = trim('ui-admin-form-control admin-ui-field__control ' . $controlClass);
        $html .= '<input class="' . adminUiEscape($class) . '"' . adminUiAttrs($attrs) . '>';
    }

    $help = trim((string) ($options['help'] ?? ''));
    if ($help !== '') {
        $html .= '<small class="ui-admin-form-help admin-ui-field__help">' . adminUiEscape($help) . '</small>';
    }
    $html .= '</div>';

    return $html;
}

/**
 * @param array{tag?:string,class?:string,attrs?:array<string,string|int|bool|null>,body_class?:string,title?:string,icon?:string,subtitle?:string,header_class?:string,header_html?:string,actions?:array<int,array{href?:string,label:string,icon?:string,class?:string,attrs?:array<string,string|int|bool|null>}>,actions_html?:string} $options
 */
function adminRenderPanelOpen(array $options = []): string
{
    $tag = adminUiPanelTag((string) ($options['tag'] ?? 'section'));

    $class = trim(adminUiClass('admin-card ui-panel admin-ui-panel ' . (string) ($options['class'] ?? '')));
    $bodyClass = trim(adminUiClass('card-body ui-panel__body ui-card admin-ui-panel__body ' . (string) ($options['body_class'] ?? '')));
    $html = '<' . $tag . ' class="' . adminUiEscape($class) . '"' . adminUiAttrs((array) ($options['attrs'] ?? [])) . '>';

    $title = trim((string) ($options['title'] ?? ''));
    $headerHtml = trim((string) ($options['header_html'] ?? ''));
    $actions = (array) ($options['actions'] ?? []);
    $actionsHtml = trim((string) ($options['actions_html'] ?? ''));
    if ($title !== '' || $headerHtml !== '' || $actions !== [] || $actionsHtml !== '') {
        $headerClass = trim(adminUiClass('card-header ui-panel__head ui-card admin-ui-panel__head ui-admin-card-header-actions ' . (string) ($options['header_class'] ?? '')));
        $html .= '<div class="' . adminUiEscape($headerClass) . '">';
        if ($headerHtml !== '') {
            $html .= $headerHtml;
        } elseif ($title !== '') {
            $icon = adminUiClass((string) ($options['icon'] ?? ''));
            $subtitle = trim((string) ($options['subtitle'] ?? ''));
            $html .= '<div class="admin-ui-panel-title-wrap">';
            $html .= '<h3 class="admin-ui-panel-title">';
            if ($icon !== '') {
                $html .= '<i class="bi ' . adminUiEscape($icon) . '"></i> ';
            }
            $html .= adminUiEscape($title) . '</h3>';
            if ($subtitle !== '') {
                $html .= '<span class="admin-ui-panel-subtitle">' . adminUiEscape($subtitle) . '</span>';
            }
            $html .= '</div>';
        }
        if ($actions !== [] || $actionsHtml !== '') {
            $html .= '<div class="admin-ui-panel-actions">';
            if ($actions !== []) {
                $html .= adminRenderActionButtons($actions, ['class' => 'admin-ui-action-row-compact']);
            }
            $html .= $actionsHtml;
            $html .= '</div>';
        }
        $html .= '</div>';
    }

    $html .= '<div class="' . adminUiEscape($bodyClass) . '">';
    return $html;
}

function adminRenderPanelClose(string $tag = 'section'): string
{
    $tag = adminUiPanelTag($tag);

    return '</div></' . $tag . '>';
}

function adminRenderPanel(string $content, array $options = []): string
{
    $tag = adminUiPanelTag((string) ($options['tag'] ?? 'section'));
    return adminRenderPanelOpen($options) . $content . adminRenderPanelClose($tag);
}

/**
 * @param array{tag?:string,class?:string,attrs?:array<string,string|int|bool|null>} $options
 */
function adminRenderPanelShellOpen(array $options = []): string
{
    $tag = adminUiPanelTag((string) ($options['tag'] ?? 'section'));

    $class = trim(adminUiClass('admin-card ui-panel admin-ui-panel ' . (string) ($options['class'] ?? '')));
    return '<' . $tag . ' class="' . adminUiEscape($class) . '"' . adminUiAttrs((array) ($options['attrs'] ?? [])) . '>';
}

function adminRenderPanelShellClose(string $tag = 'section'): string
{
    $tag = adminUiPanelTag($tag);

    return '</' . $tag . '>';
}

/**
 * @param array{class?:string,attrs?:array<string,string|int|bool|null>,html?:string,icon?:string,icon_class?:string,icon_wrap_class?:string,title?:string,title_tag?:string,title_class?:string,description?:string,description_class?:string,layout?:string,actions_html?:string,actions_class?:string} $options
 */
function adminRenderPanelHeader(array $options = []): string
{
    $class = trim(adminUiClass('card-header ui-panel__head ' . (string) ($options['class'] ?? '')));
    $html = '<div class="' . adminUiEscape($class) . '"' . adminUiAttrs((array) ($options['attrs'] ?? [])) . '>';
    $customHtml = trim((string) ($options['html'] ?? ''));

    if ($customHtml !== '') {
        $html .= $customHtml;
    } else {
        $title = trim((string) ($options['title'] ?? ''));
        $description = trim((string) ($options['description'] ?? ''));
        $icon = adminUiClass((string) ($options['icon'] ?? ''));
        $layout = (string) ($options['layout'] ?? '');

        if ($layout === 'inline') {
            if ($icon !== '') {
                $iconClass = trim(adminUiClass((string) ($options['icon_class'] ?? 'me-2')));
                $html .= '<i class="bi ' . adminUiEscape(trim($icon . ' ' . $iconClass)) . '"></i>';
            }
            $html .= adminUiEscape($title);
        } else {
            if ($icon !== '') {
                $iconWrapClass = trim(adminUiClass((string) ($options['icon_wrap_class'] ?? 'settings-card-icon')));
                $html .= '<div class="' . adminUiEscape($iconWrapClass) . '"><i class="bi ' . adminUiEscape($icon) . '"></i></div>';
            }
            $html .= '<div>';
            if ($title !== '') {
                $titleTag = strtolower((string) ($options['title_tag'] ?? 'h3'));
                if (!in_array($titleTag, ['h2', 'h3', 'h4', 'h5', 'h6', 'div', 'strong'], true)) {
                    $titleTag = 'h3';
                }
                $titleClass = trim(adminUiClass((string) ($options['title_class'] ?? 'admin-ui-panel-title')));
                $html .= '<' . $titleTag . ($titleClass !== '' ? ' class="' . adminUiEscape($titleClass) . '"' : '') . '>' . adminUiEscape($title) . '</' . $titleTag . '>';
            }
            if ($description !== '') {
                $descriptionClass = trim(adminUiClass((string) ($options['description_class'] ?? 'admin-ui-panel-subtitle')));
                $html .= '<p' . ($descriptionClass !== '' ? ' class="' . adminUiEscape($descriptionClass) . '"' : '') . '>' . adminUiEscape($description) . '</p>';
            }
            $html .= '</div>';
        }
    }

    $actionsHtml = trim((string) ($options['actions_html'] ?? ''));
    if ($actionsHtml !== '') {
        $actionsClass = trim(adminUiClass((string) ($options['actions_class'] ?? 'admin-ui-panel-actions')));
        $html .= '<div class="' . adminUiEscape($actionsClass) . '">' . $actionsHtml . '</div>';
    }

    return $html . '</div>';
}

function adminRenderPanelBodyOpen(string $class = '', array $attrs = []): string
{
    $bodyClass = trim(adminUiClass('card-body ui-panel__body ' . $class));
    return '<div class="' . adminUiEscape($bodyClass) . '"' . adminUiAttrs($attrs) . '>';
}

function adminRenderPanelBodyClose(): string
{
    return '</div>';
}

/**
 * @param array{tag?:string,class?:string,attrs?:array<string,string|int|bool|null>,body_class?:string,body_attrs?:array<string,string|int|bool|null>,header_class?:string,header_html?:string,icon?:string,title?:string,description?:string} $options
 */
function adminRenderSubtabPanelOpen(array $options = []): string
{
    $tag = adminUiPanelTag((string) ($options['tag'] ?? 'div'), 'div');

    $class = trim(adminUiClass('settings-subtab-panel ui-panel ' . (string) ($options['class'] ?? '')));
    $html = '<' . $tag . ' class="' . adminUiEscape($class) . '"' . adminUiAttrs((array) ($options['attrs'] ?? [])) . '>';
    $title = trim((string) ($options['title'] ?? ''));
    $headerHtml = trim((string) ($options['header_html'] ?? ''));

    if ($title !== '' || $headerHtml !== '') {
        $html .= adminRenderPanelHeader([
            'class' => (string) ($options['header_class'] ?? ''),
            'html' => $headerHtml,
            'icon' => (string) ($options['icon'] ?? ''),
            'title' => $title,
            'layout' => 'inline',
        ]);
    }

    return $html . adminRenderPanelBodyOpen((string) ($options['body_class'] ?? ''), (array) ($options['body_attrs'] ?? []));
}

function adminRenderSubtabPanelClose(string $tag = 'div'): string
{
    $tag = adminUiPanelTag($tag, 'div');

    return adminRenderPanelBodyClose() . '</' . $tag . '>';
}

/**
 * @param array{tag?:string,class?:string,attrs?:array<string,string|int|bool|null>,header_class?:string,header_html?:string,icon?:string,title?:string,description?:string} $options
 */
function adminRenderSettingsCardOpen(array $options = []): string
{
    $tag = adminUiPanelTag((string) ($options['tag'] ?? 'div'), 'div');

    $class = trim(adminUiClass('settings-card ui-card ' . (string) ($options['class'] ?? '')));
    $html = '<' . $tag . ' class="' . adminUiEscape($class) . '"' . adminUiAttrs((array) ($options['attrs'] ?? [])) . '>';
    $html .= adminRenderPanelHeader([
        'class' => trim('settings-card-header ' . (string) ($options['header_class'] ?? '')),
        'html' => (string) ($options['header_html'] ?? ''),
        'icon' => (string) ($options['icon'] ?? 'bi-sliders'),
        'title' => (string) ($options['title'] ?? ''),
        'title_tag' => 'h6',
        'title_class' => 'settings-card-title',
        'description' => (string) ($options['description'] ?? ''),
        'description_class' => 'settings-card-desc',
    ]);

    return $html;
}

function adminRenderSettingsCardClose(string $tag = 'div'): string
{
    $tag = adminUiPanelTag($tag, 'div');

    return '</' . $tag . '>';
}

/**
 * @param array<string,array{target?:string,label:string,icon?:string,class?:string,attrs?:array<string,string|int|bool|null>}> $items
 * @param array{class?:string,button_class?:string,active_class?:string,inactive_class?:string,aria_label?:string} $options
 */
function adminRenderButtonTabs(array $items, string $active, array $options = []): string
{
    if ($items === []) {
        return '';
    }

    $wrapperClass = trim(adminUiClass('admin-ui-button-tabs ' . (string) ($options['class'] ?? '')));
    $buttonBaseClass = trim(adminUiClass('admin-ui-button-tab ' . (string) ($options['button_class'] ?? '')));
    $activeClass = adminUiClass((string) ($options['active_class'] ?? 'is-active')) ?: 'is-active';
    $inactiveClass = adminUiClass((string) ($options['inactive_class'] ?? ''));
    $ariaLabel = (string) ($options['aria_label'] ?? 'Sekmeler');

    $html = '<div class="' . adminUiEscape($wrapperClass) . '" role="tablist" aria-label="' . adminUiEscape($ariaLabel) . '">';
    foreach ($items as $key => $item) {
        $target = trim((string) ($item['target'] ?? $key));
        $label = (string) ($item['label'] ?? '');
        if ($target === '' || $label === '') {
            continue;
        }

        $isActive = $active === (string) $key || $active === $target;
        $classes = trim(adminUiClass(
            $buttonBaseClass . ' ' . (string) ($item['class'] ?? '') . ' ' . ($isActive ? $activeClass : $inactiveClass)
        ));
        $attrs = (array) ($item['attrs'] ?? []);
        $attrs['type'] = 'button';
        $attrs['data-tab-target'] = $target;
        $attrs['role'] = 'tab';
        $attrs['aria-selected'] = $isActive ? 'true' : 'false';
        $attrs['aria-controls'] = (string) ($attrs['aria-controls'] ?? $target);

        $icon = adminUiClass((string) ($item['icon'] ?? ''));
        $html .= '<button class="' . adminUiEscape($classes) . '"' . adminUiAttrs($attrs) . '>';
        if ($icon !== '') {
            $html .= '<i class="bi ' . adminUiEscape($icon) . '"></i> ';
        }
        $html .= adminUiEscape($label) . '</button>';
    }
    $html .= '</div>';

    return $html;
}

/**
 * @param array<string,array{label:string,href:string,icon?:string,description?:string,permission?:string,badge?:string|int,badge_tone?:string,badge_class?:string,class?:string,inactive_class?:string,title_class?:string,description_class?:string,copy_class?:string,icon_class?:string,icon_wrap_class?:string,leading_class?:string,attrs?:array<string,string|int|bool|null>}> $items
 * @param array{class?:string,link_class?:string,active_class?:string,inactive_class?:string,aria_label?:string,base_uri?:string,title_class?:string,description_class?:string,copy_class?:string,icon_class?:string,icon_wrap_class?:string,leading_class?:string,badge_class?:string} $options
 */
function adminRenderTabBar(array $items, string $active, array $options = []): string
{
    $visibleItems = array_filter($items, static function (array $item): bool {
        $permission = trim((string) ($item['permission'] ?? ''));
        return $permission === '' || (function_exists('adminCurrentUserCan') && adminCurrentUserCan($permission));
    });
    if ($visibleItems === []) {
        return '';
    }

    $baseUri = rtrim((string) ($options['base_uri'] ?? ($GLOBALS['baseUri'] ?? '')), '/');
    $navClass = trim(adminUiClass('site-subtabs admin-ui-tabs ' . (string) ($options['class'] ?? '')));
    $linkBaseClass = trim(adminUiClass('site-subtab-link admin-ui-tab ' . (string) ($options['link_class'] ?? '')));
    $activeClass = trim(adminUiClass((string) ($options['active_class'] ?? 'active')));
    $inactiveClass = trim(adminUiClass((string) ($options['inactive_class'] ?? '')));
    $ariaLabel = (string) ($options['aria_label'] ?? 'Alt sekmeler');
    $defaultTitleClass = trim(adminUiClass('admin-ui-tab-title ' . (string) ($options['title_class'] ?? '')));
    $defaultDescriptionClass = trim(adminUiClass('admin-ui-tab-desc ' . (string) ($options['description_class'] ?? '')));
    $defaultCopyClass = trim(adminUiClass('admin-ui-tab-copy ' . (string) ($options['copy_class'] ?? '')));
    $defaultIconClass = trim(adminUiClass((string) ($options['icon_class'] ?? '')));
    $defaultIconWrapClass = trim(adminUiClass((string) ($options['icon_wrap_class'] ?? '')));
    $defaultLeadingClass = trim(adminUiClass((string) ($options['leading_class'] ?? '')));
    $defaultBadgeClass = trim(adminUiClass('admin-ui-tab-badge ' . (string) ($options['badge_class'] ?? '')));

    $html = '<nav class="' . adminUiEscape($navClass) . '" aria-label="' . adminUiEscape($ariaLabel) . '">';
    foreach ($visibleItems as $key => $item) {
        $href = (string) ($item['href'] ?? '#');
        if ($baseUri !== '' && str_starts_with($href, '/')) {
            $href = $baseUri . $href;
        }

        $isActive = $active === (string) $key;
        $stateClass = $isActive
            ? $activeClass
            : trim(adminUiClass($inactiveClass . ' ' . (string) ($item['inactive_class'] ?? '')));
        $classes = trim(adminUiClass($linkBaseClass . ' ' . (string) ($item['class'] ?? '') . ($stateClass !== '' ? ' ' . $stateClass : '')));
        $attrs = (array) ($item['attrs'] ?? []);
        if ($isActive) {
            $attrs['aria-current'] = 'page';
        }

        $icon = adminUiClass((string) ($item['icon'] ?? ''));
        $html .= '<a class="' . adminUiEscape($classes) . '" href="' . adminUiEscape($href) . '"' . adminUiAttrs($attrs) . '>';
        $leadingClass = trim(adminUiClass($defaultLeadingClass . ' ' . (string) ($item['leading_class'] ?? '')));
        if ($leadingClass !== '') {
            $html .= '<span class="' . adminUiEscape($leadingClass) . '">';
        }
        if ($icon !== '') {
            $iconClass = trim(adminUiClass($defaultIconClass . ' ' . (string) ($item['icon_class'] ?? '')));
            $iconHtml = '<i class="bi ' . adminUiEscape(trim($icon . ' ' . $iconClass)) . '"></i>';
            $iconWrapClass = trim(adminUiClass($defaultIconWrapClass . ' ' . (string) ($item['icon_wrap_class'] ?? '')));
            $html .= $iconWrapClass !== ''
                ? '<span class="' . adminUiEscape($iconWrapClass) . '">' . $iconHtml . '</span>'
                : $iconHtml;
        }

        $label = (string) ($item['label'] ?? '');
        $description = trim((string) ($item['description'] ?? ''));
        if ($description !== '') {
            $copyClass = trim(adminUiClass($defaultCopyClass . ' ' . (string) ($item['copy_class'] ?? '')));
            $titleClass = trim(adminUiClass($defaultTitleClass . ' ' . (string) ($item['title_class'] ?? '')));
            $descriptionClass = trim(adminUiClass($defaultDescriptionClass . ' ' . (string) ($item['description_class'] ?? '')));
            $html .= '<span class="' . adminUiEscape($copyClass) . '">';
            $html .= '<strong class="' . adminUiEscape($titleClass) . '">' . adminUiEscape($label) . '</strong>';
            $html .= '<small class="' . adminUiEscape($descriptionClass) . '">' . adminUiEscape($description) . '</small>';
            $html .= '</span>';
        } else {
            $html .= '<span>' . adminUiEscape($label) . '</span>';
        }
        if ($leadingClass !== '') {
            $html .= '</span>';
        }

        if (($item['badge'] ?? '') !== '') {
            $badgeClass = trim(adminUiClass($defaultBadgeClass . ' ' . (string) ($item['badge_class'] ?? '')));
            $html .= adminRenderBadge((string) $item['badge'], [
                'tone' => (string) ($item['badge_tone'] ?? 'primary'),
                'class' => $badgeClass,
            ]);
        }
        $html .= '</a>';
    }
    $html .= '</nav>';

    return $html;
}

/**
 * @param array<int,array{href?:string,label:string,icon?:string,class?:string,attrs?:array<string,string|int|bool|null>}> $actions
 * @param array{class?:string,attrs?:array<string,string|int|bool|null>,tag?:string,title_tag?:string,title_class?:string,description_class?:string,actions_html?:string} $options
 */
function adminRenderPageHero(string $icon, string $kicker, string $title, string $description = '', array $actions = [], array $options = []): string
{
    $tag = (string) ($options['tag'] ?? 'section');
    if (!in_array($tag, ['section', 'div', 'header'], true)) {
        $tag = 'section';
    }
    $titleTag = (string) ($options['title_tag'] ?? 'h2');
    if (!in_array($titleTag, ['h1', 'h2', 'h3'], true)) {
        $titleTag = 'h2';
    }
    $class = trim('ui-admin-page-hero admin-ui-page-hero ' . adminUiClass((string) ($options['class'] ?? '')));
    $titleClass = adminUiClass((string) ($options['title_class'] ?? ''));
    $titleAttrs = $titleClass !== '' ? ' class="' . adminUiEscape($titleClass) . '"' : '';
    $descriptionClass = adminUiClass((string) ($options['description_class'] ?? ''));
    $descriptionAttrs = $descriptionClass !== '' ? ' class="' . adminUiEscape($descriptionClass) . '"' : '';
    $attrs = adminUiAttrs((array) ($options['attrs'] ?? []));

    $html = '<' . $tag . ' class="' . adminUiEscape($class) . '"' . $attrs . '>';
    $html .= '<div class="ui-admin-page-hero-text admin-ui-page-hero__text">';
    if ($kicker !== '') {
        $html .= '<span class="ui-admin-kicker admin-ui-page-kicker"><i class="bi ' . adminUiEscape(adminUiClass($icon)) . '"></i> ' . adminUiEscape($kicker) . '</span>';
    }
    $html .= '<' . $titleTag . $titleAttrs . '>' . adminUiEscape($title) . '</' . $titleTag . '>';
    if ($description !== '') {
        $html .= '<p' . $descriptionAttrs . '>' . adminUiEscape($description) . '</p>';
    }
    $html .= '</div>';

    $actionsHtml = trim((string) ($options['actions_html'] ?? ''));
    if ($actions !== [] || $actionsHtml !== '') {
        $html .= '<div class="ui-admin-page-hero-actions admin-ui-page-hero__actions admin-ui-action-row">';
        foreach ($actions as $action) {
            $label = (string) ($action['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $href = trim((string) ($action['href'] ?? ''));
            $buttonClass = trim('ui-admin-btn ui-admin-btn-hero ' . adminUiClass((string) ($action['class'] ?? '')));
            $iconHtml = trim((string) ($action['icon'] ?? '')) !== ''
                ? '<i class="bi ' . adminUiEscape(adminUiClass((string) $action['icon'])) . '"></i> '
                : '';
            $tagName = $href !== '' ? 'a' : 'button';
            $actionAttrs = adminUiAttrs((array) ($action['attrs'] ?? []));
            $html .= '<' . $tagName
                . ($href !== '' ? ' href="' . adminUiEscape($href) . '"' : ' type="button"')
                . ' class="' . adminUiEscape($buttonClass) . '"'
                . $actionAttrs . '>'
                . $iconHtml . adminUiEscape($label)
                . '</' . $tagName . '>';
        }
        $html .= $actionsHtml;
        $html .= '</div>';
    }

    $html .= '</' . $tag . '>';
    return $html;
}

/**
 * @param array<int,array{tone?:string,icon?:string,label:string,value:mixed,class?:string,href?:string,attrs?:array<string,string|int|bool|null>,change_label?:string,change_icon?:string,change_class?:string}> $cards
 * @param array{class?:string,aria_label?:string,base_class?:string,card_base_class?:string,card_class?:string} $options
 */
function adminRenderStatCards(array $cards, array $options = []): string
{
    $baseClass = (string) ($options['base_class'] ?? 'admin-stat-grid ui-grid admin-ui-stat-grid');
    $gridClass = trim(adminUiClass($baseClass . ' ' . (string) ($options['class'] ?? '')));
    $attrs = ' class="' . adminUiEscape($gridClass) . '"';
    if (!empty($options['aria_label'])) {
        $attrs .= ' aria-label="' . adminUiEscape((string) $options['aria_label']) . '"';
    }

    $cardBaseClass = (string) ($options['card_base_class'] ?? 'admin-stat-card ui-card admin-ui-stat-card');
    $defaultCardClass = (string) ($options['card_class'] ?? '');
    $html = '<div' . $attrs . '>';
    foreach ($cards as $card) {
        $label = (string) ($card['label'] ?? '');
        if ($label === '') {
            continue;
        }
        $tone = adminUiClass((string) ($card['tone'] ?? 'info')) ?: 'info';
        $icon = adminUiClass((string) ($card['icon'] ?? 'bi-bar-chart')) ?: 'bi-bar-chart';
        $class = trim(adminUiClass($cardBaseClass . ' stat-' . $tone . ' ' . $defaultCardClass . ' ' . (string) ($card['class'] ?? '')));
        $href = trim((string) ($card['href'] ?? ''));
        $tag = $href !== '' ? 'a' : 'div';
        $itemAttrs = adminUiAttrs((array) ($card['attrs'] ?? []));
        $html .= '<' . $tag . ($href !== '' ? ' href="' . adminUiEscape($href) . '"' : '') . ' class="' . adminUiEscape($class) . '"' . $itemAttrs . '>';
        $html .= '<div class="stat-icon"><i class="bi ' . adminUiEscape($icon) . '"></i></div>';
        $html .= '<div class="stat-content"><span class="stat-label">' . adminUiEscape($label) . '</span><span class="stat-value">' . adminUiEscape($card['value'] ?? '') . '</span>';
        $changeLabel = (string) ($card['change_label'] ?? '');
        if ($changeLabel !== '') {
            $changeClass = adminUiClass((string) ($card['change_class'] ?? 'neutral')) ?: 'neutral';
            $changeIcon = adminUiClass((string) ($card['change_icon'] ?? '')) ?: '';
            $html .= '<span class="stat-change ' . adminUiEscape($changeClass) . '">';
            if ($changeIcon !== '') {
                $html .= '<i class="bi ' . adminUiEscape($changeIcon) . '"></i> ';
            }
            $html .= adminUiEscape($changeLabel) . '</span>';
        }
        $html .= '</div>';
        $html .= '</' . $tag . '>';
    }
    $html .= '</div>';

    return $html;
}

function adminRenderFilterToolbarOpen(string $bodyClass = '', string $wrapperClass = '', array $attrs = []): string
{
    $wrapper = trim(adminUiClass('admin-card ui-panel admin-filter-toolbar ' . $wrapperClass));
    $class = trim(adminUiClass('card-body ui-admin-card-compact ui-panel__body ui-card admin-filter-toolbar__body ' . $bodyClass));
    return '<div class="' . adminUiEscape($wrapper) . '"' . adminUiAttrs($attrs) . '><div class="' . adminUiEscape($class) . '">';
}

function adminRenderFilterToolbarClose(): string
{
    return '</div></div>';
}

/**
 * @param array{icon?:string,tone?:string,title:string,description:string,pro?:bool,class?:string,attrs?:array<string,string|int|bool|null>,meta?:array<int,array{icon?:string,label:string}>,actions?:array<int,array{href?:string,label:string,icon?:string,class?:string,attrs?:array<string,string|int|bool|null>}>} $options
 */
function adminRenderEmptyState(array $options): string
{
    $icon = adminUiClass((string) ($options['icon'] ?? 'bi-inbox')) ?: 'bi-inbox';
    $tone = adminUiClass((string) ($options['tone'] ?? 'info')) ?: 'info';
    $class = trim(adminUiClass('ui-admin-empty ui-empty admin-ui-empty ' . (!empty($options['pro']) ? 'ui-admin-empty-pro ' : '') . (string) ($options['class'] ?? '')));
    $html = '<div class="' . adminUiEscape($class) . '"' . adminUiAttrs((array) ($options['attrs'] ?? [])) . '>';
    $html .= '<div class="ui-admin-empty-icon tone-' . adminUiEscape($tone) . ' ui-empty"><i class="bi ' . adminUiEscape($icon) . '"></i></div>';
    $html .= '<h3 class="ui-admin-empty-title ui-empty">' . adminUiEscape((string) ($options['title'] ?? 'Kayıt bulunamadı')) . '</h3>';
    $html .= '<p class="ui-admin-empty-desc ui-empty">' . adminUiEscape((string) ($options['description'] ?? 'Filtreye uyan kayıt bulunamadı.')) . '</p>';

    $meta = (array) ($options['meta'] ?? []);
    if ($meta !== []) {
        $html .= '<div class="ui-admin-empty-meta" aria-label="Durum bilgisi">';
        foreach ($meta as $item) {
            $label = (string) ($item['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $metaIcon = trim((string) ($item['icon'] ?? '')) !== ''
                ? '<i class="bi ' . adminUiEscape(adminUiClass((string) $item['icon'])) . '"></i> '
                : '';
            $html .= '<span>' . $metaIcon . adminUiEscape($label) . '</span>';
        }
        $html .= '</div>';
    }

    $actions = (array) ($options['actions'] ?? []);
    if ($actions !== []) {
        $html .= '<div class="ui-admin-empty-actions ui-empty">';
        foreach ($actions as $action) {
            $label = (string) ($action['label'] ?? '');
            if ($label === '') {
                continue;
            }
            $href = trim((string) ($action['href'] ?? ''));
            $tag = $href !== '' ? 'a' : 'button';
            $buttonClass = trim(adminUiClass('ui-admin-btn ui-admin-btn-outline ' . (string) ($action['class'] ?? '')));
            $iconHtml = trim((string) ($action['icon'] ?? '')) !== ''
                ? '<i class="bi ' . adminUiEscape(adminUiClass((string) $action['icon'])) . '"></i> '
                : '';
            $html .= '<' . $tag
                . ($href !== '' ? ' href="' . adminUiEscape($href) . '"' : ' type="button"')
                . ' class="' . adminUiEscape($buttonClass) . '"'
                . adminUiAttrs((array) ($action['attrs'] ?? [])) . '>'
                . $iconHtml . adminUiEscape($label)
                . '</' . $tag . '>';
        }
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

function adminRenderBulkActionBarOpen(string $class = '', array $attrs = []): string
{
    $barClass = trim(adminUiClass('admin-bulk-action-bar ui-panel ' . $class));
    return '<div class="' . adminUiEscape($barClass) . '"' . adminUiAttrs($attrs) . '>';
}

function adminRenderBulkActionBarClose(): string
{
    return '</div>';
}

/**
 * @param array{message:string,title?:string,ok?:string,cancel?:string,tone?:string,kind?:string,icon?:string} $options
 */
function adminConfirmAttrs(array $options): string
{
    $map = [
        'message' => 'data-admin-confirm',
        'title' => 'data-admin-confirm-title',
        'ok' => 'data-admin-confirm-ok',
        'cancel' => 'data-admin-confirm-cancel',
        'tone' => 'data-admin-confirm-tone',
        'kind' => 'data-admin-confirm-kind',
        'icon' => 'data-admin-confirm-icon',
    ];
    $attrs = [];
    foreach ($map as $key => $attrName) {
        if (array_key_exists($key, $options) && (string) $options[$key] !== '') {
            $attrs[$attrName] = (string) $options[$key];
        }
    }

    return adminUiAttrs($attrs);
}

function adminLogUiEscape(mixed $value): string
{
    return adminUiEscape($value);
}

function adminLogUiClass(string $value): string
{
    return adminUiClass($value);
}

/**
 * Render the shared hero used by the admin log screens.
 *
 * @param array<int,array{href?:string,label:string,icon?:string,class?:string,attrs?:array<string,string|int|bool|null>}> $actions
 */
function adminRenderLogPageHero(string $icon, string $kicker, string $title, string $description, array $actions = []): string
{
    return adminRenderPageHero($icon, $kicker, $title, $description, $actions, ['class' => 'logs-page-hero']);
}

/**
 * Render shared statistic cards for admin log pages.
 *
 * @param array<int,array{tone?:string,icon?:string,label:string,value:mixed,class?:string,attrs?:array<string,string|int|bool|null>}> $cards
 * @param array{class?:string,aria_label?:string} $options
 */
function adminRenderLogStatCards(array $cards, array $options = []): string
{
    $options['base_class'] = 'admin-stat-grid logs-summary logs-stat-grid ui-grid admin-ui-stat-grid';
    $options['card_base_class'] = 'admin-stat-card logs-stat ui-card admin-ui-stat-card';
    return adminRenderStatCards($cards, $options);
}

function adminRenderLogToolbarOpen(string $bodyClass = '', string $wrapperClass = ''): string
{
    return adminRenderFilterToolbarOpen(trim('logs-toolbar-shell ' . $bodyClass), trim('logs-toolbar-card logs-filter-toolbar ' . $wrapperClass));
}

function adminRenderLogToolbarClose(): string
{
    return adminRenderFilterToolbarClose();
}

/**
 * @param array{icon?:string,tone?:string,title:string,description:string,pro?:bool,class?:string,attrs?:array<string,string|int|bool|null>,actions?:array<int,array{href?:string,label:string,icon?:string,class?:string}>} $options
 */
function adminRenderLogEmptyState(array $options): string
{
    $options['class'] = trim('admin-log-empty ' . (string) ($options['class'] ?? ''));
    return adminRenderEmptyState($options);
}

function adminRenderLogPagination(int $totalPages, int $currentPage, callable $urlForPage, array $options = []): string
{
    $options['wrapper_class'] = trim('logs-pagination-wrapper ' . (string) ($options['wrapper_class'] ?? ''));
    return adminRenderPagination($totalPages, $currentPage, $urlForPage, $options);
}

/**
 * @param array{
 *     tag?:string,
 *     class?:string,
 *     header_class?:string,
 *     body_class?:string,
 *     attrs?:array<string,string|int|bool|null>,
 *     icon?:string,
 *     title:string,
 *     count?:mixed,
 *     count_label?:string,
 *     count_text?:string,
 *     actions_html?:string
 * } $options
 */
function adminRenderLogListPanelOpen(array $options): string
{
    $tag = strtolower((string) ($options['tag'] ?? 'section'));
    if (!in_array($tag, ['section', 'div', 'article'], true)) {
        $tag = 'section';
    }

    $panelClass = trim(adminUiClass('admin-card logs-list-card ui-panel ' . (string) ($options['class'] ?? '')));
    $headerClass = trim(adminUiClass('card-header logs-list-head ui-admin-card-header-actions ui-panel__head ui-card ' . (string) ($options['header_class'] ?? '')));
    $bodyClass = trim(adminUiClass('card-body ui-admin-card-body-flush ui-panel__body ui-card ' . (string) ($options['body_class'] ?? '')));
    $icon = adminUiClass((string) ($options['icon'] ?? 'bi-journal-text')) ?: 'bi-journal-text';
    $title = (string) ($options['title'] ?? '');
    $countText = (string) ($options['count_text'] ?? '');
    if ($countText === '' && array_key_exists('count', $options)) {
        $countText = (string) $options['count'] . ' ' . (string) ($options['count_label'] ?? 'kayit');
    }
    $actionsHtml = trim((string) ($options['actions_html'] ?? ''));

    $html = '<' . $tag . ' class="' . adminUiEscape($panelClass) . '"' . adminUiAttrs((array) ($options['attrs'] ?? [])) . '>';
    $html .= '<div class="' . adminUiEscape($headerClass) . '">';
    $html .= '<div><h3><i class="bi ' . adminUiEscape($icon) . '"></i> ' . adminUiEscape($title) . '</h3>';
    if ($countText !== '') {
        $html .= '<span>' . adminUiEscape($countText) . '</span>';
    }
    $html .= '</div>';
    if ($actionsHtml !== '') {
        $html .= '<div class="logs-toolbar-actions">' . $actionsHtml . '</div>';
    }
    $html .= '</div><div class="' . adminUiEscape($bodyClass) . '">';

    return $html;
}

function adminRenderLogListPanelClose(string $tag = 'section'): string
{
    $tag = strtolower($tag);
    if (!in_array($tag, ['section', 'div', 'article'], true)) {
        $tag = 'section';
    }

    return '</div></' . $tag . '>';
}

/**
 * @param array{
 *     wrapper_class?:string,
 *     table_class?:string,
 *     wrapper_attrs?:array<string,string|int|bool|null>,
 *     table_attrs?:array<string,string|int|bool|null>
 * } $options
 */
function adminRenderLogTableOpen(array $options = []): string
{
    $wrapperClass = trim(adminUiClass('table-wrapper ui-table-wrap ui-surface admin-log-table-wrap admin-log-standard-wrap ' . (string) ($options['wrapper_class'] ?? '')));
    $tableClass = trim(adminUiClass('admin-table admin-log-table admin-log-standard-table ' . (string) ($options['table_class'] ?? '')));

    return '<div class="' . adminUiEscape($wrapperClass) . '"' . adminUiAttrs((array) ($options['wrapper_attrs'] ?? [])) . '>'
        . '<table class="' . adminUiEscape($tableClass) . '"' . adminUiAttrs((array) ($options['table_attrs'] ?? [])) . '>';
}

function adminRenderLogTableClose(): string
{
    return '</table></div>';
}

function adminRenderLogClearTrigger(array $options = []): string
{
    $label = (string) ($options['label'] ?? 'Temizle');
    $icon = adminUiClass((string) ($options['icon'] ?? 'bi-trash')) ?: 'bi-trash';
    $baseClass = (string) ($options['base_class'] ?? 'ui-admin-btn ui-admin-btn-danger-outline ui-admin-btn-xs');
    $class = trim(adminUiClass($baseClass . ' ' . (string) ($options['class'] ?? '')));
    $attrs = (array) ($options['attrs'] ?? []);
    $attrs = array_merge([
        'type' => 'button',
        'class' => $class,
        'data-clear-logs-open' => true,
    ], $attrs);

    return '<button' . adminUiAttrs($attrs) . '><i class="bi ' . adminUiEscape($icon) . '"></i> ' . adminUiEscape($label) . '</button>';
}

function adminRenderLogClearModal(array $config): void
{
    global $baseUri;

    if (trim((string) ($config['base_uri'] ?? '')) === '') {
        $config['base_uri'] = function_exists('base_uri') ? base_uri() : (string) ($baseUri ?? '');
    }

    $logClearModal = $config;
    include __DIR__ . '/partials/log-clear-modal.php';
}

function adminLogCleanupAudit(?PDO $pdo, string $actionType, string $scope, int $deleted, array $context = []): void
{
    if (!$pdo instanceof PDO || !function_exists('adminAuditLogger')) {
        return;
    }

    $subjectMap = [
        'admin_action_log_cleared' => 'Yönetici işlem günlüğü',
        'activity_logs_cleared' => 'Kullanıcı işlem günlüğü',
        'application_logs_cleared' => 'Uygulama logları',
        'email_logs_cleared' => 'E-posta logları',
        'cron_logs_cleared' => 'Cron logları',
        'rate_limit_records_deleted' => 'İstek sınırı kayıtları',
        'notification_records_deleted' => 'Bildirim geçmişi',
        'system_notifications_deleted' => 'Sistem bildirimleri',
        'system_notifications_cleared' => 'Sistem bildirimleri',
        'events_audit_logs_cleared' => 'Etkinlik audit logları',
    ];
    $scopeMap = [
        'all' => 'Tümü',
        'older_than_30_days' => '30 günden eski kayıtlar',
        'old' => 'Eski kayıtlar',
        'filtered' => 'Aktif filtre',
        'user' => 'Belirli kullanıcı',
        'login' => 'Giriş kilitleri',
        'expired' => 'Süresi dolmuş kayıtlar',
        'single' => 'Tek kayıt',
        'selected' => 'Seçili kayıtlar',
    ];

    $subject = $subjectMap[$actionType] ?? $actionType;
    $scopeLabel = $scopeMap[$scope] ?? $scope;
    $payload = array_merge([
        'scope' => $scope,
        'scope_label' => $scopeLabel,
        'deleted' => max(0, $deleted),
        'actor_id' => adminCurrentUserId(),
    ], $context);

    adminAuditLogger()->logAction(
        $pdo,
        $actionType,
        'logs',
        0,
        $subject . ' temizlendi; kapsam: ' . $scopeLabel . '; silinen: ' . max(0, $deleted),
        [],
        $payload,
        false
    );
}

function adminLogCleanupRespond(bool $ok, string $message, string $redirectUrl, int $statusCode = 200): void
{
    if (adminIsAjaxRequest()) {
        sendJsonResponse($statusCode, $ok, $message, ['ok' => $ok]);
    }

    flash($ok ? 'success' : 'error', $message);
    $redirectUrl = adminSanitizeRedirectUrl($redirectUrl);
    header('Location: ' . ($redirectUrl !== '' ? $redirectUrl : 'index.php'));
    exit;
}

function adminSanitizeRedirectUrl(string $redirectUrl): string
{
    $redirectUrl = trim($redirectUrl);
    if ($redirectUrl === '') {
        return '';
    }

    if (
        preg_match('/[\x00-\x1F\x7F]/', $redirectUrl) === 1
        || preg_match('~^[a-z][a-z0-9+.-]*:~i', $redirectUrl) === 1
        || str_starts_with($redirectUrl, '//')
        || str_contains($redirectUrl, '\\')
    ) {
        return '';
    }

    return $redirectUrl;
}

/**
 * Run an admin log cleanup action with the standard validation, response and audit flow.
 *
 * @param array{
 *     action_type:string,
 *     scope?:string,
 *     allowed_scopes?:array<int,string>,
 *     permission?:string|array<int,string>,
 *     permission_message?:string,
 *     redirect_url:string,
 *     delete:callable(PDO,string):int,
 *     validate?:callable(string):string,
 *     ready?:bool,
 *     ready_message?:string,
 *     source?:string,
 *     context?:array<string,mixed>|callable(string,int):array<string,mixed>,
 *     activity?:bool,
 *     activity_subject?:string,
 *     app_log?:bool,
 *     app_log_channel?:string,
 *     app_log_message?:string,
 *     audit?:bool,
 *     after?:callable(PDO,string,int,array<string,mixed>):void,
 *     require_deleted?:bool,
 *     failure_message?:string,
 *     success_message?:string|callable(int,string):string,
 *     error_prefix?:string
 * } $options
 */
function adminRunLogCleanup(?PDO $pdo, array $options): void
{
    $redirectUrl = (string) ($options['redirect_url'] ?? 'logs.php');
    $respond = static function (bool $ok, string $message, int $statusCode = 200) use ($redirectUrl): void {
        adminLogCleanupRespond($ok, $message, $redirectUrl, $statusCode);
    };

    if (!$pdo instanceof PDO) {
        $respond(false, 'Veritabanı bağlantısı hazır olmadığı için işlem yapılamadı.', 500);
    }

    if (!verify_csrf_token($_POST['_token'] ?? ($_POST['csrf_token'] ?? ''))) {
        $respond(false, 'Güvenlik doğrulaması başarısız.', 403);
    }

    $permission = $options['permission'] ?? 'logs.manage';
    if ($permission !== '' && !adminCurrentUserCan($permission)) {
        $respond(false, (string) ($options['permission_message'] ?? 'Bu işlemi yapmak için gerekli izin hesabınıza tanımlanmamış.'), 403);
    }

    if (array_key_exists('ready', $options) && !$options['ready']) {
        $respond(false, (string) ($options['ready_message'] ?? 'Bu günlük alanı hazır olmadığı için temizleme yapılamadı.'), 422);
    }

    $scope = trim((string) ($options['scope'] ?? ($_POST['scope'] ?? 'all')));
    $allowedScopes = array_values(array_map('strval', (array) ($options['allowed_scopes'] ?? [])));
    if ($allowedScopes !== [] && !in_array($scope, $allowedScopes, true)) {
        $respond(false, 'Geçersiz temizleme kapsamı.', 422);
    }

    if (isset($options['validate']) && is_callable($options['validate'])) {
        $validationError = (string) $options['validate']($scope);
        if ($validationError !== '') {
            $respond(false, $validationError, 422);
        }
    }

    $deleteCallback = $options['delete'] ?? null;
    if (!is_callable($deleteCallback)) {
        $respond(false, 'Temizleme işlemi tanımlı değil.', 500);
    }

    $actionType = (string) ($options['action_type'] ?? '');
    if ($actionType === '') {
        $respond(false, 'Temizleme işlem tipi tanımlı değil.', 500);
    }

    try {
        $deleted = max(0, (int) $deleteCallback($pdo, $scope));
        if (($options['require_deleted'] ?? false) && $deleted <= 0) {
            $message = (string) ($options['failure_message'] ?? 'Silinecek kayıt bulunamadı.');
            $respond(false, str_replace(['{deleted}', '{scope}'], [(string) $deleted, $scope], $message), 422);
        }

        $contextOption = $options['context'] ?? [];
        $context = is_callable($contextOption) ? (array) $contextOption($scope, $deleted) : (array) $contextOption;
        if (!isset($context['source']) && isset($options['source'])) {
            $context['source'] = (string) $options['source'];
        }

        $activityPayload = array_merge([
            'scope' => $scope,
            'deleted' => $deleted,
        ], $context);

        if (($options['activity'] ?? true) && function_exists('logActivity')) {
            logActivity($pdo, $actionType, (string) ($options['activity_subject'] ?? 'logs'), null, $activityPayload);
        }

        if (($options['app_log'] ?? false) && function_exists('appLog')) {
            appLog(
                $pdo,
                'info',
                (string) ($options['app_log_channel'] ?? 'maintenance'),
                (string) ($options['app_log_message'] ?? $actionType),
                $activityPayload
            );
        }

        if (($options['audit'] ?? true)) {
            adminLogCleanupAudit($pdo, $actionType, $scope, $deleted, $context);
        }

        if (isset($options['after']) && is_callable($options['after'])) {
            $options['after']($pdo, $scope, $deleted, $context);
        }

        $successMessage = $options['success_message'] ?? null;
        if (is_callable($successMessage)) {
            $message = (string) $successMessage($deleted, $scope);
        } elseif (is_string($successMessage) && $successMessage !== '') {
            $message = str_replace(['{deleted}', '{scope}'], [(string) $deleted, $scope], $successMessage);
        } else {
            $message = $deleted . ' kayıt temizlendi.';
        }

        $respond(true, $message);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => $options['source'] ?? 'admin_log_cleanup', 'action_type' => $actionType, 'scope' => $scope]);
        }
        $prefix = (string) ($options['error_prefix'] ?? 'Temizleme hatası: ');
        $respond(false, $prefix . safeErrorMessage($e), 500);
    }
}

function adminRenderLogsSubtabs(string $active): void
{
    global $baseUri, $pdo;

    $systemNotificationBadge = '';
    if ($pdo instanceof PDO && function_exists('adminTableExists')) {
        try {
            if (adminTableExists($pdo, 'notifications')) {
                $systemNotificationWhere = function_exists('adminSystemNotificationWhereSql')
                    ? adminSystemNotificationWhereSql($pdo, 'n')
                    : "n.type = 'system'";
                $systemNotificationCountSql = "SELECT COUNT(*) FROM notifications n WHERE {$systemNotificationWhere}";
                if (adminTableExists($pdo, 'notification_reads')) {
                    $systemNotificationCountSql .= ' AND NOT EXISTS (
                        SELECT 1 FROM notification_reads nr
                        WHERE nr.notification_id = n.id
                    )';
                }
                $systemNotificationCount = (int) $pdo->query($systemNotificationCountSql)->fetchColumn();
                if ($systemNotificationCount > 0) {
                    $systemNotificationBadge = $systemNotificationCount > 99 ? '99+' : (string) $systemNotificationCount;
                }
            }
        } catch (Throwable $e) {
            $systemNotificationBadge = '';
        }
    }

    $items = [
        'activity' => [
            'label' => 'Yönetici İşlem Günlüğü',
            'href' => '/admin/logs.php',
            'icon' => 'bi-journal-text',
            'permission' => 'logs.view',
        ],
        'action' => [
            'label' => 'Kullanıcı İşlem Günlüğü',
            'href' => '/admin/action-log.php',
            'icon' => 'bi-clock-history',
            'permission' => 'logs.view',
        ],
        'application' => [
            'label' => 'Uygulama Logları',
            'href' => '/admin/application-logs.php',
            'icon' => 'bi-journal-code',
            'permission' => 'logs.view',
        ],
        'email' => [
            'label' => 'E-posta Logları',
            'href' => '/admin/email-logs.php',
            'icon' => 'bi-envelope-paper',
            'permission' => 'logs.view',
        ],
        'cron' => [
            'label' => 'Cron Logları',
            'href' => '/admin/logs.php?view=cron',
            'icon' => 'bi-card-list',
            'permission' => 'logs.view',
        ],
        'system_notifications' => [
            'label' => 'Sistem Bildirimleri',
            'href' => '/admin/logs.php?view=system_notifications',
            'icon' => 'bi-cpu',
            'permission' => 'logs.view',
            'badge' => $systemNotificationBadge,
        ],
        'rate_limits' => [
            'label' => 'Rate Limit İzleme',
            'href' => '/admin/rate-limits.php',
            'icon' => 'bi-speedometer2',
            'permission' => 'rate_limits.view',
        ],
    ];

    echo adminRenderTabBar($items, $active, [
        'class' => 'logs-subtabs',
        'link_class' => 'logs-subtab-link',
        'aria_label' => 'Günlükler alt sekmeleri',
        'base_uri' => (string) ($baseUri ?? ''),
        'badge_class' => 'logs-subtab-badge',
    ]);
}

function adminSystemNotificationWhereSql(PDO $pdo, string $alias = 'n'): string
{
    $alias = trim($alias);
    $prefix = '';
    if ($alias !== '') {
        $prefix = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias) === 1 ? $alias . '.' : 'n.';
    }

    try {
        if (function_exists('adminColumnExists') && adminColumnExists($pdo, 'notifications', 'is_admin_loggable')) {
            return '(' . $prefix . 'is_admin_loggable = 1)';
        }
    } catch (Throwable $e) {
        return '(' . $prefix . "type = 'system')";
    }

    $clauses = [$prefix . "type = 'system'"];

    $systemEventKeys = [
        'topic_approved',
        'topic_rejected',
        'topic_revision_requested',
        'comment_approved',
        'comment_edited_by_staff',
        'user_banned',
        'user_unbanned',
        'user_restricted',
        'user_restriction_removed',
        'user_group_changed',
        'ban_appeal_created',
        'ban_appeal_message_added',
        'ban_appeal_updated',
        'topic_report_status_updated',
        'user_report_status_updated',
    ];
    $systemEntityTypes = ['ban_appeal'];

    try {
        if (function_exists('adminColumnExists') && adminColumnExists($pdo, 'notifications', 'event_key')) {
            $eventList = implode(', ', array_map(static fn (string $eventKey): string => $pdo->quote($eventKey), $systemEventKeys));
            $clauses[] = $prefix . "event_key IN ({$eventList})";
        }

        if (function_exists('adminColumnExists') && adminColumnExists($pdo, 'notifications', 'entity_type')) {
            $entityList = implode(', ', array_map(static fn (string $entityType): string => $pdo->quote($entityType), $systemEntityTypes));
            $clauses[] = $prefix . "entity_type IN ({$entityList})";
        }
    } catch (Throwable $e) {
        return '(' . $prefix . "type = 'system')";
    }

    return '(' . implode(' OR ', $clauses) . ')';
}

function adminSettingDefinitions(): array
{
    // Performans (#20): Tanımlar saf sabit bir dizi olduğundan, her çağrıda ~1000
    // satırlık dizinin yeniden inşa edilmesini önlemek için istek içi memoizasyon.
    // adminSettingValue() gibi sık çağrılan yollar artık tekrar tekrar inşa etmez.
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $definitions = [
        // -- Genel ----------------------------------------------
        'items_per_page'         => ['label' => 'Sayfa Başına İçerik',   'type' => 'number', 'default' => '20',                                         'section' => 'general'],
        'site_language'          => ['label' => 'Site Dili',             'type' => 'select', 'default' => 'tr', 'section' => 'general', 'options' => ['tr' => 'Türkçe']],
        'timezone'               => ['label' => 'Saat Dilimi',           'type' => 'select', 'default' => 'Europe/Istanbul', 'section' => 'general', 'options' => ['Europe/Istanbul' => 'Europe/Istanbul (UTC+3)', 'Europe/London' => 'Europe/London (UTC+0)', 'America/New_York' => 'America/New_York (UTC-5)', 'Asia/Tokyo' => 'Asia/Tokyo (UTC+9)', 'UTC' => 'UTC']],
        'date_format'            => ['label' => 'Tarih Formatı',         'type' => 'select', 'default' => 'd.m.Y', 'section' => 'general', 'options' => ['d.m.Y' => '31.12.2025', 'Y-m-d' => '2025-12-31', 'd/m/Y' => '31/12/2025', 'M d, Y' => 'Dec 31, 2025']],
        'terms_url'              => ['label' => 'Kullanım Koşulları URL', 'type' => 'string', 'default' => '',                                           'section' => 'general'],
        'privacy_url'            => ['label' => 'Gizlilik Politikası URL','type' => 'string', 'default' => '',                                           'section' => 'general'],
        'footer_text'            => ['label' => 'Footer Metni',          'type' => 'text',   'default' => '',                                            'section' => 'general'],
        'site_name'              => ['label' => 'Site Adı',              'type' => 'string', 'default' => 'İçerik Topic',                              'section' => 'general'],
        'site_description'       => ['label' => 'Site Açıklaması',       'type' => 'text',   'default' => 'Topluluk içerikleri ve paylaşım platformu.',     'section' => 'general'],

        // -- SEO ------------------------------------------------
        'default_meta_title'       => ['label' => 'Varsayilan Meta Basligi',      'type' => 'string', 'default' => '',    'section' => 'seo'],
        'meta_title_suffix'        => ['label' => 'Meta Baslik Son Eki',          'type' => 'string', 'default' => '',    'section' => 'seo'],
        'default_meta_description' => ['label' => 'Varsayilan Meta Aciklamasi',   'type' => 'text',   'default' => '',    'section' => 'seo'],
        'meta_description_max_length' => ['label' => 'Meta Aciklama Azami Uzunluk', 'type' => 'number', 'default' => '160', 'section' => 'seo', 'min' => 80, 'max' => 320],
        'allow_indexing'           => ['label' => 'Arama Motoru Indekslemesi',    'type' => 'bool',   'default' => '1',   'section' => 'seo'],
        'canonical_base_url'       => ['label' => 'Canonical Ana URL',            'type' => 'string', 'default' => '',                               'section' => 'seo', 'tooltip' => 'Canonical URL\'ler için kullanılacak temel URL (boş bırakılırsa otomatik tespit edilir)'],
        'canonical_trailing_slash' => ['label' => 'Canonical Sonda Slash Kullan', 'type' => 'bool',   'default' => '0',                                                  'section' => 'seo', 'tooltip' => 'Canonical URL\'lerin sonuna "/" ekler (örn: /sayfa/ yerine /sayfa)'],
        'og_image'                 => ['label' => 'Open Graph Görsel URL',        'type' => 'string', 'default' => '',                                                   'section' => 'seo', 'tooltip' => 'Sosyal medyada paylaşımlarda kullanılacak varsayılan Open Graph görseli (1200x630px)'],
        'og_type'                  => ['label' => 'Open Graph Türü',              'type' => 'select', 'default' => 'website', 'section' => 'seo', 'options' => ['website' => 'Website', 'article' => 'Article', 'blog' => 'Blog'], 'tooltip' => 'Open Graph içerik türü (website: genel site, article: makale, blog: blog yazısı)'],
        'twitter_card'             => ['label' => 'Twitter Card Türü',            'type' => 'select', 'default' => 'summary_large_image', 'section' => 'seo', 'options' => ['summary' => 'Summary', 'summary_large_image' => 'Summary Large Image'], 'tooltip' => 'Twitter\'da paylaşımlarda kullanılacak kart türü (summary_large_image: büyük görsel önerilir)'],
        'twitter_handle'           => ['label' => 'Twitter Kullanıcı Adı',        'type' => 'string', 'default' => '',                                                   'section' => 'seo', 'tooltip' => 'Sitenizin Twitter kullanıcı adı (@ olmadan, örn: icerik_topic)'],
        'google_analytics_id'      => ['label' => 'Google Analytics ID',          'type' => 'string', 'default' => '',                                                   'section' => 'seo', 'tooltip' => 'Google Analytics izleme kodu (örn: G-XXXXXXXXXX veya UA-XXXXXXXXX-X)'],
        'google_site_verification' => ['label' => 'Google Site Doğrulama Kodu',   'type' => 'string', 'default' => '',                                                   'section' => 'seo', 'tooltip' => 'Google Search Console site doğrulama meta tag içeriği'],
        'custom_head_code'         => ['label' => 'Özel Head Kodu (JS/CSS)',      'type' => 'text',   'default' => '',                                                   'section' => 'seo', 'tooltip' => '<head> bölümüne eklenecek özel HTML/JS/CSS kodu (tracking scriptleri, meta taglar vb.)'],
        'sitemap_enabled'          => ['label' => 'Sitemap Aktif',                'type' => 'bool',   'default' => '1',                                                  'section' => 'seo', 'tooltip' => 'XML sitemap oluşturma ve sunma özelliğini aktif eder'],
        'sitemap_max_urls'         => ['label' => 'Sitemap Maks. URL Sayısı',   'type' => 'number', 'default' => '1000',                                                'section' => 'seo', 'tooltip' => 'Tek bir sitemap dosyasına dahil edilecek maksimum URL sayısı (Google limiti: 50.000). Bu sayı aşılırsa otomatik olarak topic-sitemap-2.xml, topic-sitemap-3.xml gibi yeni sitemaplar oluşturulur.'],
        'sitemap_changefreq'       => ['label' => 'Sitemap Değişim Sıklığı',    'type' => 'select', 'default' => 'weekly', 'section' => 'seo', 'options' => ['always' => 'Her zaman', 'hourly' => 'Saatlik', 'daily' => 'Günlük', 'weekly' => 'Haftalık', 'monthly' => 'Aylık', 'yearly' => 'Yıllık', 'never' => 'Asla'], 'tooltip' => 'Arama motorlarına içeriğin ne sıklıkla değiştiğini bildirir (tavsiye niteliğinde)'],
        'sitemap_priority_home'    => ['label' => 'Sitemap Ana Sayfa Önceliği', 'type' => 'select', 'default' => '1.0', 'section' => 'seo', 'options' => ['1.0' => '1.0', '0.9' => '0.9', '0.8' => '0.8', '0.7' => '0.7', '0.5' => '0.5'], 'tooltip' => 'Anasayfanın sitedeki göreli önceliği (0.0-1.0 arası, 1.0 en yüksek)'],
        'sitemap_priority_topics'  => ['label' => 'Sitemap Konu Önceliği',      'type' => 'select', 'default' => '0.6', 'section' => 'seo', 'options' => ['0.9' => '0.9', '0.8' => '0.8', '0.7' => '0.7', '0.6' => '0.6', '0.5' => '0.5', '0.4' => '0.4'], 'tooltip' => 'Konu sayfalarının sitedeki göreli önceliği (0.0-1.0 arası)'],
        'sitemap_priority_categories' => ['label' => 'Sitemap Kategori Önceliği', 'type' => 'select', 'default' => '0.7', 'section' => 'seo', 'options' => ['0.9' => '0.9', '0.8' => '0.8', '0.7' => '0.7', '0.6' => '0.6', '0.5' => '0.5'], 'tooltip' => 'Kategori sayfalarının sitedeki göreli önceliği (0.0-1.0 arası)'],
        'sitemap_include_categories' => ['label' => 'Sitemap Kategorileri Dahil Et', 'type' => 'bool', 'default' => '1',                                                'section' => 'seo', 'tooltip' => 'Kategori sayfalarını XML sitemap\'e dahil eder'],
        'image_sitemap_enabled'    => ['label' => 'Image Sitemap Aktif',        'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Görseller için ayrı XML sitemap oluşturur (Google Images için önemli)'],
        'image_sitemap_max_images' => ['label' => 'Konu Başına Maks. Görsel',   'type' => 'number', 'default' => '20',                                                   'section' => 'seo', 'tooltip' => 'Bir konu sayfasından image sitemap\'e dahil edilecek maksimum görsel sayısı'],
        'image_sitemap_hero'       => ['label' => 'Üst Resmi Dahil Et',         'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Konu kapak görsellerini (hero image) image sitemap\'e dahil eder'],
        'image_sitemap_media'      => ['label' => 'Medya Dosyalarını Dahil Et', 'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Medya galerisi görsellerini image sitemap\'e dahil eder'],
        'image_sitemap_inline'     => ['label' => 'İçerik Görsellerini Dahil Et', 'type' => 'bool', 'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'İçerik metni içindeki görselleri image sitemap\'e dahil eder'],
        'robots_enabled'           => ['label' => 'Robots.txt Aktif',           'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Dinamik robots.txt dosyası oluşturma ve sunma özelliğini aktif eder'],
        'robots_disallow_admin'    => ['label' => 'Robots: /admin/ Engelle',    'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Admin panelini arama motorlarından gizler (güvenlik için önerilir)'],
        'robots_disallow_includes' => ['label' => 'Robots: /includes/ Engelle', 'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'PHP include dosyalarını arama motorlarından gizler (önerilir)'],
        'robots_disallow_uploads'  => ['label' => 'Robots: /uploads/ Engelle',  'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Yükleme klasörünü arama motorlarından gizler; image sitemap içinden de dışlanır'],
        'robots_disallow_database' => ['label' => 'Robots: /database/ Engelle', 'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Veritabanı klasörünü arama motorlarından gizler (güvenlik için önerilir)'],
        'robots_crawl_delay'       => ['label' => 'Robots Crawl Delay (sn)',    'type' => 'number', 'default' => '0',                                                    'section' => 'seo', 'tooltip' => 'Botların istekler arasında beklemesi gereken süre (0=sınırsız, sunucu yükünü azaltır)'],
        'robots_custom_rules'      => ['label' => 'Robots Özel Kurallar',       'type' => 'text',   'default' => '',                                                     'section' => 'seo', 'tooltip' => 'Robots.txt\'e eklenecek özel kurallar (her satır bir kural)'],
        'robots_noindex_profiles'  => ['label' => 'Profil Sayfalarına Noindex', 'type' => 'bool',   'default' => '0',                                                    'section' => 'seo', 'tooltip' => 'Tüm profil sayfalarına noindex ekler (genellikle kapalı tutulur)'],
        'structured_data'          => ['label' => 'Yapısal Veri (JSON-LD)',       'type' => 'bool',   'default' => '1',                                                  'section' => 'seo', 'tooltip' => 'Schema.org yapısal veri (JSON-LD) ekleme özelliğini aktif eder (rich snippets için önemli)'],
        'schema_site_search'       => ['label' => 'Site Search Schema',         'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Google\'da site içi arama kutusu gösterilmesini sağlar (SearchAction schema)'],
        'schema_breadcrumbs'       => ['label' => 'Breadcrumb Schema',          'type' => 'bool',   'default' => '1',                                                    'section' => 'seo', 'tooltip' => 'Breadcrumb yapısal verisi ekler (Google arama sonuçlarında breadcrumb gösterir)'],

        // Meta Tags Settings
        'seo_public_page_presets_json' => [
            'label' => 'Public Sayfa SEO Presetleri JSON',
            'type' => 'text',
            'default' => '',
            'section' => 'seo',
            'tooltip' => 'Public sayfa presetleri yapılandırılmış arayüzden otomatik oluşturulur.'
        ],
        // Structured Data Settings
        'structured_data_category' => [
            'label' => 'Kategori Structured Data',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Kategori sayfalarında BreadcrumbList ve CollectionPage schema.org yapısal verisi ekler'
        ],
        'structured_data_profile' => [
            'label' => 'Profil Structured Data',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Profil sayfalarında Person schema.org yapısal verisi ve etkileşim istatistikleri ekler'
        ],
        'schema_organization_name' => [
            'label' => 'Organization Name',
            'type' => 'string',
            'default' => 'İçerik Topic',
            'section' => 'seo',
            'tooltip' => 'Schema.org Organization yapısal verisinde kullanılacak organizasyon adı'
        ],
        'schema_organization_logo' => [
            'label' => 'Organization Logo URL',
            'type' => 'string',
            'default' => '',
            'section' => 'seo',
            'tooltip' => 'Schema.org Organization yapısal verisinde kullanılacak logo URL (tam URL gerekli)'
        ],

        // Pagination SEO Settings
        'pagination_strategy' => [
            'label' => 'Pagination Stratejisi',
            'type' => 'select',
            'options' => [
                'full' => 'Tam (Canonical + Prev/Next)',
                'canonical-only' => 'Sadece Canonical',
                'noindex' => 'Sayfa 2+ Noindex'
            ],
            'default' => 'full',
            'section' => 'seo',
            'tooltip' => 'Tam: Tüm sayfalar canonical + rel prev/next (Bing için önerilir). Canonical-only: Sadece canonical tag. Noindex: 2. sayfadan sonrası noindex'
        ],
        'pagination_max_pages_index' => [
            'label' => 'Maksimum İndexlenecek Sayfa',
            'type' => 'number',
            'default' => '50',
            'section' => 'seo',
            'tooltip' => 'Bu sayıdan sonraki sayfalara otomatik noindex eklenir (crawl bütçesini korur)'
        ],

        // Image SEO Settings
        'image_alt_auto_generate' => [
            'label' => 'Otomatik Alt Text',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Görseller için otomatik olarak SEO dostu alt text oluşturur'
        ],
        'image_alt_template' => [
            'label' => 'Alt Text Şablonu',
            'type' => 'text',
            'default' => '{{title}} - {{category}} modu',
            'section' => 'seo',
            'tooltip' => 'Konu kartları için alt text şablonu. Değişkenler: {{title}}, {{category}}, {{username}}, {{context}}'
        ],
        'image_alt_fallback' => [
            'label' => 'Alt Text Fallback',
            'type' => 'string',
            'default' => 'İçerik görseli',
            'section' => 'seo',
            'tooltip' => 'Şablon uygulanamadığında kullanılacak varsayılan alt text'
        ],
        'image_alt_min_length' => [
            'label' => 'Minimum Alt Text Uzunluğu',
            'type' => 'number',
            'default' => '10',
            'section' => 'seo',
            'tooltip' => 'Alt text minimum karakter sayısı. Daha kısa olanlar fallback ile değiştirilir.'
        ],
        'image_title_auto_generate' => [
            'label' => 'Otomatik Title Text',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Görseller için otomatik olarak SEO dostu title text oluşturur'
        ],
        'image_title_template' => [
            'label' => 'Title Text Şablonu',
            'type' => 'text',
            'default' => '{{title}} - {{category}} modu',
            'section' => 'seo',
            'tooltip' => 'Görseller için title text şablonu. Değişkenler: {{title}}, {{category}}, {{username}}, {{context}}'
        ],
        'image_title_fallback' => [
            'label' => 'Title Text Fallback',
            'type' => 'string',
            'default' => 'İçerik görseli',
            'section' => 'seo',
            'tooltip' => 'Şablon uygulanamadığında kullanılacak varsayılan title text'
        ],
        'image_title_min_length' => [
            'label' => 'Minimum Title Text Uzunluğu',
            'type' => 'number',
            'default' => '10',
            'section' => 'seo',
            'tooltip' => 'Title text minimum karakter sayısı. Daha kısa olanlar fallback ile değiştirilir.'
        ],

        // Sitemap Settings
        'sitemap_route_enabled' => [
            'label' => 'Sitemap Routing Aktif',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Sitemap dosyalarını route.php üzerinden dinamik olarak sunar (/sitemap.xml, /topic-sitemap.xml, /image-sitemap.xml)'
        ],
        'sitemap_cache_duration' => [
            'label' => 'Sitemap Cache Süresi (saniye)',
            'type' => 'number',
            'default' => '3600',
            'section' => 'seo',
            'tooltip' => 'Sitemap dosyalarının önbellekte tutulma süresi (3600 = 1 saat). Performans için önerilir.'
        ],

        // Index/Noindex Settings
        'index_homepage' => [
            'label' => 'Anasayfa Indexlensin',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Anasayfanın arama motorları tarafından indekslenmesine izin verir'
        ],
        'index_categories' => [
            'label' => 'Kategori Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Kategori sayfalarının arama motorları tarafından indekslenmesine izin verir'
        ],
        'index_profiles' => [
            'label' => 'Profil Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Kullanıcı profil sayfalarının arama motorları tarafından indekslenmesine izin verir'
        ],
        'index_topics' => [
            'label' => 'Konu Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Konu detay sayfalarının arama motorları tarafından indekslenmesine izin verir'
        ],
        'index_search_results' => [
            'label' => 'Arama Sonuclari Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Arama sonuç sayfalarının indekslenmesi (genellikle kapalı tutulur, duplicate content önler)'
        ],
        'index_archive_pages' => [
            'label' => 'Arsiv Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Genel arşiv sayfalarının indekslenmesi (genellikle kapalı, duplicate content riski)'
        ],
        'index_empty_categories' => [
            'label' => 'Bos Kategori Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Kapalı olduğunda içeriği olmayan kategori sayfalarına noindex uygulanır'
        ],
        'index_draft_topics' => [
            'label' => 'Taslak Konular Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Kapalı olduğunda taslak konulara noindex uygulanır'
        ],
        'sitemap_exclude_drafts' => [
            'label' => 'Taslakları Sitemap\'ten Çıkar',
            'type' => 'bool',
            'default' => '1',
            'section' => 'seo',
            'tooltip' => 'Taslak durumundaki konuları sitemap.xml içeriklerinden hariç tutar'
        ],
        'index_paginated_pages' => [
            'label' => 'Sayfalama Sayfalari Indexlensin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'seo',
            'tooltip' => 'Kapalıysa 2. ve sonraki sayfalara otomatik olarak noindex eklenir. (Crawl bütçesi için önerilir)'
        ],

        // -- Görünüm --------------------------------------------
        'accent_color'           => ['label' => 'Vurgu Rengi',            'type' => 'color',  'default' => '#8b1538',  'section' => 'appearance'],
        'secondary_color'        => ['label' => 'İkincil Renk',           'type' => 'color',  'default' => '#3b82f6',  'section' => 'appearance'],
        'logo_url'               => ['label' => 'Logo URL',               'type' => 'string', 'default' => '',         'section' => 'appearance'],
        'favicon_url'            => ['label' => 'Favicon URL',            'type' => 'string', 'default' => '',         'section' => 'appearance'],
        'custom_css'             => ['label' => 'Özel CSS Kodları',       'type' => 'text',   'default' => '',         'section' => 'appearance'],
        'dark_mode'              => ['label' => 'Karanlık Mod Desteği',   'type' => 'select', 'default' => 'auto',     'section' => 'appearance', 'options' => ['auto' => 'Otomatik (Sistem)', 'light' => 'Her Zaman Açık', 'dark' => 'Her Zaman Karanlik']],
        'font_family'            => ['label' => 'Yazı Tipi',              'type' => 'select', 'default' => 'roboto',  'section' => 'appearance', 'options' => ['roboto' => 'Roboto']],
        'allow_user_theme_selection' => ['label' => 'Kullanıcı Tema Seçimi', 'type' => 'bool', 'default' => '0',       'section' => 'appearance'],
        'theme_preview_enabled'  => ['label' => 'Tema Önizleme',          'type' => 'bool',   'default' => '1',        'section' => 'appearance'],
        'theme_debug_mode'       => ['label' => 'Tema Debug Modu',        'type' => 'bool',   'default' => '0',        'section' => 'appearance'],
        // Internal/canonical active public theme key. Not rendered in settings UI.
        'theme_active_id'        => ['label' => 'Active Theme (Internal)', 'type' => 'string', 'default' => 'default',  'section' => '__internal'],

        // -- Header Ayarlari -----------------------------------
        'header_sticky'          => ['label' => 'Yapışkan Header',            'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_show_search'     => ['label' => 'Arama Çubuğu Göster',        'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_search_placeholder' => ['label' => 'Arama Placeholder',       'type' => 'string', 'default' => 'İçerik ara...', 'section' => 'lay_header'],
        'header_show_auth_buttons' => ['label' => 'Giriş/Kayıt Butonları',   'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_show_profile_btn'  => ['label' => 'Profil Butonu Göster',    'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_show_admin_btn'  => ['label' => 'Admin Panel Butonu',         'type' => 'bool',   'default' => '1',        'section' => 'lay_header'],
        'header_brand_text'      => ['label' => 'Marka Metni',               'type' => 'string', 'default' => 'İçerik Topic', 'section' => 'lay_header'],
        'header_brand_icon'      => ['label' => 'Marka İkonu (harf)',         'type' => 'string', 'default' => 'M',       'section' => 'lay_header'],
        'header_bg_color'        => ['label' => 'Arka Plan Rengi',           'type' => 'color',  'default' => '#1a2332',  'section' => 'lay_header'],
        'header_text_color'      => ['label' => 'Yazı Rengi',                'type' => 'color',  'default' => '#ffffff',  'section' => 'lay_header'],
        'header_accent_color'    => ['label' => 'Vurgu Rengi',               'type' => 'color',  'default' => '#8b1538',  'section' => 'lay_header'],
        'header_border_color'    => ['label' => 'Alt Çizgi Rengi',           'type' => 'color',  'default' => '#8b1538',  'section' => 'lay_header'],
        'header_custom_css'      => ['label' => 'Header Özel CSS',           'type' => 'text',   'default' => '',         'section' => 'lay_header'],
        'header_topbar_enabled'  => ['label' => 'Üst Bilgi Çubuğu',          'type' => 'bool',   'default' => '0',        'section' => 'lay_header'],
        'header_topbar_text'     => ['label' => 'Üst Çubuk Metni',           'type' => 'string', 'default' => '',         'section' => 'lay_header'],
        'header_topbar_bg'       => ['label' => 'Üst Çubuk Arka Plan',       'type' => 'color',  'default' => '#0f172a',  'section' => 'lay_header'],

        // -- Footer Ayarlari -----------------------------------
        'footer_nav_links'       => ['label' => 'Footer Linkleri (satır başına: Başlık|URL)', 'type' => 'text', 'default' => "Ana sayfa|{base_url}/index.php\nKategoriler|{base_url}/kategoriler\nEtkinlikler|{base_url}/events\nMod Yükle|{base_url}/konu-yukle", 'section' => 'lay_footer'],
        'footer_copyright'  => ['label' => 'Telif Hakkı Metni', 'type' => 'string', 'default' => '&copy; {current_year}. <a href="{base_url}/index.php" class="site-footer-brand-link">{site_name}</a> - Tüm hakları saklıdır.', 'section' => 'lay_footer'],

        // -- Sidebar Ayarlari ----------------------------------
        // Genel Sidebar Ayarları
        'sidebar_enabled'        => ['label' => 'Sidebar Aktif',              'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_position'       => ['label' => 'Sidebar Konumu',            'type' => 'select', 'default' => 'right',    'section' => 'lay_sidebar', 'options' => ['right' => 'Sağ', 'left' => 'Sol']],
        'sidebar_width'          => ['label' => 'Sidebar Genişligi (px)',    'type' => 'number', 'default' => '330',      'section' => 'lay_sidebar'],
        'sidebar_builder_config' => ['label' => 'Sidebar Builder JSON',      'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Anasayfa Sidebar
        'sidebar_home_sticky'    => ['label' => 'Anasayfa: Kayan Sidebar',    'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_home_template'  => ['label' => 'Anasayfa Sidebar Şablonu',  'type' => 'select', 'default' => 'default',  'section' => 'lay_sidebar', 'options' => ['default' => 'Varsayılan', 'minimal' => 'Minimal', 'full' => 'Tam', 'custom' => 'Ozel']],
        'sidebar_home_popular'   => ['label' => 'Anasayfa: Popüler İçerikler', 'type' => 'bool', 'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_home_categories'=> ['label' => 'Anasayfa: Kategori Bulutu', 'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_home_stats'     => ['label' => 'Anasayfa: Site İstatistikleri', 'type' => 'bool', 'default' => '1',      'section' => 'lay_sidebar'],
        'sidebar_home_custom'    => ['label' => 'Anasayfa: Özel Widget',     'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Konu Detay Sidebar
        'sidebar_topic_sticky'   => ['label' => 'Konu Detay: Kayan Sidebar',  'type' => 'bool', 'default' => '1',       'section' => 'lay_sidebar'],
        'sidebar_topic_template' => ['label' => 'Konu Sidebar Şablonu',      'type' => 'select', 'default' => 'default',  'section' => 'lay_sidebar', 'options' => ['default' => 'Varsayılan', 'minimal' => 'Minimal', 'related' => 'Benzer İçerikler', 'custom' => 'Ozel']],
        'sidebar_topic_popular'  => ['label' => 'Konu: Popüler İçerikler',   'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_topic_related'  => ['label' => 'Konu: Benzer İçerikler',    'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_topic_categories'=> ['label' => 'Konu: Kategori Bulutu',    'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_topic_author'   => ['label' => 'Konu: Yazar Bilgisi',       'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_topic_custom'   => ['label' => 'Konu: Özel Widget',         'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Kategori Sidebar
        'sidebar_category_sticky'=> ['label' => 'Kategori: Kayan Sidebar',    'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_category_template' => ['label' => 'Kategori Sidebar Şablonu', 'type' => 'select', 'default' => 'default', 'section' => 'lay_sidebar', 'options' => ['default' => 'Varsayılan', 'minimal' => 'Minimal', 'navigation' => 'Navigasyon', 'custom' => 'Ozel']],
        'sidebar_category_list'  => ['label' => 'Kategori: Kategori Listesi', 'type' => 'bool',  'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_category_popular'=> ['label' => 'Kategori: Popüler İçerikler', 'type' => 'bool', 'default' => '1',       'section' => 'lay_sidebar'],
        'sidebar_category_stats' => ['label' => 'Kategori: Kategori İstatistikleri', 'type' => 'bool', 'default' => '1',   'section' => 'lay_sidebar'],
        'sidebar_category_custom'=> ['label' => 'Kategori: Özel Widget',     'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Arama Sidebar
        'sidebar_search_sticky'  => ['label' => 'Arama: Kayan Sidebar',      'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_search_template'=> ['label' => 'Arama Sidebar Şablonu',     'type' => 'select', 'default' => 'default',  'section' => 'lay_sidebar', 'options' => ['default' => 'Varsayılan', 'minimal' => 'Minimal', 'filters' => 'Filtreler', 'custom' => 'Ozel']],
        'sidebar_search_filters' => ['label' => 'Arama: Filtre Paneli',      'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_search_categories'=> ['label' => 'Arama: Kategori Listesi', 'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_search_popular' => ['label' => 'Arama: Popüler Aramalar',   'type' => 'bool',   'default' => '1',        'section' => 'lay_sidebar'],
        'sidebar_search_custom'  => ['label' => 'Arama: Özel Widget',        'type' => 'text',   'default' => '',         'section' => 'lay_sidebar'],

        // Genel Widget Ayarları
        'sidebar_popular_count'  => ['label' => 'Popüler İçerik Sayısı',     'type' => 'number', 'default' => '6',        'section' => 'lay_sidebar'],
        'sidebar_popular_sort'   => ['label' => 'Popüler Sıralama',          'type' => 'select', 'default' => 'downloads','section' => 'lay_sidebar', 'options' => ['downloads' => 'Indirme', 'views' => 'Görüntülenme', 'date' => 'Tarih']],
        'sidebar_widget_position'=> ['label' => 'Özel Widget Konumu',        'type' => 'select', 'default' => 'bottom',   'section' => 'lay_sidebar', 'options' => ['top' => 'En Üst', 'after-popular' => 'Popülerden Sonra', 'after-categories' => 'Kategorilerden Sonra', 'bottom' => 'En Alt']],

        // -- Menu Ayarlari ------------------------------------
        'menu_items'             => ['label' => 'Üst Menü Öğeleri (satır başına: Başlık|URL|Ikon)', 'type' => 'text', 'default' => "Anasayfa|/index.php|bi-house\nKategoriler|{category_list}|bi-grid\nMod Yükle|{upload_topic}|bi-cloud-arrow-up\nEtkinlikler|{events}|bi-calendar-event\nLiderlik|{leaderboard}|bi-trophy\nİletişim|{contact}|bi-envelope-paper", 'section' => 'lay_menu'],
        'menu_show_categories'   => ['label' => 'Kategorileri Menüye Ekle',  'type' => 'bool',   'default' => '0',        'section' => 'lay_menu'],
        'menu_category_limit'    => ['label' => 'Menüde Maks. Kategori',     'type' => 'number', 'default' => '8',        'section' => 'lay_menu'],
        'menu_cta_enabled'       => ['label' => 'CTA Butonu Aktif',          'type' => 'bool',   'default' => '0',        'section' => 'lay_menu'],
        'menu_cta_text'          => ['label' => 'CTA Buton Metni',           'type' => 'string', 'default' => 'İçerik Yükle', 'section' => 'lay_menu'],
        'menu_cta_url'           => ['label' => 'CTA Buton URL',             'type' => 'string', 'default' => '',         'section' => 'lay_menu'],
        'menu_cta_color'         => ['label' => 'CTA Buton Rengi',           'type' => 'color',  'default' => '#8b1538',  'section' => 'lay_menu'],
        'menu_cta_icon'          => ['label' => 'CTA Buton İkonu',           'type' => 'string', 'default' => 'bi-cloud-arrow-up', 'section' => 'lay_menu'],



        // -- Yorum Sistemi -------------------------------------
        // Genel açma/kapama + onay anahtarları kolay erişim için bu sekmede.
        'allow_comments'            => ['label' => 'Yorumlara Izin Ver',           'type' => 'bool',   'default' => '1', 'section' => 'comments'],
        'comment_approval_required' => ['label' => 'Yorum Onayi Gerekli',         'type' => 'bool',   'default' => '0', 'section' => 'comments'],
        'max_comment_length'        => ['label' => 'Maks. Yorum Uzunlugu',        'type' => 'number', 'default' => '2000', 'section' => 'comments'],
        'comment_nested'            => ['label' => 'Yanit (Nested) Yorumlar',     'type' => 'bool',   'default' => '0', 'section' => 'comments'],
        'comment_max_depth'         => ['label' => 'Maks. Yanit Derinligi',       'type' => 'number', 'default' => '3', 'section' => 'comments'],
        'comment_allow_guest'       => ['label' => 'Misafir Yorum Izni',          'type' => 'bool',   'default' => '0', 'section' => 'comments'],
        'comment_edit_window'       => ['label' => 'Duzenleme Suresi (dk, 0=kapali)', 'type' => 'number', 'default' => '0', 'section' => 'comments'],
        'comment_min_length'        => ['label' => 'Min. Yorum Uzunlugu',         'type' => 'number', 'default' => '1', 'section' => 'comments'],
        'comment_per_page'          => ['label' => 'Sayfa Basina Yorum',          'type' => 'number', 'default' => '50', 'section' => 'comments'],
        'comment_sort_order'        => ['label' => 'Yorum Siralama',              'type' => 'select', 'default' => 'asc', 'section' => 'comments', 'options' => ['asc' => 'Eskiden yeniye', 'desc' => 'Yeniden eskiye']],
        'comment_rate_minutes'      => ['label' => 'Yorum Gönderim Süresi (dakika)', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Yorum gönderim limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'comment_rate_max'          => ['label' => 'Yorum Gönderim Limiti', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde bir kullanıcının en fazla kaç yorum gönderebileceğini belirler.'],
        'comment_rate_admin_bypass' => ['label' => 'Adminleri Yorum Limitinden Muaf Tut', 'type' => 'bool', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Açıksa admin hesapları yorum gönderim limitine takılmaz.'],
        'comment_realtime_poll'     => ['label' => 'Canli Yorum Yoklama (sn, 0=kapali)', 'type' => 'number', 'default' => '15', 'section' => 'comments'],

        // -- Gelişmiş Yorum Özellikleri ----------------------
        'comment_reactions_enabled'  => ['label' => 'Reaksiyon Sistemi Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Kullanıcıların yorumlara emoji reaksiyonları (👍 ❤️ 😂) eklemesine izin verir.'],
        'comment_reactions_types'    => [
            'label' => 'Aktif Reaksiyonlar',
            'type' => 'multicheck',
            'options' => [
                'like' => 'Beğen (👍)',
                'love' => 'Muhteşem (❤️)',
                'laugh' => 'Komik (😂)',
                'wow' => 'İnanılmaz (😮)',
                'sad' => 'Üzgün (😢)',
                'angry' => 'Kızgın (😡)'
            ],
            'default' => 'like,love,laugh,wow,sad,angry',
            'section' => 'comments',
            'tooltip' => 'Aktif etmek istediğiniz reaksiyonları seçin.'
        ],
        'comment_markdown_enabled'   => ['label' => 'Markdown Desteği', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Yorumlarda **kalın**, *italik*, `kod` gibi markdown formatlamaya izin verir.'],
        'comment_mentions_enabled'   => ['label' => 'Mention (@kullanıcı) Sistemi', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => '@kullaniciadi şeklinde kullanıcı etiketlemeye izin verir. Etiketlenen kullanıcı bildirim alır.'],
        'comment_edit_history'       => ['label' => 'Düzenleme Geçmişi Kaydet', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Yorumlarda yapılan düzenlemelerin geçmişini saklar ve görüntülemeye izin verir.'],
        'comment_media_enabled'      => ['label' => 'Görsel Yükleme İzni', 'type' => 'bool', 'default' => '0', 'section' => 'comments', 'tooltip' => 'Kullanıcıların yorumlara görsel (screenshot) eklemesine izin verir.'],
        'comment_spam_detection'     => ['label' => 'Spam Denetimi Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Yorum spam kontrollerinin tamamını açar veya kapatır. Kapalıysa tek kelime, cümle içi kelime, minimum harf/rakam, büyük harf, anlamsız kelime ve tekrarlı yorum denetimleri çalışmaz.'],
        'comment_spam_violation_action' => ['label' => 'Spam’e Takılan Yorum Ne Yapılsın?', 'type' => 'select', 'default' => 'reject', 'section' => 'comments', 'options' => ['reject' => 'Reddet', 'pending' => 'Onaya düşür'], 'tooltip' => 'Spam filtresine takılan yorumun kaydedilmeden reddedileceğini veya onaya gönderileceğini belirler.'],
        'comment_spam_exact_terms'   => ['label' => 'Tek Kelime Filtresi', 'type' => 'text', 'default' => '', 'section' => 'comments', 'rows' => 4, 'tooltip' => 'Satır, virgül veya noktalı virgülle ayırın. Yorumun tamamı bu kelime/ifadelerden biriyle eşleşirse spam sayılır. Örn: sa, as, vv.'],
        'comment_spam_contains_terms'=> ['label' => 'Cümlede Geçen Kelime Filtresi', 'type' => 'text', 'default' => '', 'section' => 'comments', 'rows' => 4, 'tooltip' => 'Satır, virgül veya noktalı virgülle ayırın. Tek kelimeler tam kelime olarak, çok kelimeli ifadeler cümle içinde geçerse yakalanır.'],
        'comment_spam_min_alnum_count' => ['label' => 'Yorumda Bulunması Gereken En Az Harf/Rakam Sayısı', 'type' => 'number', 'default' => '2', 'section' => 'comments', 'min' => 0, 'tooltip' => 'Yorumda bulunması gereken en az harf veya rakam sayısıdır. 0 girilirse bu kontrol tamamen kapanır.'],
        'comment_spam_nonsense_words_enabled' => ['label' => 'Anlamsız Kelime Engelleme', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Açıksa rhsrh, hrtdtrhd, r6ytu, ghk gibi rastgele harf/rakam dizilerini spam olarak işaretler. Normal cümle içindeki tek garip kelime için daha toleranslı davranır.'],
        'comment_spam_duplicate_enabled' => ['label' => 'Tekrarlı Yorum Kontrolü', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Açıksa aynı kullanıcının aynı konuya kısa süre içinde aynı yorumu tekrar göndermesini spam olarak işaretler. Misafirlerde IP ve yorum içeriği birlikte değerlendirilir.'],
        'comment_spam_duplicate_minutes' => ['label' => 'Tekrar Süresi (dk)', 'type' => 'number', 'default' => '5', 'section' => 'comments', 'min' => 0, 'max' => 1440, 'tooltip' => 'Aynı yorum bu dakika aralığında tekrar gönderilirse spam sayılır. 0 girilirse tekrarlı yorum kontrolü kapanır.'],
        'comment_spam_block_uppercase' => ['label' => 'Büyük Harf Engelleme', 'type' => 'bool', 'default' => '0', 'section' => 'comments', 'tooltip' => 'Açıksa belirgin şekilde tamamen büyük harfle yazılmış yorumlar spam sayılır. Yüzde ayarı yoktur; kısa kısaltmalar korunur.'],
        'comment_spam_exempt_usernames' => ['label' => 'Spam Muaf Kullanıcı Adları', 'type' => 'multiselect', 'default' => '', 'section' => 'comments', 'options' => [], 'size' => 7, 'searchable' => true, 'search_placeholder' => 'Kullanici ara...', 'max_visible_options' => 10, 'normalize_list_values' => true, 'tooltip' => 'Listeden seçilen kullanıcı adları yorum spam kontrollerinden tamamen muaf tutulur.'],
        'comment_spam_exempt_groups'    => ['label' => 'Spam Muaf Gruplar', 'type' => 'multiselect', 'default' => '', 'section' => 'comments', 'options' => [], 'size' => 7, 'normalize_list_values' => true, 'tooltip' => 'Listeden seçilen gruplardaki kullanıcılar yorum spam kontrollerinden tamamen muaf tutulur.'],
        'comment_report_enabled'     => ['label' => 'Şikayet Sistemi Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'comments', 'tooltip' => 'Kullanıcıların yorumları şikayet etmesine izin verir.'],
        'comment_auto_hide_reports'  => ['label' => 'Oto-Gizleme Şikayet Sayısı', 'type' => 'number', 'default' => '5', 'section' => 'comments', 'min' => 0, 'tooltip' => 'Bir yorum bu sayıda şikayet alırsa otomatik gizlenir (0 = kapalı).'],

        // -- Dosya Yoneticisi -----------------------------------
        // Download Manager
        'download_countdown_seconds' => ['label' => 'Konu İçi Geri Sayım Süresi (sn)', 'type' => 'number', 'default' => '5', 'section' => 'downloads', 'min' => 0, 'max' => 300, 'tooltip' => 'Konu içindeki indirme kartında uygulanacak bekleme süresi. 0 beklemeyi kapatır.'],
        'download_redirect_countdown_seconds' => ['label' => 'Yönlendirme Sayfası Geri Sayım Süresi (sn)', 'type' => 'number', 'default' => '5', 'section' => 'downloads', 'min' => 0, 'max' => 300, 'tooltip' => 'Dış bağlantı yönlendirme sayfasındaki bekleme süresi. 0 beklemeyi kapatır.', 'enabled_when' => ['key' => 'download_redirect_auto_enabled', 'value' => '1'], 'disabled_help' => 'Bu süreyi kullanmak için Otomatik Yönlendirme Aktif ayarını açın.'],
        'download_ready_text'        => ['label' => 'Tiklama Oncesi Metin', 'type' => 'string', 'default' => 'İndirmek için tıklayınız', 'section' => 'downloads'],
        'download_wait_text'         => ['label' => 'Geri Sayım Metni', 'type' => 'string', 'default' => 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz', 'section' => 'downloads'],
        'download_show_counts'       => ['label' => 'Link Bazli İndirme Sayacıni Goster', 'type' => 'bool', 'default' => '1', 'section' => 'downloads'],
        'download_done_text'         => ['label' => 'Hazır Metni', 'type' => 'string', 'default' => 'İndirme linkiniz hazır, indirmek için tıklayın', 'section' => 'downloads'],
        'download_security_notice_text' => ['label' => 'Güvenlik Notu Metni', 'type' => 'text', 'default' => 'İndirme bağlantısı açılmadan önce kısa bir güvenlik beklemesi uygulanır. Hedef alan adını kontrol edip dış bağlantı onay ekranından devam edebilirsiniz.', 'section' => 'downloads', 'rows' => 3, 'tooltip' => 'Konu içindeki indirme alanında gösterilen güvenlik beklemesi açıklamasını düzenler.'],
        'download_redirect_page_enabled' => ['label' => 'Yönlendirme Sayfası Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'downloads', 'tooltip' => 'Kapalıysa geçerli indirme bağlantıları sayaç güncellemesinden sonra doğrudan hedef adrese yönlendirilir.'],
        'download_redirect_auto_enabled' => ['label' => 'Otomatik Yönlendirme Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'downloads'],
        'download_redirect_show_target_url' => ['label' => 'Hedef URL Görünsün', 'type' => 'bool', 'default' => '1', 'section' => 'downloads'],
        'download_redirect_kicker' => ['label' => 'Üst Etiket Metni', 'type' => 'string', 'default' => 'Güvenli geçiş kontrolü', 'section' => 'downloads'],
        'download_redirect_title' => ['label' => 'Sayfa Başlığı', 'type' => 'string', 'default' => 'Dış indirme bağlantısı', 'section' => 'downloads'],
        'download_redirect_intro' => ['label' => 'Açıklama Metni', 'type' => 'text', 'default' => 'Dosya site dışında barındırılıyor. Devam etmeden önce hedef alan adını ve bağlantı bilgisini kontrol edin.', 'section' => 'downloads'],
        'download_redirect_host_label' => ['label' => 'Hedef Alan Adı Etiketi', 'type' => 'string', 'default' => 'Hedef alan adı', 'section' => 'downloads'],
        'download_redirect_link_label' => ['label' => 'Bağlantı Etiketi', 'type' => 'string', 'default' => 'Bağlantı', 'section' => 'downloads'],
        'download_redirect_topic_label' => ['label' => 'İçerik Etiketi', 'type' => 'string', 'default' => 'İçerik', 'section' => 'downloads'],
        'download_redirect_protocol_label' => ['label' => 'Protokol Etiketi', 'type' => 'string', 'default' => 'Protokol', 'section' => 'downloads'],
        'download_redirect_safety_domain_text' => ['label' => 'Güvenlik Maddesi 1', 'type' => 'string', 'default' => 'Hedef domain açıkça gösteriliyor', 'section' => 'downloads'],
        'download_redirect_safety_count_text' => ['label' => 'Güvenlik Maddesi 2', 'type' => 'string', 'default' => 'İndirme sayacı yalnızca devam edince güncellenir', 'section' => 'downloads'],
        'download_redirect_safety_external_text' => ['label' => 'Güvenlik Maddesi 3', 'type' => 'string', 'default' => 'Dış sitenin güvenliği size ait kontroldedir', 'section' => 'downloads'],
        'download_redirect_note' => ['label' => 'Alt Not', 'type' => 'text', 'default' => 'Devam ettiğinizde indirme sayacı güncellenecek ve yeni hedefe yönlendirileceksiniz.', 'section' => 'downloads'],
        'download_redirect_timer_template' => ['label' => 'Sayaç Metni Şablonu', 'type' => 'string', 'default' => '{{seconds}} saniye içinde otomatik yönlendirileceksiniz.', 'section' => 'downloads', 'tooltip' => '{{seconds}} değişkeni kalan saniye ile değiştirilir.'],
        'download_redirect_primary_label' => ['label' => 'Birincil Buton Metni', 'type' => 'string', 'default' => 'Hedefe Git', 'section' => 'downloads'],
        'download_redirect_primary_countdown_label' => ['label' => 'Geri Sayım Buton Metni', 'type' => 'string', 'default' => 'Hedefe Git ({{seconds}})', 'section' => 'downloads'],
        'download_redirect_redirecting_label' => ['label' => 'Yönleniyor Buton Metni', 'type' => 'string', 'default' => 'Yönlendiriliyor...', 'section' => 'downloads'],
        'download_redirect_secondary_label' => ['label' => 'İkincil Buton Metni', 'type' => 'string', 'default' => 'Konuya Dön', 'section' => 'downloads'],
        'download_redirect_default_link_name' => ['label' => 'Varsayılan Link Adı', 'type' => 'string', 'default' => 'Harici kaynak', 'section' => 'downloads'],
        'download_redirect_default_topic_title' => ['label' => 'Varsayılan İçerik Adı', 'type' => 'string', 'default' => 'Konu', 'section' => 'downloads'],
        'download_redirect_missing_message' => ['label' => 'Bulunamadı Hata Metni', 'type' => 'string', 'default' => 'İndirme bağlantısı bulunamadı veya kaldırılmış.', 'section' => 'downloads'],
        'download_redirect_invalid_message' => ['label' => 'Geçersiz Link Hata Metni', 'type' => 'string', 'default' => 'Geçersiz indirme bağlantısı.', 'section' => 'downloads'],
        'download_redirect_error_message' => ['label' => 'Genel Hata Metni', 'type' => 'string', 'default' => 'İndirme işlemi sırasında bir hata oluştu.', 'section' => 'downloads'],

        'download_access_mode' => [
            'label' => 'İndirme Erişim Kilidi',
            'type' => 'select',
            'default' => 'public',
            'section' => 'downloads',
            'options' => [
                'public' => 'Herkese Açık',
                'members' => 'Sadece Giriş Yapan Üyeler',
                'members_comment' => 'Üyelik + Yorum Şartlı',
            ],
            'tooltip' => 'Konu detayındaki indirme kartlarının kimlere açık olacağını belirler.',
        ],
        'download_access_comment_requirement' => [
            'label' => 'Yorum Doğrulaması',
            'type' => 'select',
            'default' => 'submitted',
            'section' => 'downloads',
            'options' => [
                'submitted' => 'Yorum Gönderimi Yeterli',
                'approved' => 'Yorum Onayı Gerekli',
            ],
            'tooltip' => 'Üyelik + Yorum Şartlı modunda yorumun ne zaman geçerli sayılacağını belirler.',
        ],
        'download_access_grant_mode' => [
            'label' => 'Yorum Erişimi Süresi',
            'type' => 'select',
            'default' => 'permanent',
            'section' => 'downloads',
            'options' => [
                'permanent' => 'Kalıcı erişim',
                'timed' => 'Belirli süreli erişim',
            ],
            'tooltip' => 'Belirli süreli erişimde süre dolduğunda kullanıcının yeniden yorum yapması gerekir.',
        ],
        'download_access_grant_duration_value' => [
            'label' => 'Erişim Süresi Değeri',
            'type' => 'number',
            'default' => '24',
            'section' => 'downloads',
            'min' => 1,
            'max' => 525600,
            'tooltip' => 'Dakika, saat veya gün seçimine uygulanacak pozitif tam sayı.',
        ],
        'download_access_grant_duration_unit' => [
            'label' => 'Erişim Süresi Birimi',
            'type' => 'select',
            'default' => 'hours',
            'section' => 'downloads',
            'options' => [
                'minutes' => 'Dakika',
                'hours' => 'Saat',
                'days' => 'Gün',
            ],
        ],
        'download_access_relock_on_comment_delete' => [
            'label' => 'Yorum Silinince Erişimi Tekrar Kilitle',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
            'tooltip' => 'Açıksa erişimi sağlayan yorum silindiğinde indirme bağlantıları tekrar kilitlenir.',
        ],
        'download_exempt_usernames' => [
            'label' => 'İndirme Muaf Kullanıcı Adları',
            'type' => 'multiselect',
            'default' => '',
            'section' => 'downloads',
            'options' => [],
            'size' => 7,
            'searchable' => true,
            'search_placeholder' => 'Kullanici ara...',
            'max_visible_options' => 10,
            'normalize_list_values' => true,
            'tooltip' => 'Listeden seçilen kullanıcı adları, seçilen kapsam dahilindeki indirme kilitleri ve sınırlarından muaf tutulur.',
        ],
        'download_exempt_groups' => [
            'label' => 'İndirme Muaf Gruplar',
            'type' => 'multiselect',
            'default' => '',
            'section' => 'downloads',
            'options' => [],
            'size' => 7,
            'normalize_list_values' => true,
            'tooltip' => 'Listeden seçilen gruplardaki kullanıcılar, seçilen kapsam dahilindeki indirme kilitleri ve sınırlarından muaf tutulur.',
        ],
        'download_exempt_scopes' => [
            'label' => 'Muafiyet Kapsamı',
            'type' => 'multiselect',
            'default' => 'access_lock,inline_countdown,redirect_countdown,count_rate_limit',
            'section' => 'downloads',
            'options' => [
                'access_lock' => 'Erişim kilidi',
                'inline_countdown' => 'Konu içi geri sayım',
                'redirect_countdown' => 'Yönlendirme geri sayımı',
                'count_rate_limit' => 'İndirme sayacı sınırı',
            ],
            'size' => 4,
            'allow_empty_selection' => true,
            'tooltip' => 'Muaf kullanıcı ve grupların hangi indirme kilidi veya sınırlarını bypass edeceğini belirler.',
        ],
        'download_access_login_message' => [
            'label' => 'Giriş Kilidi Mesajı',
            'type' => 'string',
            'default' => 'Önce giriş yapın, sonra bir yorum gönderin; kilit otomatik açılır.',
            'section' => 'downloads',
        ],
        'download_access_comment_message' => [
            'label' => 'Yorum Gerekli Açıklama Metni',
            'type' => 'string',
            'default' => 'Önce bir yorum gönderin; kilit otomatik açılır.',
            'section' => 'downloads',
            'tooltip' => 'Yorum şartı henüz tamamlanmadığında, “Yorum gerekli” başlığının altında gösterilen açıklama metnidir.',
        ],
        'download_access_comment_title' => [
            'label' => 'Yorum Kilidi Başlığı',
            'type' => 'string',
            'default' => 'Yorum gerekli',
            'section' => 'downloads',
        ],
        'download_access_locked_button_text' => [
            'label' => 'Kilitli Kart Buton Metni',
            'type' => 'string',
            'default' => 'Kilidi Aç',
            'section' => 'downloads',
        ],
        'download_access_comment_cta_label' => [
            'label' => 'Yorum Çağrı Metni',
            'type' => 'string',
            'default' => 'Yorumlara Git',
            'section' => 'downloads',
        ],
        'download_access_open_auth_popup' => [
            'label' => 'Kilitte Giriş/Kayıt Penceresini Aç',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_focus_comment_form' => [
            'label' => 'Yorum Şartında Forma Odaklan',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_unlock_after_auth' => [
            'label' => 'Giriş Sonrası Sayfayı Yenile',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_unlock_after_comment' => [
            'label' => 'Yorum Sonrası Sayfayı Yenile',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_auth_modal_title' => [
            'label' => 'Giriş/Kayıt Penceresi Başlığı',
            'type' => 'string',
            'default' => 'İndirme linklerini açmak için giriş yapın',
            'section' => 'downloads',
        ],
        'download_access_auth_login_label' => [
            'label' => 'Giriş Sekmesi Metni',
            'type' => 'string',
            'default' => 'Giriş Yap',
            'section' => 'downloads',
        ],
        'download_access_auth_register_label' => [
            'label' => 'Kayıt Sekmesi Metni',
            'type' => 'string',
            'default' => 'Kayıt Ol',
            'section' => 'downloads',
        ],
        'download_access_auth_success_message' => [
            'label' => 'Giriş Başarı Mesajı',
            'type' => 'string',
            'default' => 'Oturum başarıyla açıldı. Kilitli indirme kartları güncelleniyor.',
            'section' => 'downloads',
        ],
        'download_access_success_notice_enabled' => [
            'label' => 'Erişim Başarı Bildirimini Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
            'tooltip' => 'Üyelik veya yorum şartlarını tamamlayan kullanıcıya indirme alanında yeşil başarı durumu gösterir.',
        ],
        'download_access_success_message' => [
            'label' => 'Erişim Başarı Mesajı',
            'type' => 'string',
            'default' => 'Tüm erişim şartlarını tamamladınız. İndirme bağlantıları kullanıma hazır.',
            'section' => 'downloads',
        ],
        'download_access_progress_enabled' => [
            'label' => 'Erişim İlerlemesini Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_progress_template' => [
            'label' => 'Erişim İlerleme Metni Şablonu',
            'type' => 'string',
            'default' => '{{completed}} adımdan {{total}} adımı tamamlandı',
            'section' => 'downloads',
            'tooltip' => '{{completed}} tamamlanan, {{total}} toplam adım sayısıyla değiştirilir.',
        ],
        'download_access_success_animation_enabled' => [
            'label' => 'Başarı Animasyonunu Etkinleştir',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_success_auto_compact' => [
            'label' => 'Başarı Alanını Otomatik Daralt',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_success_compact_delay' => [
            'label' => 'Başarı Alanı Daralma Süresi (Saniye)',
            'type' => 'number',
            'default' => '5',
            'section' => 'downloads',
            'min' => 0,
            'max' => 60,
            'tooltip' => '0 seçilirse başarı alanı beklemeden kompakt hale gelir.',
        ],
        'download_access_highlight_first_card' => [
            'label' => 'Kilit Açılınca İlk Kartı Vurgula',
            'type' => 'bool',
            'default' => '1',
            'section' => 'downloads',
        ],
        'download_access_pending_message' => [
            'label' => 'Yorum Onayı Bekleme Mesajı',
            'type' => 'string',
            'default' => 'Yorumunuz gönderildi ve yönetici onayı bekliyor. Onaylandığında indirme bağlantıları otomatik açılacak.',
            'section' => 'downloads',
        ],
        'download_access_pending_button_text' => [
            'label' => 'Onay Bekleyen Kart Buton Metni',
            'type' => 'string',
            'default' => 'Onay Bekleniyor',
            'section' => 'downloads',
        ],
        'download_access_expired_title' => [
            'label' => 'Süresi Dolan Erişim Başlığı',
            'type' => 'string',
            'default' => 'Yorum erişim süreniz doldu',
            'section' => 'downloads',
        ],
        'download_access_expired_message' => [
            'label' => 'Süresi Dolan Erişim Açıklaması',
            'type' => 'string',
            'default' => 'İndirme bağlantılarını yeniden açmak için yeni bir yorum gönderin.',
            'section' => 'downloads',
        ],
        'download_access_active_until_template' => [
            'label' => 'Aktif Erişim Bitiş Metni',
            'type' => 'string',
            'default' => 'İndirme erişiminiz {{expires_at}} tarihine kadar açık.',
            'section' => 'downloads',
            'tooltip' => '{{expires_at}} erişimin biteceği tarih ve saatle değiştirilir.',
        ],

        'max_upload_size'        => ['label' => 'Maks. Dosya Boyutu (MB)',    'type' => 'number', 'default' => '50',  'section' => 'file_manager', 'group' => 'Dosya Ayarlari', 'tooltip' => 'Dosya Yoneticisi uzerinden yuklenebilecek tekil dosya boyutu. 0 sinirsiz anlamina gelir.'],
        'upload_path'            => ['label' => 'Yukleme Dizini',             'type' => 'string', 'default' => 'uploads/',  'section' => 'file_manager', 'group' => 'Dosya Ayarlari', 'tooltip' => 'Dosya Yoneticisi kok dizini. Guvenlik icin proje icindeki goreli dizin kullanin.'],
        'allowed_file_ext'       => ['label' => 'Izinli Dosya Uzantilari',    'type' => 'string', 'default' => 'jpg,jpeg,png,gif,webp,pdf,zip,rar,7z,txt,md,json,csv,docx,xlsx,pptx,mp4,webm,mp3,wav', 'section' => 'file_manager', 'group' => 'Dosya Ayarlari', 'tooltip' => 'Dosya Yoneticisi icin kabul edilecek guvenli uzantilari virgul ile ayirin. Script ve calistirilabilir uzantilar kabul edilmez.'],
        'media_default_folder'   => ['label' => 'Varsayilan Yukleme Klasoru', 'type' => 'select', 'default' => 'genel', 'section' => 'file_manager', 'group' => 'Dosya Ayarlari', 'options' => ['genel' => 'Genel', 'konu' => 'Konu', 'profil' => 'Profil'], 'tooltip' => 'Dosya Yoneticisi acilisinda secili dizin yokken yukleme formunun hedef klasoru.'],

        // -- Mod Yukle (Kullanici) ------------------------------
        'user_upload_enabled'    => ['label' => 'Kullanicilar Mod Yukleyebilir', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Genel', 'tooltip' => 'Kullanici tarafindaki Mod Yukle sayfasini aktif eder veya tamamen kapatir.'],
        'user_upload_require_approval' => ['label' => 'Modlar Onay Gerektirir', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Akış', 'tooltip' => 'Acikken kullanici gonderileri yayinlanmadan once moderator/admin onayina duser.'],
        'user_upload_default_status' => ['label' => 'Onaysiz Gonderim Varsayilan Durumu', 'type' => 'select', 'default' => 'published', 'section' => 'content_moderation', 'group' => 'Akış', 'options' => ['published' => 'Yayinda', 'draft' => 'Taslak'], 'tooltip' => 'Onay zorunlu degilse yeni kullanici modlarinin hangi durumla kaydedilecegini belirler.'],
        'user_upload_require_cover' => ['label' => 'Üst Resim Zorunlu', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'Kullanici mod gonderirken kapak/ust gorsel yuklemeyi zorunlu hale getirir.'],
        'user_upload_require_gallery' => ['label' => 'En Az 1 Galeri Resmi Zorunlu', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'Galeri adiminda en az bir mod gorseli yuklenmeden gonderime izin vermez.'],
        'user_upload_require_author' => ['label' => 'Yapimci Alani Zorunlu', 'type' => 'bool', 'default' => '0', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'Kullanici mod yuklerken yapimci bilgisini doldurmak zorunda kalir.'],
        'user_upload_require_version' => ['label' => 'Oyun Surumu Zorunlu', 'type' => 'bool', 'default' => '0', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'Kullanici mod yuklerken uyumlu oyun surumunu doldurmak zorunda kalir.'],
        'user_upload_require_download_link' => ['label' => 'Indirme Linki Zorunlu', 'type' => 'bool', 'default' => '0', 'section' => 'content_moderation', 'group' => 'Zorunlu Alanlar', 'tooltip' => 'En az bir indirme kaynagi girilmeden gonderime izin verilmez.'],
        'user_upload_min_title_length' => ['label' => 'Minimum Baslik Uzunlugu', 'type' => 'number', 'default' => '3', 'section' => 'content_moderation', 'group' => 'Kalite', 'tooltip' => 'Mod basliginin kabul edilmesi icin gereken en az karakter sayisi.'],
        'user_upload_max_title_length' => ['label' => 'Maksimum Baslik Uzunlugu', 'type' => 'number', 'default' => '150', 'section' => 'content_moderation', 'group' => 'Kalite', 'tooltip' => 'Mod basliginda izin verilen en fazla karakter sayisi.'],
        'user_upload_min_content_length' => ['label' => 'Minimum Aciklama Uzunlugu', 'type' => 'number', 'default' => '10', 'section' => 'content_moderation', 'group' => 'Kalite', 'tooltip' => 'Mod aciklamasinin kabul edilmesi icin gereken en az metin uzunlugu.'],
        'user_upload_max_images' => ['label' => 'Maks. Resim Sayisi', 'type' => 'number', 'default' => '10', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Bir mod gonderisinde yuklenebilecek maksimum galeri gorseli sayisi.'],
        'user_upload_cover_max_size_mb' => ['label' => 'Kapak Maks. Boyut (MB)', 'type' => 'number', 'default' => '10', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak gorseli icin izin verilen maksimum dosya boyutu.'],
        'user_upload_gallery_max_size_mb' => ['label' => 'Galeri Gorsel Maks. Boyut (MB)', 'type' => 'number', 'default' => '10', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Her bir galeri gorseli icin izin verilen maksimum dosya boyutu.'],
        'user_upload_allowed_image_ext' => ['label' => 'Izinli Gorsel Uzantilari', 'type' => 'string', 'default' => 'jpg,jpeg,png,webp', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri yuklemelerinde kabul edilecek gorsel uzantilarini virgulle ayirin.'],
        'user_upload_image_min_width' => ['label' => 'Gorsel Minimum Genislik (px)', 'type' => 'number', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri gorselleri icin minimum genislik. 0 sinir yok demektir.'],
        'user_upload_image_min_height' => ['label' => 'Gorsel Minimum Yukseklik (px)', 'type' => 'number', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri gorselleri icin minimum yukseklik. 0 sinir yok demektir.'],
        'user_upload_image_max_width' => ['label' => 'Gorsel Maksimum Genislik (px)', 'type' => 'number', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri gorselleri icin maksimum genislik. 0 sinir yok demektir.'],
        'user_upload_image_max_height' => ['label' => 'Gorsel Maksimum Yukseklik (px)', 'type' => 'number', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Görsel Kuralları', 'tooltip' => 'Kapak ve galeri gorselleri icin maksimum yukseklik. 0 sinir yok demektir.'],
        'user_upload_allow_video_url' => ['label' => 'Video URL Alanina Izin Ver', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Video ve Linkler', 'tooltip' => 'Kullanici formunda tanitim videosu URL alani gorunsun ve kaydedilebilsin.'],
        'user_upload_allowed_video_hosts' => ['label' => 'Izinli Video Saglayicilari', 'type' => 'string', 'default' => 'youtube.com,youtu.be,vimeo.com', 'section' => 'user_uploads', 'group' => 'Video ve Linkler', 'tooltip' => 'Virgul ile ayirin. Bos birakilirsa tum video URLleri kabul edilir.'],
        'user_upload_max_size_mb' => ['label' => 'Maks. Dosya Boyutu (MB)', 'type' => 'number', 'default' => '50', 'section' => 'user_uploads', 'group' => 'Limitler', 'tooltip' => 'Opsiyonel mod dosyasi/ek dosya yuklemesi icin izin verilen maksimum boyut.'],
        'user_upload_rate_limit' => ['label' => 'Mod Gönderim Limiti', 'type' => 'number', 'default' => '0', 'section' => 'rate_limit', 'group' => 'Gönderimler', 'tooltip' => 'Belirlenen süre içinde bir kullanıcının en fazla kaç mod gönderebileceğini belirler. 0 = sınırsız.'],
        'user_upload_rate_window' => ['label' => 'Mod Gönderim Süresi (dakika)', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'group' => 'Gönderimler', 'tooltip' => 'Mod gönderim limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'ban_appeal_message_limit' => ['label' => 'Ban İtiraz Mesaj Limiti', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'group' => 'Ban İtirazları', 'tooltip' => 'Belirlenen süre içinde kullanıcının en fazla kaç ban itiraz mesajı gönderebileceğini belirler. 0 = limit kapalı.'],
        'ban_appeal_message_cooldown_minutes' => ['label' => 'Ban İtiraz Mesaj Süresi (dakika)', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'group' => 'Ban İtirazları', 'tooltip' => 'Ban itiraz mesaj limitinin kaç dakikalık süre içinde uygulanacağını belirler. Limiti kapatmak için mesaj limitini 0 yapın.'],
        'user_upload_block_duplicate_titles' => ['label' => 'Ayni Baslikla Tekrar Gonderimi Engelle', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Kalite', 'tooltip' => 'Ayni kullanicinin ayni baslikla tekrar mod gondermesini engeller.'],
        'user_upload_default_content_align' => ['label' => 'Varsayilan Aciklama Hizasi', 'type' => 'select', 'default' => 'center', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'options' => ['left' => 'Sol', 'center' => 'Orta', 'right' => 'Sag'], 'tooltip' => 'Kullanici aciklama metni icin varsayilan hizalamayi belirler.'],
        'user_upload_submission_notice' => ['label' => 'Gonderim Sonrasi Bilgilendirme Metni', 'type' => 'text', 'default' => 'Onay durumunu Profil > Konularim menusunden takip edebilirsiniz.', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Basarili gonderimden sonra kullaniciya gosterilecek takip/bilgilendirme metni.'],
        'user_upload_lock_after_submit' => ['label' => 'Basarili Gonderimden Sonra Butonu Kilitle', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Basarili gonderimden sonra tekrar tiklamayi ve cift gonderimi onlemek icin butonu kilitler.'],
        'user_upload_wizard_enabled' => ['label' => 'Wizard Arayuzu Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Mod yukleme formunu adim adim wizard arayuzuyle gosterir.'],
        'user_upload_allow_step_skip' => ['label' => 'Wizard Adim Atlamaya Izin Ver', 'type' => 'bool', 'default' => '0', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Acikken kullanici zorunlu kontrolleri tamamlamadan wizard adimlari arasinda gezebilir.'],
        'user_upload_show_profile_followup' => ['label' => 'Son Adimda Profil Takip Kutusu Goster', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Onay adiminda kullaniciya gonderisini profilinden takip edebilecegini hatirlatan kutuyu gosterir.'],
        'user_upload_show_profile_button' => ['label' => 'Konularima Git Butonu Goster', 'type' => 'bool', 'default' => '1', 'section' => 'user_uploads', 'group' => 'Form Davranışı', 'tooltip' => 'Profil takip kutusunda Konularima Git baglantisini gosterir veya gizler.'],

        // -- Icerik Moderasyonu -------------------------------
        'content_moderation_enabled' => ['label' => 'İçerik Moderasyonu Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'content_moderation', 'group' => 'Akış', 'tooltip' => 'Kapalıysa otomatik yasak kelime taraması ve otomatik flag aksiyonları devre dışı kalır. Onay ve zorunlu alan ayarları ayrıca uygulanmaya devam eder.'],
        'content_moderation_blocked_words' => ['label' => 'Yasak Kelime Listesi', 'type' => 'text', 'default' => '', 'section' => 'content_moderation', 'group' => 'Kelime Filtresi', 'tooltip' => 'Virgül veya satır ile ayırın. Başlık ve açıklama metninde aranır.'],
        'content_moderation_blocked_words_action' => ['label' => 'Yasak Kelime Aksiyonu', 'type' => 'select', 'default' => 'draft', 'section' => 'content_moderation', 'group' => 'Kelime Filtresi', 'options' => ['draft' => 'Taslak + Moderasyon Bayrağı', 'reject' => 'Gönderimi Reddet', 'flag' => 'Yayına Al + Moderasyon Bayrağı'], 'tooltip' => 'Yasak kelime bulunduğunda konuya uygulanacak davranışı belirler.'],
        'content_moderation_blocked_words_message' => ['label' => 'Reddetme Mesajı', 'type' => 'string', 'default' => 'İçerikte izin verilmeyen kelime bulundu. Lütfen metni düzenleyin.', 'section' => 'content_moderation', 'group' => 'Kelime Filtresi'],
        'content_moderation_flag_note' => ['label' => 'Moderasyon Bayrağı Notu', 'type' => 'string', 'default' => 'Otomatik içerik moderasyonu incelemesi gerekiyor.', 'section' => 'content_moderation', 'group' => 'Kelime Filtresi'],

        // -- Rota Filtreleri -------------------------------
        'route_topic_prefix'            => ['label' => 'Konu URL On Eki',                     'type' => 'string', 'default' => 'konu',      'section' => 'route_filters'],
        'route_category_prefix'         => ['label' => 'Kategori URL On Eki',                 'type' => 'string', 'default' => 'kategori',  'section' => 'route_filters'],
        'route_category_list_prefix'    => ['label' => 'Genel Kategori Listesi URL On Eki',   'type' => 'string', 'default' => 'kategori',  'section' => 'route_filters', 'tooltip' => 'Tum kategorilerin listelendigi ana kategori sayfasinin URL on eki. Ornek: /kategori veya /kategoriler'],
        'route_profile_prefix'          => ['label' => 'Profil URL On Eki',                   'type' => 'string', 'default' => 'profil',    'section' => 'route_filters'],
        'route_login_path'              => ['label' => 'Giris Sayfasi URL Yolu',              'type' => 'string', 'default' => 'giris',            'section' => 'route_filters', 'tooltip' => 'Ornek: /giris'],
        'route_register_path'           => ['label' => 'Kayit Sayfasi URL Yolu',              'type' => 'string', 'default' => 'kayit',            'section' => 'route_filters', 'tooltip' => 'Ornek: /kayit'],
        'route_logout_path'             => ['label' => 'Cikis Islem URL Yolu',                'type' => 'string', 'default' => 'cikis',            'section' => 'route_filters', 'tooltip' => 'Ornek: /cikis'],
        'route_forgot_password_path'    => ['label' => 'Sifremi Unuttum URL Yolu',            'type' => 'string', 'default' => 'sifremi-unuttum',  'section' => 'route_filters', 'tooltip' => 'Ornek: /sifremi-unuttum'],
        'route_reset_password_path'     => ['label' => 'Sifre Sifirlama URL Yolu',            'type' => 'string', 'default' => 'sifre-sifirla',    'section' => 'route_filters', 'tooltip' => 'Ornek: /sifre-sifirla'],
        'route_notifications_path'      => ['label' => 'Bildirimler URL Yolu',                'type' => 'string', 'default' => 'bildirimler',      'section' => 'route_filters', 'tooltip' => 'Ornek: /bildirimler'],
        'route_messages_path'           => ['label' => 'Mesajlar URL Yolu',                   'type' => 'string', 'default' => 'mesajlar',         'section' => 'route_filters', 'tooltip' => 'Ornek: /mesajlar'],
        'route_leaderboard_path'        => ['label' => 'Liderlik URL Yolu',                   'type' => 'string', 'default' => 'liderlik',         'section' => 'route_filters', 'tooltip' => 'Ornek: /liderlik'],
        'route_ban_appeals_path'        => ['label' => 'Ban Itiraz URL Yolu',                 'type' => 'string', 'default' => 'ban-itiraz',       'section' => 'route_filters', 'tooltip' => 'Ornek: /ban-itiraz'],
        'route_contact_path'            => ['label' => 'Iletisim URL Yolu',                   'type' => 'string', 'default' => 'iletisim',         'section' => 'route_filters', 'tooltip' => 'Ornek: /iletisim'],
        'route_upload_topic_path'       => ['label' => 'Konu Yukle URL Yolu',                 'type' => 'string', 'default' => 'konu-yukle',       'section' => 'route_filters', 'tooltip' => 'Ornek: /konu-yukle'],
        'route_edit_topic_path'         => ['label' => 'Konu Duzenle URL Yolu',               'type' => 'string', 'default' => 'konu-duzenle',     'section' => 'route_filters', 'tooltip' => 'Ornek: /konu-duzenle'],
        'route_download_path'           => ['label' => 'Indirme URL Yolu',                    'type' => 'string', 'default' => 'indir',            'section' => 'route_filters', 'tooltip' => 'Ornek: /indir'],
        'route_events_path'             => ['label' => 'Etkinlik Merkezi URL Yolu',           'type' => 'string', 'default' => 'events',           'section' => 'route_filters', 'tooltip' => 'Events alt sayfalari bu taban yola baglanir. Ornek: /events, /events/wheel'],
        'route_www_redirect'            => ['label' => 'WWW Yonlendirmesi',                   'type' => 'select', 'default' => 'none',      'section' => 'route_filters', 'options' => ['none' => 'Islem Yok', 'www' => 'WWW Zorla (www.site.com)', 'non-www' => 'WWW Kaldir (site.com)']],
        'route_https_redirect'          => ['label' => 'HTTPS Zorla (SSL)',                   'type' => 'bool',   'default' => '0',         'section' => 'route_filters'],
        'route_hide_index_php'          => ['label' => 'Ana Sayfa URL\'sinde index.php Gizle',  'type' => 'bool',   'default' => '0',         'section' => 'route_filters', 'tooltip' => 'Etkinleştirildiğinde, ana sayfa https://site.example/ şeklinde görünür, https://site.example/index.php yerine'],
        'route_trailing_slash'          => ['label' => 'Sondaki Slash (/) Yonlendirmesi',     'type' => 'select', 'default' => 'none',      'section' => 'route_filters', 'options' => ['none' => 'Islem Yok', 'add' => 'Sona Slash Ekle (/kategori/)', 'remove' => 'Sondaki Slash\'i Kaldir (/kategori)']],
        'route_topic_id_suffix'         => ['label' => 'Konu URL Sonuna ID Ekle',              'type' => 'bool',   'default' => '1',         'section' => 'route_filters', 'tooltip' => 'Etkinse konu URLleri /konu/slug-id formatinda uretilir.'],
        'route_slug_format'             => ['label' => 'URL Slug Formati (Bosluklar Icin)',   'type' => 'select', 'default' => 'dash',      'section' => 'route_filters', 'options' => ['dash' => 'Tire (-) ornek-baslik', 'underscore' => 'Alt Cizgi (_) ornek_baslik']],
        'route_case_sensitive'          => ['label' => 'Harf Buyuklugu (Case Sensitivity)',   'type' => 'select', 'default' => 'lowercase', 'section' => 'route_filters', 'options' => ['sensitive' => 'Duyarli (Orijinal Birak)', 'lowercase' => 'Tumunu Kucult (SEO Onerisi)']],
        'route_url_max_length'          => ['label' => 'Maksimum URL Uzunlugu',               'type' => 'number', 'default' => '200',       'section' => 'route_filters', 'tooltip' => 'Slug olusturulurken bu degerden sonrasi kesilir.'],
        'route_pagination_format'       => ['label' => 'Sayfalama Formati',                   'type' => 'select', 'default' => 'query',     'section' => 'route_filters', 'options' => ['query' => 'Query String (?page=2)', 'path' => 'Path Bazi (/sayfa/2)']],
        'route_sort_format'             => ['label' => 'Siralama Formati',                    'type' => 'select', 'default' => 'query',     'section' => 'route_filters', 'options' => ['query' => 'Query String (?sort=newest)', 'path' => 'Path Bazi (/siralama/yeni)']],


        // -- Konu Yonetimi --------------------------------

        // -- Toast Bildirim Ayarları -----------------------
        'toast_enabled' => [
            'label'   => 'Toast Bildirim Sistemi',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Sayfa üzerinde anlık bildirim (toast) gösterimini açar veya kapatır'
        ],
        'toast_position' => [
            'label'   => 'Bildirim Konumu',
            'type'    => 'select',
            'options' => [
                'top-right'    => 'Sağ Üst',
                'top-left'     => 'Sol Üst',
                'top-center'   => 'Orta Üst',
                'bottom-right' => 'Sağ Alt',
                'bottom-left'  => 'Sol Alt',
                'bottom-center'=> 'Orta Alt',
            ],
            'default' => 'bottom-right',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimlerin ekranın hangi köşesinde görüneceğini belirler'
        ],
        'toast_duration' => [
            'label'   => 'Varsayılan Gösterim Süresi (ms)',
            'type'    => 'number',
            'default' => '5000',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimin ekranda kalma süresi (milisaniye). 1000 = 1 saniye'
        ],
        'toast_duration_success' => [
            'label'   => 'Başarı Bildirimi Süresi (ms)',
            'type'    => 'number',
            'default' => '4000',
            'section' => 'toast_notifications',
            'tooltip' => 'Başarılı işlem bildirimlerinin ekranda kalma süresi. 0 = varsayılanı kullan'
        ],
        'toast_duration_error' => [
            'label'   => 'Hata Bildirimi Süresi (ms)',
            'type'    => 'number',
            'default' => '8000',
            'section' => 'toast_notifications',
            'tooltip' => 'Hata bildirimlerinin ekranda kalma süresi. Hatalar daha uzun gösterilmelidir. 0 = varsayılanı kullan'
        ],
        'toast_duration_warning' => [
            'label'   => 'Uyarı Bildirimi Süresi (ms)',
            'type'    => 'number',
            'default' => '6000',
            'section' => 'toast_notifications',
            'tooltip' => 'Uyarı bildirimlerinin ekranda kalma süresi. 0 = varsayılanı kullan'
        ],
        'toast_theme' => [
            'label'   => 'Bildirim Teması',
            'type'    => 'select',
            'options' => [
                'default'  => 'Klasik (Koyu Arkaplan)',
                'colored'  => 'Renkli Dolgulu',
                'glass'    => 'Cam Efekti (Glassmorphism)',
                'minimal'  => 'Minimalist',
            ],
            'default' => 'default',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimlerin görsel temasını belirler'
        ],
        'toast_animation' => [
            'label'   => 'Giriş Animasyonu',
            'type'    => 'select',
            'options' => [
                'slide'  => 'Kayma (Slide)',
                'fade'   => 'Solma (Fade)',
                'bounce' => 'Zıplama (Bounce)',
                'scale'  => 'Büyüme (Scale)',
            ],
            'default' => 'slide',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimlerin ekrana giriş animasyonunu belirler'
        ],
        'toast_progress_bar' => [
            'label'   => 'İlerleme Çubuğu Göster',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimin altında kalan süreyi gösteren bir ilerleme çubuğu gösterir'
        ],
        'toast_close_button' => [
            'label'   => 'Kapatma Butonu (×)',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimin sağ üst köşesine bir kapatma (×) butonu ekler'
        ],
        'toast_max_visible' => [
            'label'   => 'Maks. Görünür Bildirim Sayısı',
            'type'    => 'number',
            'default' => '5',
            'section' => 'toast_notifications',
            'tooltip' => 'Ekranda aynı anda görünebilecek maksimum bildirim sayısı. Sınır aşılırsa en eski bildirim kapanır'
        ],
        'toast_stack_direction' => [
            'label'   => 'Yığılma Yönü',
            'type'    => 'select',
            'options' => [
                'down' => 'Aşağı Doğru (Yeni altta)',
                'up'   => 'Yukarı Doğru (Yeni üstte)',
            ],
            'default' => 'down',
            'section' => 'toast_notifications',
            'tooltip' => 'Yeni bildirimlerin mevcut bildirimlerin altına mı üstüne mi ekleneceğini belirler'
        ],
        'toast_click_to_close' => [
            'label'   => 'Tıklayarak Kapat',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Bildirimin herhangi bir yerine tıklayarak kapatma imkanı sunar'
        ],
        'toast_pause_on_hover' => [
            'label'   => 'Üzerine Gelince Duraklat',
            'type'    => 'bool',
            'default' => '1',
            'section' => 'toast_notifications',
            'tooltip' => 'Fare bildirimin üzerine geldiğinde zamanlayıcı durur, ayrıldığında devam eder'
        ],

        // -- Dosya Yoneticisi / Resim Ayarlari -------------------
        'allowed_image_ext'      => ['label' => 'Izinli Gorsel Uzantilari',   'type' => 'string', 'default' => 'png,jpg,jpeg,webp,gif', 'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'auto_resize_images'     => ['label' => 'Otomatik Boyutlandir',       'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'image_resize_width'     => ['label' => 'Boyutlandirma Genişlik (px)','type' => 'number', 'default' => '1920', 'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'image_resize_height'    => ['label' => 'Boyutlandirma Yukseklik (px)','type' => 'number','default' => '1080', 'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'webp_enabled'           => ['label' => 'WebP Donusumu Aktif',        'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'webp_quality'           => ['label' => 'WebP Kalite (1-100)',        'type' => 'number', 'default' => '82',   'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'webp_keep_original'     => ['label' => 'Orijinal Dosyayi Sakla',     'type' => 'bool',   'default' => '0',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'jpeg_quality'           => ['label' => 'JPEG Kalite (1-100)',        'type' => 'number', 'default' => '85',   'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'png_compression'        => ['label' => 'PNG Sıkıştırma (0-9)',      'type' => 'number', 'default' => '6',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'image_strip_metadata'   => ['label' => 'EXIF/Meta Veri Temizle',     'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'image_sharpen'          => ['label' => 'Boyutlandirmada Netlestir',  'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'thumbnail_enabled'      => ['label' => 'Otomatik Thumbnail Olustur', 'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'thumbnail_width'        => ['label' => 'Thumbnail Genişlik (px)',    'type' => 'number', 'default' => '400',  'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'thumbnail_height'       => ['label' => 'Thumbnail Yukseklik (px)',   'type' => 'number', 'default' => '300',  'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'thumbnail_crop'         => ['label' => 'Thumbnail Kirp (Cover)',     'type' => 'bool',   'default' => '1',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'watermark_enabled'      => ['label' => 'Filigran Ekle',             'type' => 'bool',   'default' => '0',    'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'watermark_text'         => ['label' => 'Filigran Metni',            'type' => 'string', 'default' => '',     'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'watermark_position'     => ['label' => 'Filigran Konumu',           'type' => 'select', 'default' => 'bottom-right', 'section' => 'file_manager', 'group' => 'Resim Ayarlari', 'options' => ['top-left' => 'Sol Ust', 'top-right' => 'Sağ Ust', 'bottom-left' => 'Sol Alt', 'bottom-right' => 'Sağ Alt', 'center' => 'Orta']],
        'watermark_opacity'      => ['label' => 'Filigran Saydamlik (%)',    'type' => 'number', 'default' => '30',   'section' => 'file_manager', 'group' => 'Resim Ayarlari'],
        'watermark_font_size'    => ['label' => 'Filigran Yazi Boyutu (px)', 'type' => 'number', 'default' => '16',   'section' => 'file_manager', 'group' => 'Resim Ayarlari'],

        // -- E-posta --------------------------------------------
        'mail_driver'            => ['label' => 'E-posta Surucusu',       'type' => 'select', 'default' => 'smtp', 'section' => 'email', 'options' => ['smtp' => 'SMTP', 'sendmail' => 'Sendmail', 'mail' => 'PHP mail()']],
        'smtp_host'              => ['label' => 'SMTP Sunucu',            'type' => 'string', 'default' => 'localhost', 'section' => 'email'],
        'smtp_port'              => ['label' => 'SMTP Port',              'type' => 'number', 'default' => '587',       'section' => 'email'],
        'smtp_username'          => ['label' => 'SMTP Kullanici Adi',     'type' => 'string', 'default' => '',          'section' => 'email'],
        'smtp_password'          => ['label' => 'SMTP Sifre',             'type' => 'string', 'default' => '',          'section' => 'email'],
        'smtp_encryption'        => ['label' => 'SMTP Sifreleme',         'type' => 'select', 'default' => 'tls', 'section' => 'email', 'options' => ['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Yok']],
        'mail_from_name'         => ['label' => 'Gonderici Adi',          'type' => 'string', 'default' => 'İçerik Topic', 'section' => 'email'],
        'mail_from_address'      => ['label' => 'Gonderici E-posta',      'type' => 'string', 'default' => 'noreply@topic.test', 'section' => 'email'],

        // -- Sistem ---------------------------------------------
        'maintenance_mode'       => ['label' => 'Bakim Modu',                'type' => 'bool',   'default' => '0',        'section' => 'general'],
        'maintenance_message'    => ['label' => 'Bakim Mesaji',              'type' => 'text',   'default' => 'Site bakim modundadir, lutfen daha sonra tekrar deneyin.', 'section' => 'general'],

        // -- Rate Limit -----------------------------------------
        'register_rate_limit'    => ['label' => 'Kayıt Deneme Limiti', 'type' => 'number', 'default' => '3', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde aynı IP adresinin en fazla kaç kayıt denemesi yapabileceğini belirler.'],
        'register_rate_window'   => ['label' => 'Kayıt Deneme Süresi (dakika)', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Kayıt deneme limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'login_rate_limit'       => ['label' => 'Başarısız Giriş Deneme Limiti', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde aynı IP adresinin en fazla kaç başarısız giriş denemesi yapabileceğini belirler.'],
        'login_rate_window'      => ['label' => 'Başarısız Giriş Deneme Süresi (dakika)', 'type' => 'number', 'default' => '15', 'section' => 'rate_limit', 'tooltip' => 'Başarısız giriş deneme limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'password_reset_rate_limit' => ['label' => 'Şifre Sıfırlama İstek Limiti', 'type' => 'number', 'default' => '3', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde aynı IP adresinin en fazla kaç şifre sıfırlama isteği gönderebileceğini belirler.'],
        'password_reset_rate_window' => ['label' => 'Şifre Sıfırlama İstek Süresi (dakika)', 'type' => 'number', 'default' => '30', 'section' => 'rate_limit', 'tooltip' => 'Şifre sıfırlama istek limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'search_rate_limit'      => ['label' => 'Arama İstek Limiti', 'type' => 'number', 'default' => '30', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde aynı IP adresinin en fazla kaç arama isteği gönderebileceğini belirler.'],
        'search_rate_window'     => ['label' => 'Arama İstek Süresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Arama istek limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'api_topics_rate_limit'  => ['label' => 'Konu API İstek Limiti', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde konu API uçlarına en fazla kaç istek gönderilebileceğini belirler.'],
        'api_topics_rate_window' => ['label' => 'Konu API İstek Süresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Konu API istek limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'api_messages_rate_limit' => ['label' => 'Mesaj API İstek Limiti', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde mesaj API uçlarına en fazla kaç istek gönderilebileceğini belirler.'],
        'api_messages_rate_window' => ['label' => 'Mesaj API İstek Süresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Mesaj API istek limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'api_leaderboard_rate_limit' => ['label' => 'Liderlik API İstek Limiti', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde liderlik ve kullanıcı sıralama API uçlarına en fazla kaç istek gönderilebileceğini belirler.'],
        'api_leaderboard_rate_window' => ['label' => 'Liderlik API İstek Süresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Liderlik API istek limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'api_analytics_rate_limit' => ['label' => 'Analitik API İstek Limiti', 'type' => 'number', 'default' => '120', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde analitik API uçlarına en fazla kaç istek gönderilebileceğini belirler.'],
        'api_analytics_rate_window' => ['label' => 'Analitik API İstek Süresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Analitik API istek limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'api_favorite_rate_limit' => ['label' => 'Favori İşlem Limiti', 'type' => 'number', 'default' => '30', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde en fazla kaç favori ekleme veya kaldırma işlemi yapılabileceğini belirler.'],
        'api_favorite_rate_window' => ['label' => 'Favori İşlem Süresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Favori işlem limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'download_count_rate_limit' => ['label' => 'İndirme Sayacı Artırma Limiti', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde aynı IP adresinin aynı indirme kaydı için sayacı en fazla kaç kez artırabileceğini belirler.'],
        'download_count_rate_window' => ['label' => 'İndirme Sayacı Artırma Süresi (dakika)', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'İndirme sayacı artırma limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'comment_mention_rate_max' => ['label' => 'Kullanıcı Etiketleme Arama Limiti', 'type' => 'number', 'default' => '30', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde yorumlarda @kullanıcı araması için en fazla kaç istek yapılabileceğini belirler.'],
        'comment_mention_rate_window' => ['label' => 'Kullanıcı Etiketleme Arama Süresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Kullanıcı etiketleme arama limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'comment_edit_rate_max'  => ['label' => 'Yorum Düzenleme/Silme İşlem Limiti', 'type' => 'number', 'default' => '20', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde en fazla kaç yorum düzenleme veya silme işlemi yapılabileceğini belirler.'],
        'comment_edit_rate_window' => ['label' => 'Yorum Düzenleme/Silme İşlem Süresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Yorum düzenleme/silme işlem limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'comment_reaction_rate_max' => ['label' => 'Yorum Reaksiyon İşlem Limiti', 'type' => 'number', 'default' => '60', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde en fazla kaç yorum reaksiyon işlemi yapılabileceğini belirler.'],
        'comment_reaction_rate_window' => ['label' => 'Yorum Reaksiyon İşlem Süresi (dakika)', 'type' => 'number', 'default' => '1', 'section' => 'rate_limit', 'tooltip' => 'Yorum reaksiyon işlem limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],
        'comment_report_rate_max' => ['label' => 'Yorum Şikayet Gönderim Limiti', 'type' => 'number', 'default' => '5', 'section' => 'rate_limit', 'tooltip' => 'Belirlenen süre içinde en fazla kaç yorum şikayeti gönderilebileceğini belirler.'],
        'comment_report_rate_window' => ['label' => 'Yorum Şikayet Gönderim Süresi (dakika)', 'type' => 'number', 'default' => '10', 'section' => 'rate_limit', 'tooltip' => 'Yorum şikayet gönderim limitinin kaç dakikalık süre içinde uygulanacağını belirler.'],

        // -- Sosyal Medya ---------------------------------------
        'social_facebook'        => ['label' => 'Facebook URL',   'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_twitter'         => ['label' => 'Twitter/X URL',  'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_instagram'       => ['label' => 'Instagram URL',  'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_youtube'         => ['label' => 'YouTube URL',    'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_github'          => ['label' => 'GitHub URL',     'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_discord'         => ['label' => 'Discord URL',    'type' => 'string', 'default' => '', 'section' => 'social_features'],
        'social_telegram'        => ['label' => 'Telegram URL',   'type' => 'string', 'default' => '', 'section' => 'social_features'],

        // -- Kullanıcı Sistemi -----------------------------------
        'allow_registration'            => ['label' => 'Kayıt Olma İzni',                'type' => 'bool',   'default' => '1',     'section' => 'user_system'],
        'register_username_min_length'  => ['label' => 'Kayıt Kullanıcı Adı Minimum',   'type' => 'number', 'default' => '3',     'section' => 'user_system', 'min' => 3, 'max' => 30, 'tooltip' => 'Kayit formunda izin verilen en dusuk kullanici adi uzunlugu.'],
        'register_username_max_length'  => ['label' => 'Kayıt Kullanıcı Adı Maksimum',   'type' => 'number', 'default' => '30',    'section' => 'user_system', 'min' => 3, 'max' => 30, 'tooltip' => 'Kayit formunda izin verilen en yuksek kullanici adi uzunlugu.'],
        'register_allowed_email_domains' => [
            'label' => 'İzin Verilen E-posta Domainleri',
            'type' => 'textarea',
            'default' => '',
            'section' => 'user_system',
            'tooltip' => 'Her satira bir domain yazin. Ornek: gmail.com veya @gmail.com. Liste doluysa sadece bu domainler kayit olabilir.',
        ],
        'account_email_verification_enabled' => [
            'label' => 'E-posta Doğrulama Sistemi',
            'type' => 'bool',
            'default' => '0',
            'section' => 'user_system',
            'tooltip' => 'Yeni kayıt olan kullanıcılara e-posta doğrulama bağlantısı gönderir. Kullanıcı bağlantıya tıkladığında e-posta adresi doğrulanmış sayılır.',
        ],
        'account_email_verification_required' => [
            'label' => 'Giriş İçin Doğrulama Zorunlu',
            'type' => 'bool',
            'default' => '0',
            'section' => 'user_system',
            'tooltip' => 'E-posta adresini doğrulamayan kullanıcıların giriş yapmasını engeller. E-posta Doğrulama Sistemi açık olmalıdır.',
        ],
        'account_email_verification_ttl_minutes' => ['label' => 'Doğrulama Bağlantısı Süresi (Dakika)', 'type' => 'number', 'default' => '1440', 'section' => 'user_system', 'min' => 15, 'max' => 10080, 'tooltip' => 'Doğrulama bağlantısının kaç dakika geçerli olacağını belirler.'],
        'account_email_verification_resend_cooldown_minutes' => ['label' => 'Tekrar Gönderme Bekleme Süresi (Dakika)', 'type' => 'number', 'default' => '10', 'section' => 'user_system', 'min' => 1, 'max' => 1440, 'tooltip' => 'Aynı e-posta için yeni doğrulama bağlantısı isteyebilmek için beklenecek süre.'],
        'account_email_verification_reminder_enabled' => ['label' => 'Doğrulama Hatırlatma Cronu', 'type' => 'bool', 'default' => '1', 'section' => 'user_system', 'tooltip' => 'E-posta doğrulama bekleyen hesaplara belirli aralıklarla yeniden doğrulama mesajı gönderir.'],
        'account_email_verification_reminder_after_minutes' => ['label' => 'Hatırlatma Eşiği (Dakika)', 'type' => 'number', 'default' => '1440', 'section' => 'user_system', 'min' => 60, 'max' => 10080, 'tooltip' => 'İlk doğrulama e-postasından sonra kaç dakika geçince hatırlatma gönderileceğini belirler.'],
        'account_email_verification_reminder_batch_size' => ['label' => 'Hatırlatma Parti Boyutu', 'type' => 'number', 'default' => '50', 'section' => 'user_system', 'min' => 1, 'max' => 500, 'tooltip' => 'Cron çalıştığında tek turda kaç hesabın yeniden doğrulama mesajı alacağını belirler.'],
        'registration_requires_admin_approval' => [
            'label' => 'Yeni Kayıtlar Yönetici Onayı Beklesin',
            'type' => 'bool',
            'default' => '0',
            'section' => 'user_system',
            'tooltip' => 'Açıkken yeni hesaplar pasif oluşturulur ve yönetici onayı olmadan giriş yapamaz.',
        ],
        'registration_pending_message' => [
            'label' => 'Onay Bekleyen Hesap Mesajı',
            'type' => 'textarea',
            'default' => 'Hesabınız oluşturuldu. Yönetici onayından sonra giriş yapabilirsiniz.',
            'section' => 'user_system',
            'tooltip' => 'Kayıt sonrası kullanıcıya gösterilecek bekleme mesajı.',
        ],
        'registration_suspicious_alert_enabled' => ['label' => 'Şüpheli Kayıt Bildirimi', 'type' => 'bool', 'default' => '1', 'section' => 'user_system', 'tooltip' => 'Kısa süre içinde aynı IP üzerinden gelen yoğun kayıt sinyallerinde yöneticilere bildirim gönderir.'],
        'registration_suspicious_window_minutes' => ['label' => 'İnceleme Penceresi (Dakika)', 'type' => 'number', 'default' => '15', 'section' => 'user_system', 'min' => 5, 'max' => 1440, 'tooltip' => 'Şüpheli kayıt kontrolü için taranacak zaman aralığı.'],
        'registration_suspicious_ip_threshold' => ['label' => 'IP Eşik Sayısı', 'type' => 'number', 'default' => '3', 'section' => 'user_system', 'min' => 2, 'max' => 100, 'tooltip' => 'Aynı IP adresinden gelen kayıt sayısı bu eşiği aşarsa alarm üretilir.'],
        'registration_suspicious_cooldown_minutes' => ['label' => 'Bildirim Soğuma Süresi (Dakika)', 'type' => 'number', 'default' => '60', 'section' => 'user_system', 'min' => 5, 'max' => 1440, 'tooltip' => 'Aynı tür alarmın tekrar gönderilmeden önce bekleyeceği süre.'],
        'login_identifier_mode'         => [
            'label' => 'Giris Kimligi',
            'type' => 'select',
            'default' => 'email',
            'section' => 'user_system',
            'options' => [
                'email' => 'Sadece E-posta',
                'username' => 'Sadece Kullanici Adi',
                'both' => 'E-posta veya Kullanici Adi',
            ],
            'tooltip' => 'Kullanici girisinde hangi kimlik bilgisinin kullanilacagini belirler.',
        ],
        'login_show_remember_session'   => ['label' => 'Oturumumu Hatirla Alanini Goster', 'type' => 'bool',   'default' => '1',     'section' => 'user_system', 'tooltip' => 'Giris formunda oturumu bu cihazda hatirla kutusunu gosterir.'],
        'login_remember_session_default'=> ['label' => 'Oturumu Hatirla Varsayilan',      'type' => 'bool',   'default' => '0',     'section' => 'user_system', 'tooltip' => 'Giris formunda oturum hatirlama kutusunu varsayilan olarak secili getirir.'],
        'password_min_length'           => ['label' => 'Minimum Şifre Uzunluğu',          'type' => 'number', 'default' => '8',     'section' => 'user_system', 'tooltip' => 'Kullanıcı şifrelerinin minimum karakter sayısı'],
        'password_require_uppercase'    => ['label' => 'Büyük Harf Gerekli',              'type' => 'bool',   'default' => '1',     'section' => 'user_system', 'tooltip' => 'Şifrelerde en az bir büyük harf zorunlu'],
        'password_require_numbers'      => ['label' => 'Sayı Gerekli',                    'type' => 'bool',   'default' => '1',     'section' => 'user_system', 'tooltip' => 'Şifrelerde en az bir sayı zorunlu'],
        'password_require_special'      => ['label' => 'Özel Karakter Gerekli',           'type' => 'bool',   'default' => '0',     'section' => 'user_system', 'tooltip' => 'Şifrelerde özel karakter zorunlu'],
        'password_expiry_days' => [
            'label' => 'Şifre Geçerlilik Süresi (Gün)',
            'type' => 'number',
            'default' => '90',
            'section' => 'user_system',
            'tooltip' => 'Kullanıcı şifrelerinin kaç gün sonra süresinin dolacağı. (0: Süresiz)'
        ],
        'session_timeout_minutes' => [
            'label' => 'Oturum Zaman Aşımı (Dakika)',
            'type' => 'number',
            'default' => '120',
            'section' => 'user_system',
            'tooltip' => 'Hareketsiz kalan oturumların kaç dakika sonra sonlanacağı.'
        ],
        'remember_session_timeout_minutes' => [
            'label' => 'Oturumu Acik Tut Suresi (Dakika)',
            'type' => 'number',
            'default' => '43200',
            'section' => 'user_system',
            'tooltip' => 'Oturumumu acik tut secildiginde kalici girisin kac dakika gecerli olacagi. 43200 = 30 gun.'
        ],
        'spam_blocked_usernames' => [
            'label' => 'Yasakli Kullanici Adlari',
            'type' => 'textarea',
            'default' => "admin\nroot\nsystem\nmoderator\nsupport\nofficial\nowner\ntest\ndemo\nguest\nstaff",
            'section' => 'user_system',
            'tooltip' => 'Virgul veya satir ile ayirarak kayit ve profil degisikliginde engellenecek kullanici adlarini girin.'
        ],
        'spam_blocked_username_fragments' => [
            'label' => 'Yasakli Kullanici Adi Parcalari',
            'type' => 'textarea',
            'default' => "admin\nmoderator\nsupport\nofficial\nsystem\nstaff\nowner",
            'section' => 'user_system',
            'tooltip' => 'Kullanici adinda gecmesi halinde engel olacak parcalari girin. Ornek: admin, moderator, support.'
        ],
        'spam_profanity_words' => [
            'label' => 'Kufur / Argo Kelimeler',
            'type' => 'textarea',
            'default' => "kufur\nargo\nhakaret\nsalak\naptal\ngerizekali\namk\norospu",
            'section' => 'user_system',
            'tooltip' => 'Kullanici adinda gecmesi halinde otomatik engellenecek kufur veya argo kelimeleri girin.'
        ],
        'spam_meaningless_words' => [
            'label' => 'Anlamsiz Kelimeler',
            'type' => 'textarea',
            'default' => "vv\naa\nss\nxx\nqq\nzz\naaaa\nbbbb\ncccc",
            'section' => 'user_system',
            'tooltip' => 'Gibberish, rastgele harf dizisi veya anlamsiz kelimeleri girin.'
        ],
        'spam_meaningless_patterns' => [
            'label' => 'Anlamsiz Desenler',
            'type' => 'textarea',
            'default' => "qwerty\nasdf\nzxcv\nqaz\nwsx\nedc\nrfv\ntgb\nyhn\nujm\n1234\n1111\n0000",
            'section' => 'user_system',
            'tooltip' => 'qwerty, asdf, zx gibi otomatik kalip veya desenleri girin.'
        ],
        'spam_blocked_email_domains' => [
            'label' => 'Yasakli E-posta Alan Adlari',
            'type' => 'textarea',
            'default' => '',
            'section' => 'user_system',
            'tooltip' => 'Kayit formunda engellenecek e-posta alan adlarini girin. Ornek: temppost.test, mailinator.com'
        ],
        'password_reset_token_ttl_minutes' => [
            'label' => 'Şifre Sıfırlama Bağlantısı Süresi (Dakika)',
            'type' => 'number',
            'default' => '60',
            'section' => 'user_system',
            'min' => 15,
            'max' => 1440,
            'tooltip' => 'Şifremi unuttum bağlantısının kaç dakika geçerli olacağını belirler.',
        ],

        // -- Konu Yönetimi -----------------------------------------
        'topic_min_title_length' => [
            'label' => 'Minimum Başlık Uzunluğu',
            'type' => 'number',
            'default' => '5',
            'section' => 'content_moderation',
            'group' => 'Kalite',
            'tooltip' => 'Yeni konu eklerken gereken minimum başlık karakter sayısı'
        ],
        'topic_require_excerpt' => [
            'label' => 'Kısa Açıklama Zorunlu',
            'type' => 'bool',
            'default' => '1',
            'section' => 'content_moderation',
            'group' => 'Kalite',
            'tooltip' => 'Yeni konu eklerken kısa açıklama alanının doldurulmasını zorunlu kılar'
        ],
        'topic_user_edit_requires_approval' => [
            'label' => 'Kullanıcı Düzenlemesi Onaya Düşsün',
            'type' => 'bool',
            'default' => '1',
            'section' => 'content_moderation',
            'group' => 'Akış',
            'tooltip' => 'Kullanıcı kendi konusunu düzenlediğinde değişikliğin yeniden moderasyon onayına düşmesini sağlar'
        ],
        'topic_health_scan_batch_size' => [
            'label' => 'Sağlık Taraması Parti Boyutu',
            'type' => 'number',
            'default' => '3',
            'section' => 'topic_management',
            'tooltip' => 'Konular sayfasındaki manuel sağlık taramasında tek istekte kontrol edilecek konu sayısını belirler'
        ],

        // -- Konu İçi Görünüm ------------------------------------
        'topic_detail_show_toolbar' => [
            'label' => 'Konu Üst Araç Çubuğu',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu başlığının altında tarih, sayaçlar ve hızlı aksiyonların yer aldığı araç çubuğunu gösterir'
        ],
        'topic_detail_show_info_panel' => [
            'label' => 'İçerik Bilgileri Paneli',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu sahibi, yayın tarihi, kategori ve benzeri bilgilerin yer aldığı paneli gösterir'
        ],
        'show_author_info' => [
            'label' => 'Yazar Bilgisi Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu içi bilgi panelinde içerik sahibini ve profil bağlantısını gösterir'
        ],
        'show_view_count' => [
            'label' => 'Görüntülenme Sayacı',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detayında görüntülenme bilgisinin gösterilip gösterilmeyeceğini belirler'
        ],
        'show_download_count' => [
            'label' => 'İndirme Sayacı',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detayındaki üst sayaçlarda toplam indirme bilgisini gösterir'
        ],
        'topic_detail_show_media' => [
            'label' => 'Medya Galerisini Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu içindeki resim, video ve medya galeri bölümünü gösterir'
        ],
        'topic_detail_show_download_panel' => [
            'label' => 'İndirme Panelini Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detayında indirme bağlantıları panelinin görünürlüğünü yönetir'
        ],
        'show_related_topics' => [
            'label' => 'Benzer Konuları Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detay sayfasında aynı kategoriden benzer içerikleri gösterir'
        ],
        'topic_detail_related_limit' => [
            'label' => 'Benzer Konu Limiti',
            'type' => 'number',
            'default' => '4',
            'section' => 'topic_detail',
            'tooltip' => 'Benzer konular bölümünde gösterilecek maksimum içerik sayısını belirler'
        ],
        'topic_detail_show_tags' => [
            'label' => 'Etiketleri Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detayındaki etiket listesinin görünürlüğünü yönetir'
        ],
        'topic_detail_comments_enabled' => [
            'label' => 'Konu Yorumlarını Göster',
            'type' => 'bool',
            'default' => '1',
            'section' => 'topic_detail',
            'tooltip' => 'Konu detay sayfasındaki yorum bölümünü tamamen gösterir veya gizler'
        ],

                // -- Performans -----------------------------------------
        'cache_enabled' => [
            'label' => 'Sistem Önbelleği Aktif',
            'type' => 'bool',
            'default' => '1',
            'section' => 'performance',
            'tooltip' => 'Veritabanı sorgularını ve yoğun sayfaları önbelleğe alır'
        ],
        'cache_ttl' => [
            'label' => 'Önbellek Süresi (Saniye)',
            'type' => 'number',
            'default' => '3600',
            'section' => 'performance',
            'tooltip' => 'Önbelleğin ne kadar süreyle geçerli olacağı'
        ],

        // -- Sosyal Özellikler ----------------------------------

                // -- İçerik Moderasyonu --------------------------------
        'auto_hide_reported' => [
            'label' => 'Raporlanan Konuları Gizle',
            'type' => 'bool',
            'default' => '1',
            'section' => 'moderation',
            'tooltip' => 'Rapor sayısı eşiği aştığında konuyu otomatik olarak gizler'
        ],
        'report_threshold' => [
            'label' => 'Gizleme Eşiği (Rapor Sayısı)',
            'type' => 'number',
            'default' => '5',
            'section' => 'moderation',
            'tooltip' => 'Bir konunun otomatik gizlenmesi için gereken rapor sayısı'
        ],

        // -- Analitik & İstatistikler ---------------------------

        // -- Cron Yönetimi ---------------------------------------
        'cron_enabled' => [
            'label' => 'Cron Görevleri Aktif',
            'type' => 'bool',
            'default' => '1',
            'section' => 'cron',
            'tooltip' => 'Tüm arka plan cron görevlerinin çalışmasına izin verir'
        ],
        'cron_php_binary' => [
            'label' => 'PHP CLI Yolu',
            'type' => 'string',
            'default' => '',
            'section' => 'cron',
            'tooltip' => 'Boş bırakılırsa otomatik olarak bulunan ilk uygun PHP CLI yolu kullanılır. Örn: /usr/bin/php8.2'
        ],
        'cron_secret_key' => [
            'label' => 'Cron Güvenlik Anahtarı (Secret)',
            'type' => 'string',
            'default' => 'turkmod_cron_123',
            'section' => 'cron',
            'tooltip' => 'Cron URL\'lerini dışarıdan tetiklemek için gereken güvenlik şifresi (?secret=...)'
        ],
        'cron_health_scan_interval' => [
            'label' => 'Konu Sağlık Taraması Sıklığı (Saat)',
            'type' => 'number',
            'default' => '24',
            'section' => 'cron',
            'tooltip' => 'Düzenli sağlık taramasının (link/resim kontrolü) saat cinsinden sıklığı'
        ],
        'cron_batch_size' => [
            'label' => 'Görev Başına İşlem Limiti (Batch Size)',
            'type' => 'number',
            'default' => '50',
            'section' => 'cron',
            'tooltip' => 'Cron her çalıştığında tek seferde maksimum kaç içeriğin işleneceği'
        ],
        'popup_announcement_enabled' => [
            'label' => 'Popup Duyuru Aktif',
            'type' => 'bool',
            'default' => '0',
            'section' => 'popup_announcement',
            'tooltip' => 'Girişte ziyaretçilere gösterilecek popup duyurusunu açar veya kapatır.'
        ],
        'popup_announcement_title' => [
            'label' => 'Duyuru Başlığı',
            'type' => 'string',
            'default' => 'Önemli Duyuru',
            'section' => 'popup_announcement',
            'tooltip' => 'Popup penceresinin üst kısmında görünecek başlık.'
        ],
        'popup_announcement_content' => [
            'label' => 'Duyuru İçeriği',
            'type' => 'richtext',
            'default' => '<p>Sitemize hoş geldiniz! Güncel paylaşımlarımıza göz atmayı unutmayın.</p>',
            'section' => 'popup_announcement',
            'tooltip' => 'Popup içerisinde gösterilecek duyuru metni.'
        ],
        'popup_announcement_type' => [
            'label' => 'Duyuru Türü / Teması',
            'type' => 'select',
            'options' => [
                'info' => 'Bilgi (Mavi)',
                'success' => 'Başarı / Hediye (Yeşil)',
                'warning' => 'Uyarı (Sarı / Turuncu)',
                'danger' => 'Önemli / Kritik (Kırmızı)'
            ],
            'default' => 'info',
            'section' => 'popup_announcement',
            'tooltip' => 'Popup penceresinin renk şemasını ve ikon stilini belirler.'
        ],
        'popup_announcement_strict' => [
            'label' => 'Katı Kapatma Modu',
            'type' => 'bool',
            'default' => '0',
            'section' => 'popup_announcement',
            'tooltip' => 'Eğer aktif edilirse, kullanıcı popup dışına tıklayarak pencereyi kapatamaz. Sadece kapatma butonuna tıklaması gerekir.'
        ],
        'popup_announcement_timer' => [
            'label' => 'Otomatik Kapanma Süresi (Saniye)',
            'type' => 'number',
            'default' => '0',
            'section' => 'popup_announcement',
            'tooltip' => 'Popup penceresinin kaç saniye sonra otomatik kapanacağını belirler. 0 girilirse otomatik kapanma devre dışı kalır.'
        ],
        'popup_announcement_button_text' => [
            'label' => 'Kapat Buton Metni',
            'type' => 'string',
            'default' => 'Kapat',
            'section' => 'popup_announcement',
            'tooltip' => 'Duyuruyu kapatmak için kullanılacak butonun metni.'
        ],
        'popup_announcement_action_text' => [
            'label' => 'Aksiyon Buton Metni (İsteğe Bağlı)',
            'type' => 'string',
            'default' => '',
            'section' => 'popup_announcement',
            'tooltip' => 'Yönlendirme için ek buton metni (boş bırakılırsa gösterilmez).'
        ],
        'popup_announcement_action_url' => [
            'label' => 'Aksiyon Buton Linki (İsteğe Bağlı)',
            'type' => 'string',
            'default' => '',
            'section' => 'popup_announcement',
            'tooltip' => 'Aksiyon butonuna tıklandığında gidilecek URL adresi (örn: https://site.com/sayfa).'
        ],
        'popup_announcement_target' => [
            'label' => 'Duyuru Hedef Kitlesi',
            'type' => 'select',
            'options' => [
                'all' => 'Hem Üyelere Hem Ziyaretçilere gösterilsin',
                'guests' => 'Sadece Ziyaretçilere Gösterilsin',
                'members' => 'Sadece Üyelere Gösterilsin'
            ],
            'default' => 'all',
            'section' => 'popup_announcement',
            'tooltip' => 'Duyurunun hangi kullanıcılara gösterileceğini belirler.'
        ],
        'popup_announcement_cookie_days' => [
            'label' => 'Duyuru Gösterim Sıklığı (Gün)',
            'type' => 'number',
            'default' => '1',
            'section' => 'popup_announcement',
            'tooltip' => 'Ziyaretçi duyuruyu kapattıktan sonra kaç gün boyunca tekrar görmesin? (0 girilirse her sayfa yenilemede tekrar gösterilir).'
        ],
    ];

    $definitions += [
        'account_email_system_enabled' => ['label' => 'Hesap E-postaları Aktif', 'type' => 'bool', 'default' => '1', 'section' => 'email'],
    ];

    $moduleRoot = dirname(__DIR__) . '/includes/src/Modules';
    foreach (glob($moduleRoot . '/*/module.php') ?: [] as $moduleFile) {
        $moduleMetadata = require $moduleFile;
        $moduleConfig = is_array($moduleMetadata) && isset($moduleMetadata['config']) && is_array($moduleMetadata['config'])
            ? $moduleMetadata['config']
            : [];

        foreach ($moduleConfig as $key => $definition) {
            if (!is_string($key) || !is_array($definition) || !isset($definition['section'])) {
                continue;
            }

            $definitions[$key] = $definition;
        }
    }

    foreach (\App\Engine\Email\AccountEmailService::catalog() as $templateKey => $template) {
        $prefix = 'account_email_' . $templateKey . '_';
        $definitions[$prefix . 'enabled'] = ['label' => $template['label'] . ' Aktif', 'type' => 'bool', 'default' => $template['enabled'], 'section' => 'email'];
        $definitions[$prefix . 'subject'] = ['label' => 'E-posta Konusu', 'type' => 'string', 'default' => $template['subject'], 'section' => 'email'];
        $definitions[$prefix . 'body'] = ['label' => 'HTML E-posta İçeriği', 'type' => 'text', 'default' => $template['body'], 'section' => 'email'];
    }

    return $cache = adminNormalizeSettingDefinitions($definitions);
}

/**
 * @param array<string, array<string, mixed>> $definitions
 * @return array<string, array<string, mixed>>
 */
function adminNormalizeSettingDefinitions(array $definitions): array
{
    foreach ($definitions as $key => $definition) {
        if (trim((string) ($definition['tooltip'] ?? '')) === '') {
            $definitions[$key]['tooltip'] = adminDefaultSettingTooltip((string) $key, $definition);
        }
    }

    return $definitions;
}

/**
 * @param array<string, mixed> $definition
 */
function adminDefaultSettingTooltip(string $key, array $definition): string
{
    $label = trim((string) ($definition['label'] ?? $key));
    $type = (string) ($definition['type'] ?? 'string');
    $section = (string) ($definition['section'] ?? 'general');
    $sectionNames = [
        'general' => 'genel site davranışını',
        'seo' => 'SEO ve arama motoru görünürlüğünü',
        'appearance' => 'public görünüm ayarlarını',
        'lay_header' => 'header görünümünü',
        'lay_footer' => 'footer görünümünü',
        'lay_sidebar' => 'sidebar düzenini',
        'lay_menu' => 'navigasyon menüsünü',
        'moderation' => 'moderasyon kurallarını',
        'comments' => 'yorum sistemini',
        'downloads' => 'indirme deneyimini',
        'file_manager' => 'dosya yöneticisi davranışını',
        'user_uploads' => 'kullanıcı mod yükleme akışını',
        'route_filters' => 'public URL rota davranışını',
        'topic_management' => 'konu yönetimi akışını',
        'topic_detail' => 'public konu içi görünümünü',
        'notifications' => 'bildirim merkezi davranışını',
        'toast_notifications' => 'toast bildirim görünümünü',
        'email' => 'e-posta gönderim ayarlarını',
        'user_system' => 'kullanıcı sistemi erişim ve şifre politikalarını',
        'rate_limit' => 'rate limit güvenlik kurallarını',
        'leaderboard' => 'liderlik tablosu davranışını',
        'user_system' => 'kullanıcı sistemi erişim ve şifre politikalarını',
        'performance' => 'performans ayarlarını',
        'social_features' => 'sosyal özellikleri',
        'content_moderation' => 'içerik moderasyonunu',
        'cron' => 'zamanlanmış görevleri',
        'popup_announcement' => 'giriş popup duyurusu sistemini',
    ];
    $sectionText = $sectionNames[$section] ?? 'ilgili sistem davranışını';

    if ($type === 'bool') {
        return "{$label} seçeneğini açar veya kapatır; {$sectionText} doğrudan etkiler.";
    }

    if ($type === 'number') {
        return "{$label} için kullanılacak sayısal limiti veya süre değerini belirler.";
    }

    if ($type === 'select') {
        return "{$label} için kullanılacak seçenek değerini belirler; değişiklik {$sectionText} etkiler.";
    }

    if ($type === 'text' || $type === 'textarea') {
        return "{$label} alanında kullanılacak çok satırlı metin veya yapılandırma içeriğini belirler.";
    }

    if ($type === 'color') {
        return "{$label} için kullanılacak tema rengini belirler; geçerli renk değeri girilmelidir.";
    }

    if ($type === 'multicheck' || $type === 'multiselect') {
        return "{$label} için aktif olacak seçenekleri belirler.";
    }

    return "{$label} değerini belirler; değişiklik {$sectionText} etkiler.";
}

/**
 * Render a single admin setting control with the shared admin UI contract.
 *
 * @param array<string, mixed> $definition
 * @param array<string, string> $settings
 */
function adminRenderSettingField(string $key, array $definition, array $settings, string $gridItemClass = ''): string
{
    $type = (string) ($definition['type'] ?? 'string');
    $label = (string) ($definition['label'] ?? $key);
    $tooltip = trim((string) ($definition['tooltip'] ?? ''));
    $value = (string) ($settings[$key] ?? ($definition['default'] ?? ''));
    $fieldId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) ?: $key;
    $isTextareaType = in_array($type, ['text', 'textarea', 'richtext'], true);
    $rows = max(2, (int) ($definition['rows'] ?? ($type === 'richtext' ? 6 : 3)));
    $placeholder = isset($definition['placeholder'])
        ? ' placeholder="' . htmlspecialchars((string) $definition['placeholder'], ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $autocomplete = isset($definition['autocomplete'])
        ? ' autocomplete="' . htmlspecialchars((string) $definition['autocomplete'], ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $inputmode = isset($definition['inputmode'])
        ? ' inputmode="' . htmlspecialchars((string) $definition['inputmode'], ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $step = isset($definition['step'])
        ? ' step="' . htmlspecialchars((string) $definition['step'], ENT_QUOTES, 'UTF-8') . '"'
        : '';
    $enabledWhen = isset($definition['enabled_when']) && is_array($definition['enabled_when']) ? $definition['enabled_when'] : [];
    $enabledWhenKey = trim((string) ($enabledWhen['key'] ?? ''));
    $enabledWhenValue = (string) ($enabledWhen['value'] ?? '1');
    $isConditionallyDisabled = $enabledWhenKey !== ''
        && (string) ($settings[$enabledWhenKey] ?? '0') !== $enabledWhenValue;
    $isExplicitlyDisabled = !empty($definition['disabled']);
    $isDisabled = $isConditionallyDisabled || $isExplicitlyDisabled;
    $disabledHelp = trim((string) ($definition['disabled_help'] ?? ''));
    $isWideField = $isTextareaType || $type === 'multiselect' || !empty($definition['wide']);
    $classes = trim('admin-ui-field ' . $gridItemClass . ' ' . ($isWideField ? 'admin-field-wide' : '') . ($isDisabled ? ' is-conditionally-disabled' : ''));
    $conditionalAttributes = $enabledWhenKey !== ''
        ? ' data-setting-enabled-when="' . htmlspecialchars($enabledWhenKey, ENT_QUOTES, 'UTF-8') . '" data-setting-enabled-value="' . htmlspecialchars($enabledWhenValue, ENT_QUOTES, 'UTF-8') . '"'
        : '';

    $helpIcon = $tooltip !== ''
        ? ' <i class="bi bi-info-circle admin-help-icon" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '"></i>'
        : '';

    ob_start();
    ?>
    <div class="<?= htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') ?>" data-setting-field="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"<?= $conditionalAttributes ?>>
        <?php if ($type === 'bool'): ?>
            <label class="ui-admin-switch<?= $isDisabled ? ' ui-admin-switch-disabled' : '' ?>">
                <input type="checkbox" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" value="1" <?= (!$isDisabled && $value === '1') ? 'checked' : '' ?><?= $isDisabled ? ' disabled aria-disabled="true"' : '' ?>>
                <span class="ui-admin-switch-label">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?= $helpIcon ?>
                </span>
            </label>
        <?php elseif ($type === 'color'): ?>
            <?php
                $colorDefault = (string) ($definition['default'] ?? '#000000');
                $resolvedColor = preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : $colorDefault;
            ?>
            <label class="ui-admin-form-label admin-ui-field__label" for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?= $helpIcon ?></label>
            <div class="admin-color-field" data-color-field>
                <input id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" type="color" class="admin-color-input" value="<?= htmlspecialchars($resolvedColor, ENT_QUOTES, 'UTF-8') ?>" data-color-input>
                <div class="admin-color-meta">
                    <strong data-color-value><?= htmlspecialchars(strtoupper($resolvedColor), ENT_QUOTES, 'UTF-8') ?></strong>
                    <span>Mevcut renk<?= $value === '' ? ' (varsayılan)' : '' ?></span>
                </div>
                <span class="admin-color-default">Varsayılan: <?= htmlspecialchars(strtoupper($colorDefault), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php else: ?>
            <label class="ui-admin-form-label admin-ui-field__label" for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?><?= $helpIcon ?></label>
            <?php if ($isTextareaType): ?>
                <textarea id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" rows="<?= $rows ?>" class="ui-admin-form-control<?= ($type === 'richtext' || !empty($definition['rich'])) ? ' rich-editor' : '' ?>"<?= $placeholder . $autocomplete ?><?= $isDisabled ? ' readonly aria-disabled="true"' : '' ?>><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></textarea>
            <?php elseif ($type === 'select'): ?>
                <select id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" class="ui-admin-form-select"<?= $isDisabled ? ' disabled aria-disabled="true"' : '' ?>>
                    <?php foreach (($definition['options'] ?? []) as $optionValue => $optionLabel): ?>
                        <option value="<?= htmlspecialchars((string) $optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= $value === (string) $optionValue ? 'selected' : '' ?>><?= htmlspecialchars((string) $optionLabel, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'multicheck'): ?>
                <?php
                    $listValue = ($value !== '' || !empty($definition['allow_empty_selection']))
                        ? $value
                        : (string) ($definition['default'] ?? '');
                    $currentValues = adminSettingListValues($listValue, !empty($definition['normalize_list_values']));
                ?>
                <div class="admin-multicheck-group">
                    <?php foreach (($definition['options'] ?? []) as $optionValue => $optionLabel): ?>
                        <label class="ui-admin-switch">
                            <input type="checkbox" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>[]" value="<?= htmlspecialchars((string) $optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= (!$isDisabled && in_array((string) $optionValue, $currentValues, true)) ? 'checked' : '' ?><?= $isDisabled ? ' disabled aria-disabled="true"' : '' ?>>
                            <span class="ui-admin-switch-label"><?= htmlspecialchars((string) $optionLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php elseif ($type === 'multiselect'): ?>
                <?php
                    $listValue = ($value !== '' || !empty($definition['allow_empty_selection']))
                        ? $value
                        : (string) ($definition['default'] ?? '');
                    $currentValues = adminSettingListValues($listValue, !empty($definition['normalize_list_values']));
                    $selectSize = max(4, min(14, (int) ($definition['size'] ?? 8)));
                    $multiselectSearchable = !empty($definition['searchable']);
                    $multiselectSearchPlaceholder = (string) ($definition['search_placeholder'] ?? 'Ara...');
                    $multiselectMaxVisible = max(0, (int) ($definition['max_visible_options'] ?? 0));
                ?>
                <?php if ($multiselectSearchable): ?>
                    <div class="admin-searchable-multiselect" data-admin-searchable-multiselect<?= $multiselectMaxVisible > 0 ? ' data-admin-multiselect-max-visible="' . htmlspecialchars((string) $multiselectMaxVisible, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <input type="search" class="ui-admin-form-control admin-searchable-multiselect__search" placeholder="<?= htmlspecialchars($multiselectSearchPlaceholder, ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars($label . ' ara', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off" data-admin-multiselect-search<?= $isDisabled ? ' disabled aria-disabled="true"' : '' ?>>
                <?php endif; ?>
                        <select id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>[]" class="ui-admin-form-select admin-multiselect" multiple size="<?= $selectSize ?>"<?= $multiselectSearchable ? ' data-admin-multiselect-list' : '' ?><?= $isDisabled ? ' disabled aria-disabled="true"' : '' ?>>
                            <?php foreach (($definition['options'] ?? []) as $optionValue => $optionLabel): ?>
                                <option value="<?= htmlspecialchars((string) $optionValue, ENT_QUOTES, 'UTF-8') ?>" <?= in_array((string) $optionValue, $currentValues, true) ? 'selected' : '' ?>><?= htmlspecialchars((string) $optionLabel, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($definition['options'])): ?>
                            <small class="admin-setting-conditional-help">Listelenecek kayit bulunamadi.</small>
                        <?php endif; ?>
                <?php if ($multiselectSearchable): ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <input id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>" name="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" type="<?= $type === 'number' ? 'number' : 'text' ?>" class="ui-admin-form-control" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"<?= $placeholder . $autocomplete . $inputmode . $step ?><?= $type === 'number' && isset($definition['min']) ? ' min="' . htmlspecialchars((string) $definition['min'], ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= $type === 'number' && isset($definition['max']) ? ' max="' . htmlspecialchars((string) $definition['max'], ENT_QUOTES, 'UTF-8') . '"' : '' ?><?= $isDisabled ? ' readonly aria-disabled="true"' : '' ?>>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($isDisabled && $disabledHelp !== ''): ?>
            <small class="admin-setting-conditional-help" data-setting-conditional-help><?= htmlspecialchars($disabledHelp, ENT_QUOTES, 'UTF-8') ?></small>
        <?php endif; ?>
    </div>
    <?php
    return trim((string) ob_get_clean());
}

/**
 * @param array<string, array<string, mixed>> $definitions
 * @param array<string, string> $settings
 * @param array<int, string>|null $keys
 */
function adminRenderSettingsGrid(array $definitions, array $settings, string $section, ?array $keys = null, string $gridClass = 'admin-settings-grid ui-grid'): string
{
    $allowedKeys = $keys !== null ? array_fill_keys($keys, true) : null;
    $html = '<div class="' . htmlspecialchars($gridClass, ENT_QUOTES, 'UTF-8') . '">';

    foreach ($definitions as $key => $definition) {
        if ((string) ($definition['section'] ?? '') !== $section) {
            continue;
        }
        if ($allowedKeys !== null && !isset($allowedKeys[$key])) {
            continue;
        }
        $html .= adminRenderSettingField((string) $key, $definition, $settings);
    }

    return $html . '</div>';
}

/**
 * @param array<string, array<string, mixed>> $definitions
 * @return array<int, string>
 */
function adminSettingKeysForSection(array $definitions, string $section): array
{
    $keys = [];
    foreach ($definitions as $key => $definition) {
        if ((string) ($definition['section'] ?? '') === $section) {
            $keys[] = (string) $key;
        }
    }

    return $keys;
}

/**
 * @param array<string, array<string, mixed>> $definitions
 * @param array<string, array<string, mixed>> $groupMeta
 * @return array<int, array<string, mixed>>
 */
function adminBuildSettingGroupsFromDefinitions(array $definitions, string $section, array $groupMeta = [], string $fallbackTitle = 'Temel Ayarlar'): array
{
    $groups = [];

    foreach ($groupMeta as $groupName => $meta) {
        if (!is_array($meta)) {
            continue;
        }
        $title = is_string($groupName) ? $groupName : (string) ($meta['title'] ?? $fallbackTitle);
        $groups[$title] = array_merge([
            'title' => $title,
            'icon' => 'bi-sliders',
            'description' => '',
            'badge' => '',
            'keys' => [],
        ], $meta, ['title' => (string) ($meta['title'] ?? $title), 'keys' => []]);
    }

    foreach ($definitions as $key => $definition) {
        if ((string) ($definition['section'] ?? '') !== $section) {
            continue;
        }

        $groupName = trim((string) ($definition['group'] ?? ''));
        $groupName = $groupName !== '' ? $groupName : $fallbackTitle;

        if (!isset($groups[$groupName])) {
            $groups[$groupName] = [
                'title' => $groupName,
                'icon' => 'bi-sliders',
                'description' => '',
                'badge' => '',
                'keys' => [],
            ];
        }

        $groups[$groupName]['keys'][] = (string) $key;
    }

    return array_values(array_filter($groups, static function (array $group): bool {
        return !empty($group['keys']);
    }));
}

/**
 * Render grouped settings in the compact rule-card layout used by Advanced Settings.
 *
 * @param array<string, array<string, mixed>> $definitions
 * @param array<string, string> $settings
 * @param array<int|string, array<string, mixed>> $groups
 * @param array<string, string> $options
 */
function adminRenderSettingsRuleSections(array $definitions, array $settings, string $section, array $groups, array $options = []): string
{
    $listClass = trim((string) ($options['list_class'] ?? 'settings-rule-list'));
    $ruleClassBase = trim((string) ($options['rule_class'] ?? 'settings-rule'));
    $gridClassBase = trim((string) ($options['grid_class'] ?? 'settings-rule-grid ui-grid'));
    $showCountBadge = (bool) ($options['show_count_badge'] ?? true);
    $html = '<div class="' . htmlspecialchars($listClass, ENT_QUOTES, 'UTF-8') . '">';

    foreach ($groups as $groupId => $group) {
        if (!is_array($group)) {
            continue;
        }

        $keys = array_values(array_filter(
            array_map(static fn($key): string => (string) $key, (array) ($group['keys'] ?? [])),
            static fn(string $key): bool => isset($definitions[$key]) && (string) ($definitions[$key]['section'] ?? '') === $section
        ));
        if ($keys === []) {
            continue;
        }

        $title = (string) ($group['title'] ?? (is_string($groupId) ? $groupId : 'Ayarlar'));
        $description = trim((string) ($group['description'] ?? ''));
        $icon = trim((string) ($group['icon'] ?? 'bi-sliders'));
        $badge = trim((string) ($group['badge'] ?? ''));
        if ($badge === '' && $showCountBadge) {
            $badge = count($keys) . ' ayar';
        }

        $extraClass = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', (string) ($group['class'] ?? '')) ?? '';
        $layout = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($group['layout'] ?? '')) ?? '';
        $ruleClass = trim($ruleClassBase . ($extraClass !== '' ? ' ' . $extraClass : '') . ($layout !== '' ? ' ' . $ruleClassBase . '--' . $layout : ''));
        $gridModifierBase = strtok($gridClassBase, ' ') ?: $gridClassBase;
        $gridClass = trim($gridClassBase . ($layout !== '' ? ' ' . $gridModifierBase . '--' . $layout : ''));

        $html .= '<section class="' . htmlspecialchars($ruleClass, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="settings-rule-head">';
        $html .= '<div class="settings-rule-title-wrap">';
        $html .= '<span class="settings-rule-icon" aria-hidden="true"><i class="bi ' . htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') . '"></i></span>';
        $html .= '<div class="settings-rule-title-text">';
        $html .= '<div class="settings-rule-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
        if ($description !== '') {
            $html .= '<div class="settings-rule-desc">' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $html .= '</div></div>';
        if ($badge !== '') {
            $html .= '<span class="settings-rule-badge">' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '</span>';
        }
        $html .= '</div>';
        $html .= adminRenderSettingsGrid($definitions, $settings, $section, $keys, $gridClass);
        $html .= '</section>';
    }

    return $html . '</div>';
}

function adminRuntimeSchemaUpdatesAllowed(): bool
{
    return !function_exists('runtimeSchemaUpdatesAllowed') || runtimeSchemaUpdatesAllowed();
}

function adminContentModerationBlockedWords(array $settings): array
{
    $raw = (string) ($settings['content_moderation_blocked_words'] ?? '');
    if (trim($raw) === '') {
        return [];
    }

    $words = [];
    foreach (preg_split('/[\r\n,]+/u', $raw) ?: [] as $word) {
        $word = trim((string) $word);
        if ($word === '') {
            continue;
        }

        $key = function_exists('mb_strtolower') ? mb_strtolower($word, 'UTF-8') : strtolower($word);
        $words[$key] = $word;
    }

    return array_values($words);
}

function adminContentModerationTextMatches(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return false;
    }

    if (function_exists('mb_stripos')) {
        return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
    }

    return stripos($haystack, $needle) !== false;
}

function adminContentModerationMatches(array $settings, string $title, string $content): array
{
    if ((string) ($settings['content_moderation_enabled'] ?? '1') !== '1') {
        return [];
    }

    $words = adminContentModerationBlockedWords($settings);
    if ($words === []) {
        return [];
    }

    $haystack = html_entity_decode(strip_tags($title . ' ' . $content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $matched = [];
    foreach ($words as $word) {
        if (adminContentModerationTextMatches($haystack, (string) $word)) {
            $matched[] = (string) $word;
        }
    }

    return array_values(array_unique($matched));
}

function adminContentModerationDecision(array $settings, string $title, string $content): array
{
    $matches = adminContentModerationMatches($settings, $title, $content);
    if ($matches === []) {
        return [
            'matched' => false,
            'action' => 'none',
            'message' => '',
            'flags' => null,
        ];
    }

    $action = (string) ($settings['content_moderation_blocked_words_action'] ?? 'draft');
    if (!in_array($action, ['draft', 'reject', 'flag'], true)) {
        $action = 'draft';
    }

    $message = trim((string) ($settings['content_moderation_blocked_words_message'] ?? ''));
    if ($message === '') {
        $message = 'İçerikte izin verilmeyen kelime bulundu. Lütfen metni düzenleyin.';
    }

    $note = trim((string) ($settings['content_moderation_flag_note'] ?? ''));
    if ($note === '') {
        $note = 'Otomatik içerik moderasyonu incelemesi gerekiyor.';
    }

    return [
        'matched' => true,
        'action' => $action,
        'message' => $message,
        'flags' => [
            'source' => 'content_moderation',
            'type' => 'blocked_words',
            'action' => $action,
            'words' => $matches,
            'note' => $note,
            'created_at' => date(DATE_ATOM),
        ],
    ];
}

function ensureAdminSchema(?PDO $pdo): void
{
    if (!$pdo) {
        return;
    }

    $requiredTables = [
        'admin_settings', 'categories', 'topics', 'comments', 'users', 'media_files',
        'topic_download_links', 'topic_revisions', 'request_rate_limits', 'application_logs',
    ];
    $missing = [];
    foreach ($requiredTables as $table) {
        if (!adminTableExists($pdo, $table)) {
            $missing[] = $table;
        }
    }

    $requiredColumns = [
        'categories' => ['parent_id', 'display_order', 'seo_title', 'seo_description', 'deleted_at'],
        'topics' => ['category_id', 'author_topic', 'topic_version', 'topic_descriptions', 'primary_media_file_id', 'moderation_flags'],
        'comments' => ['parent_id', 'user_id', 'topic_id'],
        'users' => ['username', 'status', 'remember_token', 'deleted_at'],
        'media_files' => ['topic_id', 'display_order', 'health_status'],
    ];
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            if (!adminColumnExists($pdo, $table, $column)) {
                $missing[] = $table . '.' . $column;
            }
        }
    }

    if ($missing !== []) {
        throw new RuntimeException('Database schema is incomplete: ' . implode(', ', $missing) . '; run Admin Panel > Database Synchronization.');
    }
}

function topicRevisionEnsureSchema(?PDO $pdo): void
{
    if ($pdo && !adminTableExists($pdo, 'topic_revisions')) {
        throw new RuntimeException('Missing topic_revisions; run Admin Panel > Database Synchronization.');
    }
}

function topicRevisionNextNumber(PDO $pdo, int $topicId): int
{
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(revision_number), 0) + 1 FROM topic_revisions WHERE topic_id = ?");
    $stmt->execute([$topicId]);
    return max(1, (int)$stmt->fetchColumn());
}

function topicRevisionCapture(PDO $pdo, int $topicId, ?int $actorUserId, string $reason = 'admin_update'): ?int
{
    $stmt = $pdo->prepare("SELECT id, category_id, title, slug, author_topic, topic_version, topic_descriptions, status, meta_title, meta_description, primary_media_file_id
                           FROM topics
                           WHERE id = ? AND deleted_at IS NULL
                           LIMIT 1");
    $stmt->execute([$topicId]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$topic) {
        return null;
    }

    $linksStmt = $pdo->prepare("SELECT id, name, url, download_count, display_order, created_at, updated_at
                                FROM topic_download_links
                                WHERE topic_id = ?
                                ORDER BY display_order, id");
    $linksStmt->execute([$topicId]);
    $links = $linksStmt->fetchAll(PDO::FETCH_ASSOC);

    $mediaStmt = $pdo->prepare("SELECT id, type, disk, path, original_name, mime_type, size, display_order, is_primary, created_at, updated_at
                                FROM media_files
                                WHERE topic_id = ?
                                ORDER BY is_primary DESC, display_order, id");
    $mediaStmt->execute([$topicId]);
    $media = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);

    $insert = $pdo->prepare("INSERT INTO topic_revisions
        (topic_id, actor_user_id, revision_number, reason, category_id, title, slug, author_topic, topic_version, topic_descriptions, status, meta_title, meta_description, primary_media_file_id, links_json, media_json, ip_address, user_agent, created_at)
        VALUES
        (:topic_id, :actor_user_id, :revision_number, :reason, :category_id, :title, :slug, :author_topic, :topic_version, :topic_descriptions, :status, :meta_title, :meta_description, :primary_media_file_id, :links_json, :media_json, :ip_address, :user_agent, NOW())");
    $insert->execute([
        'topic_id' => $topicId,
        'actor_user_id' => $actorUserId ?: null,
        'revision_number' => topicRevisionNextNumber($pdo, $topicId),
        'reason' => $reason,
        'category_id' => $topic['category_id'] ?? null,
        'title' => (string)$topic['title'],
        'slug' => (string)$topic['slug'],
        'author_topic' => $topic['author_topic'] ?? null,
        'topic_version' => $topic['topic_version'] ?? null,
        'topic_descriptions' => $topic['topic_descriptions'] ?? null,
        'status' => (string)$topic['status'],
        'meta_title' => $topic['meta_title'] ?? null,
        'meta_description' => $topic['meta_description'] ?? null,
        'primary_media_file_id' => $topic['primary_media_file_id'] ?? null,
        'links_json' => json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'media_json' => json_encode($media, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
    ]);

    $revisionId = (int)$pdo->lastInsertId();
    topicRevisionPrune($pdo, $topicId);
    return $revisionId;
}

function topicRevisionPrune(PDO $pdo, int $topicId, int $limit = 50): void
{
    $limit = max(1, $limit);
    $stmt = $pdo->prepare("SELECT id FROM topic_revisions WHERE topic_id = ? ORDER BY revision_number DESC, id DESC LIMIT 18446744073709551615 OFFSET " . (int) $limit);
    $stmt->execute([$topicId]);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$ids) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $delete = $pdo->prepare("DELETE FROM topic_revisions WHERE id IN ({$placeholders})");
    $delete->execute($ids);
}

function topicRevisionList(PDO $pdo, int $topicId): array
{
    $stmt = $pdo->prepare("SELECT tr.*, u.username AS actor_name, u.email AS actor_email
                           FROM topic_revisions tr
                           LEFT JOIN users u ON u.id = tr.actor_user_id
                           WHERE tr.topic_id = ?
                           ORDER BY tr.revision_number DESC, tr.id DESC");
    $stmt->execute([$topicId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function topicRevisionFind(PDO $pdo, int $revisionId): ?array
{
    $stmt = $pdo->prepare("SELECT tr.*, t.title AS current_title, u.username AS actor_name, u.email AS actor_email
                           FROM topic_revisions tr
                           LEFT JOIN topics t ON t.id = tr.topic_id
                           LEFT JOIN users u ON u.id = tr.actor_user_id
                           WHERE tr.id = ?
                           LIMIT 1");
    $stmt->execute([$revisionId]);
    $revision = $stmt->fetch(PDO::FETCH_ASSOC);
    return $revision ?: null;
}

function topicRevisionRestore(PDO $pdo, int $revisionId, ?int $actorUserId): int
{
    $revision = topicRevisionFind($pdo, $revisionId);
    if (!$revision) {
        throw new RuntimeException('Revizyon bulunamadi.');
    }

    $topicId = (int)$revision['topic_id'];
    topicRevisionCapture($pdo, $topicId, $actorUserId, 'before_restore');

    $primaryMediaId = null;
    if (!empty($revision['primary_media_file_id'])) {
        $mediaCheck = $pdo->prepare("SELECT id FROM media_files WHERE id = ? AND topic_id = ? LIMIT 1");
        $mediaCheck->execute([(int)$revision['primary_media_file_id'], $topicId]);
        $primaryMediaId = $mediaCheck->fetchColumn() ? (int)$revision['primary_media_file_id'] : null;
    }

    $stmt = $pdo->prepare("UPDATE topics
        SET category_id = :category_id,
            title = :title,
            slug = :slug,
            author_topic = :author_topic,
            topic_version = :topic_version,
            topic_descriptions = :topic_descriptions,
            status = :status,
            meta_title = :meta_title,
            meta_description = :meta_description,
            primary_media_file_id = :primary_media_file_id,
            updated_at = NOW(),
            published_at = CASE WHEN :status_published = 'published' THEN COALESCE(published_at, NOW()) ELSE published_at END
        WHERE id = :topic_id");
    $stmt->execute([
        'category_id' => $revision['category_id'],
        'title' => $revision['title'],
        'slug' => $revision['slug'],
        'author_topic' => $revision['author_topic'],
        'topic_version' => $revision['topic_version'],
        'topic_descriptions' => $revision['topic_descriptions'],
        'status' => $revision['status'],
        'status_published' => $revision['status'],
        'meta_title' => $revision['meta_title'],
        'meta_description' => $revision['meta_description'],
        'primary_media_file_id' => $primaryMediaId,
        'topic_id' => $topicId,
    ]);

    $links = json_decode((string)($revision['links_json'] ?? '[]'), true);
    if (is_array($links)) {
        $lines = [];
        foreach ($links as $link) {
            $url = trim((string)($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $name = trim((string)($link['name'] ?? 'Link'));
            $lines[] = ($name !== '' ? $name : 'Link') . '|' . $url;
        }
        syncTopicDownloadLinks($pdo, $topicId, implode("\n", $lines));
    }

    seoInvalidateSitemapCaches();

    return $topicId;
}

function adminTableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?");
    $stmt->execute([$table]);

    return (int)$stmt->fetchColumn() > 0;
}

function adminColumnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);

    return (int)$stmt->fetchColumn() > 0;
}

if (!function_exists('adminCronPhpBinaryNormalizeValue')) {
    function adminCronPhpBinaryNormalizeValue(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], '', $value));
        if ($value === '') {
            return '';
        }

        $normalized = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
        $autoValues = [
            'auto',
            'automatic',
            'default',
            'php',
            'otomatik',
            'varsayilan',
            'varsayılan',
            'system',
            'sistem',
        ];
        if (in_array($normalized, $autoValues, true)) {
            return '';
        }

        return $value;
    }
}

if (!function_exists('adminCronPhpBinaryIsAutoValue')) {
    function adminCronPhpBinaryIsAutoValue(string $value): bool
    {
        return adminCronPhpBinaryNormalizeValue($value) === '';
    }
}

if (!function_exists('adminCronPhpBinaryCandidates')) {
    function adminCronPhpBinaryCandidates(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $candidates = [];
        $seen = [];
        $addCandidate = static function ($path) use (&$candidates, &$seen): void {
            $path = trim(str_replace(["\r", "\n"], '', (string) $path));
            if ($path === '') {
                return;
            }

            $basename = strtolower(basename($path));
            if (!preg_match('/^php(?:[0-9.]+)?(?:-cli)?(?:\.exe)?$/i', $basename)) {
                return;
            }

            if (
                stripos($basename, 'cgi') !== false
                || stripos($basename, 'fpm') !== false
                || stripos($basename, 'dbg') !== false
                || stripos($basename, 'config') !== false
                || stripos($basename, 'ize') !== false
            ) {
                return;
            }

            // PATH entries can point at binaries outside open_basedir; probe quietly.
            $resolved = @realpath($path);
            if (is_string($resolved) && $resolved !== '') {
                $path = $resolved;
            }

            $normalized = strtolower(str_replace('\\', '/', $path));
            if (isset($seen[$normalized])) {
                return;
            }

            if (!@is_file($path)) {
                return;
            }

            if (function_exists('is_executable') && !@is_executable($path)) {
                return;
            }

            $seen[$normalized] = true;
            $candidates[] = $path;
        };

        $patterns = [];
        if (defined('PHP_BINARY')) {
            $addCandidate(PHP_BINARY);
        }
        if (defined('PHP_BINDIR')) {
            $bindir = rtrim(str_replace('\\', '/', (string) PHP_BINDIR), '/');
            if ($bindir !== '') {
                $patterns[] = $bindir . '/php*';
            }
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $patterns = array_merge([
                'C:/xampp/php/php*.exe',
                'C:/php/php*.exe',
                'C:/Program Files/PHP/php*.exe',
                'C:/Program Files (x86)/PHP/php*.exe',
            ], $patterns);
        } else {
            $patterns = array_merge([
                '/usr/bin/php*',
                '/usr/local/bin/php*',
                '/opt/cpanel/ea-php*/root/usr/bin/php*',
                '/opt/plesk/php/*/bin/php*',
            ], $patterns);
        }

        foreach ($patterns as $pattern) {
            foreach (glob($pattern, GLOB_NOSORT) ?: [] as $candidate) {
                $addCandidate($candidate);
            }
        }

        $cache = array_values($candidates);
        return $cache;
    }
}

function adminNormalizeSettingValue(string $key, string $value, array $definition): string
{
    if ($key === 'site_language') {
        return 'tr';
    }

    if ($key === 'seo_public_page_presets_json' && function_exists('seoPublicPagePresetsJson')) {
        return seoPublicPagePresetsJson($value);
    }

    if ($key === 'sidebar_builder_config' && trim($value) !== '' && function_exists('sidebarBuilderSanitizeConfigJson')) {
        return sidebarBuilderSanitizeConfigJson($value);
    }

    if ($key === 'cron_php_binary') {
        return adminCronPhpBinaryNormalizeValue($value);
    }

    if (($definition['type'] ?? '') === 'select' && isset($definition['options']) && is_array($definition['options'])) {
        if (array_key_exists($value, $definition['options'])) {
            return $value;
        }

        return (string)($definition['default'] ?? array_key_first($definition['options']) ?? '');
    }

    if (($definition['type'] ?? '') === 'number') {
        $number = (int) $value;
        if (isset($definition['min'])) {
            $number = max((int) $definition['min'], $number);
        }
        if (isset($definition['max'])) {
            $number = min((int) $definition['max'], $number);
        }
        return (string) $number;
    }

    if ($key === 'download_exempt_scopes') {
        $allowedScopes = isset($definition['options']) && is_array($definition['options'])
            ? array_fill_keys(array_map('strval', array_keys($definition['options'])), true)
            : [];
        $scopes = array_values(array_filter(
            adminSettingListValues($value, false),
            static fn(string $scope): bool => isset($allowedScopes[$scope])
        ));

        return implode(',', $scopes);
    }

    if (in_array($key, ['comment_spam_exempt_usernames', 'comment_spam_exempt_groups', 'download_exempt_usernames', 'download_exempt_groups'], true)) {
        return implode(',', adminSettingListValues($value, true));
    }

    return $value;
}

function getAdminSettings(?PDO $pdo): array
{
    return \App\Core\AppSettings::instance($pdo)->all();
}

function adminSettingValue(?PDO $pdo, string $key, ?string $fallback = null): string
{
    $definitions = adminSettingDefinitions();
    $default = $fallback ?? (string)($definitions[$key]['default'] ?? '');

    if (!$pdo) {
        return $default;
    }

    $settings = getAdminSettings($pdo);
    return array_key_exists($key, $settings) ? (string)$settings[$key] : $default;
}

/**
 * Invalidate all layers of the settings cache (in-memory, APCu, file).
 * Call after saveAdminSettings or any programmatic settings change.
 */
function invalidateAdminSettingsCache(): void
{
    \App\Core\AppSettings::instance($GLOBALS['pdo'] ?? null)->invalidate();
}

function saveAdminSettings(?PDO $pdo, array $input): void
{
    if (!$pdo) {
        return;
    }

    $definitions = adminSettingDefinitions();
    $input['site_language'] = 'tr';
    if (isset($input['seo_public_pages']) && is_array($input['seo_public_pages']) && function_exists('seoPublicPagePresetsJson')) {
        $input['seo_public_page_presets_json'] = seoPublicPagePresetsJson($input['seo_public_pages']);
    } elseif (array_key_exists('seo_public_page_presets_json', $input) && function_exists('seoPublicPagePresetsJson')) {
        $input['seo_public_page_presets_json'] = seoPublicPagePresetsJson($input['seo_public_page_presets_json']);
    }
    $stmt = $pdo->prepare("INSERT INTO admin_settings (setting_key, setting_value, created_at, updated_at)
        VALUES (:key, :value, NOW(), NOW())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
    // Hangi section'lar bu formda var? _sections alanından veya _active_tab'dan belirle
    $allowedSections = [];
    if (!empty($input['_sections'])) {
        $allowedSections = array_map('trim', explode(',', (string)$input['_sections']));
    }

    $shouldValidateRateLimits = empty($allowedSections) || in_array('rate_limit', $allowedSections, true);
    if ($shouldValidateRateLimits) {
        $numberInput = static function (string $key) use ($input, $definitions): int {
            return max(0, (int)($input[$key] ?? ($definitions[$key]['default'] ?? 0)));
        };
        $pairedRateLimits = [
            'login_rate_limit' => 'login_rate_window',
            'register_rate_limit' => 'register_rate_window',
            'password_reset_rate_limit' => 'password_reset_rate_window',
            'search_rate_limit' => 'search_rate_window',
            'api_topics_rate_limit' => 'api_topics_rate_window',
            'api_messages_rate_limit' => 'api_messages_rate_window',
            'api_leaderboard_rate_limit' => 'api_leaderboard_rate_window',
            'api_analytics_rate_limit' => 'api_analytics_rate_window',
            'api_favorite_rate_limit' => 'api_favorite_rate_window',
            'api_reports_rate_limit' => 'api_reports_rate_window',
            'api_report_submit_rate_limit' => 'api_report_submit_rate_window',
            'api_user_reports_rate_limit' => 'api_user_reports_rate_window',
            'api_user_report_submit_rate_limit' => 'api_user_report_submit_rate_window',
            'download_count_rate_limit' => 'download_count_rate_window',
            'comment_rate_max' => 'comment_rate_minutes',
            'comment_mention_rate_max' => 'comment_mention_rate_window',
            'comment_edit_rate_max' => 'comment_edit_rate_window',
            'comment_reaction_rate_max' => 'comment_reaction_rate_window',
            'comment_report_rate_max' => 'comment_report_rate_window',
            'ban_appeal_message_limit' => 'ban_appeal_message_cooldown_minutes',
            'user_upload_rate_limit' => 'user_upload_rate_window',
        ];

        foreach ($pairedRateLimits as $limitKey => $windowKey) {
            if (!isset($definitions[$limitKey], $definitions[$windowKey])) {
                continue;
            }
            if ($numberInput((string)$limitKey) > 0 && $numberInput((string)$windowKey) <= 0) {
                $limitLabel = (string)($definitions[$limitKey]['label'] ?? $limitKey);
                $windowLabel = (string)($definitions[$windowKey]['label'] ?? $windowKey);
                throw new RuntimeException($limitLabel . ' için ' . $windowLabel . ' 1 veya daha yüksek olmalı.');
            }
        }
    }

    $routePrefixKeys = [
        'topic' => 'route_topic_prefix',
        'category' => 'route_category_prefix',
        'category_list' => 'route_category_list_prefix',
        'profile' => 'route_profile_prefix',
    ];
    $routePrefixValues = [];
    foreach ($routePrefixKeys as $type => $prefixKey) {
        $fallback = (string)($definitions[$prefixKey]['default'] ?? $type);
        $rawPrefix = trim((string)($input[$prefixKey] ?? $fallback));
        $sanitized = function_exists('routePrefixSanitize') ? routePrefixSanitize($rawPrefix) : slugify($rawPrefix);
        $routePrefixValues[$prefixKey] = $sanitized !== '' ? $sanitized : $fallback;
    }

    $coreRoutePrefixValues = [
        'route_topic_prefix' => $routePrefixValues['route_topic_prefix'] ?? '',
        'route_category_prefix' => $routePrefixValues['route_category_prefix'] ?? '',
        'route_profile_prefix' => $routePrefixValues['route_profile_prefix'] ?? '',
    ];
    if (count(array_unique($coreRoutePrefixValues)) !== count($coreRoutePrefixValues)) {
        foreach (['route_topic_prefix', 'route_category_prefix', 'route_profile_prefix'] as $prefixKey) {
            $routePrefixValues[$prefixKey] = (string)($definitions[$prefixKey]['default'] ?? '');
        }
    }

    if (in_array((string)($routePrefixValues['route_category_list_prefix'] ?? ''), [
        (string)($routePrefixValues['route_topic_prefix'] ?? ''),
        (string)($routePrefixValues['route_profile_prefix'] ?? ''),
    ], true)) {
        $routePrefixValues['route_category_list_prefix'] = (string)($definitions['route_category_list_prefix']['default'] ?? 'kategori');
    }

    $publicRoutePathKeys = function_exists('routePublicStaticPathSettingKeys')
        ? routePublicStaticPathSettingKeys()
        : [
            'login' => 'route_login_path',
            'register' => 'route_register_path',
            'logout' => 'route_logout_path',
            'forgot_password' => 'route_forgot_password_path',
            'reset_password' => 'route_reset_password_path',
            'notifications' => 'route_notifications_path',
            'messages' => 'route_messages_path',
            'leaderboard' => 'route_leaderboard_path',
            'ban_appeals' => 'route_ban_appeals_path',
            'contact' => 'route_contact_path',
            'upload_topic' => 'route_upload_topic_path',
            'edit_topic' => 'route_edit_topic_path',
            'download' => 'route_download_path',
            'events' => 'route_events_path',
        ];
    $publicRoutePathValues = [];
    foreach ($publicRoutePathKeys as $routeKey => $settingKey) {
        if (!isset($definitions[$settingKey])) {
            continue;
        }

        $fallback = (string)($definitions[$settingKey]['default'] ?? $routeKey);
        $rawPath = trim((string)($input[$settingKey] ?? $fallback));
        $sanitized = function_exists('routePublicStaticPathSanitize')
            ? routePublicStaticPathSanitize($rawPath)
            : slugify($rawPath);
        $publicRoutePathValues[$settingKey] = $sanitized !== '' ? $sanitized : $fallback;
    }

    $publicRouteBlocked = [];
    $reservedRouteSegments = function_exists('routePublicStaticReservedSegments')
        ? routePublicStaticReservedSegments()
        : ['admin', 'api', 'assets', 'database', 'docs', 'includes', 'tests', 'uploads', 'cron', 'install', 'route', 'index', 'health'];
    foreach ($reservedRouteSegments as $segment) {
        $segment = trim((string)$segment);
        if ($segment !== '') {
            $publicRouteBlocked[$segment] = true;
        }
    }
    foreach (['route_topic_prefix', 'route_category_prefix', 'route_category_list_prefix', 'route_profile_prefix'] as $prefixKey) {
        $segment = trim((string)($routePrefixValues[$prefixKey] ?? ''));
        if ($segment !== '') {
            $publicRouteBlocked[$segment] = true;
        }
    }

    foreach ($publicRoutePathKeys as $routeKey => $settingKey) {
        if (!isset($definitions[$settingKey])) {
            continue;
        }

        $fallback = (string)($definitions[$settingKey]['default'] ?? $routeKey);
        $candidate = trim((string)($publicRoutePathValues[$settingKey] ?? $fallback));
        if ($candidate === '' || isset($publicRouteBlocked[$candidate])) {
            $candidate = trim((string)$fallback);
        }
        if ($candidate === '' || isset($publicRouteBlocked[$candidate])) {
            $base = $candidate !== '' ? $candidate : slugify((string)$routeKey);
            if ($base === '') {
                $base = 'route';
            }
            $candidate = $base;
            $suffix = 2;
            while (isset($publicRouteBlocked[$candidate])) {
                $candidate = $base . '-' . $suffix;
                $suffix++;
            }
        }

        $publicRoutePathValues[$settingKey] = $candidate;
        $publicRouteBlocked[$candidate] = true;
    }

    $downloadAccessDurationUnit = strtolower(trim((string) ($input['download_access_grant_duration_unit'] ?? 'hours')));
    if (!in_array($downloadAccessDurationUnit, ['minutes', 'hours', 'days'], true)) {
        $downloadAccessDurationUnit = 'hours';
    }
    $downloadAccessDurationMaximum = function_exists('topicDownloadGrantDurationMaximum')
        ? topicDownloadGrantDurationMaximum($downloadAccessDurationUnit)
        : match ($downloadAccessDurationUnit) {
            'minutes' => 525600,
            'days' => 3650,
            default => 87600,
        };

    foreach ($definitions as $key => $definition) {
        $section = (string)($definition['section'] ?? '');

        // Eğer izin verilen section'lar belirtilmişse, sadece o section'daki ayarları kaydet
        if (!empty($allowedSections) && !in_array($section, $allowedSections, true)) {
            continue;
        }

        $type = (string)$definition['type'];
        if ($type === 'bool') {
            if (array_key_exists($key, $input)) {
                $rawBoolValue = $input[$key];
                if (is_array($rawBoolValue)) {
                    $rawBoolValue = reset($rawBoolValue);
                }
                $normalizedBool = strtolower(trim((string) $rawBoolValue));
                $value = in_array($normalizedBool, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
            } else {
                $value = '0';
            }
        } elseif ($type === 'multicheck' || $type === 'multiselect') {
            $value = isset($input[$key]) ? implode(',', adminSettingListValues($input[$key], !empty($definition['normalize_list_values']))) : '';
        } else {
            $value = trim((string)($input[$key] ?? $definition['default']));
        }

        if ($type === 'number') {
            $numericValue = (int) $value;
            $numericValue = max(0, $numericValue);
            $value = (string) $numericValue;
        }

        if (in_array($key, ['route_topic_prefix', 'route_category_prefix', 'route_category_list_prefix', 'route_profile_prefix'], true)) {
            $value = $routePrefixValues[$key] ?? (string)$definition['default'];
        }

        if (array_key_exists($key, $publicRoutePathValues)) {
            $value = (string)($publicRoutePathValues[$key] ?? $definition['default']);
        }

        if ($key === 'sidebar_builder_config' && function_exists('sidebarBuilderSanitizeConfigJson')) {
            $value = sidebarBuilderSanitizeConfigJson($value);
        }

        if (preg_match('/^account_email_[a-z0-9_]+_(subject|body)$/', $key) === 1) {
            if ($value === '') {
                throw new RuntimeException('Hesap e-posta konusu ve içeriği boş bırakılamaz.');
            }
            preg_match_all('/{{\s*([a-zA-Z0-9_]+)\s*}}/', $value, $matches);
            $unknownVariables = array_diff(array_unique($matches[1] ?? []), \App\Engine\Email\AccountEmailService::allowedVariables());
            if ($unknownVariables !== []) {
                throw new RuntimeException('Bilinmeyen hesap e-posta değişkeni: ' . implode(', ', $unknownVariables));
            }
        }

        $value = adminNormalizeSettingValue($key, $value, $definition);
        if ($key === 'download_access_grant_duration_value') {
            $value = (string) max(1, min($downloadAccessDurationMaximum, (int) $value));
        }

        $stmt->execute(['key' => $key, 'value' => $value]);
    }

    // Ayar cache'ini invalidate et
    invalidateAdminSettingsCache();
    try {
        // Yeni değerleri aynı kaydetme isteğinde yeniden derleyerek sonraki HTTP
        // isteğinin eski dosya/APCu içeriğine düşmesini engelle.
        getAdminSettings($pdo);
    } catch (Throwable $e) {
        if (function_exists('appLogException')) {
            appLogException($e, ['source' => 'saveAdminSettings.cache_warm']);
        } else {
            error_log('Admin settings cache warm failed: ' . $e->getMessage());
        }
    }
}

function getAdminCategories(?PDO $pdo, bool $activeOnly = false): array
{
    if (!$pdo) {
        return [
            ['id' => 1, 'parent_id' => null, 'name' => 'Genel', 'slug' => 'genel', 'description' => '', 'status' => 'active', 'display_order' => 0, 'seo_title' => '', 'seo_description' => '', 'topic_count' => 0],
        ];
    }

    ensureAdminSchema($pdo);

    try {
        $where = $activeOnly ? "WHERE cat.status = 'active' AND cat.deleted_at IS NULL" : "WHERE cat.deleted_at IS NULL";
        $stmt = $pdo->query("SELECT cat.*, COUNT(t.id) AS topic_count
            FROM categories cat
            LEFT JOIN topics t ON t.category_id = cat.id AND t.deleted_at IS NULL
            {$where}
            GROUP BY cat.id
            ORDER BY COALESCE(cat.parent_id, 0), cat.display_order, cat.name");
        $rows = $stmt->fetchAll();
        if (!empty($rows)) {
            return $rows;
        }
    } catch (Throwable $e) {
        return [];
    }

    return [];
}

function buildAdminCategoryTree(array $categories, ?int $parentId = null, int $depth = 0): array
{
    $branch = [];

    foreach ($categories as $category) {
        $categoryParent = $category['parent_id'] === null ? null : (int)$category['parent_id'];
        if ($categoryParent !== $parentId) {
            continue;
        }

        $category['depth'] = $depth;
        $branch[] = $category;
        array_push($branch, ...buildAdminCategoryTree($categories, (int)$category['id'], $depth + 1));
    }

    return $branch;
}

function getAdminCategoryOptions(?PDO $pdo): array
{
    $categories = getAdminCategories($pdo, true);
    if (empty($categories) && $pdo) {
        ensureDefaultCategory($pdo);
        $categories = getAdminCategories($pdo, true);
    }

    return buildAdminCategoryTree($categories);
}

function categoryHasTopics(?PDO $pdo, int $id): bool
{
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM topics WHERE category_id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn() > 0;
}




