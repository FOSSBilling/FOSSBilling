<?php

/**
 * Copyright 2022-2026 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 */

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Twig\Loader\FilesystemLoader;

/**
 * Twig loader that exposes both the admin and client module template
 * directories plus both themes' html directories under a single root
 * namespace, so the strict-variables test can render any FOSSBilling
 * template by basename.
 */
final class CombinedTwigLoader extends FilesystemLoader
{
    public function __construct(string $themesPath)
    {
        parent::__construct();

        foreach (['admin_default', 'huraga'] as $code) {
            $custom = Path::join($themesPath, $code, 'html_custom');
            if (is_dir($custom)) {
                $this->addPath($custom);
            }
            $default = Path::join($themesPath, $code, 'html');
            if (is_dir($default)) {
                $this->addPath($default);
            }
        }

        $finder = new Finder();
        $finder->directories()->in(PATH_MODS)->depth('== 2')->ignoreDotFiles(true)->name(['admin', 'client', 'email']);
        foreach ($finder as $dir) {
            $parent = Path::getDirectory($dir->getPathName());
            if (basename($parent) === 'templates') {
                $grandparent = Path::getDirectory($parent);
                $module = basename($grandparent);
                $area = basename($dir->getPathName());
                // FilesystemLoader namespaces can't contain '/' (Twig splits
                // `@ns/template` on the first '/'). Use an underscore join.
                $this->addPath($dir->getPathName(), $module . '_' . $area);
            }
        }
    }
}
