(function () {
    document.querySelectorAll('.recipient-admin-item').forEach(function (item) {
        const view = item.querySelector('.recipient-admin-view');
        const editForm = item.querySelector('.recipient-admin-edit');
        const editButton = item.querySelector('[data-edit]');
        const cancelButton = item.querySelector('[data-cancel]');

        if (!view || !editForm || !editButton || !cancelButton) {
            return;
        }

        editButton.addEventListener('click', function () {
            document.querySelectorAll('.recipient-admin-edit').forEach(function (form) {
                form.hidden = true;
            });
            document.querySelectorAll('.recipient-admin-view').forEach(function (block) {
                block.hidden = false;
            });

            view.hidden = true;
            editForm.hidden = false;
            const firstField = editForm.querySelector('input[name="name"], input[name="email"]');
            if (firstField instanceof HTMLInputElement) {
                firstField.focus();
            }
        });

        cancelButton.addEventListener('click', function () {
            editForm.hidden = true;
            view.hidden = false;
        });
    });
}());
