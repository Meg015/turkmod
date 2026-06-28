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

    <section class="appeal-hero">
        <h1><i class="bi bi-shield-exclamation appeal-icon-muted" aria-hidden="true"></i>Ban Itirazlarim</h1>
        <p>{appeal_restriction_message}</p>
    </section>

    <section class="appeal-card appeal-form appeal-form-card">
        <h2><i class="bi bi-pencil-square appeal-icon" aria-hidden="true"></i>Ban Itirazi Gonder</h2>
        <p>Ban kararinin hatali oldugunu dusunuyorsaniz, lutfen asagidaki formu doldurarak itirazinizi gonderiniz. Yoneticiler tarafindan incelenecektir.</p>
        {if appeal_has_active}
        <div class="ui-admin-alert ui-admin-alert-warning ui-alert ui-alert--warning">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <div>Acik veya incelenen bir itirazaniz var. Yeni bir mesaj daha gonderebilirsiniz; tum kayitlar yonetici paneline duser.</div>
        </div>
        {/if}
        <form method="post" action="{base_url}/ban-appeals.php" class="appeal-form">
            <input type="hidden" name="_token" value="{appeal_csrf_token}">
            <label class="form-label appeal-label" for="message">Itiraz Metni <span class="appeal-required">*</span></label>
            <textarea id="message" name="message" class="ui-admin-form-control" maxlength="3000" required placeholder="Ban kararina neden itiraz ettigini acik ve net sekilde yaz..."></textarea>
            <div class="appeal-help">En az 10, en fazla 3000 karakter olmalidir.</div>
            <div class="appeal-actions">
                <button type="submit" class="appeal-submit"><i class="bi bi-send appeal-icon" aria-hidden="true"></i>Itirazi Gonder</button>
            </div>
        </form>
        <form method="post" action="{base_url}/logout.php" class="appeal-logout-form">
            <input type="hidden" name="_token" value="{appeal_csrf_token}">
            <button type="submit" class="appeal-logout"><i class="bi bi-box-arrow-right appeal-icon" aria-hidden="true"></i>Cikis Yap</button>
        </form>
    </section>

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
</main>
