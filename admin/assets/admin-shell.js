(function () {
    "use strict";

    function toastContainer() {
        return document.getElementById("toastContainer");
    }

    function defineToastFallback() {
        if (typeof window.showToast === "function") {
            return;
        }

        window.showToast = function (message, type, duration) {
            var container = toastContainer();
            if (!container) {
                return;
            }

            var options = {};
            if (message && typeof message === "object") {
                options = message;
                message = options.message || options.text || "";
                type = options.type || type;
                duration = options.duration || duration;
            } else if (duration && typeof duration === "object") {
                options = duration;
                duration = options.duration;
            }

            type = type || "info";
            duration = duration || (type === "success" ? 3200 : parseInt(container.getAttribute("data-toast-duration"), 10) || 5000);
            if (type === "error" && options.solution) {
                duration = Math.max(duration, 7600);
            }
            if (options.sticky) {
                duration = 0;
            }

            var toast = document.createElement("div");
            toast.className = "topic-toast toast-" + type;

            var icon = "bi-info-circle";
            if (type === "success") icon = "bi-check-circle-fill";
            else if (type === "error") icon = "bi-exclamation-triangle-fill";
            else if (type === "warning") icon = "bi-exclamation-circle-fill";

            var iconEl = document.createElement("i");
            iconEl.className = "bi " + icon;

            var messageEl = document.createElement("span");
            messageEl.className = "toast-content";

            if (options.title) {
                var titleEl = document.createElement("span");
                titleEl.className = "toast-title";
                titleEl.textContent = options.title;
                messageEl.appendChild(titleEl);
            }

            var bodyEl = document.createElement("span");
            bodyEl.className = "toast-message";
            bodyEl.textContent = String(message || "");
            messageEl.appendChild(bodyEl);

            if (options.solution || options.detail) {
                var detailEl = document.createElement("span");
                detailEl.className = "toast-detail";
                detailEl.textContent = options.solution || options.detail;
                messageEl.appendChild(detailEl);
            }

            if (options.actionLabel && options.actionUrl) {
                var actionEl = document.createElement("a");
                actionEl.className = "toast-action";
                actionEl.href = options.actionUrl;
                actionEl.textContent = options.actionLabel;
                if (options.actionTarget) actionEl.target = options.actionTarget;
                if (options.actionTarget === "_blank") actionEl.rel = "noopener";
                messageEl.appendChild(actionEl);
            }

            toast.appendChild(iconEl);
            toast.appendChild(messageEl);
            container.appendChild(toast);

            if (duration <= 0) {
                return;
            }

            setTimeout(function () {
                toast.classList.add("toast-out");
                setTimeout(function () {
                    toast.remove();
                }, 300);
            }, duration);
        };
    }

    function dispatchFallbackFlashes() {
        var container = toastContainer();
        if (!container || typeof window.showToast !== "function") {
            return;
        }
        if (window.showToast._uiFoundationEnhanced || container.dataset.uiFoundationFlashDispatched === "1") {
            return;
        }
        container.dataset.adminShellFlashDispatched = "1";

        [
            ["success", container.getAttribute("data-toast-success")],
            ["error", container.getAttribute("data-toast-error")],
            ["info", container.getAttribute("data-toast-info")]
        ].forEach(function (entry) {
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

            var message = normalizeAlertText(alert);
            if (message && !footerFlashMessages.has(message) && typeof window.showToast === "function") {
                window.showToast(message, alertTone(alert));
                footerFlashMessages.add(message);
            }

            alert.remove();
        });
    }

    function initSettingsTabs() {
        var tabLinks = document.querySelectorAll(".settings-tabs .nav-link");
        var sections = document.querySelectorAll(".settings-section");
        var activeTabInput = document.getElementById("activeTabInput");

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

        if (tabLinks.length === 0) {
            return;
        }

        tabLinks.forEach(function (link) {
            link.addEventListener("click", function (event) {
                event.preventDefault();
                var id = this.getAttribute("href").replace("#", "");
                activateTab(id);
                history.replaceState(null, "", "#" + id);
            });
        });

        var hash = "";
        try {
            hash = new URL(window.location.href).hash.replace("#", "");
        } catch (error) {
            hash = "";
        }
        if (hash && document.getElementById(hash)) {
            activateTab(hash);
        } else if (sections.length > 0) {
            activateTab(sections[0].id);
        }
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
    defineToastFallback();

    document.addEventListener("DOMContentLoaded", function () {
        dispatchFallbackFlashes();
        consumeInlineAdminFlashes();
        initSidebar();
        initAlertDismiss();
        initSettingsTabs();
        setTimeout(initQuillEditors, 100);
    });
})();
