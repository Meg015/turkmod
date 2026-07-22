function getAdminNotificationsPageData() {
    const node = document.getElementById('adminNotificationsPageData');
    if (!node) {
        return {};
    }
    try {
        return JSON.parse(node.textContent || '{}') || {};
    } catch (error) {
        return {};
    }
}

function initNotificationComposerTemplates(adminNotificationsPageData) {
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

        [title, message, link].forEach(function (field) {
            field?.dispatchEvent(new Event('input', { bubbles: true }));
        });
        type?.dispatchEvent(new Event('change', { bubbles: true }));
    });
}

function initNotificationTemplatePreviews(adminNotificationsPageData) {
    const payloads = adminNotificationsPageData.templatePreviewPayloads || {};
    const typeMeta = adminNotificationsPageData.typeMeta || {};
    const modal = document.getElementById('notificationPreviewModal');
    const modalContent = modal?.querySelector('[data-notification-preview-content]');
    const modalTitle = modal?.querySelector('#notificationPreviewTitle');
    const modalChannel = modal?.querySelector('[data-notification-preview-channel]');
    const closeButton = modal?.querySelector('button[data-notification-preview-close]');
    const closeControls = modal ? Array.from(modal.querySelectorAll('[data-notification-preview-close]')) : [];
    let activeForm = null;
    let restoreFocus = null;

    const renderTemplate = function (template, payload) {
        return String(template || '').replace(/{{\s*([a-zA-Z0-9_]+)\s*}}/g, function (_, key) {
            const value = Object.prototype.hasOwnProperty.call(payload, key) ? payload[key] : '';
            return value === null || typeof value === 'undefined' ? '' : String(value);
        }).trim();
    };

    const fieldValue = function (form, names) {
        for (const name of names) {
            const field = form.querySelector('[name="' + name + '"]');
            if (field) {
                return field.value || '';
            }
        }

        return '';
    };

    const parseFieldNames = function (value) {
        return String(value || '')
            .split(',')
            .map(function (name) { return name.trim(); })
            .filter(Boolean);
    };

    const uniqueFieldNames = function (names) {
        return names.filter(function (name, index, list) {
            return name && list.indexOf(name) === index;
        });
    };

    const previewFieldNames = function (form, datasetKey, defaults) {
        return uniqueFieldNames(parseFieldNames(form.dataset[datasetKey] || '').concat(defaults || []));
    };

    const previewWatchFieldNames = function (form) {
        return uniqueFieldNames([
            'title_template',
            'message_template',
            'link_template',
            'title',
            'message',
            'link',
            'email_subject_template',
            'email_body_template',
            'email_link_template',
            'email_preview_template',
            'type'
        ].concat(
            previewFieldNames(form, 'previewTypeFields', []),
            previewFieldNames(form, 'previewTitleFields', []),
            previewFieldNames(form, 'previewMessageFields', []),
            previewFieldNames(form, 'previewLinkFields', [])
        ));
    };

    const buildPreview = function (form) {
        const payload = payloads[form.dataset.templateKey || '__new'] || payloads.__new || {};
        const type = fieldValue(form, previewFieldNames(form, 'previewTypeFields', ['type'])) || 'info';
        const meta = typeMeta[type] || typeMeta.info || { icon: 'bi-info-circle', class: 'info' };
        const channel = form.dataset.channelPreview || 'site';
        const emailWarning = form.querySelector('.notification-email-warning')?.textContent?.trim() || '';
        const titleTemplate = fieldValue(form, previewFieldNames(form, 'previewTitleFields', ['title_template', 'title']));
        const messageTemplate = fieldValue(form, previewFieldNames(form, 'previewMessageFields', ['message_template', 'message']));
        const linkTemplate = fieldValue(form, previewFieldNames(form, 'previewLinkFields', ['link_template', 'link']));

        return {
            channel,
            type,
            meta,
            title: renderTemplate(titleTemplate, payload) || 'Başlık önizlemesi',
            message: renderTemplate(messageTemplate, payload) || 'Mesaj önizlemesi',
            link: renderTemplate(linkTemplate, payload),
            emailSubject: renderTemplate(form.querySelector('[name="email_subject_template"]')?.value, payload) || 'E-posta konusu',
            emailBody: renderTemplate(form.querySelector('[name="email_body_template"]')?.value, payload) || 'E-posta gövdesi',
            emailLink: renderTemplate(form.querySelector('[name="email_link_template"]')?.value, payload),
            emailPreview: renderTemplate(form.querySelector('[name="email_preview_template"]')?.value, payload),
            emailWarning
        };
    };

    const appendPreviewLink = function (parent, link) {
        if (!link) {
            return;
        }

        const anchor = document.createElement('a');
        anchor.href = link;
        anchor.target = '_blank';
        anchor.rel = 'noopener';

        const icon = document.createElement('i');
        icon.className = 'bi bi-link-45deg';
        icon.setAttribute('aria-hidden', 'true');

        const label = document.createElement('span');
        label.textContent = link;

        anchor.append(icon, label);
        parent.appendChild(anchor);
    };

    const renderSitePreview = function (preview) {
        const title = document.createElement('strong');
        title.className = 'notification-preview-title';

        const icon = document.createElement('i');
        icon.className = 'bi ' + (preview.meta.icon || 'bi-info-circle') + ' type-' + (preview.meta.class || 'info');
        icon.setAttribute('aria-hidden', 'true');

        const titleText = document.createElement('span');
        titleText.textContent = preview.title;
        title.append(icon, titleText);

        const message = document.createElement('p');
        message.textContent = preview.message;

        modalContent.append(title, message);
        appendPreviewLink(modalContent, preview.link);
    };

    const renderEmailPreview = function (preview) {
        const label = document.createElement('small');
        label.textContent = 'Önizleme';

        const subject = document.createElement('strong');
        subject.textContent = preview.emailSubject;

        const preheader = document.createElement('span');
        preheader.textContent = preview.emailPreview || 'Önizleme metni eklenmemiş.';

        const body = document.createElement('p');
        body.textContent = preview.emailBody;

        modalContent.append(label, subject, preheader, body);
        appendPreviewLink(modalContent, preview.emailLink);

        if (preview.emailWarning) {
            const warning = document.createElement('div');
            warning.className = 'notification-email-warning';

            const icon = document.createElement('i');
            icon.className = 'bi bi-exclamation-triangle';
            icon.setAttribute('aria-hidden', 'true');

            const text = document.createElement('span');
            text.textContent = preview.emailWarning;
            warning.append(icon, text);
            modalContent.appendChild(warning);
        }
    };

    const renderModalPreview = function (form) {
        if (!modalContent || !modalTitle || !modalChannel) {
            return;
        }

        const preview = buildPreview(form);
        modalTitle.textContent = preview.channel === 'email' ? 'E-Posta Önizlemesi' : 'Site İçi Önizleme';
        modalChannel.textContent = preview.channel === 'email' ? 'E-Posta Bildirimleri' : 'Site İçi Bildirimleri';
        modalContent.className = 'notification-template-preview notification-preview-modal-content ' + (preview.channel === 'email' ? 'notification-email-preview' : 'notification-site-preview');
        modalContent.replaceChildren();

        if (preview.channel === 'email') {
            renderEmailPreview(preview);
            return;
        }

        renderSitePreview(preview);
    };

    const openPreview = function (form, trigger) {
        if (!modal || !modalContent) {
            return;
        }

        activeForm = form;
        restoreFocus = trigger || null;
        renderModalPreview(form);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('notification-preview-modal-open');
        closeButton?.focus();
    };

    const closePreview = function () {
        if (!modal || modal.hidden) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('notification-preview-modal-open');
        activeForm = null;

        if (restoreFocus && document.contains(restoreFocus)) {
            restoreFocus.focus();
        }
        restoreFocus = null;
    };

    const keepFocusInsideModal = function (event) {
        if (!modal || modal.hidden || event.key !== 'Tab') {
            return;
        }

        const focusable = Array.from(modal.querySelectorAll('a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'))
            .filter(function (node) {
                return Boolean(node.offsetWidth || node.offsetHeight || node.getClientRects().length);
            });

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
            return;
        }

        if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    if (!modal || !modalContent) {
        return;
    }

    document.querySelectorAll('[data-notification-preview-open]').forEach(function (button) {
        if (button.dataset.notificationPreviewBound === '1') {
            return;
        }
        button.dataset.notificationPreviewBound = '1';
        button.addEventListener('click', function () {
            const form = button.closest('form[data-live-template-preview="1"]');
            if (form) {
                openPreview(form, button);
            }
        });
    });

    closeControls.forEach(function (control) {
        if (control.dataset.notificationPreviewCloseBound === '1') {
            return;
        }
        control.dataset.notificationPreviewCloseBound = '1';
        control.addEventListener('click', closePreview);
    });

    if (modal.dataset.notificationPreviewEscapeBound !== '1') {
        modal.dataset.notificationPreviewEscapeBound = '1';
        document.addEventListener('keydown', function (event) {
            keepFocusInsideModal(event);
            if (event.key === 'Escape') {
                closePreview();
            }
        });
    }

    document.querySelectorAll('form[data-live-template-preview="1"]').forEach(function (form) {
        previewWatchFieldNames(form).forEach(function (name) {
            const field = form.querySelector('[name="' + name + '"]');
            if (field) {
                if (field.dataset.notificationPreviewFieldBound === '1') {
                    return;
                }
                field.dataset.notificationPreviewFieldBound = '1';
                field.addEventListener('input', function () {
                    if (activeForm === form && !modal.hidden) {
                        renderModalPreview(form);
                    }
                });
                field.addEventListener('change', function () {
                    if (activeForm === form && !modal.hidden) {
                        renderModalPreview(form);
                    }
                });
            }
        });
    });
}

