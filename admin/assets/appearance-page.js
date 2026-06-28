// Toggle checkbox from preview badges
function toggleCheckbox(key) {
    const checkbox = document.querySelector('input[name="' + key + '"]');
    if (checkbox) {
        checkbox.checked = !checkbox.checked;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

// Sidebar sub-tab switching
document.querySelectorAll('.sidebar-subtab-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var target = this.getAttribute('data-subtab');
        var container = this.closest('.admin-card');
        container.querySelectorAll('.sidebar-subtab-btn').forEach(function(b) {
            b.classList.remove('active');
        });
        this.classList.add('active');
        container.querySelectorAll('.sidebar-subtab-panel').forEach(function(p) {
            p.classList.remove('is-active');
        });
        var panel = container.querySelector('#' + target);
        if (panel) panel.classList.add('is-active');
    });
});

document.getElementById('appearanceForm').addEventListener('submit', function(){
    var active = document.querySelector('.settings-tabs .nav-link.active');
    if(active) document.getElementById('activeTabInput').value = active.getAttribute('href').replace('#','');
});

// Live color hex value update
document.querySelectorAll('[data-color-field]').forEach(function(field) {
    var input = field.querySelector('[data-color-input]');
    var label = field.querySelector('[data-color-value]');
    if (input && label) {
        input.addEventListener('input', function() {
            label.textContent = this.value.toUpperCase();
        });
    }
});

