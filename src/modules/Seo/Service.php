<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Seo;

use FOSSBilling\InjectionAwareInterface;

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

    public function pingSitemap($config, $forced = false)
    {
        $systemService = $this->di['mod_service']('system');

        $key = 'mod_seo_last_sitemap_submit';
        $last_time = $systemService->getParamValue($key);

        // Make sure we don't ping more than once a day
        if ($last_time && (time() - strtotime($last_time)) < 24 * 60 * 60 && !$forced) {
            return false;
        }

        $url = urldecode(SYSTEM_URL . 'sitemap.xml');

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

        return [
            'sitemap_url' => SYSTEM_URL . 'sitemap.xml',
            'last_exec' => $systemService->getParamValue('mod_seo_last_sitemap_submit'),
            'engines' => $this->_getEngineDetails(),
        ];
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

        return isset($config['sitemap_' . $engine]) && $config['sitemap_' . $engine] == 'on';
    }

    /**
     * Load engines from the Engines directory.
     */
    private function _getEngines(): array
    {
        $engines = [];
        $dir = __DIR__ . '/Engines';
        $files = scandir($dir);

        foreach ($files as $file) {
            if (str_ends_with($file, '.php')) {
                $engine = substr($file, 0, -4);
                $class = 'Box\\Mod\\Seo\\Engines\\' . $engine;
                $engines[$engine] = new $class();
            }
        }

        return $engines;
    }

    /**
     * Get the details of all engines.
     */
    private function _getEngineDetails(): array
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