let accountEmailEditorInitStarted = false;

function parseAccountEmailDocument(value) {
    value = String(value || '');
    if (!/<(?:!doctype|html|body)\b/i.test(value)) {
        return null;
    }
    const parsed = new DOMParser().parseFromString(value, 'text/html');
    let editable = parsed.querySelector('[data-account-email-editable="1"]');
    if (!editable && parsed.body) {
        editable = parsed.body.querySelector('div[style*="background:#fff"], div[style*="background: #fff"]');
    }
    if (!editable) {
        editable = parsed.body;
    }
    return { document: parsed, editable, hasDoctype: /<!doctype\s+html/i.test(value) };
}

function accountEmailEditableHtml(value) {
    const parsed = parseAccountEmailDocument(value);
    return parsed && parsed.editable ? parsed.editable.innerHTML : String(value || '');
}

function composeAccountEmailDocument(template, editorHtml) {
    const parsed = parseAccountEmailDocument(template);
    if (!parsed || !parsed.editable) {
        return String(editorHtml || '');
    }
    parsed.editable.innerHTML = String(editorHtml || '');
    return (parsed.hasDoctype ? '<!doctype html>' : '') + parsed.document.documentElement.outerHTML;
}

function setAccountEmailEditorValue(textarea, value) {
    if (!textarea) {
        return;
    }
    const documentValue = String(value || '');
    const editableValue = accountEmailEditableHtml(documentValue);
    textarea.value = documentValue;
    textarea.accountEmailDocumentTemplate = documentValue;
    textarea.accountEmailInitialEditorHtml = editableValue;
    if (textarea.quillInstance) {
        textarea.quillInstance.clipboard.dangerouslyPasteHTML(editableValue, 'silent');
        textarea.accountEmailInitialEditorHtml = textarea.quillInstance.root.innerHTML;
    }
}

