function initSettingsPageMainBindings() {
document.querySelectorAll('.seo-subtab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var target = this.getAttribute('data-seo-tab');

        document.querySelectorAll('.seo-subtab-btn').forEach(function(item) {
            item.classList.remove('active');
        });

        this.classList.add('active');

        document.querySelectorAll('.seo-subtab-panel').forEach(function(panel) {
            panel.classList.remove('is-active');
        });

        var panel = document.getElementById(target);
        if (panel) {
            panel.classList.add('is-active');
        }
    });
});

(function() {
    var cards = document.querySelectorAll('[data-seo-public-page-card]');

    function syncCard(card) {
        var noindex = card.querySelector('[data-seo-public-page-noindex]');
        var sitemap = card.querySelector('[data-seo-public-page-sitemap]');
        var sitemapRow = card.querySelector('[data-seo-public-page-sitemap-row]');
        if (!noindex || !sitemap) {
            return;
        }

        var locked = !!noindex.disabled;
        var blocked = !!noindex.checked || locked;
        sitemap.disabled = blocked;
        sitemap.setAttribute('aria-disabled', blocked ? 'true' : 'false');
        if (blocked) {
            sitemap.checked = false;
        }
        if (sitemapRow) {
            sitemapRow.classList.toggle('is-disabled', blocked);
        }
        card.classList.toggle('is-noindex', blocked);
    }

    cards.forEach(function(card) {
        var noindex = card.querySelector('[data-seo-public-page-noindex]');
        if (noindex) {
            noindex.addEventListener('change', function() {
                syncCard(card);
            });
        }
        syncCard(card);
    });
})();

document.querySelectorAll('[data-settings-subtab]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var target = this.getAttribute('data-settings-subtab');
        var scope = this.getAttribute('data-settings-subtab-scope') || '';
        if (!target || !scope) return;

        document.querySelectorAll('[data-settings-subtab-scope="' + scope + '"][data-settings-subtab]').forEach(function(item) {
            item.classList.remove('active');
        });
        this.classList.add('active');

        document.querySelectorAll('[data-settings-subtab-scope="' + scope + '"][data-settings-subtab-panel]').forEach(function(panel) {
            panel.classList.remove('is-active');
        });

        var panel = document.getElementById(target);
        if (panel) {
            panel.classList.add('is-active');
        }
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + scope + ':' + target);
        }
        if (target === 'email-tab-account') {
            ensureAccountEmailRichEditors();
        }
    });
});

