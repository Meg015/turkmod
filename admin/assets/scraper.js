/* Scraper Bot Admin JS */
const scraperConfigNode = document.getElementById('adminScraperConfig');
const scraperConfig = scraperConfigNode ? JSON.parse(scraperConfigNode.textContent || '{}') : {};
const scraperBaseUri = (
    (typeof baseUri !== 'undefined' && baseUri)
        || scraperConfig.baseUri
        || document.querySelector('meta[name="app-base-uri"]')?.getAttribute('content')
        || ''
).replace(/\/$/, '');
const API = scraperBaseUri + '/api/scraper.php';

function apiPost(action, data = {}) {
    const csrfToken = document.querySelector('input[name="_token"]')?.value || '';
    const fd = new FormData();
    fd.append('action', action);
    fd.append('_token', csrfToken);
    Object.entries(data).forEach(([k, v]) => fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v));
    return fetch(API, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(async r => {
            const text = (await r.text()).replace(/^\uFEFF/, '').trim();
            try {
                return JSON.parse(text);
            } catch (error) {
                return { success: false, error: text.trim() || `HTTP ${r.status}` };
            }
        });
}
function apiGet(action, params = {}) {
    const qs = new URLSearchParams({ action, ...params });
    return fetch(API + '?' + qs)
        .then(async r => {
            const text = (await r.text()).replace(/^\uFEFF/, '').trim();
            try {
                return JSON.parse(text);
            } catch (error) {
                return { success: false, error: text.trim() || `HTTP ${r.status}` };
            }
        });
}

function escapeHtml(value = '') {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function scraperToast(message, type = 'info', duration) {
    if (window.adminToast && typeof window.adminToast[type] === 'function') {
        window.adminToast[type](message, duration);
        return;
    }
    if (typeof window.showToast === 'function') {
        window.showToast(message, type, duration);
    }
}

function escapeJsSingle(value = '') {
    return String(value)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\r?\n/g, ' ');
}

function normalizeDiscoveredTopic(item, index) {
    if (typeof item === 'string') {
        return {
            url: item,
            title: 'Link ' + (index + 1),
            image: '',
            alreadyImported: false,
            importedStatus: '',
            importedTopicId: 0,
        };
    }

    return {
        url: item?.url || '',
        title: item?.title || ('Link ' + (index + 1)),
        image: item?.image || '',
        alreadyImported: !!item?.already_imported,
        importedStatus: item?.imported_status || '',
        importedTopicId: parseInt(item?.imported_topic_id || '0', 10) || 0,
    };
}

function renderImportedWarning(topic) {
    if (!topic.alreadyImported) return '';
    const status = topic.importedStatus ? ` (${escapeHtml(topic.importedStatus)})` : '';
    return `<div class="bulk-imported-info" data-imported="1"><i class="bi bi-info-circle"></i> Daha önce çekildi${status}</div>`;
}

function getScraperImageUrl(src = '') {
    if (!src) return '';
    if (src.startsWith('data:')) return src;
    const normalizedSrc = src.startsWith('//') ? `${window.location.protocol}${src}` : src;
    if (normalizedSrc.startsWith('http://') || normalizedSrc.startsWith('https://')) {
        try {
            const url = new URL(normalizedSrc, window.location.href);
            if (url.origin === window.location.origin) return url.href;
            return `${scraperBaseUri}/api/scraper-image.php?url=${encodeURIComponent(url.href)}`;
        } catch (error) {
            return '';
        }
    }
    return `${scraperBaseUri}/${src.replace(/^\/+/, '')}`;
}

