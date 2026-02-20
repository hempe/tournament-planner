<?php

namespace TP\Components;

class Input extends Component
{
    public readonly string $class;
    public readonly string $style;
    public readonly string $content;

    /**
     * @param Closure|string|Component|array<Closure|string|Component> $class
     * @param Closure|string|Component|array<Closure|string|Component> $style
     */
    public function __construct(
        private readonly string $type = 'text',
        private readonly string $value = '',
        private readonly string $name = '',
        private readonly string $placeholder = '',
        private readonly bool $required = false,
        \Closure|string|Component|array $class = 'input',
        \Closure|string|Component|array $style = '',
        private readonly string $step = '',
        private readonly string $id = '',
        \Closure|string|Component|array $content = '',
    ) {
        $this->class = $this->captureOutput($class);
        $this->style = $this->captureOutput($style);
        $this->content = $this->captureOutput($content);
    }

    protected function template(): void
    {
        $required = $this->required ? 'required' : '';
        $step = $this->step !== '' ? "step=\"{$this->step}\"" : '';
        $id = $this->id !== '' ? "id=\"{$this->id}\"" : '';
        $value = htmlspecialchars($this->value);
        echo <<<HTML
            <input type="{$this->type}"
                value="{$value}"
                name="{$this->name}"
                class="{$this->class}"
                placeholder="{$this->placeholder}"
                style="{$this->style}"
                {$id}
                {$step}
                {$required}>{$this->content}
        HTML;
    }
}