function activateSettingsSubtabFromHash() {
    var hash = String(window.location.hash || '').replace(/^#/, '');
    if (!hash || hash.indexOf(':') === -1) {
        return;
    }

    var parts = hash.split(':');
    var scope = parts.shift();
    var target = parts.join(':');
    if (!scope || !target) {
        return;
    }

    var button = document.querySelector('[data-settings-subtab-scope="' + scope + '"][data-settings-subtab="' + target + '"]');
    if (button) {
        button.click();
    }
}

function userSystemRuleField(rule, name) {
    return rule ? rule.querySelector('[name="' + name + '"]') : null;
}

function userSystemNumber(rule, name, fallback) {
    var field = userSystemRuleField(rule, name);
    if (!field || String(field.value || '').trim() === '') {
        return fallback || 0;
    }
    var value = parseInt(String(field.value || '').trim(), 10);
    return Number.isFinite(value) ? value : (fallback || 0);
}

function userSystemChecked(rule, name) {
    var field = userSystemRuleField(rule, name);
    return !!(field && field.checked);
}

function userSystemValue(rule, name) {
    var field = userSystemRuleField(rule, name);
    return field ? String(field.value || '') : '';
}

function userSystemMinuteText(minutes) {
    return Number(minutes) === 1 ? '1 dakika' : minutes + ' dakika';
}

function userSystemDayText(days) {
    return Number(days) === 1 ? '1 gün' : days + ' gün';
}

function userSystemListCount(rule, name) {
    var raw = userSystemValue(rule, name);
    if (raw.trim() === '') {
        return 0;
    }
    return raw.split(/[\r\n,;]+/).map(function (item) {
        return item.trim();
    }).filter(Boolean).length;
}

function updateUserSystemSummary(rule) {
    var summary = rule.querySelector('[data-user-system-summary]');
    if (!summary) {
        return;
    }

    var mode = rule.getAttribute('data-user-summary-mode') || '';
    var text = '';
    var warnings = [];

    if (mode === 'login_identity') {
        var loginMode = userSystemValue(rule, 'login_identifier_mode');
        if (loginMode === 'both') {
            text = 'Kullanıcı e-posta veya kullanıcı adıyla giriş yapabilir.';
        } else if (loginMode === 'username') {
            text = 'Kullanıcı sadece kullanıcı adıyla giriş yapabilir.';
        } else {
            text = 'Kullanıcı sadece e-posta adresiyle giriş yapabilir.';
        }
    } else if (mode === 'session_behavior') {
        var sessionMinutes = userSystemNumber(rule, 'session_timeout_minutes', 0);
        var rememberMinutes = userSystemNumber(rule, 'remember_session_timeout_minutes', 0);
        if (userSystemChecked(rule, 'login_show_remember_session')) {
            text = userSystemMinuteText(sessionMinutes) + ' hareketsizlikte oturum kapanır; açık tut seçilirse ' + userSystemMinuteText(rememberMinutes) + ' geçerli olur';
            text += userSystemChecked(rule, 'login_remember_session_default') ? ' ve varsayılan seçili gelir.' : '.';
            if (rememberMinutes > 0 && sessionMinutes > 0 && rememberMinutes <= sessionMinutes) {
                warnings.push('Açık tut süresi normal oturum süresinden uzun olmalı; aksi halde kalıcı oturum beklenen farkı yaratmaz.');
            }
        } else {
            text = userSystemMinuteText(sessionMinutes) + ' hareketsizlikte oturum kapanır; açık tut seçeneği girişte görünmez.';
            if (userSystemChecked(rule, 'login_remember_session_default')) {
                warnings.push('Açık tut seçeneği gizliyken varsayılan seçili ayarı etkisiz kalır.');
            }
        }
        if (sessionMinutes <= 0) {
            warnings.push('Oturum zaman aşımı 1 dakika veya daha yüksek olmalı.');
        }
    } else if (mode === 'registration_status') {
        text = userSystemChecked(rule, 'allow_registration')
            ? 'Yeni kullanıcı kaydı açık.'
            : 'Yeni kullanıcı kaydı kapalı.';
    } else if (mode === 'username_length') {
        var usernameMin = userSystemNumber(rule, 'register_username_min_length', 0);
        var usernameMax = userSystemNumber(rule, 'register_username_max_length', 0);
        text = 'Kullanıcı adı ' + usernameMin + '-' + usernameMax + ' karakter aralığında olmalı.';
        if (usernameMin > usernameMax) {
            warnings.push('Minimum kullanıcı adı uzunluğu maksimum değerden büyük olamaz.');
        }
        if (usernameMin < 3) {
            warnings.push('Çok kısa kullanıcı adları taklit ve spam riskini artırır; 3 veya üzeri önerilir.');
        }
    } else if (mode === 'password_policy') {
        var requirements = [];
        if (userSystemChecked(rule, 'password_require_uppercase')) requirements.push('büyük harf');
        if (userSystemChecked(rule, 'password_require_numbers')) requirements.push('sayı');
        if (userSystemChecked(rule, 'password_require_special')) requirements.push('özel karakter');
        var expiryDays = userSystemNumber(rule, 'password_expiry_days', 0);
        var passwordMin = userSystemNumber(rule, 'password_min_length', 0);
        text = 'Şifre en az ' + passwordMin + ' karakter olmalı';
        text += requirements.length ? '; ' + requirements.join(', ') + ' zorunlu.' : '; ek karakter zorunluluğu yok.';
        text += expiryDays > 0 ? ' ' + userSystemDayText(expiryDays) + ' sonra süresi dolar.' : ' Süre sonu uygulanmaz.';
        if (passwordMin < 8) {
            warnings.push('Minimum şifre uzunluğu güvenlik için düşük; 8 veya üzeri önerilir.');
        }
        if (requirements.length === 0) {
            warnings.push('Hiçbir karakter zorunluluğu yok; en az sayı veya büyük harf zorunluluğu önerilir.');
        }
        if (expiryDays > 0 && expiryDays < 30) {
            warnings.push('Şifre geçerlilik süresi çok kısa; sık parola değişimi kullanıcıları zayıf parola seçmeye itebilir.');
        }
    } else if (mode === 'email_allow_list') {
        var allowedDomains = userSystemListCount(rule, 'register_allowed_email_domains');
        text = allowedDomains > 0
            ? 'Sadece listedeki ' + allowedDomains + ' e-posta domaini kayıt olabilir.'
            : 'Domain izin listesi boş; tüm e-posta domainleri kayıt olabilir.';
    } else if (mode === 'registration_approval') {
        text = userSystemChecked(rule, 'registration_requires_admin_approval')
            ? 'Yeni kayıtlar yönetici onayına düşer ve bekleme mesajı gösterilir.'
            : 'Yeni kayıtlar otomatik olarak aktif açılır.';
    } else if (mode === 'suspicious_registration') {
        if (!userSystemChecked(rule, 'registration_suspicious_alert_enabled')) {
            text = 'Şüpheli kayıt bildirimi kapalı.';
        } else {
            var suspiciousWindow = userSystemNumber(rule, 'registration_suspicious_window_minutes', 0);
            var suspiciousThreshold = userSystemNumber(rule, 'registration_suspicious_ip_threshold', 0);
            var suspiciousCooldown = userSystemNumber(rule, 'registration_suspicious_cooldown_minutes', 0);
            text = userSystemMinuteText(suspiciousWindow) + ' içinde ' + suspiciousThreshold + ' kayıt sinyali görülürse uyarı gönderilir; ' + userSystemMinuteText(suspiciousCooldown) + ' soğuma uygulanır.';
            if (suspiciousWindow <= 0) {
                warnings.push('İnceleme penceresi 1 dakika veya daha yüksek olmalı.');
            }
            if (suspiciousThreshold < 2) {
                warnings.push('IP eşik sayısı çok düşük; 1 değeri normal tekil kayıtları da alarm yapabilir.');
            }
            if (suspiciousCooldown <= 0) {
                warnings.push('Bildirim soğuma süresi 1 dakika veya daha yüksek olmalı.');
            }
        }
    } else if (mode === 'email_verification') {
        var verificationEnabled = userSystemChecked(rule, 'account_email_verification_enabled');
        var verificationRequired = userSystemChecked(rule, 'account_email_verification_required');
        var reminderEnabled = userSystemChecked(rule, 'account_email_verification_reminder_enabled');
        var verificationTtl = userSystemNumber(rule, 'account_email_verification_ttl_minutes', 0);
        var verificationCooldown = userSystemNumber(rule, 'account_email_verification_resend_cooldown_minutes', 0);
        var reminderAfter = userSystemNumber(rule, 'account_email_verification_reminder_after_minutes', 0);
        var reminderBatch = userSystemNumber(rule, 'account_email_verification_reminder_batch_size', 0);
        if (!verificationEnabled) {
            text = 'E-posta doğrulama sistemi kapalı.';
            if (verificationRequired) {
                warnings.push('E-posta doğrulama kapalıyken giriş için doğrulama zorunlu ayarı etkisiz veya çelişkili kalır.');
            }
            if (reminderEnabled) {
                warnings.push('Doğrulama sistemi kapalıyken hatırlatma cron ayarı çalışmaz.');
            }
        } else {
            text = 'Doğrulama bağlantısı ' + userSystemMinuteText(verificationTtl) + ' geçerli; ' + userSystemMinuteText(verificationCooldown) + ' sonra tekrar istenebilir.';
            text += verificationRequired ? ' Giriş için doğrulama zorunlu.' : ' Giriş için doğrulama zorunlu değil.';
            if (reminderEnabled) {
                text += ' Hatırlatma ' + userSystemMinuteText(reminderAfter) + ' sonra, parti başına ' + reminderBatch + ' hesapla çalışır.';
            }
            if (verificationTtl <= 0 || verificationCooldown <= 0) {
                warnings.push('Doğrulama bağlantısı süresi ve tekrar gönderme bekleme süresi 1 dakika veya daha yüksek olmalı.');
            }
            if (verificationCooldown >= verificationTtl) {
                warnings.push('Tekrar gönderme bekleme süresi bağlantı geçerlilik süresinden kısa olmalı.');
            }
            if (reminderEnabled && reminderAfter < verificationTtl) {
                warnings.push('Hatırlatma eşiği bağlantı süresinden önceyse kullanıcı geçerli link varken tekrar e-posta alabilir.');
            }
            if (reminderEnabled && reminderBatch <= 0) {
                warnings.push('Hatırlatma parti boyutu 1 veya daha yüksek olmalı.');
            }
        }
    } else if (mode === 'password_reset_ttl') {
        var passwordResetTtl = userSystemNumber(rule, 'password_reset_token_ttl_minutes', 0);
        text = 'Şifre sıfırlama bağlantısı ' + userSystemMinuteText(passwordResetTtl) + ' geçerli olur.';
        if (passwordResetTtl < 15) {
            warnings.push('Şifre sıfırlama bağlantısı süresi çok kısa; kullanıcıların işlemi tamamlaması zorlaşabilir.');
        }
        if (passwordResetTtl > 1440) {
            warnings.push('Şifre sıfırlama bağlantısı süresi çok uzun; güvenlik için 24 saat veya altı önerilir.');
        }
    } else if (mode === 'username_block_lists') {
        text = userSystemListCount(rule, 'spam_blocked_usernames') + ' tam kullanıcı adı ve ' + userSystemListCount(rule, 'spam_blocked_username_fragments') + ' kullanıcı adı parçası engelleniyor.';
    } else if (mode === 'text_block_lists') {
        text = userSystemListCount(rule, 'spam_profanity_words') + ' argo/küfür kelimesi, ' + userSystemListCount(rule, 'spam_meaningless_words') + ' anlamsız kelime ve ' + userSystemListCount(rule, 'spam_meaningless_patterns') + ' desen kontrol ediliyor.';
    } else if (mode === 'email_block_list') {
        var blockedDomains = userSystemListCount(rule, 'spam_blocked_email_domains');
        text = blockedDomains > 0
            ? blockedDomains + ' e-posta domaini kayıt sırasında engelleniyor.'
            : 'E-posta domain engel listesi boş.';
    }

    summary.textContent = text;

    var warning = rule.querySelector('[data-user-system-warning]');
    if (warning) {
        if (warnings.length) {
            warning.innerHTML = '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>' + warnings.join('<br>') + '</span>';
            warning.hidden = false;
        } else {
            warning.textContent = '';
            warning.hidden = true;
        }
    }
}

document.querySelectorAll('[data-user-summary-mode]').forEach(function (rule) {
    rule.querySelectorAll('input, select, textarea').forEach(function (field) {
        field.addEventListener('input', function () { updateUserSystemSummary(rule); });
        field.addEventListener('change', function () { updateUserSystemSummary(rule); });
    });
    updateUserSystemSummary(rule);
});

document.querySelectorAll('[data-color-field]').forEach(function(field) {
    var input = field.querySelector('[data-color-input]');
    var value = field.querySelector('[data-color-value]');
    if (!input || !value) return;

    var syncColorValue = function() {
        value.textContent = String(input.value || '').toUpperCase();
    };

    input.addEventListener('input', syncColorValue);
    input.addEventListener('change', syncColorValue);
    syncColorValue();
});

function userUploadField(name) {
    return document.querySelector('[name="' + name + '"]');
}

function setUserUploadFieldDisabled(name, disabled) {
    var field = userUploadField(name);
    if (!field) return;
    field.disabled = disabled;
    var wrapper = field.closest('.user-upload-setting-group-grid > div') || field.closest('div');
    if (wrapper) wrapper.classList.toggle('user-upload-setting-disabled', disabled);
}

function updateUserUploadDependentSettings() {
    var allowVideo = userUploadField('user_upload_allow_video_url');
    var wizardEnabled = userUploadField('user_upload_wizard_enabled');
    var showProfileFollowup = userUploadField('user_upload_show_profile_followup');
    var requireApproval = userUploadField('user_upload_require_approval');

    setUserUploadFieldDisabled('user_upload_allowed_video_hosts', !!allowVideo && !allowVideo.checked);
    setUserUploadFieldDisabled('user_upload_allow_step_skip', !!wizardEnabled && !wizardEnabled.checked);
    setUserUploadFieldDisabled('user_upload_show_profile_button', !!showProfileFollowup && !showProfileFollowup.checked);
    setUserUploadFieldDisabled('user_upload_default_status', !!requireApproval && requireApproval.checked);
}

function numericUserUploadValue(name) {
    var field = userUploadField(name);
    if (!field || String(field.value).trim() === '') return 0;
    return Number(field.value || 0);
}

function validateUserUploadSettingLogic() {
    var checks = [
        ['user_upload_min_title_length', 'user_upload_max_title_length', 'Minimum başlık uzunluğu maksimumdan büyük olamaz.'],
        ['user_upload_image_min_width', 'user_upload_image_max_width', 'Minimum görsel genişliği maksimumdan büyük olamaz.'],
        ['user_upload_image_min_height', 'user_upload_image_max_height', 'Minimum görsel yüksekliği maksimumdan büyük olamaz.']
    ];

    for (var i = 0; i < checks.length; i++) {
        var minValue = numericUserUploadValue(checks[i][0]);
        var maxValue = numericUserUploadValue(checks[i][1]);
        if (minValue > 0 && maxValue > 0 && minValue > maxValue) {
            showToast(checks[i][2], 'warning');
            var field = userUploadField(checks[i][0]);
            if (field) field.focus();
            return false;
        }
    }
    return true;
}

[
    'user_upload_allow_video_url',
    'user_upload_wizard_enabled',
    'user_upload_show_profile_followup',
    'user_upload_require_approval'
].forEach(function(name) {
    var field = userUploadField(name);
    if (field) field.addEventListener('change', updateUserUploadDependentSettings);
});
updateUserUploadDependentSettings();

function updateDownloadAccessDurationSettings() {
    var mode = document.querySelector('[name="download_access_grant_mode"]');
    var unit = document.querySelector('[name="download_access_grant_duration_unit"]');
    var duration = document.querySelector('[name="download_access_grant_duration_value"]');
    var timed = !!mode && mode.value === 'timed';
    var limits = { minutes: 525600, hours: 87600, days: 3650 };
    var unitValue = unit ? String(unit.value || 'hours') : 'hours';
    var maximum = limits[unitValue] || limits.hours;
    if (duration) {
        duration.min = '1';
        duration.max = String(maximum);
        var currentValue = parseInt(duration.value || '1', 10);
        if (!Number.isFinite(currentValue) || currentValue < 1) {
            duration.value = '1';
        } else if (currentValue > maximum) {
            duration.value = String(maximum);
        }
    }
    ['download_access_grant_duration_value', 'download_access_grant_duration_unit', 'download_access_active_until_template'].forEach(function(name) {
        var field = document.querySelector('[name="' + name + '"]');
        if (!field) return;
        field.disabled = !timed;
        var wrapper = field.closest('[data-setting-field]') || field.closest('div');
        if (wrapper) wrapper.classList.toggle('user-upload-setting-disabled', !timed);
    });
}

var downloadAccessGrantMode = document.querySelector('[name="download_access_grant_mode"]');
if (downloadAccessGrantMode) {
    downloadAccessGrantMode.addEventListener('change', updateDownloadAccessDurationSettings);
}
var downloadAccessGrantUnit = document.querySelector('[name="download_access_grant_duration_unit"]');
if (downloadAccessGrantUnit) {
    downloadAccessGrantUnit.addEventListener('change', updateDownloadAccessDurationSettings);
}
updateDownloadAccessDurationSettings();

var accountEmailEditorInitStarted = false;

function parseAccountEmailDocument(value) {
    value = String(value || '');
    if (!/<(?:!doctype|html|body)\b/i.test(value)) return null;
    var parsed = new DOMParser().parseFromString(value, 'text/html');
    var editable = parsed.querySelector('[data-account-email-editable="1"]');
    if (!editable && parsed.body) editable = parsed.body.querySelector('div[style*="background:#fff"], div[style*="background: #fff"]');
    if (!editable) editable = parsed.body;
    return { document: parsed, editable: editable, hasDoctype: /<!doctype\s+html/i.test(value) };
}

function accountEmailEditableHtml(value) {
    var parsed = parseAccountEmailDocument(value);
    return parsed && parsed.editable ? parsed.editable.innerHTML : String(value || '');
}

function composeAccountEmailDocument(template, editorHtml) {
    var parsed = parseAccountEmailDocument(template);
    if (!parsed || !parsed.editable) return String(editorHtml || '');
    parsed.editable.innerHTML = String(editorHtml || '');
    return (parsed.hasDoctype ? '<!doctype html>' : '') + parsed.document.documentElement.outerHTML;
}

function setAccountEmailEditorValue(textarea, value) {
    if (!textarea) return;
    var documentValue = String(value || '');
    var editableValue = accountEmailEditableHtml(documentValue);
    textarea.value = documentValue;
    textarea.accountEmailDocumentTemplate = documentValue;
    textarea.accountEmailInitialEditorHtml = editableValue;
    if (textarea.quillInstance) {
        textarea.quillInstance.clipboard.dangerouslyPasteHTML(editableValue, 'silent');
        textarea.accountEmailInitialEditorHtml = textarea.quillInstance.root.innerHTML;
    }
}

function syncAccountEmailEditor(textarea) {
    if (!textarea) return;
    var editorHtml = '';
    if (textarea.quillInstance) {
        editorHtml = textarea.quillInstance.root.innerHTML;
    } else {
        return;
    }
    if (editorHtml === textarea.accountEmailInitialEditorHtml) return;
    textarea.value = composeAccountEmailDocument(textarea.accountEmailDocumentTemplate || textarea.value, editorHtml);
}

function syncAllAccountEmailEditors() {
    document.querySelectorAll('textarea.account-email-body').forEach(syncAccountEmailEditor);
}


function initAccountEmailQuill(textarea) {
    if (!textarea || textarea.dataset.accountEmailEditorInit === '1') return;
    textarea.dataset.accountEmailEditorInit = '1';
    var wrapper = document.createElement('div');
    wrapper.className = 'quill-container account-email-quill-container';
    var editor = document.createElement('div');
    wrapper.appendChild(editor);
    textarea.insertAdjacentElement('afterend', wrapper);
    textarea.classList.add('ui-admin-hidden');
    var quill = new Quill(editor, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['link', 'image', 'video'],
                ['clean']
            ]
        }
    });
    var sourceDocument = textarea.value || '';
    var editableHtml = accountEmailEditableHtml(sourceDocument);
    textarea.accountEmailDocumentTemplate = sourceDocument;
    if (editableHtml) {
        try {
            quill.setContents(quill.clipboard.convert(editableHtml), 'silent');
        } catch (error) {
            quill.clipboard.dangerouslyPasteHTML(editableHtml, 'silent');
        }
    }
    textarea.accountEmailInitialEditorHtml = quill.root.innerHTML;
    quill.on('text-change', function() {
        syncAccountEmailEditor(textarea);
        var card = textarea.closest('[data-account-email-card]');
        if (card && card.querySelector('[data-account-email-preview] iframe')) refreshAccountEmailPreview(card);
    });
    textarea.quillInstance = quill;
}

