<?php

namespace TP\Components;

use TP\Components\Color;
use TP\Components\Icon;
use TP\Components\IconActionButton;
use TP\Components\IconButton;
use TP\Components\Select;
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
            ? new IconButton(
                href: '/login',
                title: __('auth.login'),
                type: 'button',
                icon: 'fa-sign-in',
                color: Color::None,
                class: 'nav-button',
                style: 'color: var(--fg-navtop);',
                title_inline: true
            )
            : '';


        echo <<<HTML
        <div class="body">
            
            <div class="site-title">
                <!-- Logo -->
                <div>
                    <a href="https://www.golfelfaro.es"><img src="/favicon.svg" alt="EL FARO GOLF"></a>
                </div><!-- End logo -->

                <!-- Text -->
                <div class="site-name">CLUB DEPORTIVO <nobr>DE GOLF</nobr><br>
                    <nobr>EL FARO</nobr>
                    <nobr>DE MASPALOMAS</nobr>
                </div>
            </div>
            <nav class="navtop">
                {$adminButtons}
                {$logoutButton}
                {$loginButton}
            </nav>

           
            {$this->content}
        </div>
        HTML;
    }
}
