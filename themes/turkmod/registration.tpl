<div class="auth-wrapper auth-screen-register ui-theme-auth ui-theme-auth-register">
    <div class="container ui-container ui-theme-auth-shell">
        {if auth_error}<div class="ui-admin-alert ui-admin-alert-danger ui-alert ui-alert--error ui-theme-auth-alert" role="alert" aria-live="assertive">{auth_error}</div>{/if}
        {if auth_success}<div class="ui-admin-alert ui-admin-alert-success ui-alert ui-alert--success ui-theme-auth-alert" role="status">{auth_success}</div>{/if}

        <section class="ui-card ui-theme-auth-card" aria-labelledby="registerTitle">
            <div class="ui-theme-auth-layout">
                <div class="ui-theme-auth-form-panel">
                    <header class="ui-theme-auth-header">
                        <span class="ui-theme-auth-eyebrow">Yeni üyelik</span>
                        <h1 id="registerTitle">Topluluğa katıl</h1>
                        <p>Ücretsiz hesabını oluştur, içeriklerini paylaşmaya ve profilini geliştirmeye başla.</p>
                    </header>

                    {if auth_allow_registration}
                    <form class="ui-form ui-theme-auth-form" method="post" action="{base_url}/kayit" novalidate>
                        <input type="hidden" name="_token" value="{auth_csrf_token}">

                        <div class="auth-field ui-field ui-theme-auth-field">
                            <label class="ui-label" for="name">Adın</label>
                            <span class="auth-input-shell ui-theme-auth-input">
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <input class="ui-input" id="name" name="name" type="text" value="{auth_name_value}" placeholder="Adın Soyadın" required minlength="2" maxlength="255" aria-required="true" autocomplete="name">
                            </span>
                        </div>

                        <div class="auth-field ui-field ui-theme-auth-field">
                            <label class="ui-label" for="email">E-posta adresi</label>
                            <span class="auth-input-shell ui-theme-auth-input">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <input class="ui-input" id="email" name="email" type="email" value="{auth_email_value}" placeholder="ornek@email.com" required aria-required="true" autocomplete="email">
                            </span>
                        </div>

                        <div class="ui-theme-auth-fields ui-theme-auth-fields--split">
                            <div class="auth-field ui-field ui-theme-auth-field">
                                <label class="ui-label" for="password">Şifre</label>
                                <span class="auth-input-shell ui-theme-auth-input">
                                    <i class="bi bi-key" aria-hidden="true"></i>
                                    <input class="ui-input" id="password" name="password" type="password" placeholder="En az {auth_password_min_length} karakter" required minlength="{auth_password_min_length}" aria-required="true" autocomplete="new-password" data-password-strength data-password-confirm="#password_confirm" data-password-require-uppercase="{auth_password_require_uppercase}" data-password-require-numbers="{auth_password_require_numbers}" data-password-require-special="{auth_password_require_special}">
                                </span>
                            </div>

                            <div class="auth-field ui-field ui-theme-auth-field">
                                <label class="ui-label" for="password_confirm">Şifre tekrar</label>
                                <span class="auth-input-shell ui-theme-auth-input">
                                    <i class="bi bi-key" aria-hidden="true"></i>
                                    <input class="ui-input" id="password_confirm" name="password_confirm" type="password" placeholder="Şifreni tekrar yaz" required minlength="{auth_password_min_length}" aria-required="true" autocomplete="new-password">
                                </span>
                            </div>
                        </div>

                        <p class="ui-help ui-theme-auth-help"><i class="bi bi-info-circle" aria-hidden="true"></i> {auth_password_policy_hint}</p>

                        <label class="ui-check ui-theme-auth-check ui-theme-auth-terms">
                            <input type="checkbox" required aria-required="true">
                            <span><a href="{base_url}/rules.html">Kullanım koşullarını</a> ve <a href="{base_url}/privacy.html">gizlilik politikasını</a> okudum, kabul ediyorum.</span>
                        </label>

                        <button class="ui-button ui-theme-auth-submit" type="submit">
                            <span>Hesabımı Oluştur</span>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>
                    {else}
                    <div class="ui-admin-alert ui-admin-alert-warning ui-alert ui-alert--warning ui-theme-auth-alert" role="status">
                        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Yeni kayıtlar şu anda kapalı.
                    </div>
                    {/if}

                    <div class="ui-theme-auth-switch">
                        <span>Zaten hesabın var mı?</span>
                        <a href="{base_url}/giris">Giriş yap</a>
                    </div>
                </div>

                <aside class="ui-theme-auth-support" aria-label="TurkMod üyelik bilgileri">
                    <span class="ui-theme-auth-eyebrow">TurkMod topluluğu</span>
                    <h2>Üret, paylaş ve keşfet</h2>
                    <ul class="ui-theme-auth-benefits">
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i></span>
                            <span><strong>Hızlıca başla</strong><small>Kayıttan sonra profilini tamamla ve içeriğini yükle.</small></span>
                        </li>
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
                            <span><strong>Topluluğa katıl</strong><small>İçerikleri değerlendir ve diğer üyelerle etkileşime geç.</small></span>
                        </li>
                        <li>
                            <span class="ui-theme-auth-benefit-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                            <span><strong>Güvenli ve ücretsiz</strong><small>Parolan güvenle saklanır, hesabın sana özel kalır.</small></span>
                        </li>
                    </ul>
                    <div class="ui-theme-auth-security">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <span><strong>Gizlilik odaklı</strong> E-posta adresin herkese açık gösterilmez.</span>
                    </div>
                </aside>
            </div>
        </section>
    </div>
</div>