function ensureAccountEmailRichEditors(attempt) {
    if (accountEmailEditorInitStarted && document.querySelector('textarea.account-email-body[data-account-email-editor-init="1"]')) return;
    attempt = Number(attempt || 0);
    if (typeof window.Quill === 'undefined' && attempt < 8) {
        window.setTimeout(function() { ensureAccountEmailRichEditors(attempt + 1); }, 150);
        return;
    }
    accountEmailEditorInitStarted = true;
    document.querySelectorAll('textarea.account-email-body').forEach(function(textarea) {
        if (typeof window.Quill !== 'undefined') {
            initAccountEmailQuill(textarea);
        } else {
            console.error('Quill is not loaded. Account email editor cannot be initialized.');
        }
    });
}

function refreshAccountEmailPreview(card) {
    if (!card) return;
    var body = card.querySelector('.account-email-body');
    var preview = card.querySelector('[data-account-email-preview]');
    if (!body || !preview) return;
    syncAccountEmailEditor(body);
    var html = String(body.value || '');
    var samples = {
        site_name: 'Türk Mod', username: 'Test Kullanıcısı', recipient_email: 'test@example.com',
        action_url: '#', login_url: '#', profile_url: '#', expires_minutes: '60',
        old_email: 'eski@example.com', new_email: 'yeni@example.com',
        actor_context: 'Hesap sahibi', ip_address: '127.0.0.1', date_time: '12.07.2026 23:30', support_email: 'info@example.com'
    };
    Object.keys(samples).forEach(function(key) {
        html = html.split('{{' + key + '}}').join(samples[key]);
    });
    var frame = preview.querySelector('iframe');
    if (!frame) {
        frame = document.createElement('iframe');
        frame.className = 'account-email-preview-frame';
        frame.setAttribute('sandbox', '');
        frame.setAttribute('title', 'E-posta şablonu önizlemesi');
        preview.replaceChildren(frame);
    }
    frame.srcdoc = html;
}

