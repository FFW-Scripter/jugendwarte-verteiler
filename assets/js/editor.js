(function () {
    const editor = document.getElementById('editor');
    const toolbar = document.getElementById('toolbar');
    const bodyField = document.getElementById('body');
    const form = document.getElementById('compose-form');

    if (!editor || !toolbar || !bodyField || !form) {
        return;
    }

    if (bodyField.value.trim() !== '') {
        editor.innerHTML = bodyField.value;
    }

    toolbar.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-cmd]');
        if (!button) {
            return;
        }

        event.preventDefault();
        editor.focus();

        const command = button.getAttribute('data-cmd');
        const value = button.getAttribute('data-value');

        if (command === 'createLink') {
            const url = window.prompt('Link-Adresse (https:// oder mailto:)');
            if (url) {
                document.execCommand('createLink', false, url.trim());
            }
            return;
        }

        document.execCommand(command, false, value || null);
    });

    editor.addEventListener('paste', function (event) {
        if (!event.clipboardData) {
            return;
        }

        const html = event.clipboardData.getData('text/html');
        const text = event.clipboardData.getData('text/plain');
        event.preventDefault();

        if (html) {
            document.execCommand('insertHTML', false, html);
            return;
        }

        document.execCommand('insertText', false, text);
    });

    form.addEventListener('submit', function () {
        bodyField.value = editor.innerHTML;
    });
}());
