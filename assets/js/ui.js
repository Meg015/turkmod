/* ============================================================
   UI.JS - User Interface Components & Interactions
   Consolidated from: public-ui.js, theme.js, toast.js,
   navbar-dropdown.js, mobile-sidebar.js, footer-ui.js,
   search-autocomplete.js
   ============================================================ */

/* ============================================================
   SECTION 1: THEME CONTROLLER
   ============================================================ */

/**
 * Public theme controller.
 * Resolves the stored/site theme mode before paint and keeps the toggle icon in sync.
 */
(function () {
    'use strict';

    var storageKey = 'theme';
    var modeStorageKey = 'theme-mode';
    var systemQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function safeStorageGet(key) {
        try {
            if (!window.localStorage) {
                return '';
            }
            return localStorage.getItem(key) || '';
        } catch (error) {
            return '';
        }
    }

    function safeStorageSet(key, value) {
        try {
            if (!window.localStorage) {
                return;
            }
            localStorage.setItem(key, value);
        } catch (error) {}
    }

    function normalizeMode(value) {
        return value === 'light' || value === 'dark' || value === 'auto' ? value : 'auto';
    }

    function getConfiguredMode() {
        var html = document.documentElement;
        var siteMode = normalizeMode(html.getAttribute('data-theme-mode'));
        var rawStoredMode = safeStorageGet(modeStorageKey) || safeStorageGet(storageKey);
        var storedMode = normalizeMode(rawStoredMode);

        return rawStoredMode
            ? storedMode
            : siteMode;
    }

    function resolveTheme(mode) {
        var normalizedMode = normalizeMode(mode);
        if (normalizedMode === 'auto') {
            return systemQuery && systemQuery.matches ? 'dark' : 'light';
        }

        return normalizedMode;
    }

    function updateThemeIcon(theme, mode) {
        var icon = document.getElementById('theme-icon');
        if (!icon) {
            return;
        }

        icon.className = theme === 'dark' ? 'bi bi-lightbulb' : 'bi bi-moon-stars-fill';
        icon.setAttribute('data-theme-mode', normalizeMode(mode));
    }

    function applyTheme(mode) {
        var normalizedMode = normalizeMode(mode);
        var theme = resolveTheme(normalizedMode);
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme-mode', normalizedMode);
        document.documentElement.setAttribute('data-bs-theme', theme);
        updateThemeIcon(theme, normalizedMode);
        return theme;
    }

    window.toggleTheme = function () {
        var currentTheme = document.documentElement.getAttribute('data-theme') || resolveTheme(getConfiguredMode());
        var nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        safeStorageSet(storageKey, nextTheme);
        safeStorageSet(modeStorageKey, nextTheme);
        applyTheme(nextTheme);
    };

    applyTheme(getConfiguredMode());

    if (systemQuery) {
        var handleSystemChange = function () {
            if (getConfiguredMode() === 'auto') {
                applyTheme('auto');
            }
        };

        if (systemQuery.addEventListener) {
            systemQuery.addEventListener('change', handleSystemChange);
        } else if (systemQuery.addListener) {
            systemQuery.addListener(handleSystemChange);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(getConfiguredMode());
        document.querySelectorAll('.theme-toggle').forEach(function (button) {
            button.addEventListener('click', window.toggleTheme);
        });
    });
})();

/* ============================================================
   SECTION 2: DIALOG / CONFIRM / PROMPT SYSTEM
   appConfirm and appPrompt are used by other JS files as
   a lightweight Promise-based dialog fallback. Toast system
   is in ui-foundation.js (definitive version).
   ============================================================ */

