<?php

namespace TP\Components;

class Card extends Component
{
    private readonly string $content;
    private readonly string $title;
    private readonly string $class;
    private readonly string $style;

    /**
     * @param Closure|string|Component|array<Closure|string|Component> $title The title of the card
     * @param Closure|string|Component|array<Closure|string|Component> $content The card's content
     * @param Closure|string|Component|array<Closure|string|Component> $class
     * @param Closure|string|Component|array<Closure|string|Component> $style
     */
    public function __construct(
        \Closure|string|Component|array $title,
        \Closure|string|Component|array $content,
        \Closure|string|Component|array $class = '',
        \Closure|string|Component|array $style = '',
    ) {
        $this->title = $this->captureOutput($title);
        $this->content = $this->captureOutput($content);
        $this->class = $this->captureOutput($class);
        $this->style = $this->captureOutput($style);
    }

    protected function template(): void
    {
        $class = $this->class ? "card {$this->class}" : 'card';
        $style = $this->style ? "style=\"{$this->style}\"" : '';
        echo <<<HTML
        <div class="{$class}" {$style}>
            <div class="card-title">{$this->title}</div>
            <div class="card-content" style="display: flex; flex-direction: column;">{$this->content}</div>
        </div>
        HTML;
    }
}
