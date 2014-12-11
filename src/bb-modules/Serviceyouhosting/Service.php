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

namespace Box\Mod\Serviceyouhosting;
class Service
{
    public $mod = null;
    public $api_admin = null;

    public function __construct()
    {
        $this->mod = new \Box_Mod('serviceyouhosting');

        $mod_api = new \Box_Mod('api');
        $this->api_admin = $mod_api->getService()->getApiAdmin();
    }

    public function getCartProductTitle($product, array $data)
    {
        if($data['domain_type'] == 'subdomain') {
            if(!isset($data['subdomain']) || empty($data['subdomain'])) {
                throw new \Box_Exception('Please enter subdomain', null, 7102);
            }

            if(!isset($data['master_domain']) || empty($data['master_domain'])) {
                throw new \Box_Exception('Please select master domain', null, 7103);
            }

            $domain = $data['subdomain'].'.'.$data['master_domain'];
        }

        if($data['domain_type'] == 'domain') {
            if(!isset($data['domain'])) {
                throw new \Box_Exception('Please enter domain value', null, 7104);
            }

            $domain = $data['domain'];
        }

        return __(':product for :domain', array(':domain'=>$domain, ':product'=>$product['title']));
    }

    public function validateOrderData(array &$data)
    {
        if(!isset($data['domain_type']) || empty($data['domain_type'])) {
            throw new \Box_Exception('Unknown domain type.', null, 7101);
        }

        if($data['domain_type'] == 'subdomain') {
            if(!isset($data['subdomain']) || empty($data['subdomain'])) {
                throw new \Box_Exception('Please enter subdomain', null, 7102);
            }

            if(!isset($data['master_domain']) || empty($data['master_domain'])) {
                throw new \Box_Exception('Please select master domain', null, 7103);
            }

            $data['master_domain'] = strtolower($data['master_domain']);
            $data['subdomain'] = strtolower($data['subdomain']);
            $domain = $data['master_domain'];
            $full_domain = $data['subdomain'].'.'.$data['master_domain'];
        }

        if($data['domain_type'] == 'domain') {
            if(!isset($data['domain']) || empty($data['domain'])) {
                throw new \Box_Exception('Please enter domain value', null, 7104);
            }

            $domain = strtolower($data['domain']);
            $full_domain = $domain;
        }

        if(!isset($data['skip_domain_check'])) {
            $check_data = array(
                'type'          =>  $data['domain_type'],
                'domain'        =>  $domain,
                'subdomain'     =>  isset($data['subdomain']) ? $data['subdomain'] : null,
            );
            $this->getApi()->call('Account.check', $check_data);
        }

        $data['full_domain'] = $full_domain;
    }

    public function activate($order)
    {
        $o = $this->api_admin->order_get(array('id'=>$order['id']));

        $yh_client_id = $this->getYouhostingClientId($o['client_id']);
        if(!$yh_client_id) {
            throw new Exception('Client must confirm his account before activating order');
        }

        if(!isset($o['meta']['order_captcha_id'])) {
            throw new Exception('Captcha must be solved by client before activating YouHosting account');
        }

        $product = $this->api_admin->product_get(array('id'=>$order['product_id']));

        $password = Box_Tools::generatePassword();
        $params = array(
            'client_id'     =>  $yh_client_id,
            'captcha_id'    =>  $o['meta']['order_captcha_id'],
            'plan_id'       =>  $product['config']['plan_id'],
            'type'          =>  $o['config']['domain_type'],
            'domain'        =>  ($o['config']['domain_type'] == 'subdomain') ? $o['config']['master_domain'] : $o['config']['domain'],
            'subdomain'     =>  isset($o['config']['subdomain']) ? $o['config']['subdomain'] : null,
            'password'      =>  $password,
        );

        $account = $this->getApi()->call('Account.create', $params);
        $this->api_admin->order_update(array('id'=>$order['id'], 'meta'=>array('yh_account_id'=>$account['id'], 'password'=>$password)));

        return array(
            'account'       =>  $account,
            'ns'            =>  $this->getApi()->call('Settings.nameservers'),
        );
    }

