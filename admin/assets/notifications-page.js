const adminNotificationsPageDataNode = document.getElementById('adminNotificationsPageData');
const adminNotificationsPageData = adminNotificationsPageDataNode ? JSON.parse(adminNotificationsPageDataNode.textContent || '{}') : {};

(function () {
    const templates = adminNotificationsPageData.composerTemplates || {};
    const picker = document.getElementById('notificationTemplatePicker');
    if (!picker) {
        return;
    }

    const form = picker.closest('form');
    if (!form) {
        return;
    }

    picker.addEventListener('change', function () {
        const template = templates[this.value];
        if (!template) {
            return;
        }

        const title = form.querySelector('[name="title"]');
        const message = form.querySelector('[name="message"]');
        const link = form.querySelector('[name="link"]');
        const type = form.querySelector('[name="type"]');

        if (title) {
            title.value = template.title || '';
        }
        if (message) {
            message.value = template.message || '';
        }
        if (link) {
            link.value = template.link || '';
        }
        if (type && template.type) {
            type.value = template.type;
        }
    });
})();

(function () {
    const payloads = adminNotificationsPageData.templatePreviewPayloads || {};
    const typeMeta = adminNotificationsPageData.typeMeta || {};

    const renderTemplate = function (template, payload) {
        return String(template || '').replace(/{{\s*([a-zA-Z0-9_]+)\s*}}/g, function (_, key) {
            const value = Object.prototype.hasOwnProperty.call(payload, key) ? payload[key] : '';
            return value === null || typeof value === 'undefined' ? '' : String(value);
        }).trim();
    };

    const updatePreview = function (form) {
        const payload = payloads[form.dataset.templateKey || '__new'] || payloads.__new || {};
        const type = form.querySelector('[name="type"]')?.value || 'info';
        const meta = typeMeta[type] || typeMeta.info || { icon: 'bi-info-circle', class: 'info' };
        const title = renderTemplate(form.querySelector('[name="title_template"]')?.value, payload) || 'Başlık önizlemesi';
        const message = renderTemplate(form.querySelector('[name="message_template"]')?.value, payload) || 'Mesaj önizlemesi';
        const link = renderTemplate(form.querySelector('[name="link_template"]')?.value, payload);
        const icon = form.querySelector('[data-preview-icon]');
        const titleNode = form.querySelector('[data-preview-title]');
        const messageNode = form.querySelector('[data-preview-message]');
        const linkWrapper = form.querySelector('[data-preview-link-wrapper]');
        const linkNode = form.querySelector('[data-preview-link]');

        if (icon) {
            icon.className = 'bi ' + meta.icon + ' type-' + meta.class;
        }
        if (titleNode) {
            titleNode.textContent = title;
        }
        if (messageNode) {
            messageNode.textContent = message;
        }
        if (linkWrapper && linkNode) {
            linkWrapper.classList.toggle('notif-preview-link-hidden', !link);
            linkWrapper.setAttribute('href', link || '#');
            linkNode.textContent = link;
        }
    };

    document.querySelectorAll('form[data-live-template-preview="1"]').forEach(function (form) {
        ['title_template', 'message_template', 'link_template', 'type'].forEach(function (name) {
            const field = form.querySelector('[name="' + name + '"]');
            if (field) {
                field.addEventListener('input', function () { updatePreview(form); });
                field.addEventListener('change', function () { updatePreview(form); });
            }
        });
        updatePreview(form);
    });
})();