(function() {
    var root = document.querySelector('[data-sidebar-builder]');
    var hidden = document.getElementById('sidebar_builder_config');
    var initialEl = document.getElementById('sidebarBuilderInitial');
    var catalogEl = document.getElementById('sidebarBuilderCatalog');
    var form = document.getElementById('appearanceForm');
    if (!root || !hidden || !initialEl || !catalogEl || !form) {
        return;
    }

    var state = safeJson(initialEl.textContent, { version: 1, global: {}, areas: { left: [], right: [] } });
    var catalog = safeJson(catalogEl.textContent, {});
    var selected = null;
    var dragData = null;

    function safeJson(text, fallback) {
        try {
            var parsed = JSON.parse(text || '');
            return parsed && typeof parsed === 'object' ? parsed : fallback;
        } catch (error) {
            return fallback;
        }
    }

    function uid(type) {
        return 'sbw_' + type + '_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    }

    function ensureState() {
        state.version = 1;
        state.global = Object.assign({
            enabled: true,
            left_width: 260,
            right_width: 300,
            sticky: true,
            mobile_behavior: 'offcanvas',
            desktop_layout: 'both'
        }, state.global || {});
        state.areas = state.areas || {};
        state.areas.left = Array.isArray(state.areas.left) ? state.areas.left : [];
        state.areas.right = Array.isArray(state.areas.right) ? state.areas.right : [];
    }

    function catalogKeys() {
        return Object.keys(catalog).sort(function(a, b) {
            var ao = catalog[a].default_area === 'left' ? 0 : 1;
            var bo = catalog[b].default_area === 'left' ? 0 : 1;
            return ao === bo ? String(catalog[a].label).localeCompare(String(catalog[b].label), 'tr') : ao - bo;
        });
    }

    function pageLabel(value) {
        return ({ all: 'Tüm sayfalar', home: 'Anasayfa', topic: 'Konu', category: 'Kategori', search: 'Arama' })[value] || value;
    }

    function deviceLabel(value) {
        return ({ desktop: 'Desktop', tablet: 'Tablet', mobile: 'Mobil' })[value] || value;
    }

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
        });
    }

    function findWidget(id) {
        for (var area of ['left', 'right']) {
            var index = state.areas[area].findIndex(function(widget) { return widget.id === id; });
            if (index >= 0) {
                return { area: area, index: index, widget: state.areas[area][index] };
            }
        }
        return null;
    }

    function createWidget(type) {
        var def = catalog[type] || {};
        return {
            id: uid(type),
            type: type,
            title: def.default_title || def.label || type,
            enabled: true,
            pages: ['all'],
            devices: ['desktop', 'tablet'],
            settings: Object.assign({}, def.settings || {})
        };
    }

    function renderLibrary() {
        var list = root.querySelector('[data-sidebar-library]');
        var search = (root.querySelector('[data-sidebar-search]')?.value || '').toLocaleLowerCase('tr');
        if (!list) return;
        list.innerHTML = catalogKeys().filter(function(type) {
            var def = catalog[type] || {};
            return !search || String(def.label + ' ' + def.description).toLocaleLowerCase('tr').includes(search);
        }).map(function(type) {
            var def = catalog[type] || {};
            return '<div class="sidebar-builder-library-item" draggable="true" data-sidebar-library-item="' + esc(type) + '">' +
                '<span class="sidebar-builder-library-icon"><i class="bi ' + esc(def.icon || 'bi-puzzle') + '"></i></span>' +
                '<span><strong>' + esc(def.label || type) + '</strong><small>' + esc(def.description || '') + '</small></span>' +
                '<button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-sidebar-add="' + esc(type) + '">Ekle</button>' +
            '</div>';
        }).join('');
    }

    function renderColumns() {
        ['left', 'right'].forEach(function(area) {
            var zone = root.querySelector('[data-sidebar-dropzone="' + area + '"]');
            var count = root.querySelector('[data-sidebar-count="' + area + '"]');
            if (!zone) return;
            var widgets = state.areas[area] || [];
            if (count) count.textContent = widgets.length + ' widget';
            zone.innerHTML = widgets.length ? widgets.map(function(widget, index) {
                var def = catalog[widget.type] || {};
                var isSelected = selected === widget.id ? ' is-selected' : '';
                var enabled = widget.enabled !== false;
                var widgetSettings = widget.settings || {};
                var widgetIcon = widgetSettings.custom_icon || def.icon || 'bi-puzzle';
                return '<article class="sidebar-builder-widget' + isSelected + (enabled ? '' : ' is-disabled') + '" draggable="true" data-sidebar-widget="' + esc(widget.id) + '" data-area="' + area + '">' +
                    '<div class="sidebar-builder-widget-main">' +
                        '<span class="sidebar-builder-handle" aria-hidden="true">::</span>' +
                        '<span class="sidebar-builder-widget-icon"><i class="bi ' + esc(widgetIcon) + '"></i></span>' +
                        '<span class="sidebar-builder-widget-copy"><strong>' + esc(widget.title || def.label || widget.type) + '</strong><small>' + esc(def.label || widget.type) + ' · ' + esc((widget.pages || ['all']).map(pageLabel).join(', ')) + '</small></span>' +
                    '</div>' +
                    '<div class="sidebar-builder-widget-actions">' +
                        '<button type="button" title="Yukari tasi" data-sidebar-move="up" data-id="' + esc(widget.id) + '"><i class="bi bi-arrow-up"></i></button>' +
                        '<button type="button" title="Asagi tasi" data-sidebar-move="down" data-id="' + esc(widget.id) + '"><i class="bi bi-arrow-down"></i></button>' +
                        '<button type="button" title="' + (enabled ? 'Kapat' : 'Ac') + '" data-sidebar-toggle="' + esc(widget.id) + '"><i class="bi ' + (enabled ? 'bi-toggle-on' : 'bi-toggle-off') + '"></i></button>' +
                        '<button type="button" title="Kopyala" data-sidebar-duplicate="' + esc(widget.id) + '"><i class="bi bi-files"></i></button>' +
                        '<button type="button" title="Sil" data-sidebar-remove="' + esc(widget.id) + '"><i class="bi bi-trash"></i></button>' +
                    '</div>' +
                '</article>';
            }).join('') : '<div class="sidebar-builder-drop-empty ui-empty">Widgetleri buraya surukle</div>';
        });
    }

    function renderInspector() {
        var body = root.querySelector('[data-sidebar-inspector]');
        var label = root.querySelector('[data-sidebar-selected-label]');
        if (!body) return;
        var found = selected ? findWidget(selected) : null;
        if (!found) {
            if (label) label.textContent = 'Secim yok';
            body.innerHTML = '<div class="sidebar-builder-empty ui-empty"><i class="bi bi-cursor"></i><strong>Bir widget sec</strong><span>Orta alandaki kartlardan birini secince ayarlari burada acilir.</span></div>';
            return;
        }
        var widget = found.widget;
        var def = catalog[widget.type] || {};
        if (label) label.textContent = def.label || widget.type;
        var settings = widget.settings || {};
        body.innerHTML = '<div class="sidebar-builder-selected-card ui-card">' +
                '<strong>' + esc(def.label || widget.type) + '</strong><span>' + esc(def.description || '') + '</span>' +
            '</div>' +
            '<label class="ui-admin-form-label">Baslik<input class="ui-admin-form-control" data-inspector-field="title" value="' + esc(widget.title || '') + '"></label>' +
            '<div class="sidebar-builder-check-grid"><strong>Görüneceği sayfalar</strong>' + checkboxGroup('pages', widget.pages || ['all'], ['all', 'home', 'category', 'topic', 'search'], pageLabel) + '</div>' +
            '<div class="sidebar-builder-check-grid"><strong>Cihazlar</strong>' + checkboxGroup('devices', widget.devices || ['desktop', 'tablet'], ['desktop', 'tablet', 'mobile'], deviceLabel) + '</div>' +
            settingsFields(widget.type, settings) +
            '<label class="ui-admin-switch sidebar-builder-inspector-switch"><input type="checkbox" data-inspector-field="enabled" ' + (widget.enabled !== false ? 'checked' : '') + '><span class="ui-admin-switch-label">Widget aktif</span></label>';
    }

    function checkboxGroup(field, values, allowed, labeler) {
        values = Array.isArray(values) ? values : [];
        return '<div class="sidebar-builder-checkboxes">' + allowed.map(function(value) {
            return '<label><input type="checkbox" data-inspector-list="' + field + '" value="' + esc(value) + '" ' + (values.includes(value) ? 'checked' : '') + '><span>' + esc(labeler(value)) + '</span></label>';
        }).join('') + '</div>';
    }

    function settingsFields(type, settings) {
        var html = '<div class="sidebar-builder-setting-group"><strong>Gorunum</strong>' +
            '<label class="ui-admin-form-label">Stil<select class="ui-admin-form-select" data-setting-field="style_variant">' +
                option('card', 'Kart', settings.style_variant || 'card') +
                option('minimal', 'Minimal', settings.style_variant || 'card') +
                option('list', 'Liste', settings.style_variant || 'card') +
                option('highlight', 'Vurgulu', settings.style_variant || 'card') +
                option('compact', 'Kompakt', settings.style_variant || 'card') +
            '</select></label>' +
            '<label class="ui-admin-form-label">Ikon class<input class="ui-admin-form-control" data-setting-field="custom_icon" placeholder="bi-stars" value="' + esc(settings.custom_icon || '') + '"></label>' +
            '<label class="ui-admin-form-label">Vurgu rengi<input class="ui-admin-form-control" type="color" data-setting-field="accent_color" value="' + esc(colorValue(settings.accent_color || '#8b1538')) + '"></label>' +
            '<label class="ui-admin-switch sidebar-builder-inspector-switch"><input type="checkbox" data-setting-field="hide_title" ' + (settings.hide_title === true ? 'checked' : '') + '><span class="ui-admin-switch-label">Widget basligini gizle</span></label>' +
        '</div>';
        if (['category_tree', 'recent_comments', 'popular_topics', 'tag_cloud', 'leaderboard', 'editor_picks', 'category_showcase', 'latest_downloads', 'trending_tags', 'related_content'].includes(type)) {
            html += '<label class="ui-admin-form-label">Limit<input class="ui-admin-form-control" type="number" min="1" max="30" data-setting-field="limit" value="' + esc(settings.limit || 5) + '"></label>';
        }
        if (type === 'popular_topics') {
            html += '<label class="ui-admin-form-label">Siralama<select class="ui-admin-form-select" data-setting-field="sort">' +
                option('downloads', 'Indirme', settings.sort) + option('views', 'Goruntulenme', settings.sort) + option('date', 'Tarih', settings.sort) +
            '</select></label>';
        }
        if (type === 'editor_picks') {
            html += '<label class="ui-admin-form-label">Konu ID listesi<textarea class="ui-admin-form-control sidebar-builder-code" rows="4" data-setting-field="topic_ids" placeholder="12, 18, 24">' + esc(settings.topic_ids || '') + '</textarea></label>';
        }
        if (type === 'category_showcase') {
            html += '<label class="ui-admin-form-label">Kategori slug listesi<textarea class="ui-admin-form-control sidebar-builder-code" rows="4" data-setting-field="category_slugs" placeholder="ets2, ats, fs-25">' + esc(settings.category_slugs || '') + '</textarea></label>';
        }
        if (type === 'announcement_band') {
            html += '<label class="ui-admin-form-label">Duyuru metni<textarea class="ui-admin-form-control" rows="4" data-setting-field="message">' + esc(settings.message || '') + '</textarea></label>';
            html += '<label class="ui-admin-form-label">Buton metni<input class="ui-admin-form-control" data-setting-field="button_label" value="' + esc(settings.button_label || '') + '"></label>';
            html += '<label class="ui-admin-form-label">Buton URL<input class="ui-admin-form-control" data-setting-field="button_url" value="' + esc(settings.button_url || '#') + '"></label>';
            html += '<label class="ui-admin-form-label">Ton<select class="ui-admin-form-select" data-setting-field="tone">' +
                option('primary', 'Ana renk', settings.tone) + option('success', 'Basari', settings.tone) + option('warning', 'Uyari', settings.tone) + option('danger', 'Onemli', settings.tone) + option('info', 'Bilgi', settings.tone) +
            '</select></label>';
        }
        if (type === 'user_action') {
            html += '<label class="ui-admin-form-label">Misafir basligi<input class="ui-admin-form-control" data-setting-field="guest_title" value="' + esc(settings.guest_title || '') + '"></label>';
            html += '<label class="ui-admin-form-label">Misafir metni<textarea class="ui-admin-form-control" rows="3" data-setting-field="guest_text">' + esc(settings.guest_text || '') + '</textarea></label>';
            html += '<label class="ui-admin-form-label">Uye basligi<input class="ui-admin-form-control" data-setting-field="member_title" value="' + esc(settings.member_title || '') + '"></label>';
            html += '<label class="ui-admin-form-label">Uye metni<textarea class="ui-admin-form-control" rows="3" data-setting-field="member_text">' + esc(settings.member_text || '') + '</textarea></label>';
        }
        if (type === 'sponsored_content') {
            html += '<label class="ui-admin-form-label">Sponsor etiketi<input class="ui-admin-form-control" data-setting-field="sponsor_label" value="' + esc(settings.sponsor_label || '') + '"></label>';
            html += '<label class="ui-admin-form-label">Baslik<input class="ui-admin-form-control" data-setting-field="headline" value="' + esc(settings.headline || '') + '"></label>';
            html += '<label class="ui-admin-form-label">Aciklama<textarea class="ui-admin-form-control" rows="4" data-setting-field="description">' + esc(settings.description || '') + '</textarea></label>';
            html += '<label class="ui-admin-form-label">Gorsel URL<input class="ui-admin-form-control" data-setting-field="image_url" value="' + esc(settings.image_url || '') + '"></label>';
            html += '<label class="ui-admin-form-label">Hedef URL<input class="ui-admin-form-control" data-setting-field="target_url" value="' + esc(settings.target_url || '#') + '"></label>';
            html += '<label class="ui-admin-form-label">Buton metni<input class="ui-admin-form-control" data-setting-field="button_label" value="' + esc(settings.button_label || '') + '"></label>';
        }
        if (type === 'poll_cta') {
            html += '<label class="ui-admin-form-label">Soru<input class="ui-admin-form-control" data-setting-field="question" value="' + esc(settings.question || '') + '"></label>';
            html += '<label class="ui-admin-form-label">Secenekler<textarea class="ui-admin-form-control" rows="4" data-setting-field="options">' + esc(settings.options || '') + '</textarea></label>';
            html += '<label class="ui-admin-form-label">Buton metni<input class="ui-admin-form-control" data-setting-field="button_label" value="' + esc(settings.button_label || '') + '"></label>';
            html += '<label class="ui-admin-form-label">Buton URL<input class="ui-admin-form-control" data-setting-field="button_url" value="' + esc(settings.button_url || '#') + '"></label>';
        }
        if (type === 'custom_html') {
            html += '<label class="ui-admin-form-label">HTML<textarea class="ui-admin-form-control sidebar-builder-code" rows="8" data-setting-field="html">' + esc(settings.html || '') + '</textarea></label>';
        }
        if (type === 'category_tree') {
            html += '<label class="ui-admin-switch sidebar-builder-inspector-switch"><input type="checkbox" data-setting-field="show_counts" ' + (settings.show_counts !== false ? 'checked' : '') + '><span class="ui-admin-switch-label">Sayaclari goster</span></label>';
        }
        return html;
    }

    function colorValue(value) {
        value = String(value || '').trim();
        return /^#[0-9a-fA-F]{6}$/.test(value) ? value : '#8b1538';
    }

    function option(value, label, selectedValue) {
        return '<option value="' + esc(value) + '"' + (String(selectedValue || '') === value ? ' selected' : '') + '>' + esc(label) + '</option>';
    }

    function updateSettingField(target) {
        var found = selected ? findWidget(selected) : null;
        if (!found) return false;
        var setting = target.closest('[data-setting-field]');
        if (!setting) return false;
        var settingName = setting.getAttribute('data-setting-field');
        found.widget.settings = found.widget.settings || {};
        found.widget.settings[settingName] = setting.type === 'checkbox' ? setting.checked : setting.value;
        renderColumns();
        serialize();
        return true;
    }

    function renderMetrics() {
        var active = state.areas.left.concat(state.areas.right).filter(function(widget) { return widget.enabled !== false; }).length;
        var pages = new Set();
        state.areas.left.concat(state.areas.right).forEach(function(widget) {
            (widget.pages || []).forEach(function(page) { pages.add(page); });
        });
        setMetric('catalog', catalogKeys().length);
        setMetric('active', active);
        setMetric('conditions', pages.size);
    }

    function setMetric(key, value) {
        var el = root.querySelector('[data-sidebar-metric="' + key + '"]');
        if (el) el.textContent = value;
    }

    function serialize() {
        ensureState();
        hidden.value = JSON.stringify(state, null, 2);
    }

    function renderAll() {
        ensureState();
        renderLibrary();
        renderColumns();
        renderInspector();
        renderMetrics();
        serialize();
    }

    function addWidget(type, area) {
        if (!catalog[type]) return;
        area = area || catalog[type].default_area || 'right';
        state.areas[area].push(createWidget(type));
        selected = state.areas[area][state.areas[area].length - 1].id;
        renderAll();
    }

    function moveWidget(id, direction) {
        var found = findWidget(id);
        if (!found) return;
        var list = state.areas[found.area];
        var next = direction === 'up' ? found.index - 1 : found.index + 1;
        if (next < 0 || next >= list.length) return;
        var tmp = list[found.index];
        list[found.index] = list[next];
        list[next] = tmp;
        renderAll();
    }

    function removeWidget(id) {
        var found = findWidget(id);
        if (!found) return;
        state.areas[found.area].splice(found.index, 1);
        selected = null;
        renderAll();
    }

    function duplicateWidget(id) {
        var found = findWidget(id);
        if (!found) return;
        var copy = JSON.parse(JSON.stringify(found.widget));
        copy.id = uid(copy.type);
        copy.title = copy.title + ' kopya';
        state.areas[found.area].splice(found.index + 1, 0, copy);
        selected = copy.id;
        renderAll();
    }

    function transferWidget(id, targetArea, beforeId) {
        var found = findWidget(id);
        if (!found || !state.areas[targetArea]) return;
        var widget = found.widget;
        state.areas[found.area].splice(found.index, 1);
        var target = state.areas[targetArea];
        var beforeIndex = beforeId ? target.findIndex(function(item) { return item.id === beforeId; }) : -1;
        if (beforeIndex >= 0) {
            target.splice(beforeIndex, 0, widget);
        } else {
            target.push(widget);
        }
        selected = id;
        renderAll();
    }

    root.addEventListener('click', function(event) {
        var add = event.target.closest('[data-sidebar-add]');
        if (add) {
            addWidget(add.getAttribute('data-sidebar-add'));
            return;
        }
        var card = event.target.closest('[data-sidebar-widget]');
        if (card && !event.target.closest('.sidebar-builder-widget-actions')) {
            selected = card.getAttribute('data-sidebar-widget');
            renderAll();
            return;
        }
        var move = event.target.closest('[data-sidebar-move]');
        if (move) {
            moveWidget(move.getAttribute('data-id'), move.getAttribute('data-sidebar-move'));
            return;
        }
        var toggle = event.target.closest('[data-sidebar-toggle]');
        if (toggle) {
            var found = findWidget(toggle.getAttribute('data-sidebar-toggle'));
            if (found) {
                found.widget.enabled = found.widget.enabled === false;
                selected = found.widget.id;
                renderAll();
            }
            return;
        }
        var dup = event.target.closest('[data-sidebar-duplicate]');
        if (dup) {
            duplicateWidget(dup.getAttribute('data-sidebar-duplicate'));
            return;
        }
        var remove = event.target.closest('[data-sidebar-remove]');
        if (remove) {
            removeWidget(remove.getAttribute('data-sidebar-remove'));
        }
    });

    root.addEventListener('input', function(event) {
        var search = event.target.closest('[data-sidebar-search]');
        if (search) {
            renderLibrary();
            return;
        }
        var found = selected ? findWidget(selected) : null;
        if (!found) return;
        var field = event.target.closest('[data-inspector-field]');
        if (field) {
            var fieldName = field.getAttribute('data-inspector-field');
            found.widget[fieldName] = field.type === 'checkbox' ? field.checked : field.value;
            renderColumns();
            renderMetrics();
            serialize();
            return;
        }
        updateSettingField(event.target);
    });

    root.addEventListener('change', function(event) {
        var found = selected ? findWidget(selected) : null;
        if (!found) return;
        if (updateSettingField(event.target)) {
            return;
        }
        var listInput = event.target.closest('[data-inspector-list]');
        if (listInput) {
            var field = listInput.getAttribute('data-inspector-list');
            var checked = Array.from(root.querySelectorAll('[data-inspector-list="' + field + '"]:checked')).map(function(input) { return input.value; });
            found.widget[field] = checked.length ? checked : [field === 'pages' ? 'all' : 'desktop'];
            renderColumns();
            renderMetrics();
            serialize();
        }
    });

    root.addEventListener('dragstart', function(event) {
        var lib = event.target.closest('[data-sidebar-library-item]');
        var card = event.target.closest('[data-sidebar-widget]');
        if (lib) {
            dragData = { kind: 'new', type: lib.getAttribute('data-sidebar-library-item') };
        } else if (card) {
            dragData = { kind: 'move', id: card.getAttribute('data-sidebar-widget') };
        }
        if (dragData && event.dataTransfer) {
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', JSON.stringify(dragData));
        }
    });

    root.addEventListener('dragover', function(event) {
        if (event.target.closest('[data-sidebar-dropzone]') || event.target.closest('[data-sidebar-widget]')) {
            event.preventDefault();
        }
    });

    root.addEventListener('drop', function(event) {
        var zone = event.target.closest('[data-sidebar-dropzone]');
        var beforeCard = event.target.closest('[data-sidebar-widget]');
        if (!zone && beforeCard) {
            zone = beforeCard.closest('[data-sidebar-dropzone]');
        }
        if (!zone) return;
        event.preventDefault();
        var area = zone.getAttribute('data-sidebar-dropzone');
        var beforeId = beforeCard ? beforeCard.getAttribute('data-sidebar-widget') : '';
        var data = dragData;
        try {
            data = event.dataTransfer ? JSON.parse(event.dataTransfer.getData('text/plain') || 'null') || data : data;
        } catch (error) {
        }
        if (!data) return;
        if (data.kind === 'new') {
            addWidget(data.type, area);
        } else if (data.kind === 'move') {
            transferWidget(data.id, area, beforeId);
        }
        dragData = null;
    });

    form.addEventListener('submit', serialize);
    root.querySelector('[data-sidebar-search]')?.addEventListener('input', renderLibrary);
    renderAll();
})();