    /**
     * Suspend service
     *
     * @param $order
     * @return boolean
     */
    public function suspend($order)
    {
        $o = $this->api_admin->order_get(array('id'=>$order['id']));
        $params = array('id'=>$o['meta']['yh_account_id']);

        try {
            $this->getApi()->call('Account.suspend', $params);
        } catch(\Exception $e) {
            //6401 = Account not found
            if($e->getCode() == 6401) {
                return true;
            }
            throw $e;
        }

        return true;
    }

    /**
     * @param $order
     * @return boolean
     */
    public function unsuspend($order)
    {
        $o = $this->api_admin->order_get(array('id'=>$order['id']));
        $params = array('id'=>$o['meta']['yh_account_id']);
        $this->getApi()->call('Account.unsuspend', $params);

        return true;
    }

    /**
     * @param $order
     * @return boolean
     */
    public function cancel($order)
    {
        return $this->suspend($order);
    }

    /**
     *
     * @param $order
     * @return boolean
     */
    public function uncancel($order)
    {
        return $this->unsuspend($order);
    }

    /**
     * @param $order
     * @return boolean
     */
    public function delete($order)
    {
        try {
            $o = $this->api_admin->order_get(array('id'=>$order['id']));
            $params = array('id'=>$o['meta']['yh_account_id']);
            $this->getApi()->call('Account.delete', $params);
        } catch(\Exception $e) {
            error_log($e);
        }

        return true;
    }

    public function sync($order_id)
    {
        $order = $this->api_admin->order_get(array('id'=>$order_id));
        $account = $this->getApi()->call('Account.get', array('id'=>$order['meta']['yh_account_id']));

        $this->api_admin->order_update(array(
            'id'            => $order_id,
            'status'        => $account['status'],
            'created_at'    => $account['created_at']
        ));

    }