function scraperIsSafeHtmlUrl(value = '', attributeName = '') {
    const raw = String(value || '').trim();
    if (!raw) return true;
    if (raw.startsWith('#') || raw.startsWith('/') || raw.startsWith('./') || raw.startsWith('../')) return true;
    if (/^(mailto|tel):/i.test(raw)) return true;
    if (/^data:image\/(?:png|jpe?g|gif|webp|avif);base64,/i.test(raw)) {
        return attributeName === 'src';
    }

    try {
        const url = new URL(raw, window.location.href);
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch (error) {
        return false;
    }
}

function replaceBrokenScraperThumbnail(img) {
    if (!img || img.dataset.scraperThumbHandled === '1') return;
    img.dataset.scraperThumbHandled = '1';

    const fallback = document.createElement('div');
    fallback.className = 'scraper-thumb-empty';
    fallback.innerHTML = '<i class="bi bi-image"></i>';
    img.replaceWith(fallback);
}

function initScraperThumbnailFallbacks(root = document) {
    root.querySelectorAll('[data-scraper-thumb]').forEach((img) => {
        img.addEventListener('error', () => replaceBrokenScraperThumbnail(img), { once: true });
        if (img.complete && img.naturalWidth === 0) {
            replaceBrokenScraperThumbnail(img);
        }
    });
}

function scraperSanitizeHtml(value = '') {
    if (typeof document === 'undefined') {
        return escapeHtml(value);
    }

    const template = document.createElement('template');
    template.innerHTML = String(value || '');
    template.content.querySelectorAll('script, iframe, object, embed, link, meta, style, base, form').forEach(el => el.remove());

    template.content.querySelectorAll('*').forEach(el => {
        Array.from(el.attributes).forEach(attr => {
            const name = attr.name.toLowerCase();
            const attrValue = attr.value || '';

            if (name.startsWith('on') || name === 'style' || name === 'srcdoc' || name === 'srcset') {
                el.removeAttribute(attr.name);
                return;
            }

            if (['href', 'src', 'xlink:href', 'formaction'].includes(name) && !scraperIsSafeHtmlUrl(attrValue, name)) {
                el.removeAttribute(attr.name);
                return;
            }

            if (name === 'target') {
                const target = attrValue.toLowerCase();
                if (!['_blank', '_self', '_parent', '_top'].includes(target)) {
                    el.removeAttribute(attr.name);
                } else if (target === '_blank') {
                    el.setAttribute('rel', 'noopener noreferrer');
                }
            }
        });
    });

    return template.innerHTML;
}

function scraperSetTrustedHtml(target, html = '') {
    if (!target) return;
    target.innerHTML = scraperSanitizeHtml(html);
}

function scraperSetImagePreview(target, src = '', options = {}) {
    if (!target) return;
    target.textContent = '';

    const imageUrl = getScraperImageUrl(src);
    if (imageUrl) {
        const img = document.createElement('img');
        img.src = imageUrl;
        img.referrerPolicy = 'no-referrer';
        img.className = 'scraper-cover-image';
        target.appendChild(img);

        if (options.badge) {
            const badge = document.createElement('span');
            badge.className = 'crm-cover-badge';
            badge.textContent = options.badge;
            target.appendChild(badge);
        }

        return;
    }

    const placeholder = document.createElement('div');
    placeholder.className = 'crm-cover-placeholder';

    const icon = document.createElement('i');
    icon.className = `bi ${options.placeholderIcon || 'bi-image'}`;
    placeholder.appendChild(icon);

    const label = document.createElement('span');
    label.textContent = options.placeholderText || 'Görsel yok';
    placeholder.appendChild(label);

    target.appendChild(placeholder);
}

function scraperSetGalleryPreview(target, images = []) {
    if (!target) return;
    target.textContent = '';

    const validImages = images.map(src => getScraperImageUrl(src)).filter(Boolean);
    if (validImages.length === 0) {
        const empty = document.createElement('span');
        empty.className = 'crm-gallery-empty';
        empty.textContent = 'Ek görsel yok';
        target.appendChild(empty);
        return;
    }

    validImages.forEach(src => {
        const thumb = document.createElement('div');
        thumb.className = 'crm-gallery-thumb scraper-gallery-thumb-lg';

        const img = document.createElement('img');
        img.src = src;
        img.referrerPolicy = 'no-referrer';
        img.className = 'scraper-gallery-image';

        thumb.appendChild(img);
        target.appendChild(thumb);
    });
}

function getDisplayUrl(url = '') {
    return String(url).replace(/^https?:\/\//, '').replace(/\/$/, '');
}

function ensurePreviewSiteDefaults(data = {}) {
    if (!data.site_defaults || typeof data.site_defaults !== 'object') {
        data.site_defaults = {};
    }
    return data.site_defaults;
}

function renderDetectionBadge(enabled, label) {
    return enabled
        ? `<span class="bulk-imported-info scraper-detected-badge"><i class="bi bi-magic"></i> ${escapeHtml(label)} otomatik tespit edildi</span>`
        : '';
}

function renderTranslationErrors(errors = []) {
    if (!Array.isArray(errors) || errors.length === 0) return '';
    return `
        <div class="ui-admin-alert ui-admin-alert-error scraper-alert-spaced">
            <strong>DeepL çeviri uyarısı</strong>
            <div>${errors.map(error => escapeHtml(error)).join('<br>')}</div>
        </div>
    `;
}

function getPreviewPublishCategoryId() {
    const value = pendingImportData?.site_defaults?.category_id ?? pendingImportData?.category_id ?? 0;
    return parseInt(value, 10) || 0;
}

function getPreviewPublishStatus() {
    const status = pendingImportData?.site_defaults?.status || pendingImportData?.publish_status || '';
    if (status === 'draft' || status === 'published') return status;
    if (typeof botDefaultStatus !== 'undefined' && (botDefaultStatus === 'draft' || botDefaultStatus === 'published')) {
        return botDefaultStatus;
    }
    return 'draft';
}

function renderMappingTopicCard(item, index, siteId, localCatId) {
    const topic = normalizeDiscoveredTopic(item, index);
    const safeUrl = escapeHtml(topic.url);
    const safeTitle = escapeHtml(topic.title);
    const safeImage = escapeHtml(getScraperImageUrl(topic.image));
    const displayUrl = escapeHtml(getDisplayUrl(topic.url));
    const jsUrl = escapeJsSingle(topic.url);
    const importedClass = topic.alreadyImported ? ' is-imported' : '';
    const importedInfo = renderImportedWarning(topic);
    const badge = item && typeof item === 'object' && item.page
        ? `Sayfa ${parseInt(item.page, 10) || item.page}`
        : `#${index + 1}`;
    // Harici resimler için proxy veya placeholder kullan
    const isExternalImage = safeImage && (safeImage.startsWith('http://') || safeImage.startsWith('https://')) && !safeImage.includes(window.location.hostname);
    const thumbHtml = safeImage
        ? `<img src="${safeImage}" alt="${safeTitle}" width="300" height="150" loading="lazy" referrerpolicy="no-referrer" data-remove-on-error>`
        : '<div class="mapping-topic-thumb-placeholder"><i class="bi bi-image"></i></div>';

    return `
        <article class="mapping-topic-card${importedClass}">
            <a class="mapping-topic-thumb" href="${safeUrl}" target="_blank" rel="noopener">
                ${thumbHtml}
                <span>${escapeHtml(badge)}</span>
            </a>
            <div class="mapping-topic-body">
                ${importedInfo}
                <h6 title="${safeTitle}">${safeTitle}</h6>
                <a class="mapping-topic-url" href="${safeUrl}" target="_blank" rel="noopener">${displayUrl}</a>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary mapping-topic-action" data-scraper-action="preview-topic" data-url="${safeUrl}" data-site-id="${siteId}" data-local-cat-id="${localCatId}">
                    <i class="bi bi-cloud-download"></i> Önizle & Çek
                </button>
            </div>
        </article>
    `;
}

function renderBulkTopicCard(item, index, siteId, localCatId, mappingId) {
    const topic = normalizeDiscoveredTopic(item, index);
    const safeUrl = escapeHtml(topic.url);
    const safeTitle = escapeHtml(topic.title);
    const safeImage = escapeHtml(getScraperImageUrl(topic.image));
    const displayUrl = escapeHtml(getDisplayUrl(topic.url));
    const jsUrl = escapeJsSingle(topic.url);
    const safeMappingId = parseInt(mappingId, 10) || 0;
    const importedClass = topic.alreadyImported ? ' is-imported' : '';
    const importedInfo = topic.alreadyImported
        ? `<div class="bulk-imported-info"><i class="bi bi-info-circle"></i> Daha önce çekildi${topic.importedStatus ? ` (${escapeHtml(topic.importedStatus)})` : ''}</div>`
        : '';
    const badge = item && typeof item === 'object' && item.page
        ? `Sayfa ${parseInt(item.page, 10) || item.page}`
        : `#${index + 1}`;
    const thumbHtml = safeImage
        ? `<img src="${safeImage}" alt="${safeTitle}" width="300" height="150" loading="lazy" referrerpolicy="no-referrer" data-remove-on-error>`
        : '<div class="mapping-topic-thumb-placeholder"><i class="bi bi-image"></i></div>';
    const checked = typeof botBulkDefaultSelected === 'undefined' || botBulkDefaultSelected === '1' ? 'checked' : '';

    return `
        <article class="mapping-topic-card${importedClass}">
            <a class="mapping-topic-thumb" href="${safeUrl}" target="_blank" rel="noopener">
                ${thumbHtml}
                <span>${escapeHtml(badge)}</span>
            </a>
            <div class="mapping-topic-body">
                ${importedInfo}
                <label class="bulk-topic-select">
                    <input type="checkbox" data-bulk-topic-checkbox="${safeMappingId}" data-imported="${topic.alreadyImported ? '1' : '0'}" value="${safeUrl}" ${checked}>
                    <span>Seçili</span>
                </label>
                <h6 title="${safeTitle}">${safeTitle}</h6>
                <a class="mapping-topic-url" href="${safeUrl}" target="_blank" rel="noopener">${displayUrl}</a>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary mapping-topic-action" data-scraper-action="preview-topic" data-url="${safeUrl}" data-site-id="${siteId}" data-local-cat-id="${localCatId}">
                    <i class="bi bi-cloud-download"></i> Önizle & Çek
                </button>
            </div>
        </article>
    `;
}

function renderMappingPagination(state, mappingId) {
    const page = state?.page || 1;
    const safeMappingId = parseInt(mappingId, 10) || 0;
    const currentUrl = escapeHtml(getDisplayUrl(state?.currentUrl || ''));
    const prevDisabled = state?.prevUrl ? '' : 'disabled';
    const nextDisabled = state?.nextUrl ? '' : 'disabled';

    return `
        <div class="mapping-pagination-bar" data-mapping-pagination="${safeMappingId}">
            <div class="mapping-pagination-info">
                <strong>${page}. sayfa</strong>
                <span title="${escapeHtml(state?.currentUrl || '')}">${currentUrl}</span>
            </div>
            <div class="mapping-pagination-actions">
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-outline" data-mapping-page-button data-direction="prev" data-mapping-id="${safeMappingId}" ${prevDisabled}>
                    <i class="bi bi-chevron-left"></i> Önceki
                </button>
                <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-primary" data-mapping-page-button data-direction="next" data-mapping-id="${safeMappingId}" ${nextDisabled}>
                    Sonraki <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    `;
}

function renderBulkProgress(progress = {}) {
    const total = Math.max(parseInt(progress.total, 10) || 0, 0);
    const current = Math.max(parseInt(progress.current, 10) || 0, 0);
    const percent = total > 0 ? Math.min(100, Math.round((current / total) * 100)) : 0;
    const message = escapeHtml(progress.message || 'Hazır');
    const detail = escapeHtml(progress.detail || '');
    const success = Math.max(parseInt(progress.success, 10) || 0, 0);
    const failed = Math.max(parseInt(progress.failed, 10) || 0, 0);

    return `
        <div class="bulk-progress">
            <div class="bulk-progress-head">
                <strong>${message}</strong>
                <span>${percent}%</span>
            </div>
            <div class="bulk-progress-track">
                <div class="bulk-progress-fill" data-bulk-progress="${percent}"></div>
            </div>
            <div class="bulk-progress-meta">
                <span>${current} / ${total}</span>
                <span>${success} başarılı</span>
                <span>${failed} hatalı</span>
            </div>
            ${detail ? `<div class="bulk-progress-meta"><span>${detail}</span></div>` : ''}
        </div>
    `;
}

function applyScraperPresentation(root = document) {
    root.querySelectorAll('[data-bulk-progress]').forEach(el => {
        const percent = Math.max(0, Math.min(100, parseInt(el.getAttribute('data-bulk-progress'), 10) || 0));
        el.style.setProperty('--bulk-progress-percent', percent + '%');
    });
    root.querySelectorAll('[data-thumb-url]').forEach(el => {
        const url = el.getAttribute('data-thumb-url') || '';
        if (!url) return;
        el.style.setProperty('--scraper-url-thumb-image', `url("${url.replace(/"/g, '\\"')}")`);
    });
}

/* ── Tab switching ── */
document.querySelectorAll('.scraper-tab-link').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        const id = link.dataset.tab;
        document.querySelectorAll('.scraper-tab-link').forEach(l => l.classList.toggle('active', l.dataset.tab === id));
        document.querySelectorAll('.scraper-tab-pane').forEach(p => p.classList.toggle('active', p.id === 'tab-' + id));
    });
});

document.querySelectorAll('.settings-subtab-link').forEach(link => {
    link.addEventListener('click', () => {
        const id = link.dataset.settingsTab;
        document.querySelectorAll('.settings-subtab-link').forEach(l => l.classList.toggle('active', l.dataset.settingsTab === id));
        document.querySelectorAll('.settings-subtab-pane').forEach(p => p.classList.toggle('active', p.id === 'settings-tab-' + id));
    });
});

document.querySelectorAll('.site-subtab-link').forEach(link => {
    link.addEventListener('click', () => {
        const id = link.dataset.siteTab;
        document.querySelectorAll('.site-subtab-link').forEach(l => l.classList.toggle('active', l.dataset.siteTab === id));
        document.querySelectorAll('.site-subtab-pane').forEach(p => p.classList.toggle('active', p.id === 'site-tab-' + id));
    });
});

/* ── Site Form ── */
const siteForm = document.getElementById('siteForm');
if (siteForm) siteForm.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(siteForm);
    const data = {};
    fd.forEach((v, k) => {
        if (k === '_token') return;
        if (k.endsWith('[]')) {
            const cleanKey = k.slice(0, -2);
            if (!Array.isArray(data[cleanKey])) data[cleanKey] = [];
            data[cleanKey].push(v);
        } else {
            data[k] = v;
        }
    });
    apiPost('save_site', data).then(r => {
        if (r.success) { scraperToast(r.message, 'success'); setTimeout(() => location.reload(), 800); }
        else scraperToast(r.error || 'Hata', 'error');
    }).catch(() => scraperToast('Bağlantı hatası', 'error'));
});

