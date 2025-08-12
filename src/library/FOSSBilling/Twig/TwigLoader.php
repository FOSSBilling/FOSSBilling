<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\Twig;

use FOSSBilling\Twig\Enum\AppArea;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Twig\Loader\FilesystemLoader;

class TwigLoader extends FilesystemLoader
{
    /**
     * Create new TwigLoader with FOSSBilling-specific path configuration.
     *
     * @param AppArea   $appArea    Either 'admin' or 'client'.
     * @param string    $themePath  Path to the theme directory.
     */
    public function __construct(AppArea $appArea, string $themePath)
    {
        parent::__construct();

        // Load path in priority order: custom theme files, default theme, module templates.
        $paths = [];
        $customPath = Path::join($themePath, 'html_custom');
        if (is_dir($customPath)) {
            $paths[] = $customPath;
        }
        $defaultPath = Path::join($themePath, 'html');
        if (is_dir($defaultPath)) {
            $paths[] = $defaultPath;
        }
        $paths = array_merge($paths, $this->getModuleTemplatePaths($appArea));

        $this->setPaths($paths);

        // Add additional path to load symbols if they exist.
        $symbolPath = Path::join($themePath, 'build', 'symbol');
        if (is_dir($symbolPath)) {
            $this->prependPath($symbolPath, 'symbol');
        }
    }

    /**
     * Get module template paths for the specified app area.
     *
     * @param AppArea   $appArea  The application area as an AppArea enum value.
     *
     * @return string[] Array of module template paths.
     */
    private function getModuleTemplatePaths(AppArea $appArea): array
    {
        $paths = [];
        $htmlDir = "html_{$appArea->value}";

        $finder = new Finder();
        $finder->directories()->in(PATH_MODS)->depth('== 1')->ignoreDotFiles(true)->name($htmlDir);

        foreach ($finder as $dir) {
            $moduleTemplatePath = $dir->getPathName();
            if (is_dir($moduleTemplatePath)) {
                $paths[] = $moduleTemplatePath;
            }
        }

        return $paths;
    }
}
