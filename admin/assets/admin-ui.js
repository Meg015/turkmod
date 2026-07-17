(function () {
    'use strict';

    /* ═══════════════════════════════════════════
       TOAST HELPERS — wraps existing window.showToast
       Usage: adminToast.success('Kaydedildi'), .error(), .info(), .warning()
       ═══════════════════════════════════════════ */
    window.adminToast = {
        success: function (msg, dur) { if (window.showToast) window.showToast(msg, 'success', dur); },
        error:   function (msg, dur) { if (window.showToast) window.showToast(msg, 'error', dur); },
        info:    function (msg, dur) { if (window.showToast) window.showToast(msg, 'info', dur); },
        warning: function (msg, dur) { if (window.showToast) window.showToast(msg, 'warning', dur); }
    };

    /* ═══════════════════════════════════════════
       KEYBOARD SHORTCUTS — global + page hint overlay
       ═══════════════════════════════════════════ */
    var SHORTCUTS = [
        { keys: '?',        desc: 'Bu yardım panelini aç/kapat',      group: 'Genel' },
        { keys: 'Esc',      desc: 'Açık popup veya paneli kapat',     group: 'Genel' },
        { keys: 'g d',      desc: 'Dashboard',                         group: 'Gezinme' },
        { keys: 'g u',      desc: 'Kullanıcılar',                      group: 'Gezinme' },
        { keys: 'g t',      desc: 'Konular',                           group: 'Gezinme' },
        { keys: 'g q',      desc: 'Bekleyen İş Kuyruğu',               group: 'Gezinme' },
        { keys: 'g r',      desc: 'Şikayetler & Raporlar',             group: 'Gezinme' },
        { keys: 'g s',      desc: 'Genel Ayarlar',                     group: 'Gezinme' },
        { keys: 'n',        desc: 'Yeni konu oluştur',                 group: 'İşlem' },
        { keys: 'e',        desc: 'Satırdaki ilk Düzenle butonunu aç', group: 'İşlem' },
        { keys: '/',        desc: 'Sayfadaki arama kutusuna odaklan',  group: 'İşlem' },
        { keys: 't',        desc: 'Tema değiştir (açık/koyu)',         group: 'İşlem' }
    ];

    var NAV_BASE = (function () {
        var m = (window.location.pathname || '').match(/^(.*)\/admin\//);
        return m ? m[1] : '';
    })();

    var NAV_MAP = {
        d: '/admin/index.php',
        u: '/admin/users.php',
        t: '/admin/topics.php',
        q: '/admin/queue.php',
        r: '/admin/complaints-reports.php',
        s: '/admin/settings.php'
    };

    var lastG = 0;

    function isTypingTarget(el) {
        if (!el) return false;
        var tag = (el.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
        if (el.isContentEditable) return true;
        return false;
    }

    function buildShortcutOverlay() {
        if (document.getElementById('ui-admin-shortcut-overlay')) return;
        var grouped = {};
        SHORTCUTS.forEach(function (s) { (grouped[s.group] = grouped[s.group] || []).push(s); });

        var html = '<div class="ui-admin-shortcut-overlay" id="ui-admin-shortcut-overlay" role="dialog" aria-label="Klavye kısayolları">'
            + '<div class="ui-admin-shortcut-modal">'
            +   '<div class="ui-admin-shortcut-head">'
            +     '<h3><i class="bi bi-keyboard"></i> Klavye Kısayolları</h3>'
            +     '<button type="button" class="ui-admin-shortcut-close" aria-label="Kapat"><i class="bi bi-x-lg"></i></button>'
            +   '</div>'
            +   '<div class="ui-admin-shortcut-body">';
        Object.keys(grouped).forEach(function (g) {
            html += '<div class="ui-admin-shortcut-group"><h4>' + g + '</h4><ul>';
            grouped[g].forEach(function (s) {
                var keysHtml = s.keys.split(' ').map(function (k) { return '<kbd>' + k + '</kbd>'; }).join(' <span class="ui-admin-shortcut-plus">sonra</span> ');
                html += '<li><span class="ui-admin-shortcut-keys">' + keysHtml + '</span><span class="ui-admin-shortcut-desc">' + s.desc + '</span></li>';
            });
            html += '</ul></div>';
        });
        html += '</div></div></div>';
        document.body.insertAdjacentHTML('beforeend', html);
        var overlay = document.getElementById('ui-admin-shortcut-overlay');
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeShortcutOverlay(); });
        overlay.querySelector('.ui-admin-shortcut-close').addEventListener('click', closeShortcutOverlay);
    }

    function toggleShortcutOverlay() {
        buildShortcutOverlay();
        var el = document.getElementById('ui-admin-shortcut-overlay');
        if (el.classList.contains('is-open')) closeShortcutOverlay(); else el.classList.add('is-open');
    }
    function closeShortcutOverlay() {
        var el = document.getElementById('ui-admin-shortcut-overlay');
        if (el) el.classList.remove('is-open');
    }

    var OPEN_MODAL_SELECTOR = '.ui-admin-modal-overlay.is-open, .ui-admin-modal-overlay.ui-admin-modal-open, .media-modal-overlay.is-open, .media-modal-overlay.ui-admin-modal-open, .mm-modal-overlay.active, .ui-admin-detail-overlay.is-open';
    var MODAL_FOCUSABLE_SELECTOR = 'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"]), [contenteditable="true"]';

    function listOpenModals() {
        return Array.prototype.slice.call(document.querySelectorAll(OPEN_MODAL_SELECTOR)).filter(function (modal) {
            return modal && !modal.hidden;
        });
    }

    function topOpenModal() {
        var modals = listOpenModals();
        return modals.length ? modals[modals.length - 1] : null;
    }

    function topbarOffset() {
        var topbar = document.querySelector('.admin-topbar');
        if (!topbar || typeof topbar.getBoundingClientRect !== 'function') {
            return 0;
        }
        var rect = topbar.getBoundingClientRect();
        return Math.max(0, Math.ceil(rect.bottom));
    }

    function modalViewportGap() {
        var viewportHeight = window.innerHeight || (document.documentElement && document.documentElement.clientHeight) || 0;
        return viewportHeight > 0 && viewportHeight < 700 ? 8 : 12;
    }

    function findModalViewportShell(modal) {
        if (!modal || !modal.querySelector) {
            return null;
        }
        return modal.querySelector('.contacts-message-modal-shell, .ui-admin-detail-modal, .ui-admin-modal-shell, .media-modal, .mm-modal');
    }

    function applyModalTopOffset(modal, offset) {
        if (!modal || !modal.style) {
            return;
        }
        modal.style.setProperty('--ui-admin-modal-top-offset', offset + 'px');
        if (modal.id === 'contactMessageModal') {
            modal.style.setProperty('--contacts-modal-top-offset', offset + 'px');
        }
    }

    function ensureModalViewportFit(modal) {
        if (!modal) {
            return;
        }
        var shell = findModalViewportShell(modal);
        if (!shell || typeof shell.getBoundingClientRect !== 'function') {
            return;
        }
        var shellRect = shell.getBoundingClientRect();
        var minTop = Math.max(4, topbarOffset() + 4);
        if (shellRect.top >= minTop) {
            return;
        }
        var correction = Math.ceil(minTop - shellRect.top);
        var baseOffset = Number.parseFloat(modal.style.getPropertyValue('--ui-admin-modal-top-offset')) || topbarOffset();
        var adjustedOffset = Math.max(0, baseOffset + correction);
        applyModalTopOffset(modal, adjustedOffset);
    }

    function syncModalViewportOffset(modal) {
        if (!modal || !modal.style) {
            return;
        }
        var offset = topbarOffset();
        var viewportHeight = window.innerHeight || (document.documentElement && document.documentElement.clientHeight) || 0;
        if (viewportHeight > 0) {
            offset = Math.min(offset, Math.floor(viewportHeight * 0.35));
        }
        var gap = modalViewportGap();
        applyModalTopOffset(modal, offset);
        modal.style.setProperty('--ui-admin-modal-viewport-gap', gap + 'px');
        if (modal.id === 'contactMessageModal') {
            modal.style.setProperty('--contacts-modal-viewport-gap', gap + 'px');
        }
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                ensureModalViewportFit(modal);
            });
        } else {
            ensureModalViewportFit(modal);
        }
    }

    function syncAllModalViewportOffsets() {
        listOpenModals().forEach(syncModalViewportOffset);
    }

    var modalOffsetRaf = 0;
    function requestModalOffsetSync() {
        if (modalOffsetRaf) {
            return;
        }
        if (window.requestAnimationFrame) {
            modalOffsetRaf = window.requestAnimationFrame(function () {
                modalOffsetRaf = 0;
                syncAllModalViewportOffsets();
            });
            return;
        }
        syncAllModalViewportOffsets();
    }

    function syncDialogBodyLock() {
        var nativeDialog = document.getElementById('ui-admin-native-dialog');
        var nativeOpen = !!(nativeDialog && !nativeDialog.hidden);
        var hasOpenModal = listOpenModals().length > 0;
        document.body.classList.toggle('ui-admin-dialog-open', nativeOpen || hasOpenModal);
    }

    function modalFocusableElements(modal) {
        return Array.prototype.slice.call(modal.querySelectorAll(MODAL_FOCUSABLE_SELECTOR)).filter(function (el) {
            var style = window.getComputedStyle(el);
            var rect = el.getBoundingClientRect();
            return style.visibility !== 'hidden' && style.display !== 'none' && rect.width > 0 && rect.height > 0;
        });
    }

    function trapModalFocus(modal, event) {
        var focusable = modalFocusableElements(modal);
        if (focusable.length === 0) {
            if (!modal.hasAttribute('tabindex')) {
                modal.setAttribute('tabindex', '-1');
            }
            modal.focus({ preventScroll: true });
            event.preventDefault();
            return true;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        var active = document.activeElement;
        if (event.shiftKey) {
            if (active === first || active === modal) {
                event.preventDefault();
                last.focus({ preventScroll: true });
                return true;
            }
            return false;
        }

        if (active === last) {
            event.preventDefault();
            first.focus({ preventScroll: true });
            return true;
        }
        return false;
    }

    function callKnownModalCloser(modal) {
        if (!modal || !modal.id) {
            return false;
        }
        if (modal.id === 'userDetailModal' && typeof window.closeUserDetail === 'function') {
            window.closeUserDetail();
            return true;
        }
        if (modal.id === 'userEditModal' && typeof window.closeUserEditModal === 'function') {
            window.closeUserEditModal();
            return true;
        }
        if (modal.id === 'previewModal' && typeof window.closePreview === 'function') {
            window.closePreview();
            return true;
        }
        if (modal.id === 'mediaPreviewModal' && typeof window.closeMediaPreview === 'function') {
            window.closeMediaPreview();
            return true;
        }
        return false;
    }

    function closeModalOverlay(modal, options) {
        if (!modal) {
            return false;
        }

        var opts = Object.assign({ preferNavigate: true }, options || {});
        if (callKnownModalCloser(modal)) {
            syncDialogBodyLock();
            return true;
        }

        if (opts.preferNavigate) {
            var closeLink = modal.querySelector('[data-ui-modal-close][href]');
            if (closeLink) {
                var href = closeLink.getAttribute('href') || '';
                if (href !== '' && href !== '#' && !/^javascript:/i.test(href)) {
                    window.location.href = href;
                    return true;
                }
            }
        }

        modal.classList.remove('is-open', 'ui-admin-modal-open', 'active', 'is-active');
        if (modal.classList.contains('media-modal-overlay') || modal.classList.contains('ui-admin-modal-overlay')) {
            modal.style.display = 'none';
        }
        if (modal.classList.contains('mm-modal-overlay')) {
            modal.style.display = 'none';
        }
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        syncDialogBodyLock();
        return true;
    }

    function openModalOverlay(modal) {
        if (!modal) {
            return false;
        }
        if (modal.classList.contains('mm-modal-overlay')) {
            modal.classList.add('active');
        } else if (modal.classList.contains('ui-admin-modal-overlay')) {
            modal.classList.add('ui-admin-modal-open');
        } else {
            modal.classList.add('is-open');
        }
        modal.removeAttribute('aria-hidden');
        modal.hidden = false;
        prepareOpenModal(modal);
        syncDialogBodyLock();
        return true;
    }

    function prepareOpenModal(modal) {
        if (!modal) {
            return;
        }
        if (!modal.hasAttribute('tabindex')) {
            modal.setAttribute('tabindex', '-1');
        }
        syncModalViewportOffset(modal);

        if (modal.dataset.uiModalPrepared !== '1') {
            modal.dataset.uiModalPrepared = '1';
            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    closeModalOverlay(modal, { preferNavigate: true });
                }
            });
        }

        modal.scrollTop = 0;
        var modalBody = modal.querySelector('.ui-admin-detail-modal-body, .media-modal-body, .mm-modal-body');
        if (modalBody) {
            modalBody.scrollTop = 0;
        }
        var contactShell = modal.querySelector('.contacts-message-modal-shell');
        if (contactShell) {
            contactShell.scrollTop = 0;
        }
        ensureModalViewportFit(modal);

        var focusTarget = modal.querySelector('[data-ui-modal-close], [autofocus]') || modalFocusableElements(modal)[0];
        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus({ preventScroll: true });
        } else {
            modal.focus({ preventScroll: true });
        }
    }

    function initOpenModalOverlays() {
        listOpenModals().forEach(prepareOpenModal);
        syncDialogBodyLock();
    }

    function nodeContainsOpenModal(node) {
        if (!node || node.nodeType !== 1) {
            return false;
        }
        if (node.matches && node.matches(OPEN_MODAL_SELECTOR)) {
            return true;
        }
        return !!(node.querySelector && node.querySelector(OPEN_MODAL_SELECTOR));
    }

    function observeModalLifecycle() {
        if (!window.MutationObserver || !document.body || document.body.dataset.uiModalObserverBound === '1') {
            return;
        }
        document.body.dataset.uiModalObserverBound = '1';

        var observer = new MutationObserver(function (mutations) {
            var shouldRefresh = false;
            for (var i = 0; i < mutations.length; i += 1) {
                var mutation = mutations[i];
                if (mutation.type === 'attributes') {
                    if (mutation.target && mutation.target.matches && mutation.target.matches('.ui-admin-detail-overlay, .ui-admin-modal-overlay, .media-modal-overlay, .mm-modal-overlay')) {
                        shouldRefresh = true;
                        break;
                    }
                    continue;
                }
                if (mutation.type === 'childList') {
                    for (var j = 0; j < mutation.addedNodes.length; j += 1) {
                        if (nodeContainsOpenModal(mutation.addedNodes[j])) {
                            shouldRefresh = true;
                            break;
                        }
                    }
                    if (shouldRefresh) {
                        break;
                    }
                }
            }

            if (shouldRefresh) {
                initOpenModalOverlays();
            } else {
                syncDialogBodyLock();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'hidden', 'style']
        });
    }

    window.uiAdminModal = {
        open: openModalOverlay,
        close: closeModalOverlay,
        getOpen: topOpenModal,
        refresh: initOpenModalOverlays
    };

    window.addEventListener('resize', requestModalOffsetSync, { passive: true });
    window.addEventListener('orientationchange', requestModalOffsetSync);
    window.addEventListener('scroll', requestModalOffsetSync, { passive: true });
    window.addEventListener('load', requestModalOffsetSync, { once: true });

    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        var openModal = topOpenModal();
        var k = e.key;
        if (k === 'Escape') {
            closeShortcutOverlay();
            if (isTypingTarget(e.target)) { e.target.blur(); }
            if (openModal) {
                e.preventDefault();
                closeModalOverlay(openModal, { preferNavigate: true });
            }
            return;
        }
        if (k === 'Tab' && openModal) {
            trapModalFocus(openModal, e);
            return;
        }
        if (isTypingTarget(e.target)) {
            return;
        }
        if (k === '?') { e.preventDefault(); toggleShortcutOverlay(); return; }
        if (k === '/') {
            var search = document.querySelector('input[type="search"], input[name="q"], input[name="search"], input.ui-admin-search, input[placeholder*="Ara"]');
            if (search) { e.preventDefault(); search.focus(); search.select && search.select(); }
            return;
        }
        if (k === 't') { e.preventDefault(); if (typeof window.toggleTheme === 'function') window.toggleTheme(); return; }
        if (k === 'n') {
            e.preventDefault();
            window.location.href = NAV_BASE + '/admin/create.php';
            return;
        }
        if (k === 'e') {
            var firstEdit = document.querySelector('a[href*="user-edit.php"], a[href*="edit.php?id"], button[title*="Düzenle" i], a[title*="Düzenle" i]');
            if (firstEdit) { e.preventDefault(); firstEdit.click(); }
            return;
        }
        if (k === 'g') { lastG = Date.now(); return; }
        if (lastG && (Date.now() - lastG) < 1200 && NAV_MAP[k]) {
            e.preventDefault();
            window.location.href = NAV_BASE + NAV_MAP[k];
            lastG = 0;
        }
    });

    var themeKey = 'admin-theme';
    var modeKey = 'admin-theme-mode';
    var systemQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function normalizeMode(value) {
        return value === 'light' || value === 'dark' || value === 'auto' ? value : 'auto';
    }

    function configuredMode() {
        var html = document.documentElement;
        var settingMode = normalizeMode(html.getAttribute('data-theme-mode'));
        var stored = localStorage.getItem(modeKey) || localStorage.getItem(themeKey);

        return stored ? normalizeMode(stored) : settingMode;
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

        icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        icon.setAttribute('data-theme-mode', normalizeMode(mode));
    }

    function applyTheme(mode) {
        var normalizedMode = normalizeMode(mode);
        var theme = resolveTheme(normalizedMode);
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme-mode', normalizedMode);
        updateThemeIcon(theme, normalizedMode);
        return theme;
    }

    window.toggleTheme = function () {
        var currentTheme = document.documentElement.getAttribute('data-theme') || resolveTheme(configuredMode());
        var nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        localStorage.setItem(themeKey, nextTheme);
        localStorage.setItem(modeKey, nextTheme);
        applyTheme(nextTheme);
    };

    function dialogIcon(tone) {
        if (tone === 'danger') {
            return 'warning';
        }
        if (tone === 'success' || tone === 'warning' || tone === 'error' || tone === 'info') {
            return tone;
        }
        return 'question';
    }

    function swalOptions(options) {
        var tone = options.tone || 'warning';
        var kindClass = options.kind ? String(options.kind).replace(/[^a-z0-9_-]/gi, '') : '';
        var iconClass = options.icon ? String(options.icon).replace(/[^a-z0-9_-]/gi, '') : '';

        return {
            title: options.title || 'Onay gerekiyor',
            text: options.message || '',
            icon: dialogIcon(tone),
            iconHtml: iconClass ? '<i class="bi ' + iconClass + '"></i>' : undefined,
            confirmButtonText: options.ok || 'Onayla',
            cancelButtonText: options.cancel || 'Vazgeç',
            showCancelButton: options.showCancel !== false,
            reverseButtons: true,
            focusCancel: tone === 'danger',
            customClass: {
                popup: 'admin-dialog' + (kindClass ? ' admin-dialog-' + kindClass : ''),
                icon: iconClass ? 'admin-dialog-icon-custom' : '',
                confirmButton: tone === 'danger' ? 'ui-admin-btn ui-admin-btn-danger' : 'ui-admin-btn ui-admin-btn-primary',
                cancelButton: 'ui-admin-btn ui-admin-btn-outline'
            },
            buttonsStyling: false
        };
    }

    function csrfMeta() {
        return document.querySelector('meta[name="csrf-token"]');
    }

    function readAdminCsrfToken() {
        var meta = csrfMeta();
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    function updateAdminCsrfToken(token) {
        if (!token || typeof token !== 'string') {
            return;
        }

        var meta = csrfMeta();
        if (meta) {
            meta.setAttribute('content', token);
        }

        document.querySelectorAll('input[name="_token"], input[name="csrf_token"]').forEach(function (input) {
            input.value = token;
        });

        window.adminCsrfToken = token;
    }

    function applyAdminJsonResponse(data) {
        if (!data || typeof data !== 'object') {
            return data;
        }

        updateAdminCsrfToken(data._token || data.csrf_token || '');
        return data;
    }

    function setSubmittingState(form, submitter) {
        if (!form || form.dataset.adminSubmitting === '1') {
            return false;
        }

        form.dataset.adminSubmitting = '1';
        form.setAttribute('aria-busy', 'true');

        var button = submitter || form.querySelector('button[type="submit"], input[type="submit"]');
        if (button) {
            if (button.name && !form.querySelector('input[data-admin-submit-clone="' + button.name + '"]')) {
                var clone = document.createElement('input');
                clone.type = 'hidden';
                clone.name = button.name;
                clone.value = button.value || '';
                clone.setAttribute('data-admin-submit-clone', button.name);
                form.appendChild(clone);
            }
            button.dataset.adminOriginalText = button.textContent || button.value || '';
            button.disabled = true;
            setButtonBusy(button, true);
            if (button.tagName === 'BUTTON' && !button.dataset.adminNoBusyText) {
                button.innerHTML = '<i class="bi bi-hourglass-split"></i> İşleniyor';
            }
        }

        return true;
    }

    function ensureNativeDialog() {
        var existing = document.getElementById('ui-admin-native-dialog');
        if (existing) {
            return existing;
        }

        var overlay = document.createElement('div');
        overlay.id = 'ui-admin-native-dialog';
        overlay.className = 'ui-admin-native-dialog-overlay';
        overlay.hidden = true;
        overlay.innerHTML = ''
            + '<div class="ui-admin-native-dialog" role="dialog" aria-modal="true" aria-labelledby="ui-admin-native-dialog-title">'
            + '  <div class="ui-admin-native-dialog-icon"><i class="bi bi-question-lg"></i></div>'
            + '  <div class="ui-admin-native-dialog-copy">'
            + '    <h3 id="ui-admin-native-dialog-title"></h3>'
            + '    <p data-ui-admin-native-message></p>'
            + '    <input class="ui-admin-form-control ui-admin-native-dialog-input" data-ui-admin-native-input hidden>'
            + '  </div>'
            + '  <div class="ui-admin-native-dialog-actions">'
            + '    <button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-native-cancel" data-ui-admin-native-cancel>Vazgeç</button>'
            + '    <button type="button" class="ui-admin-btn ui-admin-btn-primary ui-admin-native-ok" data-ui-admin-native-ok>Onayla</button>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(overlay);
        return overlay;
    }

    function nativeDialog(options) {
        var opts = Object.assign({
            title: 'İşlem onayı',
            message: '',
            ok: 'Onayla',
            cancel: 'Vazgeç',
            tone: 'warning',
            kind: '',
            icon: '',
            showCancel: true,
            input: null,
            value: '',
            placeholder: ''
        }, options || {});

        var overlay = ensureNativeDialog();
        var panel = overlay.querySelector('.ui-admin-native-dialog');
        var title = overlay.querySelector('#ui-admin-native-dialog-title');
        var message = overlay.querySelector('[data-ui-admin-native-message]');
        var icon = overlay.querySelector('.ui-admin-native-dialog-icon i');
        var input = overlay.querySelector('[data-ui-admin-native-input]');
        var okButton = overlay.querySelector('[data-ui-admin-native-ok]');
        var cancelButton = overlay.querySelector('[data-ui-admin-native-cancel]');

        var kindClass = opts.kind ? String(opts.kind).replace(/[^a-z0-9_-]/gi, '') : '';
        overlay.className = 'ui-admin-native-dialog-overlay is-' + (opts.tone || 'warning') + (kindClass ? ' is-' + kindClass : '');
        panel.className = 'ui-admin-native-dialog is-' + (opts.tone || 'warning') + (kindClass ? ' is-' + kindClass : '');
        icon.className = 'bi ' + (opts.icon || (opts.tone === 'forbidden' ? 'bi-shield-lock-fill' : (opts.tone === 'danger' ? 'bi-exclamation-triangle-fill' : (opts.tone === 'success' ? 'bi-check-circle-fill' : 'bi-question-lg'))));
        title.textContent = opts.title || 'Bilgi';
        message.textContent = opts.message || '';
        okButton.textContent = opts.ok || 'Onayla';
        cancelButton.textContent = opts.cancel || 'Vazgeç';
        cancelButton.hidden = opts.showCancel === false;

        if (opts.input) {
            input.hidden = false;
            input.type = opts.input === 'textarea' ? 'text' : opts.input;
            input.value = opts.value || '';
            input.placeholder = opts.placeholder || '';
        } else {
            input.hidden = true;
            input.value = '';
        }

        overlay.hidden = false;
        document.body.classList.add('ui-admin-dialog-open');

        return new Promise(function (resolve) {
            var finish = function (result) {
                overlay.hidden = true;
                document.body.classList.remove('ui-admin-dialog-open');
                okButton.removeEventListener('click', onOk);
                cancelButton.removeEventListener('click', onCancel);
                overlay.removeEventListener('click', onOverlay);
                document.removeEventListener('keydown', onKeydown);
                resolve(result);
            };
            var onOk = function () {
                finish(opts.input ? input.value : true);
            };
            var onCancel = function () {
                finish(opts.input ? null : false);
            };
            var onOverlay = function (event) {
                if (event.target === overlay) {
                    onCancel();
                }
            };
            var onKeydown = function (event) {
                if (event.key === 'Escape') {
                    onCancel();
                }
                if (event.key === 'Enter' && opts.input && document.activeElement === input) {
                    event.preventDefault();
                    onOk();
                }
            };

            okButton.addEventListener('click', onOk);
            cancelButton.addEventListener('click', onCancel);
            overlay.addEventListener('click', onOverlay);
            document.addEventListener('keydown', onKeydown);
            window.setTimeout(function () {
                (opts.input ? input : okButton).focus();
            }, 0);
        });
    }

    function setButtonBusy(button, isBusy) {
        if (!button || !button.classList) {
            return;
        }
        button.classList.toggle('is-ui-loading', !!isBusy);
    }

    window.adminConfirm = function (message, options) {
        var opts = Object.assign({ message: message }, options || {});
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire(swalOptions(opts)).then(function (result) {
                return Boolean(result.isConfirmed);
            });
        }

        return nativeDialog(opts).then(Boolean);
    };

    window.adminAlert = function (message, options) {
        var opts = Object.assign({ message: message }, options || {});
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire(swalOptions(Object.assign({
                title: opts.title || 'Bilgi',
                ok: opts.ok || 'Tamam',
                showCancel: false,
                tone: opts.tone || 'info'
            }, opts))).then(function () {
                return true;
            });
        }

        return nativeDialog(Object.assign({ showCancel: false, ok: opts.ok || 'Tamam' }, opts)).then(function () {
            return true;
        });
    };

    window.adminForbidden = function (message, options) {
        var now = Date.now();
        if (window.adminForbidden._lastShownAt && now - window.adminForbidden._lastShownAt < 900) {
            return Promise.resolve(true);
        }
        window.adminForbidden._lastShownAt = now;

        return nativeDialog(Object.assign({
            title: 'Bu işlem için yetkiniz yok',
            message: message || 'Bu alanı kullanmak için gerekli izin hesabınıza tanımlanmamış.',
            ok: 'Tamam',
            showCancel: false,
            tone: 'forbidden'
        }, options || {})).then(function () {
            return true;
        });
    };

    window.adminPrompt = function (message, options) {
        var opts = Object.assign({ message: message }, options || {});
        if (window.Swal && typeof window.Swal.fire === 'function') {
            return window.Swal.fire(Object.assign(swalOptions({
                title: opts.title || message || 'Bilgi girin',
                message: opts.help || '',
                ok: opts.ok || 'Kaydet',
                cancel: opts.cancel || 'Vazgeç',
                tone: opts.tone || 'info'
            }), {
                input: opts.input || 'text',
                inputValue: opts.value || '',
                inputPlaceholder: opts.placeholder || '',
                inputAttributes: opts.inputAttributes || {}
            })).then(function (result) {
                return result.isConfirmed ? result.value : null;
            });
        }

        return nativeDialog(Object.assign({ input: opts.input || 'text', showCancel: true }, opts));
    };

    window.adminUpdateCsrfToken = updateAdminCsrfToken;
    window.adminApplyResponse = applyAdminJsonResponse;
    window.adminNotifyFromResponse = function (data, fallbackType) {
        applyAdminJsonResponse(data);
        if (!data || !data.message || !window.adminToast) {
            return;
        }

        var type = data.success === false ? 'error' : (fallbackType || 'success');
        if (typeof window.adminToast[type] === 'function') {
            window.adminToast[type](data.message);
        } else if (window.showToast) {
            window.showToast(data.message, type);
        }
    };
    window.adminFetchJson = function (url, options) {
        var opts = Object.assign({}, options || {});
        var headers = new Headers(opts.headers || {});
        var token = readAdminCsrfToken();

        if (token && !headers.has('X-CSRF-Token')) {
            headers.set('X-CSRF-Token', token);
        }
        if (!headers.has('X-Requested-With')) {
            headers.set('X-Requested-With', 'XMLHttpRequest');
        }

        if (opts.body instanceof FormData) {
            if (token && !opts.body.has('_token')) {
                opts.body.append('_token', token);
            }
        } else if (opts.body instanceof URLSearchParams) {
            if (token && !opts.body.has('_token')) {
                opts.body.append('_token', token);
            }
            if (!headers.has('Content-Type')) {
                headers.set('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            }
        } else if (opts.body && typeof opts.body === 'object') {
            opts.body._token = opts.body._token || token;
            headers.set('Content-Type', 'application/json; charset=UTF-8');
            opts.body = JSON.stringify(opts.body);
        }

        opts.headers = headers;

        return fetch(url, opts).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                applyAdminJsonResponse(data);
                if (!response.ok || data.success === false) {
                    var error = new Error(data.message || data.error || 'İşlem tamamlanamadı.');
                    error.response = response;
                    error.data = data;
                    if ((response.status === 401 || response.status === 403 || data.error === 'forbidden') && window.adminForbidden) {
                        window.adminForbidden(data.message || error.message);
                    } else if (opts.notifyError !== false) {
                        window.adminNotifyFromResponse(data, 'error');
                    }
                    throw error;
                }
                if (opts.notify) {
                    window.adminNotifyFromResponse(data, opts.notify === true ? 'success' : String(opts.notify));
                }
                return data;
            });
        });
    };

    (function bindForbiddenFetchModal() {
        if (!window.fetch || window.fetch._adminForbiddenWrapped) {
            return;
        }

        var originalFetch = window.fetch.bind(window);
        var lastShownAt = 0;

        function requestUrl(input) {
            if (typeof input === 'string') {
                return input;
            }
            return input && input.url ? String(input.url) : '';
        }

        function shouldInspect(input, init) {
            var url = requestUrl(input);
            var headers = new Headers((init && init.headers) || (input && input.headers) || {});
            return url.indexOf('/admin/') !== -1
                || url.indexOf('admin/') === 0
                || headers.get('X-Requested-With') === 'XMLHttpRequest';
        }

        function showForbidden(message) {
            var now = Date.now();
            if (now - lastShownAt < 900) {
                return;
            }
            lastShownAt = now;
            if (window.adminForbidden) {
                window.adminForbidden(message);
            }
        }

        window.fetch = function (input, init) {
            return originalFetch(input, init).then(function (response) {
                if ((response.status === 401 || response.status === 403) && shouldInspect(input, init)) {
                    response.clone().json().then(function (data) {
                        showForbidden(data && (data.message || data.error) ? (data.message || data.error) : '');
                    }).catch(function () {
                        showForbidden('');
                    });
                }
                return response;
            });
        };
        window.fetch._adminForbiddenWrapped = true;
    })();

    var eventsReadableNumberFormatter = null;

    function formatEventsReadableNumber(number) {
        if (!eventsReadableNumberFormatter && typeof Intl !== 'undefined' && Intl.NumberFormat) {
            eventsReadableNumberFormatter = new Intl.NumberFormat('tr-TR', {
                maximumFractionDigits: 2
            });
        }

        if (eventsReadableNumberFormatter) {
            return eventsReadableNumberFormatter.format(number);
        }

        return String(Math.round(number * 100) / 100);
    }

    function formatEventsReadableDuration(seconds, zeroLabel) {
        var remaining = Math.max(0, Math.round(seconds));
        var units = [
            ['g\u00fcn', 86400],
            ['saat', 3600],
            ['dk', 60],
            ['sn', 1]
        ];
        var parts = [];

        if (remaining === 0) {
            return zeroLabel || '0 sn';
        }

        units.forEach(function (unit) {
            var amount;
            if (parts.length >= 2) {
                return;
            }
            amount = Math.floor(remaining / unit[1]);
            if (amount <= 0) {
                return;
            }
            parts.push(amount + ' ' + unit[0]);
            remaining %= unit[1];
        });

        return parts.join(' ');
    }

    function formatEventsReadableDays(value, zeroLabel) {
        var days = Math.max(0, Math.round(value));
        var years;
        var remainingDays;

        if (days === 0) {
            return zeroLabel || '0 g\u00fcn';
        }

        if (days >= 365) {
            years = Math.floor(days / 365);
            remainingDays = days % 365;
            return remainingDays > 0
                ? years + ' y\u0131l ' + remainingDays + ' g\u00fcn'
                : years + ' y\u0131l';
        }

        if (days >= 7 && days % 7 === 0) {
            return Math.floor(days / 7) + ' hafta';
        }

        return days + ' g\u00fcn';
    }

    function readableEventsNumberFromInput(input) {
        var rawValue = String(input.value || '').trim().replace(',', '.');
        var number;
        var min;
        var max;
        var format;
        var zeroLabel;
        var unit;
        var suffix;

        if (rawValue === '') {
            return input.dataset.uiEventsReadableZeroLabel || 'De\u011fer girilmedi';
        }

        number = Number(rawValue);
        if (!Number.isFinite(number)) {
            return 'Ge\u00e7erli say\u0131 girin';
        }

        min = input.hasAttribute('min') ? Number(input.getAttribute('min')) : NaN;
        max = input.hasAttribute('max') ? Number(input.getAttribute('max')) : NaN;
        if (Number.isFinite(min)) {
            number = Math.max(min, number);
        }
        if (Number.isFinite(max)) {
            number = Math.min(max, number);
        }

        format = input.dataset.uiEventsReadableFormat || 'count';
        zeroLabel = input.dataset.uiEventsReadableZeroLabel || '';
        unit = input.dataset.uiEventsReadableUnit || '';
        suffix = input.dataset.uiEventsReadableSuffix || '';

        if (number === 0 && zeroLabel !== '') {
            return zeroLabel;
        }
        if (format === 'duration_seconds') {
            return formatEventsReadableDuration(number, zeroLabel || '0 sn');
        }
        if (format === 'duration_minutes') {
            return formatEventsReadableDuration(number * 60, zeroLabel || '0 dk');
        }
        if (format === 'days') {
            return formatEventsReadableDays(number, zeroLabel || '0 g\u00fcn');
        }
        if (format === 'percent') {
            return '%' + formatEventsReadableNumber(number) + (suffix ? ' ' + suffix : '');
        }
        if (format === 'level') {
            return formatEventsReadableNumber(number) + '. seviye';
        }

        return (formatEventsReadableNumber(number) + (unit ? ' ' + unit : '')).trim();
    }

    function initEventsReadableNumberValues() {
        var inputs = Array.prototype.slice.call(document.querySelectorAll('[data-ui-events-readable-input]'));

        if (inputs.length === 0) {
            return;
        }

        function updateInput(input) {
            var row = input.closest ? input.closest('[data-ui-events-setting-row], .ui-events-rule-field') : null;
            var target = row ? row.querySelector('[data-ui-events-readable-value]') : null;
            if (target) {
                target.textContent = readableEventsNumberFromInput(input);
            }
        }

        inputs.forEach(function (input) {
            if (input._eventsReadableValuesBound !== true) {
                input._eventsReadableValuesBound = true;
                input.addEventListener('input', function () { updateInput(input); });
                input.addEventListener('change', function () { updateInput(input); });
            }
            input.dataset.eventsReadableValuesBound = 'true';
            updateInput(input);
        });
    }

    window.initEventsReadableNumberValues = initEventsReadableNumberValues;

    applyTheme(configuredMode());

    if (systemQuery) {
        var handleSystemChange = function () {
            if (configuredMode() === 'auto') {
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
        applyTheme(configuredMode());
        initOpenModalOverlays();
        observeModalLifecycle();
        requestModalOffsetSync();
        initEventsReadableNumberValues();

        var themeToggle = document.getElementById('theme-toggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', window.toggleTheme);
        }

        document.addEventListener('click', function (event) {
            var closeTrigger = event.target && event.target.closest ? event.target.closest('[data-ui-modal-close]') : null;
            if (!closeTrigger) {
                return;
            }

            var modal = closeTrigger.closest('.ui-admin-detail-overlay, .ui-admin-modal-overlay, .media-modal-overlay, .mm-modal-overlay');
            if (!modal) {
                return;
            }

            if (closeTrigger.tagName.toLowerCase() === 'a') {
                var href = closeTrigger.getAttribute('href') || '';
                if (href !== '' && href !== '#' && !/^javascript:/i.test(href)) {
                    return;
                }
                event.preventDefault();
            } else {
                event.preventDefault();
            }

            closeModalOverlay(modal, { preferNavigate: false });
        });

        // Profile dropdown interactive behavior (click to toggle & click outside to close)
        var profileTrigger = document.querySelector('.admin-profile-trigger');
        var profileDropdown = document.getElementById('admin-profile-dropdown');
        if (profileTrigger && profileDropdown) {
            profileTrigger.addEventListener('click', function (e) {
                e.stopPropagation();
                var menu = profileDropdown.querySelector('.admin-profile-menu');
                var expanded = profileTrigger.getAttribute('aria-expanded') === 'true';
                profileTrigger.setAttribute('aria-expanded', !expanded ? 'true' : 'false');
                if (menu) menu.classList.toggle('is-active', !expanded);
            });

            document.addEventListener('click', function (e) {
                if (!profileDropdown.contains(e.target)) {
                    profileTrigger.setAttribute('aria-expanded', 'false');
                    var menu = profileDropdown.querySelector('.admin-profile-menu');
                    if (menu) menu.classList.remove('is-active');
                }
            });
        }

        document.addEventListener('submit', function (event) {
            var form = event.target && event.target.closest ? event.target.closest('form') : null;
            if (!form) {
                return;
            }

            if (form.dataset.adminNoLock === '1') {
                return;
            }

            if (form.dataset.adminSubmitting === '1') {
                event.preventDefault();
                return;
            }

            if (!form.matches('form[data-admin-confirm]') || form.dataset.adminConfirmed === '1') {
                if ((form.method || '').toLowerCase() === 'post' || form.dataset.adminLock === '1') {
                    setSubmittingState(form, event.submitter || null);
                }
                return;
            }

            event.preventDefault();
            window.adminConfirm(form.dataset.adminConfirm, {
                title: form.dataset.adminConfirmTitle || 'İşlem onayı',
                ok: form.dataset.adminConfirmOk || 'Onayla',
                cancel: form.dataset.adminConfirmCancel || 'Vazgeç',
                tone: form.dataset.adminConfirmTone || 'danger',
                kind: form.dataset.adminConfirmKind || '',
                icon: form.dataset.adminConfirmIcon || ''
            }).then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                form.dataset.adminConfirmed = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    setSubmittingState(form, null);
                    form.submit();
                }
            });
        });

        document.addEventListener('click', function (event) {
            var trigger = event.target && event.target.closest ? event.target.closest('[data-admin-confirm]:not(form)') : null;
            if (!trigger || trigger.dataset.adminConfirmed === '1') {
                return;
            }

            var href = trigger.getAttribute('href');
            var isSubmit = trigger.matches('button[type="submit"], input[type="submit"]');
            if (!href && !isSubmit) {
                return;
            }

            event.preventDefault();
            window.adminConfirm(trigger.dataset.adminConfirm, {
                title: trigger.dataset.adminConfirmTitle || 'İşlem onayı',
                ok: trigger.dataset.adminConfirmOk || 'Onayla',
                cancel: trigger.dataset.adminConfirmCancel || 'Vazgeç',
                tone: trigger.dataset.adminConfirmTone || 'danger',
                kind: trigger.dataset.adminConfirmKind || '',
                icon: trigger.dataset.adminConfirmIcon || ''
            }).then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                trigger.dataset.adminConfirmed = '1';
                if (href) {
                    window.location.href = href;
                    return;
                }

                var ownerForm = trigger.form || trigger.closest('form');
                if (ownerForm && typeof ownerForm.requestSubmit === 'function') {
                    ownerForm.requestSubmit(trigger);
                } else if (ownerForm) {
                    ownerForm.submit();
                }
            });
        });
    });
})();