function addReplaceRuleRow(rule = {}) {
    const container = document.getElementById('replaceRulesContainer');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'replace-rule-row';
    row.innerHTML = `
        <div>
            <label class="ui-admin-form-label">Bul</label>
            <input type="text" name="site_replace_find[]" class="ui-admin-form-control" value="${escapeHtml(rule.find || '')}" placeholder="Eski kelime">
        </div>
        <div>
            <label class="ui-admin-form-label">Değiştir</label>
            <input type="text" name="site_replace_replace[]" class="ui-admin-form-control" value="${escapeHtml(rule.replace || '')}" placeholder="Yeni kelime">
        </div>
        <div>
            <label class="ui-admin-form-label">Alan</label>
            <select name="site_replace_scope[]" class="ui-admin-form-select">
                <option value="all" ${(rule.scope || 'all') === 'all' ? 'selected' : ''}>Tümü</option>
                <option value="title" ${rule.scope === 'title' ? 'selected' : ''}>Başlık</option>
                <option value="content" ${rule.scope === 'content' ? 'selected' : ''}>İçerik</option>
                <option value="download_links" ${rule.scope === 'download_links' ? 'selected' : ''}>İndirme linkleri</option>
            </select>
        </div>
        <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline replace-rule-remove" data-ui-remove-closest=".replace-rule-row" title="Kaldır"><i class="bi bi-x-lg"></i></button>
    `;
    container.appendChild(row);
}

function setReplaceRules(rules = []) {
    const container = document.getElementById('replaceRulesContainer');
    if (!container) return;
    container.innerHTML = '';
    if (Array.isArray(rules) && rules.length) {
        rules.forEach(rule => addReplaceRuleRow(rule));
    } else {
        addReplaceRuleRow();
    }
}

function addRemoveTextRow(rule = {}) {
    const container = document.getElementById('removeTextsContainer');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'replace-rule-row';
    row.innerHTML = `
        <div><label class="ui-admin-form-label">Silinecek Metin</label><input type="text" name="remove_text_text[]" class="ui-admin-form-control" value="${escapeHtml(rule.text || '')}"></div>
        <div><label class="ui-admin-form-label">Yerine</label><input type="text" class="ui-admin-form-control" value="Tamamen silinir" disabled></div>
        <div><label class="ui-admin-form-label">Alan</label><select name="remove_text_scope[]" class="ui-admin-form-select"><option value="all" ${(rule.scope || 'all') === 'all' ? 'selected' : ''}>Tümü</option><option value="title" ${rule.scope === 'title' ? 'selected' : ''}>Başlık</option><option value="content" ${rule.scope === 'content' ? 'selected' : ''}>İçerik</option><option value="download_links" ${rule.scope === 'download_links' ? 'selected' : ''}>İndirme linkleri</option></select></div>
        <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline replace-rule-remove" data-ui-remove-closest=".replace-rule-row" title="Kaldır"><i class="bi bi-x-lg"></i></button>`;
    container.appendChild(row);
}

function addAutoTagRow(rule = {}) {
    const container = document.getElementById('autoTagsContainer');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'replace-rule-row';
    row.innerHTML = `
        <div><label class="ui-admin-form-label">İçerikte Geçerse</label><input type="text" name="auto_tag_keyword[]" class="ui-admin-form-control" value="${escapeHtml(rule.keyword || '')}"></div>
        <div><label class="ui-admin-form-label">Etiket</label><input type="text" name="auto_tag_tag[]" class="ui-admin-form-control" value="${escapeHtml(rule.tag || '')}"></div>
        <div></div>
        <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline replace-rule-remove" data-ui-remove-closest=".replace-rule-row" title="Kaldır"><i class="bi bi-x-lg"></i></button>`;
    container.appendChild(row);
}

function addDownloadLinkRuleRow(rule = {}) {
    const container = document.getElementById('downloadLinkRulesContainer');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'replace-rule-row';
    row.innerHTML = `
        <div><label class="ui-admin-form-label">Link Adında Bul</label><input type="text" name="download_link_find[]" class="ui-admin-form-control" value="${escapeHtml(rule.find || '')}"></div>
        <div><label class="ui-admin-form-label">Değiştir</label><input type="text" name="download_link_replace[]" class="ui-admin-form-control" value="${escapeHtml(rule.replace || '')}"></div>
        <div></div>
        <button type="button" class="ui-admin-btn ui-admin-btn-sm ui-admin-btn-danger-outline replace-rule-remove" data-ui-remove-closest=".replace-rule-row" title="Kaldır"><i class="bi bi-x-lg"></i></button>`;
    container.appendChild(row);
}

function setSimpleRows(containerId, rows, addFn) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '';
    if (Array.isArray(rows) && rows.length) rows.forEach(row => addFn(row));
}

setReplaceRules();
setSimpleRows('removeTextsContainer', [], addRemoveTextRow);
setSimpleRows('autoTagsContainer', [], addAutoTagRow);
setSimpleRows('downloadLinkRulesContainer', [], addDownloadLinkRuleRow);

function editSite(id) {
    apiGet('get_site', { id }).then(r => {
        if (!r.success || !r.site) return scraperToast('Site bulunamadı', 'error');
        const s = r.site, sel = s.selectors || {}, set = s.settings || {};
        document.getElementById('site_id').value = s.id;
        document.getElementById('site_name').value = s.name || '';
        document.getElementById('site_base_url').value = s.base_url || '';
        document.getElementById('site_description').value = s.description || '';
        document.getElementById('site_status').value = s.status || 'active';
        document.getElementById('sel_topic_list').value = sel.topic_list || '';
        document.getElementById('sel_topic_link').value = sel.topic_link || '';
        document.getElementById('sel_title').value = sel.title || '';
        document.getElementById('sel_content').value = sel.content || '';
        document.getElementById('sel_images').value = sel.images || '';
        document.getElementById('sel_download_links').value = sel.download_links || '';
        document.getElementById('sel_pagination').value = sel.pagination || '';
        document.getElementById('max_images').value = set.max_images || 5;
        document.getElementById('source_lang').value = set.source_lang || 'EN';
        document.getElementById('target_lang').value = set.target_lang || 'TR';
        document.getElementById('translate').checked = !!set.translate;
        ['title_template','content_prepend','content_append','remove_selectors','trim_before_text','trim_after_text','site_default_category_id','site_default_status','site_default_author_id','skip_image_contains','allowed_image_domains','min_image_width','skip_download_domains','detect_author_labels','detect_version_pattern'].forEach(key => {
            const el = document.getElementById(key);
            if (el) el.value = set[key] || (key === 'min_image_width' ? '0' : '');
        });
        const detectAuthorEnabled = document.getElementById('detect_author_enabled');
        if (detectAuthorEnabled) detectAuthorEnabled.checked = set.detect_author_enabled !== false;
        const detectVersionEnabled = document.getElementById('detect_version_enabled');
        if (detectVersionEnabled) detectVersionEnabled.checked = set.detect_version_enabled !== false;
        setReplaceRules(set.replacements || []);
        setSimpleRows('removeTextsContainer', set.remove_texts || [], addRemoveTextRow);
        setSimpleRows('autoTagsContainer', set.auto_tags || [], addAutoTagRow);
        setSimpleRows('downloadLinkRulesContainer', set.download_link_replacements || [], addDownloadLinkRuleRow);
        // Switch to sites tab
        document.querySelector('[data-tab="sites"]')?.click();
        document.querySelector('[data-site-tab="basic"]')?.click();
        document.getElementById('siteFormTitle').textContent = 'Site Düzenle';
        siteForm.scrollIntoView({ behavior: 'smooth' });
    });
}

async function deleteSite(id) {
    if (!await adminConfirm('Bu siteyi ve tüm verilerini silmek istediğinize emin misiniz?', {
        title: 'Site silinsin mi?',
        ok: 'Sil',
        tone: 'danger'
    })) return;
    apiPost('delete_site', { id }).then(r => {
        if (r.success) { scraperToast(r.message, 'success'); setTimeout(() => location.reload(), 600); }
        else scraperToast(r.error || 'Hata', 'error');
    });
}

function resetSiteForm() {
    siteForm?.reset();
    document.getElementById('site_id').value = '';
    document.getElementById('siteFormTitle').textContent = 'Yeni Site Ekle';
    setReplaceRules();
    setSimpleRows('removeTextsContainer', [], addRemoveTextRow);
    setSimpleRows('autoTagsContainer', [], addAutoTagRow);
    setSimpleRows('downloadLinkRulesContainer', [], addDownloadLinkRuleRow);
    const detectAuthorEnabled = document.getElementById('detect_author_enabled');
    if (detectAuthorEnabled) detectAuthorEnabled.checked = true;
    const detectAuthorLabels = document.getElementById('detect_author_labels');
    if (detectAuthorLabels) detectAuthorLabels.value = 'author,authors,credit,credits';
    const detectVersionEnabled = document.getElementById('detect_version_enabled');
    if (detectVersionEnabled) detectVersionEnabled.checked = true;
    const detectVersionPattern = document.getElementById('detect_version_pattern');
    if (detectVersionPattern) detectVersionPattern.value = '1\\.(?:[3-9]\\d|[1-9]\\d{2,})';
    document.querySelector('[data-site-tab="basic"]')?.click();
}

/* ── Mapping Form ── */
const mappingForm = document.getElementById('mappingForm');
if (mappingForm) mappingForm.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(mappingForm);
    const data = {};
    fd.forEach((v, k) => { if (k !== '_token') data[k] = v; });
    apiPost('save_mapping', data).then(r => {
        if (r.success) { scraperToast(r.message, 'success'); setTimeout(() => location.reload(), 800); }
        else scraperToast(r.error || 'Hata', 'error');
    });
});

