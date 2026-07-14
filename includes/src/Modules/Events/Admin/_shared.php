<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Support/helpers/asset-helpers.php';

if (!function_exists('eventsAdminStyles')) {
    function eventsAdminStyles(string $baseUri): void
    {
        static $printed = false;
        if ($printed) {
            return;
        }
        $printed = true;

        $GLOBALS['baseUri'] = $baseUri;
        $cssAssets = [
            'events.css',
            'admin-theme.css',
            'admin-responsive.css',
            'admin-accessibility.css',
            'admin-width.css',
            'admin-forms.css',
            'admin-modals.css',
            'admin-widgets.css',
            'admin-tables-enhanced.css',
            'admin-modals-enhanced.css',
            'admin-navigation.css',
            'admin-interactive.css',
            'admin-empty-states.css',
            'admin-toolbar-redesign.css',
            'admin-backgrounds-improved.css',
            'admin-console.css',
        ];
        foreach ($cssAssets as $asset) {
            echo '<link rel="stylesheet" href="' . eventsGetAssetUrl('/events/assets/css/' . $asset, 'css') . '">' . PHP_EOL;
        }
foreach (['admin-theme.js', 'admin-mobile.js', 'admin-ui.js'] as $asset) {
            echo '<script src="' . eventsGetAssetUrl('/events/assets/js/' . $asset, 'js') . '" defer></script>' . PHP_EOL;
        }
    }
}

if (!function_exists('eventsAdminTabs')) {
    function eventsAdminTabs(string $baseUri): void
    {
        $groups = [
            'Sistem' => [
                ['events.php', 'Dashboard', 'bi-speedometer2'],
                ['events-pending.php', 'İşlem Bekleyenler', 'bi-bell-fill'],
                ['events-settings.php', 'Ayarlar', 'bi-sliders2-vertical'],
            ],
            'Modüller' => [
                ['events-wheel.php', 'Çark', 'bi-arrow-clockwise'],
                ['events-raffles.php', 'Çekilişler', 'bi-ticket-perforated'],
                ['events-tasks.php', 'Görevler', 'bi-check2-square'],
                ['events-rewards.php', 'Ödüller', 'bi-gift'],
            ],
            'Raporlar' => [
                ['events-audit-log.php', 'Loglar', 'bi-journal-check'],
                ['events-stats.php', 'İstatistik', 'bi-graph-up'],
            ]
        ];

        $current = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

        $pendingCount = 0;
        $pendingDefaultTab = '';
        global $pdo;
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $pendingOverview = function_exists('eventsPendingOverview') ? eventsPendingOverview($pdo) : ['total' => 0, 'reward_count' => 0, 'raffle_count' => 0];
                $pendingCount = (int)($pendingOverview['total'] ?? 0);
                if ((int)($pendingOverview['reward_count'] ?? 0) > 0) {
                    $pendingDefaultTab = 'rewards';
                } elseif ((int)($pendingOverview['raffle_count'] ?? 0) > 0) {
                    $pendingDefaultTab = 'raffles';
                }
            } catch (Throwable $e) { error_log('[silent-catch] ' . $e->getMessage()); }
        }

        echo '<section class="ui-events-app-header ui-events-module-shell ui-events-admin-shell ui-events-admin-navigation ui-section ui-panel__head" data-ui-events-admin-component="navigation" aria-label="Etkinlik yönetimi modül navigasyonu">';
        echo '  <div class="ui-events-app-title"><i class="bi bi-controller"></i> Etkinlik Yönetimi</div>';
        echo '  <p class="ui-events-app-subtitle">Çark, çekiliş, görev, ödül ve aktivite puan akışlarını tek yerden yönetin.</p>';
        echo '  <div class="ui-events-app-nav ui-events-app-nav-minimal">';

        $groupIndex = 0;
        $groupCount = count($groups);
        foreach ($groups as $groupLabel => $items) {
            echo '<div class="ui-events-nav-group ui-events-admin-nav-group">';
            echo '<span class="ui-events-nav-section-label">' . htmlspecialchars((string)$groupLabel) . '</span>';
            foreach ($items as [$file, $label, $icon]) {
                $active = $current === $file ? ' is-active' : '';
                $ariaCurrent = $current === $file ? ' aria-current="page"' : '';
                $href = rtrim($baseUri, '/') . '/admin/' . $file;
                if ($file === 'events-pending.php' && $pendingDefaultTab !== '') {
                    $href .= '?tab=' . rawurlencode($pendingDefaultTab);
                }
                echo '<a class="ui-events-nav-link' . $active . '" href="' . htmlspecialchars($href) . '"' . $ariaCurrent . '>';
                echo '  <i class="bi ' . htmlspecialchars($icon) . '"></i> ' . htmlspecialchars($label);
                if ($file === 'events-pending.php' && $pendingCount > 0) {
                    echo '  <span class="ui-events-badge ui-events-badge-warning ui-events-nav-count">' . $pendingCount . '</span>';
                }
                echo '</a>';
            }
            echo '</div>';
            $groupIndex++;
            if ($groupIndex < $groupCount) {
                echo '<span class="ui-events-nav-separator" aria-hidden="true"></span>';
            }
        }

        echo '  </div>';
        echo '</section>';
    }
}

