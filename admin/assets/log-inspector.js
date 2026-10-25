/* Shared log workspace. Enhances server-rendered records without duplicating forms or IDs. */
(function () {
    'use strict';

    const text = (node) => node ? node.textContent.replace(/\s+/g, ' ').trim() : '';
    function element(tag, className, value) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (value !== undefined) node.textContent = value;
        return node;
    }

    function enhanceNavigation() {
        const nav = document.querySelector('.logs-subtabs');
        if (!nav) return;
        const picker = element('label', 'logs-page-picker');
        picker.append(element('span', '', 'Günlük türü'));
        const select = element('select', 'ui-admin-form-select');
        select.setAttribute('aria-label', 'Günlük türü');
        nav.querySelectorAll('a').forEach((link) => {
            const option = new Option(link.title || text(link), link.href);
            option.selected = link.getAttribute('aria-current') === 'page';
            select.add(option);
        });
        select.addEventListener('change', () => { window.location.href = select.value; });
        picker.append(select);
        nav.after(picker);
        nav.classList.add('logs-nav-enhanced');
    }

    function enhanceFilters(form, formIndex) {
        if (form.dataset.logFiltersReady) return;
        form.dataset.logFiltersReady = '1';
        const labels = {
            q: 'Ara', cron_q: 'Ara', system_q: 'Ara', status: 'Durum', state: 'Durum',
            action_type: 'Eylem', target_type: 'Hedef türü', target_id: 'Hedef ID', actor_id: 'Yönetici ID',
            date_from: 'Başlangıç', date_to: 'Bitiş', group: 'Grup', event_type: 'Olay', ip: 'IP adresi',
            device: 'Cihaz', level: 'Seviye', channel: 'Kanal', source: 'Kaynak', driver: 'Sürücü',
            cron_status: 'Durum', cron_job: 'İş', system_target: 'Hedef'
        };
        const fields = [];
        Array.from(form.querySelectorAll('input:not([type="hidden"]), select')).forEach((control, index) => {
            let block = control;
            while (block.parentElement !== form) block = block.parentElement;
            if (block === control) {
                block = element('div');
                control.before(block);
                block.append(control);
            }
            block.classList.add('log-filter-field');
            if (!control.id) control.id = 'log-filter-' + formIndex + '-' + index;
            let label = block.querySelector('label');
            if (!label) {
                label = element('label', 'ui-admin-form-label', labels[control.name] || control.getAttribute('aria-label') || 'Filtre');
                block.prepend(label);
            }
            label.htmlFor = control.id;
            fields.push({ block, control });
        });
        if (!fields.length) return;
        const search = fields.find(({ control }) => ['q', 'cron_q', 'system_q'].includes(control.name));
        const primary = fields.find(({ control }) => ['status', 'state', 'cron_status', 'system_target', 'level', 'group'].includes(control.name))
            || fields.find(({ control }) => control.tagName === 'SELECT');
        const top = element('div', 'log-filter-main');
        const actions = element('div', 'log-filter-buttons');
        const buttons = Array.from(form.querySelectorAll('button[type="submit"], a'));
        form.closest('.logs-filter-toolbar')?.querySelectorAll('.logs-toolbar-actions > a').forEach((link) => buttons.push(link));
        buttons.forEach((button) => actions.append(button));
        if (search) {
            search.block.classList.add('log-filter-search');
            top.append(search.block);
        }
        if (primary && primary !== search) top.append(primary.block);
        top.append(actions);
        const extra = fields.filter((field) => field !== search && field !== primary);
        const hidden = Array.from(form.querySelectorAll('input[type="hidden"]'));
        form.replaceChildren(...hidden, top);
        if (extra.length) {
            const details = element('details', 'log-filter-more');
            const summary = element('summary', '', 'Diğer filtreler');
            const icon = element('i', 'bi bi-sliders');
            icon.setAttribute('aria-hidden', 'true');
            summary.prepend(icon);
            const active = extra.filter(({ control }) => control.value !== '' && control.value !== 'all');
            if (active.length) {
                summary.append(element('span', 'log-filter-count', String(active.length)));
                details.open = true;
            }
            const grid = element('div', 'log-filter-extra');
            extra.forEach(({ block }) => grid.append(block));
            details.append(summary, grid);
            form.append(details);
        }
        form.classList.add('log-filters-ready');
    }

    function field(label, content, wide) {
        const block = element('div', 'log-detail-field' + (wide ? ' log-detail-field-wide' : ''));
        block.append(element('dt', '', label));
        const value = element('dd');
        if (typeof content === 'string') value.textContent = content || '—';
        else value.append(...Array.from(content.childNodes));
        block.append(value);
        return block;
    }

    function tableRecords(table) {
        const headers = Array.from(table.querySelectorAll('thead th')).map(text);
        const profiles = {
            application: ['Mesaj', 'Ayrıntı', 'Kanal', 'Seviye'],
            email: ['Konu', 'Alıcı', 'Kaynak', 'Durum'],
            rate_limits: ['Tür', 'Hedef', 'Deneme', 'Durum'],
            system_notifications: ['Bildirim', 'Hedef', 'Bilgi', 'Hedef'],
            cron: ['Mesaj', 'Job', '#', 'Durum'],
            activity: ['Eylem', 'Hedef', 'Yönetici', 'Durum']
        }[table.dataset.logKind] || ['Eylem', 'Hedef', 'Yönetici', 'Durum'];
        return Array.from(table.tBodies).flatMap((body) => Array.from(body.rows)).map((row) => {
            const cells = Array.from(row.cells);
            const named = new Map(cells.map((cell, index) => [cell.dataset.label || headers[index], cell]));
            const primary = named.get(profiles[0]);
            const summary = named.get(profiles[1]);
            const badge = named.get(profiles[3])?.querySelector('.ui-admin-badge, .admin-badge');
            const notification = row.querySelector('[data-system-notification-detail-open]');
            const record = {
                title: text(primary?.querySelector('.ui-admin-log-message-title, strong') || primary) || 'Kayıt',
                summary: text(summary?.querySelector('.ui-admin-log-summary, strong, code') || summary),
                meta: text(named.get(profiles[2])),
                date: notification?.dataset.created || text(named.get('Tarih') || named.get('İlk / Son')),
                status: notification?.dataset.readLabel || text(badge),
                tone: badge?.className || '',
                checkbox: row.querySelector('td input[type="checkbox"]'),
                detail: element('dl', 'log-detail-grid'),
                attributes: Array.from(row.attributes).filter((attr) => attr.name.startsWith('data-'))
            };
            // Full notification content lives on the former detail trigger, not in its truncated preview.
            if (notification) {
                record.title = notification.dataset.title || record.title;
                record.summary = notification.dataset.message || record.summary;
                record.meta = notification.dataset.target || record.meta;
                record.detail.append(field('Kayıt ID', '#' + notification.dataset.id), field('Mesaj', notification.dataset.message || 'Mesaj yok', true));
                record.detail.append(field('Olay anahtarı', notification.dataset.eventKey || '—'));
                notification.remove();
            }
            const key = row.querySelector('.rate-limit-key strong[title]')?.title;
            if (key) record.detail.append(field('Anahtar', key, true));
            record.checkbox?.remove();
            cells.forEach((cell, index) => {
                const label = cell.dataset.label || headers[index] || 'Ayrıntı';
                if (label === 'Seç' || (notification && label === 'Bildirim') || (!text(cell) && !cell.querySelector('button, a, input'))) return;
                const wide = ['Ayrıntı', 'Detay', 'Teknik', 'Mesaj', 'Konu', 'Bildirim', 'İşlem'].includes(label);
                record.detail.append(field(label, cell, wide));
            });
            return record;
        });
    }

    function activityRecords(feed) {
        return Array.from(feed.querySelectorAll('.user-activity-item')).map((item) => {
            const badge = item.querySelector('.ui-admin-badge');
            const detail = element('dl', 'log-detail-grid');
            item.querySelectorAll('.user-activity-detail-grid > span').forEach((entry) => {
                const value = entry.querySelector('strong');
                if (value) detail.append(field(text(entry.querySelector('b')), value, text(entry.querySelector('b')) === 'İstek'));
            });
            const json = item.querySelector('.user-activity-json');
            if (json) {
                const block = element('div');
                block.append(json);
                detail.append(field('Teknik bağlam', block, true));
            }
            return {
                title: text(item.querySelector('.user-activity-action')),
                summary: text(item.querySelector('.user-activity-row-meta')),
                meta: text(item.querySelector('.user-activity-headline > strong')),
                date: text(item.querySelector('time')),
                status: text(badge), tone: badge?.className || '', detail, attributes: []
            };
        });
    }

    function buildInspector(host, records, selectAll, index) {
        if (!records.length) return;
        const root = element('div', 'log-inspector');
        root.dataset.logInspector = '';
        const listPane = element('div', 'log-inspector-list-pane');
        const bar = element('div', 'log-inspector-list-bar');
        bar.append(element('span', '', 'KAYITLAR'), element('span', 'log-inspector-hint', 'Ayrıntı için bir kayıt seçin'));
        if (selectAll) {
            const label = element('label', 'log-inspector-check-all');
            label.append(selectAll, document.createTextNode(' Tümünü seç'));
            bar.prepend(label);
        }
        const list = element('div', 'log-inspector-list');
        list.setAttribute('aria-label', 'Günlük kayıtları');
        const aside = element('aside', 'log-inspector-detail');
        aside.dataset.logDetail = '';
        aside.id = 'log-inspector-detail-' + index;
        aside.hidden = true;
        aside.tabIndex = -1;
        aside.setAttribute('aria-labelledby', aside.id + '-title');
        const heading = element('div', 'log-inspector-detail-head');
        const headingCopy = element('div');
        headingCopy.append(element('span', 'log-detail-eyebrow', 'KAYIT AYRINTISI'));
        const title = element('h3');
        title.id = aside.id + '-title';
        headingCopy.append(title);
        const close = element('button', 'ui-admin-btn ui-admin-btn-ghost ui-admin-btn-xs log-inspector-close');
        close.type = 'button';
        close.dataset.logClose = '';
        close.setAttribute('aria-label', 'Ayrıntıyı kapat');
        close.title = 'Ayrıntıyı kapat (Esc)';
        close.append(element('i', 'bi bi-x-lg'));
        heading.append(headingCopy, close);
        const content = element('div', 'log-inspector-detail-body');
        aside.append(heading, content);
        let selected = null;
        const entries = [];

        function clearSelection(returnFocus) {
            if (!selected) return;
            const previous = selected;
            previous.row.classList.remove('is-selected');
            previous.button.setAttribute('aria-expanded', 'false');
            content.replaceChildren();
            aside.hidden = true;
            root.classList.remove('has-selection');
            selected = null;
            if (returnFocus) {
                previous.button.focus({ preventScroll: true });
                if (window.matchMedia('(max-width: 700px)').matches) previous.button.scrollIntoView({ block: 'nearest' });
            }
        }

        records.forEach((record) => {
            const row = element('article', 'log-inspector-record');
            record.attributes.forEach((attr) => row.setAttribute(attr.name, attr.value));
            if (record.checkbox) {
                const label = element('label', 'log-record-check');
                label.append(record.checkbox);
                row.append(label);
            }
            const button = element('button', 'log-record-select');
            button.type = 'button';
            button.dataset.logSelect = '';
            button.setAttribute('aria-controls', aside.id);
            button.setAttribute('aria-expanded', 'false');
            const main = element('span', 'log-record-copy');
            main.append(element('strong', 'log-record-title', record.title));
            if (record.summary) main.append(element('span', 'log-record-summary', record.summary));
            if (record.meta) main.append(element('span', 'log-record-meta', record.meta));
            const info = element('span', 'log-record-info');
            if (record.date) info.append(element('time', '', record.date));
            if (record.status) {
                const tone = /danger|error|failed/.test(record.tone) ? 'danger' : /warning/.test(record.tone) ? 'warning' : /success/.test(record.tone) ? 'success' : 'muted';
                info.append(element('span', 'ui-admin-badge ui-admin-badge-' + tone + ' log-record-status', record.status));
            }
            const arrow = element('i', 'bi bi-chevron-right log-record-arrow');
            arrow.setAttribute('aria-hidden', 'true');
            button.append(main, info, arrow);
            row.append(button);
            list.append(row);
            const entry = { row, button, record };
            entries.push(entry);
            button.addEventListener('click', () => {
                clearSelection(false);
                selected = entry;
                row.classList.add('is-selected');
                button.setAttribute('aria-expanded', 'true');
                title.textContent = record.title;
                content.replaceChildren(record.detail);
                aside.hidden = false;
                root.classList.add('has-selection');
                aside.focus({ preventScroll: true });
                if (window.matchMedia('(max-width: 700px)').matches) aside.scrollIntoView({ block: 'start', behavior: 'auto' });
            });
        });
        // Native buttons provide Enter/Space activation; arrows move through the record list.
        list.addEventListener('keydown', (event) => {
            const current = entries.findIndex(({ button }) => button === event.target);
            if (current < 0 || !['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? entries.length - 1
                : Math.max(0, Math.min(entries.length - 1, current + (event.key === 'ArrowDown' ? 1 : -1)));
            entries[next].button.focus();
        });
        close.addEventListener('click', () => clearSelection(true));
        root.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && selected) {
                event.stopPropagation();
                clearSelection(true);
            }
        });
        // Keep all checkboxes in the original bulk form, even while no detail is selected.
        listPane.append(bar, list);
        root.append(listPane, aside);
        host.replaceChildren(root);
        host.classList.add('log-inspector-host');
    }

    function init() {
        enhanceNavigation();
        document.querySelectorAll('.logs-page .admin-log-filter-form, .user-activity-page .admin-log-filter-form').forEach(enhanceFilters);
        document.querySelectorAll('.admin-log-standard-table').forEach((table, index) => {
            const host = table.closest('.admin-log-standard-wrap');
            if (!host) return;
            const selectAll = table.querySelector('thead input[type="checkbox"]');
            if (selectAll) selectAll.remove();
            buildInspector(host, tableRecords(table), selectAll, index);
        });
        document.querySelectorAll('.user-activity-feed').forEach((feed, index) => buildInspector(feed, activityRecords(feed), null, 100 + index));
        document.querySelectorAll('.logs-page, .user-activity-page').forEach((page) => {
            const hero = page.querySelector('.logs-page-hero');
            const clear = page.querySelector('[data-clear-logs-open]');
            if (hero && clear) {
                let actions = hero.querySelector('.admin-page-heading__actions');
                if (!actions) {
                    actions = element('div', 'ui-admin-page-hero-actions admin-ui-page-hero__actions admin-ui-action-row admin-page-heading__actions logs-heading-actions');
                    hero.append(actions);
                }
                clear.className = 'ui-admin-btn ui-admin-btn-hero ui-admin-btn-danger-outline';
                clear.replaceChildren(element('i', 'bi bi-trash'), document.createTextNode(' Günlüğü Temizle'));
                actions.append(clear);
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
