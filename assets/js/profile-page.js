(function () {
    "use strict";

    function setActiveProfileTab() {
        var shell = document.querySelector("[data-profile-page]");
        var activeTab = shell ? shell.getAttribute("data-profile-active-tab") || "" : "";
        if (activeTab !== "") {
            document.body.setAttribute("data-tab", activeTab);
        }
    }

    function initAvatarForm() {
        var form = document.getElementById("profileAvatarForm");
        if (!form) {
            return;
        }

        var input = form.querySelector("[data-avatar-input]");
        var preview = form.querySelector("[data-avatar-preview]");
        var selected = form.querySelector("[data-avatar-selected]");
        var submit = form.querySelector("[data-avatar-submit]");
        var reset = form.querySelector("[data-avatar-reset]");
        var actionText = form.querySelector("[data-avatar-action-text]");
        var initialPreview = preview ? preview.innerHTML : "";
        var previewUrl = "";
        var maxSize = 2 * 1024 * 1024;
        var allowedTypes = ["image/jpeg", "image/png", "image/webp", "image/gif"];

        function clearPreview() {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = "";
            }
            if (preview) {
                preview.innerHTML = initialPreview;
            }
            if (selected) {
                selected.textContent = "Hen\\u00fcz yeni dosya se\\u00e7ilmedi.";
            }
            if (submit) {
                submit.disabled = true;
            }
            if (reset) {
                reset.hidden = true;
            }
            if (actionText) {
                actionText.textContent = "Dosya se\\u00e7";
            }
            if (input) {
                input.value = "";
            }
        }

        if (input) {
            input.addEventListener("change", function () {
                var file = input.files && input.files[0] ? input.files[0] : null;
                if (!file) {
                    clearPreview();
                    return;
                }
                if (!allowedTypes.includes(file.type)) {
                    if (window.showToast) {
                        window.showToast("L\\u00fctfen JPG, PNG, WebP veya GIF se\\u00e7in.", "warning");
                    }
                    clearPreview();
                    return;
                }
                if (file.size > maxSize) {
                    if (window.showToast) {
                        window.showToast("Profil foto\\u011fraf\\u0131 en fazla 2 MB olabilir.", "warning");
                    }
                    clearPreview();
                    return;
                }

                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                }
                previewUrl = URL.createObjectURL(file);
                if (preview) {
                    var image = document.createElement("img");
                    image.src = previewUrl;
                    image.alt = "";
                    image.width = 64;
                    image.height = 64;
                    image.decoding = "async";
                    image.setAttribute("data-avatar-img", "");
                    image.setAttribute("data-ui-avatar-img", "");
                    preview.replaceChildren(image);
                }
                if (selected) {
                    selected.textContent = file.name;
                }
                if (submit) {
                    submit.disabled = false;
                }
                if (reset) {
                    reset.hidden = false;
                }
                if (actionText) {
                    actionText.textContent = "Foto\\u011fraf\\u0131 de\\u011fi\\u015ftir";
                }
            });
        }

        if (reset) {
            reset.addEventListener("click", clearPreview);
        }

        form.addEventListener("submit", function (event) {
            if (!input || !input.files || !input.files.length) {
                event.preventDefault();
                if (window.showToast) {
                    window.showToast("\\u00d6nce bir profil foto\\u011fraf\\u0131 se\\u00e7in.", "warning");
                }
            }
        });
    }

    function initPasswordForm() {
        var form = document.getElementById("profilePasswordForm");
        if (!form) {
            return;
        }

        form.addEventListener("submit", function (event) {
            var newPassword = document.getElementById("pw_new");
            var confirmPassword = document.getElementById("pw_confirm");
            if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
                event.preventDefault();
                if (window.showToast) {
                    window.showToast("\\u015eifreler e\\u015fle\\u015fmiyor.", "warning");
                }
                confirmPassword.focus();
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function () {
        setActiveProfileTab();
        initAvatarForm();
        initPasswordForm();
    });
})();
