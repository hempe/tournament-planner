<?php

declare(strict_types=1);

use TP\Components\Page;
use TP\Components\Card;
use TP\Components\Icon;
use TP\Components\IconButton;
use TP\Components\Color;

/** @var string|null $message */
$message = $message ?? __('errors.server_error');

?>
<?= new Page(function () use ($message) {
    yield new Card(
        __('errors.500_title'),
        function () use ($message) {
            ?>
            <div style="text-align: center; padding: 2rem 0;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">
                    <?= new Icon('alert-triangle', __('errors.500_title')) ?>
                </div>
                <p style="font-size: 1.2rem; margin-bottom: 2rem;">
                    <?= htmlspecialchars($message) ?>
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <?= new IconButton(__('navigation.home'), 'link', 'home', Color::Primary, false, '/') ?>
                    <?= new IconButton(__('navigation.try_again'), 'link', 'rotate-ccw', Color::Primary, false, 'javascript:history.back()') ?>
                </div>
            </div>
            <?php
        }
    );
}) ?>