// Global toast helper. Container element must exist (rendered by public-footer.php).
// Usage: showToast('Message', 'success'|'error'|'warning'|'info', durationMs)
(function () {
    'use strict';

    function appDialogEscape(value) {
        return String(value || '').replace(/[&<>"]/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char];
        });
    }

    function closeAppDialog(dialog, value, resolve) {
        dialog.classList.add('is-closing');
        setTimeout(function () {
            dialog.remove();
            document.body.classList.remove('app-dialog-open');
            resolve(value);
        }, 160);
    }

    function createAppDialog(options) {
        options = options || {};

        return new Promise(function (resolve) {
            var dialog = document.createElement('div');
            var needsInput = options.input === true;
            dialog.className = 'app-dialog-overlay';
            dialog.setAttribute('role', 'presentation');
            dialog.innerHTML = [
                '<div class="app-dialog" role="dialog" aria-modal="true" aria-labelledby="appDialogTitle">',
                    '<div class="app-dialog-icon"><i class="bi ', appDialogEscape(options.icon || 'bi-question-circle'), '"></i></div>',
                    '<div class="app-dialog-copy">',
                        '<h3 id="appDialogTitle">', appDialogEscape(options.title || 'Onay gerekiyor'), '</h3>',
                        options.message ? '<p>' + appDialogEscape(options.message) + '</p>' : '',
                    '</div>',
                    needsInput ? '<input class="app-dialog-input" type="' + appDialogEscape(options.type || 'text') + '" value="' + appDialogEscape(options.value || '') + '" placeholder="' + appDialogEscape(options.placeholder || '') + '">' : '',
                    '<div class="app-dialog-actions">',
                        '<button type="button" class="app-dialog-btn app-dialog-cancel">', appDialogEscape(options.cancel || 'Vazgeç'), '</button>',
                        '<button type="button" class="app-dialog-btn app-dialog-ok">', appDialogEscape(options.ok || 'Onayla'), '</button>',
                    '</div>',
                '</div>'
            ].join('');

            document.body.appendChild(dialog);
            document.body.classList.add('app-dialog-open');

            var input = dialog.querySelector('.app-dialog-input');
            var ok = dialog.querySelector('.app-dialog-ok');
            var cancel = dialog.querySelector('.app-dialog-cancel');

            function resolveCancel() { closeAppDialog(dialog, needsInput ? null : false, resolve); }
            function resolveOk() { closeAppDialog(dialog, needsInput ? (input.value || '').trim() : true, resolve); }

            cancel.addEventListener('click', resolveCancel);
            ok.addEventListener('click', resolveOk);
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) resolveCancel();
            });
            dialog.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') resolveCancel();
                if (event.key === 'Enter') resolveOk();
            });

            setTimeout(function () {
                (input || ok).focus();
            }, 0);
        });
    }

    window.appConfirm = function (message, options) {
        return createAppDialog(Object.assign({
            title: 'İşlem onayı',
            message: message,
            ok: 'Onayla',
            icon: 'bi-exclamation-circle'
        }, options || {}));
    };

    window.appPrompt = function (message, options) {
        return createAppDialog(Object.assign({
            title: message || 'Bilgi girin',
            message: '',
            ok: 'Ekle',
            input: true,
            type: 'url',
            placeholder: 'https://',
            icon: 'bi-link-45deg'
        }, options || {}));
    };

    document.addEventListener('submit', function (event) {
        var form = event.target && event.target.closest ? event.target.closest('form[data-app-confirm]') : null;
        if (!form || form.dataset.appConfirmed === '1') return;

        event.preventDefault();
        window.appConfirm(form.dataset.appConfirm, {
            title: form.dataset.appConfirmTitle || 'İşlem onayı',
            ok: form.dataset.appConfirmOk || 'Onayla',
            icon: form.dataset.appConfirmIcon || 'bi-exclamation-circle'
        }).then(function (confirmed) {
            if (!confirmed) return;
            form.dataset.appConfirmed = '1';
            form.submit();
        });
    });

    // Auto-fire flash messages from data attributes on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('toastContainer');
        if (!container) return;
        if (container.dataset.uiFoundationFlashDispatched === '1') return;
        container.dataset.uiFoundationFlashDispatched = '1';

        [
            ['success', container.getAttribute('data-toast-success')],
            ['error', container.getAttribute('data-toast-error')],
            ['warning', container.getAttribute('data-toast-warning')],
            ['info', container.getAttribute('data-toast-info')]
        ].forEach(function (entry) {
            if (entry[1]) {
                window.showToast(entry[1], entry[0]);
            }
        });
    });
})();

