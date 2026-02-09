<?php

namespace TP\Components;

use TP\Components\Icon;
use TP\Components\Color;

class IconButton extends \Component
{
    public readonly string $class;
    public readonly string $style;

    /**
     * @param Closure|string|Component|array<Closure|string|Component> $class
     * @param Closure|string|Component|array<Closure|string|Component> $style
     */
    public function __construct(
        public readonly string $title,
        public readonly string $type,
        public readonly string $icon,
        public readonly Color $color,
        public readonly bool $title_inline = false,
        public readonly string|null $onClick = null,
        public readonly bool $required = false,
        Closure|string|Component|array $class = '',
        Closure|string|Component|array $style = '',
    ) {
        $styles = "flex-grow:0;" . $this->title_inline ? '' : 'min-width:16px; display:block;';
        $this->class = $this->captureOutput($class);
        $this->style = $styles . " " . $this->captureOutput($style);
    }

    protected function template(): void
    {
        $required = $this->required ? 'required' : '';
        $onClick = $this->onClick ? $this->onClick : 'event.stopPropagation();';
        $titleInline = $this->title_inline ? $this->title : '';
        $icon = new Icon($this->icon, $this->title);

        echo <<<HTML
        <button
            class="pristine {$this->class}"
            type="{$this->type}"
            {$this->color->value}
            {$required}
            title="{$this->title}"
            style="{$this->style}"
            onclick="{$onClick}">
            {$icon}
            {$titleInline}
        </button>
        HTML;
    }
}
