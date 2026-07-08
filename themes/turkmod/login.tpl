<div class="auth-wrapper ui-theme-auth ui-theme-auth-login">
    <div class="container ui-container ui-theme-auth-shell">
        {if auth_error}<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error ui-theme-auth-alert" role="alert" aria-live="assertive">{auth_error}</div>{/if}
        {if auth_success}<div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success ui-theme-auth-alert" role="status">{auth_success}</div>{/if}

        <section class="ui-card ui-theme-auth-card" aria-labelledby="loginTitle">
            <div class="ui-theme-auth-layout">
                <div class="ui-theme-auth-form-panel">
                    <header class="ui-theme-auth-header">
                        <span class="ui-theme-auth-eyebrow">Hesap erişimi</span>
                        <h1 id="loginTitle">Tekrar hoş geldin</h1>
                        <p>Hesabına giriş yap ve topluluğa kaldığın yerden devam et.</p>
                    </header>

                    {if auth_show_onboarding}
                    <div class="ui-theme-auth-onboarding" role="status" aria-live="polite">
                        <span class="ui-theme-auth-onboarding-icon"><i class="bi bi-check-circle" aria-hidden="true"></i></span>
                        <span><strong>Hesabın hazır.</strong> Giriş yaptıktan sonra profilini tamamlayabilir ve ilk içeriğini paylaşabilirsin.</span>
                    </div>
                    {/if}

                    <form class="ui-form ui-theme-auth-form" method="post" action="{base_url}/giris" novalidate data-remember-email-form>
                        <input type="hidden" name="_token" value="{auth_csrf_token}">
                        <input type="hidden" name="redirect" value="{auth_redirect}">

                        <div class="auth-field ui-field ui-theme-auth-field">
                            <label class="ui-label" for="identifier">{auth_login_label}</label>
                            <span class="auth-input-shell ui-theme-auth-input">
                                <i class="bi {auth_login_icon}" aria-hidden="true"></i>
                                <input class="ui-input" id="identifier" name="identifier" type="{auth_login_type}" placeholder="{auth_login_placeholder}" required aria-required="true" autocomplete="{auth_login_autocomplete}" data-remember-email-input>
                            </span>
                        </div>

                        <div class="auth-field ui-field ui-theme-auth-field">
                            <label class="ui-label" for="password">Şifre</label>
                            <span class="auth-input-shell ui-theme-auth-input">
                                <i class="bi bi-key" aria-hidden="true"></i>
                                <input class="ui-input" id="password" name="password" type="password" required aria-required="true" autocomplete="current-password">
                            </span>
                        </div>

                        <div class="ui-theme-auth-options">
                            <label class="ui-check ui-theme-auth-check">
                                <input type="checkbox" name="remember_email" value="1" data-remember-email-check>
                                <span>{auth_login_remember_label}</span>
                            </label>
                            <a class="ui-theme-auth-link" href="{base_url}/sifremi-unuttum">Şifremi unuttum</a>
                        </div>

                        <label class="ui-check ui-theme-auth-check ui-theme-auth-session-check">
                            <input type="checkbox" name="remember_session" value="1">
                            <span>
                                <strong>Oturumumu açık tut</strong>
                                <small>Yalnızca kişisel cihazlarında kullan.</small>
                            </span>
                        </label>

                        <button class="ui-button ui-theme-auth-submit" type="submit">
                            <span>Giriş Yap</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>

                    <div class="ui-theme-auth-switch">
                        <span>Henüz hesabın yok mu?</span>
                        <a href="{base_url}/kayit">Ücretsiz kayıt ol</a>
                    </div>

                    {if auth_demo_visible}<div class="ui-theme-auth-demo"><strong>Demo bilgileri:</strong> admin@topic.test / password</div>{/if}
                </div>

                <aside class="ui-theme-auth-support" aria-label="TurkMod hesap avantajları">
                    <span class="ui-theme-auth-eyebrow">TurkMod hesabı</span>
                    <h2>Topluluk deneyimi tek yerde</h2>
                    <ul class="ui-theme-auth-benefits">
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i></span>
                            <span><strong>Mod paylaş ve yönet</strong><small>İçeriklerini kolayca yayına al.</small></span>
                        </li>
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-heart" aria-hidden="true"></i></span>
                            <span><strong>Favorilerini koru</strong><small>Beğendiğin içeriklere her cihazdan ulaş.</small></span>
                        </li>
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-trophy" aria-hidden="true"></i></span>
                            <span><strong>Etkinlikleri takip et</strong><small>Görevlerini ve topluluk ödüllerini kaçırma.</small></span>
                        </li>
                    </ul>
                    <div class="ui-theme-auth-security">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span><strong>Güvenli bağlantı</strong> Oturum ve parola verilerin korunur.</span>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</div>