document.querySelectorAll('[data-account-email-card]').forEach(function(card) {
    var body = card.querySelector('.account-email-body');
    if (body) body.addEventListener('input', function() {
        if (card.querySelector('[data-account-email-preview] iframe')) refreshAccountEmailPreview(card);
    });
    var previewButton = card.querySelector('.account-email-preview-button');
    if (previewButton) previewButton.addEventListener('click', function() { refreshAccountEmailPreview(card); });
    card.querySelectorAll('.account-email-token').forEach(function(button) {
        button.addEventListener('click', function() {
            if (!body) return;
            var token = String(button.getAttribute('data-token') || '');
            if (body.quillInstance) {
                var range = body.quillInstance.getSelection(true);
                var index = range ? range.index : Math.max(0, body.quillInstance.getLength() - 1);
                body.quillInstance.insertText(index, token, 'user');
                body.quillInstance.setSelection(index + token.length, 0, 'silent');
                syncAccountEmailEditor(body);
                refreshAccountEmailPreview(card);
                return;
            }
            var start = body.selectionStart || body.value.length;
            var end = body.selectionEnd || body.value.length;
            body.value = body.value.slice(0, start) + token + body.value.slice(end);
            body.focus();
            body.selectionStart = body.selectionEnd = start + token.length;
            refreshAccountEmailPreview(card);
        });
    });
    var resetButton = card.querySelector('.account-email-reset');
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            var subject = card.querySelector('input[name$="_subject"]');
            var defaultBody = card.querySelector('.account-email-default-body');
            if (subject) subject.value = String(resetButton.getAttribute('data-default-subject') || '');
            if (body && defaultBody) {
                setAccountEmailEditorValue(body, defaultBody.value);
            }
            refreshAccountEmailPreview(card);
        });
    }
});

