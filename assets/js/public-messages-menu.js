(function () {
    "use strict";

    function getCsrfToken() {
        if (window.publicApi && typeof window.publicApi.csrfToken === "function") {
            return window.publicApi.csrfToken();
        }

        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute("content") || "" : "";
    }

    function createState(iconClass, text) {
        var state = document.createElement("div");
        state.className = "notif-menu-state";

        var icon = document.createElement("i");
        icon.className = iconClass;
        state.appendChild(icon);
        state.appendChild(document.createTextNode(text));

        return state;
    }

    function toAbsoluteUrl(value) {
        try {
            return new URL(value, window.location.origin).toString();
        } catch (error) {
            return value;
        }
    }

    function fetchJson(url, options) {
        if (window.publicFetchJson) {
            return window.publicFetchJson(url, options || {});
        }

        return Promise.reject(new Error("Public API helper yuklenemedi."));
    }

    function initMessagesMenu(root) {
        if (!root || root.dataset.publicMessagesMenuReady === "1") {
            return;
        }
        root.dataset.publicMessagesMenuReady = "1";

        var apiUrl = root.getAttribute("data-messages-api") || "";
        var toggle = root.querySelector("[data-messages-toggle]");
        var list = root.querySelector("#msgList") || root.querySelector("[data-messages-list]");
        var badge = root.querySelector("#msgBadge") || root.querySelector("[data-messages-badge]");
        var markAll = root.querySelector("[data-messages-mark-all]");
        var hasMessageBaseline = false;
        var latestMessageIds = new Map();
        var latestUnreadCounts = new Map();

        function applyMessageNotificationPreferences(preferences) {
            preferences = preferences || {};
            window.publicMessageNotificationPreferences = preferences;

            var realtime = window.publicTopbarRealtime || {};
            if (typeof realtime.setMessagePreferences === "function") {
                realtime.setMessagePreferences(preferences);
            }
        }

        function detectIncomingMessages(latest) {
            var isInitialLoad = !hasMessageBaseline;

            latest.forEach(function (thread) {
                var threadId = Number(thread.thread_id || 0);
                var messageId = Number(thread.last_message_id || 0);
                if (threadId <= 0 || messageId <= 0) {
                    return;
                }

                var previousMessageId = Number(latestMessageIds.get(threadId) || 0);
                var previousUnreadCount = Number(latestUnreadCounts.get(threadId) || 0);
                var unreadCount = Math.max(0, Number(thread.unread_count || 0));
                latestMessageIds.set(threadId, messageId);
                latestUnreadCounts.set(threadId, unreadCount);

                if (isInitialLoad || messageId <= previousMessageId || thread.last_message_is_mine) {
                    return;
                }

                var messageCount = Math.max(1, unreadCount - previousUnreadCount);

                var realtime = window.publicTopbarRealtime || {};
                if (typeof realtime.notifyMessage === "function") {
                    realtime.notifyMessage({
                        message_id: messageId,
                        message_count: messageCount,
                        thread_id: threadId,
                        sender_user_id: Number(thread.last_sender_user_id || thread.with_user_id || 0),
                        sender_name: thread.with_user_name || "",
                        thread_url: thread.thread_url || ""
                    });
                }
            });

            hasMessageBaseline = true;
        }

        function updateBadge(count) {
            if (!badge) {
                return;
            }

            var total = Number(count || 0);
            if (total > 0) {
                badge.textContent = total > 99 ? "99+" : String(total);
                badge.classList.add("is-visible");
            } else {
                badge.classList.remove("is-visible");
            }
        }

        function dropdownUrl() {
            var url = toAbsoluteUrl(apiUrl);
            if (url.indexOf("?") === -1) {
                return url + "?action=dropdown";
            }

            return url + "&action=dropdown";
        }

        function render(data) {
            if (!list || !data || data.success !== true) {
                if (list) {
                    list.innerHTML = "";
                    list.appendChild(createState("bi bi-exclamation-triangle", "Mesajlar yuklenemedi."));
                }
                return;
            }

            applyMessageNotificationPreferences(data.notification_preferences);
            updateBadge(data.unread_count || 0);
            list.innerHTML = "";

            var latest = Array.isArray(data.latest) ? data.latest : [];
            detectIncomingMessages(latest);
            if (latest.length === 0) {
                list.appendChild(createState("bi bi-chat-square", "Mesaj yok"));
                return;
            }

            latest.forEach(function (thread) {
                var threadId = Number(thread.thread_id || 0);
                var threadUrl = thread.thread_url || "";
                if (!threadUrl) {
                    return;
                }
                var unread = Number(thread.unread_count || 0);

                var item = document.createElement("a");
                item.href = threadUrl;
                item.className = "notif-item " + (unread > 0 ? "unread" : "");

                var avatarWrap = document.createElement("div");
                avatarWrap.className = "notif-item-icon";

                var avatar = document.createElement("img");
                avatar.src = thread.with_user_avatar || "";
                avatar.alt = thread.with_user_name || "Kullanici";
                avatar.width = 34;
                avatar.height = 34;
                avatar.loading = "lazy";
                avatar.style.borderRadius = "50%";
                avatar.style.objectFit = "cover";
                avatarWrap.appendChild(avatar);

                var content = document.createElement("div");
                content.className = "notif-item-content";

                var title = document.createElement("div");
                title.className = "notif-item-title";
                title.textContent = thread.with_user_name || "Kullanici";

                var message = document.createElement("div");
                message.className = "notif-item-msg";
                message.textContent = thread.last_message_preview || "Henuz mesaj yok";

                if (unread > 0) {
                    message.textContent += " · " + (unread > 99 ? "99+" : String(unread)) + " okunmamis";
                }

                content.appendChild(title);
                content.appendChild(message);
                item.appendChild(avatarWrap);
                item.appendChild(content);

                list.appendChild(item);
            });
        }

        function fetchDropdown() {
            if (!apiUrl) {
                return Promise.resolve();
            }

            return fetchJson(dropdownUrl(), {
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
                .then(render)
                .catch(function () {
                    if (!list) {
                        return;
                    }

                    list.innerHTML = "";
                    list.appendChild(createState("bi bi-exclamation-triangle", "Mesajlar yuklenemedi."));
                });
        }

        function markAllRead(event) {
            if (event) {
                event.preventDefault();
            }

            var formData = new FormData();
            formData.append("_token", getCsrfToken());
            formData.append("action", "mark_all_read");

            fetchJson(apiUrl, {
                method: "POST",
                body: formData,
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
                .then(function () {
                    fetchDropdown();
                })
                .catch(function () {
                    fetchDropdown();
                });
        }

        if (toggle) {
            toggle.addEventListener("click", function (e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                var show = !root.classList.contains("show");
                document.querySelectorAll(".notif-dropdown.show, [data-messages-dropdown].show").forEach(function (el) {
                    if (el !== root) {
                        el.classList.remove("show");
                    }
                });
                root.classList.toggle("show", show);
                toggle.setAttribute("aria-expanded", show ? "true" : "false");
                if (show) {
                    fetchDropdown();
                }
            });
        }

        if (markAll) {
            markAll.addEventListener("click", markAllRead);
        }

        document.addEventListener("click", function (event) {
            if (root.classList.contains("show") && !root.contains(event.target)) {
                root.classList.remove("show");
                if (toggle) {
                    toggle.setAttribute("aria-expanded", "false");
                }
            }
        });

        fetchDropdown();
        window.publicTopbar = window.publicTopbar || {};
        window.publicTopbar.refreshMessages = fetchDropdown;
    }

    function initAll() {
        document.querySelectorAll("[data-messages-dropdown]").forEach(initMessagesMenu);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAll);
    } else {
        initAll();
    }
})();
