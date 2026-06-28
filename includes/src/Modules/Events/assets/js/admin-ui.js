/**
 * Admin UI Interactive Elements
 * Table sorting, filtering, inline editing, modals
 */

(function() {
    'use strict';

    // Table Sorting
    function initTableSorting() {
        document.querySelectorAll('.admin-table th.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const table = this.closest('.admin-table');
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const columnIndex = Array.from(this.parentNode.children).indexOf(this);
                const isAsc = this.classList.contains('sort-asc');

                // Remove sort classes from all headers
                table.querySelectorAll('th').forEach(h => {
                    h.classList.remove('sort-asc', 'sort-desc');
                });

                // Sort rows
                rows.sort((a, b) => {
                    const aVal = a.children[columnIndex].textContent.trim();
                    const bVal = b.children[columnIndex].textContent.trim();

                    // Try numeric sort
                    const aNum = parseFloat(aVal);
                    const bNum = parseFloat(bVal);

                    if (!isNaN(aNum) && !isNaN(bNum)) {
                        return isAsc ? bNum - aNum : aNum - bNum;
                    }

                    // String sort
                    return isAsc ? bVal.localeCompare(aVal) : aVal.localeCompare(bVal);
                });

                // Update table
                rows.forEach(row => tbody.appendChild(row));
                this.classList.add(isAsc ? 'sort-desc' : 'sort-asc');
            });
        });
    }

    // Table Row Selection
    function initRowSelection() {
        const selectAllCheckbox = document.querySelector('.table-select-all');
        const rowCheckboxes = document.querySelectorAll('.table-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    checkbox.closest('tr').classList.toggle('selected', this.checked);
                });
                updateBulkActions();
            });
        }

        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                this.closest('tr').classList.toggle('selected', this.checked);
                updateBulkActions();
            });
        });
    }

    // Update Bulk Actions Display
    function updateBulkActions() {
        const selected = document.querySelectorAll('.table-checkbox:checked').length;
        const bulkActions = document.querySelector('.bulk-actions');

        if (bulkActions) {
            if (selected > 0) {
                bulkActions.style.display = 'flex';
                bulkActions.querySelector('.bulk-actions-count').textContent =
                    `${selected} öğe seçildi`;
            } else {
                bulkActions.style.display = 'none';
            }
        }
    }

    // Expandable Rows
    function initExpandableRows() {
        document.querySelectorAll('.table-row-expandable').forEach(row => {
            row.addEventListener('click', function(e) {
                if (e.target.closest('.table-action-btn')) return;

                const detailsRow = this.nextElementSibling;
                if (detailsRow && detailsRow.classList.contains('table-row-details')) {
                    this.classList.toggle('expanded');
                    detailsRow.classList.toggle('visible');
                }
            });
        });
    }

    // Inline Editing
    function initInlineEditing() {
        document.querySelectorAll('.table-cell-editable').forEach(cell => {
            cell.addEventListener('click', function(e) {
                if (this.classList.contains('editing')) return;

                const originalValue = this.textContent.trim();
                const input = document.createElement('input');
                input.type = 'text';
                input.value = originalValue;

                this.classList.add('editing');
                this.innerHTML = '';
                this.appendChild(input);
                input.focus();
                input.select();

                const saveEdit = () => {
                    this.classList.remove('editing');
                    this.textContent = input.value || originalValue;
                    // Trigger save event
                    this.dispatchEvent(new CustomEvent('edited', {
                        detail: { value: input.value }
                    }));
                };

                input.addEventListener('blur', saveEdit);
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') saveEdit();
                    if (e.key === 'Escape') {
                        this.classList.remove('editing');
                        this.textContent = originalValue;
                    }
                });
            });
        });
    }

    // Modal Management
    window.showModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('visible');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('visible');
            document.body.style.overflow = '';
        }
    };

    // Close modal on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('visible');
            document.body.style.overflow = '';
        }
    });

    // Close modal on close button
    document.querySelectorAll('.modal-close-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if (modal) {
                modal.classList.remove('visible');
                document.body.style.overflow = '';
            }
        });
    });

    // Tab Navigation
    function initTabs() {
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function() {
                const tabGroup = this.closest('[data-tab-group]');
                if (!tabGroup) return;

                const tabName = this.getAttribute('data-tab');

                // Deactivate all tabs in group
                tabGroup.querySelectorAll('.tab-button').forEach(b => {
                    b.classList.remove('active');
                });
                tabGroup.querySelectorAll('.tab-content').forEach(c => {
                    c.classList.remove('active');
                });

                // Activate selected tab
                this.classList.add('active');
                const content = tabGroup.querySelector(`[data-tab-content="${tabName}"]`);
                if (content) {
                    content.classList.add('active');
                }
            });
        });
    }

    function shouldHandleEventsAdminTab(tab) {
        const href = tab.getAttribute('href');
        if (!href) return true;
        const normalizedHref = href.trim().toLowerCase();
        if (normalizedHref === '' || normalizedHref === '#') return true;
        if (normalizedHref.indexOf('javascript:') === 0) return true;
        if (href.charAt(0) === '#') return true;
        return false;
    }

    function activateEventsAdminTab(root, target) {
        if (!root || !target) return;

        root.querySelectorAll('[data-ui-events-tab]').forEach(button => {
            const active = button.getAttribute('data-ui-events-tab') === target;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        root.querySelectorAll('[data-ui-events-tab-panel]').forEach(panel => {
            const active = panel.getAttribute('data-ui-events-tab-panel') === target;
            panel.classList.toggle('is-active', active);
            panel.hidden = !active;
        });
    }

    function activateEventsAdminTabFromQuery(root) {
        let requestedTab = '';
        try {
            requestedTab = new URLSearchParams(window.location.search).get('tab') || '';
        } catch (error) {
            requestedTab = '';
        }
        if (!requestedTab) return;

        const requestedButton = Array.from(root.querySelectorAll('[data-ui-events-tab]')).find(button => {
            return button.getAttribute('data-ui-events-tab') === requestedTab;
        });
        if (requestedButton && shouldHandleEventsAdminTab(requestedButton)) {
            activateEventsAdminTab(root, requestedTab);
        }
    }

    function initEventsAdminTabs() {
        document.querySelectorAll('.ui-events-admin-page [data-ui-events-tabs-root]').forEach(root => {
            activateEventsAdminTabFromQuery(root);
        });

        document.addEventListener('click', function(event) {
            const tabButton = event.target.closest('.ui-events-admin-page [data-ui-events-tabs-root] [data-ui-events-tab]');
            if (!tabButton || !shouldHandleEventsAdminTab(tabButton)) return;

            const root = tabButton.closest('[data-ui-events-tabs-root]');
            if (!root) return;

            event.preventDefault();
            activateEventsAdminTab(root, tabButton.getAttribute('data-ui-events-tab'));
        });
    }

    function runEventsWheelAjaxScripts(root) {
        if (!root) return;
        root.querySelectorAll('script').forEach(script => {
            const nextScript = document.createElement('script');
            Array.from(script.attributes).forEach(attribute => {
                nextScript.setAttribute(attribute.name, attribute.value);
            });
            nextScript.textContent = script.textContent;
            document.body.appendChild(nextScript);
            document.body.removeChild(nextScript);
        });
    }

    function replaceEventsWheelAjaxContent(html, url, pushState = true) {
        const parser = new DOMParser();
        const nextDocument = parser.parseFromString(html, 'text/html');
        const nextRoot = nextDocument.querySelector('[data-ui-events-wheel-ajax-root]');
        const currentRoot = document.querySelector('[data-ui-events-wheel-ajax-root]');
        if (!nextRoot || !currentRoot) {
            window.location.href = url;
            return;
        }

        const nextTitle = nextDocument.querySelector('title');
        if (nextTitle) {
            document.title = nextTitle.textContent;
        }

        const currentPageTitle = document.querySelector('.admin-page-title');
        const nextPageTitle = nextDocument.querySelector('.admin-page-title');
        if (currentPageTitle && nextPageTitle) {
            currentPageTitle.textContent = nextPageTitle.textContent;
        }

        const importedRoot = document.importNode(nextRoot, true);
        currentRoot.replaceWith(importedRoot);
        runEventsWheelAjaxScripts(document.querySelector('[data-ui-events-wheel-ajax-root]'));
        initEventsPageLocalUi(document);

        if (pushState) {
            history.pushState({ eventsWheelAjax: true }, '', url);
        }
    }

    function loadEventsWheelAjaxUrl(url, pushState = true) {
        const root = document.querySelector('[data-ui-events-wheel-ajax-root]');
        if (!root) {
            window.location.href = url;
            return Promise.resolve();
        }

        root.classList.add('is-loading');
        const isTable = root.querySelector('.ui-events-table-wrap') !== null;
        if (isTable) {
            root.innerHTML = `
                <div class="ui-events-admin-page">
                    <div class="ui-events-skeleton-box ui-events-skeleton-box--hero"></div>
                    <div class="admin-card ui-events-admin-panel">
                        <div class="card-body ui-events-skeleton-card-body">
                            <div class="ui-events-skeleton-box ui-events-skeleton-box--row"></div>
                            <div class="ui-events-skeleton-box ui-events-skeleton-box--row"></div>
                            <div class="ui-events-skeleton-box ui-events-skeleton-box--row"></div>
                            <div class="ui-events-skeleton-box ui-events-skeleton-box--row"></div>
                            <div class="ui-events-skeleton-box ui-events-skeleton-box--row-last"></div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            root.innerHTML = `
                <div class="ui-events-admin-page">
                    <div class="ui-events-skeleton-box ui-events-skeleton-box--hero"></div>
                    <div class="ui-events-skeleton-grid">
                        <div class="ui-events-skeleton-box ui-events-skeleton-box--card"></div>
                        <div class="ui-events-skeleton-box ui-events-skeleton-box--card"></div>
                        <div class="ui-events-skeleton-box ui-events-skeleton-box--card"></div>
                    </div>
                </div>
            `;
        }
        
        return fetch(url, {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Çark sayfası yüklenemedi.');
                return response.text();
            })
            .then(html => {
                replaceEventsWheelAjaxContent(html, url, pushState);
            })
            .catch(() => {
                window.location.href = url;
            });
    }

    function initEventsWheelAjaxNavigation() {
        if (window.__eventsWheelAjaxNavigationInitialized) return;
        window.__eventsWheelAjaxNavigationInitialized = true;

        document.addEventListener('click', function(event) {
            const link = event.target.closest('[data-ui-events-wheel-ajax-link]');
            if (!link) return;
            const href = link.getAttribute('href');
            if (!href || href.charAt(0) === '#') return;

            event.preventDefault();
            loadEventsWheelAjaxUrl(link.href, true);
        });

        window.addEventListener('popstate', function() {
            if (!document.querySelector('[data-ui-events-wheel-ajax-root]')) return;
            loadEventsWheelAjaxUrl(window.location.href, false);
        });
    }

    function eventsAdminTopbarOffset() {
        const topbar = document.querySelector('.admin-topbar');
        if (!topbar || typeof topbar.getBoundingClientRect !== 'function') {
            return 0;
        }

        const rect = topbar.getBoundingClientRect();
        return Math.max(0, Math.ceil(rect.bottom));
    }

    function eventsAdminModalViewportGap() {
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        return viewportHeight > 0 && viewportHeight < 700 ? 8 : 12;
    }

    function syncEventsAdminModalViewport(modal) {
        if (!modal || !modal.style) return;

        let offset = eventsAdminTopbarOffset();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
        if (viewportHeight > 0) {
            offset = Math.min(offset, Math.floor(viewportHeight * 0.35));
        }

        modal.style.setProperty('--ui-admin-modal-top-offset', offset + 'px');
        modal.style.setProperty('--ui-admin-modal-viewport-gap', eventsAdminModalViewportGap() + 'px');
    }

    function scheduleEventsAdminModalViewportSync(modal) {
        syncEventsAdminModalViewport(modal);
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(() => syncEventsAdminModalViewport(modal));
            return;
        }
        window.setTimeout(() => syncEventsAdminModalViewport(modal), 0);
    }

    function syncOpenEventsAdminModals() {
        document.querySelectorAll('[data-ui-events-admin-modal].is-open, [data-ui-events-admin-modal].ui-admin-modal-open').forEach(syncEventsAdminModalViewport);
    }

    let eventsAdminModalResizeFrame = 0;
    function requestOpenEventsAdminModalViewportSync() {
        if (eventsAdminModalResizeFrame) return;
        if (window.requestAnimationFrame) {
            eventsAdminModalResizeFrame = window.requestAnimationFrame(() => {
                eventsAdminModalResizeFrame = 0;
                syncOpenEventsAdminModals();
            });
            return;
        }
        syncOpenEventsAdminModals();
    }

    window.addEventListener('resize', requestOpenEventsAdminModalViewportSync, { passive: true });
    window.addEventListener('orientationchange', requestOpenEventsAdminModalViewportSync);

    function initEventsMasterModal(config) {
        document.querySelectorAll(config.rootSelector).forEach(root => {
            const boundKey = config.boundKey || 'eventsModalBound';
            if (root.dataset[boundKey] === 'true') return;
            root.dataset[boundKey] = 'true';

            const rows = Array.from(root.querySelectorAll(config.targetSelector));
            const panels = Array.from(root.querySelectorAll(config.panelSelector));
            const modal = root.querySelector(config.modalSelector);
            const closeButtons = modal ? Array.from(modal.querySelectorAll(config.closeSelector)) : [];
            let lastFocused = null;

            const openAdminModal = (preferredFocus, fallbackFocus) => {
                if (!modal) return false;

                const commonOptions = {
                    openClass: 'is-open',
                    bodyClass: 'ui-admin-dialog-open',
                    initialFocus: config.initialFocus,
                    returnFocus: lastFocused,
                    onClose: () => {
                        modal.classList.remove('ui-admin-modal-open');
                        document.body.classList.remove('ui-events-rule-modal-open');
                    }
                };

                document.body.classList.add('ui-events-rule-modal-open');
                scheduleEventsAdminModalViewportSync(modal);

                if (typeof window.openAdminManagedModal === 'function') {
                    window.openAdminManagedModal(modal, commonOptions);
                    scheduleEventsAdminModalViewportSync(modal);
                    return true;
                }

                if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
                    window.TMUI.openDialog(modal, commonOptions);
                    modal.classList.add('ui-admin-modal-open');
                    scheduleEventsAdminModalViewportSync(modal);
                    return true;
                }

                if (window.uiAdminModal && typeof window.uiAdminModal.open === 'function') {
                    window.uiAdminModal.open(modal);
                    modal.classList.add('ui-admin-modal-open');
                    scheduleEventsAdminModalViewportSync(modal);
                    return true;
                }

                modal.hidden = false;
                modal.removeAttribute('aria-hidden');
                modal.classList.add('is-open', 'ui-admin-modal-open');
                document.body.classList.add('ui-admin-dialog-open');

                const focusTarget = preferredFocus || fallbackFocus;
                if (focusTarget && typeof focusTarget.focus === 'function') {
                    focusTarget.focus({ preventScroll: true });
                }

                scheduleEventsAdminModalViewportSync(modal);
                return true;
            };

            const closeAdminModal = () => {
                if (!modal || modal.hidden) return;

                if (typeof window.closeAdminManagedModal === 'function') {
                    window.closeAdminManagedModal(modal, () => {
                        document.body.classList.remove('ui-events-rule-modal-open');
                    });
                    return;
                }

                if (window.TMUI && typeof window.TMUI.closeDialog === 'function' && modal._tmuiDialog) {
                    window.TMUI.closeDialog(modal);
                    modal.classList.remove('ui-admin-modal-open');
                    document.body.classList.remove('ui-events-rule-modal-open');
                    return;
                }

                if (window.uiAdminModal && typeof window.uiAdminModal.close === 'function') {
                    window.uiAdminModal.close(modal, { preferNavigate: false });
                    modal.classList.remove('ui-admin-modal-open');
                    document.body.classList.remove('ui-events-rule-modal-open');
                    return;
                }

                modal.classList.remove('is-open', 'ui-admin-modal-open');
                modal.hidden = true;
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('ui-admin-dialog-open');
                document.body.classList.remove('ui-events-rule-modal-open');

                if (lastFocused && typeof lastFocused.focus === 'function') {
                    lastFocused.focus();
                }
            };

            const getFallbackFocusTarget = () => {
                if (!modal) return null;
                return modal.querySelector(
                    'input:not([type="hidden"]):not([disabled]),' +
                    'select:not([disabled]),' +
                    'textarea:not([disabled]),' +
                    'button:not([disabled]):not([type="hidden"]),' +
                    '[tabindex]:not([tabindex="-1"])'
                );
            };

            const activate = panelKey => {
                rows.forEach(row => {
                    row.classList.toggle('is-selected', row.getAttribute(config.targetAttr) === panelKey);
                });

                panels.forEach(panel => {
                    const active = panel.getAttribute(config.panelAttr) === panelKey;
                    panel.classList.toggle('is-active', active);
                    panel.hidden = !active;
                });

                const activePanel = panels.find(panel => panel.getAttribute(config.panelAttr) === panelKey);
                if (activePanel) {
                    activePanel.scrollTop = 0;
                    const activeBody = activePanel.querySelector('.ui-events-rule-detail-body');
                    if (activeBody) {
                        activeBody.scrollTop = 0;
                    }
                }

                if (modal) {
                    modal.scrollTop = 0;
                }
            };

            const open = panelKey => {
                activate(panelKey);
                if (!modal) return;

                lastFocused = document.activeElement;
                const preferredFocus = config.initialFocus ? modal.querySelector(config.initialFocus) : null;
                const fallbackFocus = preferredFocus || getFallbackFocusTarget();
                if (openAdminModal(preferredFocus, fallbackFocus)) {
                    if (!preferredFocus && fallbackFocus && typeof fallbackFocus.focus === 'function') {
                        window.setTimeout(() => {
                            if (!modal.hidden && document.activeElement === document.body) {
                                fallbackFocus.focus({ preventScroll: true });
                            }
                        }, 0);
                    }
                }
            };

            const close = () => {
                closeAdminModal();
            };

            rows.forEach(row => {
                row.addEventListener('click', () => {
                    open(row.getAttribute(config.targetAttr));
                });
            });

            (config.openButtons || []).forEach(buttonConfig => {
                document.querySelectorAll(buttonConfig.selector).forEach(button => {
                    const buttonBoundKey = buttonConfig.boundKey || (boundKey + 'Button');
                    if (button.dataset[buttonBoundKey] === 'true') return;
                    button.dataset[buttonBoundKey] = 'true';
                    button.addEventListener('click', () => {
                        open(button.getAttribute(buttonConfig.attr) || buttonConfig.fallback || '');
                    });
                });
            });

            closeButtons.forEach(button => {
                button.addEventListener('click', close);
            });

            if (modal) {
                modal.addEventListener('click', event => {
                    if (event.target === modal) close();
                });
            }

            document.addEventListener('keydown', event => {
                if (window.TMUI && typeof window.TMUI.openDialog === 'function') return;
                if (event.key === 'Escape') close();
            });

            const initial = config.initialAttr ? root.getAttribute(config.initialAttr) : '';
            if (initial) {
                activate(initial);
                if (config.openInitialAttr && root.getAttribute(config.openInitialAttr) === 'true') {
                    open(initial);
                }
                if (config.openOnHash && window.location.hash === config.openOnHash) {
                    open(initial);
                }
            }
        });
    }

    function initEventsRewardFilters() {
        const searchInput = document.getElementById('ui-events-rewards-search');
        const statusFilter = document.getElementById('ui-events-rewards-status-filter');
        const typeFilter = document.getElementById('ui-events-rewards-type-filter');
        if (!searchInput || !statusFilter || !typeFilter) return;
        if (searchInput.dataset.eventsRewardFiltersBound === 'true') return;
        searchInput.dataset.eventsRewardFiltersBound = 'true';

        const filterRows = () => {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;
            const typeValue = typeFilter.value.toLowerCase();

            document.querySelectorAll('.ui-events-reward-row').forEach(row => {
                const textContent = row.textContent.toLowerCase();
                const rowStatus = (row.getAttribute('data-reward-status') || '').toLowerCase();
                const rowSource = (row.getAttribute('data-reward-source') || '').toLowerCase();
                let show = true;

                if (searchTerm && !textContent.includes(searchTerm)) {
                    show = false;
                }
                if (statusValue !== 'all' && rowStatus !== statusValue) {
                    show = false;
                }
                if (typeValue !== 'all' && rowSource !== typeValue) {
                    show = false;
                }

                row.classList.toggle('ui-events-admin-hidden', !show);
            });

            document.dispatchEvent(new CustomEvent('events:reward-filtered'));
        };

        searchInput.addEventListener('input', filterRows);
        statusFilter.addEventListener('change', filterRows);
        typeFilter.addEventListener('change', filterRows);
        filterRows();
    }

    function initEventsWheelHistoryTabs() {
        const root = document.querySelector('[data-ui-events-wheel-history-tabs]');
        if (!root || root.dataset.eventsWheelHistoryBound === 'true') return;
        root.dataset.eventsWheelHistoryBound = 'true';

        const buttons = Array.from(root.querySelectorAll('[data-ui-events-wheel-history-tab]'));
        const panels = Array.from(document.querySelectorAll('[data-ui-events-wheel-history-panel]'));

        const activatePanel = panelId => {
            buttons.forEach(button => {
                const active = button.getAttribute('data-ui-events-wheel-history-tab') === panelId;
                button.classList.toggle('ui-admin-btn-primary', active);
                button.classList.toggle('ui-admin-btn-outline', !active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach(panel => {
                const active = panel.id === panelId;
                panel.hidden = !active;
                panel.classList.toggle('is-active', active);
            });
        };

        buttons.forEach(button => {
            button.addEventListener('click', () => {
                activatePanel(button.getAttribute('data-ui-events-wheel-history-tab'));
            });
        });
    }

    function initEventsSettingsTabs() {
        const page = document.querySelector('.ui-events-admin-page[data-ui-events-admin-page="settings"]');
        if (!page) return;

        const tabButtons = Array.from(page.querySelectorAll('.ui-events-tab-btn'));
        const tabContents = Array.from(page.querySelectorAll('.ui-events-tab-content'));
        if (tabButtons.length === 0 || tabContents.length === 0) return;

        if (page.dataset.eventsSettingsTabsBound === 'true') return;
        page.dataset.eventsSettingsTabsBound = 'true';

        const storageKey = 'ui-events-settings-active-tab';

        const switchTab = tabName => {
            const fallback = 'general';
            const activeButton = page.querySelector('.ui-events-tab-btn[data-tab="' + tabName + '"]')
                || page.querySelector('.ui-events-tab-btn[data-tab="' + fallback + '"]');
            const activePanel = page.querySelector('.ui-events-tab-content[data-tab="' + tabName + '"]')
                || page.querySelector('.ui-events-tab-content[data-tab="' + fallback + '"]');

            if (!activeButton || !activePanel) return;

            tabButtons.forEach(button => {
                const active = button === activeButton;
                button.classList.toggle('active', active);
                button.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            tabContents.forEach(content => {
                content.classList.toggle('active', content === activePanel);
            });

            try {
                localStorage.setItem(storageKey, activeButton.dataset.tab || fallback);
            } catch (error) {}

            document.dispatchEvent(new CustomEvent('events:settings-tab-switched'));
        };

        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                switchTab(button.dataset.tab || 'general');
            });
        });

        let savedTab = 'general';
        try {
            savedTab = localStorage.getItem(storageKey) || 'general';
        } catch (error) {}
        switchTab(savedTab);
    }

    function initEventsSettingsSearch() {
        const page = document.querySelector('.ui-events-admin-page[data-ui-events-admin-page="settings"]');
        if (!page || page.dataset.eventsSettingsSearchBound === 'true') return;

        const input = page.querySelector('[data-ui-events-settings-search]');
        const form = page.querySelector('[data-ui-events-settings-form]');
        const rows = Array.from(page.querySelectorAll('[data-ui-events-setting-row]'));
        const sections = Array.from(page.querySelectorAll('[data-ui-events-settings-section]'));
        const emptySearch = page.querySelector('[data-ui-events-settings-empty-search]');
        if (!input || !form || rows.length === 0) return;

        page.dataset.eventsSettingsSearchBound = 'true';

        const normalize = value => String(value || '').toLocaleLowerCase('tr-TR');
        const update = () => {
            const query = normalize(input.value.trim());
            const hasQuery = query !== '';
            let globalMatches = 0;

            form.classList.toggle('is-searching', hasQuery);

            rows.forEach(row => {
                const haystack = normalize((row.dataset.uiEventsSettingText || '') + ' ' + row.textContent);
                const visible = !hasQuery || haystack.includes(query);
                row.hidden = !visible;
                if (visible) {
                    globalMatches += 1;
                }
            });

            sections.forEach(section => {
                const sectionRows = Array.from(section.querySelectorAll('[data-ui-events-setting-row]'));
                const visibleRows = sectionRows.filter(row => !row.hidden);
                const empty = section.querySelector('[data-ui-events-section-empty]');
                const count = section.querySelector('[data-ui-events-section-count]');

                section.hidden = hasQuery && visibleRows.length === 0;
                if (empty) {
                    empty.hidden = !hasQuery || visibleRows.length > 0;
                }
                if (count) {
                    count.textContent = hasQuery
                        ? visibleRows.length + '/' + sectionRows.length + ' ayar'
                        : sectionRows.length + ' ayar';
                }
            });

            const activePanel = page.querySelector('.ui-events-tab-content.active');
            const activeMatches = activePanel
                ? Array.from(activePanel.querySelectorAll('[data-ui-events-setting-row]')).filter(row => !row.hidden).length
                : globalMatches;
            if (emptySearch) {
                emptySearch.hidden = !hasQuery || activeMatches > 0;
            }
        };

        input.addEventListener('input', update);
        document.addEventListener('events:settings-tab-switched', update);
        update();
    }

    let eventsSettingsNumberFormatter = null;

    function formatEventsSettingsNumber(number) {
        if (!eventsSettingsNumberFormatter && typeof Intl !== 'undefined' && Intl.NumberFormat) {
            eventsSettingsNumberFormatter = new Intl.NumberFormat('tr-TR', {
                maximumFractionDigits: 2
            });
        }

        if (eventsSettingsNumberFormatter) {
            return eventsSettingsNumberFormatter.format(number);
        }

        return String(Math.round(number * 100) / 100);
    }

    function formatEventsSettingsDuration(seconds, zeroLabel) {
        let remaining = Math.max(0, Math.round(seconds));
        if (remaining === 0) {
            return zeroLabel || '0 sn';
        }

        const units = [
            ['gün', 86400],
            ['saat', 3600],
            ['dk', 60],
            ['sn', 1]
        ];
        const parts = [];
        units.forEach(([label, unitSeconds]) => {
            if (parts.length >= 2) return;
            const amount = Math.floor(remaining / unitSeconds);
            if (amount <= 0) return;
            parts.push(amount + ' ' + label);
            remaining %= unitSeconds;
        });

        return parts.join(' ');
    }

    function formatEventsSettingsDays(value, zeroLabel) {
        const days = Math.max(0, Math.round(value));
        if (days === 0) {
            return zeroLabel || '0 gün';
        }

        if (days >= 365) {
            const years = Math.floor(days / 365);
            const remainingDays = days % 365;
            return remainingDays > 0
                ? years + ' yıl ' + remainingDays + ' gün'
                : years + ' yıl';
        }

        if (days >= 7 && days % 7 === 0) {
            return Math.floor(days / 7) + ' hafta';
        }

        return days + ' gün';
    }

    function readableNumberFromInput(input) {
        const rawValue = String(input.value || '').trim().replace(',', '.');
        if (rawValue === '') {
            return input.dataset.uiEventsReadableZeroLabel || 'Değer girilmedi';
        }

        let number = Number(rawValue);
        if (!Number.isFinite(number)) {
            return 'Geçerli sayı girin';
        }

        const min = input.hasAttribute('min') ? Number(input.getAttribute('min')) : NaN;
        const max = input.hasAttribute('max') ? Number(input.getAttribute('max')) : NaN;
        if (Number.isFinite(min)) {
            number = Math.max(min, number);
        }
        if (Number.isFinite(max)) {
            number = Math.min(max, number);
        }

        const format = input.dataset.uiEventsReadableFormat || 'count';
        const zeroLabel = input.dataset.uiEventsReadableZeroLabel || '';
        const unit = input.dataset.uiEventsReadableUnit || '';
        const suffix = input.dataset.uiEventsReadableSuffix || '';

        if (number === 0 && zeroLabel !== '') {
            return zeroLabel;
        }

        if (format === 'duration_seconds') {
            return formatEventsSettingsDuration(number, zeroLabel || '0 sn');
        }
        if (format === 'duration_minutes') {
            return formatEventsSettingsDuration(number * 60, zeroLabel || '0 dk');
        }
        if (format === 'days') {
            return formatEventsSettingsDays(number, zeroLabel || '0 gün');
        }
        if (format === 'percent') {
            return '%' + formatEventsSettingsNumber(number) + (suffix ? ' ' + suffix : '');
        }
        if (format === 'level') {
            return formatEventsSettingsNumber(number) + '. seviye';
        }

        return (formatEventsSettingsNumber(number) + (unit ? ' ' + unit : '')).trim();
    }

    function initEventsSettingsReadableValues() {
        const inputs = Array.from(document.querySelectorAll('[data-ui-events-readable-input]'));
        if (inputs.length === 0) return;

        const updateInput = input => {
            const row = input.closest('[data-ui-events-setting-row], .ui-events-rule-field');
            const target = row ? row.querySelector('[data-ui-events-readable-value]') : null;
            if (target) {
                target.textContent = readableNumberFromInput(input);
            }
        };

        inputs.forEach(input => {
            if (input._eventsReadableValuesBound !== true) {
                input._eventsReadableValuesBound = true;
                input.addEventListener('input', () => updateInput(input));
                input.addEventListener('change', () => updateInput(input));
            }
            input.dataset.eventsReadableValuesBound = 'true';
            updateInput(input);
        });
    }

    function initEventsPageLocalUi() {
        initEventsRewardFilters();
        initEventsWheelHistoryTabs();
        initEventsSettingsTabs();
        initEventsSettingsSearch();
        initEventsSettingsReadableValues();
        initEventsPendingRewardBulkActions();

        initEventsMasterModal({
            rootSelector: '[data-ui-events-rule-master]',
            targetSelector: '[data-ui-events-rule-target]',
            targetAttr: 'data-ui-events-rule-target',
            panelSelector: '[data-ui-events-rule-panel]',
            panelAttr: 'data-ui-events-rule-panel',
            modalSelector: '[data-ui-events-rule-modal]',
            closeSelector: '[data-ui-events-rule-close]',
            initialFocus: '.ui-events-rule-detail-form.is-active [data-ui-events-rule-close]',
            boundKey: 'eventsRuleModalBound'
        });

        initEventsMasterModal({
            rootSelector: '[data-ui-events-wheel-master]',
            targetSelector: '[data-ui-events-wheel-target]',
            targetAttr: 'data-ui-events-wheel-target',
            panelSelector: '[data-ui-events-wheel-panel]',
            panelAttr: 'data-ui-events-wheel-panel',
            modalSelector: '[data-ui-events-wheel-modal]',
            closeSelector: '[data-ui-events-wheel-close]',
            initialFocus: '.ui-events-wheel-detail-form.is-active [data-ui-events-wheel-close]',
            initialAttr: 'data-ui-events-wheel-initial',
            openInitialAttr: 'data-ui-events-wheel-open-initial',
            boundKey: 'eventsWheelModalBound',
            openButtons: [
                { selector: '[data-ui-events-wheel-settings]', attr: 'data-ui-events-wheel-settings', fallback: 'settings' },
                { selector: '[data-ui-events-wheel-new]', attr: 'data-ui-events-wheel-new', fallback: 'new' }
            ]
        });

        initEventsMasterModal({
            rootSelector: '[data-ui-events-item-master]',
            targetSelector: '[data-ui-events-item-target]',
            targetAttr: 'data-ui-events-item-target',
            panelSelector: '[data-ui-events-item-panel]',
            panelAttr: 'data-ui-events-item-panel',
            modalSelector: '[data-ui-events-item-modal]',
            closeSelector: '[data-ui-events-item-close]',
            initialFocus: '.ui-events-item-detail-form.is-active [data-ui-events-item-close]',
            initialAttr: 'data-ui-events-item-initial',
            openOnHash: '#edit',
            boundKey: 'eventsItemModalBound',
            openButtons: [
                { selector: '[data-ui-events-item-new]', attr: 'data-ui-events-item-new', fallback: 'new' }
            ]
        });

        initEventsMasterModal({
            rootSelector: '[data-ui-events-task-master]',
            targetSelector: '[data-ui-events-task-target]',
            targetAttr: 'data-ui-events-task-target',
            panelSelector: '[data-ui-events-task-panel]',
            panelAttr: 'data-ui-events-task-panel',
            modalSelector: '[data-ui-events-task-modal]',
            closeSelector: '[data-ui-events-task-close]',
            initialFocus: '.ui-events-task-detail-form.is-active [data-ui-events-task-close]',
            initialAttr: 'data-ui-events-task-initial',
            boundKey: 'eventsTaskModalBound',
            openButtons: [
                { selector: '[data-ui-events-task-new]', attr: 'data-ui-events-task-new', fallback: 'new' }
            ]
        });

        initEventsMasterModal({
            rootSelector: '[data-ui-events-raffle-master]',
            targetSelector: '[data-ui-events-raffle-target]',
            targetAttr: 'data-ui-events-raffle-target',
            panelSelector: '[data-ui-events-raffle-panel]',
            panelAttr: 'data-ui-events-raffle-panel',
            modalSelector: '[data-ui-events-raffle-modal]',
            closeSelector: '[data-ui-events-raffle-close]',
            initialFocus: '.ui-events-raffle-detail-form.is-active [data-ui-events-raffle-close]',
            initialAttr: 'data-ui-events-raffle-initial',
            boundKey: 'eventsRaffleModalBound',
            openButtons: [
                { selector: '[data-ui-events-raffle-new]', attr: 'data-ui-events-raffle-new', fallback: 'new' }
            ]
        });
    }

    // Search/Filter
    function initSearch() {
        document.querySelectorAll('.search-bar-input').forEach(input => {
            input.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const table = this.closest('.table-container')?.querySelector('.admin-table');

                if (table) {
                    table.querySelectorAll('tbody tr').forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(query) ? '' : 'none';
                    });
                }
            });
        });
    }

    function eventsToast(message, type = 'info', duration) {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type, duration);
            return;
        }

        const container = document.getElementById('toastContainer') || document.body;
        const toast = document.createElement('div');
        toast.className = `topic-toast toast-${type || 'info'}`;
        toast.textContent = String(message || '');
        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('toast-out');
            setTimeout(() => toast.remove(), 300);
        }, duration || 3500);
    }

    window.EventsToast = window.EventsToast || {};
    window.EventsToast.show = eventsToast;

    function confirmPresetForAction(form) {
        const action = form.querySelector('input[name="_events_action"]')?.value || '';
        if (action === 'cancel_reward') {
            return {
                title: 'Ödül iptal edilsin mi?',
                message: 'Bu ödül bekleyen işlemlerden kaldırılır ve kullanıcıya teslim edilmez.',
                confirmLabel: 'İptal Et',
                tone: 'danger'
            };
        }
        if (action === 'apply_reward') {
            return {
                title: 'Ödül teslim edilsin mi?',
                message: 'Bu işlem ödülü kullanıcı hesabına uygular ve durumunu teslim edildi yapar.',
                confirmLabel: 'Teslim Et',
                tone: 'success'
            };
        }
        if (form.classList.contains('ui-events-draw-form')) {
            return {
                title: 'Çekiliş çekilsin mi?',
                message: 'Kazananlar belirlenecek ve sonuç etkinlik kayıtlarına işlenecek.',
                confirmLabel: 'Çekilişi Çek',
                tone: 'warning'
            };
        }
        return null;
    }

    function confirmOptionsFromElement(form, submitter) {
        if (!form) return null;
        const source = submitter?.dataset?.eventsConfirm !== undefined ? submitter : form;
        const rawConfirm = source.dataset.eventsConfirm;
        if (rawConfirm === 'off' || rawConfirm === 'false') return null;

        const preset = confirmPresetForAction(form);
        if (rawConfirm === undefined && !preset) return null;

        return {
            title: source.dataset.eventsConfirmTitle || preset?.title || 'İşlemi onayla',
            message: rawConfirm || source.dataset.eventsConfirmMessage || preset?.message || 'Bu işlem uygulanacak.',
            confirmLabel: source.dataset.eventsConfirmOk || preset?.confirmLabel || 'Onayla',
            cancelLabel: source.dataset.eventsConfirmCancel || 'Vazgeç',
            tone: source.dataset.eventsConfirmTone || preset?.tone || 'primary'
        };
    }

    function ensureEventsConfirmModal() {
        let modal = document.querySelector('[data-ui-events-confirm-modal]');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.className = 'ui-events-confirm-modal';
        modal.setAttribute('data-ui-events-confirm-modal', '');
        modal.setAttribute('hidden', '');
        modal.innerHTML = `
            <div class="ui-events-confirm-backdrop" data-ui-events-confirm-cancel></div>
            <div class="ui-events-confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="ui-events-confirm-title">
                <div class="ui-events-confirm-icon"><i class="bi bi-exclamation-triangle"></i></div>
                <div class="ui-events-confirm-copy">
                    <h3 id="ui-events-confirm-title"></h3>
                    <p data-ui-events-confirm-message></p>
                </div>
                <div class="ui-events-confirm-actions">
                    <button type="button" class="ui-admin-btn ui-admin-btn-outline" data-ui-events-confirm-cancel>Vazgeç</button>
                    <button type="button" class="ui-admin-btn ui-admin-btn-primary" data-ui-events-confirm-ok>Onayla</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        return modal;
    }

    function showEventsConfirm(options) {
        const modal = ensureEventsConfirmModal();
        const title = modal.querySelector('#ui-events-confirm-title');
        const message = modal.querySelector('[data-ui-events-confirm-message]');
        const okButton = modal.querySelector('[data-ui-events-confirm-ok]');
        const cancelButtons = modal.querySelectorAll('[data-ui-events-confirm-cancel]');
        const icon = modal.querySelector('.ui-events-confirm-icon i');

        title.textContent = options.title || 'İşlemi onayla';
        message.textContent = options.message || 'Bu işlem uygulanacak.';
        okButton.textContent = options.confirmLabel || 'Onayla';
        okButton.className = 'ui-admin-btn ' + (options.tone === 'danger' ? 'ui-admin-btn-danger' : 'ui-admin-btn-primary');
        cancelButtons.forEach(button => {
            if (button.tagName === 'BUTTON') {
                button.textContent = options.cancelLabel || 'Vazgeç';
            }
        });
        if (icon) {
            icon.className = 'bi ' + (options.tone === 'danger' ? 'bi-exclamation-octagon' : 'bi-check2-circle');
        }

        modal.classList.remove('is-danger', 'is-warning', 'is-success');
        if (options.tone) modal.classList.add('is-' + options.tone);
        modal.hidden = false;
        document.body.classList.add('ui-events-confirm-open');
        okButton.focus();

        return new Promise(resolve => {
            const cleanup = result => {
                modal.hidden = true;
                document.body.classList.remove('ui-events-confirm-open');
                okButton.removeEventListener('click', onOk);
                cancelButtons.forEach(button => button.removeEventListener('click', onCancel));
                document.removeEventListener('keydown', onKeydown);
                resolve(result);
            };
            const onOk = () => cleanup(true);
            const onCancel = () => cleanup(false);
            const onKeydown = event => {
                if (event.key === 'Escape') cleanup(false);
            };

            okButton.addEventListener('click', onOk);
            cancelButtons.forEach(button => button.addEventListener('click', onCancel));
            document.addEventListener('keydown', onKeydown);
        });
    }

    window.EventsConfirm = window.EventsConfirm || {};
    window.EventsConfirm.show = showEventsConfirm;
    window.EventsConfirm.confirmElement = function(element) {
        const form = element?.closest ? element.closest('form') : element;
        const options = confirmOptionsFromElement(form, element);
        return options ? showEventsConfirm(options) : Promise.resolve(true);
    };

    function initEventsConfirmGuard() {
        document.addEventListener('submit', function(event) {
            const form = event.target.closest('form');
            const options = confirmOptionsFromElement(form, event.submitter || null);
            if (!form || !options) return;
            if (form.dataset.eventsConfirmApproved === '1') {
                delete form.dataset.eventsConfirmApproved;
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            showEventsConfirm(options).then(confirmed => {
                if (!confirmed) return;
                form.dataset.eventsConfirmApproved = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(event.submitter || undefined);
                    return;
                }
                form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            });
        }, true);
    }

    function formActionLabel(form) {
        const action = form.querySelector('input[name="_events_action"]')?.value || '';
        const labels = {
            save_config: 'Ayarlar kaydedildi.',
            save_reward: 'Ödül kaydedildi.',
            save_item: 'Ödül kaydedildi.',
            save_task: 'Görev kaydedildi.',
            save_raffle: 'Çekiliş kaydedildi.',
            manual_reward: 'Ödül işlemi tamamlandı.',
            apply_reward: 'Ödül teslim edildi.',
            cancel_reward: 'Ödül iptal edildi.'
        };

        return labels[action] || 'İşlem tamamlandı.';
    }

    function showFormInlineStatus(form, message, type) {
        if (!form) return;
        form.querySelectorAll(':scope > .ui-events-form-status').forEach(status => status.remove());
    }

    function formErrorFromHtml(html) {
        if (!html || typeof DOMParser === 'undefined') return '';
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const error = doc.querySelector('.ui-events-error-list, .alert-danger, .form-error, .ui-events-field-error');
        return error ? error.textContent.trim().replace(/\s+/g, ' ') : '';
    }

    function setFormBusy(form, isBusy) {
        form.classList.toggle('is-ajax-loading', isBusy);
        form.querySelectorAll('button, input[type="submit"]').forEach(control => {
            if (isBusy) {
                control.dataset.eventsWasDisabled = control.disabled ? '1' : '0';
                control.disabled = true;
            } else if (control.dataset.eventsWasDisabled !== '1') {
                control.disabled = false;
            }
        });
    }

    function shouldAjaxSubmit(form) {
        if (!form || !form.closest('.ui-events-admin-page')) return false;
        if ((form.dataset.eventsAjax || '').toLowerCase() === 'off') return false;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'post') return false;
        if ((form.enctype || '').toLowerCase() === 'multipart/form-data') return false;
        return Boolean(form.querySelector('input[name="_events_action"]'));
    }

    async function submitEventsAjaxForm(form, submitter) {
        const formData = new FormData(form);
        if (submitter && submitter.name && !formData.has(submitter.name)) {
            formData.append(submitter.name, submitter.value || '');
        }

        const url = submitter?.formAction || form.action || window.location.href;
        const method = (submitter?.formMethod || form.method || 'POST').toUpperCase();

        setFormBusy(form, true);
        try {
            const response = await fetch(url, {
                method,
                body: formData,
                headers: {
                    'Accept': 'application/json, text/html;q=0.9',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                const data = await response.json();
                if (!response.ok || data.success === false) {
                    throw new Error(data.message || data.error || 'İşlem tamamlanamadı.');
                }

                const successMessage = data.message || formActionLabel(form);
                showFormInlineStatus(form, successMessage, 'success');
                eventsToast(successMessage, 'success');
                window.setTimeout(() => {
                    window.location.href = data.redirect || response.url || window.location.href;
                }, 450);
                return;
            }

            const html = await response.text();
            const htmlError = formErrorFromHtml(html);
            if (!response.ok || htmlError) {
                throw new Error(htmlError || 'İşlem tamamlanamadı.');
            }

            const successMessage = formActionLabel(form);
            showFormInlineStatus(form, successMessage, 'success');
            eventsToast(successMessage, 'success');
            window.setTimeout(() => {
                window.location.href = response.url || window.location.href;
            }, 450);
        } catch (error) {
            const errorMessage = error.message || 'İşlem tamamlanamadı.';
            showFormInlineStatus(form, errorMessage, 'error');
            eventsToast(errorMessage, 'error');
            setFormBusy(form, false);
        }
    }

    function eventsAdminBaseUri() {
        const meta = document.querySelector('meta[name="app-base-uri"]');
        if (meta && meta.getAttribute('content')) {
            return meta.getAttribute('content').replace(/\/$/, '');
        }

        const script = document.querySelector('script[src*="/events/assets/js/admin-ui.js"]');
        if (script && script.src) {
            try {
                const url = new URL(script.src, window.location.href);
                const marker = '/events/assets/js/admin-ui.js';
                const markerIndex = url.pathname.indexOf(marker);
                if (markerIndex >= 0) {
                    return url.origin + url.pathname.slice(0, markerIndex);
                }
            } catch (error) {}
        }

        const match = window.location.pathname.match(/^(.*?)(?:\/admin|\/events)(?:\/|$)/);
        return match ? window.location.origin + match[1] : '';
    }

    async function submitEventsDrawForm(form) {
        const button = form.querySelector('button[type="submit"], button:not([type])');
        const formData = new FormData(form);

        if (button) button.disabled = true;
        try {
            const response = await fetch(eventsAdminBaseUri() + '/events/api/raffle-draw', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': formData.get('_token') || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    _token: formData.get('_token') || '',
                    raffle_id: formData.get('raffle_id'),
                    notes: formData.get('notes') || ''
                })
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                throw new Error(data.message || data.error || 'Çekim tamamlanamadı.');
            }

            const successMessage = data.message || 'Çekiliş çekildi.';
            eventsToast(successMessage, 'success');
            window.setTimeout(() => {
                window.location.reload();
            }, 650);
        } catch (error) {
            eventsToast(error.message || 'Çekim tamamlanamadı.', 'error');
            if (button) button.disabled = false;
        }
    }

    function initEventsDrawForms() {
        document.addEventListener('submit', function(event) {
            const form = event.target.closest('.ui-events-admin-page .ui-events-draw-form');
            if (!form) return;

            event.preventDefault();
            submitEventsDrawForm(form);
        });
    }

    function selectedPendingRewardIds() {
        return Array.from(document.querySelectorAll('.ui-events-reward-checkbox:checked'))
            .filter(checkbox => {
                const row = checkbox.closest('.ui-events-reward-row');
                return row && !row.classList.contains('ui-events-admin-hidden');
            })
            .map(checkbox => checkbox.closest('.ui-events-reward-row')?.dataset?.rewardId || '')
            .filter(Boolean);
    }

    function pendingBulkToken() {
        return document.querySelector('.ui-events-admin-page[data-ui-events-admin-page="pending"] input[name="_token"]')?.value || '';
    }

    function showPendingBulkConfirm(options) {
        if (window.EventsConfirm && typeof window.EventsConfirm.show === 'function') {
            return window.EventsConfirm.show(options);
        }
        if (typeof window.adminConfirm === 'function') {
            return window.adminConfirm(options.message || 'Bu işlem uygulanacak.', {
                title: options.title || 'İşlem onayı',
                ok: options.ok || 'Onayla',
                tone: options.tone || 'warning'
            });
        }
        return Promise.resolve(window['confirm'](options.message || 'Bu işlem uygulanacak.'));
    }

    async function submitPendingBulkAction(action, rewardIds, button) {
        const formData = new FormData();
        formData.append('_events_action', action);
        formData.append('_token', pendingBulkToken());
        rewardIds.forEach(id => formData.append('reward_ids[]', id));

        if (button) button.disabled = true;
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: formData
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.success === false) {
                throw new Error(data.message || data.error || 'İşlem tamamlanamadı.');
            }

            eventsToast(data.message || 'İşlem tamamlandı.', 'success');
            window.setTimeout(() => window.location.reload(), 650);
        } catch (error) {
            eventsToast(error.message || 'İşlem tamamlanamadı.', 'error');
            if (button) button.disabled = false;
        }
    }

    function initEventsPendingRewardBulkActions() {
        const page = document.querySelector('.ui-events-admin-page[data-ui-events-admin-page="pending"]');
        if (!page) return;
        if (page.dataset.eventsPendingBulkBound === 'true') return;
        page.dataset.eventsPendingBulkBound = 'true';

        const selectAllControls = page.querySelectorAll('#ui-events-rewards-select-all, .ui-events-rewards-checkbox-header');
        const rewardCheckboxes = Array.from(page.querySelectorAll('.ui-events-reward-checkbox'));
        const applyAllButton = page.querySelector('#ui-events-rewards-apply-all');
        const cancelAllButton = page.querySelector('#ui-events-rewards-cancel-all');

        if (rewardCheckboxes.length === 0) return;

        const visibleRewardCheckboxes = () => rewardCheckboxes.filter(checkbox => {
            const row = checkbox.closest('.ui-events-reward-row');
            return row && !row.classList.contains('ui-events-admin-hidden');
        });

        const updateBulkButtons = () => {
            const checkedCount = selectedPendingRewardIds().length;
            const visibleCount = visibleRewardCheckboxes().length;
            [applyAllButton, cancelAllButton].forEach(button => {
                if (!button) return;
                button.hidden = checkedCount === 0;
                button.disabled = checkedCount === 0;
            });
            selectAllControls.forEach(control => {
                control.checked = visibleCount > 0 && checkedCount === visibleCount;
                control.indeterminate = checkedCount > 0 && checkedCount < visibleCount;
            });
        };

        selectAllControls.forEach(control => {
            control.addEventListener('change', () => {
                visibleRewardCheckboxes().forEach(checkbox => {
                    checkbox.checked = control.checked;
                });
                updateBulkButtons();
            });
        });

        rewardCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkButtons);
        });

        if (applyAllButton) {
            applyAllButton.addEventListener('click', async () => {
                const rewardIds = selectedPendingRewardIds();
                if (rewardIds.length === 0) return;

                const confirmed = await showPendingBulkConfirm({
                    title: 'Seçilen ödüller teslim edilsin mi?',
                    message: 'Seçili ' + rewardIds.length + ' ödül kullanıcı hesaplarına teslim edilecek.',
                    confirmLabel: 'Teslim Et',
                    cancelLabel: 'Vazgeç',
                    tone: 'success'
                });
                if (!confirmed) return;

                submitPendingBulkAction('bulk_apply_rewards', rewardIds, applyAllButton);
            });
        }

        if (cancelAllButton) {
            cancelAllButton.addEventListener('click', async () => {
                const rewardIds = selectedPendingRewardIds();
                if (rewardIds.length === 0) return;

                const confirmed = await showPendingBulkConfirm({
                    title: 'Seçilen ödüller iptal edilsin mi?',
                    message: 'Seçili ' + rewardIds.length + ' ödül bekleyen listeden iptal edilecek.',
                    confirmLabel: 'İptal Et',
                    cancelLabel: 'Vazgeç',
                    tone: 'danger'
                });
                if (!confirmed) return;

                submitPendingBulkAction('bulk_cancel_rewards', rewardIds, cancelAllButton);
            });
        }

        document.addEventListener('events:reward-filtered', updateBulkButtons);
        updateBulkButtons();
    }

    function copyEventsText(value) {
        const text = String(value || '');
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise((resolve, reject) => {
            const input = document.createElement('textarea');
            input.value = text;
            input.setAttribute('readonly', '');
            input.style.position = 'fixed';
            input.style.left = '-9999px';
            input.style.top = '0';
            document.body.appendChild(input);
            input.select();
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('Kopyalanamadı.'));
            } catch (error) {
                reject(error);
            } finally {
                input.remove();
            }
        });
    }

    function initEventsPrizeCodeControls() {
        if (window.__eventsPrizeCodeControlsBound) return;
        window.__eventsPrizeCodeControlsBound = true;

        document.addEventListener('click', event => {
            const toggleButton = event.target.closest('[data-ui-events-prize-code-toggle]');
            if (toggleButton) {
                event.preventDefault();
                event.stopPropagation();
                const root = toggleButton.closest('[data-ui-events-prize-code]');
                const mask = root?.querySelector('[data-ui-events-prize-code-mask]');
                if (!root || !mask) return;

                const nextVisible = root.getAttribute('data-code-visible') !== '1';
                root.setAttribute('data-code-visible', nextVisible ? '1' : '0');
                toggleButton.setAttribute('aria-pressed', nextVisible ? 'true' : 'false');
                toggleButton.textContent = nextVisible ? 'Gizle' : 'Göster';
                mask.textContent = nextVisible ? (root.getAttribute('data-code-value') || '') : (root.getAttribute('data-code-mask') || '');
                return;
            }

            const copyButton = event.target.closest('[data-ui-events-prize-code-copy]');
            if (!copyButton) return;

            event.preventDefault();
            event.stopPropagation();
            const root = copyButton.closest('[data-ui-events-prize-code]');
            const value = root?.getAttribute('data-code-value') || '';
            if (!value) return;

            copyEventsText(value).then(() => {
                const original = copyButton.textContent;
                copyButton.textContent = 'Kopyalandı';
                copyButton.disabled = true;
                window.setTimeout(() => {
                    copyButton.textContent = original || 'Kopyala';
                    copyButton.disabled = false;
                }, 1100);
            }).catch(() => {
                eventsToast('Kod kopyalanamadı.', 'error');
            });
        });
    }

    function initEventsAjaxForms() {
        document.addEventListener('submit', function(event) {
            const form = event.target.closest('form');
            if (!shouldAjaxSubmit(form)) return;

            event.preventDefault();
            submitEventsAjaxForm(form, event.submitter || null);
        });
    }

    function formSnapshot(form) {
        const data = new FormData(form);
        const pairs = [];
        data.forEach((value, key) => {
            if (key === '_token') return;
            pairs.push(key + '=' + String(value));
        });
        return pairs.sort().join('&');
    }

    function updateDirtySavebar(form, isDirty) {
        const savebar = form.querySelector('.ui-events-settings-savebar, .ui-events-rule-savebar');
        if (!savebar) return;
        const message = savebar.querySelector('[data-ui-events-savebar-message]') || savebar.querySelector('span');
        if (!message) return;

        if (!savebar.dataset.eventsDefaultMessage) {
            savebar.dataset.eventsDefaultMessage = message.textContent.trim();
        }

        savebar.classList.toggle('ui-events-savebar-dirty', isDirty);
        message.textContent = isDirty
            ? (savebar.dataset.eventsDirtyMessage || 'Kaydedilmemiş değişiklikler var.')
            : savebar.dataset.eventsDefaultMessage;
    }

    function initEventsDirtyForms() {
        const trackedForms = Array.from(document.querySelectorAll('.ui-events-admin-page form')).filter(form => {
            return form.querySelector('input[name="_events_action"]') && !form.matches('.ui-events-inline-form');
        });

        trackedForms.forEach(form => {
            form.dataset.eventsInitialSnapshot = formSnapshot(form);
            updateDirtySavebar(form, false);

            const update = () => {
                const isDirty = formSnapshot(form) !== form.dataset.eventsInitialSnapshot;
                form.classList.toggle('is-dirty', isDirty);
                updateDirtySavebar(form, isDirty);
            };

            form.addEventListener('input', update);
            form.addEventListener('change', update);
            form.addEventListener('submit', () => {
                form.classList.remove('is-dirty');
                updateDirtySavebar(form, false);
            });
        });

        window.addEventListener('beforeunload', function(event) {
            if (!document.querySelector('.ui-events-admin-page form.is-dirty')) return;
            event.preventDefault();
            event.returnValue = '';
        });
    }

    // Loading State
    window.setLoading = function(element, isLoading) {
        if (isLoading) {
            element.classList.add('loading');
            element.disabled = true;
        } else {
            element.classList.remove('loading');
            element.disabled = false;
        }
    };

    function initEventsActionDropdowns() {
        document.addEventListener('click', function(event) {
            const btn = event.target.closest('.ui-events-action-dropdown-btn');
            if (btn) {
                event.preventDefault();
                event.stopPropagation();
                const dropdown = btn.closest('.ui-events-action-dropdown');
                const isOpen = dropdown.classList.contains('is-open');
                
                document.querySelectorAll('.ui-events-action-dropdown.is-open').forEach(d => {
                    if (d !== dropdown) d.classList.remove('is-open');
                });
                
                dropdown.classList.toggle('is-open', !isOpen);
                return;
            }
            
            const isDropdownMenuClick = event.target.closest('.ui-events-action-dropdown-menu');
            if (!isDropdownMenuClick) {
                document.querySelectorAll('.ui-events-action-dropdown.is-open').forEach(d => {
                    d.classList.remove('is-open');
                });
            }
        });
    }

    function initEventsAdminUi() {
        if (window.__eventsAdminUiInitialized) return;
        window.__eventsAdminUiInitialized = true;

        initTableSorting();
        initRowSelection();
        initExpandableRows();
        initInlineEditing();
        initEventsConfirmGuard();
        initTabs();
        initEventsAdminTabs();
        initSearch();
        initEventsDirtyForms();
        initEventsDrawForms();
        initEventsPageLocalUi(document);
        initEventsPrizeCodeControls();
        initEventsAjaxForms();
        initEventsWheelAjaxNavigation();
        initEventsActionDropdowns();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEventsAdminUi);
    } else {
        initEventsAdminUi();
    }

})();
