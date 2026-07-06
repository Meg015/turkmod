<article class="ui-theme-topic-card topic-card ui-card{if topic.image}{else} ui-theme-topic-card--no-image{/if}" data-topic-url="{topic.url}">
{if topic.image}
<a class="ui-theme-topic-card__media ui-card__media" href="{topic.url}" aria-label="{topic.title}">
<img class="ui-theme-topic-card__image" src="{topic.image}" alt="{topic.image_alt}" title="{topic.image_title}" width="800" height="450" loading="{topic.image_loading}" decoding="{topic.image_decoding}"{if topic.image_srcset} srcset="{topic.image_srcset}"{/if}{if topic.image_sizes} sizes="{topic.image_sizes}"{/if}{if topic.image_fetchpriority} fetchpriority="{topic.image_fetchpriority}"{/if}>
<span class="ui-theme-topic-card__media-shade ui-card__media" aria-hidden="true"></span>
</a>
{/if}
<div class="ui-theme-topic-card__body ui-card__body">
<div class="ui-theme-topic-card__top ui-card__meta">
<a class="ui-theme-topic-card__category ui-card__badge" href="{topic.category_url}"><i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i><span>{topic.category}</span></a>
<time class="ui-theme-topic-card__date"><i class="bi bi-calendar3" aria-hidden="true"></i><span>{topic.date}</span></time>
</div>
<h2 class="ui-theme-topic-card__title ui-card__title"><a href="{topic.url}">{topic.title}</a></h2>
<p class="ui-theme-topic-card__excerpt ui-card__text">{topic.excerpt}</p>
<div class="ui-theme-topic-card__footer ui-card__foot">
<div class="ui-theme-topic-card__meta ui-card__meta">
{if topic.author_url}
<a class="ui-theme-topic-card__owner ui-card__meta" href="{topic.author_url}" title="Konu Sahibi: {topic.author}"><span class="ui-theme-topic-card__meta-icon ui-card__icon"><i class="bi bi-person" aria-hidden="true"></i></span><strong>{topic.author}</strong></a>
{else}
<span class="ui-theme-topic-card__owner ui-card__meta" title="Konu Sahibi: {topic.author}"><span class="ui-theme-topic-card__meta-icon ui-card__icon"><i class="bi bi-person" aria-hidden="true"></i></span><strong>{topic.author}</strong></span>
{/if}
<span class="ui-theme-topic-card__metric ui-theme-topic-card__metric--views ui-card__meta" title="Görüntülenme"><span class="ui-theme-topic-card__meta-icon ui-card__icon"><i class="bi bi-eye" aria-hidden="true"></i></span><strong>{topic.views}</strong></span>
<span class="ui-theme-topic-card__metric ui-theme-topic-card__metric--comments ui-card__meta" title="Yorum"><span class="ui-theme-topic-card__meta-icon ui-card__icon"><i class="bi bi-chat-left-text" aria-hidden="true"></i></span><strong>{topic.comments_count}</strong></span>
</div>
<a class="ui-theme-topic-card__action ui-card__actions" href="{topic.url}"><span>İncele</span><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
</div>
</div>
</article>
