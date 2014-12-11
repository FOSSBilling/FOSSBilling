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

/**
 * Youhosting service management
 */
namespace Box\Mod\Serviceyouhosting\Api;

class Client extends \Api_Abstract
{
    public function is_yh_client()
    {
        $id = $this->getService()->getYouhostingClientId($this->_identity->id);
        return (bool)$id;
    }

    /**
     * Get order info
     *
     * @return bool
     */
    public function info($data)
    {
        if(!isset($data['order_id'])) {
            throw new Exception('order_id is missing');
        }

        $id = $this->getService()->getYouhostingClientId($this->_identity->id);
        $result = array(
            'is_youhosting_client' => (bool)$id,
        );

        $order = $this->getApiClient()->order_get(array('id'=>$data['order_id']));

        if(isset($order['meta']['yh_account_id']) && $order['meta']['yh_account_id']) {
            $info = $this->getService()->getApi()->call('Account.get', array('id'=>$order['meta']['yh_account_id']));
            $result['account'] = $info;
            $result['ns'] = $this->getService()->getApi()->call('Settings.nameservers');
        }

        return $result;
    }

    /**
     * Get captcha information
     *
     * @return mixed
     */
    public function captcha()
    {
        return $this->getService()->getApi()->call('Captcha.generate');
    }

    /**
     * Signup as youhosting client
     *
     * @param $data
     * @return bool
     * @throws Exception
     */
    public function signup($data)
    {
        if(!isset($data['captcha_id'])) {
            throw new Exception('Captcha id is missing');
        }

        if(!isset($data['captcha_solution'])) {
            throw new Exception('captcha_solution is missing');
        }

        $cp = array(
            'id'        =>  $data['captcha_id'],
            'solution'  =>  $data['captcha_solution'],
        );

        $this->getService()->getApi()->call('Captcha.verify', $cp);

        $client = $this->getIdentity();

        $params = array(
            'first_name'    =>  $client->first_name,
            'last_name'     =>  $client->last_name,
            'email'         =>  $client->email,
            'password'      =>  Box_Tools::generatePassword(),
            'captcha_id'    =>  $data['captcha_id'],
        );

        $id = null;

        try {
            $result = $this->getService()->getApi()->call('Client.create', $params);
            $id = $result['id'];

        } catch(\Exception $e) {
            if($e->getCode() == 5108) {

                $yh_clients = $this->getService()->getApi()->call('Client.getList', array('email' =>  $client->email));
                if($yh_clients['total']) {
                    $id =$yh_clients['list'][0]['id'];
                } else {
                    error_log($e);
                }
            } else {
                throw $e;
            }
        }

        $this->getService()->storeYouhostingClientId($this->_identity->id, $id);

        return true;
    }

    /**
     * Get cpanel login url
     *
     * @param $data
     * @return mixed
     * @throws Exception
     */
    public function cpanel_url($data)
    {
        if(!isset($data['order_id'])) {
            throw new Exception('order_id is missing');
        }

        $order = $this->getApiClient()->order_get(array('id'=>$data['order_id']));
        $params = array(
            'id'  =>  $order['meta']['yh_account_id'],
        );
        return $this->getService()->getApi()->call('Account.getLoginUrl', $params);
    }

    /**
     * Activate order
     *
     * @param int $captcha_id - captcha id
     * @param int $order_id - order id
     * @param string $captcha_solution - captcha solution
     *
     * @return bool
     * @throws Exception
     */
    public function activate($data)
    {
        if(!isset($data['captcha_id'])) {
            throw new Exception('Captcha id is missing');
        }

        if(!isset($data['order_id'])) {
            throw new Exception('order_id is missing');
        }

        if(!isset($data['captcha_solution'])) {
            throw new Exception('captcha_solution is missing');
        }

        $cp = array(
            'id'        =>  $data['captcha_id'],
            'solution'  =>  $data['captcha_solution'],
        );
        $this->getService()->getApi()->call('Captcha.verify', $cp);

        $this->getApiAdmin()->order_update(array('id'=>$data['order_id'], 'meta'=>array('order_captcha_id'=>$data['captcha_id'])));
        $this->getApiAdmin()->order_activate(array('id'=>$data['order_id']));
        return true;
    }
}
