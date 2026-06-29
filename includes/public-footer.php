<?php
$__themeManager = $GLOBALS["themeManager"] ?? null;
if (class_exists(PublicThemeRenderer::class) && PublicThemeRenderer::isActive()) {
    PublicThemeRenderer::finish([
        "theme_manager" => $__themeManager,
        "base_uri" => $baseUri ?? "",
        "pdo" => $pdo ?? null,
        "settings" => $_lay ?? (function_exists("getAdminSettings") && isset($pdo) ? getAdminSettings($pdo) : []),
        "env_config" => $envConfig ?? [],
        "public_categories" => $publicCategories ?? [],
        "sidebar_items" => $sidebarItems ?? [],
    ]);
    return;
}

$__themeManager = $GLOBALS["themeManager"] ?? null;
?>
        </main>
        <?php
        $baseUri = $baseUri ?? "";
        $_fLay =
            $_lay ??
            (function_exists("getAdminSettings") && isset($pdo)
                ? getAdminSettings($pdo)
                : []);
        $_fLayout = $_fLay["footer_layout"] ?? "simple";
        $_fSiteName = trim((string) ($_fLay["site_name"] ?? ""));
        if ($_fSiteName === "") {
            $_fSiteName = trim((string) ($_fLay["header_brand_text"] ?? ""));
        }
        if ($_fSiteName === "") {
            $_fSiteName = "İçerik Topic";
        }
        $_fBrandSetting = trim((string) ($_fLay["footer_brand_text"] ?? ""));
        $_fBrand = ($_fBrandSetting !== "" && $_fBrandSetting !== "İçerik Topic") ? $_fBrandSetting : $_fSiteName;
        $_fDesc =
            $_fLay["footer_text"] ??
            $_fLay["footer_description"] ??
            "Topluluk dosyaları, güncellemeler, araçlar ve eklentiler.";
        $_fCopy = $_fLay["footer_copyright"] ?? "";
        $_fShowSoc = ($_fLay["footer_show_social"] ?? "1") === "1";
        $_fShowCats = ($_fLay["footer_show_categories"] ?? "0") === "1";
        $_fShowPages = ($_fLay["footer_show_pages"] ?? "0") === "1";
        $_fBg = $_fLay["footer_bg_color"] ?? "";
        $_fTxt = $_fLay["footer_text_color"] ?? "";
        $_fCustom = trim($_fLay["footer_custom_css"] ?? "");
        $_fLayout = in_array($_fLayout, ["simple", "columns", "centered"], true)
            ? $_fLayout
            : "simple";
        $_fBrandIcon = mb_substr(
            trim((string) ($_fLay["footer_brand_icon"] ?? ($_fLay["header_brand_icon"] ?? "M"))),
            0,
            2,
        ) ?: "M";
        $_fColumn1Enabled = ($_fLay["footer_column1_enabled"] ?? "1") === "1";
        $_fColumn2Enabled = ($_fLay["footer_column2_enabled"] ?? "1") === "1";
        $_fColumn3Enabled = ($_fLay["footer_column3_enabled"] ?? "1") === "1";
        $_fShowNewsletter = false;
        $_fShowMeta = ($_fLay["footer_show_meta"] ?? "1") === "1";
        $_fNewsletterTitle = trim((string) ($_fLay["footer_newsletter_title"] ?? "Güncel Kal"));
        $_fNewsletterText = trim((string) ($_fLay["footer_newsletter_text"] ?? "Yeni içeriklerden haberdar ol"));
        $_fNewsletterPlaceholder = trim((string) ($_fLay["footer_newsletter_placeholder"] ?? "E-posta adresin"));
        $_fNewsletterButtonIcon = trim((string) ($_fLay["footer_newsletter_button_icon"] ?? "bi-arrow-right"));
        $_fMetaLeftIcon = trim((string) ($_fLay["footer_meta_left_icon"] ?? "bi-shield-check"));
        $_fMetaLeftText = trim((string) ($_fLay["footer_meta_left_text"] ?? "Güvenli"));
        $_fMetaRightIcon = trim((string) ($_fLay["footer_meta_right_icon"] ?? "bi-heart-fill"));
        $_fMetaRightText = trim((string) ($_fLay["footer_meta_right_text"] ?? "Topluluk"));

        $footerSocials = [
            "social_facebook" => ["bi-facebook", "Facebook"],
            "social_twitter" => ["bi-twitter-x", "Twitter"],
            "social_instagram" => ["bi-instagram", "Instagram"],
            "social_youtube" => ["bi-youtube", "YouTube"],
            "social_discord" => ["bi-discord", "Discord"],
            "social_github" => ["bi-github", "GitHub"],
            "social_telegram" => ["bi-telegram", "Telegram"],
        ];
        $_fColumnLinks = [];
        foreach (
            array_filter(
                array_map(
                    "trim",
                    explode(
                        "\n",
                        (string) ($_fLay["footer_column2_links"] ?? ""),
                    ),
                ),
            )
            as $_fLinkLine
        ) {
            $_fLinkParts = array_map("trim", explode("|", $_fLinkLine, 2));
            if (($_fLinkParts[0] ?? "") !== "") {
                $_fColumnLinks[] = [$_fLinkParts[0], $_fLinkParts[1] ?? "#"];
            }
        }
        $_fDefaultColumnLinks = [
            ["Ana Sayfa", $baseUri . "/index.php"],
            ["Kategoriler", categoryListUrl()],
            ["Popüler", $baseUri . "/index.php?sort=popular"],
            ["Yeni", $baseUri . "/index.php?sort=newest"],
        ];
        $_fPageLinks = [
            ["Mod Yükle", routePublicStaticUrl('upload_topic')],
            ["Giriş", routePublicStaticUrl('login')],
            ["Kayıt Ol", routePublicStaticUrl('register')],
            ["Profil", $baseUri . "/profile.php"],
        ];
        if ($_fShowCats && !isset($publicCategories)) {
            $publicCategories = isset($pdo) ? getPublicCategories($pdo) : [];
        }
        $_fAuthCompact = function_exists('routeIsAuthPage')
            ? routeIsAuthPage((string) ($_SERVER["SCRIPT_NAME"] ?? ""))
            : in_array(basename((string) ($_SERVER["SCRIPT_NAME"] ?? "")), ["login.php", "register.php", "forgot-password.php", "reset-password.php", "giris", "kayit", "sifremi-unuttum", "sifre-sifirla"], true);
        ?>
        <footer class="footer<?= $_fAuthCompact ? " footer-auth-compact" : "" ?>" data-footer-layout="<?= htmlspecialchars($_fLayout) ?>" role="contentinfo">
            <div class="container footer-container footer-layout-<?= htmlspecialchars($_fLayout) ?> ui-container">
                <div class="footer-grid">
                    <div class="footer-brand">
                        <div class="footer-logo">
                            <span class="topic-brand-mark"><?= htmlspecialchars($_fBrandIcon) ?></span>
                            <span><?= htmlspecialchars($_fBrand) ?></span>
                        </div>
                        <p class="footer-tagline"><?= htmlspecialchars(
                            $_fDesc,
                        ) ?></p>
                        <?php if ($_fShowSoc): ?>
                            <div class="footer-social">
                                <?php foreach (
                                    $footerSocials
                                    as $sKey => [$sIcon, $sName]
                                ): ?>
                                    <?php $sUrl = trim($_fLay[$sKey] ?? ""); ?>
                                    <?php if ($sUrl === "") {
                                        continue;
                                    } ?>
                                    <a href="<?= htmlspecialchars(
                                        $sUrl,
                                    ) ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars(
    $sName,
) ?>" class="footer-social-link">
                                        <i class="bi <?= htmlspecialchars(
                                            $sIcon,
                                        ) ?>"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($_fColumn1Enabled): ?>
                    <div class="footer-column footer-column-custom footer-column-1">
                        <h4><?= htmlspecialchars((string) ($_fLay["footer_column1_title"] ?? "Hakkımızda")) ?></h4>
                        <?php if (trim((string) ($_fLay["footer_column1_content"] ?? "")) !== ""): ?>
                            <p class="footer-column-text"><?= nl2br(htmlspecialchars((string) $_fLay["footer_column1_content"])) ?></p>
                        <?php else: ?>
                            <p class="footer-column-text"><?= htmlspecialchars($_fDesc) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($_fColumn2Enabled): ?>
                    <div class="footer-column footer-column-2">
                        <h4><?= htmlspecialchars((string) ($_fLay["footer_column2_title"] ?? "Platform")) ?></h4>
                        <div class="footer-links">
                            <?php if ($_fShowCats): ?>
                                <?php foreach (array_slice($publicCategories ?? [], 0, 6) as $_fCat): ?>
                                    <a href="<?= categoryUrl((string) ($_fCat["slug"] ?? "")) ?>"><?= htmlspecialchars((string) ($_fCat["name"] ?? "Kategori")) ?></a>
                                <?php endforeach; ?>
                                <a href="<?= categoryListUrl() ?>">Tüm Kategoriler</a>
                            <?php else: ?>
                                <?php foreach (!empty($_fColumnLinks) ? $_fColumnLinks : $_fDefaultColumnLinks as [$_fLinkLabel, $_fLinkHref]): ?>
                                    <a href="<?= htmlspecialchars($_fLinkHref) ?>"><?= htmlspecialchars($_fLinkLabel) ?></a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($_fColumn3Enabled): ?>
                    <div class="footer-column footer-column-3">
                        <h4><?= htmlspecialchars((string) ($_fLay["footer_column3_title"] ?? "Topluluk")) ?></h4>
                        <?php if (trim((string) ($_fLay["footer_column3_content"] ?? "")) !== ""): ?>
                            <p class="footer-column-text"><?= nl2br(htmlspecialchars((string) $_fLay["footer_column3_content"])) ?></p>
                        <?php endif; ?>
                        <?php if ($_fShowPages): ?>
                        <div class="footer-links">
                            <?php foreach ($_fPageLinks as [$_fLinkLabel, $_fLinkHref]): ?>
                                <a href="<?= htmlspecialchars($_fLinkHref) ?>"><?= htmlspecialchars($_fLinkLabel) ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($_fShowNewsletter): ?>
                    <div class="footer-newsletter">
                        <h4><?= htmlspecialchars($_fNewsletterTitle !== "" ? $_fNewsletterTitle : "Güncel Kal") ?></h4>
                        <p><?= htmlspecialchars($_fNewsletterText !== "" ? $_fNewsletterText : "Yeni içeriklerden haberdar ol") ?></p>
                        <form class="newsletter-form" id="newsletterForm" novalidate>
                            <input type="email" name="newsletter_email" placeholder="<?= htmlspecialchars($_fNewsletterPlaceholder !== "" ? $_fNewsletterPlaceholder : "E-posta adresin") ?>" aria-label="Bülten e-posta adresi" autocomplete="email" required data-newsletter-email>
                            <button type="submit" aria-label="Bültene kaydol"><i class="bi <?= htmlspecialchars($_fNewsletterButtonIcon !== "" ? $_fNewsletterButtonIcon : "bi-arrow-right") ?>"></i></button>
                        </form>
                        <div class="newsletter-feedback" data-newsletter-feedback role="status" aria-live="polite"></div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="footer-bottom">
                    <p class="footer-copyright"><?= $_fCopy !== ""
                        ? htmlspecialchars($_fCopy)
                        : "(c) " .
                            date("Y") .
                            " " .
                            htmlspecialchars($_fSiteName) .
                            ". Tüm hakları saklıdır." ?></p>
                    <?php if ($_fShowMeta): ?>
                    <div class="footer-meta">
                        <?php if (!empty($_fLay["terms_url"])): ?>
                            <a href="<?= htmlspecialchars($_fLay["terms_url"]) ?>" class="footer-meta-link">Kullanım Koşulları</a>
                        <?php endif; ?>
                        <?php if (!empty($_fLay["privacy_url"])): ?>
                            <a href="<?= htmlspecialchars($_fLay["privacy_url"]) ?>" class="footer-meta-link">Gizlilik Politikası</a>
                        <?php endif; ?>
                        <?php if ($_fMetaLeftText !== ""): ?>
                            <span><i class="bi <?= htmlspecialchars($_fMetaLeftIcon !== "" ? $_fMetaLeftIcon : "bi-shield-check") ?>"></i> <?= htmlspecialchars($_fMetaLeftText) ?></span>
                        <?php endif; ?>
                        <?php if ($_fMetaRightText !== ""): ?>
                            <span><i class="bi <?= htmlspecialchars($_fMetaRightIcon !== "" ? $_fMetaRightIcon : "bi-heart-fill") ?>"></i> <?= htmlspecialchars($_fMetaRightText) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="topic-grid--list ui-grid" hidden aria-hidden="true"></div>
                <div class="feed-card--list" hidden aria-hidden="true"></div>
                <div class="topic-list-bottom-row topic-read-more topic-compat-markers" hidden aria-hidden="true">
                    <i class="bi bi-person"></i><i class="bi bi-calendar3"></i><i class="bi bi-eye"></i><span>Devamını Oku</span>
                </div>
            </div>
        </footer>
    </div>

    <?php
    $_tEnabled = ($_fLay["toast_enabled"] ?? "1") === "1";
    $_tDuration = (int) ($_fLay["toast_duration"] ?? 5000);
    $_tPos = $_fLay["toast_position"] ?? "bottom-right";
    $_tTheme = $_fLay["toast_theme"] ?? "default";
    $_tAnim = $_fLay["toast_animation"] ?? "slide";
    $_tProgress = ($_fLay["toast_progress_bar"] ?? "1") === "1" ? "true" : "false";
    $_tClose = ($_fLay["toast_close_button"] ?? "1") === "1" ? "true" : "false";
    $_tMax = (int) ($_fLay["toast_max_visible"] ?? 5);
    $_tStack = $_fLay["toast_stack_direction"] ?? "down";
    $_tClick = ($_fLay["toast_click_to_close"] ?? "1") === "1" ? "true" : "false";
    $_tPause = ($_fLay["toast_pause_on_hover"] ?? "1") === "1" ? "true" : "false";
    $_tDurSuccess = (int) ($_fLay["toast_duration_success"] ?? 0);
    $_tDurError = (int) ($_fLay["toast_duration_error"] ?? 0);
    $_tDurWarning = (int) ($_fLay["toast_duration_warning"] ?? 0);

    $fSuccess = $successMsg ?? ($_SESSION["_flash_success"] ?? "");
    $fError = $errorMsg ?? ($_SESSION["_flash_error"] ?? "");
    $fInfo = $infoMsg ?? ($_SESSION["_flash_info"] ?? "");
    unset(
        $_SESSION["_flash_success"],
        $_SESSION["_flash_error"],
        $_SESSION["_flash_info"],
    );
    ?>

    <?php if ($_tEnabled): ?>
        <div class="topic-toast-container toast-pos-<?= htmlspecialchars($_tPos) ?> ui-panel__foot" id="toastContainer" aria-live="polite" aria-atomic="true"
             data-toast-duration="<?= $_tDuration ?>"
             data-toast-theme="<?= htmlspecialchars($_tTheme) ?>"
             data-toast-animation="<?= htmlspecialchars($_tAnim) ?>"
             data-toast-progress="<?= $_tProgress ?>"
             data-toast-close="<?= $_tClose ?>"
             data-toast-max="<?= $_tMax ?>"
             data-toast-stack="<?= htmlspecialchars($_tStack) ?>"
             data-toast-click-close="<?= $_tClick ?>"
             data-toast-pause-hover="<?= $_tPause ?>"
             data-toast-dur-success="<?= $_tDurSuccess ?>"
             data-toast-dur-error="<?= $_tDurError ?>"
             data-toast-dur-warning="<?= $_tDurWarning ?>"
             data-toast-success="<?= htmlspecialchars((string) $fSuccess) ?>"
             data-toast-error="<?= htmlspecialchars((string) $fError) ?>"
             data-toast-info="<?= htmlspecialchars((string) $fInfo) ?>"></div>
    <?php else: ?>
        <div class="topic-toast-container is-hidden ui-panel__foot" id="toastContainer"></div>
    <?php endif; ?>

    <?php if (function_exists('renderPopupAnnouncementHtml')): ?>
        <?= renderPopupAnnouncementHtml($pdo ?? null, $_fLay ?? null) ?>
    <?php endif; ?>

    <!-- Script dosyalari public-header.php'de yukleniyor, tekrar yuklenmiyor -->
</body>
</html>
