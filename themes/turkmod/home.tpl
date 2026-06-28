<div class="home vstack gap-4">
{if sort_options}
<section class="card card-body ui-theme-home-toolbar ui-panel ui-panel__body">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <span class="ui-theme-home-toolbar-label">Sıralama</span>
        <nav class="nav nav-pills gap-2 ui-theme-home-toolbar-nav" aria-label="Siralama">
            {loop sort_options}
            <a class="btn btn-sm btn-light{item.active_class} ui-theme-home-toolbar-btn" href="{item.url}">{item.label}</a>
            {/loop}
        </nav>
    </div>
    {if search_notice}<div class="alert alert-info mt-3 mb-0">{search_notice}</div>{/if}
</section>
{/if}
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
</div>
