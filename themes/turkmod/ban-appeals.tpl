<main class="appeal-shell ui-section">
    {if appeal_success}
    <div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success">
        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
        <div>{appeal_success}</div>
    </div>
    {/if}
    {if appeal_error}
    <div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error">
        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
        <div>{appeal_error}</div>
    </div>
    {/if}

    <nav class="appeal-tabs" aria-label="Ban itiraz sekmeleri">
        <a href="{appeal_overview_url}" class="{appeal_overview_tab_class}"><i class="bi bi-shield-exclamation" aria-hidden="true"></i> Ban Itirazlarim</a>
        <a href="{appeal_new_url}" class="{appeal_new_tab_class}"><i class="bi bi-pencil-square" aria-hidden="true"></i> Ban Itirazi Gonder</a>
        <a href="{appeal_history_url}" class="{appeal_history_tab_class}"><i class="bi bi-clock-history" aria-hidden="true"></i> Itiraz Gecmisi</a>
    </nav>

    {if appeal_tab_overview}
    {if appeal_has_restriction_details}
    <section class="appeal-card appeal-ban-summary" aria-label="Ban detaylari">
        <div class="appeal-ban-summary-head">
            <span class="appeal-ban-kicker"><i class="bi bi-shield-lock" aria-hidden="true"></i> Ban Detaylari</span>
            <h2>{appeal_restriction_title}</h2>
            <p>{appeal_restriction_reason}</p>
        </div>
        <div class="appeal-ban-grid">
            {loop appeal_restriction_details}
            <div class="appeal-ban-detail">
                <span><i class="bi {appeal_restriction_detail.icon}" aria-hidden="true"></i>{appeal_restriction_detail.label}</span>
                <strong>{appeal_restriction_detail.value}</strong>
            </div>
            {/loop}
        </div>
    </section>
    {/if}

    {if appeal_has_result_card}
    <section class="appeal-card appeal-result-card {appeal_result_class}" aria-label="Itiraz son durumu">
        <div class="appeal-result-main">
            <span class="appeal-result-icon"><i class="bi {appeal_result_icon}" aria-hidden="true"></i></span>
            <div>
                <span class="appeal-result-kicker">Son Durum</span>
                <h2>{appeal_result_title}</h2>
                <p>{appeal_result_text}</p>
            </div>
        </div>
        <div class="appeal-result-side">
            <span class="appeal-status {appeal_result_status}">
                <i class="bi {appeal_result_icon}" aria-hidden="true"></i>
                {appeal_result_status_label}
            </span>
            <small>{appeal_result_date_label}: {appeal_result_date}</small>
        </div>
        {if appeal_result_has_admin_reply}
        <div class="appeal-result-note">
            <strong><i class="bi bi-reply" aria-hidden="true"></i> Son yonetici cevabi</strong>
            <p>{appeal_result_admin_reply}</p>
            <small>{appeal_result_admin_reply_date}</small>
        </div>
        {/if}
        {if !appeal_result_has_admin_reply}
        {if appeal_result_has_admin_note}
        <div class="appeal-result-note">
            <strong><i class="bi bi-chat-left-text" aria-hidden="true"></i> Yonetici notu</strong>
            <p>{appeal_result_admin_note}</p>
        </div>
        {/if}
        {/if}
        <a class="appeal-result-link" href="{appeal_result_history_url}">
            <i class="bi bi-clock-history" aria-hidden="true"></i> Gecmisi gor
        </a>
    </section>
    {/if}

    <section class="appeal-hero">
        <h1><i class="bi bi-shield-exclamation appeal-icon-muted" aria-hidden="true"></i>Ban Itirazlarim</h1>
        <p>{appeal_restriction_message}</p>
    </section>
    {/if}

    {if appeal_tab_new}
    <section class="appeal-card appeal-form appeal-form-card">
        <h2><i class="bi bi-pencil-square appeal-icon" aria-hidden="true"></i>{appeal_form_title}</h2>
        <p>{appeal_form_description}</p>
        {if !appeal_can_submit}
        <div class="ui-admin-alert ui-admin-alert-warning ui-alert ui-alert--warning">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <div>Hesabinizda aktif ban veya kisitlama gorunmedigi icin itiraz formu su anda kullanilamaz.</div>
        </div>
        {else}
        {if appeal_has_active}
        <div class="ui-admin-alert ui-admin-alert-warning ui-alert ui-alert--warning">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <div>Acik veya incelenen bir itiraziniz var. Yeni bir mesaj daha gonderebilirsiniz; tum kayitlar yonetici paneline duser.</div>
        </div>
        {/if}
        <form method="post" action="{appeal_action_url}" class="appeal-form">
            <input type="hidden" name="_token" value="{appeal_csrf_token}">
            <label class="form-label appeal-label" for="message">{appeal_message_label} <span class="appeal-required">*</span></label>
            <textarea id="message" name="message" class="ui-admin-form-control" maxlength="3000" required placeholder="{appeal_message_placeholder}"></textarea>
            <div class="appeal-help">En az 10, en fazla 3000 karakter olmalidir.</div>
            <div class="appeal-actions">
                <button type="submit" class="appeal-submit"><i class="bi bi-send appeal-icon" aria-hidden="true"></i>{appeal_submit_label}</button>
            </div>
        </form>
        <form method="post" action="{appeal_logout_url}" class="appeal-logout-form">
            <input type="hidden" name="_token" value="{appeal_csrf_token}">
            <button type="submit" class="appeal-logout"><i class="bi bi-box-arrow-right appeal-icon" aria-hidden="true"></i>Cikis Yap</button>
        </form>
        {/if}
    </section>
    {/if}

    {if appeal_tab_history}
    <section class="appeal-card">
        <h2><i class="bi bi-clock-history appeal-icon" aria-hidden="true"></i>Itiraz Gecmisi</h2>
        <div class="appeal-list">
            {if !appeal_has_items}
            <div class="appeal-empty">
                <i class="bi bi-inbox" aria-hidden="true"></i>
                <p>Henuz itiraz kaydi yok.</p>
            </div>
            {/if}
            {loop appeal_items}
            <article class="appeal-item">
                <div class="appeal-meta">
                    <div class="appeal-meta-left">
                        <span class="appeal-id">#{appeal_item.id}</span>
                        <span><i class="bi bi-calendar3 appeal-icon-tight" aria-hidden="true"></i>{appeal_item.created_at}</span>
                    </div>
                    <span class="appeal-status {appeal_item.status}">
                        <i class="bi {appeal_item.status_icon}" aria-hidden="true"></i>
                        {appeal_item.status_label}
                    </span>
                </div>
                <div class="appeal-message">{appeal_item.message}</div>
                <div class="appeal-history-status {appeal_item.status_summary_class}">
                    <span class="appeal-history-status-icon"><i class="bi {appeal_item.status_summary_icon}" aria-hidden="true"></i></span>
                    <div>
                        <strong>{appeal_item.status_summary_title}</strong>
                        <p>{appeal_item.status_summary_text}</p>
                        <small>{appeal_item.status_summary_date_label}: {appeal_item.status_summary_date}</small>
                    </div>
                </div>
                {if appeal_item.has_thread}
                <div class="appeal-admin-note">
                    <strong><i class="bi bi-chat-dots appeal-icon-tight" aria-hidden="true"></i>Mesaj Gecmisi:</strong><br>
                    {loop appeal_item.thread}
                    <div class="appeal-thread-line">
                        <span>{item.sender} - {item.date}</span>
                        <p>{item.message}</p>
                    </div>
                    {/loop}
                </div>
                {/if}
                {if appeal_item.has_admin_note}
                <div class="appeal-admin-note">
                    <strong><i class="bi bi-chat-left-text appeal-icon-tight" aria-hidden="true"></i>Yonetici Notu:</strong><br>
                    {appeal_item.admin_note}
                </div>
                {/if}
            </article>
            {/loop}
        </div>
    </section>
    {/if}
</main>
