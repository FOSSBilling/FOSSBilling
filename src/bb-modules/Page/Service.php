<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (http://www.boxbilling.com)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */


namespace Box\Mod\Page;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    /**
     * @var \Box_Di
     */
    protected $di = null;

    /**
     * @param \Box_Di $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return \Box_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function getPairs()
    {
        $themeService = $this->di['mod_service']('theme');
        $code = $themeService->getCurrentClientAreaThemeCode();
        $paths = array(
            BB_PATH_THEMES . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR,
            BB_PATH_MODS . DIRECTORY_SEPARATOR . 'mod_page' . DIRECTORY_SEPARATOR . 'html_client' . DIRECTORY_SEPARATOR,
        );

        $list = array();
        foreach($paths as $path) {
            foreach(glob($path.'mod_page_*.phtml') as $file) {
                $file = str_replace('mod_page_', '', pathinfo($file, PATHINFO_FILENAME));
                $list[$file] = ucwords(strtr($file, array('-'=>' ', '_'=>' ')));
            }
        }

        return $list;
    }
}