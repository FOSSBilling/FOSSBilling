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

namespace Box\Mod\Seo;

use Box\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function pingSitemap($config, $forced = false)
    {
        $systemService = $this->di['mod_service']('system');

        $key = 'mod_seo_last_sitemap_submit';
        $last_time = $systemService->getParamValue($key);

        // Make sure we don't ping more than once a day
        if ($last_time && (time() - strtotime($last_time)) < 24 * 60 * 60 && !$forced) {
            return false;
        }

        $url = urldecode(BB_URL . 'sitemap.xml');

        $engines = $this->_getEngines();

        // Load the engines and ping them
        foreach ($engines as $engine) {
            $id = $engine->getDetails()['id'];
            
            if ($this->isEngineEnabled($id)) {
                try {
                    $engine->setDi($this->di);
                    $engine->pingSitemap($url);
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
            }
        }

        // Update the last time we pinged
        $systemService->updateParams([$key => date('Y-m-d H:i:s')]);

        return true;
    }

    /**
     * @return array
     */
    public function getInfo()
    {
        $systemService = $this->di['mod_service']('system');

        $result = [
            'sitemap_url' => BB_URL . 'sitemap.xml',
            'last_exec' => $systemService->getParamValue('mod_seo_last_sitemap_submit'),
            'engines' => $this->_getEngineDetails(),
        ];

        return $result;
    }

    /**
     * @param string $engine - The ID of the engine to check
     * 
     * @return bool
     */
    public function isEngineEnabled($engine)
    {
        $extensionService = $this->di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_seo');

        return isset($config['sitemap_' . $engine]) && $config['sitemap_' . $engine] == "on";
    }

    /**
     * Load engines from the Engines directory.
     * 
     * @return array
     */
    private function _getEngines()
    {
        $engines = [];
        $dir = __DIR__ . '/Engines';
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if (substr($file, -4) == '.php') {
                $engine = substr($file, 0, -4);
                $class = 'Box\\Mod\\Seo\\Engines\\' . $engine;
                $engines[$engine] = new $class();
            }
        }

        return $engines;
    }

    /**
     * Get the details of all engines.
     * 
     * @return array
     */
    private function _getEngineDetails()
    {
        $engines = $this->_getEngines();
        $details = [];

        foreach ($engines as $engine) {
            $engineDetails = $engine->getDetails();
            $engineDetails['enabled'] = $this->isEngineEnabled($engineDetails['id']);

            $details[$engineDetails['id']] = $engineDetails;
        }

        return $details;
    }

    public static function onBeforeAdminCronRun(\Box_Event $event)
    {
        $di = $event->getDi();
        $extensionService = $di['mod_service']('extension');
        $config = $extensionService->getConfig('mod_seo');

        try {
            $seoService = $di['mod_service']('seo');
            $seoService->setDi($di);
            $seoService->pingSitemap($config);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return true;
    }
}
