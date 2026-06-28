<section class="ui-theme-profile-template" data-page-template="profile" data-profile-public="{profile.is_public}" data-profile-private="{profile.is_private}" aria-label="{profile.name}">
{if profile.cover}
<div class="ui-theme-profile-template__cover" data-ui-style-url="--ui-theme-profile-cover:{profile.cover}" aria-hidden="true"></div>
{/if}
<div class="ui-theme-profile-template__meta" aria-hidden="true">
<span data-profile-name>{profile.name}</span>
<span data-profile-group>{profile.group}</span>
<span data-profile-summary>{profile.page_summary}</span>
</div>
{if profile.is_private}
{include "profile-private.tpl"}
{/if}
{if profile.is_public}
{include "profile-public.tpl"}
{/if}
{if profile.use_captured_content}
{raw:content}
{/if}
</section>