(function() {
    var builder = document.querySelector('[data-menu-builder]');
    var form = document.getElementById('appearanceForm');
    var hidden = document.getElementById('menu_items');
    var list = document.querySelector('[data-menu-row-list]');
    var template = document.getElementById('appearanceMenuItemTemplate');
    var addButton = document.querySelector('[data-menu-add]');
    var preview = document.querySelector('.appearance-menu-preview');

    if (!builder || !form || !hidden || !list || !template) {
        return;
    }

    function getRows() {
        return Array.from(list.querySelectorAll('[data-menu-row]'));
    }

    function rowIconValue(row) {
        var select = row.querySelector('[data-menu-icon-select]');
        var custom = row.querySelector('[data-menu-icon]');
        if (!select) return '';
        return select.value === 'custom' ? (custom ? custom.value.trim() : '') : select.value.trim();
    }

    function syncIconMode(row) {
        var select = row.querySelector('[data-menu-icon-select]');
        var customWrap = row.querySelector('.appearance-menu-icon-custom');
        var custom = row.querySelector('[data-menu-icon]');
        if (!select || !customWrap || !custom) return;
        customWrap.classList.toggle('is-visible', select.value === 'custom');
        if (select.value !== 'custom') {
            custom.value = select.value;
        } else if (custom.value.trim() === '') {
            custom.value = 'bi-link-45deg';
        }
    }

    function renumberRows() {
        getRows().forEach(function(row, index) {
            var order = row.querySelector('[data-menu-order]');
            if (order && (!order.value || Number(order.value) <= 0)) {
                order.value = String(index + 1);
            }
        });
    }

    function serializeRows() {
        var rows = getRows().map(function(row, index) {
            var order = row.querySelector('[data-menu-order]');
            var label = row.querySelector('[data-menu-label]');
            var url = row.querySelector('[data-menu-url]');
            return {
                order: order ? Number(order.value || index + 1) : index + 1,
                label: label ? label.value.trim() : '',
                url: url ? url.value.trim() : '',
                icon: rowIconValue(row)
            };
        }).filter(function(item) {
            return item.label !== '' && item.url !== '';
        }).sort(function(a, b) {
            return a.order - b.order;
        });

        hidden.value = rows.map(function(item) {
            return [item.label, item.url, item.icon].join('|');
        }).join("\n");

        if (preview) {
            var cta = preview.querySelector('.appearance-menu-cta');
            preview.querySelectorAll('.appearance-menu-link').forEach(function(link) {
                link.remove();
            });
            rows.forEach(function(item) {
                var link = document.createElement('a');
                link.href = '#';
                link.className = 'appearance-menu-link';
                link.addEventListener('click', function(event) { event.preventDefault(); });
                if (item.icon) {
                    var icon = document.createElement('i');
                    icon.className = 'bi ' + item.icon;
                    link.appendChild(icon);
                }
                link.appendChild(document.createTextNode(item.label));
                preview.insertBefore(link, cta || null);
            });
        }
    }

    function moveRow(row, direction) {
        if (direction < 0 && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        } else if (direction > 0 && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        }
        getRows().forEach(function(item, index) {
            var order = item.querySelector('[data-menu-order]');
            if (order) order.value = String(index + 1);
        });
        serializeRows();
    }

    function wireRow(row) {
        row.querySelectorAll('input, select').forEach(function(input) {
            input.addEventListener('input', function() {
                syncIconMode(row);
                serializeRows();
            });
            input.addEventListener('change', function() {
                syncIconMode(row);
                serializeRows();
            });
        });
        row.querySelector('[data-menu-remove]')?.addEventListener('click', function() {
            row.remove();
            getRows().forEach(function(item, index) {
                var order = item.querySelector('[data-menu-order]');
                if (order) order.value = String(index + 1);
            });
            serializeRows();
            if (getRows().length === 0) {
                addButton?.focus();
            }
        });
        row.querySelector('[data-menu-up]')?.addEventListener('click', function() {
            moveRow(row, -1);
        });
        row.querySelector('[data-menu-down]')?.addEventListener('click', function() {
            moveRow(row, 1);
        });
        syncIconMode(row);
    }

    getRows().forEach(wireRow);
    renumberRows();
    serializeRows();

    if (addButton) {
        addButton.addEventListener('click', function() {
            var fragment = template.content.cloneNode(true);
            var row = fragment.querySelector('[data-menu-row]');
            list.appendChild(fragment);
            if (row) {
                var order = row.querySelector('[data-menu-order]');
                if (order) order.value = String(getRows().length);
                wireRow(row);
                row.querySelector('[data-menu-label]')?.focus();
                serializeRows();
            }
        });
    }

    form.addEventListener('submit', serializeRows);
})();
