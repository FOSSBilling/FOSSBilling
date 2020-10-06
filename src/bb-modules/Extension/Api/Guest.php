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


namespace Box\Mod\Extension\Api;

/**
 * Extensions
 */
class Guest extends \Api_Abstract
{
    /**
     * Checks if extensions is available
     * 
     * @param string $mod - module name to be checked
     * @return bool
     */
    public function is_on($data)
    {
        $service = $this->getService();
        if(isset($data['mod']) && !empty($data['mod'])) {
            return $service->isExtensionActive('mod', $data['mod']);
        }
        
        if(isset($data['id']) && !empty($data['type'])) {
            return $service->isExtensionActive($data['type'], $data['id']);
        }
        return true;
    }

    /**
     * Return active theme info
     * @return array
     */
    public function theme($client = true)
    {
        $systemService = $this->di['mod_service']('theme');
        return $systemService->getThemeConfig($client, null);
    }

    /**
     * Retrieve extension public settings
     *
     * @param string $ext - extension name
     * @throws Box_Exception
     * @return array
     */
    public function settings($data)
    {
        if(!isset($data['ext'])) {
            throw new \Box_Exception('Parameter ext is missing');
        }
        $service = $this->getService();
        $config = $service->getConfig($data['ext']);
        return (isset($config['public']) && is_array($config['public'])) ? $config['public'] : array();
    }

    /**
     * Retrieve list of available languages
     * @return array
     */
    public function languages()
    {
        $service = $this->di['mod_service']('system');
        return $service->getLanguages();
    }
}