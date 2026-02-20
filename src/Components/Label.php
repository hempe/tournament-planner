<?php

namespace TP\Components;
class Label extends Component
{
    public function __construct(
        private readonly string $for,
        private readonly string $text,
    ) {
    }

    protected function template(): void
    {
        echo <<<HTML
        <label for="{$this->for}">
            {$this->text}
        </label>
        HTML;
    }
}
