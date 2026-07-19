(function () {
    'use strict';

    function toBool(value, fallback) {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }
        return String(value) === '1' || String(value).toLowerCase() === 'true';
    }

    function showToast(message, type) {
        if (!message) {
            return;
        }
        if (typeof window.showToast === 'function') {
            window.showToast(message, type || 'info');
            return;
        }
        console.log('[topic-downloads]', message);
    }

    function sectionState(section) {
        const topicId = parseInt(section.dataset.topicId || '0', 10) || 0;
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const currentPath = window.location.pathname + window.location.search;

        return {
            topicId: topicId,
            csrf: section.dataset.csrf || (csrfMeta ? csrfMeta.getAttribute('content') : ''),
            countdownSeconds: Math.max(0, parseInt(section.dataset.countdownSeconds || '5', 10) || 0),
            waitText: section.dataset.waitText || 'Indirme linkiniz kontrol ediliyor, lutfen bekleyiniz',
            doneText: section.dataset.doneText || 'Indirme linkiniz hazir, indirmek icin tiklayin',
            lockButtonText: section.dataset.lockButtonText || 'Kilidi Ac',
            commentCtaLabel: section.dataset.commentCtaLabel || 'Yorumlara Git',
            lockMessage: section.dataset.lockMessage || '',
            lockReason: section.dataset.lockReason || 'none',
            downloadStage: section.dataset.downloadStage || '',
            statusApi: section.dataset.statusApi || '',
            authApi: section.dataset.authApi || '',
            loginUrl: section.dataset.loginUrl || '',
            registerUrl: section.dataset.registerUrl || '',
            commentTarget: section.dataset.commentTarget || '#comments-heading',
            currentRequestUri: section.dataset.currentRequestUri || currentPath,
            accessMode: section.dataset.accessMode || 'public',
            commentStepRequired: toBool(section.dataset.commentStepRequired, (section.dataset.accessMode || 'public') === 'members_comment'),
            successNoticeEnabled: toBool(section.dataset.successNoticeEnabled, true),
            successMessage: section.dataset.successMessage || 'Tüm erişim şartlarını tamamladınız. İndirme bağlantıları kullanıma hazır.',
            progressEnabled: toBool(section.dataset.progressEnabled, true),
            commentTitle: section.dataset.commentTitle || 'Yorum gerekli',
            progressTemplate: section.dataset.progressTemplate || '{{completed}} adımdan {{total}} adımı tamamlandı',
            progressCompleted: Math.max(0, parseInt(section.dataset.progressCompleted || '0', 10) || 0),
            progressTotal: Math.max(0, parseInt(section.dataset.progressTotal || '0', 10) || 0),
            successAnimationEnabled: toBool(section.dataset.successAnimationEnabled, true),
            successAutoCompact: toBool(section.dataset.successAutoCompact, true),
            successCompactDelay: Math.max(0, Math.min(60, parseInt(section.dataset.successCompactDelay || '5', 10) || 0)),
            highlightFirstCard: toBool(section.dataset.highlightFirstCard, true),
            pendingMessage: section.dataset.pendingMessage || 'Yorumunuz gönderildi ve yönetici onayı bekliyor. Onaylandığında indirme bağlantıları otomatik açılacak.',
            pendingButtonText: section.dataset.pendingButtonText || 'Onay Bekleniyor',
            expiredTitle: section.dataset.expiredTitle || 'Yorum erişim süreniz doldu',
            expiredMessage: section.dataset.expiredMessage || 'İndirme bağlantılarını yeniden açmak için yeni bir yorum gönderin.',
            accessUntilText: section.dataset.accessUntilText || '',
            expiresAt: section.dataset.accessExpiresAt || '',
            openAuthPopup: toBool(section.dataset.openAuthPopup, true),
            focusCommentForm: toBool(section.dataset.focusCommentForm, true),
            unlockAfterAuth: toBool(section.dataset.unlockAfterAuth, true),
            unlockAfterComment: toBool(section.dataset.unlockAfterComment, true),
            authModalTitle: section.dataset.authModalTitle || 'Indirme linklerini acmak icin giris yapin',
            authLoginLabel: section.dataset.authLoginLabel || 'Giris Yap',
            authRegisterLabel: section.dataset.authRegisterLabel || 'Kayit Ol',
            authSuccessMessage: section.dataset.authSuccessMessage || 'Oturum basariyla acildi.',
            modal: null,
            modalBackdrop: null,
            modalFeedback: null,
            modalAction: 'login',
            pendingCard: null,
            commentWatchTimer: null,
            commentWatcherActive: false,
            commentWatchStartedAt: 0,
            commentWatchInFlight: false,
            commentWatchFailures: 0,
            commentWatchWarningShown: false,
            visibilityHandler: null,
            lastAccessRefreshSucceeded: true,
            countdownTimers: new Map(),
            accessExpiryTimer: null,
            compactTimer: null,
            highlightTimer: null,
            lastFocusedElement: null,
        };
    }

    function lockedReason(card, state) {
        return card.dataset.lockReason || state.lockReason || 'none';
    }

    function lockMessage(card, state) {
        return card.dataset.lockMessage || state.lockMessage || '';
    }

    function accessStage(locked, reason, explicitStage) {
        const stage = String(explicitStage || '').toLowerCase().trim();
        if (stage === 'login' || stage === 'comment' || stage === 'pending' || stage === 'open' || stage === 'locked') {
            return stage;
        }
        if (!locked) {
            return 'open';
        }

        const normalizedReason = String(reason || '').toLowerCase().trim();
        if (normalizedReason === 'auth_required') {
            return 'login';
        }
        if (normalizedReason === 'comment_required' || normalizedReason === 'comment_expired') {
            return 'comment';
        }
        if (normalizedReason === 'comment_pending') {
            return 'pending';
        }
        return 'locked';
    }

    function syncAccessStepUi(notice, stage, success, commentStepRequired) {
        if (!notice) {
            return;
        }

        const stepMap = {
            login: 'is-pending',
            comment: 'is-pending',
            open: 'is-pending',
        };
        if (success) {
            stepMap.login = 'is-complete';
            stepMap.comment = 'is-complete';
            stepMap.open = 'is-complete';
        } else if (stage === 'login') {
            stepMap.login = 'is-active';
        } else if (stage === 'comment') {
            stepMap.login = 'is-complete';
            stepMap.comment = 'is-active';
        } else if (stage === 'pending') {
            stepMap.login = 'is-complete';
            stepMap.comment = 'is-waiting';
        } else if (stage === 'open') {
            stepMap.login = 'is-complete';
            stepMap.comment = 'is-complete';
            stepMap.open = 'is-active';
        }

        notice.querySelectorAll('[data-download-step]').forEach(function (step) {
            const key = step.getAttribute('data-download-step') || '';
            if (key === 'comment') {
                step.hidden = !commentStepRequired;
                step.setAttribute('aria-hidden', commentStepRequired ? 'false' : 'true');
            }
            step.classList.remove('is-active', 'is-complete', 'is-pending', 'is-waiting', 'is-muted');
            step.classList.add(key === 'comment' && !commentStepRequired ? 'is-muted' : (stepMap[key] || 'is-pending'));
            if (stepMap[key] === 'is-active') {
                step.setAttribute('aria-current', 'step');
            } else {
                step.removeAttribute('aria-current');
            }
            const label = key === 'login' ? 'Giriş' : (key === 'comment' ? 'Yorum' : 'İndirme bağlantısı');
            const statusLabel = stepMap[key] === 'is-complete'
                ? 'tamamlandı'
                : (stepMap[key] === 'is-active' ? 'şimdi tamamlanmalı' : (stepMap[key] === 'is-waiting' ? 'onay bekliyor' : 'sırada'));
            step.setAttribute('aria-label', label + ': ' + statusLabel);
        });

        const openIcon = notice.querySelector('[data-download-step="open"] i');
        if (openIcon) {
            openIcon.className = 'bi ' + (commentStepRequired ? 'bi-3-circle-fill' : 'bi-2-circle-fill');
        }
    }

    function cardHref(card) {
        return card.dataset.downloadHref || card.getAttribute('href') || '#';
    }

    function cancelCardCountdown(card, state, resetReady) {
        const timer = state.countdownTimers.get(card);
        if (timer) {
            window.clearInterval(timer);
            state.countdownTimers.delete(card);
        }
        card.dataset.counting = '0';
        card.removeAttribute('aria-busy');
        card.classList.remove('is-counting');
        if (resetReady) {
            card.dataset.ready = '0';
            card.classList.remove('is-ready');
        }
    }

    function updateCardLockedUi(card, state, locked, reason, message) {
        const action = card.querySelector('.topic-dl-action');
        const icon = card.querySelector('.topic-dl-icon i');
        const info = card.querySelector('.topic-dl-info');

        card.dataset.locked = locked ? '1' : '0';
        card.dataset.lockReason = locked ? reason : 'none';
        card.dataset.lockMessage = locked ? message : '';
        card.classList.toggle('is-locked', !!locked);
        card.setAttribute('aria-disabled', locked ? 'true' : 'false');

        if (locked) {
            cancelCardCountdown(card, state, true);
            card.setAttribute('href', '#');
            if (action) {
                action.textContent = reason === 'comment_required' || reason === 'comment_expired'
                    ? state.commentCtaLabel
                    : (reason === 'comment_pending' ? state.pendingButtonText : state.lockButtonText);
            }
            if (icon) {
                icon.className = reason === 'comment_pending'
                    ? 'bi bi-hourglass-split'
                    : (reason === 'comment_expired' ? 'bi bi-clock-history' : 'bi bi-lock-fill');
            }
            if (info && message) {
                let small = info.querySelector('.topic-dl-lock-message');
                if (!small) {
                    small = document.createElement('small');
                    small.className = 'topic-dl-lock-message';
                    info.appendChild(small);
                }
                small.textContent = message;
            }
            return;
        }

        card.setAttribute('href', cardHref(card));
        if (action) {
            action.textContent = card.dataset.readyText || state.doneText;
        }
        if (icon) {
            icon.className = 'bi bi-cloud-arrow-down';
        }
        const lockMsgEl = info ? info.querySelector('.topic-dl-lock-message') : null;
        if (lockMsgEl) {
            lockMsgEl.remove();
        }
    }

    function accessNoticeTitle(locked, reason, state) {
        if (!locked) {
            return 'İndirmeye hazırsınız';
        }
        if (reason === 'auth_required') {
            return 'Giriş gerekli';
        }
        if (reason === 'comment_required') {
            return state.commentTitle;
        }
        if (reason === 'comment_pending') {
            return 'Yorum onayı bekleniyor';
        }
        if (reason === 'comment_expired') {
            return state.expiredTitle;
        }
        return 'İndirme erişimi kısıtlı';
    }

    function progressText(state) {
        if (!state.progressEnabled || state.progressTotal <= 0) {
            return '';
        }
        const fallback = '{{completed}} adımdan {{total}} adımı tamamlandı';
        const template = state.progressTemplate.indexOf('{{completed}}') !== -1
            && state.progressTemplate.indexOf('{{total}}') !== -1
            ? state.progressTemplate
            : fallback;
        return template
            .split('{{completed}}').join(String(state.progressCompleted))
            .split('{{total}}').join(String(state.progressTotal));
    }

    function clearCompactTimer(state) {
        if (state.compactTimer) {
            window.clearTimeout(state.compactTimer);
            state.compactTimer = null;
        }
    }

    function scheduleSuccessCompact(notice, state) {
        clearCompactTimer(state);
        if (!notice || !state.successAutoCompact || !notice.classList.contains('is-success')) {
            return;
        }
        state.compactTimer = window.setTimeout(function compactWhenIdle() {
            const interacting = notice.matches(':hover') || notice.contains(document.activeElement);
            if (interacting) {
                state.compactTimer = window.setTimeout(compactWhenIdle, 1000);
                return;
            }
            notice.classList.add('is-compact');
            state.compactTimer = null;
        }, state.successCompactDelay * 1000);
    }

    function highlightElement(element, className, state) {
        if (!element) {
            return;
        }
        if (state.highlightTimer) {
            window.clearTimeout(state.highlightTimer);
        }
        element.classList.remove(className);
        void element.offsetWidth;
        element.classList.add(className);
        state.highlightTimer = window.setTimeout(function () {
            element.classList.remove(className);
            state.highlightTimer = null;
        }, 1800);
    }

    function firstDownloadCard(section) {
        return section.querySelector('.topic-dl-card:not([hidden])');
    }

    function updateSectionNotice(section, state, locked, reason, message, stage) {
        const nextStage = accessStage(locked, reason, stage || state.downloadStage);
        section.dataset.locked = locked ? '1' : '0';
        section.dataset.lockReason = locked ? reason : 'none';
        section.dataset.lockMessage = locked ? message : '';
        section.dataset.downloadStage = nextStage;
        state.lockReason = section.dataset.lockReason;
        state.lockMessage = section.dataset.lockMessage;
        state.downloadStage = nextStage;

        const notice = section.parentElement ? section.parentElement.querySelector('[data-download-lock-notice]') : null;
        if (!notice) {
            return;
        }
        const wasSuccess = notice.classList.contains('is-success');
        const success = !locked && state.accessMode !== 'public' && state.successNoticeEnabled;
        const visible = locked || success;
        notice.dataset.downloadStage = nextStage;
        notice.classList.toggle('is-success', success);
        notice.classList.toggle('is-approval-pending', locked && reason === 'comment_pending');
        notice.classList.toggle('is-access-expired', locked && reason === 'comment_expired');
        if (!success) {
            notice.classList.remove('is-compact', 'is-success-entering');
            clearCompactTimer(state);
        }
        notice.hidden = !visible;
        notice.style.display = visible ? '' : 'none';
        const noticeIcon = notice.querySelector(':scope > i');
        if (noticeIcon) {
            noticeIcon.className = success
                ? 'bi bi-check-circle-fill'
                : (reason === 'comment_pending'
                    ? 'bi bi-hourglass-split'
                    : (reason === 'comment_expired' ? 'bi bi-clock-history' : 'bi bi-lock-fill'));
        }
        const title = notice.querySelector('.topic-dl-access-notice__title');
        if (title && visible) {
            title.textContent = accessNoticeTitle(locked, reason, state);
        }
        const text = notice.querySelector('.topic-dl-access-notice__text, span');
        if (text && visible) {
            text.textContent = success
                ? state.successMessage
                : (reason === 'comment_pending'
                    ? state.pendingMessage
                    : (reason === 'comment_expired'
                        ? (message || state.expiredMessage)
                        : (message || (reason === 'comment_required'
                        ? 'İndirme linklerini görmek için önce yorum yapmanız gerekir.'
                        : 'Bu içeriği görmek için kayıt olmanız veya giriş yapmanız gerekir.'))));
        }
        const accessUntil = notice.querySelector('[data-download-access-until]');
        if (accessUntil) {
            accessUntil.textContent = success ? state.accessUntilText : '';
            accessUntil.hidden = !success || state.accessUntilText === '';
        }
        const progress = notice.querySelector('[data-download-progress]');
        if (progress) {
            const nextProgressText = progressText(state);
            progress.textContent = nextProgressText;
            progress.setAttribute('aria-label', nextProgressText);
            progress.hidden = nextProgressText === '';
        }
        syncAccessStepUi(notice, nextStage, success, state.commentStepRequired);
        if (success) {
            notice.classList.remove('is-compact');
            if (!wasSuccess && state.successAnimationEnabled) {
                notice.classList.add('is-success-entering');
                window.setTimeout(function () {
                    notice.classList.remove('is-success-entering');
                }, 900);
            }
            scheduleSuccessCompact(notice, state);
        }
    }

    async function fetchAccessState(section, state) {
        if (!state.statusApi || !state.topicId) {
            return null;
        }
        const url = state.statusApi + '?topic_id=' + encodeURIComponent(String(state.topicId)) + '&_=' + Date.now();
        if (!window.publicFetchJson) {
            return null;
        }
        let data = null;
        try {
            data = await window.publicFetchJson(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                },
                notifyError: false
            });
        } catch (error) {
            return null;
        }
        if (!data || data.success === false) {
            return null;
        }
        if (data._token) {
            state.csrf = data._token;
        }
        return data;
    }

    async function refreshLockState(section, state, autoOpenCard) {
        const data = await fetchAccessState(section, state);
        if (!data) {
            state.lastAccessRefreshSucceeded = false;
            return false;
        }
        state.lastAccessRefreshSucceeded = true;

        const locked = !!(data.locked || (data.access && data.access.locked));
        const reason = String((data.reason || (data.access && data.access.reason) || 'none'));
        const message = String((data.message || (data.access && data.access.message) || ''));
        const stage = String((data.stage || (data.access && data.access.stage) || ''));
        state.accessMode = String((data.mode || (data.access && data.access.mode) || state.accessMode || 'public'));
        if (Object.prototype.hasOwnProperty.call(data, 'comment_step_required')) {
            state.commentStepRequired = !!data.comment_step_required;
        } else if (data.access && Object.prototype.hasOwnProperty.call(data.access, 'comment_step_required')) {
            state.commentStepRequired = !!data.access.comment_step_required;
        } else {
            state.commentStepRequired = state.accessMode === 'members_comment';
        }
        state.progressCompleted = Math.max(0, parseInt(data.progress_completed || (data.access && data.access.progress_completed) || '0', 10) || 0);
        state.progressTotal = Math.max(0, parseInt(data.progress_total || (data.access && data.access.progress_total) || '0', 10) || 0);
        state.accessUntilText = String((data.access_until_text || (data.access && data.access.access_until_text) || ''));
        state.expiresAt = String((data.expires_at || (data.access && data.access.expires_at) || ''));

        updateSectionNotice(section, state, locked, reason, message, stage);

        const cards = section.querySelectorAll('.topic-dl-card');
        cards.forEach(function (card) {
            updateCardLockedUi(card, state, locked, reason, message);
        });

        if (!locked && state.highlightFirstCard) {
            highlightElement(autoOpenCard || firstDownloadCard(section), 'is-access-highlighted', state);
        }
        scheduleAccessExpiryRefresh(section, state, locked);
        return !locked;
    }

    function clearAccessExpiryTimer(state) {
        if (state.accessExpiryTimer) {
            window.clearTimeout(state.accessExpiryTimer);
            state.accessExpiryTimer = null;
        }
    }

    function accessExpiryTimestamp(value) {
        const normalized = String(value || '').trim().replace(' ', 'T');
        if (!normalized) {
            return 0;
        }
        const timestamp = Date.parse(normalized);
        return Number.isFinite(timestamp) ? timestamp : 0;
    }

    function scheduleAccessExpiryRefresh(section, state, locked) {
        clearAccessExpiryTimer(state);
        if (locked || !state.expiresAt) {
            return;
        }
        const expiresAt = accessExpiryTimestamp(state.expiresAt);
        if (!expiresAt) {
            return;
        }
        const maximumDelay = 2147480000;
        const remaining = expiresAt - Date.now();
        const delay = remaining <= 0 ? 0 : Math.min(maximumDelay, remaining + 250);
        state.accessExpiryTimer = window.setTimeout(function () {
            state.accessExpiryTimer = null;
            if (remaining > maximumDelay) {
                scheduleAccessExpiryRefresh(section, state, false);
                return;
            }
            refreshLockState(section, state, state.pendingCard || null).catch(function () {});
        }, delay);
    }

    function openDownload(card) {
        const href = cardHref(card);
        if (!href || href === '#') {
            return;
        }
        window.open(href, '_blank', 'noopener');
    }

    function finishCountdown(card, state, autoOpen) {
        const timer = state.countdownTimers.get(card);
        if (timer) {
            window.clearInterval(timer);
            state.countdownTimers.delete(card);
        }
        if (card.dataset.locked === '1' || !card.isConnected) {
            cancelCardCountdown(card, state, true);
            return;
        }
        card.dataset.ready = '1';
        card.dataset.counting = '0';
        card.removeAttribute('aria-busy');
        card.classList.remove('is-counting');
        card.classList.add('is-ready');
        const action = card.querySelector('.topic-dl-action');
        if (action) {
            action.textContent = state.doneText;
        }
        if (autoOpen) {
            openDownload(card);
        }
    }

    function runCountdown(card, state, autoOpen) {
        if (!card || card.dataset.locked === '1') {
            return;
        }
        if (card.dataset.ready === '1') {
            if (autoOpen) {
                openDownload(card);
            }
            return;
        }
        if (card.dataset.counting === '1') {
            return;
        }

        card.dataset.counting = '1';
        let remaining = state.countdownSeconds;
        const action = card.querySelector('.topic-dl-action');
        const button = card.querySelector('.topic-dl-button');

        card.classList.add('is-counting');
        card.setAttribute('aria-busy', 'true');
        if (button) {
            button.setAttribute('aria-live', 'polite');
        }
        if (action) {
            action.textContent = state.waitText + (remaining > 0 ? '... ' + remaining : '...');
        }

        if (remaining <= 0) {
            finishCountdown(card, state, autoOpen);
            return;
        }

        const timer = window.setInterval(function () {
            if (card.dataset.locked === '1' || !card.isConnected) {
                cancelCardCountdown(card, state, true);
                return;
            }
            remaining -= 1;
            if (remaining > 0) {
                if (action) {
                    action.textContent = state.waitText + '... ' + remaining;
                }
                return;
            }
            clearInterval(timer);
            finishCountdown(card, state, autoOpen);
        }, 1000);
        state.countdownTimers.set(card, timer);
    }

    function focusCommentArea(state) {
        const commentSection = document.querySelector('.topic-comments');
        if (!commentSection) {
            if (state.commentTarget) {
                window.location.hash = state.commentTarget.replace(/^.*#/, '');
            }
            return;
        }

        commentSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        highlightElement(commentSection, 'is-download-comment-highlighted', state);
        if (!state.focusCommentForm) {
            return;
        }

        window.setTimeout(function () {
            const input = document.getElementById('tcInput') || commentSection.querySelector('.ui-comment-textarea') || commentSection.querySelector('textarea');
            if (input) {
                input.focus();
            }
        }, 240);
    }

    function loginRedirectUrl(state) {
        if (!state.loginUrl) {
            return '';
        }
        const glue = state.loginUrl.indexOf('?') >= 0 ? '&' : '?';
        return state.loginUrl + glue + 'redirect=' + encodeURIComponent(state.currentRequestUri || (window.location.pathname + window.location.search));
    }

    function authRedirectUrl(state, data) {
        const auth = data && data.auth && typeof data.auth === 'object' ? data.auth : null;
        let redirectUrl = auth && typeof auth.redirect === 'string' ? auth.redirect.trim() : '';
        if (!redirectUrl) {
            redirectUrl = state.currentRequestUri || (window.location.pathname + window.location.search);
        }

        if (state.pendingCard && lockedReason(state.pendingCard, state) === 'comment_required' && redirectUrl.indexOf('#') === -1) {
            redirectUrl += '#comments-heading';
        }

        return redirectUrl;
    }

    function closeAuthModal(state) {
        if (!state.modal || !state.modalBackdrop) {
            return;
        }
        const focusTarget = state.lastFocusedElement;
        state.modal.hidden = true;
        state.modalBackdrop.hidden = true;
        document.body.classList.remove('topic-download-auth-open');
        state.lastFocusedElement = null;
        if (focusTarget && focusTarget.isConnected && typeof focusTarget.focus === 'function') {
            window.setTimeout(function () {
                focusTarget.focus();
            }, 0);
        }
    }

    function authModalFocusableElements(modal) {
        if (!modal) {
            return [];
        }
        return Array.from(modal.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(function (element) {
            return !element.disabled && !element.hidden && element.getClientRects().length > 0;
        });
    }

    function trapAuthModalFocus(event, state) {
        if (event.key !== 'Tab' || !state.modal || state.modal.hidden) {
            return;
        }
        const focusable = authModalFocusableElements(state.modal);
        if (focusable.length === 0) {
            event.preventDefault();
            state.modal.focus();
            return;
        }
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        } else if (!state.modal.contains(document.activeElement)) {
            event.preventDefault();
            first.focus();
        }
    }

    function switchModalTab(state, action) {
        if (!state.modal) {
            return;
        }
        state.modalAction = action === 'register' ? 'register' : 'login';
        const loginTab = state.modal.querySelector('[data-download-auth-tab="login"]');
        const registerTab = state.modal.querySelector('[data-download-auth-tab="register"]');
        const loginPane = state.modal.querySelector('[data-download-auth-pane="login"]');
        const registerPane = state.modal.querySelector('[data-download-auth-pane="register"]');

        if (loginTab) {
            loginTab.classList.toggle('is-active', state.modalAction === 'login');
            loginTab.setAttribute('aria-selected', state.modalAction === 'login' ? 'true' : 'false');
        }
        if (registerTab) {
            registerTab.classList.toggle('is-active', state.modalAction === 'register');
            registerTab.setAttribute('aria-selected', state.modalAction === 'register' ? 'true' : 'false');
        }
        if (loginPane) {
            loginPane.hidden = state.modalAction !== 'login';
        }
        if (registerPane) {
            registerPane.hidden = state.modalAction !== 'register';
        }

        updateAuthModalCopy(state);
    }

    function authIntroText(action) {
        return action === 'register'
            ? 'Yeni bir hesap olusturunca kilitli indirme kartlari sayfada otomatik guncellenir.'
            : 'Giris yaptiktan sonra kilitli indirme kartlari sayfada otomatik guncellenir.';
    }

    function updateAuthModalCopy(state) {
        if (state.modalIntro) {
            state.modalIntro.textContent = authIntroText(state.modalAction || 'login');
        }
        if (state.modalNote) {
            state.modalNote.textContent = state.lockMessage || 'Bu icerigi gormek icin kayit olmaniz veya giris yapmaniz gerekir.';
        }
    }

    function ensureAuthModal(state, section) {
        if (state.modal && state.modalBackdrop) {
            return;
        }

        const backdrop = document.createElement('div');
        backdrop.className = 'topic-download-auth-backdrop ui-modal-backdrop';
        backdrop.hidden = true;

        const modal = document.createElement('div');
        modal.className = 'topic-download-auth-modal ui-modal';
        modal.hidden = true;
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-labelledby', 'topicDownloadAuthTitle');
        modal.setAttribute('tabindex', '-1');

        modal.innerHTML = '' +
            '<div class="topic-download-auth-shell">' +
                '<header class="topic-download-auth-header">' +
                    '<span class="auth-header-icon topic-download-auth-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>' +
                    '<div class="topic-download-auth-copy">' +
                        '<span class="topic-download-auth-eyebrow">Indirme erisimi</span>' +
                        '<h3 id="topicDownloadAuthTitle"></h3>' +
                        '<p data-download-auth-intro></p>' +
                    '</div>' +
                    '<button type="button" class="topic-download-auth-close" data-download-auth-close aria-label="Kapat"><i class="bi bi-x-lg" aria-hidden="true"></i></button>' +
                '</header>' +
                '<div class="topic-download-auth-note" data-download-auth-note role="status" aria-live="polite"></div>' +
                '<div class="topic-download-auth-tabs" role="tablist" aria-label="Kimlik dogrulama">' +
                    '<button type="button" class="is-active" role="tab" data-download-auth-tab="login" aria-selected="true"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><span data-download-auth-tab-label="login"></span></button>' +
                    '<button type="button" role="tab" data-download-auth-tab="register" aria-selected="false"><i class="bi bi-person-plus" aria-hidden="true"></i><span data-download-auth-tab-label="register"></span></button>' +
                '</div>' +
                '<div class="topic-download-auth-feedback" data-download-auth-feedback hidden></div>' +
                '<div class="topic-download-auth-pane" data-download-auth-pane="login">' +
                    '<div class="auth-field ui-field">' +
                        '<label class="ui-label" for="topicDownloadLoginIdentifier">Kullanici adi veya e-posta</label>' +
                        '<span class="auth-input-shell ui-theme-auth-input"><i class="bi bi-person" aria-hidden="true"></i><input class="ui-input" id="topicDownloadLoginIdentifier" type="text" name="identifier" autocomplete="username"></span>' +
                    '</div>' +
                    '<div class="auth-field ui-field">' +
                        '<label class="ui-label" for="topicDownloadLoginPassword">Sifre</label>' +
                        '<span class="auth-input-shell ui-theme-auth-input"><i class="bi bi-key" aria-hidden="true"></i><input class="ui-input" id="topicDownloadLoginPassword" type="password" name="password" autocomplete="current-password"></span>' +
                    '</div>' +
                    '<label class="ui-check topic-download-auth-checkbox"><input type="checkbox" name="remember_session" value="1"><span>Beni hatirla</span></label>' +
                    '<button type="button" class="btn-auth topic-download-auth-submit" data-download-auth-submit="login"><span data-download-auth-submit-label="login"></span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>' +
                '</div>' +
                '<div class="topic-download-auth-pane" data-download-auth-pane="register" hidden>' +
                    '<div class="auth-field ui-field">' +
                        '<label class="ui-label" for="topicDownloadRegisterUsername">Kullanici adi</label>' +
                        '<span class="auth-input-shell ui-theme-auth-input"><i class="bi bi-person" aria-hidden="true"></i><input class="ui-input" id="topicDownloadRegisterUsername" type="text" name="username" autocomplete="username"></span>' +
                    '</div>' +
                    '<div class="auth-field ui-field">' +
                        '<label class="ui-label" for="topicDownloadRegisterEmail">E-posta</label>' +
                        '<span class="auth-input-shell ui-theme-auth-input"><i class="bi bi-envelope" aria-hidden="true"></i><input class="ui-input" id="topicDownloadRegisterEmail" type="email" name="email" autocomplete="email"></span>' +
                    '</div>' +
                    '<div class="topic-download-auth-fields topic-download-auth-fields--split">' +
                        '<div class="auth-field ui-field">' +
                            '<label class="ui-label" for="topicDownloadRegisterPassword">Sifre</label>' +
                            '<span class="auth-input-shell ui-theme-auth-input"><i class="bi bi-key" aria-hidden="true"></i><input class="ui-input" id="topicDownloadRegisterPassword" type="password" name="password" autocomplete="new-password"></span>' +
                        '</div>' +
                        '<div class="auth-field ui-field">' +
                            '<label class="ui-label" for="topicDownloadRegisterPasswordConfirm">Sifre tekrar</label>' +
                            '<span class="auth-input-shell ui-theme-auth-input"><i class="bi bi-key" aria-hidden="true"></i><input class="ui-input" id="topicDownloadRegisterPasswordConfirm" type="password" name="password_confirm" autocomplete="new-password"></span>' +
                        '</div>' +
                    '</div>' +
                    '<label class="ui-check topic-download-auth-checkbox"><input type="checkbox" name="remember_session" value="1"><span>Kayittan sonra beni hatirla</span></label>' +
                    '<button type="button" class="btn-auth topic-download-auth-submit" data-download-auth-submit="register"><span data-download-auth-submit-label="register"></span><i class="bi bi-arrow-right" aria-hidden="true"></i></button>' +
                '</div>' +
            '</div>';

        state.modal = modal;
        state.modalBackdrop = backdrop;
        state.modalFeedback = modal.querySelector('[data-download-auth-feedback]');
        state.modalIntro = modal.querySelector('[data-download-auth-intro]');
        state.modalNote = modal.querySelector('[data-download-auth-note]');

        const title = modal.querySelector('#topicDownloadAuthTitle');
        if (title) {
            title.textContent = state.authModalTitle;
        }
        const loginTabLabel = modal.querySelector('[data-download-auth-tab-label="login"]');
        if (loginTabLabel) {
            loginTabLabel.textContent = state.authLoginLabel;
        }
        const registerTabLabel = modal.querySelector('[data-download-auth-tab-label="register"]');
        if (registerTabLabel) {
            registerTabLabel.textContent = state.authRegisterLabel;
        }
        const loginSubmitLabel = modal.querySelector('[data-download-auth-submit-label="login"]');
        if (loginSubmitLabel) {
            loginSubmitLabel.textContent = state.authLoginLabel;
        }
        const registerSubmitLabel = modal.querySelector('[data-download-auth-submit-label="register"]');
        if (registerSubmitLabel) {
            registerSubmitLabel.textContent = state.authRegisterLabel;
        }

        backdrop.addEventListener('click', function () { closeAuthModal(state); });
        modal.addEventListener('click', function (event) {
            const closeBtn = event.target.closest('[data-download-auth-close]');
            if (closeBtn) {
                closeAuthModal(state);
                return;
            }
            const tabBtn = event.target.closest('[data-download-auth-tab]');
            if (tabBtn) {
                switchModalTab(state, tabBtn.getAttribute('data-download-auth-tab') || 'login');
                return;
            }
            const submitBtn = event.target.closest('[data-download-auth-submit]');
            if (submitBtn) {
                submitAuth(state, section, submitBtn.getAttribute('data-download-auth-submit') || 'login', submitBtn);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && state.modal && !state.modal.hidden) {
                closeAuthModal(state);
                return;
            }
            trapAuthModalFocus(event, state);
        });

        document.body.appendChild(backdrop);
        document.body.appendChild(modal);
    }

    function modalFieldValue(modal, pane, name) {
        const field = modal.querySelector('[data-download-auth-pane="' + pane + '"] [name="' + name + '"]');
        if (!field) {
            return '';
        }
        if (field.type === 'checkbox') {
            return field.checked ? '1' : '';
        }
        return (field.value || '').trim();
    }

    function setModalFeedback(state, text, tone) {
        if (!state.modalFeedback) {
            return;
        }
        if (!text) {
            state.modalFeedback.hidden = true;
            state.modalFeedback.className = 'topic-download-auth-feedback';
            state.modalFeedback.textContent = '';
            return;
        }
        state.modalFeedback.hidden = false;
        state.modalFeedback.className = 'topic-download-auth-feedback is-' + (tone || 'info');
        state.modalFeedback.textContent = text;
    }

    function updateCsrfTokenFromResponse(state, section, data) {
        if (!data || typeof data !== 'object') {
            return false;
        }
        const nextToken = typeof data._token === 'string' ? data._token.trim() : '';
        if (!nextToken) {
            return false;
        }
        const changed = nextToken !== state.csrf;
        state.csrf = nextToken;
        section.dataset.csrf = nextToken;
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            csrfMeta.setAttribute('content', nextToken);
        }
        document.querySelectorAll('input[name="_token"], input[name="csrf_token"]').forEach(function (input) {
            input.value = nextToken;
        });
        return changed;
    }

    function isCsrfFailureResponse(response, data) {
        if (response && response.status === 419) {
            return true;
        }
        if (!data || typeof data !== 'object') {
            return false;
        }
        const code = String(data.error || data.code || '').toLowerCase();
        return code === 'csrf_token_invalid' || code === 'csrf_failed' || code === 'csrf_invalid' || code === 'csrf_refresh_required';
    }

    async function refreshAuthCsrfToken(section, state) {
        try {
            if (window.publicApi && typeof window.publicApi.refreshCsrfToken === 'function') {
                const refreshed = await window.publicApi.refreshCsrfToken();
                const nextToken = typeof window.publicApi.csrfToken === 'function' ? window.publicApi.csrfToken() : '';
                if (refreshed && nextToken) {
                    updateCsrfTokenFromResponse(state, section, { _token: nextToken });
                    return true;
                }
            }
        } catch (error) {}

        try {
            if (window.publicFetchJson) {
                const refreshData = await window.publicFetchJson(state.statusApi, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: { topic_id: state.topicId, _token: state.csrf },
                    notifyError: false,
                    csrfRetry: false
                });
                return updateCsrfTokenFromResponse(state, section, refreshData);
            }
        } catch (error) {}

        if (!state.statusApi || !state.topicId) {
            return false;
        }

        try {
            if (!window.publicFetchJson) {
                return false;
            }
            const refreshData = await window.publicFetchJson(state.statusApi, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: { topic_id: state.topicId, _token: state.csrf },
                notifyError: false,
                csrfRetry: false
            });
            return updateCsrfTokenFromResponse(state, section, refreshData);
        } catch (error) {
            return false;
        }
    }

    async function submitAuth(state, section, action, submitBtn) {
        if (!state.authApi) {
            setModalFeedback(state, 'Auth API adresi bulunamadi.', 'error');
            return;
        }

        const payload = {
            action: action,
            _token: state.csrf,
            redirect: state.currentRequestUri || (window.location.pathname + window.location.search)
        };

        if (action === 'login') {
            payload.identifier = modalFieldValue(state.modal, 'login', 'identifier');
            payload.password = modalFieldValue(state.modal, 'login', 'password');
            payload.remember_session = modalFieldValue(state.modal, 'login', 'remember_session') === '1' ? 1 : 0;
        } else {
            payload.username = modalFieldValue(state.modal, 'register', 'username');
            payload.email = modalFieldValue(state.modal, 'register', 'email');
            payload.password = modalFieldValue(state.modal, 'register', 'password');
            payload.password_confirm = modalFieldValue(state.modal, 'register', 'password_confirm');
            payload.remember_session = modalFieldValue(state.modal, 'register', 'remember_session') === '1' ? 1 : 0;
        }

        if (!payload.identifier && action === 'login') {
            setModalFeedback(state, 'Kullanici adi veya e-posta zorunludur.', 'warning');
            return;
        }
        if (!payload.password) {
            setModalFeedback(state, 'Sifre zorunludur.', 'warning');
            return;
        }
        if (action === 'register' && !payload.username) {
            setModalFeedback(state, 'Kullanici adi zorunludur.', 'warning');
            return;
        }
        if (action === 'register' && !payload.email) {
            setModalFeedback(state, 'E-posta zorunludur.', 'warning');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.dataset.originalText = submitBtn.textContent || '';
        submitBtn.textContent = 'Islem yapiliyor...';
        setModalFeedback(state, '', 'info');

        try {
            if (!window.publicFetchJson) {
                setModalFeedback(state, 'Public API helper yuklenemedi.', 'error');
                return;
            }

            let response = null;
            let data = null;
            let requestError = null;
            let csrfRetryCount = 0;

            while (true) {
                payload._token = state.csrf;
                requestError = null;
                try {
                    data = await window.publicFetchJson(state.authApi, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: payload,
                        notifyError: false,
                        csrfRetry: false
                    });
                    response = { ok: true, status: 200 };
                } catch (error) {
                    requestError = error;
                    data = error && error.data ? error.data : null;
                    response = { ok: false, status: Number(error && error.status ? error.status : 0) };
                }

                const tokenChanged = updateCsrfTokenFromResponse(state, section, data);
                if (csrfRetryCount < 2 && isCsrfFailureResponse(response, data)) {
                    const refreshed = tokenChanged || await refreshAuthCsrfToken(section, state);
                    if (refreshed) {
                        csrfRetryCount += 1;
                        continue;
                    }
                }
                break;
            }

            if (!response.ok || !data || data.success === false) {
                if (isCsrfFailureResponse(response, data)) {
                    setModalFeedback(state, 'Form yenilendi. Lutfen tekrar gonderin.', 'info');
                    return;
                }
                const errorMsg = data && (data.message || data.error)
                    ? String(data.message || data.error)
                    : (requestError && requestError.message ? requestError.message : 'Kimlik dogrulama basarisiz.');
                setModalFeedback(state, errorMsg, 'error');
                return;
            }

            setModalFeedback(state, state.authSuccessMessage || 'Oturum basariyla acildi.', 'success');
            showToast(data.message || state.authSuccessMessage || 'Oturum basariyla acildi.', 'success');
            const redirectUrl = authRedirectUrl(state, data);
            const shouldRefreshAfterAuth = state.unlockAfterAuth !== false;

            window.setTimeout(function () {
                closeAuthModal(state);
                if (!redirectUrl) {
                    if (shouldRefreshAfterAuth) {
                        window.location.reload();
                    } else {
                        refreshLockState(section, state, state.pendingCard || null).catch(function () {});
                    }
                    return;
                }

                const currentUrl = window.location.pathname + window.location.search;
                const redirectBase = redirectUrl.replace(/#.*$/, '');
                if (redirectBase === currentUrl) {
                    const hashIndex = redirectUrl.indexOf('#');
                    if (hashIndex >= 0) {
                        window.location.hash = redirectUrl.slice(hashIndex);
                    }
                    if (shouldRefreshAfterAuth) {
                        window.location.reload();
                    } else {
                        refreshLockState(section, state, state.pendingCard || null).catch(function () {});
                    }
                    return;
                }

                window.location.replace(redirectUrl);
            }, 260);
        } catch (error) {
            setModalFeedback(state, 'Baglanti hatasi. Lutfen tekrar deneyin.', 'error');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = submitBtn.dataset.originalText || submitBtn.textContent;
        }
    }

    function openAuthModal(state, section) {
        ensureAuthModal(state, section);
        if (!state.modal || !state.modalBackdrop) {
            return;
        }

        if (state.modal.hidden && document.activeElement && !state.modal.contains(document.activeElement)) {
            state.lastFocusedElement = document.activeElement;
        }

        switchModalTab(state, state.modalAction || 'login');
        updateAuthModalCopy(state);
        setModalFeedback(state, '', 'info');

        state.modalBackdrop.hidden = false;
        state.modal.hidden = false;
        document.body.classList.add('topic-download-auth-open');

        const firstInput = state.modal.querySelector('[data-download-auth-pane="' + (state.modalAction || 'login') + '"] input');
        if (firstInput) {
            window.setTimeout(function () {
                firstInput.focus();
            }, 30);
        }
    }

    function commentWatchDelay(state) {
        if (state.commentWatchFailures > 0) {
            return Math.min(30000, 2000 * Math.pow(2, Math.min(4, state.commentWatchFailures - 1)));
        }
        const elapsed = Math.max(0, Date.now() - state.commentWatchStartedAt);
        if (elapsed < 30000) {
            return 2000;
        }
        if (elapsed < 300000) {
            return 5000;
        }
        return 15000;
    }

    function stopCommentUnlockWatcher(state) {
        state.commentWatcherActive = false;
        state.commentWatchInFlight = false;
        if (state.commentWatchTimer) {
            window.clearTimeout(state.commentWatchTimer);
            state.commentWatchTimer = null;
        }
        if (state.visibilityHandler) {
            document.removeEventListener('visibilitychange', state.visibilityHandler);
            state.visibilityHandler = null;
        }
    }

    function scheduleCommentUnlockWatch(section, state, delay) {
        if (!state.commentWatcherActive || document.hidden || state.commentWatchInFlight) {
            return;
        }
        if (state.commentWatchTimer) {
            window.clearTimeout(state.commentWatchTimer);
        }
        state.commentWatchTimer = window.setTimeout(function () {
            state.commentWatchTimer = null;
            runCommentUnlockWatch(section, state);
        }, Math.max(0, delay));
    }

    async function runCommentUnlockWatch(section, state) {
        if (!state.commentWatcherActive || state.commentWatchInFlight || document.hidden) {
            return;
        }
        if (!section.isConnected) {
            stopCommentUnlockWatcher(state);
            return;
        }

        state.commentWatchInFlight = true;
        const unlocked = await refreshLockState(section, state, state.pendingCard || null);
        state.commentWatchInFlight = false;

        if (!state.commentWatcherActive) {
            return;
        }
        if (unlocked) {
            stopCommentUnlockWatcher(state);
            return;
        }

        if (state.lastAccessRefreshSucceeded) {
            state.commentWatchFailures = 0;
        } else {
            state.commentWatchFailures += 1;
            if (state.commentWatchFailures >= 3 && !state.commentWatchWarningShown) {
                state.commentWatchWarningShown = true;
                showToast('Erişim durumu şu anda yenilenemiyor; bağlantı geri geldiğinde otomatik olarak tekrar denenecek.', 'warning');
            }
        }
        scheduleCommentUnlockWatch(section, state, commentWatchDelay(state));
    }

    function startCommentUnlockWatcher(section, state) {
        if (!state.unlockAfterComment) {
            return;
        }
        if (state.commentWatcherActive) {
            if (!document.hidden && !state.commentWatchInFlight && !state.commentWatchTimer) {
                scheduleCommentUnlockWatch(section, state, 0);
            }
            return;
        }

        state.commentWatcherActive = true;
        state.commentWatchStartedAt = Date.now();
        state.commentWatchFailures = 0;
        state.commentWatchWarningShown = false;
        state.visibilityHandler = function () {
            if (!state.commentWatcherActive) {
                return;
            }
            if (document.hidden) {
                if (state.commentWatchTimer) {
                    window.clearTimeout(state.commentWatchTimer);
                    state.commentWatchTimer = null;
                }
                return;
            }
            scheduleCommentUnlockWatch(section, state, 0);
        };
        document.addEventListener('visibilitychange', state.visibilityHandler);
        scheduleCommentUnlockWatch(section, state, 0);
    }

    function handleLockedCardClick(card, section, state) {
        const reason = lockedReason(card, state);
        const message = lockMessage(card, state);

        state.pendingCard = card;
        if ((reason === 'comment_required' || reason === 'comment_expired') && message) {
            showToast(message, 'warning');
        }

        if (reason === 'comment_pending') {
            showToast(state.pendingMessage || message, 'info');
            startCommentUnlockWatcher(section, state);
            return;
        }

        if (reason === 'comment_required' || reason === 'comment_expired') {
            focusCommentArea(state);
            return;
        }

        if (state.openAuthPopup) {
            state.modalAction = 'login';
            openAuthModal(state, section);
            return;
        }

        const fallbackLogin = loginRedirectUrl(state);
        if (fallbackLogin) {
            window.location.href = fallbackLogin;
            return;
        }

        if (message) {
            showToast(message, 'warning');
        }
    }

    function bindCard(section, state, card) {
        if (card.dataset.downloadLockInterceptorBound !== '1') {
            card.dataset.downloadLockInterceptorBound = '1';
            card.addEventListener('click', function (event) {
                if (card.dataset.locked === '1') {
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                    handleLockedCardClick(card, section, state);
                }
            }, true);
        }

        if (card.dataset.downloadHandlerBound === '1' || card.dataset.downloadCountdownBound === '1') {
            return;
        }

        card.dataset.downloadHandlerBound = '1';
        card.dataset.downloadCountdownBound = '1';
        card.addEventListener('click', function (event) {
            event.preventDefault();
            if (card.dataset.locked === '1') {
                return;
            }
            if (card.dataset.ready === '1') {
                openDownload(card);
                return;
            }
            runCountdown(card, state, false);
        });
    }

    function bindCommentBridge(section, state) {
        const schedule = function () {
            if ((section.dataset.locked || '0') !== '1') {
                return;
            }
            if (!['comment_required', 'comment_expired'].includes(section.dataset.lockReason || '')) {
                return;
            }
            startCommentUnlockWatcher(section, state);
        };

        document.addEventListener('click', function (event) {
            const submitBtn = event.target.closest('#tcSubmit, .ui-comment-inline-submit, .ui-comment-btn-submit');
            if (!submitBtn) {
                return;
            }
            window.setTimeout(schedule, 250);
        });

        window.addEventListener('topic-comment:created', schedule);
        document.addEventListener('topic-comment:created', schedule);
        document.addEventListener('topic-comment:deleted', function (event) {
            const detailTopicId = event && event.detail ? parseInt(event.detail.topicId || '0', 10) : 0;
            if (detailTopicId > 0 && detailTopicId !== state.topicId) {
                return;
            }
            refreshLockState(section, state, state.pendingCard || null).catch(function () {});
        });
    }

    function initSection(section) {
        const state = sectionState(section);
        const cards = Array.from(section.querySelectorAll('.topic-dl-card'));

        updateSectionNotice(
            section,
            state,
            toBool(section.dataset.locked, false),
            section.dataset.lockReason || 'none',
            section.dataset.lockMessage || '',
            section.dataset.downloadStage || ''
        );
        scheduleAccessExpiryRefresh(section, state, (section.dataset.locked || '0') === '1');

        cards.forEach(function (card) {
            bindCard(section, state, card);
        });

        const notice = section.parentElement ? section.parentElement.querySelector('[data-download-lock-notice]') : null;
        if (notice && notice.dataset.downloadLockNoticeBound !== '1') {
            notice.dataset.downloadLockNoticeBound = '1';
            notice.addEventListener('click', function (event) {
                if (notice.classList.contains('is-success') && notice.classList.contains('is-compact')) {
                    notice.classList.remove('is-compact');
                    scheduleSuccessCompact(notice, state);
                    return;
                }
                if (event.target.closest('button, a, input, select, textarea')) {
                    return;
                }
                const firstLocked = section.querySelector('.topic-dl-card[data-locked="1"]');
                if (firstLocked) {
                    handleLockedCardClick(firstLocked, section, state);
                }
            });
        }

        bindCommentBridge(section, state);
        if ((section.dataset.lockReason || '') === 'comment_pending') {
            startCommentUnlockWatcher(section, state);
        }
        window.addEventListener('pagehide', function () {
            stopCommentUnlockWatcher(state);
            clearAccessExpiryTimer(state);
            state.countdownTimers.forEach(function (timer) {
                window.clearInterval(timer);
            });
            state.countdownTimers.clear();
        }, { once: true });
    }

    function boot() {
        document.querySelectorAll('.topic-dl-section').forEach(initSection);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
