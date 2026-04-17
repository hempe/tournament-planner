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

    <?php if (\TP\Core\Config::getInstance()->isProduction()): ?>
    <?php
        $cssMatches = glob(__DIR__ . '/../../styles/styles.*.css');
        $cssHref = $cssMatches ? '/styles/' . basename($cssMatches[0]) : '/styles/style.css';
    ?>
    <link href="<?= $cssHref ?>" rel="stylesheet" type="text/css">
    <?php else: ?>
    <link href="/styles/style.css?v=4.4" rel="stylesheet" type="text/css">
    <link href="/styles/calendar.css?v=3.0" rel="stylesheet" type="text/css">
    <link href="/styles/confirm.css?v=2.0" rel="stylesheet" type="text/css">
    <link href="/styles/error.css?v=2.0" rel="stylesheet" type="text/css">
    <link href="/styles/success.css?v=2.0" rel="stylesheet" type="text/css">
    <?php endif; ?>

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
            },
            social: {
                prompt_yes: <?= json_encode(__('events.social_prompt_yes')) ?>,
                prompt_no: <?= json_encode(__('events.social_prompt_no')) ?>,
                unregister_title: <?= json_encode(__('events.unregister_choice_title')) ?>,
                unregister_message: <?= json_encode(__('events.unregister_choice_message')) ?>,
                tournament_only: <?= json_encode(__('events.unregister_tournament_only')) ?>,
                both: <?= json_encode(__('events.unregister_both')) ?>,
            },
        };
    </script>
    <?php if (\TP\Core\Config::getInstance()->isProduction()): ?>
    <?php
        $jsMatches = glob(__DIR__ . '/../../src/scripts/scripts.*.js');
        $jsHref = $jsMatches ? '/src/scripts/' . basename($jsMatches[0]) : '/src/scripts/confirm.js';
    ?>
    <script src="<?= $jsHref ?>"></script>
    <?php else: ?>
    <script src="/src/scripts/social-prompt.js"></script>
    <script src="/src/scripts/confirm.js"></script>
    <script src="/src/scripts/error.js"></script>
    <script src="/src/scripts/success.js"></script>
    <script src="/src/scripts/fieldset.js"></script>
    <script src="/src/scripts/scroll.js"></script>
    <script src="/src/scripts/form-state.js"></script>
    <?php endif; ?>
    <?= isset($scripts) ? $scripts : '' ?>
</head>

<body style="opacity:0">