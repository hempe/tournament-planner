<?php

namespace TP\Components;
use TP\Core\Url;

class Form extends Component
{
    public readonly string $content;
    private readonly string $action;
    public readonly string $class;
    public readonly string $style;

    /**
     * @param Closure|string|Component|array<Closure|string|Component> $content The card's content
     */
    public function __construct(
        string $action,
        \Closure|string|Component|array $content,
        private readonly string $method = 'post',
        public readonly array $hiddenInputs = [],
        \Closure|string|Component|array $class = '',
        \Closure|string|Component|array $style = '',
    ) {
        $this->action = Url::build($action);
        $this->content = $this->captureOutput($content);
        $this->class = $this->captureOutput($class);
        $this->style = $this->captureOutput($style);
    }

    protected function template(): void
    {
        $hiddenFields = '';

        // Automatically add CSRF token for POST requests
        if (strtolower($this->method) === 'post') {
            $hiddenFields .= sprintf(
                '<input type="hidden" name="_token" value="%s">',
                htmlspecialchars(csrf_token())
            );
        }

        foreach ($this->hiddenInputs as $name => $value) {
            $hiddenFields .= sprintf(
                '<input type="hidden" name="%s" value="%s">',
                htmlspecialchars($name),
                htmlspecialchars($value)
            );
        }

        echo <<<HTML
        <form 
            action="{$this->action}" 
            method="{$this->method}"
            class="{$this->class}"
            style="{$this->style}">
            {$this->content}
            {$hiddenFields}
        </form>
        HTML;
    }
}
