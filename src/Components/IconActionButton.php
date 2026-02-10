<?php

namespace TP\Components;

use TP\Components\IconButton;

class IconActionButton extends Component
{
    /**
     * @param string $actionUrl The URL to which the form will be submitted.
     * @param string $confirmMessage The confirmation message displayed when the form is submitted.
     * @param array<string, string> $hiddenInputs An associative array of hidden input names and values.
     * @param Closure|string|Component|array<Closure|string|Component> $class
     */
    public function __construct(
        private readonly string $actionUrl,
        private readonly string $title,
        private readonly Color $color,
        private readonly string $icon,
        private readonly string $confirmMessage = '',
        private readonly array $hiddenInputs = [],
        private readonly bool $title_inline = false,
        private readonly \Closure|string|Component|array $class = '',
        private readonly \Closure|string|Component|array $style = '',
    ) {
    }

    protected function template(): void
    {
        $warn = $this->color == Color::Accent ? 'true' : 'false';
        ?>
        <fieldset data-action="<?= htmlspecialchars($this->actionUrl, ENT_QUOTES, 'UTF-8') ?>"
            data-confirm="<?= htmlspecialchars($this->confirmMessage, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($this->hiddenInputs as $name => $value): ?>
                <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>

            <?= new IconButton(
                type: 'button',
                color: $this->color,
                title: $this->title,
                icon: $this->icon,
                onClick: "fieldsetSubmit(this, event, { warn: {$warn} } )",
                title_inline: $this->title_inline,
                class: $this->class,
                style: $this->style,
            ) ?>
        </fieldset>
    <?php }
}
