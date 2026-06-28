{if download_has_alert}
<div class="{download_alert_class}" role="alert">{download_alert_message}</div>
{/if}

{if download_confirm}
<main class="download-confirm-wrap">
    <section class="download-confirm-card" aria-labelledby="download-confirm-title"
             data-download-confirm
             data-confirm-href="{download_confirm_href}"
             data-auto-redirect-seconds="{download_countdown_seconds}"
             data-auto-redirect-enabled="{download_auto_redirect_enabled}"
             data-primary-label="{download_redirect_primary_label}"
             data-primary-countdown-label="{download_redirect_primary_countdown_label}"
             data-redirecting-label="{download_redirect_redirecting_label}">
        <div class="download-confirm-head">
            <span class="download-confirm-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
            <div>
                <span class="download-confirm-kicker">{download_redirect_kicker}</span>
                <h1 id="download-confirm-title">{download_redirect_title}</h1>
                <p>{download_redirect_intro}</p>
            </div>
        </div>
        <div class="download-confirm-body">
            <div class="download-confirm-host">
                <i class="bi bi-globe2" aria-hidden="true"></i>
                <div>
                    <span>{download_redirect_host_label}</span>
                    <strong>{download_target_host}</strong>
                </div>
            </div>
            <div class="download-confirm-meta">
                <div><span>{download_redirect_link_label}</span><strong>{download_link_name}</strong></div>
                <div><span>{download_redirect_topic_label}</span><strong>{download_topic_title}</strong></div>
                <div><span>{download_redirect_protocol_label}</span><strong>{download_target_scheme}</strong></div>
            </div>
            {if download_show_target_url}
            <div class="download-confirm-url" title="{download_target_url}">
                <i class="bi bi-link-45deg" aria-hidden="true"></i>
                <span>{download_target_url}</span>
            </div>
            {/if}
            <div class="download-confirm-safety-grid">
                <span><i class="bi bi-check-circle" aria-hidden="true"></i><span>{download_redirect_safety_domain_text}</span></span>
                <span><i class="bi bi-check-circle" aria-hidden="true"></i><span>{download_redirect_safety_count_text}</span></span>
                <span><i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{download_redirect_safety_external_text}</span></span>
            </div>
            <p class="download-confirm-note">{download_redirect_note}</p>
            {if download_auto_redirect_enabled}
            <div class="download-confirm-timer" role="status" aria-live="polite">
                <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                <span>{download_redirect_timer_prefix}<strong id="downloadConfirmCountdown">{download_countdown_seconds}</strong>{download_redirect_timer_suffix}</span>
            </div>
            {/if}
        </div>
        <div class="download-confirm-actions">
            <a class="ui-admin-btn ui-admin-btn-primary download-confirm-primary" href="{download_confirm_href}" rel="nofollow noopener" data-download-confirm-primary>
                <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <span data-download-confirm-primary-text>{download_redirect_primary_label}</span>
            </a>
            <a class="ui-admin-btn ui-admin-btn-secondary" href="{download_topic_href}">
                <i class="bi bi-file-earmark-text" aria-hidden="true"></i> {download_redirect_secondary_label}
            </a>
        </div>
    </section>
</main>

{/if}
