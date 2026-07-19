function openGroupManagedModal(modal, options) {
    if (!modal) return null;
    if (window.adminModal && typeof window.adminModal.open === 'function') {
        return window.adminModal.open(modal, options || {});
    }
    if (window.openAdminManagedModal && window.openAdminManagedModal !== openGroupManagedModal) {
        return window.openAdminManagedModal(modal, options || {});
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add((options && options.openClass) || 'is-open', 'ui-admin-modal-open');
    return null;
}

function closeGroupManagedModal(modal, resetCallback) {
    if (!modal) return;
    if (window.adminModal && typeof window.adminModal.close === 'function') {
        window.adminModal.close(modal, resetCallback);
        return;
    }
    if (window.closeAdminManagedModal && window.closeAdminManagedModal !== closeGroupManagedModal) {
        window.closeAdminManagedModal(modal, resetCallback);
        return;
    }
    modal.classList.remove('is-open', 'ui-admin-modal-open');
    modal.hidden = true;
    modal.setAttribute('aria-hidden', 'true');
    if (typeof resetCallback === 'function') resetCallback();
}

function escHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

function setGroupElementVisibility(element, visible) {
    if (!element) return;
    if (window.adminVisibility && typeof window.adminVisibility.set === 'function') {
        window.adminVisibility.set(element, visible, { aria: false });
        return;
    }

    element.hidden = !visible;
}

function setGroupPaneVisibility(pane, visible) {
    if (!pane) return;
    if (window.adminVisibility && typeof window.adminVisibility.set === 'function') {
        window.adminVisibility.set(pane, visible, {
            visibleClass: 'active',
            aria: false,
        });
        return;
    }

    pane.hidden = !visible;
    pane.classList.toggle('active', visible);
}

function switchTab(tabName) {
    const tabBtns = document.querySelectorAll('[data-group-tab-btn]');
    const tabPanes = document.querySelectorAll('[data-group-tab-pane]');

    tabBtns.forEach(b => {
        if (b.getAttribute('data-group-tab-btn') === tabName) {
            b.classList.add('active');
            b.classList.add('ui-admin-btn-primary');
            b.classList.remove('ui-admin-btn-outline');
        } else {
            b.classList.remove('active');
            b.classList.remove('ui-admin-btn-primary');
            b.classList.add('ui-admin-btn-outline');
        }
    });

    tabPanes.forEach(pane => {
        const isActive = pane.getAttribute('data-group-tab-pane') === tabName;
        setGroupPaneVisibility(pane, isActive);
    });
}

function openGroupModal(action, dataset) {
    const modal = document.getElementById('groupEditModal');
    if (!modal) return;

    const titleEl = document.getElementById('groupModalTitle');
    const idField = document.getElementById('groupEditId');
    const nameField = document.getElementById('groupName');
    const slugField = document.getElementById('groupSlug');
    const priorityField = document.getElementById('groupPriority');
    const colorField = document.getElementById('groupColor');
    const descField = document.getElementById('groupDescription');
    const isActiveField = document.getElementById('groupIsActive');
    const isDefaultField = document.getElementById('groupIsDefault');
    const isStaffField = document.getElementById('groupIsStaff');

    // Reset checkboxes
    const checkboxes = modal.querySelectorAll('input[type="checkbox"][name="permissions[]"]');
    checkboxes.forEach(cb => cb.checked = false);

    // Get permissions mapping
    let groupPerms = [];
    const permissionsDataEl = document.getElementById('groupPermissionsData');
    const permissionsMap = permissionsDataEl ? JSON.parse(permissionsDataEl.textContent || '{}') : {};

    if (action === 'add') {
        titleEl.innerHTML = '<i class="bi bi-plus-circle"></i> Yeni Grup Ekle';
        idField.value = '0';
        nameField.value = '';
        slugField.value = '';
        slugField.readOnly = false;
        priorityField.value = '1';
        colorField.value = '#64748b';
        descField.value = '';
        isActiveField.checked = true;
        isDefaultField.checked = false;
        isStaffField.checked = false;
        switchTab('general');
    } else {
        const id = dataset.groupId || '0';
        const name = dataset.groupName || '';
        const slug = dataset.groupSlug || '';

        idField.value = action === 'copy' ? '0' : id;
        nameField.value = action === 'copy' ? name + ' Kopya' : name;
        slugField.value = action === 'copy' ? '' : slug;
        slugField.readOnly = action === 'edit' && slug === 'admin';
        priorityField.value = dataset.groupPriority || '1';
        colorField.value = dataset.groupColor || '#64748b';
        descField.value = dataset.groupDescription || '';
        isActiveField.checked = parseInt(dataset.groupIsActive) === 1;
        isDefaultField.checked = parseInt(dataset.groupIsDefault) === 1;
        isStaffField.checked = parseInt(dataset.groupIsStaff) === 1;

        if (action === 'copy') {
            titleEl.innerHTML = '<i class="bi bi-files"></i> Grup Kopyala';
        } else {
            titleEl.innerHTML = '<i class="bi bi-sliders"></i> ' + escHtml(name);
        }

        // Check group permissions
        groupPerms = permissionsMap[id] || [];
        groupPerms.forEach(permKey => {
            const cb = modal.querySelector(`input[data-permission-checkbox="${permKey}"]`);
            if (cb) cb.checked = true;
        });

        const initialTab = dataset.groupTab || 'general';
        switchTab(initialTab);
    }

    openGroupManagedModal(modal, {
        initialFocus: '#groupName'
    });
}

function closeGroupModal() {
    const modal = document.getElementById('groupEditModal');
    closeGroupManagedModal(modal);
}

// Delegated action handlers
function initUsersGroupsActions() {
document.addEventListener('click', function(e) {
    const trigger = e.target.closest('[data-group-action]');
    if (trigger) {
        e.preventDefault();
        const action = trigger.getAttribute('data-group-action');
        openGroupModal(action, trigger.dataset);
        return;
    }

    // Modal close buttons
    if (e.target.closest('[data-ui-modal-close]') || e.target.id === 'groupModalCancelBtn' || e.target.id === 'groupModalClose') {
        const modal = document.getElementById('groupEditModal');
        if (modal && (modal.classList.contains('is-open') || modal.classList.contains('ui-admin-modal-open'))) {
            e.preventDefault();
            closeGroupModal();
        }
        return;
    }

    // Modal backdrop click
    if (e.target.id === 'groupEditModal') {
        closeGroupModal();
        return;
    }

    // Tab buttons switching inside modal
    const tabBtn = e.target.closest('[data-group-tab-btn]');
    if (tabBtn) {
        e.preventDefault();
        const tabName = tabBtn.getAttribute('data-group-tab-btn');
        switchTab(tabName);
    }
});

// ESC Key mapping
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('groupEditModal');
        if (modal && (modal.classList.contains('is-open') || modal.classList.contains('ui-admin-modal-open'))) {
            closeGroupModal();
        }
    }
});
}

