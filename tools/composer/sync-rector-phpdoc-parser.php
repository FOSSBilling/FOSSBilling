#!/usr/bin/env php
<?php

/**
 * FOSSBilling composer helper: align Rector's nested phpstan/phpdoc-parser copy.
 *
 * Rationale: the rector/rector dist ships its own nested vendor/ directory
 * containing an *unprefixed* copy of phpstan/phpdoc-parser. Rector's bootstrap
 * (preload.php plus its nested autoloader) loads that copy first, shadowing
 * the project's copy. When the nested copy is older than what the installed
 * PHPStan expects (e.g. nested 2.3.x while PHPStan 2.3.4+ relies on the
 * phpdoc-parser 2.4 API, where CallableTypeNode accepts any TypeNode as its
 * identifier), Rector crashes when precisely describing closure types during
 * analysis of any file:
 *
 *   CallableTypeNode::__construct(): Argument #1 ($identifier) must be of type
 *   IdentifierTypeNode, FullyQualifiedNameNode given
 *
 * In practice this breaks the Rector check for every Pest test file (Pest's
 * generic expect()/test() signatures trigger template inference over closures).
 *
 * This script copies the project's phpstan/phpdoc-parser over Rector's nested
 * copy whenever the nested copy is older. It is a no-op when Rector is not
 * installed (--no-dev), when the nested copy is already up to date, or when a
 * future Rector release fixes its packaging (nested copy no longer unprefixed).
 *
 * Wired into post-install-cmd/post-update-cmd in composer.json.
 *
 * Usage:
 *   php tools/composer/sync-rector-phpdoc-parser.php
 */

declare(strict_types=1);

function out(string $message): void
{
    echo '[sync-rector-phpdoc-parser] ' . $message . "\n";
}

function fail(string $message): never
{
    // Never fail the install: a stale nested copy is Rector's problem, not the
    // installer's. Report and leave the tree untouched.
    out('skipped: ' . $message);
    exit(0);
}

$projectRoot = dirname(__DIR__, 2);
if (!is_file($projectRoot . '/composer.json')) {
    fail('could not locate composer.json from ' . $projectRoot);
}

$composerConfig = json_decode((string) file_get_contents($projectRoot . '/composer.json'), true);
if (!is_array($composerConfig)) {
    fail('could not parse composer.json');
}

$configuredVendorDir = getenv('COMPOSER_VENDOR_DIR') ?: ($composerConfig['config']['vendor-dir'] ?? 'vendor');
$vendorDir = str_starts_with($configuredVendorDir, '/') ? $configuredVendorDir : $projectRoot . '/' . $configuredVendorDir;
$rootPackageDir = $vendorDir . '/phpstan/phpdoc-parser';
$nestedPackageDir = $vendorDir . '/rector/rector/vendor/phpstan/phpdoc-parser';

if (!is_dir($nestedPackageDir)) {
    // Rector not installed (--no-dev) or a future Rector without a nested copy.
    exit(0);
}
if (!is_dir($rootPackageDir)) {
    fail('project copy of phpstan/phpdoc-parser is missing');
}

// A future fixed Rector would scope (prefix) its bundled copy; only an
// unprefixed nested copy can shadow the project's classes.
$nestedNodeFile = $nestedPackageDir . '/src/Ast/Type/CallableTypeNode.php';
$nestedSource = is_file($nestedNodeFile) ? (string) file_get_contents($nestedNodeFile) : '';
if (!str_contains($nestedSource, 'namespace PHPStan\\PhpDocParser')) {
    out('nested copy already scoped or removed upstream; nothing to do');
    exit(0);
}

function installedVersion(string $vendorDir, string $package): ?string
{
    $installedFile = $vendorDir . '/composer/installed.json';
    if (!is_file($installedFile)) {
        return null;
    }
    $installed = json_decode((string) file_get_contents($installedFile), true);
    foreach (($installed['packages'] ?? []) as $entry) {
        if (($entry['name'] ?? null) === $package) {
            return $entry['version'] ?? null;
        }
    }

    return null;
}

$rootVersion = installedVersion($vendorDir, 'phpstan/phpdoc-parser');
$nestedVersion = installedVersion($vendorDir . '/rector/rector/vendor', 'phpstan/phpdoc-parser');

if ($rootVersion === null || $nestedVersion === null) {
    fail('could not determine installed versions');
}
if (version_compare($nestedVersion, $rootVersion, '>=')) {
    exit(0);
}

function removeTree(string $path): bool
{
    if (is_link($path) || is_file($path)) {
        return unlink($path);
    }
    $entries = scandir($path);
    if ($entries === false) {
        return false;
    }
    $ok = true;
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $removed = removeTree($path . '/' . $entry);
        $ok = $ok && $removed;
    }
    $removed = rmdir($path);

    return $ok && $removed;
}

function copyTree(string $from, string $to): bool
{
    if (!is_dir($to) && !mkdir($to, 0o755, true)) {
        return false;
    }
    $entries = scandir($from);
    if ($entries === false) {
        return false;
    }
    $ok = true;
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $source = $from . '/' . $entry;
        $target = $to . '/' . $entry;
        if (is_link($source)) {
            $copied = symlink((string) readlink($source), $target);
        } elseif (is_dir($source)) {
            $copied = copyTree($source, $target);
        } else {
            $copied = copy($source, $target);
        }
        $ok = $ok && $copied;
    }

    return $ok;
}

// Stage the copy beside the nested copy so a failed copy never leaves the working
// tree half-replaced: the existing directory is only swapped out once the staged
// copy is complete. Never fail the install.
$stagingDir = $nestedPackageDir . '.staging-' . getmypid();
$backupDir = $nestedPackageDir . '.backup-' . getmypid();
if (!copyTree($rootPackageDir, $stagingDir) || !is_file($stagingDir . '/src/Ast/Type/CallableTypeNode.php')) {
    removeTree($stagingDir);
    out('warning: sync failed, keeping the existing nested copy for a later run to retry');
    exit(0);
}
if (!rename($nestedPackageDir, $backupDir)) {
    removeTree($stagingDir);
    out('warning: sync failed, keeping the existing nested copy for a later run to retry');
    exit(0);
}
if (!rename($stagingDir, $nestedPackageDir)) {
    if (!rename($backupDir, $nestedPackageDir)) {
        out('warning: sync failed and the nested copy could not be restored from ' . $backupDir);
        exit(0);
    }
    removeTree($stagingDir);
    out('warning: sync failed, keeping the existing nested copy for a later run to retry');
    exit(0);
}
removeTree($backupDir);

// Keep the nested metadata truthful so repeat runs are a no-op. Composer
// never reads this file (the nested copy is not composer-managed); it only
// feeds the version guard above.
$nestedInstalledFile = $vendorDir . '/rector/rector/vendor/composer/installed.json';
$nestedInstalled = is_file($nestedInstalledFile) ? json_decode((string) file_get_contents($nestedInstalledFile), true) : null;
if (is_array($nestedInstalled)) {
    foreach (($nestedInstalled['packages'] ?? []) as $index => $entry) {
        if (($entry['name'] ?? null) === 'phpstan/phpdoc-parser') {
            $nestedInstalled['packages'][$index]['version'] = $rootVersion;
            file_put_contents($nestedInstalledFile, json_encode($nestedInstalled, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

            break;
        }
    }
}

out("synced nested copy {$nestedVersion} -> project copy {$rootVersion}");