/* ============================================================
   SECTION 3: PUBLIC UI BEHAVIORS
   ============================================================ */

// Public-facing UI behaviors: form validation, topic card navigation,
// profile dropdown, Quill rich editor init.
(function () {
    'use strict';

    // URL güvenlik kontrolü: sadece relative veya aynı origin URL'lere izin ver
    function isSafeUrl(url) {
        if (!url) return false;
        // Relative URL'ler güvenli
        if (url.charAt(0) === '/' || url.charAt(0) === '.') return true;
        // javascript: ve data: protokollerini engelle
        var lower = url.toLowerCase().trim();
        if (lower.indexOf('javascript:') === 0 || lower.indexOf('data:') === 0 || lower.indexOf('vbscript:') === 0) return false;
        // Aynı origin kontrolü
        try {
            var parsed = new URL(url, window.location.origin);
            return parsed.origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function init() {
        initFormValidation();
        initFormLoadingStates();
        initAuthEnhancements();
        initTopicCards();
        initQuillEditors();
        initCategoryToggles();
        initWidgetToggles();
        initAuthPopover();
    }

    function initAuthPopover() {
        var trigger = document.querySelector('[data-auth-popover-trigger]');
        var panel = document.getElementById('authPopoverPanel');
        if (!trigger || !panel) return;

        function openPanel() {
            panel.hidden = false;
            window.requestAnimationFrame(function () {
                panel.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                var firstLink = panel.querySelector('a, button');
                if (firstLink) firstLink.focus();
            });
        }

        function closePanel() {
            panel.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            window.setTimeout(function () {
                if (!panel.classList.contains('is-open')) {
                    panel.hidden = true;
                }
            }, 180);
        }

        function togglePanel() {
            if (panel.hidden || !panel.classList.contains('is-open')) {
                openPanel();
            } else {
                closePanel();
            }
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            togglePanel();
        });

        document.addEventListener('click', function (event) {
            if (!panel.hidden && !panel.contains(event.target) && event.target !== trigger && !trigger.contains(event.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) {
                closePanel();
                trigger.focus();
            }
            if (event.key === 'Tab' && !panel.hidden && panel.classList.contains('is-open')) {
                var focusables = Array.prototype.slice.call(panel.querySelectorAll('a, button')).filter(function (item) {
                    return !item.disabled && item.offsetParent !== null;
                });
                if (!focusables.length) return;
                var first = focusables[0];
                var last = focusables[focusables.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    }

    function initFormLoadingStates() {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.loadingInit === '1' || form.matches('[data-no-loading-state], .ttb-favorite-form')) return;
            form.dataset.loadingInit = '1';

            form.addEventListener('submit', function () {
                if (form.dataset.clientInvalid === '1') {
                    form.dataset.clientInvalid = '0';
                    return;
                }

                var submitter = form.querySelector('button[type="submit"], input[type="submit"]');
                if (!submitter || submitter.disabled) return;

                submitter.dataset.originalHtml = submitter.innerHTML;
                submitter.disabled = true;
                submitter.classList.add('is-submitting');
                submitter.setAttribute('aria-busy', 'true');
                if (submitter.tagName.toLowerCase() === 'button') {
                    submitter.innerHTML = '<span>Gönderiliyor...</span><i class="bi bi-arrow-repeat" aria-hidden="true"></i>';
                }

                window.setTimeout(function () {
                    if (!submitter.isConnected) return;
                    submitter.disabled = false;
                    submitter.classList.remove('is-submitting');
                    submitter.setAttribute('aria-busy', 'false');
                    if (submitter.dataset.originalHtml) submitter.innerHTML = submitter.dataset.originalHtml;
                }, 12000);
            });
        });
    }

    function initAuthEnhancements() {
        document.querySelectorAll('.auth-input-shell input[type="password"]').forEach(function (input) {
            if (input.dataset.passwordToggleInit === '1') return;
            input.dataset.passwordToggleInit = '1';

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'auth-password-toggle';
            toggle.setAttribute('aria-label', 'Şifreyi göster');
            toggle.setAttribute('aria-pressed', 'false');
            toggle.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
            input.parentNode.appendChild(toggle);

            toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-label', show ? 'Şifreyi gizle' : 'Şifreyi göster');
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                toggle.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '" aria-hidden="true"></i>';
                input.focus();
            });
        });

        function initPasswordStrengthMeter(passwordInput) {
            if (!passwordInput || passwordInput.dataset.strengthInit === '1') return;
            passwordInput.dataset.strengthInit = '1';

            var minLength = Number(passwordInput.getAttribute('minlength') || 8);
            var confirmSelector = passwordInput.getAttribute('data-password-confirm') || '';
            var confirmPassword = confirmSelector
                ? document.querySelector(confirmSelector)
                : document.querySelector('.auth-screen-register input[name="password_confirm"]');
            var requireUpper = passwordInput.getAttribute('data-password-require-uppercase') !== '0';
            var requireNumber = passwordInput.getAttribute('data-password-require-numbers') !== '0';
            var requireSpecial = passwordInput.getAttribute('data-password-require-special') === '1';

            var rules = [['length', 'En az ' + minLength + ' karakter']];
            if (requireUpper) rules.push(['upper', 'Büyük harf']);
            if (requireNumber) rules.push(['number', 'Rakam']);
            if (requireSpecial) rules.push(['special', 'Özel karakter']);
            if (confirmPassword) rules.push(['match', 'Şifreler eşleşiyor']);

            var meter = document.createElement('div');
            meter.className = 'auth-password-rules';
            meter.setAttribute('aria-live', 'polite');
            meter.innerHTML = rules.map(function (rule) {
                return '<span data-rule="' + rule[0] + '"><i class="bi bi-circle" aria-hidden="true"></i>' + rule[1] + '</span>';
            }).join('');

            var field = passwordInput.closest('.auth-field') || passwordInput.closest('.profile-form-group') || passwordInput.closest('.form-group');
            if (field) field.appendChild(meter);

            function updateRules() {
                var value = passwordInput.value || '';
                var confirm = confirmPassword ? confirmPassword.value || '' : '';
                var checks = {
                    length: value.length >= minLength,
                    upper: /[A-ZÇĞİÖŞÜ]/u.test(value),
                    number: /\d/.test(value),
                    special: /[^A-Za-z0-9ÇĞİÖŞÜçğıöşü]/u.test(value),
                    match: value.length > 0 && confirm.length > 0 && value === confirm
                };

                Object.keys(checks).forEach(function (key) {
                    var item = meter.querySelector('[data-rule="' + key + '"]');
                    if (!item) return;
                    item.classList.toggle('is-met', checks[key]);
                    var icon = item.querySelector('i');
                    if (icon) icon.className = 'bi ' + (checks[key] ? 'bi-check-circle-fill' : 'bi-circle');
                });
            }

            passwordInput.addEventListener('input', updateRules);
            if (confirmPassword) confirmPassword.addEventListener('input', updateRules);
            updateRules();
        }

        document.querySelectorAll('input[data-password-strength], .auth-screen-register input[name="password"]').forEach(initPasswordStrengthMeter);
    }

    // -- Form validation (client-side mirror of server-side validation) ----
    function initFormValidation() {
        document.querySelectorAll('form[novalidate]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var valid = true;
                form.dataset.clientInvalid = '0';

                form.querySelectorAll('[required]').forEach(function (input) {
                    var isThemeAuthInput = !!input.closest('.ui-theme-auth');
                    input.classList.remove('is-invalid');
                    if (isThemeAuthInput) input.removeAttribute('aria-invalid');
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        if (isThemeAuthInput) input.setAttribute('aria-invalid', 'true');
                        valid = false;
                    }
                    if (input.type === 'email' && input.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                        input.classList.add('is-invalid');
                        if (isThemeAuthInput) input.setAttribute('aria-invalid', 'true');
                        valid = false;
                    }
                    if (input.minLength > 0 && input.value.length < input.minLength) {
                        input.classList.add('is-invalid');
                        if (isThemeAuthInput) input.setAttribute('aria-invalid', 'true');
                        valid = false;
                    }
                });

                var pw = form.querySelector('[name="password"]');
                var pwc = form.querySelector('[name="password_confirm"]');
                if (pw && pwc && pw.value !== pwc.value) {
                    pwc.classList.add('is-invalid');
                    if (pwc.closest('.ui-theme-auth')) pwc.setAttribute('aria-invalid', 'true');
                    valid = false;
                }

                if (!valid) {
                    e.preventDefault();
                    form.dataset.clientInvalid = '1';
                    if (typeof window.showToast === 'function') {
                        window.showToast('Lütfen tüm alanları doğru doldurun.', 'error');
                    }
                }
            });
        });

        document.querySelectorAll('form [required]').forEach(function (input) {
            input.addEventListener('blur', function () {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    if (input.closest('.ui-theme-auth')) input.setAttribute('aria-invalid', 'true');
                } else {
                    input.classList.remove('is-invalid');
                    if (input.closest('.ui-theme-auth')) input.removeAttribute('aria-invalid');
                }
            });
            input.addEventListener('input', function () {
                if (input.value.trim()) {
                    input.classList.remove('is-invalid');
                    if (input.closest('.ui-theme-auth')) input.removeAttribute('aria-invalid');
                }
            });
        });
    }

    // -- Topic card click-to-navigate (keeps inner links functional) -------
    function initTopicCards() {
        var interactiveSelector = 'a, button, input, textarea, select, label';
        document.querySelectorAll('.feed-card--list[data-topic-url]').forEach(function (card) {
            if (!card.hasAttribute('tabindex')) {
                card.setAttribute('tabindex', '0');
            }
            if (!card.hasAttribute('role')) {
                card.setAttribute('role', 'link');
            }

            card.addEventListener('click', function (e) {
                if (e.target.closest(interactiveSelector)) return;
                var url = card.getAttribute('data-topic-url');
                if (url && isSafeUrl(url)) window.location.href = url;
            });

            card.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                if (e.target.closest(interactiveSelector)) return;
                e.preventDefault();
                var url = card.getAttribute('data-topic-url');
                if (url && isSafeUrl(url)) window.location.href = url;
            });
        });
    }

    // -- Quill editor on textarea.rich-editor ------------------------------
    function initQuillEditors() {
        if (typeof Quill === 'undefined') return;

        var AlignStyle = Quill.import('attributors/style/align');
        Quill.register(AlignStyle, true);

        document.querySelectorAll('.rich-editor').forEach(function (el) {
            if (el.tagName.toLowerCase() !== 'textarea') return;
            if (el.dataset._quillInit === '1') return;
            el.dataset._quillInit = '1';

            var wrapper = document.createElement('div');
            wrapper.className = 'quill-container';
            var editorDiv = document.createElement('div');
            wrapper.appendChild(editorDiv);

            el.parentNode.insertBefore(wrapper, el.nextSibling);
            el.style.display = 'none';

            var initialContent = el.value;
            var quill = new Quill(editorDiv, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        ['blockquote', 'code-block'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['link', 'image', 'video'],
                        ['clean'],
                        [{ align: [] }]
                    ]
                }
            });

            // İçeriği güvenli şekilde yükle (XSS koruması)
            if (initialContent) {
                try {
                    var delta = quill.clipboard.convert(initialContent);
                    quill.setContents(delta, 'silent');
                } catch (e) {
                    // Fallback: plain text olarak yükle
                    quill.setText(initialContent);
                }
            }

            quill.on('text-change', function () {
                el.value = quill.root.innerHTML;
            });
            el.quillInstance = quill;
        });
    }

    function initCategoryToggles() {
        document.querySelectorAll('.category-toggle').forEach(function (toggle) {
            if (toggle.dataset.catToggleInit === '1') return;
            toggle.dataset.catToggleInit = '1';
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var categoryItem = toggle.closest('.category-item');
                if (!categoryItem) return;

                var isOpen = categoryItem.classList.contains('open');

                if (isOpen) {
                    categoryItem.classList.remove('open');
                    toggle.setAttribute('aria-expanded', 'false');
                } else {
                    categoryItem.classList.add('open');
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });
        });
    }

    // -- Widget toggles (collapsible widgets) ------------------------------
    function initWidgetToggles() {
        // Global toggleWidget function for inline onclick handlers
        window.toggleWidget = function(button) {
            var widget = button.closest('.widget');
            if (!widget) return;

            var body = widget.querySelector('.widget-body');
            if (!body) return;

            var isActive = button.classList.contains('active');

            if (isActive) {
                button.classList.remove('active');
                body.style.display = 'none';
                button.setAttribute('aria-expanded', 'false');
            } else {
                button.classList.add('active');
                body.style.display = 'block';
                button.setAttribute('aria-expanded', 'true');
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/* ============================================================
   SECTION 4: NAVBAR DROPDOWN
   ============================================================ */

/**
 * Navbar Dropdown Handler - Keyboard Navigation Support
 */

(function() {
    'use strict';

    const root = document.querySelector('.topic-profile-dd');
    const btn = document.getElementById('profileDropdownBtn');
    const menu = document.getElementById('profileDropdownMenu');

    if (!btn || !menu) return;

    const items = () => Array.from(menu.querySelectorAll('.tpm-item, button.tpm-item'));

    function setOpen(open, focusFirst) {
        if (root) root.classList.toggle('is-open', open);
        menu.classList.toggle('show', open);
        menu.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open && focusFirst) {
            window.setTimeout(function () {
                const first = items()[0];
                if (first) first.focus();
            }, 20);
        }
    }

    function isOpen() {
        return !menu.hidden && menu.classList.contains('show');
    }

    menu.hidden = true;
    setOpen(false, false);

    function toggleMenu() {
        setOpen(!isOpen(), true);
    }

    btn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        toggleMenu();
    });

    btn.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true, true);
        } else if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleMenu();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false, false);
        }
    });

    menu.addEventListener('keydown', function (event) {
        const menuItems = items();
        const currentIndex = menuItems.indexOf(document.activeElement);

        if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false, false);
            btn.focus();
            return;
        }

        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;

        event.preventDefault();
        if (!menuItems.length) return;

        const nextIndex = event.key === 'ArrowDown'
            ? (currentIndex + 1) % menuItems.length
            : (currentIndex <= 0 ? menuItems.length - 1 : currentIndex - 1);
        menuItems[nextIndex].focus();
    });

    document.addEventListener('click', function (event) {
        if (isOpen() && root && !root.contains(event.target)) {
            setOpen(false, false);
        }
    });

    window.addEventListener('resize', function () {
        if (isOpen()) setOpen(false, false);
    });

    menu.addEventListener('focusout', function () {
        window.setTimeout(function () {
            if (isOpen() && !menu.contains(document.activeElement) && document.activeElement !== btn) {
                setOpen(false, false);
            }
        }, 50);
    });
})();
/* ============================================================
   SECTION 5: MOBILE SIDEBAR
   ============================================================ */