// Track active tab for form submission
document.getElementById('settingsForm').addEventListener('submit', function(e){
    e.preventDefault();
    e.stopPropagation();

    var saveBtn = document.querySelector('.ui-admin-btn-save-enhanced');
    var submitter = e.submitter || document.activeElement;
    if (!submitter || submitter === document.body || submitter.tagName !== 'BUTTON' || submitter.type !== 'submit') {
        submitter = saveBtn;
    }
    var submitAction = submitter && submitter.name === 'action'
        ? String(submitter.value || '')
        : 'save_settings';
    var isSettingsSave = submitAction === 'save_settings';

    if (isSettingsSave) {
        updateUserUploadDependentSettings();
        updateDownloadAccessDurationSettings();
        if (!validateUserUploadSettingLogic()) {
            return;
        }

        document.querySelectorAll('.user-upload-setting-disabled input, .user-upload-setting-disabled select, .user-upload-setting-disabled textarea').forEach(function(field) {
            field.disabled = false;
        });
    }

    var active = document.querySelector('.settings-tabs .nav-link.active');
    if(active) document.getElementById('activeTabInput').value = active.getAttribute('href').replace('#','');

    var submitButtonState = null;
    if (submitter && window.adminAsync) {
        submitButtonState = window.adminAsync.setButtonLoading(submitter, {
            className: 'loading',
            iconClass: submitter === saveBtn ? 'bi bi-arrow-repeat' : 'bi bi-arrow-repeat me-1',
            loadingText: submitter === saveBtn ? 'Kaydediliyor...' : 'Gonderiliyor...',
            iconSelector: submitter === saveBtn ? '.btn-icon-wrapper i' : '',
            textSelector: submitter === saveBtn ? '.btn-text' : ''
        });
    }

    syncAllAccountEmailEditors();
    var formData = new FormData(this);
    formData.set('action', submitAction);
    if (submitter && submitter.hasAttribute('data-account-email-template')) {
        var accountTemplateKey = String(submitter.getAttribute('data-account-email-template') || '');
        var accountCard = submitter.closest('[data-account-email-card]');
        var accountRecipient = accountCard ? accountCard.querySelector('input[type="email"]') : null;
        formData.set('account_email_template_key', accountTemplateKey);
        formData.set('account_email_test_recipient', accountRecipient ? accountRecipient.value : '');
    }
    formData.append('ajax', '1');

    window.adminFetchJson('settings.php', {
        method: 'POST',
        body: formData,
        notifyError: false
    })
    .then(function(response) {
        return response || {};
    })
    .then(data => {
        if (window.adminAsync) {
            window.adminAsync.restoreButton(submitButtonState);
        }
        if (data.success && submitter && window.adminAsync) {
            window.adminAsync.markSuccess(submitter);
        }
        
        if (typeof window.showToast === 'function') {
            window.showToast(data.message || 'İşlem tamamlandı', data.success ? 'success' : 'error');
        }
    })
    .catch(error => {
        if (window.adminAsync) {
            window.adminAsync.restoreButton(submitButtonState);
        }
        if (window.adminNotifyError) {
            window.adminNotifyError(error, 'Bir hata olustu. Lutfen tekrar deneyin.');
            return;
        }
        console.error('Save error:', error);
        if (typeof window.showToast === 'function') {
            window.showToast('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
        }
    });
});

activateSettingsSubtabFromHash();
}

