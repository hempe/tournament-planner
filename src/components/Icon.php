<?php

namespace TP\Components;
class Icon extends \Component
{
    public readonly string $class;
    public readonly string $style;

    public function __construct(
        private readonly string $icon,
        private readonly string $title,
        Closure|string|Component|array $class = '',
        Closure|string|Component|array $style = '',
    ) {
        $this->class = $this->captureOutput($class);
        $this->style = $this->captureOutput($style);
    }

    protected function template(): void
    {
        echo <<<HTML
            <i 
                style="{$this->style}"
                class="fas {$this->icon} {$this->class}" 
                title="{$this->title}>">
            </i>
        HTML;
    }
}
