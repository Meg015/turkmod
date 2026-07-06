<div class="container profile-container profile-page-shell profile-shell profile-public-shell ui-container ui-section">
<div class="topic-report-modal user-report-modal" id="userReportModal" role="dialog" aria-modal="true" aria-labelledby="user-report-heading" hidden aria-hidden="true">
<div class="topic-report-backdrop" data-user-report-modal-close data-ui-modal-close></div>
<div class="topic-report-dialog ui-panel">
<div class="topic-report-header ui-panel__head">
<h2 id="user-report-heading"><i class="bi bi-flag" aria-hidden="true"></i> Kullanıcıyı Şikayet Et</h2>
<button type="button" class="topic-report-close" data-user-report-modal-close data-ui-modal-close aria-label="Kapat"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</div>
{if profile.can_report}
<form class="user-report-form" action="{profile.report_endpoint}" method="post">
<input type="hidden" name="_token" value="{profile.csrf_token}">
<input type="hidden" name="action" value="create">
<input type="hidden" name="reported_user_id" value="{profile.id}">
<div class="topic-report-grid ui-grid">
<label>
<span>Neden</span>
<select name="reason" required>
{loop profile.report_reasons}
<option value="{item.value}">{item.label}</option>
{/loop}
</select>
</label>
<label>
<span>Detay</span>
<textarea name="details" rows="3" maxlength="1000" placeholder="Ek bilgi varsa yazın"></textarea>
</label>
</div>
<button type="submit" class="topic-report-submit"><i class="bi bi-send" aria-hidden="true"></i> Şikayet Gönder</button>
<div class="topic-report-feedback" aria-live="polite"></div>
</form>
{else}
<div class="topic-report-login">
<i class="bi bi-shield-exclamation" aria-hidden="true"></i>
<span>Kullanıcı şikayeti göndermek için giriş yapmalısınız.</span>
<a href="{base_url}/giris">Giriş yap</a>
</div>
{/if}
</div>
</div>

<div class="profile-two-column-layout ui-section">
<div class="profile-main-content ui-section">
<section class="profile-section profile-topics profile-public-topics ui-card ui-section" id="profile-topics">
<div class="profile-section-head ui-panel__head">
<div>
<span class="profile-section-kicker">Yayın arşivi</span>
<h2 class="profile-section-title"><i class="bi bi-file-earmark-text" aria-hidden="true"></i> Yayınlanan Konular</h2>
</div>
<span class="profile-count">{profile.topic_count_label}</span>
</div>
{if profile.page_summary}<div class="profile-page-summary">{profile.page_summary}</div>{/if}
{if profile.topics_hidden}
<div class="profile-empty ui-empty"><i class="bi bi-lock" aria-hidden="true"></i><p>Bu kullanıcı konularını gizlemiş.</p></div>
{else}
{if profile.topics_empty}
<div class="profile-empty ui-empty"><i class="bi bi-journal-x" aria-hidden="true"></i><p>Bu kullanıcı henüz yayınlanmış konu paylaşmadı.</p></div>
{else}
<div class="profile-topics-grid ui-grid">
{loop profile.topics}
<article class="profile-topic-card profile-public-topic-card ui-card">
{if item.image}<img class="profile-topic-card__image" src="{item.image}" alt="{if item.image_alt}{item.image_alt}{else}{item.title} kapak görseli{/if}" title="{item.image_title}" width="800" height="450" loading="lazy" decoding="async">{/if}
<div class="profile-topic-rank">{item.rank}</div>
<a href="{item.url}" class="profile-topic-link">
<h3 class="profile-topic-title">{item.title}</h3>
</a>
<div class="profile-topic-meta">
<span><i class="bi bi-eye" aria-hidden="true"></i> {item.views}</span>
<span><i class="bi bi-download" aria-hidden="true"></i> {item.downloads}</span>
<span><i class="bi bi-chat-dots" aria-hidden="true"></i> {item.comments}</span>
</div>
<div class="profile-topic-footer ui-panel__foot">
<span class="profile-topic-category"><i class="bi bi-folder2" aria-hidden="true"></i> {item.category}</span>
<a href="{item.url}" class="profile-topic-action" title="Konuya Git"><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
</div>
</article>
{/loop}
</div>
{if profile.pagination_groups}<div class="profile-paging">{loop profile.pagination_groups}{include "profile-pagination.tpl"}{/loop}</div>{/if}
{/if}
{/if}
</section>

{if profile.has_public_collections}
<section class="profile-section profile-collections profile-public-collections ui-card ui-section">
<div class="profile-section-head ui-panel__head">
<div>
<span class="profile-section-kicker">Seçili listeler</span>
<h2 class="profile-section-title"><i class="bi bi-bookmarks" aria-hidden="true"></i> Public Koleksiyonlar</h2>
</div>
</div>
<div class="profile-collection-grid ui-grid">
{loop profile.public_collections}
<article class="profile-collection-card ui-card">
<div class="profile-collection-head">
<strong>{item.name}</strong>
<span>{item.count}</span>
</div>
{if item.has_description}<p>{item.description}</p>{/if}
{if item.has_preview_topics}
<div class="profile-collection-topics">
{loop item.preview_topics}
<a href="{item.url}"><i class="bi bi-arrow-right-short" aria-hidden="true"></i><span>{item.title}</span></a>
{/loop}
</div>
{/if}
</article>
{/loop}
</div>
</section>
{/if}
</div>
{include "profile-sidebar.tpl"}
</div>
</div>