function editMapping(id) {
    apiGet('get_mapping', { id }).then(r => {
        if (!r.success || !r.mapping) return scraperToast('Eşleme bulunamadı', 'error');
        const m = r.mapping;
        const form = document.getElementById('mappingForm');
        if (!form) return;
        
        form.querySelector('[name="mapping_id"]').value = m.id;
        form.querySelector('[name="bot_site_id"]').value = m.bot_site_id;
        form.querySelector('[name="remote_category_url"]').value = m.remote_category_url;
        form.querySelector('[name="local_category_id"]').value = m.local_category_id || '';
        
        form.querySelector('button[type="submit"]').innerHTML = '<i class="bi bi-save"></i> Güncelle';
        document.querySelector('#tab-mappings .card-header').textContent = 'Kategori Eşleme Düzenle';
        
        const cancelBtn = document.getElementById('btnCancelMapping');
        if (cancelBtn) cancelBtn.style.display = 'inline-flex';
        
        document.querySelector('[data-tab="mappings"]')?.click();
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

function resetMappingForm() {
    const form = document.getElementById('mappingForm');
    if (!form) return;
    form.reset();
    form.querySelector('[name="mapping_id"]').value = '';
    form.querySelector('button[type="submit"]').innerHTML = '<i class="bi bi-plus-circle"></i> Ekle';
    document.querySelector('#tab-mappings .card-header').textContent = 'Kategori Eşleme Ekle';
    
    const cancelBtn = document.getElementById('btnCancelMapping');
    if (cancelBtn) cancelBtn.style.display = 'none';
}

async function deleteMapping(id) {
    if (!await adminConfirm('Bu eşlemeyi silmek istiyor musunuz?', {
        title: 'Eşleme silinsin mi?',
        ok: 'Sil',
        tone: 'danger'
    })) return;
    apiPost('delete_mapping', { id }).then(r => {
        if (r.success) { scraperToast(r.message, 'success'); setTimeout(() => location.reload(), 600); }
    });
}

/* ── Scrape Operations ── */
function testConnection() {
    const url = document.getElementById('site_base_url')?.value;
    if (!url) return scraperToast('URL girin', 'error');
    const btn = document.getElementById('btnTestConn');
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Test...';
    apiPost('test_connection', { url }).then(r => {
        scraperToast(r.message, r.success ? 'success' : 'error');
    }).finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-wifi"></i> Bağlantı Test'; });
}

function discoverUrls() {
    const siteId = document.getElementById('scrape_site_id')?.value;
    const catUrl = document.getElementById('scrape_category_url')?.value;
    if (!siteId || !catUrl) return scraperToast('Site ve kategori URL seçin', 'error');
    const btn = document.getElementById('btnDiscover');
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Taranıyor...';
    apiPost('discover_urls', { site_id: siteId, category_url: catUrl }).then(r => {
        const list = document.getElementById('discoveredUrls');
        if (r.success && r.urls && r.urls.length > 0) {
            list.classList.add('scraper-preview-url-grid');
            list.innerHTML = r.urls.map((u, i) => {
                const topic = normalizeDiscoveredTopic(u, i);
                const imgUrl = topic.image ? getScraperImageUrl(topic.image) : '';
                const bgHtml = imgUrl ? `<div class="scraper-url-thumb has-image" data-thumb-url="${escapeHtml(imgUrl)}"></div>` : `<div class="scraper-url-thumb"><i class="bi bi-image"></i></div>`;
                const safeTitle = escapeHtml(topic.title);
                const safeUrl = escapeHtml(topic.url);
                const importedInfo = renderImportedWarning(topic);
                return `
                <label class="scraper-url-item${topic.alreadyImported ? ' is-imported' : ''}">
                    ${bgHtml}
                    <div class="scraper-url-body">
                        ${importedInfo}
                        <div class="scraper-url-checkrow">
                            <input type="checkbox" name="urls[]" value="${safeUrl}" data-imported="${topic.alreadyImported ? '1' : '0'}" checked>
                            <div class="scraper-url-copy">
                                <strong class="scraper-url-title" title="${safeTitle}">${safeTitle}</strong>
                                <span class="scraper-url-link" title="${safeUrl}">${escapeHtml(getDisplayUrl(topic.url))}</span>
                            </div>
                        </div>
                    </div>
                </label>`;
            }).join('');
            applyScraperPresentation(list);
            document.getElementById('discoveredCount').textContent = r.count + ' Konu Bulundu';
            document.getElementById('discoveredPanel').style.display = '';
        } else {
            list.style.display = 'block';
            list.innerHTML = '<div class="scraper-mini-empty"><i class="bi bi-info-circle"></i> Hiç konu bulunamadı veya bağlantılar okunamadı.</div>';
            document.getElementById('discoveredPanel').style.display = '';
        }
    }).catch(() => scraperToast('Hata oluştu', 'error'))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-search"></i> URL Keşfet'; });
}

function scrapeSingle() {
    const siteId = document.getElementById('single_scrape_site')?.value;
    const url = document.getElementById('single_scrape_url')?.value;
    if (!siteId || !url) return scraperToast('Site ve URL gerekli', 'error');
    const btn = document.getElementById('btnScrapeSingle');
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Çekiliyor...';
    apiPost('scrape_single', { site_id: siteId, url }).then(r => {
        if (r.success) {
            if (r.warning) scraperToast(r.warning, 'warning');
            if (r.skipped) scraperToast('Bu konu daha önce çekilmiş; duplicate ayarına göre tekrar çekilmedi.', 'warning');
            scraperToast('İçerik çekildi! Import ID: ' + r.import_id, 'success');
            setTimeout(() => location.reload(), 1200);
        } else scraperToast(r.error || 'Hata', 'error');
    }).finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-download"></i> Tek Sayfa Çek'; });
}

async function scrapeBatch() {
    const siteId = document.getElementById('scrape_site_id')?.value;
    const mappingId = document.getElementById('scrape_mapping_id')?.value || '';
    const checked = document.querySelectorAll('#discoveredUrls input[type="checkbox"]:checked');
    const urls = Array.from(checked).map(c => c.value);
    if (!urls.length) return scraperToast('Çekilecek URL seçin', 'error');
    if (!await adminConfirm(urls.length + ' URL çekilecek. Devam?', {
        title: 'Toplu çekim başlasın mı?',
        ok: 'Başlat',
        tone: 'warning'
    })) return;
    if (!await warnIfSelectedImportedTopics('#discoveredUrls input[type="checkbox"]')) return;
    const btn = document.getElementById('btnScrapeBatch');
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> ' + urls.length + ' URL çekiliyor...';
    apiPost('scrape_batch', { site_id: siteId, mapping_id: mappingId, urls }).then(r => {
        if (r.success) {
            scraperToast(`Tamamlandı: ${r.imported} başarılı, ${r.failed} hatalı`, 'success');
            setTimeout(() => location.reload(), 1500);
        } else scraperToast(r.error || 'Hata', 'error');
    }).finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-collection"></i> Toplu Çek'; });
}

let pendingImportData = null;
const mappingTopicStates = {};
const bulkTopicStates = {};

function addPrevDownloadRow(name = '', url = '') {
    const container = document.getElementById('prevDownloadsContainer');
    const row = document.createElement('div');
    row.className = 'crm-dl-row';
    row.innerHTML = `
        <input type="text" class="crm-dl-name dl-name" placeholder="İsim (ör. Download)" value="${name.replace(/"/g, '&quot;')}">
        <input type="url" class="crm-dl-url dl-url" placeholder="https://..." value="${url.replace(/"/g, '&quot;')}">
        <button type="button" class="crm-dl-remove" data-ui-remove-closest=".crm-dl-row" title="Kaldır"><i class="bi bi-x-lg"></i></button>
    `;
    container.appendChild(row);
}

function populatePreviewModal(imp) {
    const modal = document.getElementById('previewModal');

    // Set editable fields
    document.getElementById('prevTitleEdit').value = imp.translated_title || imp.source_title || imp.title || '';
    document.getElementById('prevAuthorTopicEdit').value = imp.author_topic || '';
    document.getElementById('prevTopicVersionEdit').value = imp.topic_version || '';
    const detectionMeta = imp.detection_meta || {};
    const authorDetectedHtml = renderDetectionBadge(!!detectionMeta.author_topic, 'Mod yapımcısı');
    const versionDetectedHtml = renderDetectionBadge(!!detectionMeta.topic_version, 'Oyun sürümü');
    const authorInput = document.getElementById('prevAuthorTopicEdit');
    const versionInput = document.getElementById('prevTopicVersionEdit');
    if (authorInput && !document.getElementById('prevAuthorTopicDetected')) {
        authorInput.insertAdjacentHTML('afterend', '<div id="prevAuthorTopicDetected" class="scraper-inline-slot"></div>');
    }
    if (versionInput && !document.getElementById('prevTopicVersionDetected')) {
        versionInput.insertAdjacentHTML('afterend', '<div id="prevTopicVersionDetected" class="scraper-inline-slot"></div>');
    }
    const authorBadge = document.getElementById('prevAuthorTopicDetected');
    const versionBadge = document.getElementById('prevTopicVersionDetected');
    if (authorBadge) authorBadge.innerHTML = authorDetectedHtml;
    if (versionBadge) versionBadge.innerHTML = versionDetectedHtml;

    const modalBody = modal?.querySelector('.crm-body');
    if (modalBody && !document.getElementById('prevTranslationErrors')) {
        modalBody.insertAdjacentHTML('afterbegin', '<div id="prevTranslationErrors"></div>');
    }
    const translationErrors = document.getElementById('prevTranslationErrors');
    if (translationErrors) {
        translationErrors.innerHTML = renderTranslationErrors(imp.translation_errors || []);
    }

    let content = imp.translated_content || imp.source_content || imp.content || '';

    // Center content by default if not already explicitly styled
    if (content && !content.includes('text-align: center') && !content.includes('ql-align-center') && !content.includes('text-align:center')) {
        content = `<div class="scraper-content-centered">${content}</div>`;
    }

    // Download links
    let downloads = [];
    if (typeof imp.source_download_links === 'string') {
        downloads = imp.source_download_links.split('\n').filter(Boolean);
    } else if (typeof imp.download_links === 'string') {
        downloads = imp.download_links.split('\n').filter(Boolean);
    }

    document.getElementById('prevDownloadsContainer').innerHTML = '';
    if (downloads.length > 0) {
        downloads.forEach(l => {
            let parts = l.split('|');
            if (parts.length > 1) {
                addPrevDownloadRow(parts[0].trim(), parts.slice(1).join('|').trim());
            } else {
                addPrevDownloadRow('İndirme Linki', parts[0].trim());
            }
        });
    } else {
        addPrevDownloadRow('', '');
    }

    document.getElementById('prevSource').href = imp.source_url;
    document.getElementById('prevSource').textContent = imp.source_url;
    document.getElementById('prevImages').textContent = imp.images_count || 0;

    let imgList = [];
    if (Array.isArray(imp.downloaded_images) && imp.downloaded_images.length > 0) {
        imgList = imp.downloaded_images;
    } else if (typeof imp.downloaded_images === 'string' && imp.downloaded_images.trim() !== '') {
        imgList = imp.downloaded_images.split('\n').filter(Boolean);
    } else if (Array.isArray(imp.images) && imp.images.length > 0) {
        imgList = imp.images;
    } else if (typeof imp.images === 'string' && imp.images.trim() !== '') {
        imgList = imp.images.split('\n').filter(Boolean);
    } else if (Array.isArray(imp.source_images) && imp.source_images.length > 0) {
        imgList = imp.source_images;
    } else if (typeof imp.source_images === 'string' && imp.source_images.trim() !== '') {
        imgList = imp.source_images.split('\n').filter(Boolean);
    }
    
    imgList = imgList.filter(Boolean);
    
    document.getElementById('prevImages').textContent = (imgList.length > 1) ? (imgList.length - 1) : 0;
    
    scraperSetImagePreview(document.getElementById('prevCoverImage'), imgList[0] || '', {
        badge: 'Kapak',
        placeholderIcon: 'bi-image',
        placeholderText: 'Kapak yok',
    });

    scraperSetGalleryPreview(document.getElementById('prevImgList'), imgList.slice(1));
        
    // Init Quill Editor on a div element
    const contentEl = document.getElementById('prevContentEdit');
    if (contentEl) {
        const safeContent = scraperSanitizeHtml(content);
        if (typeof Quill === 'undefined') {
            contentEl.contentEditable = 'true';
            scraperSetTrustedHtml(contentEl, content);
        } else if (!contentEl.quillInstance) {
            const AlignStyle = Quill.import('attributors/style/align');
            Quill.register(AlignStyle, true);
            const quill = new Quill(contentEl, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        ['blockquote', 'code-block'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['link', 'image', 'video'],
                        ['clean'],
                        [{ 'align': [] }]
                    ]
                }
            });
            contentEl.quillInstance = quill;
        }
        
        // Correctly load HTML into Quill
        if (contentEl.quillInstance && contentEl.quillInstance.clipboard && contentEl.quillInstance.clipboard.dangerouslyPasteHTML) {
            contentEl.quillInstance.clipboard.dangerouslyPasteHTML(safeContent);
        } else if (contentEl.quillInstance) {
            scraperSetTrustedHtml(contentEl.quillInstance.root, content);
        }
    }
    
    // Hide go to topic button when modal opens
    const btnGo = document.getElementById('btnGoToTopic');
    if (btnGo) {
        btnGo.style.display = 'none';
        btnGo.href = '#';
    }

    openPreviewModalFrame();
}

function openPreviewModalFrame() {
    const modal = document.getElementById('previewModal');
    if (!modal) {
        scraperToast('Önizleme penceresi bulunamadı.', 'error');
        return null;
    }

    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
        window.TMUI.openDialog(modal, {
            bodyClass: 'ui-admin-dialog-open',
            initialFocus: '.crm-close',
            returnFocus: document.activeElement,
            onClose: function () {
                modal.classList.remove('ui-admin-modal-open');
            }
        });
        modal.classList.add('ui-admin-modal-open');
        return modal;
    }
    modal.hidden = false;
    modal.setAttribute('aria-hidden', 'false');
    modal.classList.add('is-open', 'ui-admin-modal-open');
    document.body.classList.add('ui-admin-dialog-open');
    return modal;
}

