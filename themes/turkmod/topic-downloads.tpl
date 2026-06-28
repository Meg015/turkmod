<div class="ui-theme-topic-part ui-theme-topic-part--downloads">
<section class="topic-section topic-downloads topic-download-links ui-section" aria-labelledby="dl-heading">
<h2 id="dl-heading">İndirme Bağlantıları</h2>
<div class="topic-dl-trust" role="note"><i class="bi bi-shield-check" aria-hidden="true"></i><span>İndirme bağlantısı açılmadan önce kısa bir güvenlik beklemesi uygulanır.</span></div>
<div class="topic-dl-section ui-section" data-countdown-seconds="{topic.download_countdown}" data-wait-text="{topic.download_wait_text}" data-done-text="{topic.download_done_text}">
<div class="download-grid topic-dl-grid ui-grid">
{loop topic.download_links}
<a href="{item.href}" rel="noopener" class="download-card topic-dl-card ui-card">
<div class="download-icon topic-dl-icon"><i class="bi bi-cloud-arrow-down" aria-hidden="true"></i></div>
<div class="download-info topic-dl-info"><strong>{item.name}</strong><small>{item.host}</small>{if item.show_count}<span class="download-count topic-dl-count"><i class="bi bi-download" aria-hidden="true"></i> {item.count} indirme</span>{/if}</div>
<span class="download-btn topic-dl-button"><span class="topic-dl-spinner"></span><span class="topic-dl-action">{topic.download_ready_text}</span></span>
</a>
{/loop}
</div>
</div>
</section>
</div>
