(function () {
    'use strict';

    function slugify(value) {
        return String(value || '')
            .toLocaleLowerCase('tr-TR')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/ı/g, 'i')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 191);
    }

    function initEditor(form) {
        var textarea = form.querySelector('[data-static-page-body]');
        var host = form.querySelector('[data-static-page-editor]');
        if (!textarea || !host || typeof window.Quill === 'undefined') return;

        try {
            ['align', 'color', 'background'].forEach(function (name) {
                var attributor = window.Quill.import('attributors/style/' + name);
                window.Quill.register(attributor, true);
            });
        } catch (error) {}

        var editor = new window.Quill(host, {
            theme: 'snow',
            placeholder: 'Sayfa içeriğini yazın...',
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'code-block', 'link', 'image', 'video'],
                    [{ align: [] }],
                    ['clean']
                ]
            }
        });
        if (textarea.value) {
            try {
                editor.setContents(editor.clipboard.convert(textarea.value), 'silent');
            } catch (error) {
                editor.setText(textarea.value, 'silent');
            }
        }
        var sync = function () { textarea.value = editor.root.innerHTML; };
        editor.on('text-change', sync);
        form.addEventListener('submit', sync);
    }

    function initSlug(form) {
        var title = form.querySelector('[data-static-page-title]');
        var slug = form.querySelector('[data-static-page-slug]');
        if (!title || !slug) return;
        var automatic = slug.value.trim() === '';
        slug.addEventListener('input', function () { automatic = slug.value.trim() === ''; });
        title.addEventListener('input', function () {
            if (automatic) slug.value = slugify(title.value);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('[data-static-page-form]');
        if (!form) return;
        initEditor(form);
        initSlug(form);
    });
})();
