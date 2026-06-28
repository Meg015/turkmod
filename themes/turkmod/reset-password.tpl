<div class="auth-wrapper ui-theme-auth ui-theme-auth-reset">
    <div class="container ui-container ui-theme-auth-shell">
        {if auth_error}<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error ui-theme-auth-alert" role="alert" aria-live="assertive">{auth_error}</div>{/if}

        {if auth_success}
        <section class="ui-card ui-theme-auth-card ui-theme-auth-card--success" aria-labelledby="resetSuccessTitle">
            <div class="ui-theme-auth-layout">
                <div class="ui-theme-auth-form-panel">
                    <div class="ui-theme-auth-success-icon"><i class="bi bi-check-circle" aria-hidden="true"></i></div>
                    <header class="ui-theme-auth-header">
                        <span class="ui-theme-auth-eyebrow">İşlem tamamlandı</span>
                        <h1 id="resetSuccessTitle">Şifren değiştirildi</h1>
                        <p>{auth_success}</p>
                    </header>
                    <a class="ui-button ui-theme-auth-submit" href="{base_url}/giris">
                        <span>Giriş Yap</span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                </div>

                <aside class="ui-theme-auth-support" aria-label="Şifre değişikliği sonrası güvenlik bilgileri">
                    <span class="ui-theme-auth-eyebrow">Hesap güvenliği</span>
                    <h2>Yeni şifren kullanıma hazır</h2>
                    <ul class="ui-theme-auth-benefits">
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-check2" aria-hidden="true"></i></span>
                            <span><strong>Bağlantı kapatıldı</strong><small>Kullandığın yenileme bağlantısı artık tekrar kullanılamaz.</small></span>
                        </li>
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-key" aria-hidden="true"></i></span>
                            <span><strong>Yeni parola etkin</strong><small>Sonraki girişinde yeni şifreni kullan.</small></span>
                        </li>
                    </ul>
                    <div class="ui-theme-auth-security">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <span><strong>Öneri</strong> Şifreni başka platformlarda tekrar kullanma.</span>
                    </div>
                </aside>
            </div>
        </section>
        {else}
        <section class="ui-card ui-theme-auth-card" aria-labelledby="resetTitle">
            <div class="ui-theme-auth-layout">
                <div class="ui-theme-auth-form-panel">
                    <header class="ui-theme-auth-header">
                        <span class="ui-theme-auth-eyebrow">Yeni parola</span>
                        <h1 id="resetTitle">Şifreni yenile</h1>
                        <p>Hesabın için güçlü ve yalnızca burada kullanacağın yeni bir şifre belirle.</p>
                    </header>

                    <form class="ui-form ui-theme-auth-form" method="post" action="{auth_reset_action}" novalidate>
                        <input type="hidden" name="_token" value="{auth_csrf_token}">

                        <div class="auth-field ui-field ui-theme-auth-field">
                            <label class="ui-label" for="password">Yeni şifre</label>
                            <span class="auth-input-shell ui-theme-auth-input">
                                <i class="bi bi-key" aria-hidden="true"></i>
                                <input class="ui-input" id="password" name="password" type="password" required minlength="{auth_password_min_length}" aria-required="true" autocomplete="new-password" data-password-strength data-password-confirm="#password_confirm" data-password-require-uppercase="{auth_password_require_uppercase}" data-password-require-numbers="{auth_password_require_numbers}" data-password-require-special="{auth_password_require_special}">
                            </span>
                        </div>

                        <div class="auth-field ui-field ui-theme-auth-field">
                            <label class="ui-label" for="password_confirm">Yeni şifre tekrar</label>
                            <span class="auth-input-shell ui-theme-auth-input">
                                <i class="bi bi-key" aria-hidden="true"></i>
                                <input class="ui-input" id="password_confirm" name="password_confirm" type="password" required minlength="{auth_password_min_length}" aria-required="true" autocomplete="new-password">
                            </span>
                        </div>

                        <p class="ui-help ui-theme-auth-help"><i class="bi bi-info-circle" aria-hidden="true"></i> {auth_password_policy_hint}</p>

                        <button class="ui-button ui-theme-auth-submit" type="submit">
                            <span>Şifreyi Değiştir</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>

                    <div class="ui-theme-auth-switch">
                        <span>İşlemi iptal etmek mi istiyorsun?</span>
                        <a href="{base_url}/giris">Giriş sayfasına dön</a>
                    </div>
                </div>

                <aside class="ui-theme-auth-support" aria-label="Güçlü şifre bilgileri">
                    <span class="ui-theme-auth-eyebrow">Hesap güvenliği</span>
                    <h2>Güçlü bir şifre oluştur</h2>
                    <ul class="ui-theme-auth-benefits">
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-fingerprint" aria-hidden="true"></i></span>
                            <span><strong>Benzersiz olsun</strong><small>Başka hesaplarında kullandığın şifreleri tekrar etme.</small></span>
                        </li>
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
                            <span><strong>Kuralları tamamla</strong><small>Parola göstergesi gereken koşulları anlık olarak gösterir.</small></span>
                        </li>
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-eye-slash" aria-hidden="true"></i></span>
                            <span><strong>Gizli tut</strong><small>Şifreni mesaj, e-posta veya yorum yoluyla paylaşma.</small></span>
                        </li>
                    </ul>
                    <div class="ui-theme-auth-security">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <span><strong>Güvenli saklama</strong> Parolan tek yönlü olarak şifrelenir.</span>
                    </div>
                </aside>
            </div>
        </section>
        {/if}
    </div>
</div>
