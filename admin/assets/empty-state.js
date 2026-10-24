(function () {
    'use strict';

    function classes(value) {
        return String(value || '').replace(/[^a-zA-Z0-9_\-\s]/g, '').trim();
    }

    function attributes(element, values) {
        Object.keys(values || {}).forEach(function (name) {
            var value = values[name];
            if (!/^(?:data-[\w-]+|aria-[\w-]+|id|role|hidden|disabled|form|name|value)$/.test(name) || value === false || value == null) return;
            element.setAttribute(name, value === true ? '' : String(value));
        });
    }

    function icon(name) {
        var element = document.createElement('i');
        element.className = 'bi ' + (classes(name) || 'bi-inbox');
        element.setAttribute('aria-hidden', 'true');
        return element;
    }

    function create(options) {
        options = options || {};
        var template = document.getElementById('admin-empty-state-template');
        if (!template || !template.content.firstElementChild) throw new Error('Admin empty-state template is unavailable.');
        var state = template.content.firstElementChild.cloneNode(true);
        state.className = 'admin-empty' + (options.class ? ' ' + classes(options.class) : '');
        attributes(state, options.attrs);
        state.dataset.emptySize = ['regular', 'compact', 'inline'].includes(options.size) ? options.size : 'regular';
        state.dataset.emptyTone = ['neutral', 'info', 'success', 'warning', 'danger', 'muted'].includes(options.tone) ? options.tone : 'neutral';
        state.querySelector('.admin-empty__icon').replaceChildren(icon(options.icon));
        var title = state.querySelector('[data-empty-title]');
        attributes(title, options.title_attrs);
        title.textContent = options.title == null ? 'Kayıt bulunamadı' : String(options.title);
        title.hidden = title.textContent === '';
        var description = state.querySelector('[data-empty-description]');
        attributes(description, options.description_attrs);
        description.textContent = options.description == null ? 'Yeni kayıtlar eklendiğinde burada görünecek.' : String(options.description);
        description.hidden = description.textContent === '';

        var meta = state.querySelector('[data-empty-meta]');
        (Array.isArray(options.meta) ? options.meta : []).forEach(function (item) {
            if (!item || !item.label) return;
            var label = document.createElement('span');
            if (item.icon) label.append(icon(item.icon));
            label.append(document.createTextNode(String(item.label)));
            meta.append(label);
        });
        meta.hidden = meta.children.length === 0;

        var actions = state.querySelector('[data-empty-actions]');
        (Array.isArray(options.actions) ? options.actions : []).forEach(function (action) {
            if (!action || !action.label) return;
            var button = document.createElement(action.href ? 'a' : 'button');
            button.className = 'ui-admin-btn ui-admin-btn-outline' + (action.class ? ' ' + classes(action.class) : '');
            if (action.href) button.setAttribute('href', String(action.href));
            else button.type = ['button', 'submit', 'reset'].includes(action.type) ? action.type : 'button';
            attributes(button, action.attrs);
            if (action.icon) button.append(icon(action.icon));
            button.append(document.createTextNode(String(action.label)));
            actions.append(button);
        });
        actions.hidden = actions.children.length === 0;
        return state;
    }

    function render(options) {
        return create(options).outerHTML;
    }

    function renderTableRow(columns, options, rowOptions) {
        options = Object.assign({ size: 'compact' }, options || {});
        rowOptions = rowOptions || {};
        var row = document.createElement('tr');
        row.className = 'admin-empty-row' + (rowOptions.class ? ' ' + classes(rowOptions.class) : '');
        row.setAttribute('data-admin-empty-row', '');
        attributes(row, rowOptions.attrs);
        var cell = document.createElement('td');
        cell.className = 'admin-empty-cell';
        cell.colSpan = Math.max(1, Math.floor(Number(columns) || 1));
        cell.append(create(options));
        row.append(cell);
        return row.outerHTML;
    }

    function mount(container, options) {
        var state = create(options);
        container.replaceChildren(state);
        return state;
    }

    function enhance(root) {
        var scope = root || document;
        var tables = new Set(scope.querySelectorAll('table[data-admin-empty-table]'));
        scope.querySelectorAll('[data-admin-empty-state]').forEach(function (state) {
            var cell = state.closest('td[colspan]');
            if (!cell || state.parentElement !== cell) return;
            cell.classList.add('admin-empty-cell');
            cell.removeAttribute('data-label');
            var row = cell.parentElement;
            if (row && row.tagName === 'TR') {
                row.classList.add('admin-empty-row');
                row.setAttribute('data-admin-empty-row', '');
            }
            if (state.dataset.emptySize === 'regular') state.dataset.emptySize = 'compact';
            tables.add(cell.closest('table'));
        });
        tables.forEach(function (table) {
            if (!table) return;
            var hasData = Array.from(table.tBodies).some(function (body) {
                return Array.from(body.rows).some(function (row) {
                    return !row.classList.contains('admin-empty-row') && !row.hidden && getComputedStyle(row).display !== 'none';
                });
            });
            table.toggleAttribute('data-admin-empty-table', !hasData);
        });
    }

    window.adminEmptyState = Object.freeze({ create: create, render: render, renderTableRow: renderTableRow, mount: mount, enhance: enhance });
})();