function syncAccountEmailEditor(textarea) {
    if (!textarea || !textarea.quillInstance) {
        return;
    }
    const editorHtml = textarea.quillInstance.root.innerHTML;
    if (editorHtml === textarea.accountEmailInitialEditorHtml) {
        return;
    }
    textarea.value = composeAccountEmailDocument(textarea.accountEmailDocumentTemplate || textarea.value, editorHtml);
}

function initAccountEmailQuill(textarea) {
    if (!textarea || textarea.dataset.accountEmailEditorInit === '1' || typeof window.Quill === 'undefined') {
        return;
    }
    textarea.dataset.accountEmailEditorInit = '1';

    const wrapper = document.createElement('div');
    wrapper.className = 'quill-container account-email-quill-container';
    const editor = document.createElement('div');
    wrapper.appendChild(editor);
    textarea.insertAdjacentElement('afterend', wrapper);
    textarea.classList.add('ui-admin-hidden');

    const quill = new Quill(editor, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [1, 2, 3, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                ['blockquote'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['link', 'image', 'video'],
                ['clean']
            ]
        }
    });

    const sourceDocument = textarea.value || '';
    const editableHtml = accountEmailEditableHtml(sourceDocument);
    textarea.accountEmailDocumentTemplate = sourceDocument;
    if (editableHtml) {
        try {
            quill.setContents(quill.clipboard.convert(editableHtml), 'silent');
        } catch (error) {
            quill.clipboard.dangerouslyPasteHTML(editableHtml, 'silent');
        }
    }
    textarea.accountEmailInitialEditorHtml = quill.root.innerHTML;
    textarea.quillInstance = quill;
    quill.on('text-change', function () {
        syncAccountEmailEditor(textarea);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

function ensureAccountEmailRichEditors(attempt) {
    if (accountEmailEditorInitStarted && document.querySelector('textarea.account-email-body[data-account-email-editor-init="1"]')) {
        return;
    }
    attempt = Number(attempt || 0);
    if (typeof window.Quill === 'undefined' && attempt < 8) {
        window.setTimeout(function () {
            ensureAccountEmailRichEditors(attempt + 1);
        }, 150);
        return;
    }

    accountEmailEditorInitStarted = true;
    document.querySelectorAll('textarea.account-email-body').forEach(initAccountEmailQuill);
}

function initAccountEmailTemplates(adminNotificationsPageData) {
    const modal = document.getElementById('notificationPreviewModal');
    const modalContent = modal?.querySelector('[data-notification-preview-content]');
    const modalTitle = modal?.querySelector('#notificationPreviewTitle');
    const modalChannel = modal?.querySelector('[data-notification-preview-channel]');
    const closeButton = modal?.querySelector('button[data-notification-preview-close]');
    const closeControls = modal ? Array.from(modal.querySelectorAll('[data-notification-preview-close]')) : [];
    const samplePayload = adminNotificationsPageData.accountEmailPreviewPayload || {};
    let activeCard = null;
    let restoreFocus = null;

    const renderTemplate = function (template, payload) {
        return String(template || '').replace(/{{\s*([a-zA-Z0-9_]+)\s*}}/g, function (_, key) {
            const value = Object.prototype.hasOwnProperty.call(payload, key) ? payload[key] : '';
            return value === null || typeof value === 'undefined' ? '' : String(value);
        }).trim();
    };

    const refreshAccountModal = function (card) {
        if (!modalContent || !modalTitle || !modalChannel || !card) {
            return;
        }

        const body = card.querySelector('.account-email-body');
        const subject = card.querySelector('input[name$="_subject"]');
        syncAccountEmailEditor(body);

        const subjectText = renderTemplate(subject ? subject.value : '', samplePayload) || 'E-posta konusu';
        const html = renderTemplate(body ? body.value : '', samplePayload) || '<p>Önizleme içeriği bulunamadı.</p>';
        modalTitle.textContent = 'Hesap E-Posta Önizlemesi';
        modalChannel.textContent = 'Hesap E-Posta Şablonları';
        modalContent.className = 'notification-template-preview notification-preview-modal-content account-email-preview-modal';
        modalContent.replaceChildren();

        const label = document.createElement('small');
        label.textContent = 'Konu';

        const subjectNode = document.createElement('strong');
        subjectNode.textContent = subjectText;

        const frame = document.createElement('iframe');
        frame.className = 'account-email-preview-frame';
        frame.setAttribute('sandbox', '');
        frame.setAttribute('title', 'Hesap e-posta şablonu önizlemesi');
        frame.srcdoc = html;

        modalContent.append(label, subjectNode, frame);
    };

    const openAccountPreview = function (card, trigger) {
        if (!modal || !modalContent) {
            return;
        }

        activeCard = card;
        restoreFocus = trigger || null;
        refreshAccountModal(card);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('notification-preview-modal-open');
        closeButton?.focus();
    };

    const closeAccountPreview = function () {
        const shouldRestore = activeCard !== null;
        if (!modal) {
            return;
        }
        if (!modal.hidden) {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('notification-preview-modal-open');
        }
        activeCard = null;
        if (shouldRestore && restoreFocus && document.contains(restoreFocus)) {
            restoreFocus.focus();
        }
        restoreFocus = null;
    };

    ensureAccountEmailRichEditors();

    document.querySelectorAll('[data-account-email-card]').forEach(function (card) {
        const body = card.querySelector('.account-email-body');
        const subject = card.querySelector('input[name$="_subject"]');
        const previewButton = card.querySelector('.account-email-preview-button');
        const resetButton = card.querySelector('.account-email-reset');

        card.addEventListener('submit', function () {
            syncAccountEmailEditor(body);
        });

        [body, subject].forEach(function (field) {
            if (!field) {
                return;
            }
            field.addEventListener('input', function () {
                if (activeCard === card && modal && !modal.hidden) {
                    refreshAccountModal(card);
                }
            });
        });

        if (previewButton) {
            previewButton.addEventListener('click', function () {
                openAccountPreview(card, previewButton);
            });
        }

        card.querySelectorAll('.account-email-token').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!body) {
                    return;
                }
                const token = String(button.getAttribute('data-token') || '');
                if (body.quillInstance) {
                    const range = body.quillInstance.getSelection(true);
                    const index = range ? range.index : Math.max(0, body.quillInstance.getLength() - 1);
                    body.quillInstance.insertText(index, token, 'user');
                    body.quillInstance.setSelection(index + token.length, 0, 'silent');
                    syncAccountEmailEditor(body);
                    body.dispatchEvent(new Event('input', { bubbles: true }));
                    if (activeCard === card && modal && !modal.hidden) {
                        refreshAccountModal(card);
                    }
                    return;
                }

                const start = body.selectionStart || body.value.length;
                const end = body.selectionEnd || body.value.length;
                body.value = body.value.slice(0, start) + token + body.value.slice(end);
                body.focus();
                body.selectionStart = body.selectionEnd = start + token.length;
                body.dispatchEvent(new Event('input', { bubbles: true }));
                if (activeCard === card && modal && !modal.hidden) {
                    refreshAccountModal(card);
                }
            });
        });

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                const defaultBody = card.querySelector('.account-email-default-body');
                if (subject) {
                    subject.value = String(resetButton.getAttribute('data-default-subject') || '');
                }
                if (body && defaultBody) {
                    setAccountEmailEditorValue(body, defaultBody.value);
                }
                card.dispatchEvent(new CustomEvent('notification-variable-refresh', { bubbles: true }));
                if (activeCard === card && modal && !modal.hidden) {
                    refreshAccountModal(card);
                }
            });
        }
    });

    closeControls.forEach(function (control) {
        if (control.dataset.accountEmailPreviewCloseBound === '1') {
            return;
        }
        control.dataset.accountEmailPreviewCloseBound = '1';
        control.addEventListener('click', closeAccountPreview);
    });

    if (modal && modal.dataset.accountEmailPreviewEscapeBound !== '1') {
        modal.dataset.accountEmailPreviewEscapeBound = '1';
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAccountPreview();
            }
        });
    }
}

