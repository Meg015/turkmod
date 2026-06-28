<template id="tmCommentItemTemplate">
<div class="[[classes]]" data-comment-id="[[id]]" id="comment-[[id]]">
<div class="ui-comment-body ui-panel__body">
<div class="ui-comment-profile-card">
<div class="ui-comment-profile-avatar" data-hue="[[hue]]">[[avatar_html]]</div>
<div class="ui-comment-profile-info">
<div class="ui-comment-author-line"><a href="[[profile_url]]" class="ui-comment-author-link"><strong class="ui-comment-author">[[author]]</strong></a>[[author_badge_html]][[group_badge_html]]</div>
</div>
</div>
<div class="ui-comment-divider" aria-hidden="true"></div>
<div class="ui-comment-content-wrap">
<time class="ui-comment-time">[[time_ago]]</time>
[[quote_html]]
<div class="ui-comment-text comment-body ui-panel__body">[[body_html]]</div>
<div class="ui-comment-bottom-bar">
<div class="ui-comment-bottom-main">
<div class="ui-comment-actions-row">[[actions_html]]</div>
[[reactions_html]]
</div>
<div class="ui-comment-bottom-meta">[[edited_badge_html]]</div>
</div>
</div>
<div class="ui-comment-inline-reply-slot" data-for="[[id]]"></div>
</div>
</div>[[replies_html]]
</template>
