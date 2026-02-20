<?php

namespace TP\Components;

class Checkbox extends Component
{
    public function __construct(
        private readonly string $name = '',
        private readonly string $label = '',
        private readonly string $id = '',
        private readonly bool $checked = false,
    ) {

    }
    protected function template(): void
    {
        $id = $this->id !== '' ? "id=\"{$this->id}\"" : '';
        $checked = $this->checked ? 'checked' : '';

        echo <<<HTML
            <input 
                type="hidden" 
                name="{$this->name}" 
                value="0" 
                {$id}>
            <label 
                style="display:flex;align-items:center;gap:.4rem;white-space:nowrap;margin-top:5px;">
                <input 
                    type="checkbox" 
                    name="{$this->name}"
                    value="1" 
                    {$checked}>{$this->label}
            </label>
        HTML;
    }
}