function showPreviewLoadingPopup(url = '') {
    const modal = openPreviewModalFrame();
    if (!modal) return;

    const importIdInput = document.getElementById('publish_import_id');
    if (importIdInput) importIdInput.value = '';

    const title = document.getElementById('prevTitleEdit');
    if (title) title.value = 'Önizleme hazırlanıyor...';

    const author = document.getElementById('prevAuthorTopicEdit');
    if (author) author.value = '';

    const version = document.getElementById('prevTopicVersionEdit');
    if (version) version.value = '';

    const downloads = document.getElementById('prevDownloadsContainer');
    if (downloads) downloads.innerHTML = '<div class="crm-gallery-empty">İndirme linkleri hazırlanıyor...</div>';

    const source = document.getElementById('prevSource');
    if (source) {
        source.href = url || '#';
        source.textContent = url || 'Kaynak hazırlanıyor...';
    }

    const images = document.getElementById('prevImages');
    if (images) images.textContent = '0';

    const cover = document.getElementById('prevCoverImage');
    if (cover) {
        cover.innerHTML = '<div class="crm-cover-placeholder"><i class="bi bi-hourglass-split"></i><span>Önizleme yükleniyor</span></div>';
    }

    const gallery = document.getElementById('prevImgList');
    if (gallery) gallery.innerHTML = '<span class="crm-gallery-empty">Görseller hazırlanıyor...</span>';

    const contentEl = document.getElementById('prevContentEdit');
    if (contentEl) {
        if (contentEl.quillInstance) {
            contentEl.quillInstance.root.innerHTML = '<p>İçerik çekiliyor, lütfen bekleyin...</p>';
        } else {
            contentEl.contentEditable = 'true';
            contentEl.innerHTML = '<p>İçerik çekiliyor, lütfen bekleyin...</p>';
        }
    }

    const btnGo = document.getElementById('btnGoToTopic');
    if (btnGo) {
        btnGo.style.display = 'none';
        btnGo.href = '#';
    }
}

function showPreviewErrorPopup(message = '') {
    const modal = openPreviewModalFrame();
    if (!modal) return;

    const title = document.getElementById('prevTitleEdit');
    if (title) title.value = 'Önizleme alınamadı';

    const cover = document.getElementById('prevCoverImage');
    if (cover) {
        cover.innerHTML = '<div class="crm-cover-placeholder"><i class="bi bi-exclamation-triangle"></i><span>Hata oluştu</span></div>';
    }

    const gallery = document.getElementById('prevImgList');
    if (gallery) gallery.innerHTML = '';

    const downloads = document.getElementById('prevDownloadsContainer');
    if (downloads) downloads.innerHTML = '';

    const contentEl = document.getElementById('prevContentEdit');
    if (contentEl) {
        const safeMessage = escapeHtml(message || 'İçerik çekilemedi.');
        if (contentEl.quillInstance) {
            contentEl.quillInstance.root.innerHTML = `<p>${safeMessage}</p>`;
        } else {
            contentEl.contentEditable = 'true';
            contentEl.innerHTML = `<p>${safeMessage}</p>`;
        }
    }
}

function showPreviewPopup(data) {
    openPreviewModalFrame();
    try {
        pendingImportData = data || {};
        const importIdInput = document.getElementById('publish_import_id');
        if (importIdInput) importIdInput.value = '';
        ensurePreviewSiteDefaults(pendingImportData);
        populatePreviewModal(data);
    } catch (error) {
        window.__lastPreviewPopupError = error;
        console.error('Preview popup failed', error);
        scraperToast('Önizleme açıldı, bazı alanlar yüklenemedi.', 'error');
    }
}

function previewImport(id) {
    apiGet('get_import', { id }).then(r => {
        if (!r.success || !r.import) return scraperToast('İçerik bulunamadı', 'error');
        const imp = r.import;
        pendingImportData = imp;
        ensurePreviewSiteDefaults(pendingImportData);
        document.getElementById('publish_import_id').value = imp.id;
        populatePreviewModal(imp);
    });
}

function closePreview() {
    const modal = document.getElementById('previewModal');
    if (!modal) return;
    if (window.TMUI && typeof window.TMUI.closeDialog === 'function' && modal._tmuiDialog) {
        window.TMUI.closeDialog(modal);
        modal.classList.remove('ui-admin-modal-open');
        return;
    }
    modal.classList.remove('is-open', 'ui-admin-modal-open');
    modal.setAttribute('aria-hidden', 'true');
    modal.hidden = true;
    document.body.classList.remove('ui-admin-dialog-open');
}

document.addEventListener('click', function (event) {
    if (event.target && event.target.id === 'previewModal') {
        closePreview();
    }
});

document.addEventListener('keydown', function (event) {
    if (window.TMUI && typeof window.TMUI.openDialog === 'function') return;
    const modal = document.getElementById('previewModal');
    if (event.key === 'Escape' && modal && !modal.hidden) {
        closePreview();
    }
});

