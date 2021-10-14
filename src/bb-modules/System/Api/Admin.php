<?php
/**
 * BoxBilling
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

/**
 * System management methods 
 */

namespace Box\Mod\System\Api;

class Admin extends \Api_Abstract
{
    /**
     * Return system setting param
     * @deprecated
     * @param string $key - parameter key name
     * @return string
     */
    public function param($data)
    {
        $required = array(
            'key'    => 'Parameter key is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->getParamValue($data['key']);
    }

    /**
     * Get all defined system params
     * 
     * @return array
     */
    public function get_params($data)
    {
        return $this->getService()->getParams($data);
    }

    /**
     * Updated parameters array with new values. Creates new setting if it was 
     * not defined earlier. You can create new parameters using this method.
     * This method accepts any number of parameters you pass.
     * 
     * @param string $key - name of the parameter to be changed/created
     * 
     * @return bool
     */
    public function update_params($data)
    {
        return $this->getService()->updateParams($data);
    }
    
    /**
     * System messages about working environment.
     * 
     * @param string $type - messages type to be returned: info
     * 
     * @return array
     */
    public function messages($data)
    {
        $type = $this->di['array_get']($data, 'type', 'info');
        return $this->getService()->getMessages($type);
    }
    
    /**
     * Check if passed file name template exists for admin area
     * 
     * @param string $file - template file name, example: mod_index_dashboard.phtml
     * @return bool
     */
    public function template_exists($data)
    {
        if(!isset($data['file'])) {
            return false;
        }
        
        return $this->getService()->templateExists($data['file'], $this->getIdentity());
    }
    
    /**
     * Parse string like BoxBilling template
     * 
     * @param string $_tpl - Template text to be parsed
     * 
     * @optional bool $_try - if true, will not throw error if template is not valid, returns _tpl string
     * @optional int $_client_id - if passed client id, then client API will also be available
     * 
     * @return string
     */
    public function string_render($data)
    {
        if(!isset($data['_tpl'])) {
            error_log('_tpl parameter not passed');
            return '';
        }
        $tpl = $data['_tpl'];
        $try_render = $this->di['array_get']($data, '_try', false);

        $vars = $data;
        unset($vars['_tpl'], $vars['_try']);
        return $this->getService()->renderString($tpl, $try_render, $vars);
    }
    
    /**
     * Returns system environment information. 
     * 
     * @return array
     */
    public function env($data)
    {
        $ip = $this->di['array_get']($data, 'ip', null);
        return $this->getService()->getEnv($ip);
    }

    /**
     * Method to check if staff member has permission to access module
     * 
     * @param string $mod - module name
     * 
     * @optional string $f - module method name
     * 
     * @return bool
     * @throws \Box_Exception
     */
    public function is_allowed($data)
    {
        $required = array(
            'mod'    => 'mod key is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $f = $this->di['array_get']($data, 'f', null);
        $service = $this->di['mod_service']('Staff');
        return $service->hasPermission($this->getIdentity(), $data['mod'], $f);
    }

    /**
     * Clear system cache
     *
     * @return bool
     */
    public function clear_cache()
    {
        return $this->getService()->clearCache();
    }
}