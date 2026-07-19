(function () {
    "use strict";

    function getCsrfToken() {
        if (window.publicApi && typeof window.publicApi.csrfToken === "function") {
            return window.publicApi.csrfToken();
        }

        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute("content") || "" : "";
    }

    function setCsrfToken(token) {
        if (window.publicApi && typeof window.publicApi.updateCsrfToken === "function") {
            window.publicApi.updateCsrfToken(token);
            return;
        }
        if (!token) {
            return;
        }

        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute("content", token);
        }
    }

    function notifyError(error, defaultMessage) {
        var message = error && error.message ? error.message : defaultMessage;
        if (window.showToast) {
            window.showToast(message || "Bildirim islemi tamamlanamadi.", "error");
        }
    }

    function fetchJson(url, options) {
        if (window.publicFetchJson) {
            return window.publicFetchJson(url, options || {}).then(function (data) {
                if (data && (data._token || data.csrf_token)) {
                    setCsrfToken(data._token || data.csrf_token);
                }
                return data;
            });
        }

        return Promise.reject(new Error("Public API helper yuklenemedi."));
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

    function getIconState(type) {
        if (type === "success") return { icon: "bi-check-circle", state: " is-success" };
        if (type === "warning") return { icon: "bi-exclamation-triangle", state: " is-warning" };
        if (type === "error") return { icon: "bi-x-circle", state: " is-error" };
        if (type === "system") return { icon: "bi-gear", state: " is-system" };
        return { icon: "bi-info-circle", state: "" };
    }

    function isApiSuccess(data) {
        return !!data && (data.ok === true || data.success === true);
    }

    function initNotificationMenu(root) {
        if (!root || root.dataset.publicNotifMenuReady === "1") {
            return;
        }
        root.dataset.publicNotifMenuReady = "1";

        var apiUrl = root.getAttribute("data-notif-api") || "";
        var readApiUrl = root.getAttribute("data-notif-read-api") || "";
        var notificationUrl = root.getAttribute("data-notif-url") || "";
        var toggle = root.querySelector("[data-notif-toggle]");
        var list = root.querySelector("#notifList") || root.querySelector("[data-notif-list]");
        var badge = root.querySelector("#notifBadge") || root.querySelector("[data-notif-badge]");
        var markAll = root.querySelector("[data-notif-mark-all]");

        function updateNotificationBadge(count) {
            if (!badge) {
                return;
            }

            if (count > 0) {
                badge.textContent = count > 99 ? "99+" : String(count);
                badge.classList.add("is-visible");
            } else {
                badge.classList.remove("is-visible");
            }
        }

        function renderNotifications(data) {
            if (!list || !isApiSuccess(data)) {
                return;
            }

            updateNotificationBadge(data.show_badge === false ? 0 : data.unread_count);
            list.innerHTML = "";

            if (data.disabled || data.muted) {
                list.appendChild(createState(
                    data.disabled ? "bi bi-bell-slash" : "bi bi-volume-mute",
                    data.disabled ? "Bildirim merkezi kapal\\u0131" : "Bildirimler sessize al\\u0131nd\\u0131"
                ));
                return;
            }

            if (!Array.isArray(data.latest) || data.latest.length === 0) {
                list.appendChild(createState("bi bi-inbox", "Bildirim yok"));
                return;
            }

            data.latest.forEach(function (notification) {
                var iconState = getIconState(notification.type);
                var item = document.createElement("a");
                item.href = notification.link ? notification.link : notificationUrl;
                item.className = "notif-item " + (notification.is_read ? "" : "unread"); item.setAttribute("data-notif-open","true"); item.setAttribute("data-id",notification.id);

                var iconWrap = document.createElement("div");
                iconWrap.className = "notif-item-icon" + iconState.state;

                var iconEl = document.createElement("i");
                iconEl.className = "bi " + iconState.icon;
                iconWrap.appendChild(iconEl);

                var content = document.createElement("div");
                content.className = "notif-item-content";

                var title = document.createElement("div");
                title.className = "notif-item-title";
                title.textContent = notification.title || "";

                var message = document.createElement("div");
                message.className = "notif-item-msg";
                message.textContent = notification.message || "";

                content.appendChild(title);
                content.appendChild(message);
                item.appendChild(iconWrap);
                item.appendChild(content);

                if (!notification.is_read && data.auto_mark_on_open !== false) {
                    item.addEventListener("click", function (event) {
                        event.preventDefault();

                        var formData = new FormData();
                        formData.append("_token", getCsrfToken());
                        formData.append("id", notification.id);

                        fetchJson(readApiUrl, {
                            method: "POST",
                            body: formData,
                            credentials: "same-origin",
                            headers: {
                                "Accept": "application/json",
                                "X-Requested-With": "XMLHttpRequest"
                            }
                        })
                        .then(function (data) {
                            if (!isApiSuccess(data)) {
                                throw new Error(data && data.message ? data.message : "Bildirimler g\\u00fcncellenemedi.");
                            }
                            return data;
                        })
                        .then(function () {
                            window.location.href = item.href;
                        })
                        .catch(function (error) {
                            notifyError(error, "Bildirimler guncellenemedi.");
                            window.location.href = item.href;
                        });
                    });
                }

                list.appendChild(item);
            });
        }

        function fetchNotifications() {
            if (!apiUrl) {
                return Promise.resolve();
            }

            return fetchJson(apiUrl, {
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
                .then(renderNotifications)
                .catch(function () {
                    if (list) {
                        list.innerHTML = "";
                        list.appendChild(createState("bi bi-exclamation-triangle", "Bildirimler yuklenemedi"));
                    }
                    updateNotificationBadge(0);
                });
        }

        function markAllNotificationsAsRead(event) {
            if (event) {
                event.preventDefault();
            }

            var previousBadge = badge ? badge.textContent || "0" : "0";
            if (list) {
                list.querySelectorAll(".notif-item.unread").forEach(function (item) {
                    item.classList.remove("unread");
                });
            }
            updateNotificationBadge(0);

            var formData = new FormData();
            formData.append("_token", getCsrfToken());
            formData.append("id", "all");

            fetchJson(readApiUrl, {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
                .then(function (data) {
                    if (!isApiSuccess(data)) {
                        throw new Error(data.message || "Bildirimler g\\u00fcncellenemedi.");
                    }
                    if (window.showToast) {
                        window.showToast("Bildirimler okundu olarak i\\u015faretlendi.", "success");
                    }
                    fetchNotifications();
                    if (notificationUrl) {
                        var currentPath = new URL(window.location.href).pathname.replace(/\/+$/, "");
                        var notificationPath = new URL(notificationUrl, window.location.origin).pathname.replace(/\/+$/, "");
                        if (currentPath === notificationPath) {
                            window.location.reload();
                        }
                    }
                })
                .catch(function (error) {
                    updateNotificationBadge(parseInt(previousBadge.replace(/\D/g, ""), 10) || 0);
                    fetchNotifications();
                    notifyError(error, "Bildirimler guncellenemedi.");
                });
        }

        if (toggle) {
            toggle.addEventListener("click", function () {
                root.classList.toggle("show");
                toggle.setAttribute("aria-expanded", root.classList.contains("show") ? "true" : "false");
                if (root.classList.contains("show")) {
                    fetchNotifications();
                }
            });
        }

        if (markAll) {
            markAll.addEventListener("click", markAllNotificationsAsRead);
        }

        document.addEventListener("click", function (event) {
            if (root.classList.contains("show") && !root.contains(event.target)) {
                root.classList.remove("show");
                if (toggle) {
                    toggle.setAttribute("aria-expanded", "false");
                }
            }
        });

        fetchNotifications();
        window.updateNotificationBadge = updateNotificationBadge;
        window.fetchNotifications = fetchNotifications;
    }

    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("[data-notif-dropdown]").forEach(initNotificationMenu);
    });
})();
