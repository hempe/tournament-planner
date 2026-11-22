<?php

declare(strict_types=1);

/**
 * Structure check for the build output.
 *
 *   php bin/verify-dist.php dist/<flavor> <flavor>
 */

$dist = rtrim($argv[1] ?? '', '/');
$flavor = $argv[2] ?? '';
$root = dirname(__DIR__);
if ($dist === '' || $flavor === '' || !is_file("$root/frontend/flavors/$flavor/flavor.json")) {
    fwrite(STDERR, "usage: php bin/verify-dist.php dist/<flavor> <flavor>\n");
    exit(2);
}
$first = "$dist/1-upload-first";
$last = "$dist/2-upload-last";
$errors = [];

foreach (['index.html', 'ngsw.json', 'version.txt'] as $f) {
    if (!is_file("$last/$f")) {
        $errors[] = "missing $last/$f";
    }
}
foreach (['index.php', 'bootstrap.php', '.htaccess', 'ngsw-worker.js', 'config/production.php', 'config/flavor.json', 'vendor/autoload.php', 'storage/cache/routes.php'] as $f) {
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

// Flavour: right config, and nothing of the other clubs (assets, name in index/manifest)
$shippedFlavor = is_file("$first/config/flavor.json")
    ? (json_decode((string) file_get_contents("$first/config/flavor.json"), true)['id'] ?? null)
    : null;
if ($shippedFlavor !== $flavor) {
    $errors[] = "config/flavor.json is for '" . ($shippedFlavor ?? 'none') . "', expected '$flavor'";
}
$own = [];
foreach (glob("$root/frontend/flavors/$flavor/assets/*") ?: [] as $f) {
    $own[md5_file($f)] = true;
}
$shipped = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dist, FilesystemIterator::SKIP_DOTS)) as $file) {
    $shipped[md5_file($file->getPathname())] = $file->getPathname();
}
foreach (glob("$root/frontend/flavors/*", GLOB_ONLYDIR) ?: [] as $dir) {
    $other = basename($dir);
    if ($other === $flavor) {
        continue;
    }
    foreach (glob("$dir/assets/*") ?: [] as $asset) {
        $hash = md5_file($asset);
        if (isset($shipped[$hash]) && !isset($own[$hash])) {
            $errors[] = "asset of flavour $other in dist: " . $shipped[$hash];
        }
    }
    $otherName = json_decode((string) file_get_contents("$dir/flavor.json"), true)['name'];
    foreach (["$last/index.html", "$first/site.webmanifest"] as $f) {
        if (is_file($f) && str_contains((string) file_get_contents($f), $otherName)) {
            $errors[] = "$f mentions flavour $other ($otherName)";
        }
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
echo "verify-dist OK ($flavor, version $version)\n";
