<?php

namespace TP\Components;

class Input extends Component
{
    public readonly string $class;
    public readonly string $style;

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
        \Closure|string|Component|array $class = '',
        \Closure|string|Component|array $style = '',
    ) {
        $this->class = $this->captureOutput($class);
        $this->style = $this->captureOutput($style);
    }

    protected function template(): void
    {
        $required = $this->required ? 'required' : '';
        echo <<<HTML
            <input type="{$this->type}" 
                value="{$this->value}" 
                name="{$this->name}" 
                placeholder="{$this->placeholder}" 
                style="{$this->style}" 
                {$required}>
        HTML;
    }
}
