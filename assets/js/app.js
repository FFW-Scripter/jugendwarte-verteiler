(function () {
    const form = document.getElementById('compose-form');
    const fileInput = document.getElementById('attachments');
    const fileList = document.getElementById('file-list');
    const selectedCount = document.getElementById('selected-count');
    const recipientBoxes = document.querySelectorAll('input[name="recipients[]"]');
    const toggleAll = document.getElementById('toggle-recipients');
    const smtpButton = document.getElementById('smtp-test');
    const smtpResult = document.getElementById('smtp-test-result');
    const attachedFiles = [];

    function refreshCount() {
        let count = 0;
        recipientBoxes.forEach(function (box) {
            if (box.checked) {
                count += 1;
            }
        });

        if (selectedCount) {
            selectedCount.textContent = String(count);
        }

        if (toggleAll) {
            toggleAll.checked = count === recipientBoxes.length && recipientBoxes.length > 0;
            toggleAll.indeterminate = count > 0 && count < recipientBoxes.length;
        }
    }

    function formatSize(bytes) {
        if (bytes >= 1048576) {
            return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB';
        }
        if (bytes >= 1024) {
            return Math.round(bytes / 1024) + ' KB';
        }
        return bytes + ' B';
    }

    function fileKey(file) {
        return file.name + '\0' + String(file.size) + '\0' + String(file.lastModified);
    }

    function syncFileInput() {
        if (!fileInput || !window.DataTransfer) {
            return;
        }

        const transfer = new DataTransfer();
        attachedFiles.forEach(function (file) {
            transfer.items.add(file);
        });
        fileInput.files = transfer.files;
    }

    function renderFiles() {
        if (!fileList) {
            return;
        }

        fileList.innerHTML = '';
        attachedFiles.forEach(function (file, index) {
            const item = document.createElement('li');
            const label = document.createElement('span');
            label.textContent = file.name + ' · ' + formatSize(file.size);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = 'Entfernen';
            remove.setAttribute('aria-label', file.name + ' entfernen');
            remove.addEventListener('click', function () {
                attachedFiles.splice(index, 1);
                syncFileInput();
                renderFiles();
            });

            item.appendChild(label);
            item.appendChild(remove);
            fileList.appendChild(item);
        });
    }

    if (toggleAll) {
        toggleAll.addEventListener('change', function () {
            const checked = toggleAll.checked;
            recipientBoxes.forEach(function (box) {
                box.checked = checked;
            });
            refreshCount();
        });
    }

    recipientBoxes.forEach(function (box) {
        box.addEventListener('change', refreshCount);
    });
    refreshCount();

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const maxFiles = parseInt(fileInput.getAttribute('data-max-files') || '8', 10);
            const maxBytes = parseInt(fileInput.getAttribute('data-max-bytes') || '0', 10);
            const known = {};
            attachedFiles.forEach(function (file) {
                known[fileKey(file)] = true;
            });

            const incoming = Array.from(fileInput.files || []);
            let skippedLimit = false;
            let skippedSize = false;

            for (let i = 0; i < incoming.length; i++) {
                const file = incoming[i];
                if (known[fileKey(file)]) {
                    continue;
                }

                if (attachedFiles.length >= maxFiles) {
                    skippedLimit = true;
                    break;
                }

                const total = attachedFiles.reduce(function (sum, current) {
                    return sum + current.size;
                }, 0) + file.size;

                if (maxBytes > 0 && total > maxBytes) {
                    skippedSize = true;
                    continue;
                }

                known[fileKey(file)] = true;
                attachedFiles.push(file);
            }

            if (skippedLimit) {
                window.alert('Es sind maximal ' + maxFiles + ' Anhänge möglich.');
            } else if (skippedSize) {
                window.alert('Die Anhänge wären insgesamt zu groß.');
            }

            syncFileInput();
            renderFiles();
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            const count = Array.from(recipientBoxes).filter(function (box) {
                return box.checked;
            }).length;

            if (count < 1) {
                event.preventDefault();
                window.alert('Bitte mindestens einen Empfänger auswählen.');
                return;
            }

            const ok = window.confirm('Nachricht jetzt an ' + count + ' Jugendwarte als BCC senden?');
            if (!ok) {
                event.preventDefault();
            }
        });
    }

    if (smtpButton && smtpResult) {
        smtpButton.addEventListener('click', function () {
            smtpButton.disabled = true;
            smtpResult.hidden = false;
            smtpResult.className = 'notice notice-warn';
            smtpResult.textContent = 'SMTP-Verbindung wird geprüft …';

            const body = new URLSearchParams();
            body.set('csrf', smtpButton.getAttribute('data-csrf') || '');

            fetch('smtp-test.php', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                },
                body: body.toString(),
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok && data.ok === true, message: data.message || 'Unbekannte Antwort.' };
                }).catch(function () {
                    return { ok: false, message: 'Antwort des Servers konnte nicht gelesen werden.' };
                });
            }).then(function (result) {
                smtpResult.className = result.ok ? 'notice notice-ok' : 'notice notice-error';
                smtpResult.textContent = result.message;
            }).catch(function () {
                smtpResult.className = 'notice notice-error';
                smtpResult.textContent = 'SMTP-Test konnte nicht gestartet werden.';
            }).finally(function () {
                smtpButton.disabled = false;
            });
        });
    }
}());
