<!DOCTYPE html>
<html>

<head>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="El Faro" />
    <link rel="manifest" href="/site.webmanifest?v=2.0" />

    <link href="/styles/normalize.css?v=3.0" rel="stylesheet" type="text/css">
    <link href="/styles/style.css?v=3.0" rel="stylesheet" type="text/css">

    <link href="/styles/calendar.css?v=2.0" rel="stylesheet" type="text/css">
    <link href="/styles/confirm.css?v=2.0" rel="stylesheet" type="text/css">
    <link href="/styles/error.css?v=2.0" rel="stylesheet" type="text/css">
    <link href="/styles/success.css?v=2.0" rel="stylesheet" type="text/css">

    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" integrity="sha512-Avb2QiuDEEvB4bZJYdft2mNjVShBftLdPG8FJ0V7irTLQ8Uo0qcPxh4Plq7G5tGm0rU+1SPhVotteLpBERwTkw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <title>Golf el Fargo</title>

    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#000000" media="(prefers-color-scheme: dark)">
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
        // Get system theme or saved preference
        function getSystemTheme() {
            return window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        }

        function setTheme(theme) {
            document.documentElement.setAttribute("data-theme", theme); // Applies to <html>
            localStorage.setItem("theme", theme); // Save user preference
        }

        function applyTheme() {
            const savedTheme = localStorage.getItem("theme");
            if (savedTheme) {
                setTheme(savedTheme); // Apply saved theme
            } else {
                setTheme(getSystemTheme()); // Apply system default theme
            }
        }

        applyTheme(); // Apply theme on page load

        // Get the current theme (dark or light)
        function getTheme() {
            return document.documentElement.getAttribute("data-theme");
        }
    </script>
    <script>
        // Detect and apply iframe mode
        function detectIframeMode() {
            const urlParams = new URLSearchParams(window.location.search);
            const isIframeParam = urlParams.get('iframe') === '1';
            const themeParam = urlParams.get('theme');
            const isInIframe = window.self !== window.top;

            // Set iframe mode if explicitly requested or if actually in iframe
            if (isIframeParam || isInIframe) {
                document.documentElement.setAttribute('data-iframe', 'true');

                // Always use compact mode in iframe (no standard/compact toggle)
                document.documentElement.setAttribute('data-compact', 'true');

                // Force light theme if specified or in iframe mode (default for iframe)
                if (themeParam === 'light' || !themeParam) {
                    document.documentElement.setAttribute('data-theme', 'light');
                    localStorage.setItem('theme', 'light');
                } else if (themeParam === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                }

                // Load iframe-specific CSS
                const iframeCSS = document.createElement('link');
                iframeCSS.rel = 'stylesheet';
                iframeCSS.type = 'text/css';
                iframeCSS.href = '/styles/iframe.css?v=1.2';
                document.head.appendChild(iframeCSS);

                // Auto-detect and set browser language if not already set
                if (!sessionStorage.getItem('locale-set')) {
                    const browserLang = navigator.language || navigator.userLanguage;
                    let desiredLocale = 'de_CH'; // Default

                    if (browserLang.startsWith('en')) {
                        desiredLocale = 'en_US';
                    } else if (browserLang.startsWith('es')) {
                        desiredLocale = 'es_ES';
                    } else if (browserLang.startsWith('de')) {
                        desiredLocale = 'de_CH';
                    }

                    // Only submit if locale needs to change
                    const currentLocale = '<?= \TP\Core\Translator::getInstance()->getLocale() ?>';
                    if (desiredLocale !== currentLocale) {
                        // Wait for DOM to be ready before submitting form
                        function submitLanguageForm() {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '/language/switch';
                            form.style.display = 'none';

                            const csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = 'csrf_token';
                            csrfInput.value = '<?= csrf_token() ?>';

                            const localeInput = document.createElement('input');
                            localeInput.type = 'hidden';
                            localeInput.name = 'locale';
                            localeInput.value = desiredLocale;

                            const redirectInput = document.createElement('input');
                            redirectInput.type = 'hidden';
                            redirectInput.name = 'redirect';
                            redirectInput.value = window.location.pathname + window.location.search;

                            form.appendChild(csrfInput);
                            form.appendChild(localeInput);
                            form.appendChild(redirectInput);

                            document.body.appendChild(form);
                            form.submit();
                        }

                        // Submit when DOM is ready
                        if (document.body) {
                            submitLanguageForm();
                        } else {
                            document.addEventListener('DOMContentLoaded', submitLanguageForm);
                        }
                    }

                    sessionStorage.setItem('locale-set', 'true');
                }

                // Store in sessionStorage for consistency
                sessionStorage.setItem('iframe-mode', 'true');
            } else if (sessionStorage.getItem('iframe-mode') === 'true') {
                // Restore from session if not explicitly disabled
                document.documentElement.setAttribute('data-iframe', 'true');
                document.documentElement.setAttribute('data-compact', 'true');

                // Load iframe CSS if restoring from session
                const iframeCSS = document.createElement('link');
                iframeCSS.rel = 'stylesheet';
                iframeCSS.type = 'text/css';
                iframeCSS.href = '/styles/iframe.css?v=1.2';
                document.head.appendChild(iframeCSS);
            }
        }

        // Apply immediately to prevent flash of unstyled content
        detectIframeMode();
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const scrollTop = sessionStorage.getItem('scrollPosition');
            const currentUrl = window.location.href;

            const savedUrl = sessionStorage.getItem('currentUrl');

            if (scrollTop && savedUrl === currentUrl) {
                window.scrollTo(0, scrollTop);
                sessionStorage.removeItem('scrollPosition');
            }

            sessionStorage.setItem('currentUrl', currentUrl);
            document.addEventListener('scroll', function() {
                sessionStorage.setItem('scrollPosition', document.documentElement.scrollTop);
            });

            document.body.style.opacity = '1'; // Set opacity after content is loaded
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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