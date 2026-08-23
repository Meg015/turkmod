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
                selected.textContent = "Henüz yeni dosya seçilmedi.";
            }
            if (submit) {
                submit.disabled = true;
            }
            if (reset) {
                reset.hidden = true;
            }
            if (actionText) {
                actionText.textContent = "Dosya seç";
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
                    window.showToast("Lütfen JPG, PNG, WebP veya GIF seçin.", "warning");
                    clearPreview();
                    return;
                }
                if (file.size > maxSize) {
                    window.showToast("Profil fotoğrafı en fazla 2 MB olabilir.", "warning");
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
                    actionText.textContent = "Fotoğrafı değiştir";
                }
            });
        }

        if (reset) {
            reset.addEventListener("click", clearPreview);
        }

        form.addEventListener("submit", function (event) {
            if (!input || !input.files || !input.files.length) {
                event.preventDefault();
                window.showToast("Önce bir profil fotoğrafı seçin.", "warning");
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
                window.showToast("Şifreler eşleşmiyor.", "warning");
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
