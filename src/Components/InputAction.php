<?php

namespace TP\Components;

use TP\Components\IconButton;

class InputAction extends Component
{
    /**
     * @param string $actionUrl The URL to which the form will be submitted.
     * @param string $confirmMessage The confirmation message displayed when the form is submitted.
     * @param array<string, string> $hiddenInputs An associative array of hidden input names and values.
     */
    public function __construct(
        public readonly string $actionUrl,
        public readonly string $title,
        public readonly string $icon,
        public readonly string $inputName,
        public readonly string $inputValue,
        public readonly string $type = 'textarea',
        public readonly string $inputPlaceholder = '',
        public readonly string $confirmMessage = '',
        public readonly array $hiddenInputs = [],
        public readonly bool $title_inline = false,
        public readonly Color $color = Color::Primary,
    ) {
    }

    protected function template(): void
    {
        $warn = $this->color == Color::Accent ? 'true' : 'false';
        ?>
        <fieldset class="input-action" data-action="<?= htmlspecialchars($this->actionUrl, ENT_QUOTES, 'UTF-8') ?>"
            data-confirm="<?= htmlspecialchars($this->confirmMessage, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($this->hiddenInputs as $name => $value): ?>
                <input type="hidden" name="<?= htmlspecialchars($name) ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>

            <?php if ($this->type == 'textarea'): ?>
                <textarea style="display: flex; flex-grow: 1;" name="<?= $this->inputName ?>" class="input"
                    placeholder="<?= $this->inputPlaceholder ?>"><?= $this->inputValue ?></textarea>
            <?php else: ?>
                <input style="display: flex; flex-grow: 1;" type="<?= $this->type ?>" value="<?= $this->inputValue ?>"
                    name="<?= $this->inputName ?>" class="input" placeholder="<?= $this->inputPlaceholder ?>" />
            <?php endif; ?>

            <?= new IconButton(
                type: 'button',
                color: $this->color,
                title: $this->title,
                icon: $this->icon,
                onClick: "fieldsetSubmit(this, event, { warn: $warn })",
                title_inline: $this->title_inline,
                required: true,
            ) ?>
        </fieldset>
    <?php }
}