    public function getLastClientAccountImportPage($default = 1)
    {
        $value = R::getCell("
        SELECT meta_value
        FROM extension_meta
        WHERE
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'last_account_page'
        ");

        if($value) {
            return $value;
        }

        return $default;
    }

    public function setLastClientAccountImportPage($page)
    {
        $meta = R::findOne('extension_meta', "
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'last_account_page'
        ");

        if(!$meta) {
            $meta = R::dispense('extension_meta');
            $meta->extension = 'mod_serviceyouhosting';
            $meta->meta_key = 'last_account_page';
            $meta->created_at = date('c');
        }

        $meta->meta_value = $page;
        $meta->updated_at = date('c');
        R::store($meta);
    }

    public function getLastClientImportPage($default = 1)
    {
        $value = R::getCell("
        SELECT meta_value
        FROM extension_meta
        WHERE
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'last_client_page'
        ");

        if($value) {
            return $value;
        }

        return $default;
    }

    public function setLastClientImportPage($page)
    {
        $meta = R::findOne('extension_meta', "
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'last_client_page'
        ");

        if(!$meta) {
            $meta = R::dispense('extension_meta');
            $meta->extension = 'mod_serviceyouhosting';
            $meta->meta_key = 'last_client_page';
            $meta->created_at = date('c');
        }

        $meta->meta_value = $page;
        $meta->updated_at = date('c');
        R::store($meta);
    }

    public function storeYouhostingClientId($client_id, $yh_client_id)
    {
        //remove all null values if present
        R::exec("
            DELETE FROM extension_meta
            WHERE extension = 'mod_serviceyouhosting'
            AND meta_key = 'yh_client_id'
            AND client_id = :client_id",
            array('client_id'=>$client_id)
        );

        $meta = R::dispense('extension_meta');
        $meta->extension = 'mod_serviceyouhosting';
        $meta->client_id = $client_id;
        $meta->meta_key = 'yh_client_id';
        $meta->meta_value = $yh_client_id;
        $meta->created_at = date('c');
        $meta->updated_at = date('c');
        R::store($meta);
    }

    public function isYHAccountImported($yh_account_id)
    {
        $id = R::getCell("
            SELECT client_order_id
            FROM client_order_meta
            WHERE name = 'yh_account_id'
            AND value = :yh_account_id
        ", array('yh_account_id'=>$yh_account_id));

        if($id) {
            return $id;
        }

        return null;
    }

    public function isYHClientBBClient($yh_client_id)
    {
        $id = R::getCell("
        SELECT client_id
        FROM extension_meta
        WHERE
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'yh_client_id'
            AND meta_value = :yh_client_id",
            array('yh_client_id'=>$yh_client_id));

        if($id) {
            return $id;
        }

        return null;
    }

    public function getYouhostingClientId($client_id)
    {

        $id = R::getCell("
        SELECT meta_value
        FROM extension_meta
        WHERE
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'yh_client_id'
            AND client_id = :id",
        array('id'=>$client_id));

        if($id) {
            return $id;
        }

        return null;
    }

    public function getOrCreateProductForHostingPlan($plan_id)
    {
        $id = R::getCell("
            SELECT id
            FROM  `product`
            WHERE type = 'youhosting'
            AND `config` LIKE :config
            LIMIT 1",
            array('config'=>'%'.$plan_id.'%'));
        if($id) {
            return $id;
        }

        $data = array(
            'title'                 => 'Youhosting '.$plan_id,
            'type'                  => 'youhosting',
        );
        $id = $this->api_admin->product_prepare($data);

        $data = array(
            'id'  =>  $id,
            'config'  =>  array(
                'plan_id'   =>  $plan_id,
            ),
        );
        $this->api_admin->product_update($data);

        return $id;
    }

    public function importYouhostingClient($yh_client_data, $api_admin)
    {
        if($this->isYHClientBBClient($yh_client_data['id'])) {
            throw new Exception('Skipping already imported client #'.$yh_client_data['id']);
        }

        $cdata = array(
            'aid'           =>  $yh_client_data['id'],
            'email'         =>  $yh_client_data['email'],
            'first_name'    =>  $yh_client_data['first_name'],
            'last_name'     =>  $yh_client_data['last_name'],
            'company'     =>  $yh_client_data['company'],
            'address_1'     =>  $yh_client_data['address_1'],
            'address_2'     =>  $yh_client_data['address_2'],
            'city'     =>  $yh_client_data['city'],
            'country'     =>  $yh_client_data['country'],
            'state'     =>  $yh_client_data['state'],
            'postcode'     =>  $yh_client_data['zip'],
            'phone'     =>  $yh_client_data['phone'],
            'phone_cc'     =>  $yh_client_data['phone_cc'],
            'created_at'     =>  $yh_client_data['created_at'],
        );

        $cid = $api_admin->client_create($cdata);
        $this->storeYouhostingClientId($cid, $yh_client_data['id']);
        return $cid;
    }

    public function importYouhostingAccount($account, $api_admin)
    {
        if($this->isYHAccountImported($account['id'])) {
            return false;
        }

        $client_id = $this->isYHClientBBClient($account['client_id']);
        if(!$client_id) {
            $yhc = $this->getApi()->call('Client.getList', array('page'=>1, 'per_page'=>1, 'id'=>$account['client_id']));

            if($yhc['total']==1) {
                $c = $yhc['list'][0];
                try {
                    $client_id = $this->importYouhostingClient($c, $api_admin);
                } catch(\Exception $e) {
                    error_log($e->getMessage());
                }
            }
        }

        $product_id = $this->getOrCreateProductForHostingPlan($account['plan_id']);
        $period = isset($periods[$account['period']]) ? $periods[$account['period']] : null;

        $odata = array(
            'client_id'     => $client_id,
            'product_id'    => $product_id,
            'title'         => $account['domain'],
            'period'        => $period,
            'activate'      => false,
            'invoice_option'=> 'no-invoice',
            'created_at'    => $account['created_at'],
            'meta'=> array(
                'yh_account_id' =>  $account['id'],
            ),
            'config'=> array(
                'skip_domain_check'     =>  true,
                'domain_type'           =>  'domain',
                'domain'                =>  $account['domain'],
            ),
        );

        $id = $api_admin->order_create($odata);
        $api_admin->order_update(array('id'=>$id, 'status'=>'active'));
        return $id;
    }

    public function getApi()
    {
        if(!$this->di['license']->isPro()) {
            throw new Exception('YouHosting module can only be used by PRO license owners. Get PRO license key at http://www.boxbilling.com/order', 509);
        }

        $config = $this->mod->getConfig();
        require_once 'Youhosting.php';
        $a = array(
            'api_key'   =>  $config['api_key'],
            'boxbilling'=>  array('license'=>BB_LICENSE),
        );
        return new Youhosting_Api($a);
    }
}