function initSearchableMultiselects() {
    document.querySelectorAll('[data-admin-searchable-multiselect]').forEach(function(wrapper) {
        if (wrapper.dataset.searchReady === '1') {
            return;
        }

        var search = wrapper.querySelector('[data-admin-multiselect-search]');
        var select = wrapper.querySelector('[data-admin-multiselect-list]');
        if (!search || !select) {
            return;
        }

        wrapper.dataset.searchReady = '1';

        var maxVisible = parseInt(wrapper.getAttribute('data-admin-multiselect-max-visible') || '0', 10);
        if (!Number.isFinite(maxVisible) || maxVisible < 1) {
            maxVisible = 0;
        }

        var options = Array.prototype.slice.call(select.options || []);
        options.forEach(function(option) {
            option.dataset.searchText = normalizeSettingsSearchText(option.textContent + ' ' + option.value);
        });

        function applyFilter() {
            var query = normalizeSettingsSearchText(search.value);
            var visibleCount = 0;
            options.forEach(function(option) {
                var matches = query === '' || option.dataset.searchText.indexOf(query) !== -1;
                if (matches) {
                    visibleCount += 1;
                }
                option.hidden = !matches || (maxVisible > 0 && visibleCount > maxVisible);
            });
        }

        search.addEventListener('input', applyFilter);
        search.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        });
        applyFilter();
    });
}

