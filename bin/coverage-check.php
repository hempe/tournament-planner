<?php

declare(strict_types=1);

/**
 * Fails when line coverage in the given Clover report is below the threshold.
 *   php bin/coverage-check.php /tmp/clover.xml 85
 */

$file = $argv[1] ?? '/tmp/clover.xml';
$threshold = (float) ($argv[2] ?? 85);

$xml = @simplexml_load_file($file);
if ($xml === false) {
    fwrite(STDERR, "Cannot read Clover report: {$file}\n");
    exit(1);
}

$metrics = $xml->project->metrics;
$statements = (float) $metrics['statements'];
$covered = (float) $metrics['coveredstatements'];
$percent = $statements > 0 ? $covered / $statements * 100 : 0.0;

printf("Line coverage: %.1f%% (threshold %.0f%%)\n", $percent, $threshold);
if ($percent < $threshold) {
    echo "FAIL: coverage below threshold\n";
    exit(1);
}
echo "OK\n";