/**
 * Mobile Sidebar Toggle System
 * Handles sidebar visibility on mobile devices
 */

class MobileSidebar {
    constructor() {
        this.init();
    }

    init() {
        // Sadece mobil cihazlarda çalıştır
        if (window.innerWidth > 768) return;
        if (document.documentElement.getAttribute('data-public-theme') === 'turkmod') return;

        this.createToggleButton();
        this.createOverlay();
        this.attachEventListeners();
    }

    createToggleButton() {
        const button = document.createElement('button');
        button.className = 'sidebar-toggle';
        button.innerHTML = '<i class="bi bi-list"></i>';
        button.setAttribute('aria-label', 'Menüyü Aç');
        document.body.appendChild(button);
        this.toggleButton = button;
    }

    createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        this.overlay = overlay;
    }

    attachEventListeners() {
        // Toggle button click
        this.toggleButton.addEventListener('click', () => this.toggleSidebar());

        // Overlay click - close sidebar
        this.overlay.addEventListener('click', () => this.closeSidebar());

        // ESC key - close sidebar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeSidebar();
        });

        // Window resize - reset on desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                this.closeSidebar();
                this.toggleButton.style.display = 'none';
            } else {
                this.toggleButton.style.display = 'flex';
            }
        });

        // Swipe gestures
        this.initSwipeGestures();
    }

    toggleSidebar() {
        const leftSidebar = document.querySelector('.sidebar-left');
        const rightSidebar = document.querySelector('.sidebar-right');

        if (leftSidebar) {
            const isActive = leftSidebar.classList.contains('active');

            if (isActive) {
                this.closeSidebar();
            } else {
                leftSidebar.classList.add('active');
                this.overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
    }

    closeSidebar() {
        const leftSidebar = document.querySelector('.sidebar-left');
        const rightSidebar = document.querySelector('.sidebar-right');

        if (leftSidebar) leftSidebar.classList.remove('active');
        if (rightSidebar) rightSidebar.classList.remove('active');

        this.overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    initSwipeGestures() {
        let startX = 0;
        let startY = 0;

        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;

            const diffX = endX - startX;
            const diffY = endY - startY;

            // Horizontal swipe daha dominant ise
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                // Swipe right from left edge - open sidebar
                if (startX < 50 && diffX > 0) {
                    const leftSidebar = document.querySelector('.sidebar-left');
                    if (leftSidebar && !leftSidebar.classList.contains('active')) {
                        leftSidebar.classList.add('active');
                        this.overlay.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                }
                // Swipe left - close sidebar
                else if (diffX < -50) {
                    this.closeSidebar();
                }
            }
        }, { passive: true });
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new MobileSidebar());
} else {
    new MobileSidebar();
}

