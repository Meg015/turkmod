var editModalController = null;
var COMMENT_READ_MORE_LIMIT = 160;
var commentActionsMenuController = null;
var commentUserInsightController = null;
var commentUserDetailCache = {};
var COMMENT_RESTRICTION_LABELS = {
    all: 'Tüm İşlemler',
    comment: 'Yorum Yapma',
    topic: 'Konu Oluşturma',
    upload: 'Dosya Yükleme',
    download: 'İndirme',
    message: 'Mesaj Gönderme',
    profile: 'Profil Düzenleme',
    events: 'Etkinlik Kullanımı'
};

function commentManagerCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function cmEscHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function cmConfirm(message, options) {
    if (typeof adminConfirm === 'function') {
        return adminConfirm(message, options || {});
    }
    return Promise.resolve(window.confirm(String(message).replace(/<[^>]+>/g, '')));
}

function openCommentManagedModal(modal, options) {
    if (!modal) return null;
    if (window.adminModal && typeof window.adminModal.open === 'function') {
        return window.adminModal.open(modal, options || {});
    }
    if (window.openAdminManagedModal && window.openAdminManagedModal !== openCommentManagedModal) {
        return window.openAdminManagedModal(modal, options || {});
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open', 'ui-admin-modal-open');
    var focusTarget = options && options.initialFocus ? modal.querySelector(options.initialFocus) : null;
    if (focusTarget) focusTarget.focus();
    return null;
}

function closeCommentManagedModal(modal, resetCallback) {
    if (!modal) return;
    if (window.adminModal && typeof window.adminModal.close === 'function') {
        window.adminModal.close(modal, resetCallback);
        return;
    }
    if (window.closeAdminManagedModal && window.closeAdminManagedModal !== closeCommentManagedModal) {
        window.closeAdminManagedModal(modal, resetCallback);
        return;
    }
    modal.classList.remove('is-open', 'ui-admin-modal-open');
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    if (typeof resetCallback === 'function') resetCallback();
}

function cmModerationEmpty(message) {
    if (window.adminModerationHistory && typeof window.adminModerationHistory.empty === 'function') {
        return window.adminModerationHistory.empty(message);
    }
    return '<div class="ui-admin-moderation-empty">' + cmEscHtml(message) + '</div>';
}

function cmModerationLoading(message) {
    if (window.adminModerationHistory && typeof window.adminModerationHistory.loading === 'function') {
        return window.adminModerationHistory.loading(message);
    }
    return '<span class="ui-admin-muted-sm">' + cmEscHtml(message) + '</span>';
}

function cmModerationReason(reason) {
    if (window.adminModerationHistory && typeof window.adminModerationHistory.reason === 'function') {
        return window.adminModerationHistory.reason(reason);
    }
    reason = String(reason || '').trim();
    return reason ? '<span class="ui-admin-moderation-reason">' + cmEscHtml(reason) + '</span>' : '';
}

function cmRestrictionLabel(row) {
    if (window.adminModerationHistory && typeof window.adminModerationHistory.restrictionLabel === 'function') {
        return window.adminModerationHistory.restrictionLabel(row);
    }
    var raw = String(row && (row.restriction_type || row.type) || '').trim();
    return COMMENT_RESTRICTION_LABELS[raw] || raw || 'Kısıtlama';
}

function fetchCommentUserDetails(userId) {
    return window.adminFetchJson('api/user-details.php?id=' + encodeURIComponent(userId), { notifyError: false })
        .then(function (res) {
            if (!res || (!res.success && !res.ok)) {
                throw new Error((res && res.message) || 'Kullanıcı bilgisi alınamadı.');
            }
            return res.data || {};
        });
}

function commentBanContextElements(prefix) {
    prefix = prefix || 'comment-ban';
    return {
        current: document.querySelector('[data-' + prefix + '-current]'),
        history: document.querySelector('[data-' + prefix + '-history]')
    };
}

function renderCommentBanContext(data, prefix) {
    var elements = commentBanContextElements(prefix);
    var current = elements.current;
    var history = elements.history;
    var isBanned = Number(data && data.is_banned) === 1;
    if (current) {
        current.innerHTML = isBanned
            ? '<div class="ui-admin-moderation-current is-danger"><strong><i class="bi bi-slash-circle"></i> Aktif ban var</strong><span>' + cmEscHtml(data.banned_at || 'Tarih yok') + '</span>' + cmModerationReason(data.ban_reason) + '</div>'
            : '<div class="ui-admin-moderation-current is-success"><strong><i class="bi bi-check-circle"></i> Aktif ban yok</strong><span>Kullanıcı şu anda banlı değil.</span></div>';
    }
    if (history) {
        var rows = Array.isArray(data && data.moderation_history) ? data.moderation_history : (Array.isArray(data && data.ban_history) ? data.ban_history : []);
        if (window.adminModerationHistory && typeof window.adminModerationHistory.rows === 'function') {
            history.innerHTML = window.adminModerationHistory.rows(rows, 'Moderasyon geçmişi yok.');
        } else {
            history.innerHTML = rows.length ? rows.map(function (row) {
                return '<div class="ui-admin-moderation-row ' + (row.action_type === 'unban' ? 'is-success' : 'is-danger') + '"><strong>' + cmEscHtml(row.action || '') + '</strong><span>' + cmEscHtml(row.created_at || '') + (row.admin ? ' · ' + cmEscHtml(row.admin) : '') + '</span>' + cmModerationReason(row.reason) + '</div>';
            }).join('') : cmModerationEmpty('Moderasyon geçmişi yok.');
        }
    }
}

function renderCommentRestrictionContext(data) {
    var current = document.querySelector('[data-comment-restriction-current]');
    var history = document.querySelector('[data-comment-restriction-history]');
    var activeRows = Array.isArray(data && data.restrictions) ? data.restrictions : [];
    var historyRows = Array.isArray(data && data.moderation_history) ? data.moderation_history : (Array.isArray(data && data.restriction_history) ? data.restriction_history : []);
    if (current) {
        current.innerHTML = activeRows.length ? activeRows.map(function (row) {
            return '<div class="ui-admin-moderation-row is-warning"><strong>' + cmEscHtml(cmRestrictionLabel(row)) + '</strong><span>Bitiş: ' + cmEscHtml(row.expires_at || 'Süresiz') + (row.admin_name ? ' · ' + cmEscHtml(row.admin_name) : '') + '</span>' + cmModerationReason(row.reason) + '</div>';
        }).join('') : cmModerationEmpty('Aktif kısıtlama yok.');
    }
    if (history) {
        if (window.adminModerationHistory && typeof window.adminModerationHistory.rows === 'function') {
            history.innerHTML = window.adminModerationHistory.rows(historyRows, 'Moderasyon geçmişi yok.');
        } else {
            history.innerHTML = historyRows.length ? historyRows.map(function (row) {
                var meta = [row.action || '', row.created_at || ''];
                if (row.expires_at) meta.push('Bitiş: ' + row.expires_at);
                if (row.admin) meta.push(row.admin);
                var tone = row.active ? 'is-warning' : (String(row.action_type || '').indexOf('unrestrict') === 0 ? 'is-success' : 'is-muted');
                return '<div class="ui-admin-moderation-row ' + tone + '"><strong>' + cmEscHtml(row.type || 'Kısıtlama') + '</strong><span>' + cmEscHtml(meta.filter(Boolean).join(' · ')) + '</span>' + cmModerationReason(row.reason) + '</div>';
            }).join('') : cmModerationEmpty('Moderasyon geçmişi yok.');
        }
    }
}

function ensureCommentActionsMenuController() {
    if (!commentActionsMenuController && window.adminFloatingActions) {
        commentActionsMenuController = window.adminFloatingActions.init({
            key: 'comments-manager-actions',
            menuSelector: '[data-comment-actions-menu]',
            toggleSelector: '[data-comment-actions-toggle]',
            popoverSelector: '[data-comment-actions-popover]',
            readyAttribute: 'data-comment-actions-ready'
        });
    }
    return commentActionsMenuController;
}

function closeCommentActionsMenu(menu) {
    var controller = ensureCommentActionsMenuController();
    if (controller) {
        controller.close(menu);
    }
}

function initCommentActionsMenus() {
    var controller = ensureCommentActionsMenuController();
    if (controller) {
        controller.init();
    }
}

function ensureCommentUserInsightController() {
    if (!commentUserInsightController && window.adminFloatingActions) {
        commentUserInsightController = window.adminFloatingActions.init({
            key: 'comments-manager-user-insights',
            menuSelector: '[data-comment-user-insight-menu]',
            toggleSelector: '[data-comment-user-insight-toggle]',
            popoverSelector: '[data-comment-user-insight-popover]',
            readyAttribute: 'data-comment-user-insight-ready',
            defaultWidth: 320,
            defaultHeight: 380
        });
    }
    return commentUserInsightController;
}

function closeCommentUserInsightMenu(menu) {
    var controller = ensureCommentUserInsightController();
    if (controller) {
        controller.close(menu);
    }
}

function commentInsightEmpty(message) {
    return '<div class="comments-manager-user-insight-empty">' + cmEscHtml(message) + '</div>';
}

function commentInsightRestrictionRows(rows) {
    rows = Array.isArray(rows) ? rows : [];
    if (!rows.length) {
        return commentInsightEmpty('Aktif kısıtlama yok.');
    }
    return rows.map(function (row) {
        var meta = ['Bitiş: ' + (row.expires_at || 'Süresiz')];
        if (row.admin_name) meta.push(row.admin_name);
        return '<div class="ui-admin-moderation-row is-warning"><strong><i class="bi bi-shield-exclamation"></i> ' + cmEscHtml(cmRestrictionLabel(row)) + '</strong><span>' + cmEscHtml(meta.join(' · ')) + '</span>' + cmModerationReason(row.reason) + '</div>';
    }).join('');
}

function commentInsightSetChipState(menu, data) {
    var chip = menu.querySelector('.comments-manager-user-chip');
    var status = menu.querySelector('.comments-manager-user-chip__status');
    if (!chip || !status) return;
    var activeRows = Array.isArray(data && data.restrictions) ? data.restrictions : [];
    var isBanned = Number(data && data.is_banned) === 1;
    var className = isBanned ? 'is-danger' : (activeRows.length ? 'is-warning' : 'is-success');
    var icon = isBanned ? 'bi-slash-circle' : (activeRows.length ? 'bi-shield-exclamation' : 'bi-check-circle');
    var label = isBanned ? 'Banlı' : (activeRows.length ? 'Kısıtlı' : 'Temiz');
    chip.classList.remove('is-danger', 'is-warning', 'is-success');
    chip.classList.add(className);
    status.innerHTML = '<i class="bi ' + icon + '"></i> ' + cmEscHtml(label);
    menu.setAttribute('data-is-banned', isBanned ? '1' : '0');
}

function renderCommentUserInsight(data, menu) {
    data = data || {};
    var userId = data.id || menu.getAttribute('data-user-id') || '';
    var userName = data.username || data.name || menu.getAttribute('data-user-name') || ('#' + userId);
    var activeRows = Array.isArray(data.restrictions) ? data.restrictions : [];
    var historyRows = Array.isArray(data.moderation_history) ? data.moderation_history : [];
    var isBanned = Number(data.is_banned) === 1;
    var canModerate = menu.getAttribute('data-can-moderate') === '1' && data.can_moderate !== false;
    var currentClass = isBanned ? 'is-danger' : (activeRows.length ? 'is-warning' : 'is-success');
    var currentIcon = isBanned ? 'bi-slash-circle' : (activeRows.length ? 'bi-shield-exclamation' : 'bi-check-circle');
    var currentTitle = isBanned ? 'Aktif ban var' : (activeRows.length ? activeRows.length + ' aktif kısıtlama var' : 'Aktif ban/kısıtlama yok');
    var currentMeta = isBanned
        ? (data.banned_at || 'Tarih yok')
        : (activeRows.length ? 'Kısıtlı işlem sayısı: ' + activeRows.length : 'Kullanıcı şu anda temiz görünüyor.');
    var currentReason = isBanned ? cmModerationReason(data.ban_reason) : '';
    var historyHtml = window.adminModerationHistory && typeof window.adminModerationHistory.rows === 'function'
        ? window.adminModerationHistory.rows(historyRows, 'Moderasyon geçmişi yok.')
        : cmModerationEmpty('Moderasyon geçmişi yok.');
    var actionsHtml = '';

    if (canModerate) {
        actionsHtml =
            '<div class="comments-manager-user-insight-actions">' +
                (isBanned
                    ? '<button type="button" class="user-row-action comments-manager-menu-item is-success" data-comment-user-unban="' + cmEscHtml(userId) + '" data-user-name="' + cmEscHtml(userName) + '"><i class="bi bi-check-circle"></i> Ban Kaldır</button>'
                    : '<button type="button" class="user-row-action comments-manager-menu-item is-danger" data-comment-user-ban="' + cmEscHtml(userId) + '" data-user-name="' + cmEscHtml(userName) + '"><i class="bi bi-slash-circle"></i> Banla</button>') +
                '<button type="button" class="user-row-action comments-manager-menu-item is-warning" data-comment-user-restrict="' + cmEscHtml(userId) + '" data-user-name="' + cmEscHtml(userName) + '"><i class="bi bi-shield-exclamation"></i> Kısıtla</button>' +
            '</div>';
    }

    commentInsightSetChipState(menu, data);
    return '' +
        '<div class="comments-manager-user-insight-head">' +
            '<strong>' + cmEscHtml(userName) + '</strong>' +
            '<span>#' + cmEscHtml(userId) + '</span>' +
        '</div>' +
        '<div class="comments-manager-user-insight-section">' +
            '<div class="ui-admin-moderation-current ' + currentClass + '"><strong><i class="bi ' + currentIcon + '"></i> ' + cmEscHtml(currentTitle) + '</strong><span>' + cmEscHtml(currentMeta) + '</span>' + currentReason + '</div>' +
        '</div>' +
        '<div class="comments-manager-user-insight-section">' +
            '<div class="comments-manager-user-insight-title">Aktif Kısıtlamalar</div>' +
            '<div class="comments-manager-user-insight-list">' + commentInsightRestrictionRows(activeRows) + '</div>' +
        '</div>' +
        '<div class="comments-manager-user-insight-section">' +
            '<div class="comments-manager-user-insight-title">Son 5 Moderasyon Geçmişi</div>' +
            '<div class="comments-manager-user-insight-list">' + historyHtml + '</div>' +
        '</div>' +
        actionsHtml;
}

function loadCommentUserInsight(menu) {
    if (!menu) return;
    var userId = menu.getAttribute('data-user-id');
    var content = menu.querySelector('[data-comment-user-insight-content]');
    if (!userId || !content) return;

    if (commentUserDetailCache[userId]) {
        content.innerHTML = renderCommentUserInsight(commentUserDetailCache[userId], menu);
        ensureCommentUserInsightController()?.position(menu);
        return;
    }

    content.innerHTML = cmModerationLoading('Kullanıcı bilgisi yükleniyor...');
    fetchCommentUserDetails(userId).then(function (data) {
        commentUserDetailCache[userId] = data;
        content.innerHTML = renderCommentUserInsight(data, menu);
        ensureCommentUserInsightController()?.position(menu);
    }).catch(function () {
        content.innerHTML = commentInsightEmpty('Kullanıcı bilgisi yüklenemedi.');
        ensureCommentUserInsightController()?.position(menu);
    });
}

function initCommentUserInsights() {
    ensureCommentUserInsightController();
    document.querySelectorAll('[data-comment-user-insight-menu]').forEach(function (menu) {
        if (menu.getAttribute('data-comment-user-insight-load-ready') === '1') {
            return;
        }
        var toggle = menu.querySelector('[data-comment-user-insight-toggle]');
        if (!toggle) {
            return;
        }
        menu.setAttribute('data-comment-user-insight-load-ready', '1');
        toggle.addEventListener('click', function () {
            window.setTimeout(function () {
                if (menu.hasAttribute('open')) {
                    loadCommentUserInsight(menu);
                }
            }, 0);
        });
    });
}

function initCommentBulkActions() {
    var form = document.querySelector('[data-comments-bulk-form]');
    if (!form || form.getAttribute('data-comments-bulk-ready') === '1') {
        return;
    }

    form.setAttribute('data-comments-bulk-ready', '1');
    var selectAll = document.querySelector('[data-comment-bulk-select-all]');
    var actionField = form.querySelector('[data-comments-bulk-action]');
    var countEl = form.querySelector('[data-comments-bulk-count]');
    var submitButtons = Array.from(form.querySelectorAll('[data-comments-bulk-submit]'));
    var actionLabels = {
        bulk_approve: { title: 'Toplu Onay', ok: 'Onayla', verb: 'onaylanacak', tone: 'success' },
        bulk_reject: { title: 'Toplu Ret', ok: 'Reddet', verb: 'reddedilecek', tone: 'warning' },
        bulk_delete: { title: 'Toplu Silme', ok: 'Sil', verb: 'silinecek', tone: 'danger' },
        bulk_restore: { title: 'Toplu Geri Yükleme', ok: 'Geri Yükle', verb: 'geri yüklenecek', tone: 'info' }
    };

    function checkboxes() {
        return Array.from(document.querySelectorAll('[data-comment-bulk-checkbox]'));
    }

    function selectedCheckboxes() {
        return checkboxes().filter(function (checkbox) {
            return checkbox.checked && !checkbox.disabled;
        });
    }

    function updateBulkState() {
        var boxes = checkboxes();
        var selected = selectedCheckboxes();
        var selectedCount = selected.length;
        if (countEl) {
            countEl.textContent = selectedCount + ' yorum seçildi';
        }
        submitButtons.forEach(function (button) {
            button.disabled = selectedCount === 0;
        });
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && selectedCount === boxes.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < boxes.length;
        }
        boxes.forEach(function (checkbox) {
            var card = checkbox.closest('.comments-manager-card');
            if (card) {
                card.classList.toggle('is-selected', checkbox.checked);
            }
        });
    }

    checkboxes().forEach(function (checkbox) {
        checkbox.addEventListener('change', updateBulkState);
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var shouldCheck = selectAll.checked;
            checkboxes().forEach(function (checkbox) {
                if (!checkbox.disabled) {
                    checkbox.checked = shouldCheck;
                }
            });
            updateBulkState();
        });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var submitter = event.submitter || document.activeElement;
        var bulkAction = submitter ? submitter.getAttribute('data-comments-bulk-action-name') : '';
        var selected = selectedCheckboxes();
        var meta = actionLabels[bulkAction];
        if (!meta || selected.length === 0) {
            if (typeof adminAlert === 'function') {
                adminAlert('Toplu işlem için en az bir yorum seçmelisiniz.', { title: 'Uyarı', tone: 'warning' });
            }
            return;
        }
        if (actionField) {
            actionField.value = bulkAction;
        }
        cmConfirm(selected.length + ' yorum ' + meta.verb + '. İşlemi onaylıyor musunuz?', {
            title: meta.title,
            ok: meta.ok,
            cancel: 'İptal',
            tone: meta.tone
        }).then(function (confirmed) {
            if (confirmed) {
                HTMLFormElement.prototype.submit.call(form);
            }
        });
    });

    updateBulkState();
}

