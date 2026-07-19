(function () {
    "use strict";

    document.addEventListener("DOMContentLoaded", function () {
        var root = document.querySelector("[data-notifications-page]") || document.querySelector("[data-notifications-root]");
        if (!root) {
            return;
        }

        function rootAttribute(name) {
            if (Array.isArray(name)) {
                for (var index = 0; index < name.length; index += 1) {
                    var value = root.getAttribute(name[index]) || "";
                    if (value) {
                        return value;
                    }
                }
                return "";
            }

            return root.getAttribute(name) || "";
        }

        function boolAttr(value) {
            return value === "1" || value === "true";
        }

        function isApiSuccess(data) {
            return !!data && (data.ok === true || data.success === true);
        }

        var csrfToken = rootAttribute(["data-notifications-csrf", "data-csrf-token"]);
        var readEndpoint = rootAttribute(["data-notifications-read-endpoint", "data-read-endpoint"]);
        var deleteEndpoint = rootAttribute(["data-notifications-delete-endpoint", "data-delete-endpoint"]);
        var readMoreEnabled = boolAttr(rootAttribute(["data-notifications-read-more", "data-read-more-enabled"]));
        var autoMarkOnOpen = boolAttr(rootAttribute(["data-notifications-auto-mark", "data-auto-mark-on-open"]));

        function setCsrfToken(token) {
            if (!token) {
                return;
            }

            csrfToken = token;
            if (window.publicApi && typeof window.publicApi.updateCsrfToken === "function") {
                window.publicApi.updateCsrfToken(token);
            }
            root.setAttribute("data-notifications-csrf", token);
            root.setAttribute("data-csrf-token", token);
        }

        function currentCsrfToken() {
            var publicToken = window.publicApi && typeof window.publicApi.csrfToken === "function"
                ? window.publicApi.csrfToken()
                : "";
            var metaToken = document.querySelector('meta[name="csrf-token"]');
            var token = publicToken || (metaToken ? (metaToken.getAttribute("content") || "") : "");
            if (token && token !== csrfToken) {
                setCsrfToken(token);
            }

            return csrfToken;
        }

        function refreshCsrfToken() {
            if (!window.publicApi || typeof window.publicApi.refreshCsrfToken !== "function") {
                return Promise.resolve(false);
            }

            return window.publicApi.refreshCsrfToken().then(function (refreshed) {
                currentCsrfToken();
                return refreshed;
            });
        }

        function sendNotificationPost(endpoint, buildFormData) {
            if (!endpoint) {
                return Promise.reject(new Error("Bildirim servisi adresi bulunamadi. Lutfen sayfayi yenileyip tekrar deneyin."));
            }

            var token = currentCsrfToken();
            var formData = buildFormData(token);

            if (!window.publicFetchJson) {
                return Promise.reject(new Error("Public API helper yuklenemedi."));
            }

            return window.publicFetchJson(endpoint, {
                method: "POST",
                body: formData,
                credentials: "same-origin",
                headers: {
                    "Accept": "application/json",
                    "X-CSRF-Token": token
                }
            }).then(function (data) {
                if (data && (data._token || data.csrf_token)) {
                    setCsrfToken(data._token || data.csrf_token);
                }
                return data || {};
            });
        }

        function postNotificationRead(id) {
            return sendNotificationPost(readEndpoint, function (token) {
                var formData = new FormData();
                formData.append("_token", token);
                formData.append("id", id);
                return formData;
            });
        }

        function postNotificationDelete(ids) {
            return sendNotificationPost(deleteEndpoint, function (token) {
                var formData = new FormData();
                formData.append("_token", token);
                ids.forEach(function (id) {
                    formData.append("ids[]", String(id));
                });
                return formData;
            });
        }

        window.addEventListener("pageshow", function (event) {
            if (!event.persisted) {
                currentCsrfToken();
                return;
            }

            refreshCsrfToken();
        });

        function refreshNotificationsPage() {
            var refreshUrl;
            try {
                var parsedUrl = new URL(window.location.href);
                parsedUrl.searchParams.set("_r", String(Date.now()));
                refreshUrl = parsedUrl.toString();
            } catch (error) {
                refreshUrl = window.location.href;
            }

            try {
                window.location.replace(refreshUrl);
            } catch (error) {
                window.location.href = refreshUrl;
            }

            window.setTimeout(function () {
                if (window.location.href !== refreshUrl) {
                    window.location.href = refreshUrl;
                }
            }, 500);
        }

        function getNotificationSelectionInputs() {
            return Array.from(root.querySelectorAll("[data-notif-select]"));
        }

        function syncNotificationSelectionState() {
            var items = getNotificationSelectionInputs();
            var checkedItems = items.filter(function (input) {
                return input.checked;
            });
            var selectAll = root.querySelector("[data-notif-select-all]");
            var deleteSelected = root.querySelector("[data-notif-delete-selected]");

            if (selectAll) {
                selectAll.checked = items.length > 0 && checkedItems.length === items.length;
                selectAll.indeterminate = checkedItems.length > 0 && checkedItems.length < items.length;
            }

            if (deleteSelected) {
                deleteSelected.disabled = checkedItems.length === 0;
            }
        }

        function setNotificationSelection(enabled) {
            getNotificationSelectionInputs().forEach(function (input) {
                input.checked = enabled;
            });
            syncNotificationSelectionState();
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

        function syncPreferenceEffect(input) {
            if (!input) {
                return;
            }
            var row = input.closest("[data-notification-effect-row]");
            if (!row) {
                return;
            }
            var effect = row.querySelector("[data-notification-effect]");
            if (!effect) {
                return;
            }
            var nextText = input.checked ? row.getAttribute("data-effect-on") : row.getAttribute("data-effect-off");
            effect.textContent = nextText || "";
            effect.classList.toggle("is-disabled", !input.checked);
        }

        function initPreferenceEffects() {
            root.querySelectorAll("[data-notification-effect-row] input[type='checkbox']").forEach(function (input) {
                syncPreferenceEffect(input);
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
                        syncPreferenceEffect(input);
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
                    input.addEventListener("change", function () {
                        syncPreferenceEffect(input);
                        syncMasterFromItems();
                    });
                });

                if (!master.checked) {
                    setGroupState(false);
                    return;
                }
                syncMasterFromItems();
            });
        }

        function initPreferenceTabs() {
            var tabs = Array.from(root.querySelectorAll("[data-notification-settings-tab]"));
            var panels = Array.from(root.querySelectorAll("[data-notification-settings-panel]"));
            if (tabs.length === 0 || panels.length === 0) {
                return;
            }

            function activate(tabKey) {
                tabs.forEach(function (tab) {
                    var isActive = tab.getAttribute("data-notification-settings-tab") === tabKey;
                    tab.classList.toggle("is-active", isActive);
                    tab.setAttribute("aria-selected", isActive ? "true" : "false");
                });

                panels.forEach(function (panel) {
                    var isActive = panel.getAttribute("data-notification-settings-panel") === tabKey;
                    panel.classList.toggle("is-active", isActive);
                    panel.hidden = !isActive;
                });
            }

            tabs.forEach(function (tab) {
                tab.addEventListener("click", function () {
                    activate(tab.getAttribute("data-notification-settings-tab") || "");
                });
            });

            var activeTab = tabs.find(function (tab) {
                return tab.classList.contains("is-active");
            }) || tabs[0];
            activate(activeTab.getAttribute("data-notification-settings-tab") || "");
        }

        initPreferenceTabs();
        initPreferenceEffects();
        initPreferenceGroups();
        refreshMessageToggles();

        root.addEventListener("change", function (event) {
            var preferenceInput = event.target.closest("input[type='checkbox']");
            if (preferenceInput && root.contains(preferenceInput) && preferenceInput.closest("[data-notification-effect-row]")) {
                syncPreferenceEffect(preferenceInput);
            }

            var selectAll = event.target.closest("[data-notif-select-all]");
            if (selectAll && root.contains(selectAll)) {
                setNotificationSelection(selectAll.checked);
                return;
            }

            var select = event.target.closest("[data-notif-select]");
            if (select && root.contains(select)) {
                syncNotificationSelectionState();
            }
        });

        syncNotificationSelectionState();

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
        if (markAllButton) {
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
                        if (!isApiSuccess(data)) {
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
        }

        var deleteSelectedButton = root.querySelector("[data-notif-delete-selected]");
        if (deleteSelectedButton) {
            deleteSelectedButton.addEventListener("click", function () {
                if (deleteSelectedButton.disabled) {
                    return;
                }

                var selectedIds = getNotificationSelectionInputs().filter(function (input) {
                    return input.checked;
                }).map(function (input) {
                    return Number(input.value || 0);
                }).filter(function (id) {
                    return id > 0;
                });

                if (selectedIds.length === 0) {
                    return;
                }

                var isSingle = selectedIds.length === 1;
                var confirmMessage = isSingle
                    ? "Seçili bildirimi silmek istediğinize emin misiniz?"
                    : selectedIds.length + " bildirimi silmek istediğinize emin misiniz?";
                if (!window.confirm(confirmMessage)) {
                    return;
                }

                var originalHtml = deleteSelectedButton.innerHTML;
                deleteSelectedButton.disabled = true;
                deleteSelectedButton.innerHTML = '<i class="bi bi-arrow-repeat spin"></i><span>Siliniyor...</span>';

                postNotificationDelete(selectedIds)
                    .then(function (data) {
                        if (!isApiSuccess(data)) {
                            throw new Error(data && data.message ? data.message : "Bildirimler silinemedi.");
                        }

                        var refreshTriggered = false;
                        function refreshAfterToast() {
                            if (refreshTriggered) {
                                return;
                            }
                            refreshTriggered = true;
                            refreshNotificationsPage();
                        }

                        try {
                            if (window.showToast) {
                                var deletedCount = parseInt(String(data.deleted_count || selectedIds.length), 10) || selectedIds.length;
                                window.showToast(deletedCount + " bildirim silindi.", "success", {
                                    onClose: function (reason) {
                                        if (reason !== "overflow") {
                                            refreshAfterToast();
                                        }
                                    }
                                });
                            } else {
                                refreshAfterToast();
                            }
                        } catch (toastError) {
                            refreshAfterToast();
                        }
                    })
                    .catch(function (error) {
                        deleteSelectedButton.disabled = false;
                        deleteSelectedButton.innerHTML = originalHtml;
                        syncNotificationSelectionState();
                        if (window.showToast) {
                            window.showToast(error && error.message ? error.message : "Bildirimler silinemedi.", "error");
                        }
                    });
            });
        }
    });
})();
