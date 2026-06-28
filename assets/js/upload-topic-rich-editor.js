window.ensureUploadQuillEditors = function() {
    function initFallbackEditor(textarea) {
        if (textarea.dataset._fallbackEditorInit === '1') return;
        textarea.dataset._fallbackEditorInit = '1';
        textarea.dataset._quillInit = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'upload-rich-fallback';
        wrapper.innerHTML = [
            '<div class="upload-rich-toolbar" aria-label="Metin biçimlendirme">',
                '<button type="button" data-command="bold"><i class="bi bi-type-bold"></i></button>',
                '<button type="button" data-command="italic"><i class="bi bi-type-italic"></i></button>',
                '<button type="button" data-command="underline"><i class="bi bi-type-underline"></i></button>',
                '<button type="button" data-command="insertUnorderedList"><i class="bi bi-list-ul"></i></button>',
                '<button type="button" data-command="insertOrderedList"><i class="bi bi-list-ol"></i></button>',
                '<button type="button" data-command="formatBlock" data-value="blockquote"><i class="bi bi-quote"></i></button>',
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
                let value = button.dataset.value || null;
                if (command === 'createLink') {
                    value = await window.appPrompt('Bağlantı adresi', { title: 'Bağlantı ekle' });
                    if (!value) return;
                }
                document.execCommand(command, false, value);
                syncEditor();
            });
        });

        editor.addEventListener('input', function() {
            syncEditor();
            validateUploadFieldRules(false);
            scheduleUploadTopicDraftSave();
        });
        editor.addEventListener('blur', function() {
            syncEditor();
            validateUploadFieldRules(false);
            scheduleUploadTopicDraftSave();
        });
        textarea.form && textarea.form.addEventListener('submit', syncEditor);
    }

    if (typeof Quill === 'undefined') {
        document.querySelectorAll('textarea.rich-editor').forEach(initFallbackEditor);
        return;
    }

    try {
        const alignAttributor = Quill.import('attributors/style/align');
        Quill.register(alignAttributor, true);
        const colorAttributor = Quill.import('attributors/style/color');
        Quill.register(colorAttributor, true);
        const backgroundAttributor = Quill.import('attributors/style/background');
        Quill.register(backgroundAttributor, true);
    } catch (error) {}

    document.querySelectorAll('textarea.rich-editor').forEach(function(textarea) {
        if (textarea.dataset._quillInit === '1') return;
        textarea.dataset._quillInit = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'quill-container upload-quill-container';
        const editor = document.createElement('div');
        wrapper.appendChild(editor);
        textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);
        textarea.classList.add('is-hidden');

        const quill = new Quill(editor, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['blockquote', 'link', 'image', 'video'],
                    [{ align: [] }],
                    ['clean']
                ]
            }
        });

        if (textarea.value) {
            try {
                quill.setContents(quill.clipboard.convert(textarea.value), 'silent');
            } catch (error) {
                quill.setText(textarea.value);
            }
        }

        quill.on('text-change', function() {
            textarea.value = quill.root.innerHTML;
            validateUploadFieldRules(false);
            scheduleUploadTopicDraftSave();
        });
        textarea.quillInstance = quill;
    });
};

window.addEventListener('load', window.ensureUploadQuillEditors);
window.ensureUploadQuillEditors();
