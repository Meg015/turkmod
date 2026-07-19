(function () {
    'use strict';

    /* ═══════════════════════════════════════════
       TOAST HELPERS — wraps existing window.showToast
       Usage: adminToast.success('Kaydedildi'), .error(), .info(), .warning()
       ═══════════════════════════════════════════ */
    function renderAdminToast(message, type, duration) {
        if (window.showToast) {
            window.showToast(message, type, duration);
            return;
        }

        var container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'topic-toast-container toast-pos-bottom-right';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'topic-toast toast-' + (type || 'info') + ' toast-theme-default toast-anim-slide';
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast.innerHTML = '<i class="bi bi-info-circle toast-icon"></i><span class="toast-message"></span>';
        toast.querySelector('.toast-message').textContent = String(message || '');
        container.appendChild(toast);
        if (duration !== 0) {
            window.setTimeout(function () {
                toast.classList.add('toast-out');
                window.setTimeout(function () { toast.remove(); }, 260);
            }, typeof duration === 'number' ? duration : 4200);
        }
    }

    window.adminToast = {
        success: function (msg, dur) { renderAdminToast(msg, 'success', dur); },
        error:   function (msg, dur) { renderAdminToast(msg, 'error', dur); },
        info:    function (msg, dur) { renderAdminToast(msg, 'info', dur); },
        warning: function (msg, dur) { renderAdminToast(msg, 'warning', dur); }
    };

    function normalizeVisibilityTarget(target) {
        if (typeof target === 'string') {
            return document.querySelector(target);
        }

        return target || null;
    }

    function normalizeClassList(value) {
        if (!value) {
            return [];
        }
        if (Array.isArray(value)) {
            return value.filter(Boolean);
        }

        return [value].filter(Boolean);
    }

    function setAdminVisibility(target, visible, options) {
        var el = normalizeVisibilityTarget(target);
        if (!el) {
            return null;
        }

        options = options || {};
        var visibleClasses = normalizeClassList(options.visibleClass || options.visibleClasses);
        var hiddenClasses = normalizeClassList(options.hiddenClass || options.hiddenClasses);
        var removeOnShow = normalizeClassList(options.removeOnShow);
        var removeOnHide = normalizeClassList(options.removeOnHide);

        el.hidden = !visible;
        visibleClasses.forEach(function (className) {
            el.classList.toggle(className, visible);
        });
        hiddenClasses.forEach(function (className) {
            el.classList.toggle(className, !visible);
        });
        if (visible) {
            removeOnShow.forEach(function (className) { el.classList.remove(className); });
        } else {
            removeOnHide.forEach(function (className) { el.classList.remove(className); });
        }
        if (options.aria !== false) {
            el.setAttribute('aria-hidden', visible ? 'false' : 'true');
        }

        return el;
    }

    window.adminVisibility = {
        set: setAdminVisibility,
        show: function (target, options) { return setAdminVisibility(target, true, options); },
        hide: function (target, options) { return setAdminVisibility(target, false, options); },
        toggle: function (target, force, options) {
            var el = normalizeVisibilityTarget(target);
            if (!el) {
                return null;
            }
            var next = typeof force === 'boolean' ? force : el.hidden;
            return setAdminVisibility(el, next, options);
        }
    };

    var adminPageEntries = [];
    var adminPageRunScheduled = false;

    function onAdminDomReady(callback) {
        if (typeof callback !== 'function') {
            return;
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        window.setTimeout(callback, 0);
    }

    function adminPageContext() {
        var pathname = (window.location && window.location.pathname ? window.location.pathname : '').replace(/\\/g, '/');
        var adminIndex = pathname.lastIndexOf('/admin/');
        var adminPath = adminIndex >= 0 ? pathname.slice(adminIndex + 7) : pathname.replace(/^\/+/, '');
        var file = (adminPath.split('/').pop() || 'index.php').split('?')[0] || 'index.php';
        var page = file.replace(/\.php$/i, '') || 'index';
        var query = new URLSearchParams(window.location && window.location.search ? window.location.search : '');
        var tab = query.get('tab') || '';

        if (page === 'index' && !adminPath.includes('/')) {
            page = 'dashboard';
        } else if (page === 'index') {
            page = adminPath.replace(/\/index\.php$/i, '').replace(/\//g, '-');
        }

        return {
            path: pathname,
            adminPath: adminPath,
            file: file,
            page: page,
            tab: tab,
            route: tab ? page + ':' + tab : page,
            query: query,
            body: document.body || null
        };
    }

    function adminPageList(value) {
        if (value == null || value === '') {
            return ['*'];
        }
        if (Array.isArray(value)) {
            return value.map(String).filter(Boolean);
        }

        return [String(value)];
    }

    function adminPageValueMatches(value, expected) {
        var values = adminPageList(expected);
        return values.some(function (candidate) {
            return candidate === '*' || candidate === value;
        });
    }

    function adminPageEntryMatches(entry, context) {
        var options = entry.options || {};
        var pages = adminPageList(entry.page);
        var pageMatches = pages.some(function (page) {
            return page === '*'
                || page === context.page
                || page === context.route
                || page === context.file
                || page === context.adminPath;
        });

        if (!pageMatches) {
            return false;
        }
        if (options.tab && !adminPageValueMatches(context.tab, options.tab)) {
            return false;
        }
        if (options.selector && !document.querySelector(options.selector)) {
            return false;
        }
        if (typeof options.match === 'function' && !options.match(context)) {
            return false;
        }

        return true;
    }

    function handleAdminPageError(entry, error, context) {
        if (window.console && typeof window.console.error === 'function') {
            window.console.error('[adminPage] init failed:', entry.id || entry.page || 'anonymous', error);
        }
        if (typeof window.CustomEvent === 'function') {
            window.dispatchEvent(new CustomEvent('admin:page-error', {
                detail: {
                    id: entry.id || '',
                    page: entry.page,
                    error: error,
                    context: context
                }
            }));
        }
    }

    function runAdminPages() {
        var context = adminPageContext();
        adminPageEntries.forEach(function (entry) {
            if (entry.ran && entry.once !== false) {
                return;
            }
            if (!adminPageEntryMatches(entry, context)) {
                return;
            }

            entry.ran = true;
            try {
                var result = entry.callback(context);
                if (result && typeof result.catch === 'function') {
                    result.catch(function (error) {
                        handleAdminPageError(entry, error, context);
                    });
                }
            } catch (error) {
                handleAdminPageError(entry, error, context);
            }
        });
    }

    function scheduleAdminPageRun() {
        if (adminPageRunScheduled) {
            return;
        }
        adminPageRunScheduled = true;
        onAdminDomReady(function () {
            adminPageRunScheduled = false;
            runAdminPages();
        });
    }

    function registerAdminPage(page, callback, options) {
        if (typeof page === 'function') {
            options = callback || {};
            callback = page;
            page = '*';
        }
        if (typeof callback !== 'function') {
            return null;
        }

        var entry = {
            id: (options && options.id) || String(page || '*') + ':' + adminPageEntries.length,
            page: page || '*',
            callback: callback,
            options: options || {},
            once: !options || options.once !== false,
            ran: false
        };
        adminPageEntries.push(entry);
        scheduleAdminPageRun();

        return entry.id;
    }

    window.adminReady = onAdminDomReady;
    window.adminPage = Object.assign(window.adminPage || {}, {
        register: registerAdminPage,
        run: runAdminPages,
        context: adminPageContext,
        entries: function () { return adminPageEntries.slice(); },
        ready: onAdminDomReady
    });
    window.adminPageRegistry = window.adminPage;

    var adminFloatingActionControllers = {};
    var adminFloatingActionActive = null;

    function resetFloatingActionPopover(popover) {
        if (!popover) {
            return;
        }
        popover.style.top = '';
        popover.style.left = '';
        popover.style.right = '';
        popover.style.visibility = '';
    }

    function positionFloatingActionMenu(menu, options) {
        var popover = menu ? menu.querySelector(options.popoverSelector) : null;
        var toggle = menu ? menu.querySelector(options.toggleSelector) : null;
        if (!menu || !popover || !toggle) {
            return;
        }

        if (window.matchMedia && window.matchMedia(options.mobileQuery).matches) {
            menu.classList.remove(options.floatingClass, options.upClass);
            resetFloatingActionPopover(popover);
            return;
        }

        menu.classList.add(options.floatingClass);
        popover.style.right = 'auto';
        popover.style.visibility = '';

        var viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        var triggerRect = toggle.getBoundingClientRect();
        var popoverWidth = popover.offsetWidth || options.defaultWidth;
        var popoverHeight = popover.offsetHeight || options.defaultHeight;
        var gap = options.gap;
        var left = Math.min(
            Math.max(gap, triggerRect.right - popoverWidth),
            Math.max(gap, viewportWidth - popoverWidth - gap)
        );
        var top = triggerRect.bottom + gap;
        var openUp = top + popoverHeight > viewportHeight - gap && triggerRect.top > popoverHeight + gap;
        if (openUp) {
            top = triggerRect.top - popoverHeight - gap;
        }

        top = Math.max(gap, top);
        menu.classList.toggle(options.upClass, openUp);
        popover.style.left = left + 'px';
        popover.style.top = top + 'px';

        if (window.getComputedStyle(popover).position === 'fixed') {
            var actualRect = popover.getBoundingClientRect();
            var offsetX = actualRect.left - left;
            var offsetY = actualRect.top - top;
            if (Math.abs(offsetX) > 1 || Math.abs(offsetY) > 1) {
                popover.style.left = (left - offsetX) + 'px';
                popover.style.top = (top - offsetY) + 'px';
            }
        }
    }

    function createAdminFloatingActions(options) {
        options = Object.assign({
            key: '',
            menuSelector: '.user-row-actions-menu',
            toggleSelector: 'summary',
            popoverSelector: '.user-row-actions-popover',
            readyAttribute: 'data-admin-floating-actions-ready',
            openClass: 'is-open',
            floatingClass: 'is-floating',
            upClass: 'is-up',
            mobileQuery: '(max-width: 760px)',
            gap: 8,
            defaultWidth: 190,
            defaultHeight: 260
        }, options || {});

        var state = {
            activeMenu: null,
            bound: false
        };

        function close(menu) {
            menu = menu || state.activeMenu;
            if (!menu) {
                return;
            }

            var toggle = menu.querySelector(options.toggleSelector);
            var popover = menu.querySelector(options.popoverSelector);
            menu.removeAttribute('open');
            menu.classList.remove(options.openClass, options.floatingClass, options.upClass);
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
            resetFloatingActionPopover(popover);
            if (state.activeMenu === menu) {
                state.activeMenu = null;
            }
            if (adminFloatingActionActive && adminFloatingActionActive.menu === menu) {
                adminFloatingActionActive = null;
            }
        }

        function open(menu) {
            if (!menu || !menu.querySelector(options.popoverSelector)) {
                return;
            }
            if (adminFloatingActionActive && adminFloatingActionActive.menu !== menu) {
                adminFloatingActionActive.controller.close(adminFloatingActionActive.menu);
            }
            if (state.activeMenu && state.activeMenu !== menu) {
                close(state.activeMenu);
            }

            var toggle = menu.querySelector(options.toggleSelector);
            menu.setAttribute('open', '');
            menu.classList.add(options.openClass);
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
            state.activeMenu = menu;
            adminFloatingActionActive = { controller: api, menu: menu };
            positionFloatingActionMenu(menu, options);
            window.requestAnimationFrame(function () {
                if (state.activeMenu === menu) {
                    positionFloatingActionMenu(menu, options);
                }
            });
        }

        function init(root) {
            root = root || document;
            root.querySelectorAll(options.menuSelector).forEach(function (menu) {
                if (menu.getAttribute(options.readyAttribute) === '1') {
                    return;
                }
                var toggle = menu.querySelector(options.toggleSelector);
                var popover = menu.querySelector(options.popoverSelector);
                if (!toggle || !popover) {
                    return;
                }

                menu.setAttribute(options.readyAttribute, '1');
                menu.classList.add('admin-floating-actions-menu');
                popover.classList.add('admin-floating-actions-popover');
                toggle.setAttribute('aria-haspopup', 'menu');
                toggle.setAttribute('aria-expanded', menu.hasAttribute('open') ? 'true' : 'false');
                toggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (menu.hasAttribute('open')) {
                        close(menu);
                    } else {
                        open(menu);
                    }
                });
            });

            if (state.bound) {
                return api;
            }
            state.bound = true;

            document.addEventListener('click', function (event) {
                if (state.activeMenu && !state.activeMenu.contains(event.target)) {
                    close(state.activeMenu);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && state.activeMenu) {
                    close(state.activeMenu);
                }
            });

            window.addEventListener('resize', function () {
                if (state.activeMenu) {
                    positionFloatingActionMenu(state.activeMenu, options);
                }
            });

            document.addEventListener('scroll', function () {
                if (state.activeMenu) {
                    positionFloatingActionMenu(state.activeMenu, options);
                }
            }, true);

            return api;
        }

        var api = {
            init: init,
            open: open,
            close: close,
            position: function (menu) {
                positionFloatingActionMenu(menu || state.activeMenu, options);
            },
            active: function () {
                return state.activeMenu;
            }
        };

        return api;
    }

    window.adminFloatingActions = Object.assign(window.adminFloatingActions || {}, {
        init: function (options) {
            options = options || {};
            var key = options.key || options.menuSelector || 'default';
            if (!adminFloatingActionControllers[key]) {
                adminFloatingActionControllers[key] = createAdminFloatingActions(options);
            }
            return adminFloatingActionControllers[key].init(options.root || document);
        },
        close: function () {
            if (adminFloatingActionActive) {
                adminFloatingActionActive.controller.close(adminFloatingActionActive.menu);
            }
        }
    });

    var adminModerationRestrictionLabels = {
        all: 'Tüm İşlemler',
        comment: 'Yorum Yapma',
        topic: 'Konu Oluşturma',
        upload: 'Dosya Yükleme',
        download: 'İndirme',
        message: 'Mesaj Gönderme',
        profile: 'Profil Düzenleme',
        events: 'Etkinlik Kullanımı'
    };

    function adminModerationEscapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
        });
    }

    function adminModerationEmpty(message) {
        return '<div class="ui-admin-moderation-empty">' + adminModerationEscapeHtml(message) + '</div>';
    }

    function adminModerationLoading(message) {
        return '<span class="ui-admin-muted-sm">' + adminModerationEscapeHtml(message) + '</span>';
    }

    function adminModerationReason(reason) {
        reason = String(reason || '').trim();
        return reason ? '<span class="ui-admin-moderation-reason">' + adminModerationEscapeHtml(reason) + '</span>' : '';
    }

    function adminModerationRestrictionLabel(row) {
        var raw = String(row && (row.restriction_type || row.type) || '').trim();
        return adminModerationRestrictionLabels[raw] || raw || 'Kısıtlama';
    }

    function adminModerationTone(row) {
        var tone = String(row && row.tone || '').trim();
        if (tone) {
            return tone.indexOf('is-') === 0 ? tone : 'is-' + tone;
        }

        var actionType = String(row && row.action_type || '').trim();
        if (actionType === 'ban') return 'is-danger';
        if (actionType === 'unban' || actionType.indexOf('unrestrict') === 0) return 'is-success';
        if (actionType === 'restrict' || (row && row.active)) return 'is-warning';
        return 'is-muted';
    }

    function adminModerationHistoryRows(rows, emptyMessage) {
        rows = Array.isArray(rows) ? rows : [];
        if (!rows.length) {
            return adminModerationEmpty(emptyMessage || 'Moderasyon geçmişi yok.');
        }

        return rows.map(function (row) {
            row = row || {};
            var label = row.type || row.action || row.action_type || 'Moderasyon işlemi';
            var meta = [];
            if (row.type && row.action) meta.push(row.action);
            if (row.created_at) meta.push(row.created_at);
            if (row.expires_at) meta.push('Bitiş: ' + row.expires_at);
            if (row.admin || row.actor) meta.push(row.admin || row.actor);

            return '<div class="ui-admin-moderation-row ' + adminModerationTone(row) + '">'
                + '<strong>' + adminModerationEscapeHtml(label) + '</strong>'
                + '<span>' + adminModerationEscapeHtml(meta.filter(Boolean).join(' · ')) + '</span>'
                + adminModerationReason(row.reason)
            + '</div>';
        }).join('');
    }

    window.adminModerationHistory = Object.assign(window.adminModerationHistory || {}, {
        empty: adminModerationEmpty,
        loading: adminModerationLoading,
        reason: adminModerationReason,
        rows: adminModerationHistoryRows,
        render: function (target, rows, emptyMessage) {
            if (!target) return;
            target.innerHTML = adminModerationHistoryRows(rows, emptyMessage);
        },
        restrictionLabel: adminModerationRestrictionLabel,
        escapeHtml: adminModerationEscapeHtml
    });

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

        updateAdminCsrfToken(data.csrfToken || data._token || data.csrf_token || '');
        return data;
    }

    function compactResponseText(text) {
        return String(text || '')
            .replace(/^\uFEFF/, '')
            .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, ' ')
            .replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, ' ')
            .replace(/<[^>]*>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .slice(0, 240);
    }

    function adminResponseError(response, data, rawText, defaultMessage) {
        var status = response && response.status ? Number(response.status) : 0;
        var code = data && (data.code || data.error) ? String(data.code || data.error).toLowerCase() : '';
        var rawMessage = compactResponseText(rawText);
        var rawBody = String(rawText || '');
        var rawLooksLikeHtml = /<\s*(?:!doctype|html|head|body|main|section|div|form|script|style|title)\b/i.test(rawBody);
        var message = data && data.message ? String(data.message) : '';

        if (!message && rawMessage && !rawLooksLikeHtml && rawMessage.length <= 160) {
            message = rawMessage;
        }

        if (status === 419 || code === 'csrf_token_invalid' || code === 'csrf_failed' || code === 'csrf_invalid' || code === 'csrf_refresh_required') {
            message = message || 'Guvenlik dogrulamasi yenilendi. Lutfen islemi tekrar deneyin.';
        } else if (!message && (status === 401 || status === 403 || code === 'forbidden')) {
            message = 'Bu islemi yapmak icin yetkiniz yok veya oturumunuz yenilenmis.';
        } else if (!message) {
            message = defaultMessage || (status > 0 ? 'Sunucu yaniti okunamadi. HTTP ' + status : 'Sunucudan beklenen cevap alinamadi.');
        }

        var error = new Error(message);
        error.response = response || null;
        error.status = status;
        error.code = code;
        error.data = data || null;
        error.rawText = rawText || '';
        return error;
    }

    function parseAdminJsonResponse(response) {
        return response.text().then(function (text) {
            var rawText = String(text || '').replace(/^\uFEFF/, '');
            var data = null;

            if (rawText.trim() !== '') {
                try {
                    data = JSON.parse(rawText);
                } catch (parseError) {
                    throw adminResponseError(response, null, rawText);
                }
            }

            data = applyAdminJsonResponse(data || {});
            if (!response.ok || data.success === false || data.ok === false) {
                throw adminResponseError(response, data, rawText);
            }

            return data;
        });
    }

    function parseOptionalAdminJson(response, rawText, defaultMessage) {
        var text = String(rawText || '').trim();
        if (text === '') {
            return null;
        }

        var contentType = response && response.headers ? String(response.headers.get('content-type') || '').toLowerCase() : '';
        var shouldParse = contentType.indexOf('application/json') !== -1 || /^[\[{]/.test(text);
        if (!shouldParse) {
            return null;
        }

        try {
            return applyAdminJsonResponse(JSON.parse(text));
        } catch (error) {
            throw adminResponseError(response, null, rawText, defaultMessage || 'Sunucudan gecersiz cevap alindi.');
        }
    }

    function parseAdminTextResponse(response, options) {
        var opts = options || {};

        return response.text().then(function (text) {
            var rawText = String(text || '').replace(/^\uFEFF/, '');
            var data = parseOptionalAdminJson(response, rawText, opts.invalidJsonMessage || 'Sunucudan gecersiz cevap alindi.');

            if (!response.ok || (data && (data.success === false || data.ok === false))) {
                throw adminResponseError(response, data, rawText, opts.errorMessage || 'Istek tamamlanamadi.');
            }

            return {
                text: rawText,
                data: data || null,
                response: response,
                status: response.status,
                url: response.url || '',
                contentType: response.headers ? String(response.headers.get('content-type') || '') : ''
            };
        });
    }

    function notifyAdminError(error, defaultMessage) {
        var message = error && error.message ? error.message : (defaultMessage || 'Islem tamamlanamadi.');
        if (window.adminToast && typeof window.adminToast.error === 'function') {
            window.adminToast.error(message);
            return;
        }
        renderAdminToast(message, 'error');
    }

    function isAdminCsrfError(error) {
        if (!error) {
            return false;
        }

        var code = String(error.code || '').toLowerCase();
        return Number(error.status || 0) === 419
            || code === 'csrf_token_invalid'
            || code === 'csrf_failed'
            || code === 'csrf_invalid'
            || code === 'csrf_refresh_required';
    }

    function adminBaseUri() {
        var meta = document.querySelector('meta[name="app-base-uri"]');
        return meta && meta.getAttribute('content') ? meta.getAttribute('content').replace(/\/+$/, '') : '';
    }

    function refreshAdminCsrfToken() {
        return fetch(adminBaseUri() + '/api/csrf-token.php', {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            if (!response.ok) {
                return null;
            }

            return response.text().then(function (text) {
                if (!String(text || '').trim()) {
                    return null;
                }

                try {
                    return JSON.parse(text);
                } catch (error) {
                    return null;
                }
            });
        }).then(function (data) {
            var token = data && (data.csrfToken || data._token || data.csrf_token);
            if (!token) {
                return false;
            }

            updateAdminCsrfToken(token);
            return true;
        }).catch(function () {
            return false;
        });
    }

    function buildAdminFetchOptions(options, defaultAccept) {
        var opts = Object.assign({}, options || {});
        [
            '_csrfRetried',
            'csrfRetry',
            'defaultAccept',
            'errorMessage',
            'notify',
            'notifyError'
        ].forEach(function (key) {
            delete opts[key];
        });

        var headers = new Headers(opts.headers || {});
        var token = readAdminCsrfToken();

        if (token && !headers.has('X-CSRF-Token')) {
            headers.set('X-CSRF-Token', token);
        }
        if (!headers.has('Accept')) {
            headers.set('Accept', defaultAccept || '*/*');
        }
        if (!headers.has('X-Requested-With')) {
            headers.set('X-Requested-With', 'XMLHttpRequest');
        }

        if (opts.body instanceof FormData) {
            if (token) {
                if (typeof opts.body.set === 'function') {
                    opts.body.set('_token', token);
                } else if (!opts.body.has('_token')) {
                    opts.body.append('_token', token);
                }
            }
        } else if (opts.body instanceof URLSearchParams) {
            if (token) {
                opts.body.set('_token', token);
            }
            if (!headers.has('Content-Type')) {
                headers.set('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            }
        } else if (opts.body && typeof opts.body === 'object') {
            opts.body._token = token || opts.body._token || '';
            headers.set('Content-Type', 'application/json; charset=UTF-8');
            opts.body = JSON.stringify(opts.body);
        }

        opts.credentials = opts.credentials || 'same-origin';
        opts.headers = headers;

        return opts;
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

    function setButtonBusy(button, isBusy, className) {
        if (!button || !button.classList) {
            return;
        }
        button.classList.toggle(className || 'is-ui-loading', !!isBusy);
    }

    function captureAsyncButton(button) {
        if (!button) {
            return null;
        }

        var textTarget = button._adminAsyncTextTarget || null;
        var iconTarget = button._adminAsyncIconTarget || null;

        return {
            button: button,
            html: button.innerHTML,
            value: button.value,
            disabled: button.disabled,
            className: button.className,
            ariaBusy: button.getAttribute('aria-busy'),
            textTarget: textTarget,
            textValue: textTarget ? textTarget.textContent : '',
            iconTarget: iconTarget,
            iconClass: iconTarget ? iconTarget.className : ''
        };
    }

    function setAsyncButtonLoading(button, options) {
        if (!button) {
            return null;
        }

        var opts = Object.assign({
            disabled: true,
            busyClass: 'is-ui-loading',
            className: '',
            iconClass: 'bi bi-hourglass-split',
            loadingText: 'Isleniyor...',
            loadingHtml: ''
        }, options || {});
        var textTarget = opts.textSelector && button.querySelector ? button.querySelector(opts.textSelector) : null;
        var iconTarget = opts.iconSelector && button.querySelector ? button.querySelector(opts.iconSelector) : null;

        button._adminAsyncTextTarget = textTarget;
        button._adminAsyncIconTarget = iconTarget;

        var state = captureAsyncButton(button);

        if (opts.disabled !== false) {
            button.disabled = true;
        }
        button.setAttribute('aria-busy', 'true');
        setButtonBusy(button, true, opts.busyClass);
        if (opts.className && button.classList) {
            String(opts.className).split(/\s+/).filter(Boolean).forEach(function (className) {
                button.classList.add(className);
            });
        }

        if (textTarget || iconTarget) {
            if (iconTarget && opts.iconClass) {
                iconTarget.className = opts.iconClass;
            }
            if (textTarget && opts.loadingText) {
                textTarget.textContent = opts.loadingText;
            }
        } else if (button.tagName === 'INPUT') {
            button.value = opts.loadingText || button.value;
        } else if (opts.loadingHtml) {
            button.innerHTML = opts.loadingHtml;
        } else if (opts.loadingText) {
            button.innerHTML = '<i class="' + opts.iconClass + '"></i> ' + opts.loadingText;
        }

        return state;
    }

    function restoreAsyncButton(state) {
        if (!state || !state.button) {
            return;
        }

        var button = state.button;
        button.innerHTML = state.html;
        button.value = state.value;
        button.disabled = state.disabled;
        button.className = state.className;
        if (state.ariaBusy == null) {
            button.removeAttribute('aria-busy');
        } else {
            button.setAttribute('aria-busy', state.ariaBusy);
        }
        setButtonBusy(button, false);
        delete button._adminAsyncTextTarget;
        delete button._adminAsyncIconTarget;
    }

    function markAsyncButtonSuccess(button, duration) {
        if (!button || !button.classList) {
            return;
        }
        button.classList.add('success');
        window.setTimeout(function () {
            if (button && button.classList) {
                button.classList.remove('success');
            }
        }, Number(duration || 2000));
    }

    function runAdminAsync(options, task) {
        var opts = typeof options === 'function' ? {} : (options || {});
        var runner = typeof options === 'function' ? options : task;
        var state = setAsyncButtonLoading(opts.button || null, opts);
        var succeeded = false;

        return Promise.resolve()
            .then(function () {
                return typeof runner === 'function' ? runner() : null;
            })
            .then(function (result) {
                succeeded = true;
                return result;
            })
            .catch(function (error) {
                if (typeof opts.onError === 'function') {
                    opts.onError(error);
                    return null;
                }
                throw error;
            })
            .finally(function () {
                if (opts.restoreButton !== false) {
                    restoreAsyncButton(state);
                }
                if (succeeded && opts.successClass && opts.button) {
                    markAsyncButtonSuccess(opts.button, opts.successDuration);
                }
            });
    }

    function asyncFetchJson(url, options) {
        var opts = Object.assign({}, options || {});
        var fetchOptions = Object.assign({}, opts);
        [
            'button',
            'loadingText',
            'loadingHtml',
            'iconClass',
            'className',
            'textSelector',
            'iconSelector',
            'restoreButton',
            'successClass',
            'successDuration',
            'onError'
        ].forEach(function (key) {
            delete fetchOptions[key];
        });

        return runAdminAsync(opts, function () {
            return window.adminFetchJson(url, fetchOptions);
        });
    }

    function asyncFetchText(url, options) {
        var opts = Object.assign({}, options || {});
        var fetchOptions = Object.assign({}, opts);
        [
            'button',
            'loadingText',
            'loadingHtml',
            'iconClass',
            'className',
            'textSelector',
            'iconSelector',
            'restoreButton',
            'successClass',
            'successDuration',
            'onError'
        ].forEach(function (key) {
            delete fetchOptions[key];
        });

        return runAdminAsync(opts, function () {
            return window.adminFetchText(url, fetchOptions);
        });
    }

    function asyncFetchHtml(url, options) {
        var opts = Object.assign({ defaultAccept: 'text/html, application/xhtml+xml, text/plain;q=0.9, */*;q=0.8' }, options || {});
        return asyncFetchText(url, opts);
    }

    function asyncSubmitForm(form, options) {
        var opts = Object.assign({}, options || {});
        if (!form) {
            return Promise.reject(new Error('Form bulunamadi.'));
        }

        var submitter = opts.submitter || null;
        var body = opts.body || new FormData(form);
        if (submitter && submitter.name && !body.has(submitter.name)) {
            body.append(submitter.name, submitter.value || '');
        }
        if (typeof opts.prepareBody === 'function') {
            opts.prepareBody(body, form, submitter);
        }

        return asyncFetchJson(new URL(opts.url || form.getAttribute('action') || window.location.href, window.location.href).toString(), Object.assign({}, opts, {
            method: opts.method || (form.getAttribute('method') || 'POST').toUpperCase(),
            body: body
        }));
    }

    function setAsyncProgress(progressBar, progressText, percent) {
        var safePercent = Math.max(0, Math.min(100, Math.round(Number(percent) || 0)));
        if (progressBar) {
            progressBar.style.width = safePercent + '%';
        }
        if (progressText) {
            progressText.textContent = safePercent + '%';
        }
    }

    window.adminAsync = Object.assign(window.adminAsync || {}, {
        captureButton: captureAsyncButton,
        setButtonLoading: setAsyncButtonLoading,
        restoreButton: restoreAsyncButton,
        markSuccess: markAsyncButtonSuccess,
        run: runAdminAsync,
        fetchJson: asyncFetchJson,
        fetchText: asyncFetchText,
        fetchHtml: asyncFetchHtml,
        submitForm: asyncSubmitForm,
        setProgress: setAsyncProgress
    });

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

    function showForbiddenPageDialog() {
        var forbiddenPage = document.querySelector('[data-admin-forbidden-page]');
        if (!forbiddenPage || forbiddenPage.dataset.adminForbiddenShown === '1' || !window.adminForbidden) {
            return;
        }

        forbiddenPage.dataset.adminForbiddenShown = '1';
        window.adminForbidden(forbiddenPage.dataset.adminForbiddenMessage || '');
    }

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

    function openManagedDialog(modal, options) {
        if (!modal) {
            return null;
        }
        var opts = options || {};
        var openClass = opts.openClass || 'is-open';
        var bodyClass = opts.bodyClass || 'ui-admin-dialog-open';
        var returnFocus = opts.returnFocus || document.activeElement;

        if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
            var dialog = window.TMUI.openDialog(modal, {
                openClass: openClass,
                bodyClass: bodyClass,
                initialFocus: opts.initialFocus,
                returnFocus: returnFocus,
                onClose: opts.onClose
            });
            modal.classList.add('ui-admin-modal-open');
            return dialog;
        }

        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add(openClass, 'ui-admin-modal-open');
        modal.classList.remove('is-closing');
        document.body.classList.add(bodyClass);
        syncModalViewportOffset(modal);
        syncDialogBodyLock();
        document.body.classList.add(bodyClass);

        if (opts.initialFocus) {
            window.setTimeout(function () {
                var focusTarget = modal.querySelector(opts.initialFocus);
                if (focusTarget && typeof focusTarget.focus === 'function') {
                    focusTarget.focus({ preventScroll: true });
                }
            }, 80);
        }

        return {
            close: function () {
                closeManagedDialog(modal, { onClose: opts.onClose, openClass: openClass, bodyClass: bodyClass });
            }
        };
    }

    function closeManagedDialog(modal, options) {
        if (!modal) {
            return;
        }
        var opts = typeof options === 'function' ? { onClose: options } : (options || {});
        var openClass = opts.openClass || 'is-open';
        var bodyClass = opts.bodyClass || 'ui-admin-dialog-open';
        var finish = function () {
            modal.classList.remove(openClass, 'is-open', 'is-closing', 'ui-admin-modal-open', 'active', 'is-active');
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove(bodyClass);
            syncDialogBodyLock();
            if (typeof opts.onClose === 'function') {
                opts.onClose();
            }
        };

        if (window.TMUI && typeof window.TMUI.closeDialog === 'function' && modal._tmuiDialog) {
            window.TMUI.closeDialog(modal);
            modal.classList.remove('ui-admin-modal-open');
            if (typeof opts.onClose === 'function') {
                opts.onClose();
            }
            syncDialogBodyLock();
            return;
        }

        modal.classList.add('is-closing');
        window.setTimeout(finish, Number(opts.delay || 160));
    }

    var adminManagedDialogApi = {
        alert: function (message, options) { return window.adminAlert(message, options); },
        confirm: function (message, options) { return window.adminConfirm(message, options); },
        forbidden: function (message, options) { return window.adminForbidden(message, options); },
        prompt: function (message, options) { return window.adminPrompt(message, options); },
        open: openManagedDialog,
        close: closeManagedDialog,
        refresh: initOpenModalOverlays,
        getOpen: topOpenModal,
        setBusy: setButtonBusy
    };

    window.adminDialog = Object.assign(window.adminDialog || {}, adminManagedDialogApi);
    window.adminModal = Object.assign(window.adminModal || {}, adminManagedDialogApi);
    window.openAdminManagedModal = openManagedDialog;
    window.closeAdminManagedModal = closeManagedDialog;

    window.adminUpdateCsrfToken = updateAdminCsrfToken;
    window.adminApplyResponse = applyAdminJsonResponse;
    window.adminParseJsonResponse = parseAdminJsonResponse;
    window.adminNotifyError = notifyAdminError;
    window.adminRefreshCsrfToken = refreshAdminCsrfToken;
    window.adminNotifyFromResponse = function (data, defaultType) {
        applyAdminJsonResponse(data);
        if (!data || !data.message || !window.adminToast) {
            return;
        }

        var type = data.success === false ? 'error' : (defaultType || 'success');
        if (typeof window.adminToast[type] === 'function') {
            window.adminToast[type](data.message);
        } else if (window.showToast) {
            window.showToast(data.message, type);
        }
    };
    window.adminFetchJson = function (url, options) {
        var opts = Object.assign({}, options || {});
        var retriedCsrf = !!opts._csrfRetried;
        delete opts._csrfRetried;
        var fetchOptions = buildAdminFetchOptions(opts, 'application/json');

        return fetch(url, fetchOptions).then(function (response) {
            return parseAdminJsonResponse(response).then(function (data) {
                if (opts.notify) {
                    window.adminNotifyFromResponse(data, opts.notify === true ? 'success' : String(opts.notify));
                }
                return data;
            }).catch(function (error) {
                if (!retriedCsrf && opts.csrfRetry !== false && isAdminCsrfError(error)) {
                    return refreshAdminCsrfToken().then(function (refreshed) {
                        if (!refreshed) {
                            throw error;
                        }

                        return window.adminFetchJson(url, Object.assign({}, options || {}, { _csrfRetried: true }));
                    });
                }
                var status = error && error.status ? Number(error.status) : response.status;
                var data = error && error.data ? error.data : {};
                if ((status === 401 || status === 403 || data.error === 'forbidden') && window.adminForbidden) {
                    window.adminForbidden(error.message || (data.message || ''));
                } else if (opts.notifyError !== false) {
                    notifyAdminError(error, 'Islem tamamlanamadi.');
                }
                throw error;
            });
        });
    };

    window.adminFetchText = function (url, options) {
        var opts = Object.assign({}, options || {});
        var retriedCsrf = !!opts._csrfRetried;
        delete opts._csrfRetried;
        var defaultAccept = opts.defaultAccept || 'text/plain, text/html;q=0.9, */*;q=0.8';
        var fetchOptions = buildAdminFetchOptions(opts, defaultAccept);

        return fetch(url, fetchOptions).then(function (response) {
            return parseAdminTextResponse(response, opts).catch(function (error) {
                if (!retriedCsrf && opts.csrfRetry !== false && isAdminCsrfError(error)) {
                    return refreshAdminCsrfToken().then(function (refreshed) {
                        if (!refreshed) {
                            throw error;
                        }

                        return window.adminFetchText(url, Object.assign({}, options || {}, { _csrfRetried: true }));
                    });
                }

                var status = error && error.status ? Number(error.status) : response.status;
                var data = error && error.data ? error.data : {};
                if ((status === 401 || status === 403 || data.error === 'forbidden') && window.adminForbidden) {
                    window.adminForbidden(error.message || (data.message || ''));
                } else if (opts.notifyError !== false) {
                    notifyAdminError(error, opts.errorMessage || 'Istek tamamlanamadi.');
                }
                throw error;
            });
        });
    };

    window.adminFetchHtml = function (url, options) {
        return window.adminFetchText(url, Object.assign({
            defaultAccept: 'text/html, application/xhtml+xml, text/plain;q=0.9, */*;q=0.8'
        }, options || {}));
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
                    response.clone().text().then(function (text) {
                        var data = {};
                        try {
                            data = JSON.parse(String(text || '').replace(/^\uFEFF/, ''));
                        } catch (error) {}
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

    function enhanceAdminTables(root) {
        var scope = root || document;
        scope.querySelectorAll('.admin-content table, .ui-admin-table, .admin-table, .health-table, .rate-limit-table').forEach(function (table) {
            table.classList.add('admin-ui-table');
            var wrap = table.parentElement;
            if (wrap && /wrap|responsive|container/i.test(wrap.className || '')) {
                wrap.classList.add('admin-ui-table-wrap');
            }

            var headers = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) {
                return (th.textContent || '').replace(/\s+/g, ' ').trim();
            });
            if (!headers.length) {
                return;
            }
            table.querySelectorAll('tbody tr').forEach(function (row) {
                Array.prototype.forEach.call(row.children || [], function (cell, index) {
                    if (!cell || cell.hasAttribute('data-label')) {
                        return;
                    }
                    var label = headers[index] || '';
                    if (label) {
                        cell.setAttribute('data-label', label);
                    }
                });
            });
        });
    }

    function enhanceAdminForms(root) {
        var scope = root || document;
        scope.querySelectorAll('.ui-admin-form-control, .ui-admin-form-select').forEach(function (control) {
            control.classList.add('admin-ui-field__control');
            var field = control.closest('.ui-admin-field, .admin-ui-field, .form-group, .mb-3, [data-setting-field]');
            if (field) {
                field.classList.add('admin-ui-field');
            }
        });
        scope.querySelectorAll('.ui-admin-form-label').forEach(function (label) {
            label.classList.add('admin-ui-field__label');
        });
        scope.querySelectorAll('.ui-admin-form-help, .admin-setting-conditional-help').forEach(function (help) {
            help.classList.add('admin-ui-field__help');
        });
    }

    function enhanceAdminTabs(root) {
        var scope = root || document;
        var tabContainers = [
            '.admin-tabs',
            '.ui-admin-tabs',
            '.settings-tabs',
            '.settings-subtabs',
            '.seo-subtabs',
            '.comments-subtabs',
            '.file-manager-subtabs',
            '.rate-limit-subtabs',
            '.sidebar-subtabs',
            '.bot-tabs-wrapper',
            '.site-subtabs',
            '.users-tabs',
            '.health-tabs',
            '.health-log-subtabs',
            '.users-subtabs',
            '.user-groups-subtabs',
            '.complaints-tabs',
            '.leaderboard-admin-tabs',
            '.topics-admin-tabs',
            '.notif-tabs',
            '.logs-subtabs',
            '.route-filter-subtabs',
            '.ui-admin-modal-tabs'
        ].join(',');

        scope.querySelectorAll(tabContainers).forEach(function (container) {
            if (container.dataset.adminTabsEnhanced === '1') {
                return;
            }
            container.dataset.adminTabsEnhanced = '1';
            container.classList.add('admin-ui-tabs-normalized');
            if (!container.hasAttribute('role') && container.querySelector('button')) {
                container.setAttribute('role', 'tablist');
            }

            container.querySelectorAll('a, button, .nav-link').forEach(function (tab) {
                tab.classList.add('admin-ui-tab-normalized');
                if (container.getAttribute('role') === 'tablist' && tab.tagName === 'BUTTON' && !tab.hasAttribute('role')) {
                    tab.setAttribute('role', 'tab');
                }
                if (tab.classList.contains('active') || tab.classList.contains('is-active') || tab.getAttribute('aria-current') === 'page') {
                    tab.classList.add('is-active');
                    if (tab.tagName === 'BUTTON' && !tab.hasAttribute('aria-selected')) {
                        tab.setAttribute('aria-selected', 'true');
                    }
                } else if (tab.tagName === 'BUTTON' && !tab.hasAttribute('aria-selected')) {
                    tab.setAttribute('aria-selected', 'false');
                }
                tab.querySelectorAll('.ui-admin-badge, .admin-badge, .badge, .notif-badge').forEach(function (badge) {
                    badge.classList.add('admin-ui-tab-badge');
                });
            });
        });
    }

    function enhanceAdminBadges(root) {
        var scope = root || document;
        scope.querySelectorAll('.ui-admin-badge, .admin-badge, .theme-badge, .notif-badge, .minimal-badge').forEach(function (badge) {
            badge.classList.add('admin-ui-badge-normalized');
            if (!badge.hasAttribute('data-admin-badge-normalized')) {
                badge.setAttribute('data-admin-badge-normalized', '1');
            }
        });
    }

    function enhanceAdminGrids(root) {
        var scope = root || document;
        var gridSelectors = [
            '.admin-stat-grid',
            '.ui-admin-stat-grid',
            '.comments-manager-summary-grid',
            '.complaints-summary-row',
            '.topics-stat-grid',
            '.health-summary',
            '.health-log-summary',
            '.users-summary',
            '.user-activity-summary',
            '.user-system-rule-summary',
            '.route-public-route-summary',
            '.rate-limit-rule-summary',
            '.contacts-message-panel-head-summary',
            '.notification-email-queue-summary',
            '.notification-log-summary',
            '.notif-stats',
            '.scraper-stats-grid',
            '.scraper-summary',
            '.mm-stats-grid',
            '.leaderboard-admin-stats',
            '.appeals-admin-summary',
            '.category-summary',
            '.category-stat-grid',
            '.reports-stats',
            '.ui-comment-manager-stats',
            '.queue-insight-strip',
            '.redirect-summary',
            '.action-log-summary',
            '.sidebar-builder-summary',
            '.sidebar-builder-metrics',
            '.theme-selected-summary',
            '.admin-audit-cleanup-summary',
            '.ui-admin-queue-summary',
            '.ui-admin-queue-summary-icon',
            '.dbs-alert-grid',
            '.dbs-meta',
            '.theme-metrics',
            '.theme-quick-stats'
        ].join(',');

        scope.querySelectorAll(gridSelectors).forEach(function (grid) {
            grid.classList.add('admin-ui-grid-normalized');
            Array.prototype.forEach.call(grid.children || [], function (child) {
                if (child && child.nodeType === 1) {
                    child.classList.add('admin-ui-grid-item-normalized');
                }
            });
        });
    }

    function enhanceAdminEmptyStates(root) {
        var scope = root || document;
        scope.querySelectorAll('.ui-empty, .admin-log-empty-row, .scraper-empty-table, .appeals-admin-empty-inline, .sidebar-builder-empty').forEach(function (empty) {
            empty.classList.add('admin-ui-empty-normalized');
        });
    }

    function enhanceAdminActions(root) {
        var scope = root || document;
        var actionSelectors = [
            '.action-btns',
            '.user-actions-inline',
            '.contacts-message-filter-actions',
            '.contacts-message-panel-head-summary',
            '.contacts-message-results-meta',
            '.ui-admin-table-footer',
            '.theme-actions',
            '.theme-commandbar',
            '.complaints-bulk-controls',
            '.ui-admin-page-hero-actions'
        ].join(',');

        scope.querySelectorAll(actionSelectors).forEach(function (row) {
            row.classList.add('admin-ui-action-row-normalized');
        });
    }

    function isRemoteImageUrl(url) {
        if (!url) {
            return false;
        }
        var normalizedUrl = String(url).trim();
        if (/^(data:image\/|blob:)/i.test(normalizedUrl)) {
            return false;
        }
        try {
            var parsed = new URL(normalizedUrl, window.location.href);
            return parsed.origin !== window.location.origin;
        } catch (error) {
            return false;
        }
    }

    function imagePlaceholder(className) {
        var placeholder = document.createElement('div');
        placeholder.className = 'admin-ui-image-placeholder ' + (className || '');
        placeholder.setAttribute('role', 'img');
        placeholder.setAttribute('aria-label', 'Gorsel yok');
        placeholder.innerHTML = '<i class="bi bi-image"></i>';
        return placeholder;
    }

    function bindAdminImageFallbacks(root) {
        var scope = root || document;
        scope.querySelectorAll('.admin-content img, .admin-sidebar img, .admin-topbar img').forEach(function (image) {
            if (image.dataset.adminImageBound === '1') {
                return;
            }
            image.dataset.adminImageBound = '1';

            var src = image.currentSrc || image.getAttribute('src') || '';
            if (isRemoteImageUrl(src) && image.dataset.adminAllowRemote !== '1') {
                image.replaceWith(imagePlaceholder(image.className || ''));
                return;
            }

            image.addEventListener('error', function () {
                if (image.dataset.adminImageFailed === '1') {
                    return;
                }
                image.dataset.adminImageFailed = '1';
                image.replaceWith(imagePlaceholder(image.className || ''));
            }, { once: true });
        });
    }

    function enhanceAdminSurface(root) {
        var scope = root || document;
        enhanceAdminTables(scope);
        enhanceAdminForms(scope);
        enhanceAdminTabs(scope);
        enhanceAdminBadges(scope);
        enhanceAdminGrids(scope);
        enhanceAdminEmptyStates(scope);
        enhanceAdminActions(scope);
        bindAdminImageFallbacks(scope);
    }

    function observeAdminSurfaceEnhancements() {
        if (!window.MutationObserver || document.documentElement.dataset.adminUiEnhanceObserver === '1') {
            return;
        }

        var target = document.querySelector('.admin-content') || document.body;
        if (!target) {
            return;
        }

        document.documentElement.dataset.adminUiEnhanceObserver = '1';
        var scheduled = false;
        var observer = new MutationObserver(function (mutations) {
            var shouldEnhance = mutations.some(function (mutation) {
                return Array.prototype.some.call(mutation.addedNodes || [], function (node) {
                    return node && node.nodeType === 1;
                });
            });
            if (!shouldEnhance || scheduled) {
                return;
            }

            scheduled = true;
            window.requestAnimationFrame(function () {
                scheduled = false;
                enhanceAdminSurface(target);
            });
        });

        observer.observe(target, { childList: true, subtree: true });
    }

    window.adminEnhanceTables = enhanceAdminTables;
    window.adminEnhanceForms = enhanceAdminForms;
    window.adminEnhanceTabs = enhanceAdminTabs;
    window.adminEnhanceBadges = enhanceAdminBadges;
    window.adminEnhanceGrids = enhanceAdminGrids;
    window.adminEnhanceEmptyStates = enhanceAdminEmptyStates;
    window.adminEnhanceActions = enhanceAdminActions;
    window.adminEnhanceUi = enhanceAdminSurface;
    window.adminBindImageFallbacks = bindAdminImageFallbacks;

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

    window.adminPage.register('*', function initAdminUiCore() {
        applyTheme(configuredMode());
        initOpenModalOverlays();
        observeModalLifecycle();
        requestModalOffsetSync();
        initEventsReadableNumberValues();
        enhanceAdminSurface(document);
        observeAdminSurfaceEnhancements();
        showForbiddenPageDialog();

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
    }, { id: 'admin-ui:core' });
})();
