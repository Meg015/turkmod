window.ensureUploadQuillEditors = function() {
    document.querySelectorAll('textarea.rich-editor').forEach(function(textarea) {
        if (textarea.dataset.editorReady === '1') return;
        textarea.dataset.editorReady = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'upload-rich-fallback';
        wrapper.innerHTML = [
            '<div class="upload-rich-toolbar" aria-label="Metin biçimlendirme">',
                '<button type="button" data-command="bold"><i class="bi bi-type-bold"></i></button>',
                '<button type="button" data-command="italic"><i class="bi bi-type-italic"></i></button>',
                '<button type="button" data-command="underline"><i class="bi bi-type-underline"></i></button>',
                '<button type="button" data-command="insertUnorderedList"><i class="bi bi-list-ul"></i></button>',
                '<button type="button" data-command="insertOrderedList"><i class="bi bi-list-ol"></i></button>',
                '<button type="button" data-command="createLink"><i class="bi bi-link-45deg"></i></button>',
                '<button type="button" data-command="removeFormat"><i class="bi bi-eraser"></i></button>',
            '</div>',
            '<div class="upload-rich-editor" contenteditable="true" role="textbox" aria-multiline="true"></div>'
        ].join('');
        const editor = wrapper.querySelector('.upload-rich-editor');
        editor.innerHTML = textarea.value || '';
        textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);
        textarea.classList.add('is-hidden');

        function syncEditor() {
            textarea.value = editor.innerHTML.trim();
        }

        wrapper.querySelectorAll('[data-command]').forEach(function(button) {
            button.addEventListener('click', async function() {
                editor.focus();
                const command = button.dataset.command;
                if (command === 'createLink') {
                    const url = await window.appPrompt('Bağlantı URL', { title: 'Bağlantı ekle' });
                    if (url) document.execCommand(command, false, url);
                } else {
                    document.execCommand(command, false, null);
                }
                syncEditor();
            });
        });
        editor.addEventListener('input', syncEditor);
        textarea.form && textarea.form.addEventListener('submit', syncEditor);
    });
};
window.addEventListener('load', window.ensureUploadQuillEditors);
window.ensureUploadQuillEditors();
