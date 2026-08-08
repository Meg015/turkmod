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

function notificationStatusCards(group) {
    return Array.from(document.querySelectorAll('[data-notification-status-card]')).filter(function (card) {
        return String(card.dataset.notificationStatusGroup || '') === group;
    });
}

function notificationStatusCountTargets(group, state) {
    return Array.from(document.querySelectorAll('[data-notification-status-count]')).filter(function (target) {
        return String(target.dataset.notificationStatusGroup || '') === group
            && String(target.dataset.notificationStatusCount || '') === state;
    });
}

function setNotificationStatusBadge(badge, enabled) {
    if (!badge) {
        return;
    }

    const label = badge.querySelector('[data-notification-status-label]');
    const icon = badge.querySelector('i');
    const labelText = enabled ? badge.dataset.activeLabel : badge.dataset.inactiveLabel;
    const iconClass = enabled ? badge.dataset.activeIcon : badge.dataset.inactiveIcon;

    badge.classList.toggle('notif-badge-global', enabled);
    badge.classList.toggle('notif-badge-user', !enabled);
    badge.setAttribute('aria-label', String(labelText || (enabled ? 'Aktif' : 'Kapalı')));
    if (label) {
        label.textContent = String(labelText || (enabled ? 'Aktif' : 'Kapalı'));
    }
    if (icon && iconClass) {
        icon.className = 'bi ' + String(iconClass);
    }
}

function syncNotificationStatusGroup(group) {
    const cards = notificationStatusCards(group);
    if (!group || cards.length === 0) {
        return;
    }

    let active = 0;
    cards.forEach(function (card) {
        const toggle = card.querySelector('[data-notification-status-toggle]');
        if (!toggle) {
            return;
        }
        const enabled = Boolean(toggle.checked);
        if (enabled) {
            active += 1;
        }
        card.classList.toggle('is-notification-disabled', !enabled);
        setNotificationStatusBadge(card.querySelector('[data-notification-status-badge]'), enabled);
    });

    const counts = {
        active,
        inactive: Math.max(0, cards.length - active)
    };
    Object.keys(counts).forEach(function (state) {
        notificationStatusCountTargets(group, state).forEach(function (target) {
            const valueTarget = target.querySelector('.stat-value') || target;
            valueTarget.textContent = String(counts[state]) + String(target.dataset.notificationStatusCountSuffix || '');
        });
    });
}

function syncNotificationStatusMirrors() {
    document.querySelectorAll('[data-notification-status-mirror-toggle]').forEach(function (toggle) {
        const key = String(toggle.dataset.notificationStatusMirrorToggle || '');
        if (!key) {
            return;
        }
        document.querySelectorAll('[data-notification-status-mirror-target]').forEach(function (target) {
            if (String(target.dataset.notificationStatusMirrorTarget || '') !== key) {
                return;
            }
            setNotificationStatusBadge(target, Boolean(toggle.checked));
        });
    });
}

function syncNotificationStatusUi() {
    const groups = new Set();
    document.querySelectorAll('[data-notification-status-card]').forEach(function (card) {
        const group = String(card.dataset.notificationStatusGroup || '');
        if (group) {
            groups.add(group);
        }
    });
    groups.forEach(syncNotificationStatusGroup);
    syncNotificationStatusMirrors();
}

function initNotificationStatusUi() {
    if (document.documentElement.dataset.notificationStatusUiBound === '1') {
        syncNotificationStatusUi();
        return;
    }
    document.documentElement.dataset.notificationStatusUiBound = '1';

    document.addEventListener('change', function (event) {
        const toggle = event.target.closest('[data-notification-status-toggle]');
        if (toggle) {
            const card = toggle.closest('[data-notification-status-card]');
            syncNotificationStatusGroup(String(card?.dataset.notificationStatusGroup || ''));
        }
        if (event.target.closest('[data-notification-status-mirror-toggle]')) {
            syncNotificationStatusMirrors();
        }
    });
    window.addEventListener('pageshow', syncNotificationStatusUi);
    syncNotificationStatusUi();
}

