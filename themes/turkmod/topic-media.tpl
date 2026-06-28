<div class="ui-theme-topic-part ui-theme-topic-part--media">
<section class="topic-section topic-images-videos ui-section" aria-labelledby="media-heading">
<h2 id="media-heading">Resim ve Videolar</h2>
{if topic.has_media_slides}
<div class="topic-carousel" data-carousel-slides="{topic.media_slides_json}">
<div class="topic-carousel-main">
<button id="ui-comment-prev" class="topic-carousel-nav topic-carousel-nav-prev" type="button" aria-label="Onceki medya"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
<button id="ui-comment-next" class="topic-carousel-nav topic-carousel-nav-next" type="button" aria-label="Sonraki medya"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
<div id="ui-comment-content" class="topic-carousel-content ui-section"></div>
<div class="topic-carousel-counter" id="tcCounter" aria-live="polite">1 / {topic.media_slide_count}</div>
</div>
{if topic.has_media_thumbs}
<div class="topic-carousel-thumbs" aria-label="Galeri onizlemeleri">
{loop topic.media_slides}
<button type="button" class="ui-comment-thumb {item.active_class}" data-idx="{item.index}" aria-label="Galeri gorseli {item.number}" {item.current_attr}>
{if item.has_thumb}<img src="{item.thumb}" alt="" width="320" height="180" loading="lazy" decoding="async">{else}<i class="bi bi-play-circle-fill" aria-hidden="true"></i>{/if}
</button>
{/loop}
</div>
{/if}
</div>
{/if}
{if topic.has_media_links}
<div class="topic-other-links mt-3">
{loop topic.media_links}
<div class="topic-media-item topic-media-item-inline"><a href="{item.url}" target="_blank" rel="noopener" class="topic-media-link-inline"><i class="bi bi-link-45deg" aria-hidden="true"></i> {item.label}</a></div>
{/loop}
</div>
{/if}
{if topic.show_media_placeholder}
<div class="topic-media-grid ui-grid"><div class="topic-media-placeholder"><i class="bi bi-image" aria-hidden="true"></i></div><div class="topic-media-placeholder"><i class="bi bi-image" aria-hidden="true"></i></div><div class="topic-media-placeholder"><i class="bi bi-play-circle" aria-hidden="true"></i></div></div>
{/if}
</section>
</div>