/* ============================================================
   SECTION 6: FOOTER UI HELPERS
   ============================================================ */

/**
 * Footer UI Helpers
 * Widget toggle, category toggle, tab switching, newsletter form
 * NOTE: toast/confirm/dialog moved to ui-foundation.js (definitive version).
 */

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('img[data-fallback-src]').forEach(function(img) {
        if (img.dataset.fallbackBound === '1') return;
        img.dataset.fallbackBound = '1';
        img.addEventListener('error', function() {
            var fallback = img.getAttribute('data-fallback-src');
            if (!fallback || img.src.endsWith(fallback)) return;
            img.src = fallback;
            img.classList.add('is-fallback-image');
        });
    });

    document.querySelectorAll('.category-item .subcategories').forEach(function(subcategories) {
        const item = subcategories.closest('.category-item');
        const toggle = item ? item.querySelector('.category-toggle') : null;
        const isOpen = item ? item.classList.contains('open') : false;
        subcategories.removeAttribute('hidden');
        if (toggle) {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    });

    document.addEventListener('click', function(event) {
        const toggle = event.target.closest('.category-toggle');
        if (!toggle) {
            return;
        }

        event.preventDefault();
        window.toggleCategory(toggle);
    });

    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const input = newsletterForm.querySelector('[data-newsletter-email], input[type="email"]');
            const feedback = document.querySelector('[data-newsletter-feedback]');
            const email = input ? input.value.trim() : '';
            if (!input || !email || !input.checkValidity()) {
                if (feedback) feedback.textContent = 'Lütfen geçerli bir e-posta adresi girin.';
                if (window.showToast) window.showToast('Lütfen geçerli bir e-posta adresi girin.', 'warning');
                input && input.focus();
                return;
            }
            try {
                const saved = JSON.parse(localStorage.getItem('mod2.newsletterEmails') || '[]');
                if (!saved.includes(email)) {
                    saved.push(email);
                    localStorage.setItem('mod2.newsletterEmails', JSON.stringify(saved.slice(-20)));
                }
            } catch (error) {}
            newsletterForm.classList.add('is-submitted');
            if (feedback) feedback.textContent = 'Kaydınız alındı. Yeni içerik duyuruları için bu adresi hatırlayacağız.';
            if (window.showToast) window.showToast('Bülten kaydınız alındı.', 'success');
        });
    }

    const rememberForm = document.querySelector('[data-remember-email-form]');
    if (rememberForm) {
        const input = rememberForm.querySelector('[data-remember-email-input]');
        const checkbox = rememberForm.querySelector('[data-remember-email-check]');
        try {
            const remembered = localStorage.getItem('mod2.loginEmail') || '';
            if (input && remembered && !input.value) {
                input.value = remembered;
                if (checkbox) checkbox.checked = true;
            }
        } catch (error) {}
        rememberForm.addEventListener('submit', function() {
            if (!input || !checkbox) return;
            try {
                if (checkbox.checked && input.value.trim()) {
                    localStorage.setItem('mod2.loginEmail', input.value.trim());
                } else {
                    localStorage.removeItem('mod2.loginEmail');
                }
            } catch (error) {}
        });
    }
});

