(function () {
    "use strict";

    function debounce(fn, wait) {
        var timer = null;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(null, args);
            }, wait);
        };
    }

    function throttle(fn, limit) {
        var inThrottle;
        return function() {
            var args = arguments;
            var context = this;
            if (!inThrottle) {
                fn.apply(context, args);
                inThrottle = true;
                setTimeout(function() { return inThrottle = false; }, limit);
            }
        };
    }

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text || "").replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function createSearchItem(user, onSelect) {
        var button = document.createElement("button");
        button.type = "button";
        button.className = "messages-search-item";
        button.setAttribute("data-user-id", String(user.id || 0));

        var avatar = document.createElement("img");
        avatar.src = user.avatar || "";
        avatar.alt = user.name || "Kullanici";
        avatar.width = 30;
        avatar.height = 30;
        avatar.loading = "lazy";
        avatar.setAttribute("data-ui-avatar-img", "");

        var name = document.createElement("span");
        name.textContent = user.name || "Kullanici";

        button.appendChild(avatar);
        button.appendChild(name);
        button.addEventListener("click", function () {
            onSelect(user);
        });

        return button;
    }

    function initMessagesPage(root) {
        if (!root || root.dataset.messagesPageReady === "1") {
            return;
        }
        root.dataset.messagesPageReady = "1";

        var apiUrl = root.getAttribute("data-messages-api-url") || "";
        var activeThreadId = Number(root.getAttribute("data-active-thread-id") || 0);
        var currentUserId = Number(root.getAttribute("data-current-user-id") || 0);
        var csrfToken = root.getAttribute("data-messages-csrf") || "";

        var chatModal = document.getElementById("newChatModal");
        var startForm = chatModal ? chatModal.querySelector("[data-messages-start-form]") : null;
        var searchInput = chatModal ? chatModal.querySelector("[data-messages-user-search]") : null;
        var targetInput = chatModal ? chatModal.querySelector("[data-messages-target-user-id]") : null;
        var results = chatModal ? chatModal.querySelector("[data-messages-search-results]") : null;
        var stream = root.querySelector("[data-messages-stream]");
        var sendForm = root.querySelector("[data-messages-send-form]");
        var composerTextarea = root.querySelector(".messages-composer-textarea");

        // UI State
        var threadMessages = [];
        var activeThreadData = null;
        var historyLoading = false;
        var historyExhausted = false;

        if (stream) {
            stream.scrollTop = stream.scrollHeight;
        }

        // ==========================================
        // Modal & Search
        // ==========================================
        function clearResults() {
            if(results) {
                results.innerHTML = "";
                results.hidden = true;
            }
        }

        function clearSearchInvalid() {
            if(searchInput) {
                searchInput.classList.remove("is-invalid");
                searchInput.removeAttribute("aria-invalid");
            }
        }

        function setSelectedUser(user) {
            var userId = Number(user && user.id ? user.id : 0);
            targetInput.value = userId > 0 ? String(userId) : "";
            searchInput.value = user && user.name ? String(user.name) : "";
            clearSearchInvalid();
            clearResults();
        }

        function renderResults(users) {
            results.innerHTML = "";
            if (!Array.isArray(users) || users.length === 0) {
                var empty = document.createElement("div");
                empty.className = "messages-search-empty";
                empty.textContent = "Sonuc bulunamadi.";
                results.appendChild(empty);
                results.hidden = false;
                return;
            }

            users.forEach(function (user) {
                results.appendChild(createSearchItem(user, setSelectedUser));
            });
            results.hidden = false;
        }

        function fetchUsers(query) {
            var value = String(query || "").trim();
            if (value.length < 2) {
                clearResults();
                return;
            }

            var url = new URL(apiUrl, window.location.origin);
            url.searchParams.set("action", "search");
            url.searchParams.set("q", value);
            url.searchParams.set("limit", "8");

            fetch(url.toString(), {
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var users = Array.isArray(data.users) ? data.users : [];
                renderResults(users);
            })
            .catch(function () { clearResults(); });
        }

        if (searchInput && results && targetInput) {
            var searchUsers = debounce(fetchUsers, 180);
            searchInput.addEventListener("input", function () {
                targetInput.value = "";
                clearSearchInvalid();
                searchUsers(searchInput.value);
            });

            searchInput.addEventListener("focus", function () {
                if (results.innerHTML.trim() !== "") {
                    results.hidden = false;
                }
            });

            document.addEventListener("click", function (event) {
                if (results && !results.contains(event.target) && event.target !== searchInput) {
                    results.hidden = true;
                }
            });

            if (startForm) {
                startForm.addEventListener("submit", function (event) {
                    var userId = Number(targetInput.value || 0);
                    if (userId > 0) return;
                    event.preventDefault();
                    searchInput.classList.add("is-invalid");
                    searchInput.setAttribute("aria-invalid", "true");
                    if (typeof window.showToast === "function") window.showToast("Lütfen listeden bir kullanıcı seçin.", "warning");
                    searchInput.focus();
                });
            }
        }

        var newChatBtn = root.querySelector("[data-messages-new-chat-toggle]");
        if (newChatBtn && chatModal) {
            newChatBtn.addEventListener("click", function () {
                chatModal.removeAttribute("hidden");
                chatModal.removeAttribute("aria-hidden");
                setTimeout(function () {
                    if (searchInput) searchInput.focus();
                }, 50);
            });

            var closeButtons = chatModal.querySelectorAll("[data-messages-modal-close]");
            closeButtons.forEach(function (btn) {
                btn.addEventListener("click", function () {
                    chatModal.setAttribute("hidden", "true");
                    chatModal.setAttribute("aria-hidden", "true");
                    if (targetInput) targetInput.value = "";
                    if (searchInput) searchInput.value = "";
                    var bodyText = startForm ? startForm.querySelector("textarea") : null;
                    if (bodyText) bodyText.value = "";
                    clearResults();
                });
            });
        }

        var sidebarSearch = root.querySelector("[data-messages-sidebar-search-input]");
        var threadItems = root.querySelectorAll("[data-thread-item]");
        if (sidebarSearch && threadItems.length > 0) {
            sidebarSearch.addEventListener("input", function () {
                var q = String(sidebarSearch.value || "").toLowerCase().trim();
                for (var i = 0; i < threadItems.length; i++) {
                    var item = threadItems[i];
                    var strongEl = item.querySelector("strong");
                    var previewEl = item.querySelector(".messages-thread-preview");
                    var username = strongEl ? (strongEl.textContent || "").toLowerCase() : "";
                    var preview = previewEl ? (previewEl.textContent || "").toLowerCase() : "";
                    if (username.indexOf(q) > -1 || preview.indexOf(q) > -1) {
                        item.style.display = "";
                    } else {
                        item.style.display = "none";
                    }
                }
            });
        }

        // ==========================================
        // Composer & AJAX Send
        // ==========================================
        if (composerTextarea) {
            composerTextarea.addEventListener("input", function () {
                this.style.height = "auto";
                var newHeight = this.scrollHeight;
                if (newHeight > 120) {
                    this.style.height = "120px";
                    this.style.overflowY = "auto";
                } else {
                    this.style.height = newHeight + "px";
                    this.style.overflowY = "hidden";
                }
            });

            composerTextarea.addEventListener("keydown", function (e) {
                if (e.key === "Enter" && !e.shiftKey) {
                    e.preventDefault();
                    var form = this.closest("form");
                    if (form) {
                        var submitBtn = form.querySelector("button[type='submit']");
                        if (submitBtn) submitBtn.click();
                        else form.submit();
                    }
                }
            });

            var typingTimeout = null;
            var lastTypingSent = 0;
            var stopTypingTimer = null;

            composerTextarea.addEventListener("input", function() {
                if (this.value.trim().length > 0) {
                    var now = Date.now();
                    // Send typing status if we haven't sent one in the last 3 seconds
                    if (now - lastTypingSent > 3000) {
                        lastTypingSent = now;
                        var fd = new FormData();
                        fd.append("action", "typing");
                        fd.append("_token", csrfToken);
                        fd.append("thread_id", activeThreadId);
                        fetch(apiUrl, {
                            method: "POST",
                            body: fd,
                            headers: { "X-Requested-With": "XMLHttpRequest" }
                        }).catch(function(){});
                    }

                    clearTimeout(stopTypingTimer);
                    stopTypingTimer = setTimeout(function() {
                        var fdStop = new FormData();
                        fdStop.append("action", "stop_typing");
                        fdStop.append("_token", csrfToken);
                        fdStop.append("thread_id", activeThreadId);
                        fetch(apiUrl, {
                            method: "POST",
                            body: fdStop,
                            headers: { "X-Requested-With": "XMLHttpRequest" }
                        }).catch(function(){});
                        lastTypingSent = 0;
                    }, 1500);
                } else {
                    clearTimeout(stopTypingTimer);
                    var fdStop = new FormData();
                    fdStop.append("action", "stop_typing");
                    fdStop.append("_token", csrfToken);
                    fdStop.append("thread_id", activeThreadId);
                    fetch(apiUrl, {
                        method: "POST",
                        body: fdStop,
                        headers: { "X-Requested-With": "XMLHttpRequest" }
                    }).catch(function(){});
                    lastTypingSent = 0;
                }
            });
        }

        // ==========================================
        // Message Rendering & Actions
        // ==========================================
        function handleMessageAction(e) {
            var btn = e.target.closest('button[data-msg-action]');
            if (!btn) return;

            var article = btn.closest('article.msg');
            var msgId = article ? Number(article.getAttribute('data-message-id')) : 0;
            if (msgId <= 0) return;

            var action = btn.getAttribute('data-msg-action');
            if (action === 'delete') {
                if (!confirm("Bu mesajı silmek istediğinize emin misiniz?")) return;
                var fd = new FormData();
                fd.append("action", "delete");
                fd.append("_token", csrfToken);
                fd.append("message_id", msgId);
                fetch(apiUrl, { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" } })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.ok) {
                        if (typeof window.showToast === "function") window.showToast(data.message || "Mesaj silindi.", "success");
                        pollThread(false); // refresh thread
                    }
                    else if (typeof window.showToast === "function") window.showToast(data.message || "Hata oluştu", "error");
                });
            } else if (action === 'edit') {
                var msgBodyEl = article.querySelector('.msg-body');
                var oldText = msgBodyEl.textContent.trim();
                var newBody = prompt("Mesajı düzenle:", oldText);
                if (newBody !== null && newBody.trim() !== "") {
                    var fd2 = new FormData();
                    fd2.append("action", "edit");
                    fd2.append("_token", csrfToken);
                    fd2.append("message_id", msgId);
                    fd2.append("body", newBody);
                    fetch(apiUrl, { method: "POST", body: fd2, headers: { "X-Requested-With": "XMLHttpRequest" } })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.ok) {
                            if (typeof window.showToast === "function") window.showToast(data.message || "Mesaj duzenlendi.", "success");
                            pollThread(false); // refresh thread
                        }
                        else if (typeof window.showToast === "function") window.showToast(data.message || "Hata oluştu", "error");
                    });
                }
            }
        }

        if (stream) {
            stream.addEventListener('click', handleMessageAction);
        }

        function mergeMessages(newMessages) {
            var map = {};
            threadMessages.forEach(function(m) { map[m.id] = m; });
            newMessages.forEach(function(m) { map[m.id] = m; });
            threadMessages = Object.values(map).sort(function(a, b) { return a.id - b.id; });
        }

        function formatDateOnlyLabel(ymd) {
            var parts = String(ymd || "").split("-");
            if (parts.length !== 3) {
                return "";
            }

            return parts[2] + "." + parts[1] + "." + parts[0];
        }

        function renderStream() {
            if (!stream || !activeThreadData) return;
            if (threadMessages.length === 0) {
                stream.innerHTML = '<div class="messages-stream-empty"><i class="bi bi-envelope-open" aria-hidden="true"></i><p>Bu sohbette henuz mesaj yok. Ilk mesaji gonderebilirsiniz.</p></div>';
                return;
            }

            var html = "";
            var prevDate = null;
            var prevSenderId = null;

            threadMessages.forEach(function(msg, i) {
                var isMine = msg.is_mine;
                var senderId = msg.sender_user_id;
                var nextSenderId = (i + 1 < threadMessages.length) ? threadMessages[i+1].sender_user_id : null;

                var isGroupStart = (prevSenderId === null || prevSenderId !== senderId);
                var isGroupEnd = (nextSenderId === null || nextSenderId !== senderId);

                var msgDateRaw = msg.created_at || "";
                var msgDate = msgDateRaw.split(" ")[0];

                if (msgDate !== prevDate && msgDate !== "1970-01-01") {
                    var label = formatDateOnlyLabel(msgDate);
                    html += '<div class="msg-date-separator"><span>' + escapeHtml(label) + '</span></div>';
                }
                prevDate = msgDate;

                var groupClasses = "msg " + (isMine ? "is-mine" : "is-theirs");
                if (isGroupStart) groupClasses += " msg-group-first";
                if (isGroupEnd) groupClasses += " msg-group-last";
                if (!isGroupStart && !isGroupEnd) groupClasses += " msg-group-middle";
                if (msg.is_deleted) groupClasses += " is-deleted";

                var showAvatar = !isMine && (isGroupEnd || (!isGroupStart && !isGroupEnd && i === threadMessages.length - 1));

                html += '<article class="' + groupClasses + '" data-message-id="' + msg.id + '">';

                if (!isMine) {
                    html += '<div class="msg-avatar">';
                    if (showAvatar) {
                        html += '<img src="' + escapeHtml(activeThreadData.with_user_avatar) + '" alt="' + escapeHtml(activeThreadData.with_user_name) + '" width="32" height="32" loading="lazy" data-ui-avatar-img>';
                    }
                    html += '</div>';
                }

                html += '<div class="msg-content">';

                // Content body
                html += '<div class="msg-body">';
                if (msg.is_deleted) {
                    html += '<em>' + escapeHtml(msg.body) + '</em>';
                } else {
                    html += escapeHtml(msg.body);
                }
                html += '</div>';

                // Meta info
                if (isGroupEnd || isMine) {
                    html += '<div class="msg-meta">';
                    html += '<time datetime="' + escapeHtml(msg.created_at) + '">' + escapeHtml(msg.created_at_label) + '</time>';
                    if (isMine) {
                        var titleAttr = (msg.is_read_by_recipient && msg.read_at_label) ? ' title="Görüldü: ' + escapeHtml(msg.read_at_label) + '"' : '';
                        html += '<span class="msg-read-state' + (msg.is_read_by_recipient ? ' is-read' : '') + '"' + titleAttr + '>';
                        if (msg.is_read_by_recipient) {
                            html += '<i class="bi bi-check-all" aria-hidden="true"></i>';
                        } else {
                            html += '<i class="bi bi-check" aria-hidden="true"></i>';
                        }
                        html += '</span>';
                    }
                    html += '</div>';
                }
                html += '</div>'; // close msg-content

                // Options menu (if mine and not deleted)
                if (isMine && !msg.is_deleted) {
                    var msgTime = new Date(msg.created_at.replace(' ', 'T')).getTime();
                    var isOld = (today.getTime() - msgTime) > 15 * 60 * 1000;
                    if (!isOld || isNaN(msgTime)) {
                        html += '<div class="msg-options dropdown">';
                        html += '<button class="msg-options-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots-vertical"></i></button>';
                        html += '<ul class="dropdown-menu dropdown-menu-end">';
                        html += '<li><button class="dropdown-item" type="button" data-msg-action="edit"><i class="bi bi-pencil"></i> Düzenle</button></li>';
                        html += '<li><button class="dropdown-item text-danger" type="button" data-msg-action="delete"><i class="bi bi-trash"></i> Sil</button></li>';
                        html += '</ul></div>';
                    }
                }

                html += '</article>';
                prevSenderId = senderId;
            });

            if (activeThreadData.is_typing_now) {
                html += '<div class="msg-typing-indicator" id="typingIndicator">';
                html += '<span>' + escapeHtml(activeThreadData.with_user_name) + ' yazıyor</span>';
                html += '<div class="typing-dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>';
                html += '</div>';
            }

            stream.innerHTML = html;
        }

        // ==========================================
        // Infinite Scroll History
        // ==========================================
        if (stream) {
            stream.addEventListener('scroll', function() {
                if (stream.scrollTop === 0 && !historyLoading && !historyExhausted && threadMessages.length > 0) {
                    historyLoading = true;
                    var oldScrollHeight = stream.scrollHeight;
                    var beforeId = threadMessages[0].id;

                    var url = new URL(apiUrl, window.location.origin);
                    url.searchParams.set("action", "history");
                    url.searchParams.set("thread_id", activeThreadId);
                    url.searchParams.set("before_id", beforeId);

                    fetch(url.toString(), { headers: { "X-Requested-With": "XMLHttpRequest" } })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        if (data.ok && data.messages) {
                            if (data.messages.length === 0) {
                                historyExhausted = true;
                            } else {
                                mergeMessages(data.messages);
                                renderStream();
                                stream.scrollTop = stream.scrollHeight - oldScrollHeight;
                            }
                        }
                    })
                    .finally(function() {
                        historyLoading = false;
                    });
                }
            });
        }

        if (sendForm) {
            sendForm.addEventListener("submit", function(e) {
                e.preventDefault();
                var body = composerTextarea ? composerTextarea.value.trim() : "";
                if (!body) return;

                var btn = sendForm.querySelector("button[type='submit']");
                if (btn) btn.disabled = true;

                var fd = new FormData(sendForm);
                fetch(apiUrl, {
                    method: "POST",
                    body: fd,
                    headers: { "X-Requested-With": "XMLHttpRequest" }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.ok) {
                        if (composerTextarea) {
                            composerTextarea.value = "";
                            composerTextarea.style.height = "auto";
                            composerTextarea.focus();
                        }
                        if (typeof window.showToast === "function") {
                            window.showToast(data.message || "Mesaj gonderildi.", "success");
                        }
                        // Mesaj gönderilince "yazıyor..." göstergesini hemen temizle
                        if (activeThreadData) {
                            activeThreadData.is_typing_now = false;
                        }
                        clearTimeout(window.typingTimer);
                        lastTypingSent = 0;
                        pollThread(true);
                    } else if (typeof window.showToast === "function") {
                        window.showToast(data.message || "Mesaj gonderilemedi", "error");
                    }
                })
                .catch(function() {
                    if (typeof window.showToast === "function") {
                        window.showToast("Baglanti hatasi", "error");
                    }
                })
                .finally(function() {
                    if (btn) btn.disabled = false;
                });
            });
        }

        var isPolling = false;
        function pollThread(forceScroll) {
            if (activeThreadId <= 0 || isPolling) return;
            isPolling = true;

            var url = new URL(apiUrl, window.location.origin);
            url.searchParams.set("action", "thread");
            url.searchParams.set("thread_id", activeThreadId);

            fetch(url.toString(), {
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.ok && data.thread && data.messages) {
                    var wasAtBottom = false;
                    if (stream) {
                        wasAtBottom = stream.scrollHeight - stream.scrollTop - stream.clientHeight < 50;
                    }

                    activeThreadData = data.thread;
                    var prevCount = threadMessages.length;
                    mergeMessages(data.messages);
                    // Yeni mesaj geldiyse "yazıyor..." göstergesini temizle
                    if (threadMessages.length > prevCount) {
                        activeThreadData.is_typing_now = false;
                        clearTimeout(window.typingTimer);
                    }
                    renderStream();

                    if (stream && (wasAtBottom || forceScroll)) {
                        stream.scrollTop = stream.scrollHeight;
                    }

                    // Optional: update sidebar thread preview
                    var thItem = root.querySelector('[data-thread-id="' + activeThreadId + '"]');
                    if (thItem && data.thread) {
                        var previewEl = thItem.querySelector('.messages-thread-preview');
                        var timeEl = thItem.querySelector('.messages-thread-topline time');
                        if (previewEl) previewEl.textContent = data.thread.last_message_preview;
                        if (timeEl) timeEl.textContent = data.thread.last_message_at_label;
                    }
                }
            })
            .finally(function() {
                isPolling = false;
            });
        }

        // Initial fetch to populate threadMessages array properly
        if (activeThreadId > 0) {
            pollThread(true);
        }

        // ==========================================
        // WebSocket Connection
        // ==========================================
        var ws = null;
        var wsConnected = false;
        var wsReconnectTimer = null;
        var wsReconnectDelay = 5000;
        var wsReconnectMaxDelay = 30000;

        function scheduleWebSocketReconnect() {
            if (activeThreadId <= 0 || currentUserId <= 0) {
                return;
            }

            if (wsReconnectTimer) {
                clearTimeout(wsReconnectTimer);
            }

            wsReconnectTimer = setTimeout(function () {
                wsReconnectTimer = null;
                initWebSocket();
            }, wsReconnectDelay);

            wsReconnectDelay = Math.min(wsReconnectDelay * 2, wsReconnectMaxDelay);
        }

        function initWebSocket() {
            if (activeThreadId <= 0 || currentUserId <= 0) return;
            if (wsReconnectTimer) {
                clearTimeout(wsReconnectTimer);
                wsReconnectTimer = null;
            }
            var protocol = window.location.protocol === "https:" ? "wss://" : "ws://";
            var wsUrl = protocol + window.location.hostname + ":8080/?user_id=" + currentUserId;

            try {
                ws = new WebSocket(wsUrl);
                ws.onopen = function() {
                    wsConnected = true;
                    wsReconnectDelay = 5000;
                };
                ws.onmessage = function(event) {
                    try {
                        var data = JSON.parse(event.data);
                        if (data.thread_id === activeThreadId) {
                            if (data.type === 'typing') {
                                if (data.user_id !== currentUserId && activeThreadData) {
                                    activeThreadData.is_typing_now = true;
                                    renderStream();
                                    clearTimeout(window.typingTimer);
                                    window.typingTimer = setTimeout(function() {
                                        if (activeThreadData) {
                                            activeThreadData.is_typing_now = false;
                                            renderStream();
                                        }
                                    }, 4000);
                                }
                            } else if (data.type === 'stop_typing') {
                                if (data.user_id !== currentUserId && activeThreadData) {
                                    activeThreadData.is_typing_now = false;
                                    clearTimeout(window.typingTimer);
                                    renderStream();
                                }
                            } else if (data.type === 'new_message' || data.type === 'edit_message' || data.type === 'delete_message') {
                                pollThread(false);
                            }
                        } else {
                            if (data.type === 'new_message') {
                                // Provide an indication in sidebar if it's another thread
                                var thItem = root.querySelector('[data-thread-id="' + data.thread_id + '"]');
                                if (thItem) {
                                    var previewEl = thItem.querySelector('.messages-thread-preview');
                                    if (previewEl) previewEl.innerHTML = "<strong>Yeni mesaj var</strong>";
                                }
                            }
                        }
                    } catch(e) {}
                };
                ws.onclose = function() {
                    wsConnected = false;
                    scheduleWebSocketReconnect();
                };
                ws.onerror = function() {
                    wsConnected = false;
                };
            } catch (e) {
                wsConnected = false;
                scheduleWebSocketReconnect();
            }
        }

        initWebSocket();

        // Fallback polling if WebSocket fails (e.g. wss:// to plain ws:// server)
        var fallbackPollIntervalMs = 2000;
        setInterval(function() {
            if (document.visibilityState !== "visible") {
                return;
            }
            if (!wsConnected && activeThreadId > 0) {
                pollThread(false);
            }
        }, fallbackPollIntervalMs);

    }

    document.addEventListener("DOMContentLoaded", function () {
        initMessagesPage(document.querySelector("[data-messages-root]"));
    });
})();