function parseNotificationDataList(value) {
    try {
        const parsed = JSON.parse(value || '[]');
        return Array.isArray(parsed) ? parsed.map(String) : [];
    } catch (error) {
        return [];
    }
}

function extractNotificationVariables(value) {
    const variables = [];
    String(value || '').replace(/{{\s*([a-zA-Z0-9_]+)\s*}}/g, function (_, key) {
        if (!variables.includes(key)) {
            variables.push(key);
        }
        return '';
    });
    return variables;
}

function findFormFieldByName(form, name) {
    const matches = Array.from(form.elements || []).filter(function (field) {
        return field && field.name === name;
    });
    return matches.find(function (field) {
        return field.type === 'checkbox' || field.type === 'radio';
    }) || matches[0] || null;
}

function notificationVariableReport(form) {
    const fields = String(form.dataset.variableFields || '')
        .split(',')
        .map(function (field) { return field.trim(); })
        .filter(Boolean);
    const allowed = parseNotificationDataList(form.dataset.variableAllowed);
    const required = parseNotificationDataList(form.dataset.variableRequired);
    let source = '';

    fields.forEach(function (name) {
        const field = findFormFieldByName(form, name);
        if (!field) {
            return;
        }
        if (field.classList && field.classList.contains('account-email-body')) {
            syncAccountEmailEditor(field);
        }
        source += '\n' + String(field.value || '');
    });

    const used = extractNotificationVariables(source);
    return {
        used,
        unknown: used.filter(function (name) { return !allowed.includes(name); }),
        missing: required.filter(function (name) { return !used.includes(name); })
    };
}

