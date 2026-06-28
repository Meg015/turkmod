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

// Track active tab for form submission
document.getElementById('settingsForm').addEventListener('submit', function(e){
    e.preventDefault();
    e.stopPropagation();

    updateUserUploadDependentSettings();
    if (!validateUserUploadSettingLogic()) {
        return;
    }

    document.querySelectorAll('.user-upload-setting-disabled input, .user-upload-setting-disabled select, .user-upload-setting-disabled textarea').forEach(function(field) {
        field.disabled = false;
    });

    var active = document.querySelector('.settings-tabs .nav-link.active');
    if(active) document.getElementById('activeTabInput').value = active.getAttribute('href').replace('#','');

    // Add loading state to save button
    var saveBtn = document.querySelector('.ui-admin-btn-save-enhanced');
    var originalIcon = '';
    var originalText = '';
    if (saveBtn) {
        saveBtn.classList.add('loading');
        var iconEl = saveBtn.querySelector('.btn-icon-wrapper i');
        var textEl = saveBtn.querySelector('.btn-text');
        originalIcon = iconEl ? iconEl.className : '';
        originalText = textEl ? textEl.textContent : saveBtn.textContent;
        if (iconEl) iconEl.className = 'bi bi-arrow-repeat';
        if (textEl) textEl.textContent = 'Kaydediliyor...';
    }

    var formData = new FormData(this);
    formData.append('ajax', '1');

    fetch('settings.php', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (saveBtn) {
            saveBtn.classList.remove('loading');
            var restoreIcon = saveBtn.querySelector('.btn-icon-wrapper i');
            var restoreText = saveBtn.querySelector('.btn-text');
            if (restoreIcon && originalIcon) restoreIcon.className = originalIcon;
            if (restoreText) restoreText.textContent = originalText;
            
            if (data.success) {
                saveBtn.classList.add('success');
                setTimeout(() => saveBtn.classList.remove('success'), 2000);
            }
        }
        
        if (typeof window.showToast === 'function') {
            window.showToast(data.message || 'İşlem tamamlandı', data.success ? 'success' : 'error');
        }
    })
    .catch(error => {
        if (saveBtn) {
            saveBtn.classList.remove('loading');
            var restoreIcon = saveBtn.querySelector('.btn-icon-wrapper i');
            var restoreText = saveBtn.querySelector('.btn-text');
            if (restoreIcon && originalIcon) restoreIcon.className = originalIcon;
            if (restoreText) restoreText.textContent = originalText;
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
