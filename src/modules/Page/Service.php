<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Page;

use FOSSBilling\InjectionAwareInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'can_always_access' => true,
            'hide_permissions' => true,
        ];
    }

    /**
     * @return string[]
     */
    public function getPairs(): array
    {
        $themeService = $this->di['mod_service']('theme');
        $themeCode = $themeService->getCurrentClientAreaThemeCode();
        $paths = [
            Path::join(PATH_THEMES, (string) $themeCode, 'html'),
            Path::join(PATH_MODS, 'Page', 'html_client'),
        ];

        $finder = new Finder();
        $finder->files()->in($paths)->name('mod_page_*.html.twig');

        $list = [];
        foreach ($finder as $file) {
            $fileName = $file->getBasename('.html.twig');
            $list[$fileName] = ucwords(strtr($fileName, ['-' => ' ', '_' => ' ']));
        }

        return $list;
    }
}