function normalizeSettingsSearchText(value) {
    return String(value || '')
        .toLocaleLowerCase('tr-TR')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[\u00e7\u00c7]/g, 'c')
        .replace(/[\u011f\u011e]/g, 'g')
        .replace(/[\u0131\u0130]/g, 'i')
        .replace(/[\u00f6\u00d6]/g, 'o')
        .replace(/[\u015f\u015e]/g, 's')
        .replace(/[\u00fc\u00dc]/g, 'u');
}

// Success animation on page load if there's a success message
function initSettingsPageEnhancements() {
    initSettingsPageMainBindings();
    initSearchableMultiselects();
    var successMsg = document.querySelector('.alert-success');
    var saveBtn = document.querySelector('.ui-admin-btn-save-enhanced');

    if (successMsg && saveBtn) {
        saveBtn.classList.add('success');
        setTimeout(function() {
            saveBtn.classList.remove('success');
        }, 2000);
    }

    initSettingsTooltips();
    initConditionalSettingsFields();
}

window.adminPage.register('settings', initSettingsPageEnhancements, {
    id: 'settings-page:main',
    selector: '#settingsForm'
});

function initConditionalSettingsFields() {
    var fields = document.querySelectorAll('[data-setting-enabled-when]');

    fields.forEach(function(field) {
        var controllerName = field.getAttribute('data-setting-enabled-when');
        var enabledValue = field.getAttribute('data-setting-enabled-value') || '1';
        var controller = controllerName ? document.querySelector('[name="' + controllerName + '"]') : null;
        var control = field.querySelector('input:not([type="hidden"]), select, textarea');

        if (!controller || !control) return;

        var sync = function() {
            var controllerValue = controller.type === 'checkbox'
                ? (controller.checked ? (controller.value || '1') : '0')
                : controller.value;
            var inactive = String(controllerValue) !== String(enabledValue);

            field.classList.toggle('is-conditionally-disabled', inactive);
            control.readOnly = inactive;
            control.setAttribute('aria-disabled', inactive ? 'true' : 'false');
        };

        controller.addEventListener('change', sync);
        sync();
    });
}