function initCommentReadMore() {
    document.querySelectorAll('[data-ui-comment-manager-body]').forEach(function (body) {
        if (body.getAttribute('data-ui-read-more-ready') === '1') {
            return;
        }

        body.setAttribute('data-ui-read-more-ready', '1');
        if (body.scrollHeight <= COMMENT_READ_MORE_LIMIT) {
            return;
        }

        body.classList.add('is-truncated');

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'ui-comment-manager-read-more-btn';
        toggle.setAttribute('aria-controls', body.id);
        toggle.setAttribute('aria-expanded', 'false');
        toggle.textContent = 'Devamını Oku...';

        toggle.addEventListener('click', function () {
            var isTruncated = body.classList.toggle('is-truncated');
            toggle.setAttribute('aria-expanded', isTruncated ? 'false' : 'true');
            toggle.textContent = isTruncated ? 'Devamını Oku...' : 'Daha Az Göster';

            if (isTruncated) {
                body.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        body.insertAdjacentElement('afterend', toggle);
    });
}

function openEditModal(commentId, commentBody) {
    if (!document.getElementById('editModal')) return;
    document.getElementById('editCommentId').value = commentId;
    document.getElementById('editCommentBody').value = commentBody;
    document.getElementById('editCommentReason').value = '';
    var modal = document.getElementById('editModal');
    document.body.style.overflow = 'hidden';

    if (window.adminModal && typeof window.adminModal.open === 'function') {
        editModalController = window.adminModal.open(modal, {
            openClass: 'active',
            bodyClass: 'ui-admin-dialog-open',
            initialFocus: '#editCommentBody',
            returnFocus: document.activeElement,
            onClose: function () {
                editModalController = null;
                document.body.style.overflow = '';
            }
        });
        return;
    }

    modal.hidden = false;
    modal.classList.add('active');
    document.getElementById('editCommentBody').focus();
}

function closeEditModal() {
    var modal = document.getElementById('editModal');
    if (!modal) return;
    if (window.adminModal && typeof window.adminModal.close === 'function') {
        window.adminModal.close(modal, function () {
            editModalController = null;
            document.body.style.overflow = '';
        });
        return;
    }
    modal.classList.remove('active');
    modal.hidden = true;
    document.body.style.overflow = '';
}

function openCommentBanModal(userId, userName) {
    var modal = document.getElementById('commentBanModal');
    var form = document.getElementById('commentBanForm');
    var idField = document.getElementById('commentBanUserId');
    var nameField = document.getElementById('commentBanUserName');
    var reasonField = document.getElementById('commentBanReason');
    var current = document.querySelector('[data-comment-ban-current]');
    var history = document.querySelector('[data-comment-ban-history]');
    if (!modal || !form || !idField) return;
    form.reset();
    idField.value = userId;
    if (nameField) nameField.value = userName || '';
    if (reasonField) reasonField.value = '';
    if (current) current.innerHTML = cmModerationLoading('Ban bilgisi yükleniyor...');
    if (history) history.innerHTML = cmModerationLoading('Geçmiş yükleniyor...');
    fetchCommentUserDetails(userId).then(renderCommentBanContext).catch(function () {
        if (current) current.innerHTML = cmModerationEmpty('Ban bilgisi yüklenemedi.');
        if (history) history.innerHTML = cmModerationEmpty('Ban geçmişi yüklenemedi.');
    });
    openCommentManagedModal(modal, { initialFocus: '#commentBanReason' });
}

function closeCommentBanModal() {
    var modal = document.getElementById('commentBanModal');
    closeCommentManagedModal(modal, function () {
        document.getElementById('commentBanForm')?.reset();
    });
}

function openCommentUnbanModal(userId, userName) {
    var modal = document.getElementById('commentUnbanModal');
    var form = document.getElementById('commentUnbanForm');
    var idField = document.getElementById('commentUnbanUserId');
    var nameField = document.getElementById('commentUnbanUserName');
    var reasonField = document.getElementById('commentUnbanReason');
    var elements = commentBanContextElements('comment-unban');
    var current = elements.current;
    var history = elements.history;
    if (!modal || !form || !idField) return;
    form.reset();
    idField.value = userId;
    if (nameField) nameField.value = userName || '';
    if (reasonField) reasonField.value = '';
    if (current) current.innerHTML = cmModerationLoading('Ban bilgisi yükleniyor...');
    if (history) history.innerHTML = cmModerationLoading('Geçmiş yükleniyor...');
    fetchCommentUserDetails(userId).then(function (data) {
        renderCommentBanContext(data, 'comment-unban');
        if (nameField && !nameField.value) {
            nameField.value = data.name || data.username || ('#' + (data.id || userId));
        }
    }).catch(function () {
        if (current) current.innerHTML = cmModerationEmpty('Ban bilgisi yüklenemedi.');
        if (history) history.innerHTML = cmModerationEmpty('Ban geçmişi yüklenemedi.');
    });
    openCommentManagedModal(modal, { initialFocus: '#commentUnbanReason' });
}

function closeCommentUnbanModal() {
    var modal = document.getElementById('commentUnbanModal');
    closeCommentManagedModal(modal, function () {
        document.getElementById('commentUnbanForm')?.reset();
    });
}

function openCommentRestrictionModal(userId, userName) {
    var modal = document.getElementById('commentRestrictionModal');
    var form = document.getElementById('commentRestrictionForm');
    var idField = document.getElementById('commentRestrictUserId');
    var nameField = document.getElementById('commentRestrictUserName');
    var current = document.querySelector('[data-comment-restriction-current]');
    var history = document.querySelector('[data-comment-restriction-history]');
    if (!modal || !form || !idField) return;
    form.reset();
    idField.value = userId;
    if (nameField) nameField.value = userName || '';
    if (current) current.innerHTML = cmModerationLoading('Kısıtlamalar yükleniyor...');
    if (history) history.innerHTML = cmModerationLoading('Geçmiş yükleniyor...');
    fetchCommentUserDetails(userId).then(renderCommentRestrictionContext).catch(function () {
        if (current) current.innerHTML = cmModerationEmpty('Aktif kısıtlamalar yüklenemedi.');
        if (history) history.innerHTML = cmModerationEmpty('Kısıtlama geçmişi yüklenemedi.');
    });
    openCommentManagedModal(modal, { initialFocus: '#commentRestrictTypes' });
}

function closeCommentRestrictionModal() {
    var modal = document.getElementById('commentRestrictionModal');
    closeCommentManagedModal(modal, function () {
        document.getElementById('commentRestrictionForm')?.reset();
    });
}

function initCommentsManagerPage() {
    initCommentReadMore();
    initCommentActionsMenus();
    initCommentUserInsights();
    initCommentBulkActions();

    if (window.__commentsManagerPageActionsBound) return;
    window.__commentsManagerPageActionsBound = true;

    document.addEventListener('click', function(event) {
        const editTrigger = event.target.closest('[data-comment-edit]');
        if (editTrigger) {
            closeCommentActionsMenu();
            closeCommentUserInsightMenu();
            openEditModal(editTrigger.getAttribute('data-comment-edit'), editTrigger.getAttribute('data-comment-body') || '');
            return;
        }

        const banTrigger = event.target.closest('[data-comment-user-ban]');
        if (banTrigger) {
            event.preventDefault();
            closeCommentActionsMenu();
            closeCommentUserInsightMenu();
            openCommentBanModal(banTrigger.getAttribute('data-comment-user-ban'), banTrigger.getAttribute('data-user-name') || '');
            return;
        }

        const restrictTrigger = event.target.closest('[data-comment-user-restrict]');
        if (restrictTrigger) {
            event.preventDefault();
            closeCommentActionsMenu();
            closeCommentUserInsightMenu();
            openCommentRestrictionModal(restrictTrigger.getAttribute('data-comment-user-restrict'), restrictTrigger.getAttribute('data-user-name') || '');
            return;
        }

        const unbanTrigger = event.target.closest('[data-comment-user-unban]');
        if (unbanTrigger) {
            event.preventDefault();
            closeCommentActionsMenu();
            closeCommentUserInsightMenu();
            openCommentUnbanModal(unbanTrigger.getAttribute('data-comment-user-unban'), unbanTrigger.getAttribute('data-user-name') || '');
            return;
        }

        if (event.target.closest('[data-comment-ban-close]')) {
            closeCommentBanModal();
            return;
        }

        if (event.target.closest('[data-comment-unban-close]')) {
            closeCommentUnbanModal();
            return;
        }

        if (event.target.closest('[data-comment-restriction-close]')) {
            closeCommentRestrictionModal();
            return;
        }

    });

    document.addEventListener('keydown', function(e) {
        if (!window.TMUI && e.key === 'Escape') {
            closeEditModal();
            closeCommentBanModal();
            closeCommentUnbanModal();
            closeCommentRestrictionModal();
            closeCommentUserInsightMenu();
        }
    });

    document.getElementById('commentBanModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeCommentBanModal();
    });

    document.getElementById('commentUnbanModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeCommentUnbanModal();
    });

    document.getElementById('commentRestrictionModal')?.addEventListener('click', function (event) {
        if (event.target === this) closeCommentRestrictionModal();
    });

    document.getElementById('commentBanForm')?.addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.currentTarget;
        var reason = String(form.querySelector('[name="ban_reason"]')?.value || '').trim();
        if (!reason) {
            if (typeof adminAlert === 'function') {
                adminAlert('Ban sebebi gereklidir.', { title: 'Uyarı', tone: 'warning' });
            }
            return;
        }
        cmConfirm('Bu kullanıcı banlanacak. İşlemi onaylıyor musunuz?', {
            title: 'Kullanıcıyı banla',
            ok: 'Banla',
            cancel: 'İptal',
            tone: 'danger'
        }).then(function (confirmed) {
            if (confirmed) HTMLFormElement.prototype.submit.call(form);
        });
    });

    document.getElementById('commentRestrictionForm')?.addEventListener('submit', function (event) {
        event.preventDefault();
        var form = event.currentTarget;
        var selected = Array.from(form.querySelector('#commentRestrictTypes')?.selectedOptions || []);
        var reason = String(form.querySelector('[name="restrict_reason"]')?.value || '').trim();
        if (selected.length === 0 || !reason) {
            if (typeof adminAlert === 'function') {
                adminAlert(selected.length === 0 ? 'En az bir kısıtlama türü seçmelisiniz.' : 'Kısıtlama sebebi gereklidir.', { title: 'Uyarı', tone: 'warning' });
            }
            return;
        }
        cmConfirm(selected.length + ' kısıtlama eklenecek. İşlemi onaylıyor musunuz?', {
            title: 'Kısıtlama Ekle?',
            ok: 'Evet, Ekle',
            cancel: 'İptal',
            tone: 'warning'
        }).then(function (confirmed) {
            if (confirmed) HTMLFormElement.prototype.submit.call(form);
        });
    });
}

window.openEditModal = openEditModal;
window.closeEditModal = closeEditModal;

window.adminPage.register('comments-manager', initCommentsManagerPage, {
    id: 'comments-manager-page',
    selector: '[data-ui-comment-manager-body], [data-comment-edit], [data-comment-actions-toggle], [data-comments-bulk-form], [data-comment-user-insight-toggle], #editModal, #commentBanModal, #commentUnbanModal, #commentRestrictionModal'
});
