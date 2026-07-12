<div class="ui-theme-topic-part ui-theme-topic-part--downloads">
<section class="topic-section topic-downloads topic-download-links ui-section" aria-labelledby="dl-heading">
<h2 id="dl-heading">İndirme Bağlantıları</h2>
<div class="topic-dl-trust" role="note"><i class="bi bi-shield-check" aria-hidden="true"></i><span>İndirme bağlantısı açılmadan önce kısa bir güvenlik beklemesi uygulanır.</span></div>
{if topic.download_show_access_notice}<div class="topic-dl-access-notice{if topic.download_access_success} is-success{/if}" data-download-lock-notice data-download-stage="{topic.download_access_stage}" role="status" aria-live="polite"><i class="bi {topic.download_access_notice_icon}" aria-hidden="true"></i><div class="topic-dl-access-notice__body"><strong class="topic-dl-access-notice__title">{topic.download_access_notice_title}</strong><span class="topic-dl-access-notice__text">{topic.download_access_notice_message}</span>{if topic.download_access_until_text}<span class="topic-dl-access-until" data-download-access-until>{topic.download_access_until_text}</span>{else}<span class="topic-dl-access-until" data-download-access-until hidden></span>{/if}{if topic.download_progress_enabled}<span class="topic-dl-access-progress" data-download-progress aria-label="{topic.download_progress_text}">{topic.download_progress_text}</span>{/if}<div class="topic-dl-access-steps" aria-label="İndirme kilidi adımları"><span class="topic-dl-access-step {topic.download_access_step_login_class}" data-download-step="login" title="Giriş yap"><i class="bi bi-1-circle-fill" aria-hidden="true"></i><span>Giriş</span></span>{if topic.download_comment_step_required}<span class="topic-dl-access-step {topic.download_access_step_comment_class}" data-download-step="comment" title="Yorum gönder"><i class="bi bi-2-circle-fill" aria-hidden="true"></i><span>Yorum</span></span>{/if}<span class="topic-dl-access-step {topic.download_access_step_open_class}" data-download-step="open" title="Bağlantıyı aç"><i class="bi {topic.download_access_step_open_icon}" aria-hidden="true"></i><span>Aç</span></span></div></div></div>{/if}
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
     data-access-mode="{topic.download_access_mode}"
     data-success-notice-enabled="{topic.download_success_notice_enabled}"
     data-success-message="{topic.download_success_message}"
     data-progress-enabled="{if topic.download_progress_enabled}1{else}0{/if}"
     data-comment-title="{topic.download_comment_title}"
     data-progress-template="{topic.download_progress_template}"
     data-progress-completed="{topic.download_progress_completed}"
     data-progress-total="{topic.download_progress_total}"
     data-comment-step-required="{topic.download_comment_step_required}"
     data-success-animation-enabled="{topic.download_success_animation_enabled}"
     data-success-auto-compact="{topic.download_success_auto_compact}"
     data-success-compact-delay="{topic.download_success_compact_delay}"
     data-highlight-first-card="{topic.download_highlight_first_card}"
     data-pending-message="{topic.download_pending_message}"
     data-pending-button-text="{topic.download_pending_button_text}"
     data-expired-title="{topic.download_expired_title}"
     data-expired-message="{topic.download_expired_message}"
     data-access-until-text="{topic.download_access_until_text}"
     data-access-expires-at="{topic.download_access_expires_at}"
     data-download-stage="{topic.download_access_stage}"
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
<div class="download-icon topic-dl-icon"><i class="bi {if item.locked}{item.lock_icon}{else}bi-cloud-arrow-down{/if}" aria-hidden="true"></i></div>
<div class="download-info topic-dl-info"><strong>{item.name}</strong><small>{item.host}</small>{if item.locked}<small class="topic-dl-lock-message">{item.lock_message}</small>{/if}{if item.show_count}<span class="download-count topic-dl-count"><i class="bi bi-download" aria-hidden="true"></i> {item.count} indirme</span>{/if}</div>
<span class="download-btn topic-dl-button"><span class="topic-dl-spinner"></span><span class="topic-dl-action">{item.button_text}</span></span>
</a>
{/loop}
</div>
</div>
</section>
</div>
