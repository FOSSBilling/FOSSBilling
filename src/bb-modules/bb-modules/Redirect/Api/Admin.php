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

namespace Box\Mod\Redirect\Api;

/**
 * Redirects management
 */
class Admin extends \Api_Abstract
{
    
    /**
     * Get list of redirects
     * 
     * @return array - list
     */
    public function get_list()
    {
        return $this->getService()->getRedirects();
    }
    
    /**
     * Get redirect by id
     * 
     * @param int $id - int
     * 
     * @return array
     */
    public function get($data)
    {
        $required = array(
            'id' => 'Redirect ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $bean = $this->_getRedirect($data['id']);
        return array(
            'id'        =>  $bean->id,
            'path'      =>  $bean->meta_key,
            'target'    =>  $bean->meta_value,
        );
    }
    
    /**
     * Create new redirect
     * 
     * @param string $path - redirect path
     * @param string $target - redirect target
     * 
     * @return int redirect id
     */
    public function create($data)
    {
        $required = array(
            'path'   => 'Redirect path not passed',
            'target' => 'Redirect target not passed',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $bean = $this->di['db']->dispense('extension_meta');
        $bean->extension = 'mod_redirect';
        $bean->meta_key = trim($data['path'], '/');
        $bean->meta_value = trim($data['target'], '/');
        $bean->created_at = date('Y-m-d H:i:s');
        $bean->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($bean);
        
        $id = $bean->id;
        
        $this->di['logger']->info('Created new redirect #%s', $id);
        return (int)$id;
    }
    
    /**
     * Update redirect 
     * 
     * @param int $id - redirect id
     * 
     * @optional string $path - redirect path
     * @optional string $target - redirect target
     * 
     * @return true
     */
    public function update($data)
    {
        $required = array(
            'id' => 'Redirect ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $bean = $this->_getRedirect($data['id']);
        
        $bean->meta_key = $this->di['array_get']($data, 'path', $bean->meta_key);
        $bean->meta_value = $this->di['array_get']($data, 'target', $bean->meta_value);
        $bean->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($bean);
        
        $this->di['logger']->info('Updated redirect #%s', $data['id']);
        return true;
    }
    
    /**
     * Delete redirect 
     * 
     * @param int $id - redirect id
     * @return true
     */
    public function delete($data)
    {
        $required = array(
            'id' => 'Redirect ID is required',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);
        
        $bean = $this->_getRedirect($data['id']);
        $this->di['db']->trash($bean);
        
        $this->di['logger']->info('Removed redirect #%s', $data['id']);
        return true;
    }
    
    private function _getRedirect($id)
    {
        $sql = " extension = 'mod_redirect' AND id = :id";
        $values = array('id'=>$id);
        $bean = $this->di['db']->findOne('extension_meta',$sql, $values);
        
        if(!$bean) {
            throw new \Box_Exception('Redirect not found');
        }
        
        return $bean;
    }
}