<article class="ui-theme-topic-detail" data-topic-view-id="{topic.id}" data-topic-view-url="{base_url}/api/track-view.php">
<section class="ui-theme-topic-title-card">
<div class="ui-theme-topic-title-card__copy">
<h1>{topic.title}</h1>
</div>
</section>
<header class="ui-theme-topic-hero" aria-label="{topic.title}">
{if topic.image}<img class="ui-theme-topic-hero__image" src="{topic.image}" alt="{if topic.image_alt}{topic.image_alt}{else}{topic.title} kapak görseli{/if}" title="{topic.image_title}" width="1200" height="675" loading="eager" fetchpriority="high" decoding="async">{/if}
</header>
{if topic.show_toolbar}<section class="ui-theme-topic-toolbar" aria-label="Konu bilgileri ve islemler">
<div class="ui-theme-topic-toolbar__meta">
<span><i class="bi bi-calendar3" aria-hidden="true"></i>{topic.date}</span>
{if topic.show_view_count}<span><i class="bi bi-eye" aria-hidden="true"></i>{topic.views}</span>{/if}
{if topic.show_download_count}<span><i class="bi bi-download" aria-hidden="true"></i>{topic.downloads}</span>{/if}
<span><i class="bi bi-chat-left-text" aria-hidden="true"></i>{topic.comments_count}</span>
</div>
<div class="ui-theme-topic-toolbar__actions">
<button type="button" class="ui-theme-topic-action ui-theme-topic-action--report" data-report-modal-open title="Konuyu raporla">
<i class="bi bi-flag" aria-hidden="true"></i><span>Konuyu Raporla</span>
</button>
<button type="button" class="ui-theme-topic-action ttb-favorite-btn {if topic.is_favorited}is-active{/if}" data-favorite-topic-id="{topic.id}" title="Favorilere ekle">
<i class="bi {if topic.is_favorited}bi-heart-fill{else}bi-heart{/if}" aria-hidden="true"></i><span data-favorite-label>Favori</span><span class="ttb-favorite-count">{topic.favorites_count}</span>
</button>
{if topic.can_edit}<a class="ui-theme-topic-action ui-theme-topic-action--edit" href="{topic.edit_url}"><i class="bi bi-pencil-square" aria-hidden="true"></i><span>D&uuml;zenle</span></a>{/if}
</div>
</section>
{/if}

<div class="ui-theme-topic-stack">
{if topic.has_description}{include "topic-description.tpl"}{/if}
{if topic.has_media}{include "topic-media.tpl"}{/if}
{if topic.has_details}{include "topic-meta.tpl"}{/if}
{if content}<div class="ui-theme-topic-content ui-section">{raw:content}</div>{/if}
{include "topic-report.tpl"}
{if topic.has_tags}<section class="topic-section ui-theme-topic-tags ui-section" aria-label="Etiketler"><div class="tagcloud d-flex flex-wrap gap-2">{loop topic.tags}<a class="tag" href="{item.url}">{item.label}</a>{/loop}</div></section>{/if}
{if topic.has_downloads}<div class="ui-theme-topic-download">{include "topic-downloads.tpl"}</div>{/if}
{if topic.has_related_topics}<section class="topic-section ui-theme-topic-related ui-section" aria-labelledby="related-topics-heading"><h2 id="related-topics-heading">Benzer Konular</h2><div class="topic-grid topic-grid--list ui-grid" data-topic-list-container>{loop topic.related_topics}{include "topic-related-card.tpl"}{/loop}</div></section>{/if}
{if topic.comments_enabled}<div class="ui-theme-topic-comments-slot">{include "comments.tpl"}</div>{/if}
</div>
</article>
