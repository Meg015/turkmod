<article class="ui-theme-topic-card topic-card ui-card" data-topic-url="{item.url}">
{if item.image}
<a class="ui-theme-topic-card__media ui-card__media" href="{item.url}" aria-label="{item.title}">
<img class="ui-theme-topic-card__image" src="{item.image}" alt="{item.image_alt}" width="800" height="450" loading="lazy" decoding="async">
<span class="ui-theme-topic-card__media-shade ui-card__media" aria-hidden="true"></span>
</a>
{/if}
<div class="ui-theme-topic-card__body ui-card__body">
<div class="ui-theme-topic-card__top ui-card__meta">
<a class="ui-theme-topic-card__category ui-card__badge" href="{item.category_url}"><i class="bi bi-grid-3x3-gap-fill" aria-hidden="true"></i><span>{item.category}</span></a>
<time class="ui-theme-topic-card__date"><i class="bi bi-calendar3" aria-hidden="true"></i><span>{item.date}</span></time>
</div>
<h2 class="ui-theme-topic-card__title ui-card__title"><a href="{item.url}">{item.title}</a></h2>
<p class="ui-theme-topic-card__excerpt ui-card__text">{item.excerpt}</p>
<div class="ui-theme-topic-card__footer ui-card__foot">
<div class="ui-theme-topic-card__meta ui-card__meta">
{if item.author_url}
<a class="ui-theme-topic-card__owner ui-card__meta" href="{item.author_url}" title="Konu Sahibi: {item.author}"><span class="ui-theme-topic-card__meta-icon ui-card__icon"><i class="bi bi-person" aria-hidden="true"></i></span><strong>{item.author}</strong></a>
{else}
<span class="ui-theme-topic-card__owner ui-card__meta" title="Konu Sahibi: {item.author}"><span class="ui-theme-topic-card__meta-icon ui-card__icon"><i class="bi bi-person" aria-hidden="true"></i></span><strong>{item.author}</strong></span>
{/if}
<span class="ui-theme-topic-card__metric ui-theme-topic-card__metric--views ui-card__meta" title="Goruntulenme"><span class="ui-theme-topic-card__meta-icon ui-card__icon"><i class="bi bi-eye" aria-hidden="true"></i></span><strong>{item.views}</strong></span>
<span class="ui-theme-topic-card__metric ui-theme-topic-card__metric--comments ui-card__meta" title="Yorum"><span class="ui-theme-topic-card__meta-icon ui-card__icon"><i class="bi bi-chat-left-text" aria-hidden="true"></i></span><strong>{item.comments_count}</strong></span>
</div>
<a class="ui-theme-topic-card__action ui-card__actions" href="{item.url}"><span>Incele</span><i class="bi bi-arrow-right" aria-hidden="true"></i></a>
</div>
</div>
</article>
