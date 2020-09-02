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

namespace Box\Mod\Serviceboxbillinglicense;

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
    
    public function install()
    {
        $sql="
        CREATE TABLE IF NOT EXISTS `service_boxbillinglicense` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `client_id` bigint(20) DEFAULT NULL,
        `oid` varchar(255) DEFAULT NULL,
        `created_at` varchar(35) DEFAULT NULL,
        `updated_at` varchar(35) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `oid` (`oid`),
        KEY `client_id_idx` (`client_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ";
        
        $this->di['db']->exec($sql);
    }

    public function uninstall()
    {
        
        $this->di['db']->exec("DROP TABLE IF EXISTS `service_boxbillinglicense`");
    }

    public function setModuleConfig($data)
    {
        $this->getModuleConfig();
        
        $sql="
            UPDATE extension_meta 
            SET meta_value = :config
            WHERE extension = 'mod_serviceboxbillinglicense' 
            AND meta_key = 'config'
        ";
        $params = array(
            'config'        => json_encode($data),
        );
        $this->di['db']->exec($sql, $params);
    }
    
    public function getModuleConfig()
    {
        $c = $this->di['db']->findOne('extension_meta', 'extension = :ext AND meta_key = :key', array('ext'=>'mod_serviceboxbillinglicense', 'key'=>'config'));
        if(is_null($c)) {
            $c = $this->di['db']->dispense('extension_meta');
            $c->extension = 'mod_serviceboxbillinglicense';
            $c->meta_key = 'config';
            $c->meta_value = null;
            $c->created_at = date('Y-m-d H:i:s');
            $c->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($c);
        }
        $config = json_decode($c->meta_value, 1);
        if(!isset($config['api_key'])) {
            $config['api_key'] = null;
        }
        return $config;
    }
    
    /**
     * @param $order
     * @return void
     */
    public function create($order)
    {
        $model = $this->di['db']->dispense('service_boxbillinglicense');
        $model->client_id    = $order->client_id;
        $model->created_at   = date('Y-m-d H:i:s');
        $model->updated_at   = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        return $model;
    }

    /**
     * @param $order
     * @return boolean
     */
    public function activate($order, $model)
    {
        if(!is_object($model)) {
            throw new \Box_Exception('Could not activate order. Service was not created', null, 7456);
        }
        
        $result = $this->_getApi()->partner_order_create();
        
        $model->oid = $result;
        $model->updated_at   = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        
        return true;
    }

    /**
     * Suspend VPS
     * 
     * @param $order
     * @return boolean
     */
    public function suspend($order, $model)
    {
        if(!is_object($model)) {
            throw new \Box_Exception('Could not activate order. Service was not created', null, 7456);
        }

        $this->_getApi()->partner_order_suspend($model->vserverid);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        return true;
    }

    /**
     * @param $order
     * @return boolean
     */
    public function unsuspend($order, $model)
    {
        if(!is_object($model)) {
            throw new \Box_Exception('Could not activate order. Service was not created', null, 7456);
        }
        
        $this->_getApi()->partner_order_unsuspend($model->vserverid);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        return true;
    }

    /**
     * @param $order
     * @return boolean
     */
    public function delete($order, $model)
    {
        if(is_object($model)) {
            $this->_getApi()->partner_order_delete($model->vserverid);
            $this->di['db']->trash($model);
        }
        return true;
    }

    public function reset($order, $model)
    {
        $this->_getApi()->partner_order_reset($model->f1);
        return true;
    }
    
    public function becomePartner()
    {
        $data = array(
            'agree'=> true,
        );
        $this->_getApi()->partner_signup($data);
    }
    
    public function licenseDetails($model)
    {
        $data = array(
            'order_id'=> $model->oid,
        );
        try {
            return $this->_getApi()->partner_order_get($data);
        } catch(\Exception $e) {
            error_log($e->getMessage());
        }
        return array();
    }
    
    public function licenseReset($model)
    {
        $data = array(
            'order_id'=> $model->oid,
        );
        return $this->_getApi()->partner_order_reset($data);
    }
    
    public function testApiConnection()
    {
        try {
            $this->_getApi()->client_get();
        } catch (\Exception $e) {
            error_log($e);
            throw new \Exception('Unable to connect to BoxBilling API. Make sure API key is valid.');
        }
        return true;
    }
    
    private function _getApi()
    {
        $config = $this->getModuleConfig();
        return $this->di['service_boxbilling']($config);
    }

    public function toApiArray($model, $deep = false, $identity = null)
    {
        return array(
            'id'            =>  $model->id,
            'oid'           => $model->oid,
        );
    }
}