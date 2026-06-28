<div class="card ui-panel">
<div class="card-header ui-panel__head"><h1 class="h4 mb-0">Sitede Ara</h1></div>
<div class="card-body ui-panel__body">
<form action="{base_url}/index.php" method="get" class="rounded position-relative search topic-nav-search" role="search">
<i class="bi bi-search" aria-hidden="true"></i>
<input type="search" name="q" value="{search_query}" placeholder="Mod, kategori veya konu ara" aria-label="Arama" autocomplete="off" data-search-autocomplete>
</form>
<p class="small text-secondary mt-3 mb-0">{result_count} sonuc bulundu.</p>
</div></div>
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
{include "navigation.tpl"}
