<?php

declare(strict_types=1);

/**
 * Conservative JS minifier — safe for files using template literals.
 *
 * Strategy: trim each line, drop blank lines and comment-only lines.
 * Does NOT attempt to parse tokens or collapse across lines, so template
 * literals and regex literals are left intact.
 *
 * Usage: php bin/minify-js.php file1.js file2.js ... > output.js
 */

$files = array_slice($argv, 1);

if (empty($files)) {
    fwrite(STDERR, "Usage: php minify-js.php file1.js [file2.js ...]\n");
    exit(1);
}

$combined = '';
foreach ($files as $file) {
    $combined .= file_get_contents($file) . "\n";
}

$lines = explode("\n", $combined);
$result = [];

foreach ($lines as $line) {
    $trimmed = trim($line);
    if ($trimmed === '') continue;
    if (str_starts_with($trimmed, '//')) continue;
    $result[] = $trimmed;
}

echo implode("\n", $result) . "\n";
