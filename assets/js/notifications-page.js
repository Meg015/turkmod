(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var root = document.querySelector("[data-notifications-page], [data-notifications-root]");
        if (!root) {
            return;
        }

        function rootAttribute(primaryName, fallbackName) {
            return root.getAttribute(primaryName) || (fallbackName ? root.getAttribute(fallbackName) : "") || "";
        }

        function boolAttr(value) {
            return value === "1" || value === "true";
        }

        var csrfToken = rootAttribute("data-notifications-csrf", "data-csrf-token");
        var readEndpoint = rootAttribute("data-notifications-read-endpoint", "data-read-endpoint");
        var readMoreEnabled = boolAttr(rootAttribute("data-notifications-read-more", "data-read-more-enabled"));
        var autoMarkOnOpen = boolAttr(rootAttribute("data-notifications-auto-mark", "data-auto-mark-on-open"));

        function postNotificationRead(id) {
            var formData = new FormData();
            formData.append("_token", csrfToken);
            formData.append("id", id);

            return fetch(readEndpoint, {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            }).then(function (response) {
                return response.json();
            });
        }

        function refreshMessageToggles() {
            if (!readMoreEnabled) {
                return;
            }

            root.querySelectorAll("[data-notif-message]").forEach(function (message) {
                var item = message.closest("[data-notif-item]");
                var toggle = item ? item.querySelector("[data-notif-toggle], [data-notification-message-toggle]") : null;
                if (!toggle) {
                    return;
                }

                toggle.hidden = !(message.scrollHeight > message.clientHeight + 2);
            });
        }

        function initPreferenceGroups() {
            root.querySelectorAll("[data-notification-preference-group]").forEach(function (group) {
                var master = group.querySelector("[data-notification-group-toggle]");
                var items = Array.from(group.querySelectorAll("[data-notification-group-item]"));
                if (!master || items.length === 0) {
                    return;
                }

                function setGroupState(enabled) {
                    items.forEach(function (input) {
                        input.checked = enabled;
                    });
                    master.checked = enabled;
                    master.indeterminate = false;
                    group.classList.toggle("is-group-disabled", !enabled);
                }

                function syncMasterFromItems() {
                    var checkedCount = items.filter(function (input) {
                        return input.checked;
                    }).length;
                    master.checked = checkedCount > 0;
                    master.indeterminate = checkedCount > 0 && checkedCount < items.length;
                    group.classList.toggle("is-group-disabled", checkedCount === 0);
                }

                master.addEventListener("change", function () {
                    setGroupState(master.checked);
                });

                items.forEach(function (input) {
                    input.addEventListener("change", syncMasterFromItems);
                });

                if (!master.checked) {
                    setGroupState(false);
                    return;
                }
                syncMasterFromItems();
            });
        }

        initPreferenceGroups();
        refreshMessageToggles();

        document.addEventListener("click", function (event) {
            var toggle = event.target.closest("[data-notif-toggle], [data-notification-message-toggle]");
            if (toggle && root.contains(toggle)) {
                var item = toggle.closest("[data-notif-item]");
                var message = item ? item.querySelector("[data-notif-message]") : null;
                if (!message) {
                    return;
                }

                var expanded = message.classList.toggle("is-expanded");
                toggle.innerHTML = expanded
                    ? '<span>Daha k\\u0131sa g\\u00f6ster</span><i class="bi bi-chevron-up"></i>'
                    : '<span>Daha fazla g\\u00f6ster</span><i class="bi bi-chevron-down"></i>';
                return;
            }

            var notificationLink = event.target.closest("[data-notif-open]");
            if (notificationLink) {
                event.preventDefault();
                var targetUrl = notificationLink.href;
                if (!autoMarkOnOpen) {
                    window.location.href = targetUrl;
                    return;
                }

                postNotificationRead(notificationLink.getAttribute("data-id")).finally(function () {
                    window.location.href = targetUrl;
                });
            }
        });

        var markAllButton = root.querySelector("[data-mark-all-read]");
        if (!markAllButton) {
            return;
        }

        markAllButton.addEventListener("click", function () {
            if (markAllButton.disabled) {
                return;
            }

            var originalHtml = markAllButton.innerHTML;
            var feed = document.querySelector("[data-notif-feed]");
            var unreadMetric = document.querySelector("[data-notif-unread]");
            var readMetric = document.querySelector("[data-notif-read]");
            var totalMetric = document.querySelector("[data-notif-total]");
            var sidebarUnread = document.querySelector("[data-sidebar-unread]");
            var filterUnread = document.querySelector("[data-filter-unread]");
            var filterRead = document.querySelector("[data-filter-read]");
            var unreadItems = Array.from(document.querySelectorAll("[data-notif-item].is-unread")).map(function (item) {
                var status = item.querySelector(".notification-status");
                return {
                    item: item,
                    status: status,
                    statusHtml: status ? status.innerHTML : "",
                    statusClass: status ? status.className : ""
                };
            });
            var previousState = {
                unreadMetric: unreadMetric ? unreadMetric.textContent : "",
                readMetric: readMetric ? readMetric.textContent : "",
                sidebarUnread: sidebarUnread ? sidebarUnread.textContent : "",
                filterUnread: filterUnread ? filterUnread.textContent : "",
                filterRead: filterRead ? filterRead.textContent : ""
            };
            var total = totalMetric ? parseInt(totalMetric.textContent.replace(/\D/g, ""), 10) || 0 : 0;
            var previousUnreadCount = parseInt(String(previousState.unreadMetric || previousState.filterUnread || "0").replace(/\D/g, ""), 10) || 0;

            function applyReadState() {
                unreadItems.forEach(function (entry) {
                    entry.item.classList.remove("is-unread");
                    entry.item.classList.add("is-read");

                    if (entry.status) {
                        entry.status.classList.remove("is-unread");
                        entry.status.classList.add("is-read");
                        var typeLabel = entry.status.getAttribute("data-type-label") || "";
                        entry.status.innerHTML = '<i class="bi bi-check2-circle"></i> Okundu' + (typeLabel ? " \\u00b7 " + typeLabel : "");
                    }
                });

                if (unreadMetric) unreadMetric.textContent = "0";
                if (readMetric) readMetric.textContent = String(total);
                if (sidebarUnread) {
                    sidebarUnread.textContent = "0";
                    sidebarUnread.classList.add("is-muted");
                }
                if (filterUnread) filterUnread.textContent = "0";
                if (filterRead) filterRead.textContent = String(total);
                if (typeof window.updateNotificationBadge === "function") {
                    window.updateNotificationBadge(0);
                }
            }

            function restoreReadState() {
                unreadItems.forEach(function (entry) {
                    entry.item.classList.remove("is-read");
                    entry.item.classList.add("is-unread");
                    if (entry.status) {
                        entry.status.className = entry.statusClass;
                        entry.status.innerHTML = entry.statusHtml;
                    }
                });

                if (unreadMetric) unreadMetric.textContent = previousState.unreadMetric;
                if (readMetric) readMetric.textContent = previousState.readMetric;
                if (sidebarUnread) {
                    sidebarUnread.textContent = previousState.sidebarUnread;
                    sidebarUnread.classList.remove("is-muted");
                }
                if (filterUnread) filterUnread.textContent = previousState.filterUnread;
                if (filterRead) filterRead.textContent = previousState.filterRead;
                if (typeof window.updateNotificationBadge === "function") {
                    window.updateNotificationBadge(previousUnreadCount);
                }
            }

            markAllButton.disabled = true;
            markAllButton.innerHTML = '<i class="bi bi-arrow-repeat spin"></i><span>\\u0130\\u015fleniyor...</span>';
            if (feed) feed.classList.add("is-updating");
            applyReadState();

            postNotificationRead("all")
                .then(function (data) {
                    if (!data || !data.ok) {
                        throw new Error(data && data.message ? data.message : "Bildirimler g\\u00fcncellenemedi.");
                    }

                    if (sidebarUnread) {
                        sidebarUnread.remove();
                    }

                    markAllButton.innerHTML = '<i class="bi bi-check2"></i><span>Okundu</span>';
                    if (feed) feed.classList.remove("is-updating");
                    if (window.showToast) {
                        window.showToast("T\\u00fcm bildirimler okundu olarak i\\u015faretlendi.", "success");
                    }
                    window.setTimeout(function () {
                        markAllButton.remove();
                    }, 1200);
                })
                .catch(function (error) {
                    restoreReadState();
                    if (feed) feed.classList.remove("is-updating");
                    markAllButton.disabled = false;
                    markAllButton.innerHTML = originalHtml;
                    if (window.showToast) {
                        window.showToast(error && error.message ? error.message : "Bildirimler g\\u00fcncellenemedi.", "error");
                    }
                });
        });
    });
})();
