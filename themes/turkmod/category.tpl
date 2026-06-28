<section class="ui-theme-category-shell ui-section">
<header class="ui-theme-category-hero">
<div class="ui-theme-category-hero__copy">
<span class="ui-theme-category-hero__kicker"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i> Kategoriler</span>
<h1>{category.name}</h1>
<p>{category.description}</p>
</div>
{if category.stats}
<div class="ui-theme-category-hero__stats" aria-label="Kategori ozeti">
{loop category.stats}
<span><strong>{item.value}</strong><small>{item.label}</small></span>
{/loop}
</div>
{/if}
</header>
<div class="ui-theme-category-content ui-section">
{if category_families}
<div class="ui-theme-category-directory category-overview">
<div class="ui-theme-category-parent-grid topic-all-categories-grid ui-grid">
{loop category_families}
<section class="ui-theme-category-family{if category_family.children}{else} ui-theme-category-family--empty{/if}">
<a href="{category_family.url}" class="ui-theme-category-family__head ui-panel__head">
<span class="ui-theme-category-family__icon"><i class="bi bi-folder2-open" aria-hidden="true"></i></span>
<span class="ui-theme-category-family__copy">
<span class="ui-theme-category-family__meta">{category_family.child_count} alt kategori</span>
<strong>{category_family.name}</strong>
<small>{category_family.description}</small>
</span>
<span class="ui-theme-category-family__total"><strong>{category_family.total}</strong><small>icerik</small></span>
<span class="ui-theme-category-family__arrow" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
</a>
{if category_family.children}
<div class="ui-theme-category-family__children" aria-label="{category_family.name} alt kategorileri">
{loop category_family.children}
<a href="{item.url}" class="ui-theme-category-child">
<span class="ui-theme-category-child__icon"><i class="bi bi-folder2" aria-hidden="true"></i></span>
<span class="ui-theme-category-child__copy"><strong>{item.name}</strong></span>
<span class="ui-theme-category-child__count">{item.total}</span>
</a>
{/loop}
</div>
{else}
<div class="ui-theme-category-family__empty ui-theme-category-family__empty--compact ui-empty">Alt kategori yok</div>
{/if}
</section>
{/loop}
</div>
</div>
{else}
{if topics}
<div class="topic-grid topic-grid--list ui-grid" data-contract='class="topic-grid ui-grid"' data-topic-list-container>
{loop topics}
{include "topic-card.tpl"}
{/loop}
</div>
{else}
{if empty_state}
{include "empty-state.tpl"}
{/if}
{/if}
{/if}
{include "navigation.tpl"}
</div>
</section>
