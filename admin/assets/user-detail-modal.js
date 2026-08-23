(function () {
    'use strict';

    var activeRequest = 0;
    var activeTrigger = null;

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    function openManagedModal(modal) {
        window.adminModal.open(modal, { initialFocus: '.ui-admin-detail-close' });
    }

    function closeManagedModal(modal, callback) {
        window.adminModal.close(modal, callback);
    }

    function renderList(rows, renderer) {
        if (!Array.isArray(rows) || rows.length === 0) {
            return '<p class="ui-admin-muted ui-admin-detail-empty-text ui-empty">Kayıt yok</p>';
        }
        return '<ul class="ui-admin-detail-list">' + rows.map(renderer).join('') + '</ul>';
    }

    function editAttributes(user) {
        var attributes = [
            ['id', 'id'], ['name', 'name'], ['username', 'username'], ['email', 'email'],
            ['group', 'group_id'], ['status', 'status'], ['location', 'location'], ['website', 'website'],
            ['github', 'social_github'], ['twitter', 'social_twitter'], ['discord', 'social_discord'], ['bio', 'bio']
        ];
        return attributes.map(function (pair) {
            var value = pair[0] === 'name' ? (user.name || user.username || '') : (user[pair[1]] || '');
            return 'data-user-' + pair[0] + '="' + escapeHtml(value) + '"';
        }).join(' ');
    }

    function renderActions(user, userName) {
        var actions = '';
        if (user.can_view_user_activity) {
            actions += '<a href="users.php?tab=activity&amp;user_id=' + encodeURIComponent(user.id || '') + '" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline"><i class="bi bi-activity"></i> Aktivite</a>';
        }
        if (user.can_manage_users) {
            actions += '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-admin-note-open data-user-id="' + escapeHtml(user.id) + '" data-user-name="' + escapeHtml(userName) + '"><i class="bi bi-journal-plus"></i> Not Ekle</button>'
                + '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary" data-user-edit-open ' + editAttributes(user) + '><i class="bi bi-pencil"></i> Düzenle</button>';
        }
        if (user.can_moderate) {
            actions += user.is_banned
                ? '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-success" data-user-unban="' + escapeHtml(user.id) + '" data-user-name="' + escapeHtml(userName) + '"><i class="bi bi-check-circle"></i> Ban Kaldır</button>'
                : '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger" data-user-ban="' + escapeHtml(user.id) + '" data-user-name="' + escapeHtml(userName) + '"><i class="bi bi-slash-circle"></i> Banla</button>';
            actions += '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-user-restrict="' + escapeHtml(user.id) + '" data-user-name="' + escapeHtml(userName) + '"><i class="bi bi-shield-exclamation"></i> Kısıtla</button>';
        }
        return actions ? '<div class="ui-admin-detail-actions">' + actions + '</div>' : '';
    }

    function renderDetail(data) {
        var stats = data.stats || {};
        var userName = data.name || data.username || ('#' + data.id);
        var statusLabel = ({ active: 'Aktif', inactive: 'Pasif' })[String(data.status || '').toLowerCase()] || data.status || 'Aktif';
        var badge = data.is_banned
            ? '<span class="ui-admin-badge ui-admin-badge-danger">Yasaklı</span>' + (data.ban_reason ? ' <span class="ui-admin-muted">(' + escapeHtml(data.ban_reason) + ')</span>' : '')
            : '<span class="ui-admin-badge ui-admin-badge-success">' + escapeHtml(statusLabel) + '</span>';
        var restrictionCount = Array.isArray(data.restrictions) ? data.restrictions.length : 0;
        var noteCount = Array.isArray(data.admin_notes) ? data.admin_notes.length : 0;

        return '<div class="ui-admin-detail-head ui-panel__head"><div class="ui-admin-detail-identity"><strong>' + escapeHtml(userName) + '</strong><span>' + escapeHtml(data.email) + '</span><small>Son aktivite: ' + escapeHtml(data.last_activity_at || 'Kayıt yok') + '</small></div><div class="ui-admin-detail-badges">' + badge + ' <span class="ui-admin-badge">' + escapeHtml(data.group_name || 'Üye') + '</span></div></div>'
            + '<div class="ui-admin-detail-stats"><span><b>' + (stats.total_topics || 0) + '</b> konu</span><span><b>' + (stats.total_comments || 0) + '</b> yorum</span><span><b>' + (stats.total_downloads || 0) + '</b> indirme</span><span><b>' + (data.reports_about || 0) + '</b> şikayet</span></div>'
            + '<div class="ui-admin-detail-decision"><div><span>Son giriş</span><strong>' + escapeHtml(data.last_login_at || 'Kayıt yok') + '</strong></div><div><span>Son IP</span><strong>' + escapeHtml(data.last_login_ip || 'Yok') + '</strong></div><div><span>Aktif kısıtlama</span><strong>' + restrictionCount + '</strong></div><div><span>Admin notu</span><strong>' + noteCount + '</strong></div></div>'
            + renderActions(data, userName)
            + '<div class="ui-admin-detail-grid ui-grid">'
            + '<div class="ui-admin-detail-full"><h4>Son Aktivite</h4>' + renderList(data.recent_activity, function (row) { var detail = [row.group, row.device, row.ip_address].filter(Boolean).map(escapeHtml).join(' &middot; '); return '<li><b>' + escapeHtml(row.event || row.title || 'Aktivite') + '</b> <span class="ui-admin-muted">' + escapeHtml(row.created_at) + '</span>' + (detail ? '<br><span class="ui-admin-muted">' + detail + '</span>' : '') + (row.title && row.title !== row.event ? '<br><span>' + escapeHtml(row.title) + '</span>' : '') + '</li>'; }) + '</div>'
            + '<div><h4>Son Konular</h4>' + renderList(data.recent_topics, function (row) { return '<li><a href="' + escapeHtml(row.url || '#') + '" target="_blank" rel="noopener">' + escapeHtml(row.title) + '</a> <span class="ui-admin-muted">' + escapeHtml(row.created_at) + '</span></li>'; }) + '</div>'
            + '<div><h4>Son Yorumlar</h4>' + renderList(data.recent_comments, function (row) { return '<li>' + escapeHtml(row.excerpt) + ' <span class="ui-admin-muted">' + escapeHtml(row.created_at) + '</span></li>'; }) + '</div>'
            + '<div><h4>IP Adresleri</h4>' + renderList(data.login_ips, function (ip) { return '<li><code>' + escapeHtml(ip) + '</code></li>'; }) + '</div>'
            + '<div><h4>Aktif Kısıtlamalar</h4>' + renderList(data.restrictions, function (row) { return '<li>' + escapeHtml(row.type || row.restriction_type || 'Kısıtlama') + ' <span class="ui-admin-muted">' + escapeHtml(row.reason) + '</span></li>'; }) + '</div>'
            + '<div><h4>Admin Notları</h4>' + renderList(data.admin_notes, function (row) { return '<li><b>' + escapeHtml(row.admin || 'Admin') + '</b> <span class="ui-admin-muted">' + escapeHtml(row.created_at) + '</span><br><span>' + escapeHtml(row.note) + '</span>' + (row.tags ? '<br><span class="ui-admin-muted">' + escapeHtml(row.tags) + '</span>' : '') + '</li>'; }) + '</div>'
            + '<div><h4>Kısıtlama Kayıtları</h4>' + renderList(data.restriction_history, function (row) { var meta = [row.action || '', row.created_at || '']; if (row.expires_at) meta.push('Bitiş: ' + row.expires_at); return '<li><b>' + escapeHtml(row.type || 'Kısıtlama') + '</b> ' + (row.active ? '<span class="ui-admin-badge ui-admin-badge-warning">aktif</span>' : '<span class="ui-admin-badge ui-admin-badge-muted">geçmiş</span>') + '<br><span class="ui-admin-muted">' + escapeHtml(meta.filter(Boolean).join(' - ')) + '</span>' + (row.reason ? '<br><span>' + escapeHtml(row.reason) + '</span>' : '') + '</li>'; }) + '</div>'
            + '<div class="ui-admin-detail-full"><h4>Yönetici İşlem Geçmişi</h4>' + renderList(data.audit_history, function (row) { return '<li><b>' + escapeHtml(row.action) + '</b> - ' + escapeHtml(row.actor) + ' <span class="ui-admin-muted">' + escapeHtml(row.created_at) + '</span>' + (row.reverted ? ' <span class="ui-admin-badge ui-admin-badge-muted">geri alındı</span>' : '') + (row.reason ? '<br><span class="ui-admin-muted">' + escapeHtml(row.reason) + '</span>' : '') + '</li>'; }) + '</div></div>';
    }

    function open(userId, trigger) {
        var overlay = document.getElementById('userDetailModal');
        var body = document.getElementById('userDetailBody');
        if (!overlay || !body || !userId) return;
        var requestId = ++activeRequest;
        activeTrigger = trigger || activeTrigger;
        overlay.dataset.userDetailId = String(userId);
        openManagedModal(overlay);
        body.innerHTML = '<div class="ui-admin-detail-loading"><i class="bi bi-arrow-repeat"></i> Kullanıcı bilgileri yükleniyor...</div>';

        window.adminFetchJson('api/user-details.php?id=' + encodeURIComponent(userId), { notifyError: false }).then(function (response) {
            if (requestId !== activeRequest || overlay.dataset.userDetailId !== String(userId)) return;
            if (!response || !response.success) throw new Error((response && response.message) || 'Detaylar yüklenemedi.');
            body.innerHTML = renderDetail(response.data || {});
        }).catch(function (error) {
            if (requestId !== activeRequest || overlay.dataset.userDetailId !== String(userId)) return;
            body.innerHTML = '<div class="ui-admin-detail-loading">' + escapeHtml(error.message || 'Bağlantı hatası.') + '</div>';
        });
    }

    function close(options) {
        var overlay = document.getElementById('userDetailModal');
        if (!overlay) return;
        var restoreFocus = !options || options.restoreFocus !== false;
        ++activeRequest;
        closeManagedModal(overlay, function () {
            delete overlay.dataset.userDetailId;
            if (restoreFocus) activeTrigger?.focus();
            activeTrigger = null;
        });
    }

    function init() {
        document.querySelectorAll('[data-user-detail-open]').forEach(function (trigger) {
            if (trigger.dataset.userDetailReady === '1') return;
            trigger.dataset.userDetailReady = '1';
            trigger.addEventListener('click', function () { open(trigger.dataset.userId, trigger); });
        });
    }

    window.openUserDetail = open;
    window.closeUserDetail = close;
    window.adminUserDetailModal = { init: init, open: open, close: close };
    window.adminPage.register(['users', 'comments-manager'], init, {
        id: 'user-detail-modal',
        selector: '[data-user-detail-open], #userDetailModal'
    });
}());