function shouldEnforceNotificationVariables(form, submitter) {
    const mode = form.dataset.variableEnforceRequired || '0';
    if (mode === '1') {
        return true;
    }
    if (mode !== 'conditional') {
        return false;
    }
    const action = submitter && submitter.name === 'action' ? String(submitter.value || '') : '';
    if (action.indexOf('test') !== -1) {
        return true;
    }
    const toggleName = form.dataset.variableRequiredToggle || '';
    const toggle = toggleName ? findFormFieldByName(form, toggleName) : null;
    return Boolean(toggle && toggle.checked);
}

function renderNotificationVariableStatus(form) {
    const status = form.querySelector('[data-variable-status]');
    if (!status) {
        return notificationVariableReport(form);
    }

    const report = notificationVariableReport(form);
    status.replaceChildren();
    status.className = 'is-wide notification-variable-status';
    if (report.unknown.length > 0) {
        status.classList.add('is-danger');
    } else if (report.missing.length > 0) {
        status.classList.add('is-warning');
    } else {
        status.classList.add('is-ok');
    }

    const summary = document.createElement('div');
    summary.className = 'notification-variable-summary';

    const icon = document.createElement('i');
    icon.className = report.unknown.length > 0
        ? 'bi bi-x-circle'
        : (report.missing.length > 0 ? 'bi bi-exclamation-triangle' : 'bi bi-check2-circle');
    icon.setAttribute('aria-hidden', 'true');

    const text = document.createElement('span');
    text.textContent = report.unknown.length > 0
        ? 'Bilinmeyen değişken var'
        : (report.missing.length > 0 ? 'Zorunlu değişken eksik' : 'Değişkenler uygun');
    summary.append(icon, text);
    status.appendChild(summary);

    const details = [];
    if (report.used.length > 0) {
        details.push('Kullanılan: ' + report.used.map(function (name) { return '{{' + name + '}}'; }).join(', '));
    } else {
        details.push('Kullanılan değişken yok.');
    }
    if (report.missing.length > 0) {
        details.push('Eksik: ' + report.missing.map(function (name) { return '{{' + name + '}}'; }).join(', '));
    }
    if (report.unknown.length > 0) {
        details.push('Bilinmeyen: ' + report.unknown.map(function (name) { return '{{' + name + '}}'; }).join(', '));
    }

    const detail = document.createElement('small');
    detail.textContent = details.join(' ');
    status.appendChild(detail);

    return report;
}

