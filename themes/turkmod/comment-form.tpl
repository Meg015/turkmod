<div class="ui-theme-comment-form-shell ui-section">
<div class="ui-comment-form-wrap" id="tcFormWrap">
<div class="ui-comment-form-avatar default-avatar">{if topic.current_user_avatar}<img src="{topic.current_user_avatar}" alt="{topic.current_user_name}" width="40" height="40" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="{topic.avatar_fallback}">{else}<img src="{topic.avatar_fallback}" alt="{topic.current_user_name}" width="40" height="40" loading="lazy" data-ui-avatar-img data-ui-avatar-fallback="{topic.avatar_fallback}">{/if}</div>
<div class="ui-comment-form-body ui-panel__body">
<textarea id="tcInput" class="ui-comment-textarea" placeholder="Düşüncelerini paylaş..." maxlength="{topic.comment_max_length}" rows="1"></textarea>
<div class="ui-comment-form-actions is-hidden" id="tcActions">
<span class="ui-comment-char-count"><span id="tcCharCount">0</span>/{topic.comment_max_length}</span>
<div class="ui-comment-form-btns"><button type="button" class="ui-comment-btn-cancel" id="tcCancel">İptal</button><button type="button" class="ui-comment-btn-submit" id="tcSubmit" disabled>Gönder</button></div>
</div>
</div>
</div>
</div>
