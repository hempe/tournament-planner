<?php

namespace TP\Components;

use TP\Components\Color;
use TP\Components\Icon;
use TP\Components\IconActionButton;
use TP\Components\IconButton;
use TP\Models\User;
use TP\Core\Translator;
use TP\Core\Url;

class Page extends Component
{
    /** @var string The rendered content of the page */
    public readonly string $content;


    public function __construct(
        callable|string|Component $content,
    ) {
        $this->content = $this->captureOutput($content);
    }

    protected function template(): void
    {
        $url = Url::build(isset($_GET['b']) ? '/?date=' . $_GET['b'] : '/');
        $formAction = Url::build('/language/switch');
        $title = isset($GLOBALS['title']) ? $GLOBALS['title'] : 'Golf el faro';
        $isIndex = basename($_SERVER['PHP_SELF']) == 'index.php';

        $backButton = $isIndex
            ? "<a href=\"{$url}\" style=\"padding:0;flex-grow:0;\"><img src=\"favicon-96x96.png\" style=\"height: 50px; width: 50px;\"></a>"
            : "<a class=\"button nav-button\" href=\"{$url}\">" . new Icon('fa-chevron-left', __('nav.back'), style: 'color: var(--fg-navtop);') . "</a>";

        $adminButtons = '';
        if (User::admin()) {
            $adminButtons =
                "<a class=\"button nav-button\" href=\"/events\">" . new Icon('fa-calendar', __('nav.events'), style: 'color: var(--fg-navtop);') . "</a>" .
                "<a class=\"button nav-button\" href=\"/users\">" . new Icon('fa-users', __('nav.users'), style: 'color: var(--fg-navtop);') . "</a>";
        }

        $logoutButton = User::loggedIn()
            ? new IconActionButton(
                actionUrl: "/logout",
                title: __('nav.logout'),
                color: Color::None,
                icon: 'fa-sign-out',
                confirmMessage: '',
                class: 'nav-button',
                style: 'color: var(--fg-navtop);'
            )
            : '';

        $loginButton = !User::loggedIn()
            ? new IconActionButton(
                actionUrl: Url::build('/login'),
                title: __('auth.login'),
                icon: 'fa-sign-in',
                color: Color::None,
                class: 'nav-button',
                style: 'color: var(--fg-navtop);',
                title_inline: true
            )
            : '';

        $moonIcon = new Icon('fa-moon', __('theme.dark'));
        $sunIcon = new Icon('fa-sun', __('theme.light'));

        // Language selector
        $currentLocale = Translator::getInstance()->getLocale();
        $languages = [
            'de' => __('languages.de'),
            'en' => __('languages.en'),
            'es' => __('languages.es'),
        ];

        $languageOptions = '';
        foreach ($languages as $locale => $name) {
            $selected = $locale === $currentLocale ? 'selected' : '';
            $languageOptions .= "<option style='color:black;' value=\"{$locale}\" {$selected}>{$name}</option>";
        }

        echo <<<HTML
        <div class="body">
            <nav class="navtop">
                <div>
                    {$backButton}
                    <h1>{$title}</h1>
                    {$adminButtons}
                    <div class="language-selector" style="position: relative; display: inline-block;">
                        <select
                            id="language-select"
                            onchange="switchLanguage(this.value)"
                            class="nav-button"
                            style="color: var(--fg-navtop); background: transparent; border: none; cursor: pointer; padding: 8px 12px; font-size: 14px; appearance: none; padding-right: 24px;"
                            title="<?= __('nav.language') ?>"
                        >
                            {$languageOptions}
                        </select>
                        <span style="position: absolute; right: 0px; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--fg-navtop);">
                            <i class="fa fa-globe"></i>
                        </span>
                    </div>
                    <a class="button nav-button theme-toggle" style="display: var(--theme-toggle-dark); color: var(--fg-navtop);" onclick="setTheme('dark')">
                        {$moonIcon}
                    </a>
                    <a class="button nav-button theme-toggle" style="display: var(--theme-toggle-light); color: var(--fg-navtop);" onclick="setTheme('light')">
                        {$sunIcon}
                    </a>
                    {$logoutButton}
                    {$loginButton}
                </div>
            </nav>
            {$this->content}
        </div>
        <script>
        function switchLanguage(locale) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{$formAction};

            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = '<?= csrf_token() ?>';

            const localeInput = document.createElement('input');
            localeInput.type = 'hidden';
            localeInput.name = 'locale';
            localeInput.value = locale;

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
        </script>
        HTML;
    }
}
