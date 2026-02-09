<?php

namespace TP\Components;
class Div extends \Component
{
    private readonly string $content;
    private readonly string $class;
    private readonly string $style;

    /**
     * @param Closure|string|Component|array<Closure|string|Component> $content
     * @param Closure|string|Component|array<Closure|string|Component> $class
     * @param Closure|string|Component|array<Closure|string|Component> $style
     */
    public function __construct(
        Closure|string|Component|array $content,
        Closure|string|Component|array $class = '',
        Closure|string|Component|array $style = '',
    ) {
        $this->content = $this->captureOutput($content);
        $this->class = $this->captureOutput($class);
        $this->style = $this->captureOutput($style);
    }

    protected function template(): void
    {
        echo <<<HTML
        <div class="{$this->class}" style="{$this->style}">
            {$this->content}
        </div>
        HTML;
    }
}