/* ============================================================
   SECTION 7: SEARCH AUTOCOMPLETE
   ============================================================ */

(() => {
    'use strict';

    const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]));
    const highlight = (text, query) => {
        const safeText = escapeHtml(text);
        const q = String(query || '').trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return q ? safeText.replace(new RegExp(`(${q})`, 'ig'), '<mark>$1</mark>') : safeText;
    };
    const debounce = (fn, wait = 250) => {
        let timeout;
        return (...args) => {
            window.clearTimeout(timeout);
            timeout = window.setTimeout(() => fn(...args), wait);
        };
    };

    class SearchAutocomplete {
        constructor(input) {
            this.input = input;
            this.form = input.closest('form');
            this.results = document.createElement('div');
            this.results.className = 'search-autocomplete-results';
            this.results.hidden = true;
            this.form.style.position = this.form.style.position || 'relative';
            this.form.appendChild(this.results);
            this.bind();
        }

        bind() {
            this.input.addEventListener('input', debounce(() => this.search(), 300));
            this.input.addEventListener('focus', () => this.search());
            document.addEventListener('click', (event) => {
                if (!this.form.contains(event.target)) this.hide();
            });
            this.input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') this.hide();
            });
        }

        async search() {
            const query = this.input.value.trim();
            if (query.length < 2) {
                this.hide();
                return;
            }

            try {
                const response = await fetch(`${baseUri}/api/search.php?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error('search_failed');
                const payload = await response.json();
                this.render(payload.results || [], query);
                window.analytics?.trackSearch?.(query, payload.total ?? 0);
            } catch (error) {
                this.hide();
            }
        }

        render(results, query) {
            if (!results.length) {
                this.results.innerHTML = '<div class="search-autocomplete-empty">Sonuç bulunamadı</div>';
                this.results.hidden = false;
                return;
            }

            this.results.innerHTML = results.map((item) => `
                <a href="${escapeHtml(item.url)}" class="search-autocomplete-item">
                    <img src="${escapeHtml(item.image)}" alt="" width="44" height="44" loading="lazy" decoding="async">
                    <span>
                        <strong>${highlight(item.title, query)}</strong>
                        <small>${escapeHtml(item.category || 'Genel')}</small>
                    </span>
                </a>
            `).join('');
            this.results.hidden = false;
        }

        hide() {
            this.results.hidden = true;
        }
    }

    const init = () => document.querySelectorAll('[data-search-autocomplete]').forEach((input) => new SearchAutocomplete(input));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
