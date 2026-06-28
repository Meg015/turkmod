{if pagination_items}
<nav class="topic-pagination paging" aria-label="Sayfalama"><ul>
{loop pagination_items}
{if item.is_gap}
<li class="pagination-gap"><span class="pagination-ellipsis" aria-hidden="true">{item.label}</span></li>
{else}
<li class="{item.class}"><a href="{item.url}" aria-label="{item.aria_label}">{item.label}</a></li>
{/if}
{/loop}
</ul></nav>
{/if}
