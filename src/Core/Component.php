<?php
namespace TP\Core;

abstract class Component
{
    // Virtual method with a default implementation
    protected abstract function template(): void;

    public function __toString(): string
    {
        ob_start();
        $this->template();
        return ob_get_clean();
    }

    /**
     * Captures the output of a callable, string, component, or an array of these types, and returns it as a string.
     *
     * @param Closure|string|Component|array<Closure|string|Component> $content The content to process.
     * @return string The captured output.
     */
    protected function captureOutput(\Closure|string|Component|array $content): string
    {
        if (is_array($content)) {
            // Process each element in the array recursively
            $output = '';
            foreach ($content as $item) {
                $output .= " " . $this->captureOutput($item);
            }
            return $output;
        }

        // Handle single content items
        return match (true) {
            is_callable($content) => $this->captureOutputFromCallback($content),
            $content instanceof Component => (string) $content,
            default => $content // Assume it's a string
        };
    }

    /**
     * Captures the output of a callable and returns it as a string.
     * The callable should echo or return the content.
     *
     * @param callable():string $callback The function that generates content
     * @return string The captured output
     */
    private function captureOutputFromCallback(\Closure $callback): string
    {
        ob_start();
        $result = $callback();

        // Check if the result is a Generator
        if ($result instanceof Generator) {
            $output = '';
            foreach ($result as $chunk) {
                $output .= " " . $chunk;
            }
            ob_end_clean(); // Clear the buffer since we are handling the output manually
            return $output;
        }

        // If it's a string, return it along with any echoed content
        return ob_get_clean() . $result;
    }
}
