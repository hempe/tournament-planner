<?php

declare(strict_types=1);

/**
 * Structure check for the build output.
 *
 *   php bin/verify-dist.php dist
 */

$dist = rtrim($argv[1] ?? 'dist', '/');
$first = "$dist/1-upload-first";
$last = "$dist/2-upload-last";
$errors = [];

foreach (['index.html', 'ngsw.json', 'version.txt'] as $f) {
    if (!is_file("$last/$f")) {
        $errors[] = "missing $last/$f";
    }
}
foreach (['index.php', 'bootstrap.php', '.htaccess', 'ngsw-worker.js', 'config/production.php', 'vendor/autoload.php', 'storage/cache/routes.php'] as $f) {
    if (!is_file("$first/$f")) {
        $errors[] = "missing $first/$f";
    }
}
foreach (['storage/cache', 'logs', 'resources/i18n', 'src'] as $d) {
    if (!is_dir("$first/$d")) {
        $errors[] = "missing directory $first/$d";
    }
}

// Files that must only be in the last step
foreach (['index.html', 'ngsw.json', 'version.txt'] as $f) {
    if (file_exists("$first/$f")) {
        $errors[] = "$first/$f must only be in 2-upload-last";
    }
}

// Version folder
$version = is_file("$last/version.txt") ? trim((string) file_get_contents("$last/version.txt")) : '';
if ($version === '') {
    $errors[] = 'version.txt is empty';
} else {
    if (glob("$first/app/$version/main-*.js") === [] || glob("$first/app/$version/main-*.js") === false) {
        $errors[] = "missing $first/app/$version/main-*.js";
    }
    if (is_file("$last/index.html") && !str_contains((string) file_get_contents("$last/index.html"), "/app/$version/")) {
        $errors[] = "index.html does not reference /app/$version/";
    }
}

// Things that must not ship
foreach (['src/Views', 'src/Components', 'src/Layout', 'src/scripts', 'styles', 'tests', 'frontend', 'docs', 'database', '.env', 'composer.json', 'composer.lock', 'bin'] as $p) {
    if (file_exists("$first/$p") || file_exists("$last/$p")) {
        $errors[] = "dev/legacy path must not be in dist: $p";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "verify-dist FAILED:\n  - " . implode("\n  - ", $errors) . "\n");
    exit(1);
}
echo "verify-dist OK (version $version)\n";
