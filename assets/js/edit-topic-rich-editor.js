window.ensureUploadQuillEditors = function() {
    if (typeof Quill === 'undefined') {
        console.error('Quill is not loaded. Edit rich editor cannot be initialized.');
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
        });
        textarea.quillInstance = quill;
    });
};

window.addEventListener('load', window.ensureUploadQuillEditors);
window.ensureUploadQuillEditors();
