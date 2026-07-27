<?php
/**
 * JS Jobs - translation manifest builder (publisher-side tool, NOT shipped runtime code).
 *
 * Run this in the translations repository to produce the two files the
 * component downloads:
 *
 *     manifest.json        - index of languages + SHA-256 of each file
 *     manifest.json.sig    - base64 RSA-SHA256 signature of manifest.json
 *
 * Layout expected in the repo:
 *     manifest.json
 *     fr-FR/fr-FR.com_jsjobs.ini
 *     de-DE/de-DE.com_jsjobs.ini
 *     ...
 *
 * Usage:
 *     php build-translations-manifest.php <repo-dir> <private-key.pem> [minVersion]
 *
 * The private key must be the counterpart of the jsjobs_update_pubkey.pem that
 * ships with the component. Keep it OFF the repo and off the web server.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This tool must be run from the command line.\n");
    exit(1);
}

$repo       = isset($argv[1]) ? rtrim($argv[1], "/\\") : '';
$keyFile    = isset($argv[2]) ? $argv[2] : '';
$minVersion = isset($argv[3]) ? $argv[3] : '';

if ($repo === '' || !is_dir($repo)) {
    fwrite(STDERR, "Usage: php build-translations-manifest.php <repo-dir> <private-key.pem> [minVersion]\n");
    exit(1);
}
if ($keyFile === '' || !is_file($keyFile)) {
    fwrite(STDERR, "Private key not found: {$keyFile}\n");
    exit(1);
}

$languageNames = array(
    'fr-FR' => 'Français',   'de-DE' => 'Deutsch',    'es-ES' => 'Español',
    'it-IT' => 'Italiano',   'nl-NL' => 'Nederlands', 'pl-PL' => 'Polski',
    'pt-BR' => 'Português (Brasil)', 'ru-RU' => 'Русский', 'el-GR' => 'Ελληνικά',
    'uk-UA' => 'Українська', 'ar-AA' => 'العربية',    'tr-TR' => 'Türkçe',
);

$languages = array();
foreach (new DirectoryIterator($repo) as $entry) {
    if (!$entry->isDir() || $entry->isDot()) {
        continue;
    }
    $code = $entry->getFilename();
    if (!preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $code)) {
        continue;
    }
    $rel  = $code . '/' . $code . '.com_jsjobs.ini';
    $path = $repo . '/' . $rel;
    if (!is_file($path)) {
        fwrite(STDERR, "  skip {$code}: {$rel} missing\n");
        continue;
    }

    // Refuse to publish a file that cannot be parsed - one bad key blanks the
    // entire language for every site that downloads it.
    $parsed = @parse_ini_file($path, false, INI_SCANNER_RAW);
    if ($parsed === false || !is_array($parsed) || count($parsed) === 0) {
        fwrite(STDERR, "  FAIL {$code}: does not parse - not published\n");
        continue;
    }

    $languages[] = array(
        'code'       => $code,
        'name'       => isset($languageNames[$code]) ? $languageNames[$code] : $code,
        'file'       => $rel,
        'sha256'     => hash_file('sha256', $path),
        'keys'       => count($parsed),
        'updated'    => gmdate('Y-m-d', filemtime($path)),
        'minVersion' => $minVersion,
    );
    fwrite(STDOUT, sprintf("  ok   %-7s %5d keys\n", $code, count($parsed)));
}

if (!$languages) {
    fwrite(STDERR, "No publishable languages found.\n");
    exit(1);
}

usort($languages, function ($a, $b) { return strcmp($a['code'], $b['code']); });

$manifest = array(
    'schema'    => 1,
    'product'   => 'jsjobs',
    'generated' => gmdate('c'),
    'languages' => $languages,
);

$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents($repo . '/manifest.json', $json);

// Sign the exact bytes that will be served.
$key = openssl_pkey_get_private(file_get_contents($keyFile));
if ($key === false) {
    fwrite(STDERR, "Could not load the private key.\n");
    exit(1);
}
$signature = '';
if (!openssl_sign($json, $signature, $key, OPENSSL_ALGO_SHA256)) {
    fwrite(STDERR, "Signing failed.\n");
    exit(1);
}
file_put_contents($repo . '/manifest.json.sig', base64_encode($signature));

fwrite(STDOUT, sprintf("\nmanifest.json      %d languages, %d bytes\nmanifest.json.sig  written\n", count($languages), strlen($json)));
