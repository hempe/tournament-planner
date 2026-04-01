<!DOCTYPE html>
<html>

<head>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="El Faro" />
    <link rel="manifest" href="/site.webmanifest?v=2.0" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Guntur:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <link href="/styles/style.css?v=3.0" rel="stylesheet" type="text/css">

    <link href="/styles/calendar.css?v=2.0" rel="stylesheet" type="text/css">
    <link href="/styles/confirm.css?v=2.0" rel="stylesheet" type="text/css">
    <link href="/styles/error.css?v=2.0" rel="stylesheet" type="text/css">
    <link href="/styles/success.css?v=2.0" rel="stylesheet" type="text/css">

    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />


    <title>GOLF EL FARO</title>

    <script>
        // Global translations for JavaScript
        window.translations = {
            confirm: {
                title: <?= json_encode(__('calendar.confirmation')) ?>,
                cancel: <?= json_encode(__('actions.cancel')) ?>,
                confirm: <?= json_encode(__('actions.confirm')) ?>
            },
            error: {
                title: <?= json_encode(__('errors.title')) ?>
            },
            success: {
                title: <?= json_encode(__('success.title')) ?>
            }
        };
    </script>
    <script src="/src/scripts/confirm.js"></script>
    <script src="/src/scripts/error.js"></script>
    <script src="/src/scripts/success.js"></script>
    <script src="/src/scripts/fieldset.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const scrollTop = sessionStorage.getItem('scrollPosition');
            const currentUrl = window.location.href;

            const savedUrl = sessionStorage.getItem('currentUrl');

            if (scrollTop && savedUrl === currentUrl) {
                window.scrollTo(0, scrollTop);
                sessionStorage.removeItem('scrollPosition');
            }

            sessionStorage.setItem('currentUrl', currentUrl);
            document.addEventListener('scroll', function () {
                sessionStorage.setItem('scrollPosition', document.documentElement.scrollTop);
            });

            setTimeout(() => {
                document.body.style.opacity = '1'; // Set opacity after content is loaded
            }, 100);
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Select all forms on the page
            const forms = document.querySelectorAll('form, fieldset');

            // Function to set the button state (color) based on form state
            function setSubmitButtonState(form) {
                const submitButton = form.querySelector('button[type="submit"], button[type="button"]');
                const inputs = Array.from(form.querySelectorAll('input, textarea, select'))
                    .filter(el => el.type !== 'hidden');

                if (!inputs.length)
                    return;

                // Update button class based on whether the form is dirty or pristine
                if (!!inputs.filter(i => i.value !== i.defaultValue).length) {
                    submitButton.classList.remove('pristine');
                    submitButton.classList.add('dirty');
                } else {
                    submitButton.classList.remove('dirty');
                    submitButton.classList.add('pristine');
                }
            }

            // Add event listeners to each form for input changes
            forms.forEach(form => {
                form.addEventListener('input', () => setSubmitButtonState(form));

                // Initial check for pristine state when the page loads
                setSubmitButtonState(form);
            });
        });
    </script>
    <?= isset($scripts) ? $scripts : '' ?>
</head>

<body style="opacity:0">