function initSettingsTooltips() {
    var tooltipTriggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    var activeTooltip = null;

    function removeTooltip() {
        if (activeTooltip) {
            activeTooltip.remove();
            activeTooltip = null;
        }
    }

    function showTooltip(trigger) {
        var tooltipText = trigger.getAttribute('data-bs-title');
        if (!tooltipText) return;

        removeTooltip();

        var tooltip = document.createElement('div');
        tooltip.className = 'custom-tooltip custom-tooltip-hover';
        tooltip.textContent = tooltipText;
        document.body.appendChild(tooltip);

        var rect = trigger.getBoundingClientRect();
        var tooltipRect = tooltip.getBoundingClientRect();
        var left = rect.left + (rect.width / 2) - (tooltipRect.width / 2);
        var top = rect.top - tooltipRect.height - 8;

        if (left < 10) left = 10;
        if (left + tooltipRect.width > window.innerWidth - 10) {
            left = window.innerWidth - tooltipRect.width - 10;
        }
        if (top < 10) {
            top = rect.bottom + 8;
        }

        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';
        activeTooltip = tooltip;
    }

    tooltipTriggers.forEach(function(trigger) {
        trigger.setAttribute('tabindex', trigger.getAttribute('tabindex') || '0');
        trigger.setAttribute('role', trigger.getAttribute('role') || 'img');
        trigger.setAttribute('aria-label', trigger.getAttribute('data-bs-title') || 'Ayar açıklaması');

        trigger.addEventListener('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            removeTooltip();
        });
        trigger.addEventListener('mouseenter', function() { showTooltip(trigger); });
        trigger.addEventListener('mouseleave', removeTooltip);
        trigger.addEventListener('focusin', function() { showTooltip(trigger); });
        trigger.addEventListener('focusout', removeTooltip);
    });

    window.addEventListener('scroll', removeTooltip, { passive: true });
    window.addEventListener('resize', removeTooltip);
}
