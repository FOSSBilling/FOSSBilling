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


use Box\InjectionAwareInterface;

final class Api_Handler implements InjectionAwareInterface
{
    protected $type     = NULL;
    protected $identity = NULL;
    protected $ip       = NULL;
    protected $di      = NULL;

    private   $_enable_cache    = FALSE;
    private   $_cache           = array();
    private   $_acl_exception    = FALSE;
    
    public function __construct($identity)
    {
        $this->identity = $identity;
        $role = str_replace('model_', '', strtolower(get_class($identity)));
        $this->type = $role;
    }

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function __call($method, $arguments)
    {
        if(strpos($method, '_') === FALSE) {
            throw new \Box_Exception("Method :method must contain underscore", array(':method'=>$method), 710);
        }

        if(isset($arguments[0])) {
            $arguments = $arguments[0];
        }

        $e = explode('_', $method);
        $mod = strtolower($e[0]);
        unset($e[0]);
        $method_name = implode('_', $e);
        
        if(empty($mod)) {
            throw new \Box_Exception('Invalid module name', null, 714);
        }
        
        //cache
        $cache_key = md5($this->type.$mod.$method_name.serialize($arguments));
        if($this->_enable_cache && isset($this->_cache[$cache_key])) {
            return $this->_cache[$cache_key];
        }

        $service = $this->di['mod']('extension')->getService();

        if(!$service->isExtensionActive('mod',$mod)) {
            throw new \Box_Exception('BoxBilling module :mod is not installed/activated',array(':mod'=>$mod), 715);
        }

        // permissions check
        if($this->type == 'admin') {
            $staff_service = $this->di['mod_service']('Staff');
            if(!$staff_service->hasPermission($this->identity, $mod, $method_name)) {
                if($this->_acl_exception) {
                    throw new \Box_Exception('You do not have access to :mod module', array(':mod'=>$mod), 725);
                } else {
                    if(BB_DEBUG) error_log('You do not have access to '.$mod. ' module');
                    return null;
                }
            }
        }

        $api_class = '\Box\Mod\\'.ucfirst($mod).'\\Api\\'.ucfirst($this->type);

        $api = new $api_class();

        if(!$api instanceof Api_Abstract ) {
            throw new \Box_Exception('Api class must be instance of Api_Abstract', null, 730);
        }

        $bb_mod = $this->di['mod']($mod);

        $api->setDi($this->di);
        $api->setMod($bb_mod);
        $api->setIdentity($this->identity);
        $api->setIp($this->di['request']->getClientAddress());
        if($bb_mod->hasService()) {
            $api->setService($this->di['mod_service']($mod));
        }

        if(!method_exists($api, $method_name) || !is_callable(array($api, $method_name))) {
            $reflector = new ReflectionClass($api);
            if(!$reflector->hasMethod('__call')) {
                throw new \Box_Exception(':type API call :method does not exist in module :module', array(':type'=>ucfirst($this->type), ':method'=>$method_name,':module'=>$mod), 740);
            }
        }
        $res = $api->{$method_name}($arguments);
        if($this->_enable_cache) $this->_cache[$cache_key] = $res;
        return $res;
    }
}