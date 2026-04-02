<?php

namespace TP\Components;

class Textarea extends Component
{
    public function __construct(
        private readonly string $name = '',
        private readonly string $value = '',
        private readonly string $placeholder = '',
        private readonly bool $required = false,
        private readonly string $class = 'input',
        private readonly int $rows = 4,
        private readonly string $style = '',
    ) {}

    protected function template(): void
    {
        $required = $this->required ? 'required' : '';
        $style = $this->style !== '' ? "style=\"{$this->style}\"" : '';
        $value = htmlspecialchars($this->value);
        echo "<textarea name=\"{$this->name}\" class=\"{$this->class}\" placeholder=\"{$this->placeholder}\" rows=\"{$this->rows}\" {$style} {$required}>{$value}</textarea>";
    }
}
