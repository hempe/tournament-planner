<?php

namespace TP\Components;

class Select extends Component
{
    public function __construct(
        private readonly array $options,
        private readonly string $name = '',
        private readonly string $selected = '',
        private readonly bool $required = false,
        private readonly string $class = 'input',
        private readonly string $id = '',
        private readonly string $onchange = '',
        private readonly string $style = '',
        private readonly string $title = '',
    ) {}

    protected function template(): void
    {
        $required = $this->required ? 'required' : '';
        $id = $this->id !== '' ? "id=\"{$this->id}\"" : '';
        $onchange = $this->onchange !== '' ? "onchange=\"{$this->onchange}\"" : '';
        $style = $this->style !== '' ? "style=\"{$this->style}\"" : '';
        $title = $this->title !== '' ? "title=\"{$this->title}\"" : '';

        $optionsHtml = '';
        foreach ($this->options as $value => $label) {
            $sel = (string) $value === $this->selected ? 'selected' : '';
            $optionsHtml .= "<option value=\"{$value}\" {$sel}>{$label}</option>";
        }

        echo "<select name=\"{$this->name}\" class=\"{$this->class}\" {$id} {$onchange} {$style} {$title} {$required}>{$optionsHtml}</select>";
    }
}
