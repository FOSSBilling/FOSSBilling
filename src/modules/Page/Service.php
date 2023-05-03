<?php

/**
 * FOSSBilling.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * Copyright FOSSBilling 2022
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

namespace Box\Mod\Page;

use \FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di;

    /**
     * @param \Pimple\Container $di
     */
    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    /**
     * @return \Pimple\Container
     */
    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getPairs()
    {
        $themeService = $this->di['mod_service']('theme');
        $code = $themeService->getCurrentClientAreaThemeCode();
        $paths = [
            PATH_THEMES . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR,
            PATH_MODS . DIRECTORY_SEPARATOR . 'mod_page' . DIRECTORY_SEPARATOR . 'html_client' . DIRECTORY_SEPARATOR,
        ];

        $list = [];
        foreach ($paths as $path) {
            foreach (glob($path . 'mod_page_*.html.twig') as $file) {
                $file = str_replace('mod_page_', '', pathinfo($file, PATHINFO_FILENAME));
                $list[$file] = ucwords(strtr($file, ['-' => ' ', '_' => ' ']));
            }
        }

        return $list;
    }
}
