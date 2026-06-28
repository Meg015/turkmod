    <div class="container public-breadcrumb breadcrumb-container crumb-wrap ui-container">
        <nav class="breadcrumb crumb" aria-label="Sayfa yolu">
            {loop breadcrumb_items}
            {if item.url}
            <a class="crumb-link" href="{item.url}">{item.label}</a>
            {else}
            <span class="crumb-link">{item.label}</span>
            {/if}
            {if !item.is_last}<span class="crumb-separator" aria-hidden="true">&gt;</span>{/if}
            {/loop}
        </nav>
    </div>
