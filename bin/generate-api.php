<?php

declare(strict_types=1);

/**
 * Writes frontend/src/app/core/api/generated.ts from the API controllers and DTOs.
 *   php bin/generate-api.php           write the file
 *   php bin/generate-api.php --check   exit 1 if the committed file is out of date
 */

require __DIR__ . '/../vendor/autoload.php';

use TP\Core\Api\TypeScriptGenerator;

$target = __DIR__ . '/../frontend/src/app/core/api/generated.ts';
$generated = TypeScriptGenerator::forApiControllers();

if (in_array('--check', $argv, true)) {
    if (!is_file($target) || file_get_contents($target) !== $generated) {
        fwrite(STDERR, "frontend/src/app/core/api/generated.ts is out of date — run: composer api:generate\n");
        exit(1);
    }
    echo "generated.ts is up to date\n";
    exit(0);
}

file_put_contents($target, $generated);
echo "Wrote frontend/src/app/core/api/generated.ts\n";
