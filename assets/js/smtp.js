(function () {
    const smtpButton = document.getElementById('smtp-test');
    const smtpResult = document.getElementById('smtp-test-result');

    if (!smtpButton || !smtpResult) {
        return;
    }

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
}());