function listMappingTopics(mappingId, siteId, categoryUrl, localCatId, pageUrl = null, direction = 'start') {
    const row = document.getElementById('mapping-list-row-' + mappingId);
    const list = document.getElementById('mapping-list-content-' + mappingId);
    const loading = document.getElementById('mapping-list-loading-' + mappingId);
    const state = mappingTopicStates[mappingId] || {
        siteId,
        localCatId,
        history: [],
        page: 1,
        currentUrl: categoryUrl,
        nextUrl: '',
    };
    const targetUrl = pageUrl || categoryUrl;

    row.style.display = 'table-row';
    list.style.display = 'none';
    loading.style.display = 'block';

    apiPost('discover_urls', { site_id: siteId, mapping_id: mappingId, category_url: targetUrl }).then(r => {
        loading.style.display = 'none';
        if (r.success) {
            if (direction === 'next' && state.currentUrl !== targetUrl) {
                state.history.push(state.currentUrl);
                state.page += 1;
            } else if (direction === 'prev') {
                state.history.pop();
                state.page = Math.max(1, state.page - 1);
            } else if (direction === 'start') {
                state.history = [];
                state.page = 1;
            }
            state.siteId = siteId;
            state.localCatId = localCatId;
            state.currentUrl = r.current_url || targetUrl;
            state.nextUrl = r.next_url || '';
            state.prevUrl = state.history.length ? state.history[state.history.length - 1] : '';
            mappingTopicStates[mappingId] = state;

            if (r.urls.length) {
                list.innerHTML = `
                    ${renderMappingPagination(state, mappingId)}
                    <div class="mapping-topic-grid">${r.urls.map((u, i) => renderMappingTopicCard(u, i, siteId, localCatId)).join('')}</div>
                `;
            } else {
                list.innerHTML = `
                    ${renderMappingPagination(state, mappingId)}
                    <div class="scraper-mini-empty-lg">Bu kategoride içerik bulunamadı.</div>
                `;
            }
            list.style.display = 'block';
        } else {
            list.innerHTML = `<div class="ui-admin-alert ui-admin-alert-error">${escapeHtml(r.error || 'Hata')}</div>`;
            list.style.display = 'block';
        }
    }).catch(() => {
        loading.style.display = 'none';
        list.innerHTML = `<div class="ui-admin-alert ui-admin-alert-error">Ağ bağlantısı hatası.</div>`;
        list.style.display = 'block';
    });
}

function goMappingPage(mappingId, direction) {
    const state = mappingTopicStates[mappingId];
    if (!state) {
        scraperToast('Sayfa durumu bulunamadı. Lütfen tekrar Listele deyin.', 'error');
        return;
    }

    if (direction === 'next') {
        if (!state.nextUrl) return;
        listMappingTopics(mappingId, state.siteId, state.currentUrl, state.localCatId, state.nextUrl, 'next');
        return;
    }

    if (direction === 'prev') {
        if (!state.prevUrl) return;
        listMappingTopics(mappingId, state.siteId, state.currentUrl, state.localCatId, state.prevUrl, 'prev');
    }
}

function renderBulkActions(mappingId, totalTopics, pageRange = null) {
    const disabled = totalTopics > 0 ? '' : 'disabled';
    const safeMappingId = parseInt(mappingId, 10) || 0;
    const defaultSelected = typeof botBulkDefaultSelected === 'undefined' || botBulkDefaultSelected === '1';
    const selectAllChecked = defaultSelected ? 'checked' : '';
    const clearAllChecked = defaultSelected ? '' : 'checked';
    const pageText = pageRange
        ? `${pageRange.total} sayfa içerik çekilecek (${pageRange.start}-${pageRange.end})`
        : 'İçerikler sırayla çekilir ve Gelen Kutu\'ya eklenir.';
    return `
        <div class="bulk-actions">
            <div>
                <strong>${totalTopics} konu listelendi</strong>
                <span>${escapeHtml(pageText)}</span>
            </div>
            <div class="bulk-action-controls">
                <label class="bulk-action-check">
                    <input type="checkbox" id="bulk-select-all-${safeMappingId}" data-bulk-select-control="${safeMappingId}" data-select-state="true" ${disabled} ${selectAllChecked}>
                    <span>Tümünü seç</span>
                </label>
                <label class="bulk-action-check">
                    <input type="checkbox" id="bulk-clear-all-${safeMappingId}" data-bulk-select-control="${safeMappingId}" data-select-state="false" ${disabled} ${clearAllChecked}>
                    <span>Tümünü kaldır</span>
                </label>
                <button type="button" class="ui-admin-btn ui-admin-btn-primary" data-scraper-action="scrape-bulk" data-mapping-id="${safeMappingId}" ${disabled}>
                    <i class="bi bi-cloud-download"></i> Tümünü Çek
                </button>
            </div>
        </div>
    `;
}

function updateBulkSelectionControls(mappingId) {
    const checkboxes = Array.from(document.querySelectorAll(`[data-bulk-topic-checkbox="${mappingId}"]`));
    const selectAll = document.getElementById('bulk-select-all-' + mappingId);
    const clearAll = document.getElementById('bulk-clear-all-' + mappingId);
    if (!checkboxes.length || !selectAll || !clearAll) return;

    const checkedCount = checkboxes.filter(item => item.checked).length;
    selectAll.checked = checkedCount === checkboxes.length;
    clearAll.checked = checkedCount === 0;
}

function setBulkSelection(mappingId, selected) {
    document.querySelectorAll(`[data-bulk-topic-checkbox="${mappingId}"]`).forEach(item => {
        item.checked = selected;
    });

    const selectAll = document.getElementById('bulk-select-all-' + mappingId);
    const clearAll = document.getElementById('bulk-clear-all-' + mappingId);
    if (selectAll) selectAll.checked = selected;
    if (clearAll) clearAll.checked = !selected;
}

function getSelectedBulkTopics(mappingId, topics) {
    const checked = Array.from(document.querySelectorAll(`[data-bulk-topic-checkbox="${mappingId}"]:checked`));
    if (!checked.length) return [];
    const selectedUrls = new Set(checked.map(item => item.value));
    return topics.filter(topic => selectedUrls.has(topic.url));
}

async function warnIfSelectedImportedTopics(selector) {
    const selectedImported = Array.from(document.querySelectorAll(selector))
        .filter(item => item.checked && item.dataset.imported === '1');
    if (!selectedImported.length) return true;

    const message = `${selectedImported.length} konu Daha önce çekilmiş. Tekrar çekmeye çalışırsanız bot duplicate ayarına göre atlar, günceller veya taslak kopya oluşturur. Devam?`;
    scraperToast(message, 'warning');
    return adminConfirm(message, {
        title: 'Daha önce çekilmiş içerik var',
        ok: 'Devam Et',
        tone: 'warning'
    });
}

function setBulkProgress(mappingId, progress) {
    const el = document.getElementById('bulk-progress-' + mappingId);
    if (el) {
        el.innerHTML = renderBulkProgress(progress);
        applyScraperPresentation(el);
    }
}

function getBulkPageRange(mappingId) {
    const startInput = document.getElementById('bulk-page-start-' + mappingId);
    const endInput = document.getElementById('bulk-page-end-' + mappingId);
    const legacyInput = document.getElementById('bulk-page-count-' + mappingId);
    const startValue = parseInt(startInput?.value || '1', 10) || 1;
    const endValue = parseInt(endInput?.value || legacyInput?.value || startValue, 10) || startValue;
    const start = Math.max(1, Math.min(startValue, endValue));
    const end = Math.max(1, Math.max(startValue, endValue));

    return {
        start,
        end,
        total: end - start + 1,
    };
}

