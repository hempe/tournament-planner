<?php

declare(strict_types=1);

namespace TP\Components;

use Closure;
use Generator;

abstract class Component
{
    protected abstract function template(): void;

    public function __toString(): string
    {
        ob_start();
        $this->template();
        return ob_get_clean() ?: '';
    }

    protected function captureOutput(Closure|string|Component|array $content): string
    {
        if (is_array($content)) {
            $output = '';
            foreach ($content as $item) {
                $output .= ' ' . $this->captureOutput($item);
            }
            return $output;
        }

        return match (true) {
            is_callable($content) => $this->captureOutputFromCallback($content),
            $content instanceof Component => (string) $content,
            default => (string) $content
        };
    }

    private function captureOutputFromCallback(Closure $callback): string
    {
        ob_start();
        $result = $callback();

        if ($result instanceof Generator) {
            $output = '';
            foreach ($result as $chunk) {
                $output .= ' ' . $chunk;
            }
            ob_end_clean();
            return $output;
        }

        return ob_get_clean() . (string) $result;
    }
}