function notificationSubmissionAction(form, submitter) {
    if (submitter && submitter.name === 'action') {
        return String(submitter.value || '');
    }
    const actionField = Array.from(form.elements || []).find(function (field) {
        return field && field.name === 'action';
    });
    return actionField ? String(actionField.value || '') : '';
}

function initNotificationSubmissionState() {
    document.querySelectorAll('form').forEach(function (form) {
        if (!form.closest('.notification-template-page') || form.dataset.notificationSubmitStateBound === '1') {
            return;
        }
        form.dataset.notificationSubmitStateBound = '1';
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }
            const submitter = event.submitter || null;
            const action = notificationSubmissionAction(form, submitter);
            if (!/^(?:save_|send_.*(?:test|email)|send_site_test)/.test(action)) {
                return;
            }

            const buttons = Array.from(form.querySelectorAll('button[type="submit"], input[type="submit"]'));
            buttons.forEach(function (button) {
                button.disabled = true;
            });
            if (submitter && submitter.tagName === 'BUTTON') {
                submitter.dataset.originalHtml = submitter.innerHTML;
                const sending = action.includes('send_');
                submitter.innerHTML = '<i class="bi bi-arrow-repeat"></i> ' + (sending ? 'Gönderiliyor...' : 'Kaydediliyor...');
                submitter.setAttribute('aria-busy', 'true');
            }
        });
    });
}

