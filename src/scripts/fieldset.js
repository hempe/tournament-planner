async function fieldsetSubmit(button, event, options) {
    event?.stopPropagation();
    var fieldset = button.closest('fieldset');
    var actionUrl = fieldset.getAttribute('data-action');
    var confirmMessage = fieldset.getAttribute('data-confirm');
    if (confirmMessage && !await customConfirm(confirmMessage, options)) return;
    var form = document.createElement('form');
    form.style.display = 'none';
    form.method = 'post';
    form.action = actionUrl;
    fieldset.querySelectorAll('input, textarea').forEach(function (input) {
        if (input.name) form.appendChild(input.cloneNode());
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
}

async function fieldsetSubmitChoice(button, event, options) {
    event?.stopPropagation();
    var fieldset = button.closest('fieldset');
    var actionUrl = fieldset.getAttribute('data-action');
    var socialUrl = fieldset.getAttribute('data-social-action');
    var userId = fieldset.querySelector('input[name="userId"]')?.value;

    var choice = await customUnregisterChoice();
    if (!choice) return;

    var post = (url, body) => fetch(url, { method: 'POST', body });

    var formData = new FormData();
    if (userId) formData.append('userId', userId);
    await post(actionUrl, formData);

    if (choice === 'both') {
        await post(socialUrl, new FormData());
    }

    window.location.reload();
}