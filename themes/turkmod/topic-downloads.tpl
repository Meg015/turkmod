<div class="ui-theme-topic-part ui-theme-topic-part--downloads">
<section class="topic-section topic-downloads topic-download-links ui-section" aria-labelledby="dl-heading">
<h2 id="dl-heading">Indirme Baglantilari</h2>
<div class="topic-dl-trust" role="note"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Indirme baglantisi acilmadan once kisa bir guvenlik beklemesi uygulanir.</span></div>
{if topic.download_locked}<div class="topic-dl-access-notice" data-download-lock-notice role="status" aria-live="polite"><i class="bi bi-lock-fill" aria-hidden="true"></i><span>{topic.download_lock_message}</span></div>{/if}
<div class="topic-dl-section ui-section"
     data-topic-id="{topic.download_topic_id}"
     data-csrf="{topic.download_csrf_token}"
     data-countdown-seconds="{topic.download_countdown}"
     data-wait-text="{topic.download_wait_text}"
     data-done-text="{topic.download_done_text}"
     data-status-api="{topic.download_status_api}"
     data-auth-api="{topic.download_auth_api}"
     data-login-url="{topic.download_login_url}"
     data-register-url="{topic.download_register_url}"
     data-comment-target="{topic.download_comment_target}"
     data-current-request-uri="{topic.download_current_request_uri}"
     data-locked="{if topic.download_locked}1{else}0{/if}"
     data-lock-reason="{topic.download_lock_reason}"
     data-lock-message="{topic.download_lock_message}"
     data-lock-button-text="{topic.download_lock_button_text}"
     data-comment-cta-label="{topic.download_comment_cta_label}"
     data-open-auth-popup="{topic.download_open_auth_popup}"
     data-focus-comment-form="{topic.download_focus_comment_form}"
     data-unlock-after-auth="{topic.download_unlock_after_auth}"
     data-unlock-after-comment="{topic.download_unlock_after_comment}"
     data-auth-modal-title="{topic.download_auth_modal_title}"
     data-auth-login-label="{topic.download_auth_login_label}"
     data-auth-register-label="{topic.download_auth_register_label}"
     data-auth-success-message="{topic.download_auth_success_message}">
<div class="download-grid topic-dl-grid ui-grid">
{loop topic.download_links}
<a href="{item.href}" rel="noopener" class="download-card topic-dl-card ui-card{if item.locked} is-locked{/if}"
   data-download-href="{item.download_href}"
   data-ready-text="{topic.download_ready_text}"
   data-locked="{if item.locked}1{else}0{/if}"
   data-lock-reason="{item.lock_reason}"
   data-lock-message="{item.lock_message}"
   data-locked-button-text="{topic.download_lock_button_text}"
   data-comment-cta-label="{topic.download_comment_cta_label}"
   aria-disabled="{if item.locked}true{else}false{/if}">
<div class="download-icon topic-dl-icon"><i class="bi {if item.locked}bi-lock-fill{else}bi-cloud-arrow-down{/if}" aria-hidden="true"></i></div>
<div class="download-info topic-dl-info"><strong>{item.name}</strong><small>{item.host}</small>{if item.locked}<small class="topic-dl-lock-message">{item.lock_message}</small>{/if}{if item.show_count}<span class="download-count topic-dl-count"><i class="bi bi-download" aria-hidden="true"></i> {item.count} indirme</span>{/if}</div>
<span class="download-btn topic-dl-button"><span class="topic-dl-spinner"></span><span class="topic-dl-action">{item.button_text}</span></span>
</a>
{/loop}
</div>
</div>
</section>
</div>
