(function () {
    "use strict";

    function toastContainer() {
        return document.getElementById("toastContainer");
    }

    function dispatchInlineFlashes() {
        var container = toastContainer();
        if (!container || typeof window.showToast !== "function") {
            return;
        }
        if (!window.showToast._uiFoundationEnhanced || container.dataset.uiFoundationFlashDispatched === "1") {
            return;
        }
        container.dataset.uiFoundationFlashDispatched = "1";

        [
            ["success", container.getAttribute("data-toast-success")],
            ["error", container.getAttribute("data-toast-error")],
            ["warning", container.getAttribute("data-toast-warning")],
            ["info", container.getAttribute("data-toast-info")]
        ].forEach(function (entry) {
            if (entry[0] === "info" && shouldSuppressInfoToasts()) {
                return;
            }
            if (entry[1]) {
                window.showToast(entry[1], entry[0]);
            }
        });
    }

    function initSidebar() {
        var sidebar = document.getElementById("admin-sidebar");
        var overlay = document.getElementById("admin-overlay");
        var hamburger = document.getElementById("admin-hamburger-btn");

        function openSidebar() {
            if (!sidebar || !overlay) return;
            sidebar.classList.add("open");
            overlay.classList.add("visible");
            document.body.classList.add("is-admin-sidebar-open");
        }

        function closeSidebar() {
            if (!sidebar || !overlay) return;
            sidebar.classList.remove("open");
            overlay.classList.remove("visible");
            document.body.classList.remove("is-admin-sidebar-open");
        }

        if (hamburger) {
            hamburger.addEventListener("click", function () {
                sidebar && sidebar.classList.contains("open") ? closeSidebar() : openSidebar();
            });
        }
        if (overlay) {
            overlay.addEventListener("click", closeSidebar);
        }

        document.addEventListener("keydown", function (event) {
            if (event.key === "Escape") {
                closeSidebar();
            }
        });

        document.querySelectorAll(".admin-menu-group-toggle").forEach(function (toggle) {
            toggle.addEventListener("click", function () {
                var expanded = this.getAttribute("aria-expanded") === "true";
                this.setAttribute("aria-expanded", expanded ? "false" : "true");
                var body = this.nextElementSibling;
                if (body) body.classList.toggle("is-collapsed", expanded);
            });
        });

        if (window.innerWidth < 992) {
            document.querySelectorAll(".admin-menu-item").forEach(function (link) {
                link.addEventListener("click", closeSidebar);
            });
        }
    }

    function initAlertDismiss() {
        document.querySelectorAll(".ui-admin-alert-close").forEach(function (button) {
            button.addEventListener("click", function () {
                var alert = this.closest(".ui-admin-alert");
                if (alert) {
                    alert.classList.add("is-dismissed");
                }
            });
        });
    }

    function normalizeAlertText(alert) {
        if (!alert) {
            return "";
        }

        var clone = alert.cloneNode(true);
        clone.querySelectorAll(".ui-admin-alert-close, .notification-flash-close").forEach(function (button) {
            button.remove();
        });

        return (clone.textContent || "").replace(/\s+/g, " ").trim();
    }

    function alertTone(alert) {
        var classes = alert.className || "";
        if (/danger|error/.test(classes)) return "error";
        if (/warning/.test(classes)) return "warning";
        if (/success/.test(classes)) return "success";
        return "info";
    }

    function shouldSuppressInfoToasts() {
        return !!document.querySelector(".logs-subtabs, [data-suppress-info-toasts='1']");
    }

    function shouldSuppressInlineAlertToasts(alert) {
        return !!(alert && alert.closest("[data-suppress-inline-alert-toasts='1']"));
    }

    function shouldKeepInlineAlert(alert) {
        if (!alert) {
            return false;
        }
        if (alert.closest("#toastContainer") || alert.hasAttribute("data-keep-inline-alert")) {
            return true;
        }
        return !!alert.closest("[hidden], [aria-hidden='true'], [role='dialog'], .media-modal-overlay, .ui-admin-modal-overlay");
    }

    function consumeInlineAdminFlashes() {
        var container = toastContainer();
        var footerFlashMessages = new Set();

        if (container) {
            ["success", "error", "info"].forEach(function (type) {
                var message = container.getAttribute("data-toast-" + type);
                if (message) {
                    footerFlashMessages.add(message.replace(/\s+/g, " ").trim());
                }
            });
        }

        document.querySelectorAll([
            ".ui-admin-alert.ui-alert--success",
            ".ui-admin-alert.ui-alert--error",
            ".ui-admin-alert.ui-alert--warning",
            ".ui-admin-alert.ui-alert--info",
            ".ui-admin-alert.notification-flash"
        ].join(",")).forEach(function (alert) {
            if (shouldKeepInlineAlert(alert)) {
                return;
            }
            if (shouldSuppressInlineAlertToasts(alert)) {
                return;
            }

            var message = normalizeAlertText(alert);
            var tone = alertTone(alert);
            if (tone === "info" && shouldSuppressInfoToasts()) {
                return;
            }

            if (message && !footerFlashMessages.has(message) && typeof window.showToast === "function") {
                window.showToast(message, tone);
                footerFlashMessages.add(message);
            }

            alert.remove();
        });
    }

    function initSettingsTabs() {
        if (document.documentElement.dataset.settingsTabsBound === "1") {
            return;
        }

        var tabLinks = document.querySelectorAll(".settings-tabs .nav-link");
        var sections = document.querySelectorAll(".settings-section");
        var activeTabInput = document.getElementById("activeTabInput");

        if (tabLinks.length === 0) {
            return;
        }

        document.documentElement.dataset.settingsTabsBound = "1";

        function activateTab(targetId) {
            tabLinks.forEach(function (link) {
                link.classList.toggle("active", link.getAttribute("href") === "#" + targetId);
            });
            sections.forEach(function (section) {
                section.classList.toggle("active", section.id === targetId);
            });
            if (activeTabInput) {
                activeTabInput.value = targetId;
            }
        }

        function hashTarget() {
            var hash = "";
            try {
                hash = String(window.location.hash || "").replace(/^#/, "");
            } catch (error) {
                hash = "";
            }

            if (hash.indexOf(":") !== -1) {
                hash = hash.split(":")[0];
            }

            return hash;
        }

        function activateFromHashOrDefault() {
            var target = hashTarget();
            if (target && document.getElementById(target)) {
                activateTab(target);
            } else if (sections.length > 0) {
                activateTab(sections[0].id);
            }
        }

        tabLinks.forEach(function (link) {
            link.addEventListener("click", function (event) {
                event.preventDefault();
                var id = this.getAttribute("href").replace("#", "");
                activateTab(id);
                history.replaceState(null, "", "#" + id);
            });
        });

        window.addEventListener("hashchange", activateFromHashOrDefault);
        activateFromHashOrDefault();
        window.setTimeout(activateFromHashOrDefault, 0);
    }

    function initQuillEditors() {
        var richEditors = document.querySelectorAll(".rich-editor");
        if (richEditors.length === 0) {
            return;
        }
        if (typeof window.Quill === "undefined") {
            console.error("Quill is not loaded. Rich editor cannot be initialized.");
            return;
        }

        var Quill = window.Quill;
        try {
            var AlignStyle = Quill.import("attributors/style/align");
            Quill.register(AlignStyle, true);
            var ColorStyle = Quill.import("attributors/style/color");
            Quill.register(ColorStyle, true);
            var BackgroundStyle = Quill.import("attributors/style/background");
            Quill.register(BackgroundStyle, true);
        } catch (error) {
            console.warn("Could not register Quill styles:", error);
        }

        richEditors.forEach(function (element) {
            if (element.tagName.toLowerCase() !== "textarea" || element.dataset.quillReady === "1") {
                return;
            }

            var wrapper = document.createElement("div");
            wrapper.className = "quill-container";

            var editorDiv = document.createElement("div");
            editorDiv.innerHTML = element.value;
            wrapper.appendChild(editorDiv);

            element.parentNode.insertBefore(wrapper, element.nextSibling);
            element.classList.add("ui-admin-hidden");
            element.dataset.quillReady = "1";

            try {
                var quill = new Quill(editorDiv, {
                    theme: "snow",
                    modules: {
                        toolbar: [
                            [{ header: [1, 2, 3, false] }],
                            ["bold", "italic", "underline", "strike"],
                            [{ color: [] }, { background: [] }],
                            ["blockquote", "code-block"],
                            [{ list: "ordered" }, { list: "bullet" }],
                            ["link", "image", "video"],
                            ["clean"],
                            [{ align: [] }]
                        ]
                    }
                });

                quill.on("text-change", function () {
                    element.value = quill.root.innerHTML;
                });

                element.quillInstance = quill;
            } catch (error) {
                console.error("Error initializing Quill editor:", error);
                element.classList.remove("ui-admin-hidden");
                element.dataset.quillReady = "0";
                wrapper.remove();
            }
        });
    }

    window.initQuillEditors = initQuillEditors;

    function initAdminShell() {
        dispatchInlineFlashes();
        consumeInlineAdminFlashes();
        initSidebar();
        initAlertDismiss();
        initSettingsTabs();
        setTimeout(initQuillEditors, 100);
    }

    if (window.adminPage && typeof window.adminPage.register === "function") {
        window.adminPage.register("*", initAdminShell, { id: "admin-shell:core" });
    }
})();
