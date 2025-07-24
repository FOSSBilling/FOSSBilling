<?php

declare(strict_types=1);
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
        $code = $themeService->getCurrentClientAreaThemeCode();
        $paths = [
            Path::join(PATH_THEMES, $code, 'html'),
            Path::join(PATH_MODS, 'mod_page', 'html_client'),
        ];

        $list = [];
        foreach ($paths as $path) {
            foreach (glob($path . 'mod_page_*.html.twig') as $file) {
                $file = str_replace('mod_page_', '', Path::getFilenameWithoutExtension($file));
                $list[$file] = ucwords(strtr($file, ['-' => ' ', '_' => ' ']));
            }
        }

        return $list;
    }
}