async function listBulkMappingTopics(mappingId, siteId, categoryUrl, localCatId) {
    const row = document.getElementById('bulk-list-row-' + mappingId);
    const list = document.getElementById('bulk-list-content-' + mappingId);
    const loading = document.getElementById('bulk-list-loading-' + mappingId);
    const pageRange = getBulkPageRange(mappingId);
    const pageCount = pageRange.total;
    const topics = [];
    const seen = new Set();
    let currentUrl = categoryUrl;
    let pagesFetched = 0;
    let pagesInRangeFetched = 0;
    let errorMessage = '';
    const maxTopicsPerPage = typeof botBulkMaxTopicsPerPage !== 'undefined' ? (parseInt(botBulkMaxTopicsPerPage, 10) || 0) : 0;

    row.style.display = 'table-row';
    list.style.display = 'block';
    loading.style.display = 'none';
    list.innerHTML = `<div id="bulk-progress-${mappingId}">${renderBulkProgress({ total: pageCount, current: 0, message: 'Sayfalar taranıyor', detail: `Aralık: ${pageRange.start}-${pageRange.end}` })}</div>`;
    applyScraperPresentation(list);

    for (let page = 1; page <= pageRange.end; page++) {
        const isInRange = page >= pageRange.start;
        const progressCurrent = Math.max(0, Math.min(pageCount, page - pageRange.start));
        setBulkProgress(mappingId, {
            total: pageCount,
            current: progressCurrent,
            message: `Kategori sayfası ${page} ${isInRange ? 'taranıyor' : 'geçiliyor'}`,
            detail: `Aralık: ${pageRange.start}-${pageRange.end}`,
        });

        let result;
        try {
            result = await apiPost('discover_urls', { site_id: siteId, mapping_id: mappingId, category_url: currentUrl });
        } catch (e) {
            errorMessage = 'Ağ bağlantısı hatası.';
            break;
        }

        if (!result.success) {
            errorMessage = result.error || 'Sayfa taranamadı.';
            break;
        }

        pagesFetched = page;
        if (isInRange) {
            pagesInRangeFetched++;
            (result.urls || []).forEach(item => {
                const topic = normalizeDiscoveredTopic(item, topics.length);
                if (topic.url && !seen.has(topic.url)) {
                    seen.add(topic.url);
                    topics.push({ ...topic, page });
                }
            });
        }

        setBulkProgress(mappingId, {
            total: pageCount,
            current: Math.max(0, Math.min(pageCount, page - pageRange.start + 1)),
            message: `Kategori sayfası ${page} tamamlandı`,
            detail: `Aralık: ${pageRange.start}-${pageRange.end}`,
        });

        if (!result.next_url || page === pageRange.end) break;
        if (maxTopicsPerPage > 0 && topics.length >= maxTopicsPerPage) {
            topics.length = maxTopicsPerPage;
            break;
        }
        currentUrl = result.next_url;
    }

    bulkTopicStates[mappingId] = {
        siteId,
        localCatId,
        topics,
        pageRange,
        pageCount: pagesInRangeFetched,
    };

    list.innerHTML = `
        <div id="bulk-progress-${mappingId}">${renderBulkProgress({ total: pageCount, current: pagesInRangeFetched, message: errorMessage || 'Listeleme tamamlandı', detail: `${pageRange.start}-${pageRange.end} aralığında ${topics.length} konu bulundu` })}</div>
        ${errorMessage ? `<div class="ui-admin-alert ui-admin-alert-error">${escapeHtml(errorMessage)}</div>` : ''}
        ${renderBulkActions(mappingId, topics.length, pageRange)}
        <div class="mapping-topic-grid">${topics.map((item, i) => renderBulkTopicCard(item, i, siteId, localCatId, mappingId)).join('')}</div>
    `;
    applyScraperPresentation(list);
}

async function scrapeBulkTopics(mappingId) {
    const state = bulkTopicStates[mappingId];
    if (!state || !state.topics || !state.topics.length) {
        scraperToast('Çekilecek konu bulunamadı. Önce listeleme yapın.', 'error');
        return;
    }
    if (!state.localCatId) {
        scraperToast('Yerel kategori bulunamadı. Eşleşmede kategori seçin.', 'error');
        return;
    }

    const selectedTopics = getSelectedBulkTopics(mappingId, state.topics);
    if (!selectedTopics.length) {
        scraperToast('Çekilecek konu seçin.', 'error');
        return;
    }

    if (!warnIfSelectedImportedTopics(`[data-bulk-topic-checkbox="${mappingId}"]`)) {
        return;
    }

    let success = 0;
    let failed = 0;
    let skipped = 0;
    const total = selectedTopics.length;
    const actionButton = document.querySelector(`[data-scraper-action="scrape-bulk"][data-mapping-id="${mappingId}"]`);
    if (actionButton) {
        actionButton.disabled = true;
        actionButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Çekiliyor...';
    }

    for (let i = 0; i < selectedTopics.length; i++) {
        const topic = selectedTopics[i];
        setBulkProgress(mappingId, {
            total,
            current: i,
            success,
            failed,
            message: `Kategori sayfası ${topic.page || '-'}: ${topic.title || topic.url} çekiliyor`,
            detail: state.pageRange ? `Aralık: ${state.pageRange.start}-${state.pageRange.end}` : '',
        });

        try {
            const result = await apiPost('scrape_single', { site_id: state.siteId, url: topic.url });
            if (result.warning) scraperToast(result.warning, 'warning');
            if (result.skipped) {
                skipped++;
                continue;
            }
            if (result.success && result.import_id) {
                const publishResult = await apiPost('publish_import', {
                    import_id: result.import_id,
                    category_id: state.localCatId,
                    publish_status: result.data?.site_defaults?.status || (typeof botDefaultStatus !== 'undefined' ? botDefaultStatus : 'published'),
                });
                if (publishResult.success) success++;
                else failed++;
            } else {
                failed++;
                if (typeof botBulkContinueOnError !== 'undefined' && botBulkContinueOnError !== '1') break;
            }
        } catch (e) {
            failed++;
            if (typeof botBulkContinueOnError !== 'undefined' && botBulkContinueOnError !== '1') break;
        }

        setBulkProgress(mappingId, {
            total,
            current: i + 1,
            success,
            failed,
            message: `${i + 1}. içerik tamamlandı`,
            detail: `${success} içerik aktarıldı, ${failed} hata`,
        });
    }

    if (actionButton) {
        actionButton.disabled = false;
        actionButton.innerHTML = '<i class="bi bi-cloud-download"></i> Tümünü Çek';
    }
    scraperToast(`Toplu çekim tamamlandı: ${success} içerik aktarıldı, ${failed} hatalı`, failed ? 'error' : 'success');
}

function previewAndScrapeTopic(btnEl, url, siteId, localCatId) {
    const origBtnHtml = btnEl.innerHTML;
    btnEl.disabled = true;
    btnEl.innerHTML = '<i class="bi bi-hourglass-split"></i> Çekiliyor...';

    scraperToast('İçerik çekiliyor, lütfen bekleyin...', 'info');
    apiPost('preview_url', { site_id: siteId, url: url }).then(r => {
        btnEl.disabled = false;
        btnEl.innerHTML = origBtnHtml;
        if (r.success) {
            const defaults = ensurePreviewSiteDefaults(r.data);
            if (localCatId) defaults.category_id = localCatId;
            // Önce veriler hazır, sonra popup'ı aç
            showPreviewPopup(r.data);
            scraperToast('Önizleme hazır!', 'success');
        } else {
            scraperToast(r.error || 'İçerik çekilemedi', 'error');
            // Hata durumunda popup'ı açma (kullanıcı bunu boş geldi sanıyor)
            // showPreviewErrorPopup(r.error || 'İçerik çekilemedi');
        }
    }).catch((error) => {
        window.__lastPreviewRequestError = error;
        console.error('Preview request failed', error);
        btnEl.disabled = false;
        btnEl.innerHTML = origBtnHtml;
        scraperToast(error?.message || 'Bağlantı hatası oluştu', 'error');
        // showPreviewErrorPopup(error?.message || 'Bağlantı hatası oluştu');
    });
}

function publishImport() {
    const importId = document.getElementById('publish_import_id')?.value;
    const categoryId = getPreviewPublishCategoryId();
    const status = getPreviewPublishStatus();

    if (!categoryId && !importId) return scraperToast('Yerel kategori bulunamadı. Eşleştirmede kategori seçin.', 'error');

    // Capture edited fields
    const editedTitle = document.getElementById('prevTitleEdit').value;
    const editedAuthorTopic = document.getElementById('prevAuthorTopicEdit').value;
    const editedTopicVersion = document.getElementById('prevTopicVersionEdit').value;
    const contentEl = document.getElementById('prevContentEdit');
    const editedContent = contentEl && contentEl.quillInstance
        ? contentEl.quillInstance.root.innerHTML
        : (contentEl ? (contentEl.value || contentEl.innerHTML || '') : '');

    const dlNames = document.querySelectorAll('#prevDownloadsContainer input.dl-name');
    const dlUrls = document.querySelectorAll('#prevDownloadsContainer input.dl-url');
    let editedDownloadsArr = [];
    dlNames.forEach((n, i) => {
        const u = dlUrls[i].value.trim();
        if (u) {
            editedDownloadsArr.push((n.value.trim() || 'Link') + '|' + u);
        }
    });
    const editedDownloads = editedDownloadsArr.join('\n');

    if (importId) {
        apiPost('publish_import', {
            import_id: importId,
            category_id: categoryId,
            publish_status: status,
            title: editedTitle,
            author_topic: editedAuthorTopic,
            topic_version: editedTopicVersion,
            content: editedContent,
            download_links: editedDownloads
        }).then(r => {
            if (r.success) { 
                scraperToast(r.message, 'success'); 
                const btnGo = document.getElementById('btnGoToTopic');
                if (btnGo && r.topic_url) {
                    btnGo.href = r.topic_url;
                    btnGo.style.display = 'flex';
                } else {
                    setTimeout(() => location.reload(), 800); 
                }
            } else {
                scraperToast(r.error || r.message || 'Hata', 'error');
            }
        });
    } else if (pendingImportData) {
        // Update pending data with edits
        pendingImportData.title = editedTitle;
        pendingImportData.translated_title = editedTitle;
        pendingImportData.author_topic = editedAuthorTopic;
        pendingImportData.topic_version = editedTopicVersion;
        pendingImportData.content = editedContent;
        pendingImportData.translated_content = editedContent;
        pendingImportData.download_links = editedDownloads;

        const payload = {
            category_id: categoryId,
            publish_status: status,
            data: pendingImportData
        };
        apiPost('save_and_publish_import', payload).then(r => {
            if (r.success) { 
                scraperToast(r.message, 'success'); 
                const btnGo = document.getElementById('btnGoToTopic');
                if (btnGo && r.topic_url) {
                    btnGo.href = r.topic_url;
                    btnGo.style.display = 'flex';
                } else {
                    setTimeout(() => location.reload(), 800); 
                }
            } else {
                scraperToast(r.error || r.message || 'Hata', 'error');
            }
        });
    } else {
        scraperToast('Aktarılacak içerik bulunamadı', 'error');
    }
}

