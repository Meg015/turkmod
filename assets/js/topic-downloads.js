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
            commentWatchCount: 0,
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
        if (stage === 'login' || stage === 'comment' || stage === 'open' || stage === 'locked') {
            return stage;
        }
        if (!locked) {
            return 'open';
        }

        const normalizedReason = String(reason || '').toLowerCase().trim();
        if (normalizedReason === 'auth_required') {
            return 'login';
        }
        if (normalizedReason === 'comment_required') {
            return 'comment';
        }
        return 'locked';
    }

    function syncAccessStepUi(notice, stage) {
        if (!notice) {
            return;
        }

        const stepMap = {
            login: 'is-pending',
            comment: 'is-pending',
            open: 'is-pending',
        };
        if (stage === 'login') {
            stepMap.login = 'is-active';
        } else if (stage === 'comment') {
            stepMap.login = 'is-complete';
            stepMap.comment = 'is-active';
        } else if (stage === 'open') {
            stepMap.login = 'is-complete';
            stepMap.comment = 'is-complete';
            stepMap.open = 'is-active';
        }

        notice.querySelectorAll('[data-download-step]').forEach(function (step) {
            const key = step.getAttribute('data-download-step') || '';
            step.classList.remove('is-active', 'is-complete', 'is-pending', 'is-muted');
            step.classList.add(stepMap[key] || 'is-pending');
            if (stepMap[key] === 'is-active') {
                step.setAttribute('aria-current', 'step');
            } else {
                step.removeAttribute('aria-current');
            }
        });
    }

    function cardHref(card) {
        return card.dataset.downloadHref || card.getAttribute('href') || '#';
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
            card.classList.remove('is-ready', 'is-counting');
            card.dataset.ready = '0';
            card.dataset.counting = '0';
            card.setAttribute('href', '#');
            if (action) {
                action.textContent = reason === 'comment_required' ? state.commentCtaLabel : state.lockButtonText;
            }
            if (icon) {
                icon.className = 'bi bi-lock-fill';
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
        notice.dataset.downloadStage = nextStage;
        notice.hidden = !locked;
        notice.style.display = locked ? '' : 'none';
        const text = notice.querySelector('.topic-dl-access-notice__text, span');
        if (text && locked) {
            text.textContent = message || (reason === 'comment_required'
                ? 'İndirme linklerini görmek için önce yorum yapmanız gerekir.'
                : 'Bu içeriği görmek için kayıt olmanız veya giriş yapmanız gerekir.');
        }
        syncAccessStepUi(notice, nextStage);
    }

    async function fetchAccessState(section, state) {
        if (!state.statusApi || !state.topicId) {
            return null;
        }
        const url = state.statusApi + '?topic_id=' + encodeURIComponent(String(state.topicId)) + '&_=' + Date.now();
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });
        const data = await response.json().catch(function () { return null; });
        if (!response.ok || !data || data.success === false) {
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
            return false;
        }

        const locked = !!(data.locked || (data.access && data.access.locked));
        const reason = String((data.reason || (data.access && data.access.reason) || 'none'));
        const message = String((data.message || (data.access && data.access.message) || ''));
        const stage = String((data.stage || (data.access && data.access.stage) || ''));

        updateSectionNotice(section, state, locked, reason, message, stage);

        const cards = section.querySelectorAll('.topic-dl-card');
        cards.forEach(function (card) {
            updateCardLockedUi(card, state, locked, reason, message);
        });

        if (!locked && autoOpenCard) {
            runCountdown(autoOpenCard, state, true);
        }
        return !locked;
    }

    function openDownload(card) {
        const href = cardHref(card);
        if (!href || href === '#') {
            return;
        }
        window.open(href, '_blank', 'noopener');
    }

    function finishCountdown(card, state, autoOpen) {
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

        const timer = setInterval(function () {
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
        state.modal.hidden = true;
        state.modalBackdrop.hidden = true;
        document.body.classList.remove('topic-download-auth-open');
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
            }
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
        return code === 'csrf_token_invalid' || code === 'csrf_failed' || code === 'csrf_invalid';
    }

    async function refreshAuthCsrfToken(section, state) {
        if (!state.statusApi || !state.topicId) {
            return false;
        }

        try {
            const refreshResponse = await fetch(state.statusApi, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ topic_id: state.topicId, _token: state.csrf })
            });
            const refreshData = await refreshResponse.json().catch(function () { return null; });
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
            let response = null;
            let data = null;
            let retriedAfterCsrf = false;

            while (true) {
                payload._token = state.csrf;
                response = await fetch(state.authApi, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                data = await response.json().catch(function () { return null; });

                const tokenChanged = updateCsrfTokenFromResponse(state, section, data);
                if (!retriedAfterCsrf && isCsrfFailureResponse(response, data)) {
                    const refreshed = tokenChanged || await refreshAuthCsrfToken(section, state);
                    if (refreshed) {
                        retriedAfterCsrf = true;
                        continue;
                    }
                }
                break;
            }

            if (!response.ok || !data || data.success === false) {
                const errorMsg = isCsrfFailureResponse(response, data)
                    ? 'Guvenlik dogrulamasi yenilenemedi. Lutfen sayfayi yenileyip tekrar deneyin.'
                    : (data && (data.message || data.error) ? String(data.message || data.error) : 'Kimlik dogrulama basarisiz.');
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

    function startCommentUnlockWatcher(section, state) {
        if (!state.unlockAfterComment) {
            return;
        }
        if (state.commentWatchTimer) {
            return;
        }
        state.commentWatchCount = 0;
        state.commentWatchTimer = window.setInterval(async function () {
            state.commentWatchCount += 1;
            const unlocked = await refreshLockState(section, state, state.pendingCard || null);
            if (unlocked || state.commentWatchCount >= 15) {
                window.clearInterval(state.commentWatchTimer);
                state.commentWatchTimer = null;
                if (unlocked && state.pendingCard && state.pendingCard.dataset.locked !== '1') {
                    runCountdown(state.pendingCard, state, true);
                }
            }
        }, 1800);
    }

    function handleLockedCardClick(card, section, state) {
        const reason = lockedReason(card, state);
        const message = lockMessage(card, state);

        state.pendingCard = card;
        if (reason === 'comment_required' && message) {
            showToast(message, 'warning');
        }

        if (reason === 'comment_required') {
            focusCommentArea(state);
            startCommentUnlockWatcher(section, state);
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
            if ((section.dataset.lockReason || '') !== 'comment_required') {
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

        cards.forEach(function (card) {
            bindCard(section, state, card);
        });

        const notice = section.parentElement ? section.parentElement.querySelector('[data-download-lock-notice]') : null;
        if (notice && notice.dataset.downloadLockNoticeBound !== '1') {
            notice.dataset.downloadLockNoticeBound = '1';
            notice.addEventListener('click', function () {
                const firstLocked = section.querySelector('.topic-dl-card[data-locked="1"]');
                if (firstLocked) {
                    handleLockedCardClick(firstLocked, section, state);
                }
            });
        }

        bindCommentBridge(section, state);
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