// Permission catalog tools inside modal
function bindGroupPermissionTools() {
    const shell = document.querySelector('[data-group-permission-tools]');
    if (!shell) return;

    const items = Array.from(shell.querySelectorAll('[data-permission-item]'));
    const categories = Array.from(shell.querySelectorAll('[data-permission-category]'));
    const search = shell.querySelector('[data-permission-search]');
    const filter = shell.querySelector('[data-permission-filter]');

    function applyFilters() {
        const query = String(search?.value || '').trim().toLowerCase();
        const category = String(filter?.value || '').trim();

        categories.forEach(group => {
            const categoryMatches = !category || group.getAttribute('data-permission-category') === category;
            let visibleCount = 0;
            group.querySelectorAll('[data-permission-item]').forEach(item => {
                const text = String(item.getAttribute('data-permission-text') || '').toLowerCase();
                const visible = categoryMatches && (!query || text.includes(query));
                setGroupElementVisibility(item, visible);
                if (visible) visibleCount += 1;
            });
            setGroupElementVisibility(group, visibleCount > 0);
        });
    }

    search?.addEventListener('input', applyFilters);
    filter?.addEventListener('change', applyFilters);

    shell.querySelector('[data-permission-select-all]')?.addEventListener('click', function () {
        items.forEach(item => {
            if (!item.hidden && !item.closest('[data-permission-category]')?.hidden) {
                const input = item.querySelector('input[type="checkbox"]');
                if (input) input.checked = true;
            }
        });
    });

    shell.querySelector('[data-permission-clear-all]')?.addEventListener('click', function () {
        items.forEach(item => {
            if (!item.hidden && !item.closest('[data-permission-category]')?.hidden) {
                const input = item.querySelector('input[type="checkbox"]');
                if (input) input.checked = false;
            }
        });
    });
}

// Auto-open logic from URL deep links
function initUsersGroupsDeepLinks() {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    if (tab === 'groups') {
        const groupId = parseInt(params.get('group_id') || '0');
        const copyGroupId = parseInt(params.get('copy_group_id') || '0');
        const groupView = params.get('group_view');

        if (copyGroupId > 0) {
            const copyBtn = document.querySelector(`button[data-group-action="copy"][data-group-id="${copyGroupId}"]`);
            if (copyBtn) {
                copyBtn.click();
            }
        } else if (groupId > 0) {
            const editBtn = document.querySelector(`button[data-group-action="edit"][data-group-id="${groupId}"]`);
            if (editBtn) {
                editBtn.click();
            }
        } else if (groupView === 'form' && params.has('group_id') && groupId === 0) {
            const addBtn = document.getElementById('addGroupBtnTop') || document.getElementById('addGroupBtnHead');
            if (addBtn) {
                addBtn.click();
            }
        }
    }
}

function initUsersGroupsTab() {
    initUsersGroupsActions();
    bindGroupPermissionTools();
    initUsersGroupsDeepLinks();
}

window.openGroupModal = openGroupModal;
window.closeGroupModal = closeGroupModal;

window.adminPage.register('users:groups', initUsersGroupsTab, {
    id: 'users-groups-tab',
    selector: '[data-group-action], [data-group-permission-tools], #groupEditModal'
});