if (!function_exists('eventsAdminPageHero')) {
    function eventsAdminPageHero(string $title, string $description, string $icon, string $actionsHtml = '', string $metaHtml = ''): void
    {
        echo '<section class="ui-events-page-hero ui-events-admin-hero" data-ui-events-admin-component="hero">';
        echo '  <div class="ui-events-page-hero-main ui-events-admin-hero-main">';
        echo '    <span class="ui-events-page-hero-icon"><i class="bi ' . htmlspecialchars($icon) . '"></i></span>';
        echo '    <div class="ui-events-page-hero-text">';
        echo '      <span class="ui-events-page-kicker">Etkinlikler</span>';
        echo '      <h2>' . htmlspecialchars($title) . '</h2>';
        echo '      <p>' . htmlspecialchars($description) . '</p>';
        if ($metaHtml !== '') {
            echo '      <div class="ui-events-page-meta">' . $metaHtml . '</div>';
        }
        echo '    </div>';
        echo '  </div>';
        if ($actionsHtml !== '') {
            echo '  <div class="ui-events-page-hero-actions ui-events-admin-actions">' . $actionsHtml . '</div>';
        }
        echo '</section>';
    }
}

if (!function_exists('eventsAdminPanelHeader')) {
    function eventsAdminPanelHeader(string $icon, string $title, string $description = '', string $actionsHtml = ''): void
    {
        echo '<div class="card-header ui-events-panel-header ui-events-admin-panel-header ui-panel__head ui-panel" data-ui-events-admin-component="panel-header">';
        echo '  <div class="ui-events-panel-title ui-events-admin-panel-title ui-panel">';
        echo '    <span class="ui-events-panel-icon ui-panel"><i class="bi ' . htmlspecialchars($icon) . '"></i></span>';
        echo '    <div>';
        echo '      <h3>' . htmlspecialchars($title) . '</h3>';
        if ($description !== '') {
            echo '      <p>' . htmlspecialchars($description) . '</p>';
        }
        echo '    </div>';
        echo '  </div>';
        if ($actionsHtml !== '') {
            echo '  <div class="ui-events-panel-actions ui-events-admin-actions ui-panel">' . $actionsHtml . '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('eventsAdminSetupNotice')) {
    function eventsAdminSetupNotice(bool $ready): void
    {
        // Removed - setup notice completely removed
    }
}

if (!function_exists('eventsAdminErrorList')) {
    function eventsAdminErrorList(array $errors): void
    {
        if ($errors === []) {
            return;
        }
        echo '<div class="ui-events-error-list">';
        foreach ($errors as $error) {
            echo '<div>' . htmlspecialchars((string)$error) . '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('eventsAdminChecked')) {
    function eventsAdminChecked(mixed $value): string
    {
        return !empty($value) ? ' checked' : '';
    }
}

if (!function_exists('eventsAdminEmptyState')) {
    function eventsAdminEmptyState(string $icon, string $title, string $description, string $actionHtml = ''): void
    {
        echo '<div class="ui-events-empty-state ui-events-empty-standard ui-events-admin-empty-state ui-empty" data-ui-events-admin-component="empty-state">';
        echo '  <div class="ui-events-empty-icon">';
        echo '    <i class="bi ' . htmlspecialchars($icon) . '"></i>';
        echo '  </div>';
        echo '  <h3>' . htmlspecialchars($title) . '</h3>';
        echo '  <p>' . htmlspecialchars($description) . '</p>';
        if ($actionHtml !== '') {
            echo '  <div class="ui-events-empty-action">' . $actionHtml . '</div>';
        }
        echo '</div>';
    }
}

if (!function_exists('eventsAdminPrizeCodeSnippet')) {
    function eventsAdminPrizeCodeSnippet(?string $value, string $emptyLabel = '-'): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return '<span class="ui-events-prize-code ui-events-prize-code--empty">' . htmlspecialchars($emptyLabel, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        $length = mb_strlen($value, 'UTF-8');
        $escapedValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        if ($length <= 6 || preg_match('/^\d+(?:[.,]\d+)?$/', $value) === 1) {
            return '<code class="ui-events-prize-code ui-events-prize-code--plain">' . $escapedValue . '</code>';
        }

        $head = mb_substr($value, 0, min(3, $length), 'UTF-8');
        $tail = mb_substr($value, max(0, $length - 2), null, 'UTF-8');
        $masked = htmlspecialchars($head . str_repeat('•', min(8, max(3, $length - 5))) . $tail, ENT_QUOTES, 'UTF-8');

        return '<span class="ui-events-prize-code" data-ui-events-prize-code data-code-visible="0" data-code-value="' . $escapedValue . '" data-code-mask="' . $masked . '">'
            . '<code class="ui-events-prize-code__mask" data-ui-events-prize-code-mask>' . $masked . '</code>'
            . '<span class="ui-events-prize-code__actions">'
            . '<button type="button" class="ui-events-prize-code__btn" data-ui-events-prize-code-toggle aria-pressed="false">Göster</button>'
            . '<button type="button" class="ui-events-prize-code__btn" data-ui-events-prize-code-copy>Kopyala</button>'
            . '</span>'
            . '</span>';
    }
}

if (!function_exists('eventsAdminModalShellStart')) {
    function eventsAdminModalShellStart(string $ariaLabel, array $attributes = []): void
    {
        $classes = [
            'ui-events-rule-detail-panel',
            'ui-events-admin-detail-panel',
            'ui-events-admin-modal-panel',
            'ui-events-admin-detail-overlay',
            'ui-panel',
        ];

        $attributes = array_merge([
            'data-ui-events-admin-modal' => null,
        ], $attributes);

        $attributeHtml = '';
        foreach ($attributes as $name => $value) {
            if ($value === false) {
                continue;
            }

            $escapedName = htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8');
            if ($value === null || $value === true) {
                $attributeHtml .= ' ' . $escapedName;
                continue;
            }

            $attributeHtml .= ' ' . $escapedName . '="' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '"';
        }

        echo '<div class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') . '" role="dialog" aria-modal="true" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '"' . $attributeHtml . ' hidden>';
    }
}

if (!function_exists('eventsAdminModalShellEnd')) {
    function eventsAdminModalShellEnd(): void
    {
        echo '</div>';
    }
}
