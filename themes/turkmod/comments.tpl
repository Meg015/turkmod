<div class="ui-theme-comments-area">
{include "comment-item.tpl"}
<section class="topic-section topic-comments ui-section" aria-labelledby="comments-heading" data-topic-id="{topic.id}" data-api="{topic.comments_api}" data-csrf="{topic.csrf_token}" data-logged-in="{topic.comments_logged_in}" data-user-name="{topic.current_user_name}" data-user-avatar="{topic.current_user_avatar}" data-avatar-fallback="{topic.avatar_fallback}" data-report-enabled="{topic.comment_report_enabled}" data-topic-author="{topic.author}" data-poll="{topic.comment_poll}">
<div class="ui-comment-header ui-comment-header--compact ui-panel__head">
<h2 id="comments-heading" class="ui-comment-header__title">Yorumlar <span class="ui-comment-count" id="tcCount">(0)</span></h2>
<div class="ui-comment-sort ui-comment-header__sort"><span class="ui-comment-sort-label">Sırala:</span><select class="ui-comment-sort-select" id="tcSort"><option value="asc">En Eski</option><option value="desc">En Yeni</option><option value="popular">Popüler</option><option value="liked">Beğenilenler</option><option value="disliked">Beğenilmeyenler</option></select></div>
</div>
{if topic.comments_logged_in_bool}
{include "comment-form.tpl"}
{else}
<div class="ui-comment-login-prompt">Yorum yapmak için <a href="{base_url}/giris">giriş yapın</a>.</div>
{/if}
<div class="ui-comment-list" id="tcList"><div class="ui-comment-loading" id="tcLoading"><div class="ui-comment-skeleton"><div class="ui-comment-skeleton-avatar"></div><div class="ui-comment-skeleton-body"><div class="ui-comment-skeleton-line ui-comment-skeleton-line--short"></div><div class="ui-comment-skeleton-line ui-comment-skeleton-line--full"></div><div class="ui-comment-skeleton-line ui-comment-skeleton-line--medium"></div></div></div></div></div>
<div class="ui-comment-load-more-wrap is-hidden" id="tcLoadMoreWrap"><button type="button" class="ui-comment-load-more-btn" id="tcLoadMore">Daha fazla yorum yükle</button></div>
<div class="ui-comment-pagination-info is-hidden" id="tcPaginationInfo"></div>
</section>
</div>
