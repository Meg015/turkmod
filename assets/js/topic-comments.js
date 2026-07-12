(function(){
            const section = document.querySelector('.topic-comments');
            if(!section) return;
            const API     = section.dataset.api;
            const TOPIC   = section.dataset.topicId;
            let   CSRF    = section.dataset.csrf;
            const LOGGED  = section.dataset.loggedIn === '1';
            const REPORT_ENABLED = section.dataset.reportEnabled === '1';
            const TOPIC_AUTHOR = section.dataset.topicAuthor || '';
            const UNAME   = section.dataset.userName;
            const POLL    = parseInt(section.dataset.poll)||0;
            const BASE_URI = (section.dataset.baseUrl || (document.querySelector('meta[name="app-base-uri"]')?.content || '')).replace(/\/$/, '');
            const AVATAR_FALLBACK = section.dataset.avatarFallback || (BASE_URI + '/assets/images/noavatar-neon-helmet.svg');
            const COMMENT_MAX_LENGTH = Math.max(1, parseInt(section.dataset.commentMaxLength || '1000', 10) || 1000);
            const reactionsEnabled = section.dataset.reactionsEnabled === '1';
            const list    = document.getElementById('tcList');
            const loading = document.getElementById('tcLoading');
            const countEl = document.getElementById('tcCount');
            const alertEl = document.getElementById('tcAlert');
            const sortSelect = document.getElementById('tcSort');
            const loadMoreWrap = document.getElementById('tcLoadMoreWrap');
            const loadMoreBtn = document.getElementById('tcLoadMore');
            const paginationInfo = document.getElementById('tcPaginationInfo');
            let comments  = [];
            let pollTimer = null;
            let currentPage = 1;
            let totalPages = 1;
            let totalComments = 0;
            const SORT_STORAGE_KEY = 'topic-comments-sort-' + TOPIC;
            const ALLOWED_SORTS = ['asc', 'desc', 'popular', 'liked', 'disliked'];
            function resolveInitialSort() {
                const params = new URLSearchParams(window.location.search);
                const fromUrl = params.get('comments_sort');
                let fromStorage = '';
                try {
                    fromStorage = window.localStorage ? localStorage.getItem(SORT_STORAGE_KEY) : '';
                } catch (error) {}
                const candidate = ALLOWED_SORTS.includes(fromUrl || '') ? fromUrl : fromStorage;
                return ALLOWED_SORTS.includes(candidate || '') ? candidate : 'asc';
            }
            let currentSort = resolveInitialSort();
            if (sortSelect) {
                sortSelect.value = currentSort;
                try {
                    localStorage.setItem(SORT_STORAGE_KEY, currentSort);
                } catch (error) {}
            }
            let hasOpenInlineForm = false; // track if inline form is open
            let lastFocusedCommentHash = '';
            let hashAutoLoadInProgress = false;
            const deleteInFlightIds = new Set();

            // -- Helpers --
            function esc(s){const d=document.createElement('div');d.textContent=s;return d.innerHTML;}
            function showAlert(msg,type='success'){
                if (window.showToast) {
                    window.showToast(msg, type);
                    return;
                }
                let container = document.getElementById('tcToastContainer');
                if(!container){
                    container = document.createElement('div');
                    container.id = 'tcToastContainer';
                    document.body.appendChild(container);
                }
                const toast = document.createElement('div');
                toast.className = 'ui-comment-toast ui-comment-toast-' + type;
                let icon = 'bi-check-circle-fill';
                if(type === 'error') icon = 'bi-exclamation-triangle-fill';
                if(type === 'warning') icon = 'bi-exclamation-circle-fill';
                toast.innerHTML = '<i class="bi ' + icon + '"></i> <span>' + esc(msg) + '</span>';
                container.appendChild(toast);
                setTimeout(() => {
                    toast.classList.add('is-hiding');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }
            function dispatchCommentCreatedEvent(payload = {}) {
                try {
                    const detail = Object.assign({
                        topicId: parseInt(TOPIC || '0', 10) || 0,
                    }, payload || {});
                    const event = new CustomEvent('topic-comment:created', { detail });
                    document.dispatchEvent(event);
                    window.dispatchEvent(event);
                } catch (error) {}
            }
            function dispatchCommentDeletedEvent(payload = {}) {
                try {
                    const detail = Object.assign({
                        topicId: parseInt(TOPIC || '0', 10) || 0,
                    }, payload || {});
                    const event = new CustomEvent('topic-comment:deleted', { detail });
                    document.dispatchEvent(event);
                    window.dispatchEvent(event);
                } catch (error) {}
            }
            function avatar(name,avUrl){
                const src = avUrl
                    ? (avUrl.startsWith('http') ? avUrl : BASE_URI + '/' + avUrl.replace(/^\/+/,''))
                    : AVATAR_FALLBACK;
                return '<img src="' + escAttr(src) + '" alt="' + escAttr(name || 'Kullanıcı') + '" width="32" height="32" loading="lazy" decoding="async" data-ui-avatar-img data-ui-avatar-fallback="' + escAttr(AVATAR_FALLBACK) + '">';
            }
            function escAttr(s){return esc(s).replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
            function formatEditedDateTime(value) {
                if (!value) return '';
                const normalized = String(value).replace(' ', 'T');
                const date = new Date(normalized);
                if (Number.isNaN(date.getTime())) return String(value);
                try {
                    return new Intl.DateTimeFormat('tr-TR', {
                        dateStyle: 'medium',
                        timeStyle: 'short'
                    }).format(date);
                } catch (error) {
                    return date.toLocaleString();
                }
            }
            function setButtonBusy(btn, busyText) {
                if(!btn) return;
                if (!btn.dataset.defaultHtml) {
                    btn.dataset.defaultHtml = btn.innerHTML;
                }
                btn.dataset.busy = '1';
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                btn.classList.add('is-busy');
                if (busyText) {
                    btn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> <span>' + busyText + '</span>';
                }
            }
            function clearButtonBusy(btn, restoreHtml = true) {
                if(!btn) return;
                btn.dataset.busy = '0';
                btn.setAttribute('aria-busy', 'false');
                btn.classList.remove('is-busy');
                if (restoreHtml && btn.dataset.defaultHtml) {
                    btn.innerHTML = btn.dataset.defaultHtml;
                }
            }
            // -- Find comment by id in nested comments array --
            function findCommentById(arr, id){
                for(let i=0;i<arr.length;i++){
                    if(arr[i].id===id) return arr[i];
                    if(arr[i].replies&&arr[i].replies.length){
                        const found=findCommentById(arr[i].replies,id);
                        if(found) return found;
                    }
                }
                return null;
            }
            const commentItemTemplate = (document.getElementById('tmCommentItemTemplate')?.innerHTML || '').trim();
            function renderClientTemplate(template, values) {
                return template.replace(/\[\[([a-z0-9_]+)\]\]/gi, function(match, key) {
                    return Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : '';
                });
            }

            function slugifyProfileName(value) {
                const text = String(value || '').trim();
                if (text === '') return 'kullanici';

                const normalized = text
                    .replace(/[ÇĞİÖŞÜçğıöşü]/g, function(char) {
                        return {
                            'Ç': 'C',
                            'Ğ': 'G',
                            'İ': 'I',
                            'Ö': 'O',
                            'Ş': 'S',
                            'Ü': 'U',
                            'ç': 'c',
                            'ğ': 'g',
                            'ı': 'i',
                            'ö': 'o',
                            'ş': 's',
                            'ü': 'u',
                        }[char] || char;
                    })
                    .normalize('NFKD')
                    .replace(/[\u0300-\u036f]/g, '');

                const slug = normalized
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');

                return slug || 'kullanici';
            }

            // -- Client-side Markdown Parser (safe, lightweight) --
            function parseMarkdown(text) {
                if (!text) return '';
                // First escape HTML
                let html = esc(text);
                // Bold+Italic combined: ***text***
                html = html.replace(/\*\*\*(.+?)\*\*\*/g, '<strong><em>$1</em></strong>');
                // Bold: **text**
                html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
                // Italic: *text* (not adjacent to another *)
                html = html.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');
                // Inline code: `text`
                html = html.replace(/`([^`]+?)`/g, '<code>$1</code>');
                // Links: [text](url) — only http/https
                html = html.replace(/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
                // @mention highlight
                html = html.replace(/@([\w\-]+)/g, function(match, username) {
                    return '<span class="ui-comment-mention">@' + esc(username) + '</span>';
                });
                // Newlines to <br>
                html = html.replace(/\n/g, '<br>');
                return html;
            }

            // -- Render --
            function renderComment(c, isReply=false){
                let cls = isReply ? 'ui-comment-item ui-comment-reply animate-in' : 'ui-comment-item animate-in';
                const actions = [];
                if(LOGGED) actions.push('<button class="ui-comment-action-btn ui-comment-reply-btn" data-id="' + c.id + '" data-author="' + escAttr(c.author) + '" data-root="' + (c.parent_id || c.id) + '" title="Yanıtla"><i class="bi bi-chat-left-text"></i> <span>Yanıtla</span></button>');
                if(c.can_edit) actions.push('<button class="ui-comment-action-btn ui-comment-edit" data-id="' + c.id + '" title="Düzenle"><i class="bi bi-pencil-square"></i> <span>Düzenle</span></button>');
                if(c.can_delete) actions.push('<button class="ui-comment-action-btn ui-comment-delete" data-id="' + c.id + '" title="Sil"><i class="bi bi-trash3"></i> <span>Sil</span></button>');
                if(LOGGED && !c.can_delete && REPORT_ENABLED) actions.push('<button class="ui-comment-action-btn ui-comment-report-btn" data-id="' + c.id + '" title="Şikayet Et"><i class="bi bi-flag"></i> <span>Şikayet</span></button>');

                let replies = '';
                if(c.replies && c.replies.length){
                    replies = '<div class="ui-comment-replies">' + c.replies.map(r=>renderComment(r,true)).join('') + '</div>';
                }

                // Alıntı etiketi (minimal)
                let quoteTag = '';
                if(c.parent_id && c.parent_author){
                    const snippet = c.parent_body_preview ? '"'+esc(c.parent_body_preview.length>=80 ? c.parent_body_preview.substring(0,77)+'...' : c.parent_body_preview)+'"' : '';
                    quoteTag = '<div class="ui-comment-quote-tag"><i class="bi bi-quote"></i> <strong>' + esc(c.parent_author) + '</strong>' + (snippet ? ' - <span>' + snippet + '</span>' : '') + '</div>';
                }

                // Edited badge
                let editedBadge = '';
                if(c.is_edited){
                    const editedAtText = formatEditedDateTime(c.edited_at);
                    const editedLabel = editedAtText ? ('Son düzenleme: ' + editedAtText) : 'Yorum düzenlendi';
                    editedBadge = '<span class="comment-edited-badge" title="' + escAttr(editedLabel) + '" aria-label="' + escAttr(editedLabel) + '">Düzenlendi <button class="comment-edit-history-btn" data-comment-id="' + c.id + '" title="' + escAttr(editedLabel) + '" aria-label="' + escAttr(editedLabel) + '">geçmişi gör</button></span>';
                }

                // Reactions (Like / Dislike)
                let reactionsHtml = '';
                if(reactionsEnabled){
                    const likes = c.reactions?.like || 0;
                    const dislikes = c.reactions?.dislike || 0;
                    const userReactions = c.user_reactions || [];
                    const userLike = userReactions.includes('like') ? 'active' : '';
                    const userDislike = userReactions.includes('dislike') ? 'active' : '';
                    
                    const likeIcon = userReactions.includes('like') ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up';
                    const dislikeIcon = userReactions.includes('dislike') ? 'bi-hand-thumbs-down-fill' : 'bi-hand-thumbs-down';

                    reactionsHtml = '<div class="ui-comment-reactions ui-comment-like-dislike">' +
                        '<button class="comment-reaction-btn ui-comment-like-btn ' + userLike + '" data-comment-id="' + c.id + '" data-reaction-type="like" title="Beğen">' +
                            '<i class="bi ' + likeIcon + '"></i><span class="reaction-count">' + likes + '</span>' +
                        '</button>' +
                        '<button class="comment-reaction-btn ui-comment-dislike-btn ' + userDislike + '" data-comment-id="' + c.id + '" data-reaction-type="dislike" title="Beğenme">' +
                            '<i class="bi ' + dislikeIcon + '"></i><span class="reaction-count">' + dislikes + '</span>' +
                        '</button>' +
                    '</div>';
                }

                // Parse markdown for display
                const bodyHtml = parseMarkdown(c.body);

                let authorBadge = '';
                if(c.author === TOPIC_AUTHOR) {
                    authorBadge = '<span class="ui-comment-author-badge">Konu Sahibi</span>';
                    cls += ' is-author-comment';
                }

                let groupBadge = '';
                if(c.group_name && c.group_name !== '') {
                    groupBadge = '<span class="ui-comment-group-badge">' + esc(c.group_name) + '</span>';
                }

                const profileDisplayName = c.author || c.username || 'kullanici';
                const profileAuthorId = parseInt(c.author_id || c.user_id || 0, 10);
                const profileUrl = c.profile_url
                    ? String(c.profile_url)
                    : (profileAuthorId > 0
                        ? BASE_URI + '/profil/' + encodeURIComponent(slugifyProfileName(profileDisplayName) + '-' + profileAuthorId)
                        : '#');

                 const templateValues = {
                    classes: cls,
                    id: c.id,
                    hue: (c.author || '').length % 7,
                    avatar_html: avatar(c.author, c.avatar),
                    avatar_cls: 'ui-comment-profile-avatar',
                    avatar_style: '',
                    author: esc(c.author || 'Anonim'),
                    profile_url: escAttr(profileUrl),
                    author_badge_html: authorBadge,
                    group_badge_html: groupBadge,
                    edited_badge_html: editedBadge,
                    time_ago: esc(c.time_ago || ''),
                    quote_html: quoteTag,
                    body_html: bodyHtml,
                    actions_html: actions.join(''),
                    reactions_html: reactionsHtml,
                    replies_html: replies
                };

                if(commentItemTemplate) {
                    return renderClientTemplate(commentItemTemplate, templateValues);
                }

                return '<div class="' + cls + '" data-comment-id="' + c.id + '" id="comment-' + c.id + '">' +
                    '<div class="ui-comment-body ui-panel__body">' +
                        '<div class="ui-comment-profile-card">' +
                            '<div class="ui-comment-profile-avatar" data-hue="' + ((c.author || '').length % 7) + '">' + avatar(c.author, c.avatar) + '</div>' +
                            '<div class="ui-comment-profile-info">' +
                                '<div class="ui-comment-author-line"><a href="' + escAttr(profileUrl) + '" class="ui-comment-author-link"><strong class="ui-comment-author">' + esc(c.author) + '</strong></a>' + authorBadge + groupBadge + '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="ui-comment-divider" aria-hidden="true"></div>' +
                        '<div class="ui-comment-content-wrap">' +
                            '<time class="ui-comment-time">' + esc(c.time_ago) + '</time>' +
                            quoteTag +
                            '<div class="ui-comment-text comment-body ui-panel__body">' + bodyHtml + '</div>' +
                            '<div class="ui-comment-bottom-bar"><div class="ui-comment-bottom-main"><div class="ui-comment-actions-row">' + actions.join('') + '</div>' + reactionsHtml + '</div><div class="ui-comment-bottom-meta">' + editedBadge + '</div></div>' +
                            '<div class="ui-comment-inline-reply-slot" data-for="' + c.id + '"></div>' +
                        '</div>' +
                    '</div>' +
                '</div>' + replies;
            }

            // -- Smart re-render: only update if DOM changed --
            let dynamicUiResizeBound = false;
            let dynamicUiResizeTimer = null;

            function initCommentReadMore() {
                const isMobile = window.matchMedia('(max-width: 768px)').matches;
                const truncateHeight = isMobile ? 150 : 280;

                list.querySelectorAll('.ui-comment-read-more-btn').forEach(btn => btn.remove());
                list.querySelectorAll('.comment-body').forEach(body => {
                    body.classList.remove('is-truncated');
                    body.style.removeProperty('--comment-truncate-height');
                    body.style.removeProperty('max-height');

                    if (body.scrollHeight <= truncateHeight) return;

                    body.classList.add('is-truncated');
                    body.style.setProperty('--comment-truncate-height', truncateHeight + 'px');

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ui-comment-read-more-btn';
                    const commentItem = body.closest('.ui-comment-item');
                    const bodyId = body.id || ('comment-body-' + (commentItem?.dataset.commentId || Math.random().toString(36).slice(2, 8)));
                    body.id = bodyId;
                    btn.setAttribute('aria-controls', bodyId);
                    btn.setAttribute('aria-expanded', 'false');
                    btn.textContent = 'Devamını oku';
                    btn.addEventListener('click', function() {
                        if(body.classList.contains('is-truncated')) {
                            body.classList.remove('is-truncated');
                            btn.setAttribute('aria-expanded', 'true');
                            btn.textContent = 'Daha az göster';
                        } else {
                            body.classList.add('is-truncated');
                            btn.setAttribute('aria-expanded', 'false');
                            btn.textContent = 'Devamını oku';
                            body.scrollIntoView({behavior: 'smooth', block: 'center'});
                        }
                    });
                    body.parentNode.insertBefore(btn, body.nextSibling);
                });
            }

            function initReplyCollapseControls() {
                list.querySelectorAll('.ui-comment-collapse-btn').forEach(btn => btn.remove());

                const isMobile = window.matchMedia('(max-width: 768px)').matches;
                list.querySelectorAll('.ui-comment-replies').forEach(repliesEl => {
                    const directReplies = Array.from(repliesEl.children).filter(function(node) {
                        return node.classList && node.classList.contains('ui-comment-item');
                    });
                    const replyCount = directReplies.length;
                    if (replyCount < 3) {
                        repliesEl.classList.remove('is-collapsed');
                        return;
                    }

                    const isNestedThread = !!repliesEl.closest('.ui-comment-replies .ui-comment-replies');
                    const shouldStartCollapsed = isMobile && (replyCount >= 5 || isNestedThread);

                    if (shouldStartCollapsed) {
                        repliesEl.classList.add('is-collapsed');
                    } else {
                        repliesEl.classList.remove('is-collapsed');
                    }

                    const toggleBtn = document.createElement('button');
                    toggleBtn.type = 'button';
                    toggleBtn.className = 'ui-comment-collapse-btn';
                    toggleBtn.innerHTML = '<i class="bi bi-chevron-down" aria-hidden="true"></i><span></span>';
                    const labelEl = toggleBtn.querySelector('span');
                    const parentItem = repliesEl.closest('.ui-comment-item');
                    const repliesId = repliesEl.id || ('comment-replies-' + (parentItem?.dataset.commentId || Math.random().toString(36).slice(2, 8)));
                    repliesEl.id = repliesId;
                    toggleBtn.setAttribute('aria-controls', repliesId);

                    const updateLabel = function() {
                        const collapsed = repliesEl.classList.contains('is-collapsed');
                        toggleBtn.classList.toggle('is-collapsed', collapsed);
                        toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                        if (labelEl) {
                            labelEl.textContent = collapsed
                                ? (replyCount + ' yanıtı göster')
                                : (replyCount + ' yanıtı gizle');
                        }
                    };

                    updateLabel();
                    toggleBtn.addEventListener('click', function() {
                        repliesEl.classList.toggle('is-collapsed');
                        updateLabel();
                    });

                    repliesEl.parentNode.insertBefore(toggleBtn, repliesEl);
                });
            }

            function refreshDynamicCommentUi() {
                initCommentReadMore();
                initReplyCollapseControls();
            }

            function bindDynamicUiResize() {
                if (dynamicUiResizeBound) return;
                dynamicUiResizeBound = true;
                window.addEventListener('resize', function() {
                    clearTimeout(dynamicUiResizeTimer);
                    dynamicUiResizeTimer = window.setTimeout(function() {
                        refreshDynamicCommentUi();
                    }, 180);
                });
            }

            function renderAll(data, isPollUpdate=false){
                const newComments = data.comments || [];
                totalComments = data.total || 0;
                totalPages = data.pages || 1;
                countEl.textContent = '(' + totalComments + ')';

                // Update pagination UI
                updatePaginationUI();

                if(isPollUpdate && hasOpenInlineForm) {
                    // During poll, only update if there are truly new comments
                    const oldIds = new Set(comments.map(c => c.id));
                    const hasNew = newComments.some(c => !oldIds.has(c.id));
                    const hasRemoved = comments.some(c => !newComments.find(nc => nc.id === c.id));
                    if(!hasNew && !hasRemoved) {
                        comments = newComments;
                        return; // nothing changed, preserve inline forms
                    }
                }

                comments = newComments;
                if(!comments.length){
                    list.innerHTML = `
                    <div class="ui-comment-empty-state animate-in ui-empty">
                        <h4 class="ui-comment-empty-state-title">Henüz yorum yapılmamış</h4>
                        <p class="ui-comment-empty-state-desc">İlk yorumu sen yap ve tartışmayı başlat!</p>
                    </div>`;
                } else {
                    list.innerHTML = comments.map(c=>renderComment(c)).join('');
                }
                
                refreshDynamicCommentUi();
                bindDynamicUiResize();
                bindActions();
                ensureCommentFromHash();
            }

            function getCommentHashTargetId() {
                const hash = window.location.hash || '';
                return /^#comment-\d+$/.test(hash) ? hash.slice(1) : '';
            }

            function focusCommentFromHash(force=false) {
                const targetId = getCommentHashTargetId();
                if (!targetId) return false;

                const target = document.getElementById(targetId);
                if (!target) return false;

                const hash = '#' + targetId;
                if (!force && hash === lastFocusedCommentHash) return true;

                lastFocusedCommentHash = hash;
                target.setAttribute('tabindex', '-1');
                target.classList.add('is-linked-comment');
                target.scrollIntoView({behavior: 'smooth', block: 'center'});
                target.focus({preventScroll: true});
                window.setTimeout(function() {
                    target.classList.remove('is-linked-comment');
                }, 2400);
                return true;
            }

            function ensureCommentFromHash() {
                const targetId = getCommentHashTargetId();
                if (!targetId) return;
                if (focusCommentFromHash()) return;
                if (hashAutoLoadInProgress || currentPage >= totalPages) return;

                hashAutoLoadInProgress = true;

                const finish = function() {
                    hashAutoLoadInProgress = false;
                    updatePaginationUI();
                };

                const loadNextPage = function() {
                    const activeTargetId = getCommentHashTargetId();
                    if (!activeTargetId || activeTargetId !== targetId) {
                        finish();
                        ensureCommentFromHash();
                        return;
                    }

                    if (focusCommentFromHash(true)) {
                        finish();
                        return;
                    }

                    if (currentPage >= totalPages) {
                        finish();
                        return;
                    }

                    if (loadMoreBtn) {
                        loadMoreBtn.disabled = true;
                        loadMoreBtn.innerHTML = 'Yorum bulunuyor...';
                    }

                    loadComments(currentPage + 1, true, {skipHashEnsure: true}).then(function(data) {
                        if (!data) {
                            finish();
                            return;
                        }
                        window.setTimeout(loadNextPage, 40);
                    });
                };

                loadNextPage();
            }

            function copyCommentLink(commentId) {
                const url = new URL(window.location.href);
                url.hash = 'comment-' + commentId;
                const link = url.toString();
                const settle = function(copied) {
                    history.replaceState(null, '', url.pathname + url.search + url.hash);
                    focusCommentFromHash(true);
                    showAlert(copied ? 'Yorum bağlantısı kopyalandı.' : 'Yorum bağlantısı adres çubuğuna eklendi.', copied ? 'success' : 'warning');
                };

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(link).then(function() {
                        settle(true);
                    }).catch(function() {
                        settle(false);
                    });
                } else {
                    settle(false);
                }
            }

            // -- Pagination UI --
            function updatePaginationUI() {
                const hasAnyComments = totalComments > 0 || comments.length > 0;
                const hasNextPage = hasAnyComments && currentPage < totalPages;
                if (hasNextPage) {
                    loadMoreWrap.classList.remove('is-hidden');
                    if (currentPage < totalPages) {
                        loadMoreBtn.disabled = false;
                        loadMoreBtn.innerHTML = 'Daha fazla yorum yükle';
                    } else {
                        loadMoreBtn.disabled = true;
                        loadMoreBtn.innerHTML = 'En son yoruma ulaştınız';
                    }
                } else {
                    loadMoreWrap.classList.add('is-hidden');
                    loadMoreBtn.disabled = true;
                }
                if (hasAnyComments && totalPages > 1) {
                    paginationInfo.classList.remove('is-hidden');
                    paginationInfo.textContent = 'Sayfa ' + currentPage + ' / ' + totalPages;
                } else {
                    paginationInfo.classList.add('is-hidden');
                }
            }

            // -- Fetch --
            function loadComments(page, append=false, options={}){
                const params = new URLSearchParams({topic_id: TOPIC, page: page || 1, sort: currentSort, _t: new Date().getTime()});
                return fetch(API + '?' + params.toString()).then(r=>r.json()).then(data=>{
                    loading.classList.add('is-hidden');
                    if(append && page > 1) {
                        // Append to existing comments
                        const newComments = data.comments || [];
                        comments = comments.concat(newComments);
                        currentPage = Math.max(currentPage, page || currentPage);
                        totalComments = data.total || 0;
                        totalPages = data.pages || 1;
                        countEl.textContent = '(' + totalComments + ')';
                        // Append new HTML
                        const html = newComments.map(c=>renderComment(c)).join('');
                        list.insertAdjacentHTML('beforeend', html);
                        updatePaginationUI();
                        refreshDynamicCommentUi();
                        bindDynamicUiResize();
                        bindActions();
                        if (!options.skipHashEnsure) ensureCommentFromHash();
                    } else {
                        currentPage = page || 1;
                        renderAll(data);
                    }
                    return data;
                }).catch((e)=>{
                    if(!append) loading.innerHTML='<p class="ui-comment-empty ui-empty">Yorumlar yüklenemedi. (' + e.message + ')</p>';
                    console.error('FETCH ERROR:', e);
                    return null;
                });
            }

            // -- Poll with Visibility API --
            let _visibilityHandler = null;
            function startPoll(){
                if(POLL <= 0) return;
                // Remove previous handler to prevent accumulation on tab resume
                if(_visibilityHandler) {
                    document.removeEventListener('visibilitychange', _visibilityHandler);
                    _visibilityHandler = null;
                }
                pollTimer = setInterval(()=>{
                    if(document.hidden) return; // skip if tab not visible
                    if(currentPage > 1) return; // skip polling if user has loaded more pages
                    if(window._isPollingComments) return;
                    window._isPollingComments = true;
                    fetch(API+'?topic_id='+TOPIC+'&page='+currentPage+'&sort='+currentSort+'&_t='+Date.now()).then(r=>r.json()).then(data=>{
                        renderAll(data, true); // true = isPollUpdate
                    }).catch(()=>{}).finally(()=>{ window._isPollingComments = false; });
                }, POLL*1000);

                // Pause/resume on visibility change (single named handler)
                _visibilityHandler = ()=>{
                    if(document.hidden) {
                        clearInterval(pollTimer);
                        pollTimer = null;
                    } else {
                        startPoll(); // resume
                    }
                };
                document.addEventListener('visibilitychange', _visibilityHandler);
            }

            // -- Sort --
            if(sortSelect) {
                sortSelect.addEventListener('change', function(){
                    currentSort = this.value;
                    try {
                        localStorage.setItem(SORT_STORAGE_KEY, currentSort);
                    } catch (error) {}
                    const url = new URL(window.location.href);
                    url.searchParams.set('comments_sort', currentSort);
                    history.replaceState(null, '', url.pathname + url.search + url.hash);
                    currentPage = 1;
                    loading.classList.remove('is-hidden');
                    loadComments(1);
                });
            }

            // -- Load More --
            if(loadMoreBtn) {
                loadMoreBtn.addEventListener('click', function(){
                    currentPage++;
                    this.disabled = true;
                    this.innerHTML = 'Yükleniyor...';
                    loadComments(currentPage, true);
                });
            }

            // -- Ctrl+Enter Submit Helper --
            function bindCtrlEnter(textarea, submitFn) {
                textarea.addEventListener('keydown', function(e){
                    if((e.ctrlKey || e.metaKey) && e.key === 'Enter'){
                        e.preventDefault();
                        if (textarea.disabled) return;
                        submitFn();
                    }
                });
            }

            function bindEscapeToClose(textarea, closeFn) {
                textarea.addEventListener('keydown', function(e){
                    if(e.key !== 'Escape') return;
                    e.preventDefault();
                    closeFn();
                });
            }

            // -- Auto-expand textarea helper --
            function bindAutoExpand(textarea) {
                if(!textarea) return;
                textarea.style.height = 'auto';
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
            }

            // -- Update character limit counter helper --
            function updateCharCount(textarea, counterEl) {
                if(!textarea || !counterEl) return;
                const len = textarea.value.length;
                counterEl.textContent = len;
                
                const percent = len / COMMENT_MAX_LENGTH;
                if (percent >= 0.9) {
                    counterEl.style.color = 'var(--t-danger, #ff4d4d)';
                    counterEl.style.fontWeight = 'bold';
                } else if (percent >= 0.75) {
                    counterEl.style.color = 'var(--t-warning, #ffa500)';
                    counterEl.style.fontWeight = 'bold';
                } else {
                    counterEl.style.color = '';
                    counterEl.style.fontWeight = '';
                }
            }

            // -- Submit --
            const input=document.getElementById('tcInput');

            const actionsDiv=document.getElementById('tcActions');
            const submitBtn=document.getElementById('tcSubmit');
            const cancelBtn=document.getElementById('tcCancel');
            const charCount=document.getElementById('tcCharCount');
            let replyTo = null;
            let mainSubmitInFlight = false;

            function submitMainComment(){
                if(mainSubmitInFlight) return;
                const body=input.value.trim();
                if(!body)return;
                mainSubmitInFlight = true;
                setButtonBusy(submitBtn, 'Gönderiliyor...');
                const payload={topic_id:parseInt(TOPIC),body:body,_token:CSRF};
                if(replyTo) payload.parent_id=replyTo;
                fetch(API,{
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body:JSON.stringify(payload)
                }).then(r=>r.json()).then(data=>{
                    if(data._token) CSRF=data._token;
                    clearButtonBusy(submitBtn);
                    if(data.success){
                        input.value='';input.rows=1;actionsDiv.classList.add('is-hidden');
                        input.style.height = '';
                        charCount.textContent='0';
                        submitBtn.disabled = true;
                        replyTo = null;
                        if(data.pending){
                            showAlert(data.message,'warning');
                            dispatchCommentCreatedEvent({ pending: true, source: 'main' });
                        } else {
                            showAlert(data.message,'success');
                            dispatchCommentCreatedEvent({ pending: false, source: 'main' });
                            loadComments(1);
                        }
                    } else {
                        showAlert(data.error||'Hata oluştu.','error');
                        submitBtn.disabled = false;
                    }
                }).catch(()=>{
                    showAlert('Bağlantı hatası.','error');
                    clearButtonBusy(submitBtn);
                    submitBtn.disabled = false;
                }).finally(()=>{
                    mainSubmitInFlight = false;
                });
            }

            if(input){
                input.addEventListener('focus',()=>{
                    actionsDiv.classList.remove('is-hidden');
                    input.rows=3;
                    bindAutoExpand(input);
                });
                input.addEventListener('input',()=>{
                    const len=input.value.length;
                    submitBtn.disabled=len===0;
                    updateCharCount(input, charCount);
                });
                cancelBtn.addEventListener('click',()=>{
                    input.value='';
                    input.rows=1;
                    input.style.height = '';
                    actionsDiv.classList.add('is-hidden');
                    updateCharCount(input, charCount);
                    submitBtn.disabled=true;
                });
                submitBtn.addEventListener('click', submitMainComment);
                // Ctrl+Enter shortcut
                bindCtrlEnter(input, submitMainComment);
            }

            // -- Inline reply form --
            function removeInlineReply(){
                const old=document.querySelector('.ui-comment-inline-form');
                if(old) old.remove();
                replyTo=null;
                hasOpenInlineForm=false;
            }

            function createInlineReply(parentId, authorName, slot){
                removeInlineReply();
                replyTo=parentId;
                hasOpenInlineForm=true;
                const wrap=document.createElement('div');
                wrap.className='ui-comment-inline-form';
                wrap.innerHTML =
                    '<div class="ui-comment-quote-info"><strong>' + esc(authorName) + '</strong> adlı kullanıcının yorumunu alıntılıyorsunuz</div>' +
                    '<textarea class="ui-comment-textarea ui-comment-inline-textarea" placeholder="Yanıtınızı yazın..." rows="2" maxlength="' + COMMENT_MAX_LENGTH + '"></textarea>' +
                    '<div class="ui-comment-inline-meta"><span class="ui-inline-char-count">0</span> / ' + COMMENT_MAX_LENGTH + '</div>' +
                    '<div class="ui-comment-inline-btns">' +
                        '<button type="button" class="ui-comment-btn-cancel ui-comment-inline-cancel">İptal</button>' +
                        '<button type="button" class="ui-comment-btn-submit ui-comment-inline-submit">Yanıtla</button>' +
                    '</div>';
                slot.appendChild(wrap);
                const ta=wrap.querySelector('textarea');
                const inlineReplyCharEl=wrap.querySelector('.ui-inline-char-count');
                ta.focus();
                bindAutoExpand(ta);
                ta.addEventListener('input', function(){ updateCharCount(ta, inlineReplyCharEl); });
                let inlineReplyInFlight = false;

                function submitInlineReply(){
                    if(inlineReplyInFlight) return;
                    const body=ta.value.trim();
                    if(!body)return;
                    const btn=wrap.querySelector('.ui-comment-inline-submit');
                    inlineReplyInFlight = true;
                    setButtonBusy(btn, 'Gönderiliyor...');
                    fetch(API,{
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body:JSON.stringify({topic_id:parseInt(TOPIC),body:body,parent_id:parentId,_token:CSRF})
                    }).then(r=>r.json()).then(data=>{
                        if(data._token) CSRF=data._token;
                        if(data.success){
                            removeInlineReply();
                            if(data.pending) {
                                showAlert(data.message,'warning');
                                dispatchCommentCreatedEvent({ pending: true, source: 'reply' });
                            }
                            else {
                                showAlert(data.message,'success');
                                dispatchCommentCreatedEvent({ pending: false, source: 'reply' });
                                loadComments(1);
                            }
                        } else {
                            showAlert(data.error||'Hata','error');
                            clearButtonBusy(btn);
                            btn.disabled = false;
                        }
                    }).catch(()=>{
                        showAlert('Bağlantı hatası.','error');
                        clearButtonBusy(btn);
                        btn.disabled = false;
                    }).finally(()=>{
                        inlineReplyInFlight = false;
                    });
                }

                wrap.querySelector('.ui-comment-inline-cancel').addEventListener('click', removeInlineReply);
                wrap.querySelector('.ui-comment-inline-submit').addEventListener('click', submitInlineReply);
                // Ctrl+Enter shortcut for inline reply
                bindCtrlEnter(ta, submitInlineReply);
                bindEscapeToClose(ta, removeInlineReply);
            }

            // -- Inline Edit Form --
            function removeInlineEdit(){
                const old=document.querySelector('.ui-comment-inline-edit-form');
                if(old) {
                    const item = old.closest('.ui-comment-item');
                    if(item) item.classList.remove('is-editing');
                    old.remove();
                }
                hasOpenInlineForm=false;
            }

            function createInlineEdit(commentId, currentBody, slot){
                removeInlineEdit();
                removeInlineReply();
                hasOpenInlineForm=true;
                const item = slot.closest('.ui-comment-item');
                if(item) item.classList.add('is-editing');

                const wrap=document.createElement('div');
                wrap.className='ui-comment-inline-form ui-comment-inline-edit-form';
                wrap.innerHTML =
                    '<textarea class="ui-comment-textarea ui-comment-inline-textarea" placeholder="Yorumunuzu düzenleyin..." rows="3" maxlength="' + COMMENT_MAX_LENGTH + '">' + esc(currentBody) + '</textarea>' +
                    '<div class="ui-comment-inline-meta"><span class="ui-inline-char-count">0</span> / ' + COMMENT_MAX_LENGTH + '</div>' +
                    '<div class="ui-comment-inline-btns">' +
                        '<button type="button" class="ui-comment-btn-cancel ui-comment-inline-edit-cancel">İptal</button>' +
                        '<button type="button" class="ui-comment-btn-submit ui-comment-inline-edit-submit">Kaydet</button>' +
                    '</div>';
                slot.appendChild(wrap);
                const ta=wrap.querySelector('textarea');
                const inlineEditCharEl=wrap.querySelector('.ui-inline-char-count');
                ta.focus();
                ta.setSelectionRange(ta.value.length, ta.value.length);
                bindAutoExpand(ta);
                ta.addEventListener('input', function(){ updateCharCount(ta, inlineEditCharEl); });
                updateCharCount(ta, inlineEditCharEl);
                let inlineEditInFlight = false;

                function submitEdit(){
                    if(inlineEditInFlight) return;
                    const body=ta.value.trim();
                    if(!body)return;
                    const btn=wrap.querySelector('.ui-comment-inline-edit-submit');
                    inlineEditInFlight = true;
                    setButtonBusy(btn, 'Kaydediliyor...');
                    fetch(API,{
                        method:'POST',
                        headers:{'Content-Type':'application/json'},
                        body:JSON.stringify({action:'edit',id:commentId,body:body,_token:CSRF})
                    }).then(r=>r.json()).then(data=>{
                        if(data._token) CSRF=data._token;
                        if(data.success){
                            removeInlineEdit();
                            showAlert('Yorumunuz güncellendi.','success');
                            loadComments(currentPage);
                        } else {
                            showAlert(data.error||'Düzenleme başarısız.','error');
                            clearButtonBusy(btn);
                            btn.disabled = false;
                        }
                    }).catch(()=>{
                        showAlert('Bağlantı hatası.','error');
                        clearButtonBusy(btn);
                        btn.disabled = false;
                    }).finally(()=>{
                        inlineEditInFlight = false;
                    });
                }

                wrap.querySelector('.ui-comment-inline-edit-cancel').addEventListener('click', removeInlineEdit);
                wrap.querySelector('.ui-comment-inline-edit-submit').addEventListener('click', submitEdit);
                // Ctrl+Enter shortcut for edit
                bindCtrlEnter(ta, submitEdit);
                bindEscapeToClose(ta, removeInlineEdit);
            }

            // -- Report Modal --
            function showReportModal(commentId){
                const previouslyFocused = document.activeElement;
                const modal = document.createElement('div');
                modal.className = 'ui-comment-report-modal';
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('aria-labelledby', 'ui-comment-report-heading-' + commentId);
                modal.innerHTML =
                    '<div class="ui-comment-report-overlay" data-ui-modal-close></div>' +
                    '<div class="ui-comment-report-content ui-section">' +
                        '<div class="ui-comment-report-header ui-panel__head">' +
                            '<h3 id="ui-comment-report-heading-' + commentId + '">Yorum Şikayet Et</h3>' +
                            '<button type="button" class="ui-comment-report-close" data-ui-modal-close aria-label="Kapat">&times;</button>' +
                        '</div>' +
                        '<div class="ui-comment-report-body ui-panel__body">' +
                            '<div class="ui-comment-report-reasons">' +
                                '<button type="button" class="ui-comment-report-reason-btn" data-reason="spam" aria-pressed="false">Spam / Reklam</button>' +
                                '<button type="button" class="ui-comment-report-reason-btn" data-reason="abusive" aria-pressed="false">Küfürlü / Hakaret</button>' +
                                '<button type="button" class="ui-comment-report-reason-btn" data-reason="inappropriate" aria-pressed="false">Uygunsuz İçerik</button>' +
                                '<button type="button" class="ui-comment-report-reason-btn" data-reason="misinformation" aria-pressed="false">Yanıltıcı Bilgi</button>' +
                                '<button type="button" class="ui-comment-report-reason-btn" data-reason="other" aria-pressed="false">Diğer</button>' +
                            '</div>' +
                            '<p class="ui-comment-report-selected" aria-live="polite">Henüz bir gerekçe seçilmedi.</p>' +
                            '<textarea class="ui-comment-report-details" placeholder="Ek açıklama (isteğe bağlı)..." rows="2"></textarea>' +
                            '<button type="button" class="ui-comment-report-submit" disabled>Şikayet Et</button>' +
                        '</div>' +
                    '</div>';
                document.body.appendChild(modal);

                let selectedReason = '';
                const focusSelector = 'button:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
                let reportModalController = null;
                const closeModal = () => {
                    if (reportModalController && typeof reportModalController.close === 'function') {
                        reportModalController.close(true);
                        return;
                    }
                    modal.remove();
                    document.removeEventListener('keydown', handleKeydown);
                    if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
                        previouslyFocused.focus();
                    }
                };
                const handleKeydown = (event) => {
                    if (window.TMUI && typeof window.TMUI.openDialog === 'function') return;
                    if (event.key === 'Escape') {
                        closeModal();
                        return;
                    }
                    if (event.key !== 'Tab') return;
                    const focusables = Array.from(modal.querySelectorAll(focusSelector)).filter(el => !el.disabled);
                    if (!focusables.length) return;
                    const first = focusables[0];
                    const last = focusables[focusables.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                };
                if (window.TMUI && typeof window.TMUI.openDialog === 'function') {
                    reportModalController = window.TMUI.openDialog(modal, {
                        initialFocus: '.ui-comment-report-reason-btn',
                        returnFocus: previouslyFocused,
                        onClose: function () {
                            reportModalController = null;
                            modal.remove();
                            document.removeEventListener('keydown', handleKeydown);
                        }
                    });
                } else {
                    document.addEventListener('keydown', handleKeydown);
                    modal.querySelector('.ui-comment-report-reason-btn')?.focus();
                }

                // Close handlers
                modal.querySelector('.ui-comment-report-close')?.addEventListener('click', closeModal);
                modal.querySelector('.ui-comment-report-overlay')?.addEventListener('click', closeModal);

                // Reason selection
                const submitBtnEl = modal.querySelector('.ui-comment-report-submit');
                const selectedReasonEl = modal.querySelector('.ui-comment-report-selected');
                modal.querySelectorAll('.ui-comment-report-reason-btn').forEach(btn => {
                    btn.addEventListener('click', () => {
                        modal.querySelectorAll('.ui-comment-report-reason-btn').forEach(b => {
                            b.classList.remove('active');
                            b.setAttribute('aria-pressed', 'false');
                        });
                        btn.classList.add('active');
                        btn.setAttribute('aria-pressed', 'true');
                        selectedReason = btn.dataset.reason;
                        if (selectedReasonEl) {
                            selectedReasonEl.textContent = 'Seçilen gerekçe: ' + btn.textContent.trim();
                        }
                        submitBtnEl.disabled = false;
                    });
                });

                // Submit report
                submitBtnEl.addEventListener('click', () => {
                    if(!selectedReason) return;
                    submitBtnEl.disabled = true;
                    submitBtnEl.innerHTML = '<i class="bi bi-hourglass-split"></i> Gönderiliyor...';
                    const details = modal.querySelector('.ui-comment-report-details').value.trim();
                    fetch(API, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({action:'report', comment_id:commentId, reason:selectedReason, details:details, _token:CSRF})
                    }).then(r=>r.json()).then(data=>{
                        if(data._token) CSRF=data._token;
                        closeModal();
                        if(data.success) showAlert(data.message, 'success');
                        else showAlert(data.error||'Şikayet gönderilemedi.', 'error');
                    }).catch(()=>{
                        closeModal();
                        showAlert('Bağlantı hatası.','error');
                    });
                });
            }

            // -- Actions (delete, reply, edit, report) --
            function bindActions(){
                list.querySelectorAll('.ui-comment-delete').forEach(btn=>{
                    if(btn.dataset.deleteBound === '1') return;
                    btn.dataset.deleteBound = '1';
                    btn.addEventListener('click', async function(){
                        const deleteBtn = this;
                        const commentId = parseInt(deleteBtn.dataset.id, 10);
                        if(!commentId || deleteInFlightIds.has(commentId) || deleteBtn.dataset.busy === '1') return;
                        if(!await window.appConfirm('Bu yorumu silmek istediğinize emin misiniz?', { title: 'Yorum silinsin mi?', ok: 'Sil' })) return;

                        deleteInFlightIds.add(commentId);
                        setButtonBusy(deleteBtn, 'Siliniyor...');
                        fetch(API,{
                            method:'POST',
                            headers:{'Content-Type':'application/json'},
                            body:JSON.stringify({action:'delete',id:commentId,_token:CSRF})
                        }).then(r=>r.json()).then(d=>{
                            if(d._token) CSRF=d._token;
                            if(d.success){
                                showAlert('Yorum silindi.');
                                dispatchCommentDeletedEvent({ commentId: commentId });
                                loadComments(currentPage);
                            }
                            else {
                                showAlert(d.error||'Hata','error');
                                clearButtonBusy(deleteBtn);
                                deleteBtn.disabled = false;
                            }
                        }).catch(()=>{
                            showAlert('Bağlantı hatası.','error');
                            clearButtonBusy(deleteBtn);
                            deleteBtn.disabled = false;
                        }).finally(()=>{
                            deleteInFlightIds.delete(commentId);
                        });
                    });
                });
                list.querySelectorAll('.ui-comment-reply-btn').forEach(btn=>{
                    if(btn.dataset.replyBound === '1') return;
                    btn.dataset.replyBound = '1';
                    btn.addEventListener('click', function(){
                        const cid=parseInt(this.dataset.id);
                        const author=this.dataset.author;
                        const slot=list.querySelector('.ui-comment-inline-reply-slot[data-for="' + cid + '"]');
                        if(slot) createInlineReply(cid,author,slot);
                    });
                });
                // Edit handler
                list.querySelectorAll('.ui-comment-edit').forEach(btn=>{
                    if(btn.dataset.editBound === '1') return;
                    btn.dataset.editBound = '1';
                    btn.addEventListener('click', function(){
                        const cid=parseInt(this.dataset.id);
                        const item=list.querySelector('[data-comment-id="' + cid + '"]');
                        if(!item) return;
                        // Use raw Markdown body from comments array — textContent would lose formatting
                        const commentData = findCommentById(comments, cid);
                        const bodyEl=item.querySelector(':scope > .ui-comment-body .ui-comment-content-wrap > .ui-comment-text, :scope > .ui-comment-body > .ui-comment-text');
                        const currentBody = commentData ? commentData.body : (bodyEl ? bodyEl.textContent : '');
                        const slot=item.querySelector('.ui-comment-inline-reply-slot[data-for="' + cid + '"]');
                        if(slot) createInlineEdit(cid, currentBody, slot);
                    });
                });

                // Report handler
                list.querySelectorAll('.ui-comment-report-btn').forEach(btn=>{
                    if(btn.dataset.reportBound === '1') return;
                    btn.dataset.reportBound = '1';
                    btn.addEventListener('click', function(){
                        const cid=parseInt(this.dataset.id);
                        showReportModal(cid);
                    });
                });
            }

            // -- Init --
            window.addEventListener('hashchange', function() {
                ensureCommentFromHash();
            });
            loadComments(1);
            startPoll();
        })();

