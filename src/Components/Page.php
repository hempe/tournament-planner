<?php

namespace TP\Components;

use TP\Components\Icon;
use TP\Components\IconActionButton;
use TP\Models\User;
use TP\Components\Color;

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
        $url = isset($_GET['b']) ? '/?date=' . $_GET['b'] : '/';
        $title = isset($GLOBALS['title']) ? $GLOBALS['title'] : 'Golf el faro';
        $isIndex = basename($_SERVER['PHP_SELF']) == 'index.php';

        $backButton = $isIndex
            ? "<a href=\"{$url}\" style=\"padding:0;flex-grow:0;\"><img src=\"favicon-96x96.png\" style=\"height: 50px; width: 50px;\"></a>"
            : "<a class=\"button nav-button\" href=\"{$url}\">" . new Icon('fa-chevron-left', 'Zurück', style: 'color: var(--fg-navtop);') . "</a>";

        $adminButtons = '';
        if (User::admin()) {
            $adminButtons =
                "<a class=\"button nav-button\" href=\"/events\">" . new Icon('fa-calendar', 'Anlässe', style: 'color: var(--fg-navtop);') . "</a>" .
                "<a class=\"button nav-button\" href=\"/users\">" . new Icon('fa-users', 'Benutzer', style: 'color: var(--fg-navtop);') . "</a>";
        }

        $logoutButton = User::loggedIn()
            ? new IconActionButton(
                "/logout",
                'Logout',
                Color::None,
                'fa-sign-out',
                'nav-button',
                class: 'color: var(--fg-navtop);'
            )
            : '';

        $moonIcon = new Icon('fa-moon', 'Dark theme');
        $sunIcon = new Icon('fa-sun', 'Light theme');

        echo <<<HTML
        <div class="body">
            <nav class="navtop">
                <div>
                    {$backButton}
                    <h1>{$title}</h1>
                    {$adminButtons}
                    <a class="button nav-button" style="display: var(--theme-toggle-dark); color: var(--fg-navtop);" onclick="setTheme('dark')">
                        {$moonIcon}
                    </a>
                    <a class="button nav-button" style="display: var(--theme-toggle-light); color: var(--fg-navtop);" onclick="setTheme('light')">
                        {$sunIcon}
                    </a>
                    {$logoutButton}
                </div>
            </nav>
            {$this->content}
        </div>
        HTML;
    }
}
