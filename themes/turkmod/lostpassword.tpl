<div class="auth-wrapper ui-theme-auth ui-theme-auth-forgot">
    <div class="container ui-container ui-theme-auth-shell">
        {if auth_error}<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error ui-theme-auth-alert" role="alert" aria-live="assertive">{auth_error}</div>{/if}
        {if auth_success}<div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success ui-theme-auth-alert" role="status">{auth_success}</div>{/if}

        <section class="ui-card ui-theme-auth-card" aria-labelledby="forgotTitle">
            <div class="ui-theme-auth-layout">
                <div class="ui-theme-auth-form-panel">
                    <header class="ui-theme-auth-header">
                        <span class="ui-theme-auth-eyebrow">Hesap kurtarma</span>
                        <h1 id="forgotTitle">Şifreni yenile</h1>
                        <p>E-posta adresini gir. Hesabın kayıtlıysa güvenli şifre yenileme bağlantısını gönderelim.</p>
                    </header>

                    <form class="ui-form ui-theme-auth-form" method="post" action="{base_url}/sifremi-unuttum" novalidate>
                        <input type="hidden" name="_token" value="{auth_csrf_token}">

                        <div class="auth-field ui-field ui-theme-auth-field">
                            <label class="ui-label" for="email">E-posta adresi</label>
                            <span class="auth-input-shell ui-theme-auth-input">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <input class="ui-input" id="email" name="email" type="email" value="{auth_email_value}" placeholder="ornek@email.com" required aria-required="true" autocomplete="email">
                            </span>
                        </div>

                        <button class="ui-button ui-theme-auth-submit" type="submit">
                            <span>Yenileme Bağlantısı Gönder</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>

                    <div class="ui-theme-auth-switch">
                        <span>Şifreni hatırladın mı?</span>
                        <a href="{base_url}/giris">Giriş sayfasına dön</a>
                    </div>
                </div>

                <aside class="ui-theme-auth-support" aria-label="Şifre yenileme güvenlik bilgileri">
                    <span class="ui-theme-auth-eyebrow">Güvenli yenileme</span>
                    <h2>Hesabına kontrollü biçimde dön</h2>
                    <ul class="ui-theme-auth-benefits">
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-envelope-check" aria-hidden="true"></i></span>
                            <span><strong>E-posta doğrulaması</strong><small>Bağlantı yalnızca kayıtlı hesap adresine gönderilir.</small></span>
                        </li>
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span>
                            <span><strong>Süreli bağlantı</strong><small>Yenileme bağlantısı sınırlı bir süre boyunca geçerlidir.</small></span>
                        </li>
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                            <span><strong>Gizli hesap kontrolü</strong><small>Ekran, bir e-posta adresinin kayıtlı olup olmadığını açıklamaz.</small></span>
                        </li>
                    </ul>
                    <div class="ui-theme-auth-security">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span><strong>Güvenlik notu</strong> Yenileme bağlantısını kimseyle paylaşma.</span>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</div>
