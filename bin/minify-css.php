<?php

declare(strict_types=1);

/**
 * Minimal CSS minifier.
 * Usage: php bin/minify-css.php file1.css file2.css ... > output.css
 */

$files = array_slice($argv, 1);

if (empty($files)) {
    fwrite(STDERR, "Usage: php minify-css.php file1.css [file2.css ...]\n");
    exit(1);
}

$combined = '';
foreach ($files as $file) {
    $combined .= file_get_contents($file) . "\n";
}

// Remove /* ... */ comments
$css = preg_replace('!/\*.*?\*/!s', '', $combined);

// Collapse all whitespace (newlines, tabs, multiple spaces) to a single space
$css = preg_replace('/\s+/', ' ', $css);

// Remove spaces around safe structural tokens only.
// Deliberately excludes + and - to avoid breaking calc() expressions.
$css = preg_replace('/\s*([{};:,])\s*/', '$1', $css);

// Drop the redundant trailing semicolon before a closing brace
$css = str_replace(';}', '}', $css);

echo trim($css) . "\n";
