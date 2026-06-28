<?php

declare(strict_types=1);

require_once $projectRoot . '/includes/init.php';

$pageKey = 'download';
$downloadSettings = $adminSettingsGlobal ?? [];

function downloadSettingText(array $settings, string $key, string $fallback): string
{
    $value = trim((string) ($settings[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
}

/**
 * @return array{0:string,1:string}
 */
function downloadTimerTemplateParts(string $template): array
{
    $template = trim($template);
    if ($template === '') {
        $template = '{{seconds}} saniye içinde otomatik yönlendirileceksiniz.';
    }
    $template = str_replace(
        ['{{{{seconds}}}}', '{{{seconds}}}', '{{ seconds }}', '{{{}}}', '{{}}'],
        '{{seconds}}',
        $template
    );
    if (!str_contains($template, '{{seconds}}')) {
        $template = preg_replace('/\{+\s*seconds\s*\}+/u', '{{seconds}}', $template) ?? $template;
        $template = preg_replace('/\{+\s*\}+/u', '{{seconds}}', $template) ?? $template;
    }

    $parts = explode('{{seconds}}', $template, 2);
    if (count($parts) === 2) {
        return [$parts[0], $parts[1]];
    }

    return [$template . ' ', ''];
}

function downloadSafeTargetUrl(string $url): ?string
{
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = (string) (parse_url($url, PHP_URL_HOST) ?: '');

    if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
        return null;
    }

    return $url;
}

function downloadRedirectAfterCount(?PDO $pdo, int $linkId, string $targetUrl): void
{
    $countedLink = incrementTopicDownloadLink($pdo, $linkId);
    if ($countedLink && !empty($countedLink['url'])) {
        $safeCountedUrl = downloadSafeTargetUrl((string) $countedLink['url']);
        if ($safeCountedUrl !== null) {
            $targetUrl = $safeCountedUrl;
        }
    }

    header('Location: ' . $targetUrl, true, 302);
    exit;
}

try {
    $linkId = (int) ($_GET['id'] ?? 0);
    $link = null;
    if ($pdo && $linkId > 0) {
        $stmt = $pdo->prepare("SELECT l.id, l.topic_id, l.name, l.url, l.download_count,
                                      t.title AS topic_title, t.slug AS topic_slug
                               FROM topic_download_links l
                               INNER JOIN topics t ON t.id = l.topic_id
                               WHERE l.id = ?
                                 AND t.status = 'published'
                                 AND t.deleted_at IS NULL
                               LIMIT 1");
        $stmt->execute([$linkId]);
        $link = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$link || empty($link['url'])) {
        http_response_code(404);
        $pageTitle = 'İndirme Bağlantısı Bulunamadı';
        $download_has_alert = true;
        $download_alert_class = 'ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error';
        $download_alert_message = downloadSettingText($downloadSettings, 'download_redirect_missing_message', 'İndirme bağlantısı bulunamadı veya kaldırılmış.');
        require_once $projectRoot . '/includes/public-header.php';
        if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer()) {
            require_once $projectRoot . '/includes/public-footer.php';
            exit;
        }
        echo '<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert">' . htmlspecialchars($download_alert_message, ENT_QUOTES, 'UTF-8') . '</div>';
        require_once $projectRoot . '/includes/public-footer.php';
        exit;
    }

    $targetUrl = (string) $link['url'];
    $safeTargetUrl = downloadSafeTargetUrl($targetUrl);
    $scheme = parse_url($targetUrl, PHP_URL_SCHEME);
    $targetHost = (string) (parse_url($targetUrl, PHP_URL_HOST) ?: '');
    if ($safeTargetUrl === null) {
        http_response_code(400);
        $pageTitle = 'Geçersiz Bağlantı';
        $download_has_alert = true;
        $download_alert_class = 'ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error';
        $download_alert_message = downloadSettingText($downloadSettings, 'download_redirect_invalid_message', 'Geçersiz indirme bağlantısı.');
        require_once $projectRoot . '/includes/public-header.php';
        if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer()) {
            require_once $projectRoot . '/includes/public-footer.php';
            exit;
        }
        echo '<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert">' . htmlspecialchars($download_alert_message, ENT_QUOTES, 'UTF-8') . '</div>';
        require_once $projectRoot . '/includes/public-footer.php';
        exit;
    }

    $targetUrl = $safeTargetUrl;

    if (($_GET['confirm'] ?? '') !== '1') {
        if ((string) ($downloadSettings['download_redirect_page_enabled'] ?? '1') !== '1') {
            downloadRedirectAfterCount($pdo, $linkId, $targetUrl);
        }

        $pageTitle = downloadSettingText($downloadSettings, 'download_redirect_title', 'Dış indirme bağlantısı');
        $robotsMeta = 'noindex, nofollow';
        $bodyClass = trim(($bodyClass ?? '') . ' download-confirm-page');
        $linkName = trim((string) ($link['name'] ?? ''));
        $topicTitle = trim((string) ($link['topic_title'] ?? ''));
        $topicHref = topicUrl((string) ($link['topic_slug'] ?? ''), (int) ($link['topic_id'] ?? 0));
        $downloadActionBaseUrl = function_exists('routePublicStaticUrl')
            ? routePublicStaticUrl('download')
            : (($baseUri ?? '') . '/download.php');
        $confirmHref = $downloadActionBaseUrl . '?id=' . $linkId . '&confirm=1';
        $targetScheme = strtoupper((string) $scheme);
        $downloadCountdownSeconds = max(
            0,
            (int) ($downloadSettings['download_countdown_seconds'] ?? (defined('DOWNLOAD_COUNTDOWN_SECONDS') ? DOWNLOAD_COUNTDOWN_SECONDS : 5))
        );
        $downloadAutoRedirectEnabled = (string) ($downloadSettings['download_redirect_auto_enabled'] ?? '1') === '1';
        $downloadShowTargetUrl = (string) ($downloadSettings['download_redirect_show_target_url'] ?? '1') === '1';
        [$downloadTimerPrefix, $downloadTimerSuffix] = downloadTimerTemplateParts(
            downloadSettingText($downloadSettings, 'download_redirect_timer_template', '{{seconds}} saniye içinde otomatik yönlendirileceksiniz.')
        );

        $download_confirm = true;
        $download_confirm_href = $confirmHref;
        $download_topic_href = $topicHref;
        $download_target_host = $targetHost;
        $download_link_name = $linkName !== '' ? $linkName : downloadSettingText($downloadSettings, 'download_redirect_default_link_name', 'Harici kaynak');
        $download_topic_title = $topicTitle !== '' ? $topicTitle : downloadSettingText($downloadSettings, 'download_redirect_default_topic_title', 'Konu');
        $download_target_scheme = $targetScheme;
        $download_target_url = $targetUrl;
        $download_countdown_seconds = $downloadCountdownSeconds;
        $download_auto_redirect_enabled = $downloadAutoRedirectEnabled ? '1' : '0';
        $download_show_target_url = $downloadShowTargetUrl ? '1' : '0';
        $download_redirect_kicker = downloadSettingText($downloadSettings, 'download_redirect_kicker', 'Güvenli geçiş kontrolü');
        $download_redirect_title = $pageTitle;
        $download_redirect_intro = downloadSettingText($downloadSettings, 'download_redirect_intro', 'Dosya site dışında barındırılıyor. Devam etmeden önce hedef alan adını ve bağlantı bilgisini kontrol edin.');
        $download_redirect_host_label = downloadSettingText($downloadSettings, 'download_redirect_host_label', 'Hedef alan adı');
        $download_redirect_link_label = downloadSettingText($downloadSettings, 'download_redirect_link_label', 'Bağlantı');
        $download_redirect_topic_label = downloadSettingText($downloadSettings, 'download_redirect_topic_label', 'İçerik');
        $download_redirect_protocol_label = downloadSettingText($downloadSettings, 'download_redirect_protocol_label', 'Protokol');
        $download_redirect_safety_domain_text = downloadSettingText($downloadSettings, 'download_redirect_safety_domain_text', 'Hedef domain açıkça gösteriliyor');
        $download_redirect_safety_count_text = downloadSettingText($downloadSettings, 'download_redirect_safety_count_text', 'İndirme sayacı yalnızca devam edince güncellenir');
        $download_redirect_safety_external_text = downloadSettingText($downloadSettings, 'download_redirect_safety_external_text', 'Dış sitenin güvenliği size ait kontroldedir');
        $download_redirect_note = downloadSettingText($downloadSettings, 'download_redirect_note', 'Devam ettiğinizde indirme sayacı güncellenecek ve yeni hedefe yönlendirileceksiniz.');
        $download_redirect_timer_prefix = $downloadTimerPrefix;
        $download_redirect_timer_suffix = $downloadTimerSuffix;
        $download_redirect_primary_label = downloadSettingText($downloadSettings, 'download_redirect_primary_label', 'Hedefe Git');
        $download_redirect_primary_countdown_label = downloadSettingText($downloadSettings, 'download_redirect_primary_countdown_label', 'Hedefe Git ({{seconds}})');
        $download_redirect_redirecting_label = downloadSettingText($downloadSettings, 'download_redirect_redirecting_label', 'Yönlendiriliyor...');
        $download_redirect_secondary_label = downloadSettingText($downloadSettings, 'download_redirect_secondary_label', 'Konuya Dön');

        require_once $projectRoot . '/includes/public-header.php';
        if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer()) {
            require_once $projectRoot . '/includes/public-footer.php';
            exit;
        }
        ?>
        <main class="download-confirm-wrap">
            <section class="download-confirm-card" aria-labelledby="download-confirm-title"
                     data-download-confirm
                     data-confirm-href="<?= htmlspecialchars($confirmHref, ENT_QUOTES, 'UTF-8') ?>"
                     data-auto-redirect-seconds="<?= $downloadCountdownSeconds ?>"
                     data-auto-redirect-enabled="<?= $download_auto_redirect_enabled ?>"
                     data-primary-label="<?= htmlspecialchars($download_redirect_primary_label, ENT_QUOTES, 'UTF-8') ?>"
                     data-primary-countdown-label="<?= htmlspecialchars($download_redirect_primary_countdown_label, ENT_QUOTES, 'UTF-8') ?>"
                     data-redirecting-label="<?= htmlspecialchars($download_redirect_redirecting_label, ENT_QUOTES, 'UTF-8') ?>">
                <div class="download-confirm-head">
                    <span class="download-confirm-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                    <div>
                        <span class="download-confirm-kicker"><?= htmlspecialchars($download_redirect_kicker, ENT_QUOTES, 'UTF-8') ?></span>
                        <h1 id="download-confirm-title"><?= htmlspecialchars($download_redirect_title, ENT_QUOTES, 'UTF-8') ?></h1>
                        <p><?= htmlspecialchars($download_redirect_intro, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <div class="download-confirm-body">
                    <div class="download-confirm-host">
                        <i class="bi bi-globe2" aria-hidden="true"></i>
                        <div>
                            <span><?= htmlspecialchars($download_redirect_host_label, ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= htmlspecialchars($targetHost, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    </div>
                    <div class="download-confirm-meta">
                        <div>
                            <span><?= htmlspecialchars($download_redirect_link_label, ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= htmlspecialchars($download_link_name, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div>
                            <span><?= htmlspecialchars($download_redirect_topic_label, ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= htmlspecialchars($download_topic_title, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div>
                            <span><?= htmlspecialchars($download_redirect_protocol_label, ENT_QUOTES, 'UTF-8') ?></span>
                            <strong><?= htmlspecialchars($targetScheme, ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                    </div>
                    <?php if ($download_show_target_url === '1'): ?>
                    <div class="download-confirm-url" title="<?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-link-45deg" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($targetUrl, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="download-confirm-safety-grid ui-grid">
                        <span><i class="bi bi-check-circle" aria-hidden="true"></i><span><?= htmlspecialchars($download_redirect_safety_domain_text, ENT_QUOTES, 'UTF-8') ?></span></span>
                        <span><i class="bi bi-check-circle" aria-hidden="true"></i><span><?= htmlspecialchars($download_redirect_safety_count_text, ENT_QUOTES, 'UTF-8') ?></span></span>
                        <span><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span><?= htmlspecialchars($download_redirect_safety_external_text, ENT_QUOTES, 'UTF-8') ?></span></span>
                    </div>
                    <p class="download-confirm-note"><?= htmlspecialchars($download_redirect_note, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($download_auto_redirect_enabled === '1'): ?>
                    <div class="download-confirm-timer" role="status" aria-live="polite">
                        <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($download_redirect_timer_prefix, ENT_QUOTES, 'UTF-8') ?><strong id="downloadConfirmCountdown"><?= $downloadCountdownSeconds ?></strong><?= htmlspecialchars($download_redirect_timer_suffix, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="download-confirm-actions">
                    <a class="ui-admin-btn ui-admin-btn-primary download-confirm-primary" href="<?= htmlspecialchars($confirmHref, ENT_QUOTES, 'UTF-8') ?>" rel="nofollow noopener" data-download-confirm-primary>
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <span data-download-confirm-primary-text><?= htmlspecialchars($download_redirect_primary_label, ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                    <a class="ui-admin-btn ui-admin-btn-secondary" href="<?= htmlspecialchars($topicHref, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> <?= htmlspecialchars($download_redirect_secondary_label, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </div>
            </section>
        </main>
        <script src="<?= asset_url('assets/js/download-confirm.js', $baseUri) ?>" defer></script>
        <?php
        require_once $projectRoot . '/includes/public-footer.php';
        exit;
    }

    downloadRedirectAfterCount($pdo, $linkId, $targetUrl);
} catch (Throwable $e) {
    http_response_code(500);
    $pageTitle = 'Hata';
    $download_has_alert = true;
    $download_alert_class = 'ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error';
    $download_alert_message = downloadSettingText($downloadSettings, 'download_redirect_error_message', 'İndirme işlemi sırasında bir hata oluştu.');
    require_once $projectRoot . '/includes/public-header.php';
    if (function_exists('usesPublicThemeRenderer') && usesPublicThemeRenderer()) {
        require_once $projectRoot . '/includes/public-footer.php';
        exit;
    }
    echo '<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error" role="alert">' . htmlspecialchars($download_alert_message, ENT_QUOTES, 'UTF-8') . '</div>';
    require_once $projectRoot . '/includes/public-footer.php';
    exit;
}