async function deleteImport(id) {
    if (!await adminConfirm('Bu içeriği silmek istiyor musunuz?', {
        title: 'İçerik silinsin mi?',
        ok: 'Sil',
        tone: 'danger'
    })) return;
    apiPost('delete_import', { id }).then(r => {
        if (r.success) { scraperToast(r.message, 'success'); setTimeout(() => location.reload(), 600); }
    });
}

/* ── Bot Settings ── */
const botForm = document.getElementById('botSettingsForm');
if (botForm) botForm.addEventListener('submit', e => {
    e.preventDefault();
    const fd = new FormData(botForm);
    const data = {};
    fd.forEach((v, k) => { if (k !== '_token') data[k] = v; });
    apiPost('save_bot_settings', data).then(r => {
        scraperToast(r.success ? r.message : (r.error || 'Hata'), r.success ? 'success' : 'error');
    });
});

function selectAllUrls(checked) {
    document.querySelectorAll('#discoveredUrls input[type="checkbox"]').forEach(c => c.checked = checked);
}

/* ── Bulk Import Operations ── */
function toggleAllImports(checked) {
    document.querySelectorAll('.import-checkbox').forEach(cb => cb.checked = checked);
    document.getElementById('selectAllImports').checked = checked;
    document.getElementById('selectAllImportsHeader').checked = checked;
    updateImportSelection();
}

function updateImportSelection() {
    const checkboxes = document.querySelectorAll('.import-checkbox');
    const checked = Array.from(checkboxes).filter(cb => cb.checked);
    const count = checked.length;
    
    document.getElementById('selectedImportsCount').textContent = count;
    document.getElementById('btnBulkPublish').disabled = count === 0;
    document.getElementById('btnBulkDelete').disabled = count === 0;
    
    const allChecked = count > 0 && count === checkboxes.length;
    const someChecked = count > 0 && count < checkboxes.length;
    
    document.getElementById('selectAllImports').checked = allChecked;
    document.getElementById('selectAllImportsHeader').checked = allChecked;
    document.getElementById('selectAllImports').indeterminate = someChecked;
    document.getElementById('selectAllImportsHeader').indeterminate = someChecked;
}

async function bulkPublishImports() {
    const checkboxes = document.querySelectorAll('.import-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    if (ids.length === 0) {
        scraperToast('Lütfen en az bir içerik seçin', 'warning');
        return;
    }
    
    const categoryId = parseInt(document.getElementById('bulkImportCategory').value);
    if (!categoryId) {
        scraperToast('Lütfen bir kategori seçin', 'warning');
        return;
    }
    
    if (!await adminConfirm(`${ids.length} içerik yayınlanacak. Devam etmek istiyor musunuz?`, {
        title: 'Toplu yayınlama',
        ok: 'Yayınla',
        tone: 'warning'
    })) {
        return;
    }
    
    const btn = document.getElementById('btnBulkPublish');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Yayınlanıyor...';
    
    let completed = 0;
    let failed = 0;
    
    const publishNext = (index) => {
        if (index >= ids.length) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            scraperToast(`Toplu yayınlama tamamlandı! Başarılı: ${completed}, Başarısız: ${failed}`, completed > 0 ? 'success' : 'error');
            setTimeout(() => location.reload(), 1500);
            return;
        }
        
        const importId = ids[index];
        btn.innerHTML = `<i class="bi bi-hourglass-split"></i> ${index + 1}/${ids.length} yayınlanıyor...`;
        
        apiPost('publish_import', { 
            import_id: importId, 
            category_id: categoryId,
            publish_status: botDefaultStatus || 'published'
        }).then(r => {
            if (r.success) {
                completed++;
            } else {
                failed++;
                console.error(`Import ${importId} failed:`, r.error);
            }
            publishNext(index + 1);
        }).catch(err => {
            failed++;
            console.error(`Import ${importId} error:`, err);
            publishNext(index + 1);
        });
    };
    
    publishNext(0);
}

async function bulkDeleteImports() {
    const checkboxes = document.querySelectorAll('.import-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => parseInt(cb.value));
    
    if (ids.length === 0) {
        scraperToast('Lütfen en az bir içerik seçin', 'warning');
        return;
    }
    
    if (!await adminConfirm(`${ids.length} içerik silinecek. Bu işlem geri alınamaz! Devam etmek istiyor musunuz?`, {
        title: 'Toplu silme',
        ok: 'Sil',
        tone: 'danger'
    })) {
        return;
    }
    
    const btn = document.getElementById('btnBulkDelete');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Siliniyor...';
    
    let completed = 0;
    let failed = 0;
    
    const deleteNext = (index) => {
        if (index >= ids.length) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            scraperToast(`Toplu silme tamamlandı! Başarılı: ${completed}, Başarısız: ${failed}`, completed > 0 ? 'success' : 'error');
            setTimeout(() => location.reload(), 1500);
            return;
        }
        
        const importId = ids[index];
        btn.innerHTML = `<i class="bi bi-hourglass-split"></i> ${index + 1}/${ids.length} siliniyor...`;
        
        apiPost('delete_import', { id: importId }).then(r => {
            if (r.success) {
                completed++;
            } else {
                failed++;
                console.error(`Import ${importId} failed:`, r.error);
            }
            deleteNext(index + 1);
        }).catch(err => {
            failed++;
            console.error(`Import ${importId} error:`, err);
            deleteNext(index + 1);
        });
    };
    
    deleteNext(0);
}

document.addEventListener('click', function(event) {
    const pageButton = event.target.closest('[data-mapping-page-button]');
    if (pageButton) {
        goMappingPage(pageButton.getAttribute('data-mapping-id'), pageButton.getAttribute('data-direction'));
        return;
    }

    const action = event.target.closest('[data-scraper-action]');
    if (!action) return;

    const actionName = action.getAttribute('data-scraper-action');
    if (actionName === 'preview-topic') {
        previewAndScrapeTopic(
            action,
            action.getAttribute('data-url') || '',
            parseInt(action.getAttribute('data-site-id'), 10) || 0,
            parseInt(action.getAttribute('data-local-cat-id'), 10) || 0
        );
    } else if (actionName === 'scrape-bulk') {
        scrapeBulkTopics(action.getAttribute('data-mapping-id'));
    } else if (actionName === 'add-replace-rule') {
        addReplaceRuleRow();
    } else if (actionName === 'add-remove-text') {
        addRemoveTextRow();
    } else if (actionName === 'add-auto-tag') {
        addAutoTagRow();
    } else if (actionName === 'add-download-link-rule') {
        addDownloadLinkRuleRow();
    } else if (actionName === 'reset-site-form') {
        resetSiteForm();
    } else if (actionName === 'test-connection') {
        testConnection();
    } else if (actionName === 'edit-site') {
        editSite(action.getAttribute('data-site-id'));
    } else if (actionName === 'delete-site') {
        deleteSite(action.getAttribute('data-site-id'));
    } else if (actionName === 'reset-mapping-form') {
        resetMappingForm();
    } else if (actionName === 'list-mapping-topics') {
        listMappingTopics(
            action.getAttribute('data-mapping-id'),
            action.getAttribute('data-site-id'),
            action.getAttribute('data-category-url') || '',
            action.getAttribute('data-local-cat-id') || 0
        );
    } else if (actionName === 'edit-mapping') {
        editMapping(action.getAttribute('data-mapping-id'));
    } else if (actionName === 'delete-mapping') {
        deleteMapping(action.getAttribute('data-mapping-id'));
    } else if (actionName === 'hide-target') {
        const target = document.getElementById(action.getAttribute('data-target-id') || '');
        if (target) target.style.display = 'none';
    } else if (actionName === 'list-bulk-mapping-topics') {
        listBulkMappingTopics(
            action.getAttribute('data-mapping-id'),
            action.getAttribute('data-site-id'),
            action.getAttribute('data-category-url') || '',
            action.getAttribute('data-local-cat-id') || 0
        );
    } else if (actionName === 'filter-logs' && typeof window.filterLogs === 'function') {
        window.filterLogs(action.getAttribute('data-filter') || 'all');
    } else if (actionName === 'bulk-publish') {
        bulkPublishImports();
    } else if (actionName === 'bulk-delete') {
        bulkDeleteImports();
    } else if (actionName === 'preview-import') {
        previewImport(action.getAttribute('data-import-id'));
    } else if (actionName === 'delete-import') {
        deleteImport(action.getAttribute('data-import-id'));
    } else if (actionName === 'close-preview') {
        closePreview();
    } else if (actionName === 'add-prev-download') {
        addPrevDownloadRow();
    } else if (actionName === 'publish-import') {
        publishImport();
    }
});

document.addEventListener('change', function(event) {
    const importSelectAll = event.target.closest('[data-import-select-all]');
    if (importSelectAll) {
        toggleAllImports(importSelectAll.checked);
        return;
    }

    if (event.target.closest('[data-import-checkbox]')) {
        updateImportSelection();
        return;
    }

    const bulkTopic = event.target.closest('[data-bulk-topic-checkbox]');
    if (bulkTopic) {
        updateBulkSelectionControls(bulkTopic.getAttribute('data-bulk-topic-checkbox'));
        return;
    }

    const selectControl = event.target.closest('[data-bulk-select-control]');
    if (selectControl) {
        setBulkSelection(
            selectControl.getAttribute('data-bulk-select-control'),
            selectControl.getAttribute('data-select-state') === 'true'
        );
    }
});

document.addEventListener('error', function(event) {
    const target = event.target;
    if (target && target.matches && target.matches('[data-remove-on-error]')) {
        target.remove();
    }
}, true);

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initScraperThumbnailFallbacks());
} else {
    initScraperThumbnailFallbacks();
}
