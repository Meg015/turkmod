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
        if (target === 'email-tab-account') {
            ensureAccountEmailRichEditors();
        }
    });
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

function captureButtonState(button) {
    if (!button) {
        return null;
    }

    return {
        button: button,
        html: button.innerHTML,
        disabled: button.disabled,
        className: button.className
    };
}

function setGenericButtonLoading(button, label) {
    if (!button) {
        return null;
    }

    var state = captureButtonState(button);
    button.disabled = true;
    button.classList.add('loading');
    button.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i>' + label;
    return state;
}

function restoreButtonState(state) {
    if (!state || !state.button) {
        return;
    }

    state.button.innerHTML = state.html;
    state.button.disabled = state.disabled;
    state.button.className = state.className;
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
    if (textarea.accountEmailFallbackEditor) {
        textarea.accountEmailFallbackEditor.innerHTML = editableValue;
        textarea.accountEmailInitialEditorHtml = textarea.accountEmailFallbackEditor.innerHTML;
    }
}

function syncAccountEmailEditor(textarea) {
    if (!textarea) return;
    var editorHtml = '';
    if (textarea.quillInstance) {
        editorHtml = textarea.quillInstance.root.innerHTML;
    } else if (textarea.accountEmailFallbackEditor) {
        editorHtml = textarea.accountEmailFallbackEditor.innerHTML;
    } else {
        return;
    }
    if (editorHtml === textarea.accountEmailInitialEditorHtml) return;
    textarea.value = composeAccountEmailDocument(textarea.accountEmailDocumentTemplate || textarea.value, editorHtml);
}

function syncAllAccountEmailEditors() {
    document.querySelectorAll('textarea.account-email-body').forEach(syncAccountEmailEditor);
}

function initAccountEmailFallback(textarea) {
    if (!textarea || textarea.dataset.accountEmailEditorInit === '1') return;
    textarea.dataset.accountEmailEditorInit = '1';
    var shell = document.createElement('div');
    shell.className = 'account-email-fallback-shell';
    var toolbar = document.createElement('div');
    toolbar.className = 'account-email-fallback-toolbar';
    [
        ['bold', 'Kalın', 'B'], ['italic', 'İtalik', 'I'], ['underline', 'Altı çizili', 'U'],
        ['insertOrderedList', 'Numaralı liste', '1.'], ['insertUnorderedList', 'Madde listesi', '•']
    ].forEach(function(item) {
        var button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('aria-label', item[1]);
        button.textContent = item[2];
        button.addEventListener('click', function() {
            editor.focus();
            document.execCommand(item[0], false, null);
            syncAccountEmailEditor(textarea);
        });
        toolbar.appendChild(button);
    });
    var editor = document.createElement('div');
    editor.className = 'account-email-fallback-editor';
    editor.contentEditable = 'true';
    var sourceDocument = textarea.value || '';
    var editableHtml = accountEmailEditableHtml(sourceDocument);
    textarea.accountEmailDocumentTemplate = sourceDocument;
    textarea.accountEmailInitialEditorHtml = editableHtml;
    editor.innerHTML = editableHtml;
    editor.addEventListener('input', function() {
        syncAccountEmailEditor(textarea);
        var card = textarea.closest('[data-account-email-card]');
        if (card && card.querySelector('[data-account-email-preview] iframe')) refreshAccountEmailPreview(card);
    });
    shell.appendChild(toolbar);
    shell.appendChild(editor);
    textarea.insertAdjacentElement('afterend', shell);
    textarea.classList.add('ui-admin-hidden');
    textarea.accountEmailFallbackEditor = editor;
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
        if (typeof window.Quill !== 'undefined') initAccountEmailQuill(textarea);
        else initAccountEmailFallback(textarea);
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
            if (body.accountEmailFallbackEditor) {
                body.accountEmailFallbackEditor.focus();
                document.execCommand('insertText', false, token);
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

    var originalIcon = '';
    var originalText = '';
    var genericSubmitState = null;
    if (submitter && submitter === saveBtn) {
        saveBtn.classList.add('loading');
        var iconEl = saveBtn.querySelector('.btn-icon-wrapper i');
        var textEl = saveBtn.querySelector('.btn-text');
        originalIcon = iconEl ? iconEl.className : '';
        originalText = textEl ? textEl.textContent : saveBtn.textContent;
        if (iconEl) iconEl.className = 'bi bi-arrow-repeat';
        if (textEl) textEl.textContent = 'Kaydediliyor...';
    } else if (submitter) {
        genericSubmitState = setGenericButtonLoading(submitter, 'Gönderiliyor...');
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

    fetch('settings.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(response) {
        return response.text().then(function(responseBody) {
            var data;
            try {
                data = JSON.parse(responseBody);
            } catch (parseError) {
                throw new Error('Sunucu e-posta işlemi için geçersiz bir yanıt döndürdü.');
            }

            if (!response.ok && data.success !== false) {
                data.success = false;
            }

            return data;
        });
    })
    .then(data => {
        if (submitter && submitter === saveBtn) {
            saveBtn.classList.remove('loading');
            var restoreIcon = saveBtn.querySelector('.btn-icon-wrapper i');
            var restoreText = saveBtn.querySelector('.btn-text');
            if (restoreIcon && originalIcon) restoreIcon.className = originalIcon;
            if (restoreText) restoreText.textContent = originalText;
            
            if (data.success) {
                saveBtn.classList.add('success');
                setTimeout(() => saveBtn.classList.remove('success'), 2000);
            }
        } else {
            restoreButtonState(genericSubmitState);
            if (data.success && submitter) {
                submitter.classList.add('success');
                setTimeout(function() {
                    if (submitter) {
                        submitter.classList.remove('success');
                    }
                }, 2000);
            }
        }
        
        if (typeof window.showToast === 'function') {
            window.showToast(data.message || 'İşlem tamamlandı', data.success ? 'success' : 'error');
        }
    })
    .catch(error => {
        if (submitter && submitter === saveBtn) {
            saveBtn.classList.remove('loading');
            var restoreIcon = saveBtn.querySelector('.btn-icon-wrapper i');
            var restoreText = saveBtn.querySelector('.btn-text');
            if (restoreIcon && originalIcon) restoreIcon.className = originalIcon;
            if (restoreText) restoreText.textContent = originalText;
        } else {
            restoreButtonState(genericSubmitState);
        }
        console.error('Save error:', error);
        if (typeof window.showToast === 'function') {
            window.showToast('Bir hata oluştu. Lütfen tekrar deneyin.', 'error');
        }
    });
});

// Success animation on page load if there's a success message
document.addEventListener('DOMContentLoaded', function() {
    var successMsg = document.querySelector('.alert-success');
    var saveBtn = document.querySelector('.ui-admin-btn-save-enhanced');

    if (successMsg && saveBtn) {
        saveBtn.classList.add('success');
        setTimeout(function() {
            saveBtn.classList.remove('success');
        }, 2000);
    }

    initSettingsTooltips();
});

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
