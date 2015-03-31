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

class Admin extends \Api_Abstract
{
    /**
     * Get list of active and inactive extensions on system
     * 
     * @optional bool $installed - return installed only extensions
     * @optional bool $active - return installed and core extensions
     * @optional bool $has_settings - return extensions with configuration pages only
     * @optional string $search - filter extensions by search keyword
     * @optional string $type - filter extensions by type
     * 
     * @return array 
     */
    public function get_list($data)
    {
        $service = $this->getService();
        return $service->getExtensionsList($data);
    }
    
    /**
     * Get list of extensions from extensions.boxbilling.com
     * which can be installed on current version of BoxBilling
     * 
     * @param string $type - mod, gateway ...
     * @return array 
     */
    public function get_latest($data)
    {
        //@todo enable when extensions are available
        return array();
        /*
        $type = $this->di['array_get']($data, 'type', null);
        try {
            $list = $this->di['extension']->getLatest($type);
        } catch(\Exception $e) {
            $list = array();
        }
        return $list;
        */
    }

    /**
     * Get admin area navigation
     * 
     * @return array 
     */
    public function get_navigation($data)
    {
        $url = null;
        if(isset($data['url'])) {
            $url = $data['url'];
        }
        $service = $this->getService();
        return $service->getAdminNavigation($this->identity, $url);
    }

    /**
     * Get list of available languages on the system
     * 
     * @return array 
     */
    public function languages()
    {
        $systemService = $this->di['mod_service']('system');
        return $systemService->getLanguages(true);
    }

    /**
     * Update BoxBilling core
     * 
     * 
     * @return bool
     * 
     * @throws Box_Exception
     * @throws Exception 
     */
    public function update_core($data)
    {

        $updater = $this->di['updater'];
        if(!$updater->getCanUpdate()) {
            throw new \Box_Exception('You have latest version of BoxBilling. You do not need to update.', null, 930);
        }
        
        $new_version = $updater->getLatestVersion();
        $updater->performUpdate();
        
        $this->di['logger']->info('Updated BoxBilling from %s to %s', \Box_Version::VERSION, $new_version);
        return true;
    }

    /**
     * Update existing extension
     * 
     * @param string $type - extensions type: mod, theme, gateway ...
     * @param string $id - extension id
     * 
     * @return array
     * 
     * @throws Box_Exception
     * @throws Exception 
     */
    public function update($data)
    {

        $ext = $this->_getExtension($data);
        $service = $this->getService();
        return $service->update($ext);
    }

    /**
     * Activate existing extension
     * 
     * @param string $type - extensions type: mod, theme, gateway ...
     * @param string $id - extension id
     * 
     * @return array
     * 
     * @throws Box_Exception
     * @throws Exception 
     */
    public function activate($data)
    {
        $required = array(
            'id'         => 'Extension ID was not passed',
            'type' => 'Extension type was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $result = $service->activateExistingExtension($data);
        return $result;
    }

    /**
     * Deactivate existing extension
     * 
     * @param string $type - extensions type: mod, theme, gateway ...
     * @param string $id - extension id
     * 
     * @return bool - true
     * 
     * @throws Box_Exception
     * @throws Exception 
     */
    public function deactivate($data)
    {
        $ext = $this->_getExtension($data);
        
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminDeactivateExtension', 'params'=>array('id'=>$ext->id)));
        
        $service = $this->getService();
        $service->deactivate($ext);
        
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminDeactivateExtension', 'params'=>array('id'=>$data['id'], 'type'=>$data['type'])));
        
        $this->di['logger']->info('Deactivated extension "%s"', $data['type'].' '.$data['id']);
        return true;
    }
    
    /**
     * Completely remove extension from BoxBilling
     * 
     * @param string $type - extensions type: mod, theme, gateway ...
     * @param string $id - extension id
     * 
     * @return boolean 
     */
    public function uninstall($data)
    {
        $ext = $this->_getExtension($data);
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminUninstallExtension', 'params'=>array('id'=>$ext->id)));
        
        $service = $this->getService();
        $service->uninstall($ext);

        $this->di['events_manager']->fire(array('event'=>'onAfterAdminUninstallExtension', 'params'=>array('id'=>$ext->id)));
        return true;
    }
    
    /**
     * Install new extension from extensions site
     * 
     * @param string $type - extensions type: mod, theme, gateway ...
     * @param string $id - extension id
     * @return array
     * @throws Box_Exception 
     */
    public function install($data)
    {
        $required = array(
            'id'         => 'Extension ID was not passed',
            'type' => 'Extension type was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $this->di['events_manager']->fire(array('event'=>'onBeforeAdminInstallExtension', 'params'=>$data));

        $service = $this->getService();
        $service->downloadAndExtract($data['type'], $data['id']);
        
        try {
            $this->activate($data);
        } catch(\Exception $e) {
            error_log($e->getMessage());
        }
        $this->di['events_manager']->fire(array('event'=>'onAfterAdminInstallExtension', 'params'=>$data));
        return array(
            'success'   =>  true,
            'id'        =>  $data['id'],
            'type'      =>  $data['type'],
        );
    }
    
    /**
     * Universal method for BoxBilling extensions 
     * to retrieve configuration from database
     * It is recommended to store your extension configuration
     * using this method. Automatic decryption is available
     * All parameters in "public" array will be accessible by guest API
     * 
     * @param string $ext - extension name, ie: mod_news
     * @return array - configuration parameters
     * @throws \Box_Exception
     */
    public function config_get($data)
    {
        $required = array(
            'ext' => 'Parameter ext was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $config = $service->getConfig($data['ext']);
        return $config;
    }
    
    /**
     * Universal method for BoxBilling extensions 
     * to update or save extension configuration to database
     * Always pass all configuration parameters to this method.
     * 
     * Config is automatically encrypted and stored in database
     * 
     * @param string $ext - extension name, ie: mod_news
     * @optional string $any - Any variable passed to this method is config parameter
     * 
     * @return bool
     * @throws Box_Exception 
     */
    public function config_save($data)
    {
        $required = array(
            'ext'         => 'Parameter ext was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        return $this->getService()->setConfig($data);
    }
    
    private function _getExtension($data)
    {
        $required = array(
            'id'         => 'Extension ID was not passed',
            'type' => 'Extension type was not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();
        $ext = $service->findExtension($data['type'], $data['id']);
        if(!$ext instanceof \Model_Extension) {
            throw new \Box_Exception('Extension not found');
        }
        
        return $ext;
    }
}