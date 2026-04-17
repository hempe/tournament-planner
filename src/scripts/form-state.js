document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form, fieldset');

    function setSubmitButtonState(form) {
        const submitButton = form.querySelector('button[type="submit"], button[type="button"]');
        const inputs = Array.from(form.querySelectorAll('input, textarea, select'))
            .filter(el => el.type !== 'hidden');

        if (!inputs.length) return;

        if (inputs.filter(i => i.value !== i.defaultValue).length) {
            submitButton.classList.remove('pristine');
            submitButton.classList.add('dirty');
        } else {
            submitButton.classList.remove('dirty');
            submitButton.classList.add('pristine');
        }
    }

    forms.forEach(form => {
        form.addEventListener('input', () => setSubmitButtonState(form));
        setSubmitButtonState(form);
    });
});
