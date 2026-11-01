#!/usr/bin/env php
<?php

declare(strict_types=1);
require __DIR__ . '/vendor/autoload.php';

use Composer\Semver\VersionParser;
use Composer\Spdx\SpdxLicenses;

$root = dirname(__DIR__, 2) . '/src/modules';
$schemaPath = __DIR__ . '/manifest.schema.json';
$failures = [];
$parser = new VersionParser();
$spdx = new SpdxLicenses();

$packages = array_values(array_filter(glob($root . '/*', GLOB_ONLYDIR) ?: [], static fn (string $d): bool => is_file($d . '/manifest.json')));
if ($packages === []) {
    fwrite(STDERR, "No packages with manifest.json found under {$root}\n");
    exit(1);
}

foreach ($packages as $dir) {
    $id = basename($dir);
    $errors = [];
    $raw = file_get_contents($dir . '/manifest.json');
    $data = json_decode($raw);
    if (!$data instanceof stdClass) {
        $errors[] = 'manifest.json is not valid JSON';
        printResult($id, $errors, $failures);

        continue;
    }

    $validator = new JsonSchema\Validator();
    $validator->validate($data, (object) ['$ref' => 'file://' . $schemaPath]);
    if (!$validator->isValid()) {
        foreach ($validator->getErrors() as $e) {
            $errors[] = sprintf('[%s] %s', $e['property'] ?: '(root)', $e['message']);
        }
    }

    $m = json_decode((string) $raw, true);
    if (($m['type'] ?? null) !== 'module') {
        $errors[] = 'type must be "module"';
    }
    if (strtolower($id) !== ($m['id'] ?? null)) {
        $errors[] = sprintf('id "%s" must match directory "%s" (case-insensitive)', $m['id'] ?? '?', $id);
    }
    foreach (['author.url' => $m['author']['url'] ?? null, 'homepage_url' => $m['homepage_url'] ?? null] as $field => $url) {
        if ($url !== null && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            $errors[] = $field . ' must be an http(s) URL';
        }
    }
    if (isset($m['icon_url'])) {
        $icon = $m['icon_url'];
        $resolved = realpath($dir . '/' . $icon) ?: $dir . '/' . $icon;
        if (!is_file($dir . '/' . $icon) || !str_starts_with($resolved, (string) realpath($dir))) {
            $errors[] = 'icon_url target missing or escapes module dir';
        }
    }

    try {
        $parser->normalize($m['version'] ?? '');
        if (str_starts_with((string) ($m['version'] ?? ''), 'v')) {
            $errors[] = 'version must not have a "v" prefix';
        }
    } catch (UnexpectedValueException) {
        $errors[] = 'version is not valid semver';
    }
    foreach (['fossbilling' => $m['requires']['fossbilling'] ?? null, 'php' => $m['requires']['php'] ?? null] as $field => $c) {
        try {
            $parser->parseConstraints((string) $c);
        } catch (UnexpectedValueException) {
            $errors[] = "requires.{$field} is not a valid composer/semver constraint";
        }
    }
    foreach ($m['requires']['modules'] ?? [] as $dep => $c) {
        if ($dep === ($m['id'] ?? null)) {
            $errors[] = 'requires.modules must not self-reference';
        }

        try {
            $parser->parseConstraints((string) $c);
        } catch (UnexpectedValueException) {
            $errors[] = "requires.modules.{$dep} is not a valid constraint";
        }
    }
    if (!$spdx->validate((string) ($m['license'] ?? ''))) {
        $errors[] = 'license is not a valid SPDX expression';
    }

    printResult($id, $errors, $failures);
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("FAIL: %s\n", implode(', ', $failures)));
    exit(1);
}
echo sprintf("OK: %d package(s) valid\n", count($packages));

function printResult(string $id, array $errors, array &$failures): void
{
    if ($errors === []) {
        echo "PASS {$id}\n";

        return;
    }
    $failures[] = $id;
    echo "FAIL {$id}\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
}
