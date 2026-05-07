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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Twig\Loader\FilesystemLoader;

class TwigLoader extends FilesystemLoader
{
    private readonly Filesystem $filesystem;

    /**
     * Create new TwigLoader with FOSSBilling-specific path configuration.
     *
     * @param AppArea $appArea   either 'admin' or 'client'
     * @param string  $themePath path to the theme directory
     */
    public function __construct(AppArea $appArea, string $themePath)
    {
        parent::__construct();

        $this->filesystem = new Filesystem();

        // Load path in priority order: custom theme files, default theme, module templates.
        $paths = [];
        $customPath = Path::join($themePath, 'html_custom');
        if ($this->filesystem->exists($customPath)) {
            $paths[] = $customPath;
        }
        $defaultPath = Path::join($themePath, 'html');
        if ($this->filesystem->exists($defaultPath)) {
            $paths[] = $defaultPath;
        }
        $paths = array_merge($paths, $this->getModuleTemplatePaths($appArea));

        $this->setPaths($paths);

        // Add additional path to load symbols if they exist.
        $symbolPath = Path::join($themePath, 'build', 'symbol');
        if ($this->filesystem->exists($symbolPath)) {
            $this->prependPath($symbolPath, 'symbol');
        }
    }

    /**
     * Override findTemplate to handle module icon naming convention.
     * Handles module icon naming convention for icon.svg files.
     */
    #[\Override]
    protected function findTemplate(string $name, bool $throw = true)
    {
        // Normalize name (same as parent's private normalizeName)
        $name = preg_replace('#/{2,}#', '/', str_replace('\\', '/', $name));

        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        // Handle module icons: mod_ModuleName_icon.svg -> PATH_MODS/ModuleName/icon.svg
        // Also handles mod_ModuleName_filename.svg -> PATH_MODS/ModuleName/filename.svg
        if (preg_match('/^mod_([A-Za-z0-9]+)_(.+\.svg)$/', (string) $name, $matches)) {
            $moduleName = $matches[1];
            $iconFile = $matches[2];
            $iconPath = Path::join(PATH_MODS, $moduleName, $iconFile);
            if ($this->filesystem->exists($iconPath)) {
                return $this->cache[$name] = $iconPath;
            }
        }

        return parent::findTemplate($name, $throw);
    }

    /**
     * Get module template paths for the specified app area.
     *
     * @param AppArea $appArea the application area as an AppArea enum value
     *
     * @return string[] array of module template paths
     */
    private function getModuleTemplatePaths(AppArea $appArea): array
    {
        $paths = [];
        $areaDir = $appArea->value;

        $finder = new Finder();
        $finder->directories()->in(PATH_MODS)->depth('== 2')->ignoreDotFiles(true)->name($areaDir);

        foreach ($finder as $dir) {
            $moduleTemplatePath = $dir->getPathName();
            $parentDir = Path::getDirectory($moduleTemplatePath);
            if ($this->filesystem->exists($moduleTemplatePath) && basename($parentDir) === 'templates') {
                $paths[] = $moduleTemplatePath;
            }
        }

        return $paths;
    }
}