function initBulkEmailCampaigns(adminNotificationsPageData) {
    const root = document.querySelector('[data-bulk-email-root]');
    const form = root?.querySelector('[data-bulk-composer]');
    if (!root || !form || root.dataset.bulkEmailBound === '1') {
        return;
    }
    root.dataset.bulkEmailBound = '1';

    const config = adminNotificationsPageData.bulkEmail || {};
    const api = String(config.api || '');
    const subject = form.querySelector('[name="subject"]');
    const body = form.querySelector('[name="body_html"]');
    const campaignIdField = form.querySelector('[data-bulk-campaign-id]');
    const testEmail = form.querySelector('[data-bulk-test-email]');
    const frame = form.querySelector('[data-bulk-preview-frame]');
    const stage = form.querySelector('[data-bulk-preview-stage]');
    const loading = form.querySelector('[data-bulk-preview-loading]');
    const previewSubject = form.querySelector('[data-bulk-preview-subject]');
    const errorBox = form.querySelector('[data-bulk-error]');
    const saveState = form.querySelector('[data-bulk-save-state]');
    const progressCard = root.querySelector('[data-bulk-progress-card]');
    let csrf = String(config.csrf || form.querySelector('[name="_token"]')?.value || '');
    let previewTimer = 0;
    let previewRequest = 0;
    let pollTimer = 0;
    let quill = null;

    const statusLabels = {
        draft: 'Taslak', preparing: 'Alıcılar hazırlanıyor', queued: 'Sırada', sending: 'Gönderiliyor',
        paused: 'Duraklatıldı', completed: 'Tamamlandı', cancelled: 'İptal edildi'
    };

    const syncBody = function () {
        if (quill && body) {
            body.value = quill.root.innerHTML;
        }
        return String(body?.value || '');
    };

    const setBusy = function (button, busy, label) {
        if (!button) {
            return;
        }
        if (busy) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = '<i class="bi bi-arrow-repeat"></i> ' + label;
            return;
        }
        button.disabled = false;
        button.removeAttribute('aria-busy');
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    };

    const showError = function (message) {
        if (!errorBox) {
            return;
        }
        errorBox.textContent = String(message || 'İşlem tamamlanamadı.');
        errorBox.hidden = false;
    };

    const clearError = function () {
        if (errorBox) {
            errorBox.hidden = true;
            errorBox.textContent = '';
        }
    };

    const request = async function (action, values) {
        const payload = new FormData();
        payload.set('action', action);
        payload.set('_token', csrf);
        Object.entries(values || {}).forEach(function (entry) {
            payload.set(entry[0], String(entry[1] ?? ''));
        });
        const response = await fetch(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
            body: payload
        });
        const data = await response.json().catch(function () { return {}; });
        if (data.csrfToken) {
            csrf = String(data.csrfToken);
            const token = form.querySelector('[name="_token"]');
            if (token) {
                token.value = csrf;
            }
        }
        if (!response.ok || data.success === false) {
            throw new Error(String(data.message || 'İşlem tamamlanamadı.'));
        }
        return data;
    };

    const renderPreview = async function (subjectValue, bodyValue) {
        const requestId = ++previewRequest;
        const normalizedSubject = String(subjectValue || '').trim();
        const normalizedBody = String(bodyValue || '').trim();
        if (!normalizedSubject || !normalizedBody) {
            if (frame) {
                frame.srcdoc = '';
            }
            if (previewSubject) {
                previewSubject.textContent = 'Önizleme için konu ve içerik girin';
            }
            clearError();
            loading?.setAttribute('hidden', '');
            return;
        }
        loading?.removeAttribute('hidden');
        try {
            const data = await request('preview', {
                subject: subjectValue,
                body_html: bodyValue
            });
            if (requestId !== previewRequest) {
                return;
            }
            const preview = data.preview || {};
            if (frame) {
                frame.srcdoc = String(preview.html || '');
            }
            if (previewSubject) {
                previewSubject.textContent = String(preview.subject || 'Önizleme');
            }
            clearError();
        } catch (error) {
            if (requestId === previewRequest) {
                // Empty composer state is expected on first load; reserve errors for explicit actions.
                clearError();
                if (frame) {
                    frame.srcdoc = '';
                }
                if (previewSubject) {
                    previewSubject.textContent = 'Önizleme için konu ve içerik girin';
                }
            }
        } finally {
            if (requestId === previewRequest && loading) {
                loading.setAttribute('hidden', '');
            }
        }
    };

    const schedulePreview = function () {
        window.clearTimeout(previewTimer);
        previewTimer = window.setTimeout(function () {
            renderPreview(String(subject?.value || ''), syncBody());
        }, 420);
    };

    const setEditorValue = function (value) {
        if (!body) {
            return;
        }
        body.value = String(value || '');
        if (quill) {
            quill.clipboard.dangerouslyPasteHTML(body.value, 'silent');
        }
        schedulePreview();
    };

    const initEditor = function () {
        if (!body || typeof window.Quill === 'undefined') {
            body?.addEventListener('input', schedulePreview);
            return;
        }
        const host = document.createElement('div');
        host.className = 'bulk-email-quill';
        body.insertAdjacentElement('afterend', host);
        body.hidden = true;
        quill = new window.Quill(host, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'code-block'],
                        [{ align: [] }],
                        ['link', 'image'],
                        ['clean']
                    ],
                    handlers: {
                        image: function () {
                            const prompt = typeof window.appPrompt === 'function'
                                ? window.appPrompt('Görsel adresi', { placeholder: 'https://...', ok: 'Ekle', icon: 'bi-image' })
                                : Promise.resolve(window.prompt('Görsel URL adresi'));
                            prompt.then(function (url) {
                                url = String(url || '').trim();
                                if (!/^https?:\/\//i.test(url)) {
                                    return;
                                }
                                const range = quill.getSelection(true);
                                quill.insertEmbed(range ? range.index : quill.getLength() - 1, 'image', url, 'user');
                            });
                        }
                    }
                }
            }
        });
        quill.clipboard.dangerouslyPasteHTML(body.value, 'silent');
        quill.on('text-change', function () {
            syncBody();
            schedulePreview();
        });
    };

    const updateProgress = function (campaign) {
        if (!progressCard || !campaign) {
            return;
        }
        progressCard.hidden = false;
        progressCard.classList.remove('bulk-email-progress-empty');
        progressCard.dataset.campaignId = String(campaign.id || '');
        progressCard.dataset.status = String(campaign.status || '');
        const percent = Math.max(0, Math.min(100, Number(campaign.progress_percent || 0)));
        const status = String(campaign.status || 'draft');
        const statusNode = progressCard.querySelector('[data-bulk-status]');
        const percentNode = progressCard.querySelector('[data-bulk-percent]');
        const bar = progressCard.querySelector('[data-bulk-progress-bar]');
        const track = progressCard.querySelector('.bulk-email-progress-track');
        const title = progressCard.querySelector('[data-bulk-progress-subject]');
        if (statusNode) {
            statusNode.textContent = statusLabels[status] || status;
        }
        if (percentNode) {
            percentNode.textContent = percent + '%';
        }
        if (bar) {
            bar.style.width = percent + '%';
        }
        track?.setAttribute('aria-valuenow', String(percent));
        if (title) {
            title.textContent = String(campaign.subject_template || 'Toplu e-posta kampanyası');
        }
        progressCard.querySelectorAll('[data-bulk-count]').forEach(function (node) {
            node.textContent = Number(campaign[node.dataset.bulkCount] || 0).toLocaleString('tr-TR');
        });
        progressCard.querySelectorAll('[data-bulk-action]').forEach(function (button) {
            const action = button.dataset.bulkAction;
            const visible = (action === 'pause' && ['preparing', 'queued', 'sending'].includes(status))
                || (action === 'resume' && status === 'paused')
                || (action === 'cancel' && ['preparing', 'queued', 'sending', 'paused'].includes(status))
                || (action === 'retry' && status !== 'cancelled' && Number(campaign.failed_count || 0) > 0);
            button.hidden = !visible;
        });
        if (['completed', 'cancelled'].includes(status)) {
            window.clearTimeout(pollTimer);
        } else {
            schedulePoll(Number(campaign.id || 0));
        }
    };

    const poll = async function (campaignId) {
        if (!campaignId || document.hidden) {
            schedulePoll(campaignId);
            return;
        }
        try {
            const response = await fetch(api + '?campaign_id=' + encodeURIComponent(campaignId), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (response.ok && data.campaign) {
                updateProgress(data.campaign);
            }
        } catch (error) {
            schedulePoll(campaignId);
        }
    };

    const schedulePoll = function (campaignId) {
        window.clearTimeout(pollTimer);
        if (campaignId > 0) {
            pollTimer = window.setTimeout(function () { poll(campaignId); }, 4000);
        }
    };

    const submitContentAction = async function (action, button, extra) {
        clearError();
        setBusy(button, true, action === 'test' ? 'Gönderiliyor...' : 'Kaydediliyor...');
        try {
            const data = await request(action, Object.assign({
                campaign_id: campaignIdField?.value || '',
                subject: subject?.value || '',
                body_html: syncBody()
            }, extra || {}));
            if (data.campaign) {
                if (action === 'save' && campaignIdField) {
                    campaignIdField.value = String(data.campaign.id || '');
                    if (saveState) {
                        saveState.textContent = 'Taslak #' + data.campaign.id + ' kaydedildi';
                    }
                }
                updateProgress(data.campaign);
                if (action === 'start' && campaignIdField) {
                    campaignIdField.value = '';
                    if (saveState) {
                        saveState.textContent = 'Yeni taslak';
                    }
                }
            }
            if (typeof window.showToast === 'function') {
                window.showToast(String(data.message || 'İşlem tamamlandı.'), 'success');
            }
            return data;
        } catch (error) {
            showError(error.message);
            if (typeof window.showToast === 'function') {
                window.showToast(error.message, 'error');
            }
            return null;
        } finally {
            setBusy(button, false);
        }
    };

    initEditor();
    subject?.addEventListener('input', schedulePreview);
    form.querySelectorAll('[data-bulk-token]').forEach(function (button) {
        button.addEventListener('click', function () {
            const token = String(button.dataset.bulkToken || '');
            if (quill) {
                const range = quill.getSelection(true);
                const index = range ? range.index : Math.max(0, quill.getLength() - 1);
                quill.insertText(index, token, 'user');
                quill.setSelection(index + token.length, 0, 'silent');
            } else if (body) {
                const start = body.selectionStart || body.value.length;
                body.value = body.value.slice(0, start) + token + body.value.slice(body.selectionEnd || start);
                schedulePreview();
            }
        });
    });
    form.querySelectorAll('[data-bulk-device]').forEach(function (button) {
        button.addEventListener('click', function () {
            form.querySelectorAll('[data-bulk-device]').forEach(function (item) { item.classList.toggle('is-active', item === button); });
            stage?.classList.toggle('is-mobile', button.dataset.bulkDevice === 'mobile');
        });
    });
    form.querySelector('[data-bulk-save]')?.addEventListener('click', function (event) {
        submitContentAction('save', event.currentTarget);
    });
    form.querySelector('[data-bulk-test]')?.addEventListener('click', function (event) {
        submitContentAction('test', event.currentTarget, { test_email: testEmail?.value || '' });
    });
    form.querySelector('[data-bulk-start]')?.addEventListener('click', async function (event) {
        const button = event.currentTarget;
        const count = Number(config.eligibleCount || 0).toLocaleString('tr-TR');
        const confirmed = typeof window.appConfirm === 'function'
            ? await window.appConfirm(count + ' uygun üyeye gönderim kuyruğu oluşturulacak.', { title: 'Gönderim başlatılsın mı?', ok: 'Gönderimi Başlat', icon: 'bi-send' })
            : window.confirm(count + ' uygun üyeye gönderim başlatılsın mı?');
        if (confirmed) {
            submitContentAction('start', button);
        }
    });
    root.addEventListener('click', async function (event) {
        const actionButton = event.target.closest('[data-bulk-action]');
        if (actionButton && progressCard) {
            const action = String(actionButton.dataset.bulkAction || '');
            if (action === 'cancel') {
                const confirmed = typeof window.appConfirm === 'function'
                    ? await window.appConfirm('Henüz gönderilmemiş alıcılar iptal edilecek.', { title: 'Kampanya iptal edilsin mi?', ok: 'İptal Et', icon: 'bi-x-octagon' })
                    : window.confirm('Kampanya iptal edilsin mi?');
                if (!confirmed) {
                    return;
                }
            }
            setBusy(actionButton, true, 'İşleniyor...');
            try {
                const data = await request(action, { campaign_id: progressCard.dataset.campaignId || '' });
                updateProgress(data.campaign);
                window.showToast?.(String(data.message || 'Kampanya güncellendi.'), 'success');
            } catch (error) {
                showError(error.message);
                window.showToast?.(error.message, 'error');
            } finally {
                setBusy(actionButton, false);
            }
            return;
        }

        const loadButton = event.target.closest('[data-bulk-edit], [data-bulk-history-preview]');
        if (!loadButton) {
            return;
        }
        const id = Number(loadButton.dataset.bulkEdit || loadButton.dataset.bulkHistoryPreview || 0);
        if (!id) {
            return;
        }
        setBusy(loadButton, true, 'Yükleniyor...');
        try {
            const response = await fetch(api + '?campaign_id=' + encodeURIComponent(id), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.campaign) {
                throw new Error(String(data.message || 'Kampanya yüklenemedi.'));
            }
            if (loadButton.hasAttribute('data-bulk-edit')) {
                subject.value = String(data.campaign.subject_template || '');
                setEditorValue(data.campaign.body_html_template || '');
                campaignIdField.value = String(data.campaign.id || '');
                if (saveState) {
                    saveState.textContent = 'Taslak #' + data.campaign.id;
                }
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                await renderPreview(String(data.campaign.subject_template || ''), String(data.campaign.body_html_template || ''));
                form.querySelector('.bulk-email-preview-panel')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        } catch (error) {
            showError(error.message);
        } finally {
            setBusy(loadButton, false);
        }
    });

    updateProgress(config.activeCampaign || null);
    schedulePreview();
}

function initNotificationsPage() {
    const adminNotificationsPageData = getAdminNotificationsPageData();
    initNotificationComposerTemplates(adminNotificationsPageData);
    initNotificationTemplatePreviews(adminNotificationsPageData);
    initAccountEmailTemplates(adminNotificationsPageData);
    initAdminEmailTemplates(adminNotificationsPageData);
    initNotificationVariableControls();
    initNotificationStatusUi();
    initNotificationSubmissionState();
    initBulkEmailCampaigns(adminNotificationsPageData);
}

window.adminPage.register('notifications', initNotificationsPage, {
    id: 'notifications-page',
    selector: '#adminNotificationsPageData'
});
