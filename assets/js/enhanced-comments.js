/**
 * Enhanced Comments System - Frontend
 * Reactions, Markdown, Mentions, Edit History
 */

(function () {
    'use strict';

    function fetchJson(url, options) {
        if (window.publicFetchJson) {
            return window.publicFetchJson(url, options || {});
        }

        return Promise.reject(new Error('Public API helper yuklenemedi.'));
    }

    // Initialize enhanced comments
    window.EnhancedComments = {
        init: function (config) {
            this.config = config || {};
            this.reactionsEnabled = config.reactionsEnabled !== false;
            this.markdownEnabled = config.markdownEnabled !== false;
            this.mentionsEnabled = config.mentionsEnabled !== false;
            this.editHistoryEnabled = config.editHistoryEnabled !== false;

            this.bindReactionButtons();
            this.bindEditHistoryButtons();
            this.initMarkdownToolbar();
            this.initMentionAutocomplete();
            this.setupMutationObserver();
        },

        // ─── Reactions ───────────────────────────────────────
        bindReactionButtons: function () {
            if (!this.reactionsEnabled) return;

            // Handle reaction button clicks
            document.addEventListener('click', (e) => {
                const reactionBtn = e.target.closest('.comment-reaction-btn');
                if (reactionBtn) {
                    e.preventDefault();
                    const commentId = parseInt(reactionBtn.dataset.commentId);
                    const reactionType = reactionBtn.dataset.reactionType;
                    this.toggleReaction(commentId, reactionType, reactionBtn);
                }
            });
        },

        toggleReaction: function (commentId, reactionType, btn) {
            const previousHtml = this.captureReactionState(commentId);
            this.applyOptimisticReaction(commentId, reactionType, btn);

            // Get CSRF token from section or input
            let csrfToken = document.querySelector('.topic-comments')?.dataset?.csrf || '';
            if (!csrfToken) {
                csrfToken = document.querySelector('input[name="_token"]')?.value || '';
            }

            if (!csrfToken) {
                this.restoreReactionState(commentId, previousHtml);
                this.showToast('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
                return;
            }

            const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
            fetchJson(baseUri + '/api/comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'react',
                    comment_id: commentId,
                    reaction_type: reactionType,
                    _token: csrfToken
                })
            })
                .then(data => {
                    if (data.success) {
                        this.updateReactionUI(commentId, data.reactions, data.user_reactions);

                        // Update CSRF token
                        if (data._token) {
                            const section = document.querySelector('.topic-comments');
                            if (section) section.dataset.csrf = data._token;
                            const tokenInput = document.querySelector('input[name="_token"]');
                            if (tokenInput) tokenInput.value = data._token;
                        }
                    } else {
                        this.restoreReactionState(commentId, previousHtml);
                        this.showToast(data.error || 'Reaksiyon eklenemedi', 'error');
                    }
                })
                .catch((err) => {
                    console.error('Reaction error:', err);
                    this.restoreReactionState(commentId, previousHtml);
                    this.showToast('Bir hata oluştu', 'error');
                });
        },

        captureReactionState: function (commentId) {
            const container = document.querySelector(`[data-comment-id="${commentId}"] .ui-comment-reactions`);
            return container ? container.innerHTML : '';
        },

        restoreReactionState: function (commentId, html) {
            const container = document.querySelector(`[data-comment-id="${commentId}"] .ui-comment-reactions`);
            if (container && typeof html === 'string') container.innerHTML = html;
        },

        applyOptimisticReaction: function (commentId, reactionType, btn) {
            if (!btn) return;

            const isActive = btn.classList.contains('active');
            btn.classList.toggle('active', !isActive);
            btn.classList.add('is-optimistic-pending');
            btn.setAttribute('aria-busy', 'true');
            
            const icon = btn.querySelector('.bi');
            if (icon) {
                if (reactionType === 'like') {
                    icon.className = !isActive ? 'bi bi-hand-thumbs-up-fill' : 'bi bi-hand-thumbs-up';
                } else if (reactionType === 'dislike') {
                    icon.className = !isActive ? 'bi bi-hand-thumbs-down-fill' : 'bi bi-hand-thumbs-down';
                }
            }

            const countEl = btn.querySelector('.reaction-count');
            const current = Number(countEl ? countEl.textContent : 0);
            if (countEl) countEl.textContent = Math.max(0, current + (!isActive ? 1 : -1));
        },

        updateReactionUI: function (commentId, reactions, userReactions) {
            const container = document.querySelector(`[data-comment-id="${commentId}"] .ui-comment-reactions`);
            if (!container) return;

            const likes = reactions.like || 0;
            const dislikes = reactions.dislike || 0;
            const userLike = userReactions.includes('like') ? 'active' : '';
            const userDislike = userReactions.includes('dislike') ? 'active' : '';
            
            container.innerHTML = `
                <button class="comment-reaction-btn ui-comment-like-btn ${userLike}" data-comment-id="${commentId}" data-reaction-type="like" title="Beğen">
                    <i class="bi bi-hand-thumbs-up${userLike ? '-fill' : ''}"></i>
                    <span class="reaction-count">${likes}</span>
                </button>
                <button class="comment-reaction-btn ui-comment-dislike-btn ${userDislike}" data-comment-id="${commentId}" data-reaction-type="dislike" title="Beğenme">
                    <i class="bi bi-hand-thumbs-down${userDislike ? '-fill' : ''}"></i>
                    <span class="reaction-count">${dislikes}</span>
                </button>
            `;
        },

        // ─── Edit History ────────────────────────────────────
        bindEditHistoryButtons: function () {
            if (!this.editHistoryEnabled) return;

            document.addEventListener('click', (e) => {
                const historyBtn = e.target.closest('.comment-edit-history-btn');
                if (!historyBtn) return;

                e.preventDefault();
                const commentId = parseInt(historyBtn.dataset.commentId);
                this.showEditHistory(commentId);
            });
        },

        showEditHistory: function (commentId) {
            const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
            fetchJson(baseUri + `/api/comments.php?action=edit_history&comment_id=${commentId}&_=${Date.now()}`, { cache: 'no-store' })
                .then(data => {
                    if (data.success) {
                        this.renderEditHistoryModal(data.history);
                    } else {
                        this.showToast(data.error || 'Geçmiş yüklenemedi', 'error');
                    }
                })
                .catch(() => {
                    this.showToast('Bir hata oluştu', 'error');
                });
        },

        renderEditHistoryModal: function (history) {
            const previouslyFocused = document.activeElement;
            const modal = document.createElement('div');
            modal.className = 'comment-history-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', 'comment-history-heading');
            modal.innerHTML = `
                <div class="comment-history-overlay"></div>
                <div class="comment-history-content">
                    <div class="comment-history-header">
                        <h3 id="comment-history-heading"><i class="bi bi-clock-history"></i> Düzenleme Geçmişi</h3>
                        <button class="comment-history-close" type="button" aria-label="Kapat">&times;</button>
                    </div>
                    <div class="comment-history-body">
                        ${history.length === 0 ? '<p class="text-muted">Düzenleme geçmişi bulunamadı.</p>' : ''}
                        ${history.map(h => `
                            <div class="history-item">
                                <div class="history-meta">
                                    <strong>${this.escapeHtml(h.editor_name)}</strong>
                                    <span class="text-muted">${h.time_ago}</span>
                                </div>
                                ${h.edit_reason ? `
                                    <div class="history-reason">
                                        <strong>Neden:</strong> ${this.escapeHtml(h.edit_reason)}
                                    </div>
                                ` : ''}
                                <div class="history-diff">
                                    <div class="history-old">
                                        <label>Eski:</label>
                                        <div>${this.escapeHtml(h.old_body)}</div>
                                    </div>
                                    <div class="history-new">
                                        <label>Yeni:</label>
                                        <div>${this.escapeHtml(h.new_body)}</div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const closeModal = () => {
                modal.remove();
                document.removeEventListener('keydown', handleHistoryKeydown);
                if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
                    previouslyFocused.focus();
                }
            };

            const focusSelector = 'button:not([disabled]), [tabindex]:not([tabindex="-1"])';
            const handleHistoryKeydown = (e) => {
                if (e.key === 'Escape') { closeModal(); return; }
                if (e.key !== 'Tab') return;
                const focusables = Array.from(modal.querySelectorAll(focusSelector)).filter(el => !el.disabled);
                if (!focusables.length) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault(); last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault(); first.focus();
                }
            };

            document.addEventListener('keydown', handleHistoryKeydown);
            modal.querySelector('.comment-history-close')?.addEventListener('click', closeModal);
            modal.querySelector('.comment-history-overlay')?.addEventListener('click', closeModal);
            // Initial focus on close button
            modal.querySelector('.comment-history-close')?.focus();
        },

        // ─── Markdown Toolbar ────────────────────────────────
        initMarkdownToolbar: function () {
            if (!this.markdownEnabled) return;

            const textareas = document.querySelectorAll('.ui-comment-textarea, .ui-comment-inline-textarea');
            textareas.forEach(textarea => {
                if (textarea.dataset.markdownInit) return;
                textarea.dataset.markdownInit = 'true';

                const toolbar = this.createMarkdownToolbar(textarea);
                textarea.parentNode.insertBefore(toolbar, textarea);
            });
        },

        setupMutationObserver: function () {
            if (!this.markdownEnabled) return;

            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType !== Node.ELEMENT_NODE) return;
                        
                        const textareas = node.matches('.ui-comment-textarea, .ui-comment-inline-textarea')
                            ? [node]
                            : Array.from(node.querySelectorAll('.ui-comment-textarea, .ui-comment-inline-textarea'));

                        textareas.forEach(textarea => {
                            if (textarea.dataset.markdownInit) return;
                            textarea.dataset.markdownInit = 'true';

                            const toolbar = this.createMarkdownToolbar(textarea);
                            textarea.parentNode.insertBefore(toolbar, textarea);
                        });
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        createMarkdownToolbar: function (textarea) {
            const toolbar = document.createElement('div');
            toolbar.className = 'markdown-toolbar';
            toolbar.innerHTML = `
                <button type="button" class="md-btn" data-action="bold" title="Kalın (Ctrl+B)">
                    <i class="bi bi-type-bold"></i>
                </button>
                <button type="button" class="md-btn" data-action="italic" title="İtalik (Ctrl+I)">
                    <i class="bi bi-type-italic"></i>
                </button>
                <button type="button" class="md-btn" data-action="code" title="Kod">
                    <i class="bi bi-code"></i>
                </button>
                <button type="button" class="md-btn" data-action="link" title="Link">
                    <i class="bi bi-link-45deg"></i>
                </button>
                <span class="md-divider"></span>
                <button type="button" class="md-btn" data-action="mention" title="Kullanıcı etiketle (@)">
                    <i class="bi bi-at"></i>
                </button>
            `;

            toolbar.addEventListener('click', (e) => {
                const btn = e.target.closest('.md-btn');
                if (!btn) return;

                e.preventDefault();
                const action = btn.dataset.action;
                this.applyMarkdown(textarea, action);
            });

            return toolbar;
        },

        applyMarkdown: async function (textarea, action) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            let replacement = '';

            switch (action) {
                case 'bold':
                    replacement = `**${selectedText || 'kalın metin'}**`;
                    break;
                case 'italic':
                    replacement = `*${selectedText || 'italik metin'}*`;
                    break;
                case 'code':
                    replacement = `\`${selectedText || 'kod'}\``;
                    break;
                case 'link':
                    const url = await window.appPrompt('Link URL', { title: 'Bağlantı ekle', value: 'https://' });
                    if (url) replacement = `[${selectedText || 'link metni'}](${url})`;
                    break;
                case 'mention':
                    replacement = `@${selectedText || 'kullaniciadi'}`;
                    break;
            }

            if (replacement) {
                textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
                textarea.focus();
                textarea.setSelectionRange(start + replacement.length, start + replacement.length);
            }
        },

        // ─── Mention Autocomplete ────────────────────────────
        initMentionAutocomplete: function () {
            if (!this.mentionsEnabled) return;
            if (this.mentionAutocompleteInit) return;
            this.mentionAutocompleteInit = true;
            this.mentionSuggestions = [];
            this.mentionSelectedIndex = 0;
            this.mentionActiveTextarea = null;
            this.mentionTriggerStart = -1;

            const dropdown = document.createElement('div');
            dropdown.className = 'mention-autocomplete';
            dropdown.setAttribute('role', 'listbox');
            dropdown.hidden = true;
            document.body.appendChild(dropdown);
            this.mentionDropdown = dropdown;

            document.addEventListener('input', (e) => {
                const textarea = e.target.closest?.('.ui-comment-textarea, .ui-comment-inline-textarea');
                if (!textarea) return;
                this.handleMentionInput(textarea);
            });

            document.addEventListener('keydown', (e) => {
                if (!this.mentionDropdown || this.mentionDropdown.hidden) return;
                if (!e.target.matches('.ui-comment-textarea, .ui-comment-inline-textarea')) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.moveMentionSelection(1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.moveMentionSelection(-1);
                } else if (e.key === 'Enter' || e.key === 'Tab') {
                    const selected = this.mentionSuggestions[this.mentionSelectedIndex];
                    if (selected) {
                        e.preventDefault();
                        this.insertMentionSuggestion(selected);
                    }
                } else if (e.key === 'Escape') {
                    this.hideMentionAutocomplete();
                }
            });

            dropdown.addEventListener('mousedown', (e) => {
                const option = e.target.closest('.mention-autocomplete-option');
                if (!option) return;
                e.preventDefault();
                const selected = this.mentionSuggestions[parseInt(option.dataset.index || '0', 10)];
                if (selected) this.insertMentionSuggestion(selected);
            });

            document.addEventListener('click', (e) => {
                if (e.target.closest('.mention-autocomplete')) return;
                if (e.target.closest('.ui-comment-textarea, .ui-comment-inline-textarea')) return;
                this.hideMentionAutocomplete();
            });
        },

        handleMentionInput: function (textarea) {
            const trigger = this.getMentionTrigger(textarea);
            if (!trigger) {
                this.hideMentionAutocomplete();
                return;
            }

            this.mentionActiveTextarea = textarea;
            this.mentionTriggerStart = trigger.start;
            this.fetchMentionSuggestions(trigger.query, textarea, trigger.start);
        },

        getMentionTrigger: function (textarea) {
            const caret = textarea.selectionStart || 0;
            const beforeCaret = textarea.value.substring(0, caret);
            const match = beforeCaret.match(/(^|\s)@([^\s@]{1,40})$/u);
            if (!match) return null;

            return {
                query: match[2],
                start: caret - match[2].length - 1,
            };
        },

        fetchMentionSuggestions: function (query, textarea, triggerStart) {
            clearTimeout(this.mentionFetchTimer);
            this.mentionFetchTimer = setTimeout(() => {
                const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
                const url = `${baseUri}/api/comments.php?action=mention_search&q=${encodeURIComponent(query)}`;

                fetchJson(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                    .then(data => {
                        if (this.mentionActiveTextarea !== textarea || this.mentionTriggerStart !== triggerStart) return;
                        this.renderMentionSuggestions(Array.isArray(data.users) ? data.users : [], textarea);
                    })
                    .catch(() => this.hideMentionAutocomplete());
            }, 160);
        },

        renderMentionSuggestions: function (users, textarea) {
            if (!this.mentionDropdown) return;
            this.mentionDropdown.textContent = '';
            this.mentionSuggestions = users.slice(0, 8);
            this.mentionSelectedIndex = 0;

            if (!this.mentionSuggestions.length) {
                this.hideMentionAutocomplete();
                return;
            }

            this.mentionSuggestions.forEach((user, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'mention-autocomplete-option' + (index === 0 ? ' active' : '');
                option.dataset.index = String(index);
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', index === 0 ? 'true' : 'false');

                const avatar = document.createElement('span');
                avatar.className = 'mention-autocomplete-avatar';
                avatar.textContent = String(user.name || '?').trim().charAt(0).toUpperCase() || '?';

                const name = document.createElement('span');
                name.className = 'mention-autocomplete-name';
                name.textContent = '@' + String(user.name || '').trim();

                option.appendChild(avatar);
                option.appendChild(name);
                this.mentionDropdown.appendChild(option);
            });

            const rect = textarea.getBoundingClientRect();
            // Fixed positioning — use rect values directly (no scroll offset needed)
            let top = rect.bottom + 6;
            let left = rect.left;
            const width = Math.min(Math.max(rect.width, 220), 420);
            // Keep dropdown within viewport
            if (top + 250 > window.innerHeight) top = rect.top - 250;
            if (left + width > window.innerWidth) left = window.innerWidth - width - 8;
            if (left < 8) left = 8;
            this.mentionDropdown.style.left = `${left}px`;
            this.mentionDropdown.style.top = `${top}px`;
            this.mentionDropdown.style.width = `${width}px`;
            this.mentionDropdown.hidden = false;
        },

        moveMentionSelection: function (delta) {
            if (!this.mentionSuggestions.length || !this.mentionDropdown) return;
            this.mentionSelectedIndex = (this.mentionSelectedIndex + delta + this.mentionSuggestions.length) % this.mentionSuggestions.length;

            this.mentionDropdown.querySelectorAll('.mention-autocomplete-option').forEach((option, index) => {
                const active = index === this.mentionSelectedIndex;
                option.classList.toggle('active', active);
                option.setAttribute('aria-selected', active ? 'true' : 'false');
                if (active) option.scrollIntoView({ block: 'nearest' });
            });
        },

        insertMentionSuggestion: function (user) {
            const textarea = this.mentionActiveTextarea;
            if (!textarea || this.mentionTriggerStart < 0) return;

            const name = String(user.name || '').trim();
            if (!name) return;

            const end = textarea.selectionStart || textarea.value.length;
            const replacement = `@${name} `;
            textarea.value = textarea.value.substring(0, this.mentionTriggerStart) + replacement + textarea.value.substring(end);
            const caret = this.mentionTriggerStart + replacement.length;
            textarea.focus();
            textarea.setSelectionRange(caret, caret);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            this.hideMentionAutocomplete();
        },

        hideMentionAutocomplete: function () {
            clearTimeout(this.mentionFetchTimer);
            if (this.mentionDropdown) {
                this.mentionDropdown.hidden = true;
                this.mentionDropdown.textContent = '';
            }
            this.mentionSuggestions = [];
            this.mentionSelectedIndex = 0;
            this.mentionActiveTextarea = null;
            this.mentionTriggerStart = -1;
        },

        // ─── Utilities ───────────────────────────────────────
        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showToast: function (message, type = 'info') {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type);
                return;
            }

            const aliases = { danger: 'error', failed: 'error', warn: 'warning', ok: 'success' };
            const normalizedType = aliases[type] || type || 'info';
            const icons = {
                success: 'bi-check-circle-fill',
                error: 'bi-exclamation-triangle-fill',
                warning: 'bi-exclamation-circle-fill',
                info: 'bi-info-circle'
            };

            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                container.className = 'topic-toast-container toast-pos-top-right';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'topic-toast toast-' + normalizedType + ' toast-theme-default toast-anim-slide';
            toast.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status');
            toast.innerHTML = '<i class="bi ' + (icons[normalizedType] || icons.info) + ' toast-icon"></i>'
                + '<span class="toast-message"></span>'
                + '<button type="button" class="toast-close-btn" aria-label="Kapat">&times;</button>';
            toast.querySelector('.toast-message').textContent = message;

            const dismiss = function () {
                toast.classList.add('toast-out');
                setTimeout(function () { toast.remove(); }, 350);
            };
            toast.querySelector('.toast-close-btn').addEventListener('click', dismiss);
            container.appendChild(toast);
            setTimeout(dismiss, 4000);
        }
    };

})();
