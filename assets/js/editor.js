(function () {
    const editor = document.getElementById('editor');
    const toolbar = document.getElementById('toolbar');
    const bodyField = document.getElementById('body');
    const form = document.getElementById('compose-form');
    const imageInput = document.getElementById('editor-image-input');
    const imageButton = document.getElementById('editor-image-btn');
    const emojiButton = document.getElementById('editor-emoji-btn');
    const emojiPanel = document.getElementById('emoji-panel');
    const foreColorInput = document.getElementById('editor-fore-color');
    const backColorInput = document.getElementById('editor-back-color');

    const MAX_IMAGE_BYTES = 512 * 1024;
    const MAX_IMAGES = 6;

    const EMOJIS = [
        '😀', '😊', '😉', '😍', '👍', '👏', '🙏', '💪',
        '✅', '❌', '⚠️', '❗', '❓', '⭐', '🔥', '🚒',
        '🧯', '📅', '📍', '⏰', '📣', '📝', '📎', '🎉',
        '🏃', '🎯', '❤️', '💙', '💚', '🟡', '🔴', '🟢',
    ];

    if (!editor || !toolbar || !bodyField || !form) {
        return;
    }

    if (bodyField.value.trim() !== '') {
        editor.innerHTML = bodyField.value;
    }

    function countImages() {
        return editor.querySelectorAll('img').length;
    }

    function focusEditor() {
        editor.focus();
    }

    function runCommand(command, value) {
        focusEditor();
        document.execCommand(command, false, value || null);
    }

    function insertHtml(html) {
        focusEditor();
        document.execCommand('insertHTML', false, html);
    }

    function insertImageFile(file) {
        if (!file || !file.type.startsWith('image/')) {
            window.alert('Bitte eine Bilddatei wählen (JPEG, PNG, GIF oder WebP).');
            return;
        }

        if (file.size > MAX_IMAGE_BYTES) {
            window.alert('Das Bild ist zu groß. Maximal 512 KB pro Inline-Bild.');
            return;
        }

        if (countImages() >= MAX_IMAGES) {
            window.alert('Es sind maximal ' + MAX_IMAGES + ' Inline-Bilder möglich.');
            return;
        }

        const reader = new FileReader();
        reader.onload = function () {
            if (typeof reader.result !== 'string') {
                return;
            }

            const safeSrc = reader.result.replace(/"/g, '&quot;');
            insertHtml(
                '<img src="' + safeSrc + '" alt="" class="inline-image">'
            );
        };
        reader.readAsDataURL(file);
    }

    function buildEmojiPanel() {
        if (!emojiPanel) {
            return;
        }

        emojiPanel.innerHTML = '';
        EMOJIS.forEach(function (emoji) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'emoji-btn';
            button.textContent = emoji;
            button.title = 'Emoji einfügen';
            button.addEventListener('click', function () {
                insertHtml(emoji);
                closeEmojiPanel();
            });
            emojiPanel.appendChild(button);
        });
    }

    function openEmojiPanel() {
        if (!emojiPanel || !emojiButton) {
            return;
        }

        emojiPanel.hidden = false;
        emojiButton.setAttribute('aria-expanded', 'true');
    }

    function closeEmojiPanel() {
        if (!emojiPanel || !emojiButton) {
            return;
        }

        emojiPanel.hidden = true;
        emojiButton.setAttribute('aria-expanded', 'false');
    }

    toolbar.addEventListener('click', function (event) {
        const button = event.target.closest('button[data-cmd]');
        if (!button) {
            return;
        }

        event.preventDefault();

        const command = button.getAttribute('data-cmd');
        const value = button.getAttribute('data-value');

        if (command === 'createLink') {
            const url = window.prompt('Link-Adresse (https:// oder mailto:)');
            if (url) {
                runCommand('createLink', url.trim());
            }
            return;
        }

        runCommand(command, value);
    });

    if (imageButton && imageInput) {
        imageButton.addEventListener('click', function () {
            imageInput.click();
        });

        imageInput.addEventListener('change', function () {
            const file = imageInput.files && imageInput.files[0];
            if (file) {
                insertImageFile(file);
            }
            imageInput.value = '';
        });
    }

    if (emojiButton) {
        buildEmojiPanel();
        emojiButton.addEventListener('click', function (event) {
            event.preventDefault();
            if (emojiPanel && emojiPanel.hidden) {
                openEmojiPanel();
            } else {
                closeEmojiPanel();
            }
        });
    }

    document.addEventListener('click', function (event) {
        if (!emojiPanel || emojiPanel.hidden) {
            return;
        }

        if (event.target === emojiButton || emojiPanel.contains(event.target)) {
            return;
        }

        closeEmojiPanel();
    });

    if (foreColorInput) {
        foreColorInput.addEventListener('input', function () {
            runCommand('foreColor', foreColorInput.value);
        });
    }

    if (backColorInput) {
        backColorInput.addEventListener('input', function () {
            runCommand('backColor', backColorInput.value);
        });
    }

    editor.addEventListener('paste', function (event) {
        if (!event.clipboardData) {
            return;
        }

        const items = event.clipboardData.items;
        if (items) {
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image/') === 0) {
                    event.preventDefault();
                    insertImageFile(items[i].getAsFile());
                    return;
                }
            }
        }

        const html = event.clipboardData.getData('text/html');
        const text = event.clipboardData.getData('text/plain');
        event.preventDefault();

        if (html) {
            document.execCommand('insertText', false, text);
            return;
        }

        document.execCommand('insertText', false, text);
    });

    editor.addEventListener('dragover', function (event) {
        event.preventDefault();
    });

    editor.addEventListener('drop', function (event) {
        const file = event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files[0];
        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        event.preventDefault();
        insertImageFile(file);
    });

    form.addEventListener('submit', function () {
        bodyField.value = editor.innerHTML;
    });
}());