function initNotificationVariableControls() {
    document.querySelectorAll('form[data-variable-control="1"]').forEach(function (form) {
        if (form.dataset.variableControlBound === '1') {
            return;
        }
        form.dataset.variableControlBound = '1';

        const refresh = function () {
            renderNotificationVariableStatus(form);
        };
        const fields = String(form.dataset.variableFields || '')
            .split(',')
            .map(function (field) { return field.trim(); })
            .filter(Boolean);

        fields.forEach(function (name) {
            const field = findFormFieldByName(form, name);
            if (!field) {
                return;
            }
            field.addEventListener('input', refresh);
            field.addEventListener('change', refresh);
        });

        const toggleName = form.dataset.variableRequiredToggle || '';
        const toggle = toggleName ? findFormFieldByName(form, toggleName) : null;
        if (toggle) {
            toggle.addEventListener('change', refresh);
        }

        form.addEventListener('notification-variable-refresh', refresh);
        form.addEventListener('submit', function (event) {
            const submitter = event.submitter || null;
            const action = submitter && submitter.name === 'action' ? String(submitter.value || '') : '';
            if (['reset_template', 'delete_template', 'reset_admin_registration_site_template'].includes(action)) {
                return;
            }

            const report = renderNotificationVariableStatus(form);
            const enforceRequired = shouldEnforceNotificationVariables(form, submitter);
            if (report.unknown.length > 0 || (enforceRequired && report.missing.length > 0)) {
                event.preventDefault();
                form.querySelector('[data-variable-status]')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });

        refresh();
    });
}

function initAdminEmailTemplates(adminNotificationsPageData) {
    const modal = document.getElementById('notificationPreviewModal');
    const modalContent = modal?.querySelector('[data-notification-preview-content]');
    const modalTitle = modal?.querySelector('#notificationPreviewTitle');
    const modalChannel = modal?.querySelector('[data-notification-preview-channel]');
    const closeButton = modal?.querySelector('button[data-notification-preview-close]');
    const closeControls = modal ? Array.from(modal.querySelectorAll('[data-notification-preview-close]')) : [];
    const samplePayload = adminNotificationsPageData.adminEmailPreviewPayload || {};
    let activeCard = null;
    let restoreFocus = null;

    const renderTemplate = function (template, payload) {
        return String(template || '').replace(/{{\s*([a-zA-Z0-9_]+)\s*}}/g, function (_, key) {
            const value = Object.prototype.hasOwnProperty.call(payload, key) ? payload[key] : '';
            return value === null || typeof value === 'undefined' ? '' : String(value);
        }).trim();
    };

    const escapeHtml = function (value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char] || char;
        });
    };

    const plainTextToPreviewHtml = function (value) {
        const lines = String(value || '').replace(/\r\n|\r/g, '\n').split(/\n{2,}/);
        return lines.map(function (block) {
            const text = block.trim();
            if (!text) {
                return '';
            }
            return '<p style="margin:0 0 14px;color:#344054;font-size:15px;line-height:1.65;">' + escapeHtml(text).replace(/\n/g, '<br>') + '</p>';
        }).join('');
    };

    const previewDocument = function (html) {
        if (/<(?:!doctype|html|body)\b/i.test(html)) {
            return html;
        }
        const content = /<[a-z][a-z0-9:-]*(?:\s[^>]*)?>/i.test(html) ? html : plainTextToPreviewHtml(html);
        return '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:20px;background:#f3f5f8;color:#172033;font-family:Segoe UI,Roboto,sans-serif;"><div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #e4e8ef;border-radius:14px;padding:24px;">' + content + '</div></body></html>';
    };

    const refreshAdminModal = function (card) {
        if (!modalContent || !modalTitle || !modalChannel || !card) {
            return;
        }

        const body = card.querySelector('.admin-email-body');
        const subject = card.querySelector('input[name$="_subject"]');
        const actionLabel = card.querySelector('input[name$="_action_label"]');
        const subjectText = renderTemplate(subject ? subject.value : '', samplePayload) || 'E-posta konusu';
        const bodyHtml = renderTemplate(body ? body.value : '', samplePayload) || '<p>Önizleme içeriği bulunamadı.</p>';
        const actionText = renderTemplate(actionLabel ? actionLabel.value : '', samplePayload);

        modalTitle.textContent = 'Yönetici E-Posta Önizlemesi';
        modalChannel.textContent = 'Yönetici E-Postaları';
        modalContent.className = 'notification-template-preview notification-preview-modal-content account-email-preview-modal admin-email-preview-modal';
        modalContent.replaceChildren();

        const label = document.createElement('small');
        label.textContent = 'Konu';
        const subjectNode = document.createElement('strong');
        subjectNode.textContent = subjectText;
        const action = document.createElement('span');
        action.className = 'admin-email-preview-action';
        action.textContent = actionText ? 'Buton: ' + actionText : 'Buton metni eklenmemiş.';
        const frame = document.createElement('iframe');
        frame.className = 'account-email-preview-frame';
        frame.setAttribute('sandbox', '');
        frame.setAttribute('title', 'Yönetici e-posta şablonu önizlemesi');
        frame.srcdoc = previewDocument(bodyHtml);

        modalContent.append(label, subjectNode, action, frame);
    };

    const openAdminPreview = function (card, trigger) {
        if (!modal || !modalContent) {
            return;
        }
        activeCard = card;
        restoreFocus = trigger || null;
        refreshAdminModal(card);
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('notification-preview-modal-open');
        closeButton?.focus();
    };

    const closeAdminPreview = function () {
        const shouldRestore = activeCard !== null;
        if (!modal) {
            return;
        }
        if (!modal.hidden) {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('notification-preview-modal-open');
        }
        activeCard = null;
        if (shouldRestore && restoreFocus && document.contains(restoreFocus)) {
            restoreFocus.focus();
        }
        restoreFocus = null;
    };

    document.querySelectorAll('[data-admin-email-card]').forEach(function (card) {
        const body = card.querySelector('.admin-email-body');
        const subject = card.querySelector('input[name$="_subject"]');
        const actionLabel = card.querySelector('input[name$="_action_label"]');
        const previewButton = card.querySelector('.admin-email-preview-button');
        const resetButton = card.querySelector('.admin-email-reset');

        [body, subject, actionLabel].forEach(function (field) {
            if (!field) {
                return;
            }
            field.addEventListener('input', function () {
                if (activeCard === card && modal && !modal.hidden) {
                    refreshAdminModal(card);
                }
            });
        });

        card.querySelectorAll('.admin-email-token').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!body) {
                    return;
                }
                const token = String(button.getAttribute('data-token') || '');
                const start = body.selectionStart || body.value.length;
                const end = body.selectionEnd || body.value.length;
                body.value = body.value.slice(0, start) + token + body.value.slice(end);
                body.focus();
                body.selectionStart = body.selectionEnd = start + token.length;
                body.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });

        if (previewButton) {
            previewButton.addEventListener('click', function () {
                openAdminPreview(card, previewButton);
            });
        }

        if (resetButton) {
            resetButton.addEventListener('click', function () {
                const defaultBody = card.querySelector('.admin-email-default-body');
                if (subject) {
                    subject.value = String(resetButton.getAttribute('data-default-subject') || '');
                    subject.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (actionLabel) {
                    actionLabel.value = String(resetButton.getAttribute('data-default-action-label') || '');
                    actionLabel.dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (body && defaultBody) {
                    body.value = defaultBody.value;
                    body.dispatchEvent(new Event('input', { bubbles: true }));
                }
                card.dispatchEvent(new CustomEvent('notification-variable-refresh', { bubbles: true }));
                if (activeCard === card && modal && !modal.hidden) {
                    refreshAdminModal(card);
                }
            });
        }
    });

    closeControls.forEach(function (control) {
        if (control.dataset.adminEmailPreviewCloseBound === '1') {
            return;
        }
        control.dataset.adminEmailPreviewCloseBound = '1';
        control.addEventListener('click', closeAdminPreview);
    });

    if (modal && modal.dataset.adminEmailPreviewEscapeBound !== '1') {
        modal.dataset.adminEmailPreviewEscapeBound = '1';
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAdminPreview();
            }
        });
    }
}

function initNotificationsPage() {
    const adminNotificationsPageData = getAdminNotificationsPageData();
    initNotificationComposerTemplates(adminNotificationsPageData);
    initNotificationTemplatePreviews(adminNotificationsPageData);
    initAccountEmailTemplates(adminNotificationsPageData);
    initAdminEmailTemplates(adminNotificationsPageData);
    initNotificationVariableControls();
}

window.adminPage.register('notifications', initNotificationsPage, {
    id: 'notifications-page',
    selector: '#adminNotificationsPageData'
});
