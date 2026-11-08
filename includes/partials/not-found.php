<?php
declare(strict_types=1);
$notFoundHomeUrl = function_exists('routePublicStaticUrl') ? routePublicStaticUrl('home') : $baseUri . '/';
$notFoundExploreUrl = function_exists('categoryListUrl') ? categoryListUrl() : $baseUri . '/kategoriler';
?>
<section class="not-found-shell" aria-labelledby="not-found-title">
    <div class="not-found-card">
        <span class="not-found-eyebrow"><span aria-hidden="true"></span> YOLUN SONU, KEŞFİN BAŞI</span>
        <div class="not-found-visual" aria-hidden="true">
            <span>4</span><span class="not-found-zero"><i class="bi bi-folder-x"></i></span><span>4</span>
        </div>
        <div class="not-found-content">
            <span class="not-found-status">HATA 404</span>
            <h1 class="not-found-title" id="not-found-title"><?= htmlspecialchars($notFoundTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            <p class="not-found-desc"><?= htmlspecialchars($notFoundDescription, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="not-found-actions">
                <a href="<?= htmlspecialchars($notFoundHomeUrl, ENT_QUOTES, 'UTF-8') ?>" class="not-found-btn not-found-btn--primary"><i class="bi bi-house-door" aria-hidden="true"></i> Ana sayfaya dön</a>
                <a href="<?= htmlspecialchars($notFoundExploreUrl, ENT_QUOTES, 'UTF-8') ?>" class="not-found-btn not-found-btn--secondary">Modları keşfet <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
            </div>
        </div>
        <p class="not-found-footnote"><i class="bi bi-signpost-split" aria-hidden="true"></i> Bir sonraki favori modunuz sizi bekliyor.</p>
    </div>
</section>
