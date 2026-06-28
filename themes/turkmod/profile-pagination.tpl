    {if pagination_pages}
    <nav class="topic-pagination profile-tab-pagination" aria-label="Profil sayfalama">
        <ul>
            {loop pagination_pages}
            {if item.is_gap}
            <li class="disabled"><span>{item.label}</span></li>
            {else}
            <li class="{item.class}"><a href="{item.url}" aria-label="{item.aria_label}">{item.label}</a></li>
            {/if}
            {/loop}
        </ul>
    </nav>
    {/if}
