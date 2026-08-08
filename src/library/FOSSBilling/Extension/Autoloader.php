<?php

declare(strict_types=1);
/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Extension;

use Composer\Autoload\ClassLoader;
use Symfony\Component\Filesystem\Path;

/**
 * Autoloader for extensions installed under PATH_EXTENSIONS.
 *
 * Composer's autoloader is generated during installation and cannot see
 * extensions added to the disk afterwards, so they are resolved at runtime:
 *
 *     FOSSBilling\Extension\Gateway\Stripe\Stripe
 *         -> src/extensions/gateways/Stripe/Stripe.php
 *
 * An extension may carry its own vendor directory. That is registered the first
 * time one of its classes is loaded, and always appended, so a package core
 * also ships resolves to core's copy.
 */
final class Autoloader
{
    private const string NAMESPACE_PREFIX = 'FOSSBilling\\Extension\\';

    /** @var array<string, true> Bundles whose vendor directory has been considered. */
    private static array $registeredVendors = [];

    public static function register(): void
    {
        spl_autoload_register(self::load(...));
    }

    public static function load(string $class): void
    {
        if (!str_starts_with($class, self::NAMESPACE_PREFIX)) {
            return;
        }

        // A qualifying class name is at least Type\Id\ClassName.
        $segments = explode('\\', substr($class, strlen(self::NAMESPACE_PREFIX)));
        if (count($segments) < 3) {
            return;
        }

        $type = ExtensionType::fromNamespaceSegment(array_shift($segments));
        if (!$type instanceof ExtensionType) {
            return;
        }

        // Guard against directory traversal through a crafted class name.
        foreach ($segments as $segment) {
            if (preg_match('/\A[A-Za-z0-9_]+\z/', $segment) !== 1) {
                return;
            }
        }

        $id = array_shift($segments);
        $directory = $type->pathFor($id);
        $file = Path::join($directory, implode(DIRECTORY_SEPARATOR, $segments) . '.php');

        if (!is_file($file)) {
            return;
        }

        self::registerVendor($directory);

        require_once $file;
    }

    /**
     * Append an extension's own dependencies to the autoloader.
     *
     * Composer's generated autoload.php prepends itself, which would let an
     * extension's copy of a package shadow core's. The maps it generates are
     * loaded into a loader of our own instead, and registered last.
     */
    private static function registerVendor(string $directory): void
    {
        if (isset(self::$registeredVendors[$directory])) {
            return;
        }

        self::$registeredVendors[$directory] = true;

        $vendor = Path::join($directory, 'vendor');
        if (!is_dir(Path::join($vendor, 'composer'))) {
            return;
        }

        $loader = new ClassLoader($vendor);

        $psr4 = Path::join($vendor, 'composer', 'autoload_psr4.php');
        if (is_file($psr4)) {
            foreach (require $psr4 as $namespace => $paths) {
                $loader->setPsr4($namespace, $paths);
            }
        }

        $psr0 = Path::join($vendor, 'composer', 'autoload_namespaces.php');
        if (is_file($psr0)) {
            foreach (require $psr0 as $namespace => $paths) {
                $loader->set($namespace, $paths);
            }
        }

        $classmap = Path::join($vendor, 'composer', 'autoload_classmap.php');
        if (is_file($classmap)) {
            $loader->addClassMap(require $classmap);
        }

        // Appended, so anything core also provides resolves to core's copy.
        $loader->register(false);

        $files = Path::join($vendor, 'composer', 'autoload_files.php');
        if (is_file($files)) {
            foreach (require $files as $file) {
                if (is_file($file)) {
                    require_once $file;
                }
            }
        }
    }
}
