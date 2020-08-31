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
class Service implements \Box\InjectionAwareInterface
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

    public $mod = null;

    public function __construct()
    {
        $this->mod = new \Box_Mod('serviceyouhosting');
    }

    public function getCartProductTitle($product, array $data)
    {
        if ($data['domain_type'] == 'subdomain') {
            if (!isset($data['subdomain']) || empty($data['subdomain'])) {
                throw new \Box_Exception('Please enter subdomain', null, 7102);
            }

            if (!isset($data['master_domain']) || empty($data['master_domain'])) {
                throw new \Box_Exception('Please select master domain', null, 7103);
            }

            $domain = $data['subdomain'] . '.' . $data['master_domain'];
        }

        if ($data['domain_type'] == 'domain') {
            if (!isset($data['domain'])) {
                throw new \Box_Exception('Please enter domain value', null, 7104);
            }

            $domain = $data['domain'];
        }

        return __(':product for :domain', array(':domain' => $domain, ':product' => $product['title']));
    }

    public function validateOrderData(array &$data)
    {
        if (!isset($data['domain_type']) || empty($data['domain_type'])) {
            throw new \Box_Exception('Unknown domain type.', null, 7101);
        }

        if ($data['domain_type'] == 'subdomain') {
            if (!isset($data['subdomain']) || empty($data['subdomain'])) {
                throw new \Box_Exception('Please enter subdomain', null, 7102);
            }

            if (!isset($data['master_domain']) || empty($data['master_domain'])) {
                throw new \Box_Exception('Please select master domain', null, 7103);
            }

            $data['master_domain'] = strtolower($data['master_domain']);
            $data['subdomain']     = strtolower($data['subdomain']);
            $domain                = $data['master_domain'];
            $full_domain           = $data['subdomain'] . '.' . $data['master_domain'];
        }

        if ($data['domain_type'] == 'domain') {
            if (!isset($data['domain']) || empty($data['domain'])) {
                throw new \Box_Exception('Please enter domain value', null, 7104);
            }

            $domain      = strtolower($data['domain']);
            $full_domain = $domain;
        }

        if (!isset($data['skip_domain_check'])) {
            $check_data = array(
                'type'      => $data['domain_type'],
                'domain'    => $domain,
                'subdomain' => $this->di['array_get']($data, 'subdomain', null),
            );
            $this->getApi()->call('Account.check', $check_data);
        }

        $data['full_domain'] = $full_domain;
    }

    public function activate($order)
    {
        $order        = $this->di['db']->getExistingModelById('ClientOrder', $order->id, 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $o            = $orderService->toApiArray($order);

        $yh_client_id = $this->getYouhostingClientId($o['client_id']);
        if (!$yh_client_id) {
            throw new \Exception('Client must confirm his account before activating order');
        }

        if (!isset($o['meta']['order_captcha_id'])) {
            throw new \Exception('Captcha must be solved by client before activating YouHosting account');
        }

        $product        = $this->di['db']->getExistingModelById('Product', $order->product_id, 'Product not found');
        $productService = $this->di['mod_service']('product');
        $product        = $productService->toApiArray($product);


        $password = $this->di['tools']->generatePassword();
        $params   = array(
            'client_id'  => $yh_client_id,
            'captcha_id' => $o['meta']['order_captcha_id'],
            'plan_id'    => $product['config']['plan_id'],
            'type'       => $o['config']['domain_type'],
            'domain'     => ($o['config']['domain_type'] == 'subdomain') ? $o['config']['master_domain'] : $o['config']['domain'],
            'subdomain'  => isset($o['config']['subdomain']) ? $o['config']['subdomain'] : null,
            'password'   => $password,
        );

        $account = $this->getApi()->call('Account.create', $params);

        $orderService = $this->di['mod_service']('order');
        $orderService->updateOrder($o, array('id' => $order->id, 'meta' => array('yh_account_id' => $account['id'], 'password' => $password)));

        return array(
            'account' => $account,
            'ns'      => $this->getApi()->call('Settings.nameservers'),
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
        $order        = $this->di['db']->getExistingModelById('ClientOrder', $order->id, 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $orderArr     = $orderService->toApiArray($order);

        $params = array('id' => $orderArr['meta']['yh_account_id']);

        try {
            $this->getApi()->call('Account.suspend', $params);
        } catch (\Exception $e) {
            //6401 = Account not found
            if ($e->getCode() == 6401) {
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
        $order        = $this->di['db']->getExistingModelById('ClientOrder', $order['id'], 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $orderArr     = $orderService->toApiArray($order);

        $params = array('id' => $orderArr['meta']['yh_account_id']);
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
            $o = $this->di['db']->getExistingModelById('ClientOrder', $order['id'], 'Order not found');

            $params = array('id' => $o['meta']['yh_account_id']);
            $this->getApi()->call('Account.delete', $params);
        } catch (\Exception $e) {
            error_log($e);
        }

        return true;
    }

    public function sync($order_id)
    {
        $order    = $this->di['db']->getExistingModelById('ClientOrder', $order_id, 'Order not found');
        $orderArr = $this->di['mod_service']('order')->toApiArray($order);

        $account = $this->getApi()->call('Account.get', array('id' => $orderArr['meta']['yh_account_id']));

        $orderService = $this->di['mod_service']('order');
        $orderService->updateOrder($order, array(
            'id'         => $order_id,
            'status'     => $account['status'],
            'created_at' => $account['created_at']
        ));
    }

    public function getLastClientAccountImportPage($default = 1)
    {
        $value = $this->di['db']->getCell("
        SELECT meta_value
        FROM extension_meta
        WHERE
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'last_account_page'
        ");

        if ($value) {
            return $value;
        }

        return $default;
    }

    public function setLastClientAccountImportPage($page)
    {
        $meta = $this->di['db']->findOne('extension_meta', "
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'last_account_page'
        ");

        if (!$meta) {
            $meta             = $this->di['db']->dispense('extension_meta');
            $meta->extension  = 'mod_serviceyouhosting';
            $meta->meta_key   = 'last_account_page';
            $meta->created_at = date('Y-m-d H:i:s');
        }

        $meta->meta_value = $page;
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);
    }

    public function getLastClientImportPage($default = 1)
    {
        $value = $this->di['db']->getCell("
        SELECT meta_value
        FROM extension_meta
        WHERE
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'last_client_page'
        ");

        if ($value) {
            return $value;
        }

        return $default;
    }

    public function setLastClientImportPage($page)
    {
        $meta = $this->di['db']->findOne('extension_meta', "
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'last_client_page'
        ");

        if (!$meta) {
            $meta             = $this->di['db']->dispense('extension_meta');
            $meta->extension  = 'mod_serviceyouhosting';
            $meta->meta_key   = 'last_client_page';
            $meta->created_at = date('Y-m-d H:i:s');
        }

        $meta->meta_value = $page;
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);
    }

    public function storeYouhostingClientId($client_id, $yh_client_id)
    {
        //remove all null values if present
        $this->di['db']->exec("
            DELETE FROM extension_meta
            WHERE extension = 'mod_serviceyouhosting'
            AND meta_key = 'yh_client_id'
            AND client_id = :client_id",
            array('client_id'=>$client_id)
        );

        $meta             = $this->di['db']->dispense('extension_meta');
        $meta->extension  = 'mod_serviceyouhosting';
        $meta->client_id  = $client_id;
        $meta->meta_key   = 'yh_client_id';
        $meta->meta_value = $yh_client_id;
        $meta->created_at = date('Y-m-d H:i:s');
        $meta->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($meta);
    }

    public function isYHAccountImported($yh_account_id)
    {
        $id = $this->di['db']->getCell("
            SELECT client_order_id
            FROM client_order_meta
            WHERE name = 'yh_account_id'
            AND value = :yh_account_id
        ", array('yh_account_id' => $yh_account_id));

        if ($id) {
            return $id;
        }

        return null;
    }

    public function isYHClientBBClient($yh_client_id)
    {
        $id = $this->di['db']->getCell("
        SELECT client_id
        FROM extension_meta
        WHERE
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'yh_client_id'
            AND meta_value = :yh_client_id",
            array('yh_client_id'=>$yh_client_id));

        if ($id) {
            return $id;
        }

        return null;
    }

    public function getYouhostingClientId($client_id)
    {

        $id = $this->di['db']->getCell("
        SELECT meta_value
        FROM extension_meta
        WHERE
            extension = 'mod_serviceyouhosting'
            AND meta_key = 'yh_client_id'
            AND client_id = :id",
        array('id'=>$client_id));

        if ($id) {
            return $id;
        }

        return null;
    }

    public function getOrCreateProductForHostingPlan($plan_id)
    {
        $id = $this->di['db']->getCell("
            SELECT id
            FROM  `product`
            WHERE type = 'youhosting'
            AND `config` LIKE :config
            LIMIT 1",
            array('config' => '%' . $plan_id . '%')
        );
        if ($id) {
            return $id;
        }

        $title = 'Youhosting ' . $plan_id;
        $type  = 'youhosting';

        $productService = $this->di['mod_service']('product');

        $id = $productService->createProduct($title, $type);


        $product = $this->di['db']->getExistingModelById('Product', $id, 'Product not found');
        $productService->updateProduct($product, array(
            'config' => array('plan_id' => $plan_id,)
        ));

        return $id;
    }

    public function importYouhostingClient($yh_client_data)
    {
        if ($this->isYHClientBBClient($yh_client_data['id'])) {
            throw new \Exception('Skipping already imported client #' . $yh_client_data['id']);
        }

        $cdata = array(
            'aid'        => $yh_client_data['id'],
            'email'      => $yh_client_data['email'],
            'first_name' => $yh_client_data['first_name'],
            'last_name'  => $yh_client_data['last_name'],
            'company'    => $yh_client_data['company'],
            'address_1'  => $yh_client_data['address_1'],
            'address_2'  => $yh_client_data['address_2'],
            'city'       => $yh_client_data['city'],
            'country'    => $yh_client_data['country'],
            'state'      => $yh_client_data['state'],
            'postcode'   => $yh_client_data['zip'],
            'phone'      => $yh_client_data['phone'],
            'phone_cc'   => $yh_client_data['phone_cc'],
            'created_at' => $yh_client_data['created_at'],
        );

        $clientService = $this->di['mod_service']('client');

        $validator = $this->di['validator'];
        $validator->isEmailValid($yh_client_data['email']);

        if ($clientService->emailAreadyRegistered($yh_client_data['email'])) {
            throw new \Box_Exception('Email is already registered.');
        }

        $cid = $clientService->adminCreateClient($cdata);

        $this->storeYouhostingClientId($cid, $yh_client_data['id']);

        return $cid;
    }

    public function importYouhostingAccount($account)
    {
        if ($this->isYHAccountImported($account['id'])) {
            return false;
        }

        $client_id = $this->isYHClientBBClient($account['client_id']);
        if (!$client_id) {
            $yhc = $this->getApi()->call('Client.getList', array('page' => 1, 'per_page' => 1, 'id' => $account['client_id']));

            if ($yhc['total'] == 1) {
                $c = $yhc['list'][0];
                try {
                    $client_id = $this->importYouhostingClient($c);
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
            }
        }

        $product_id = $this->getOrCreateProductForHostingPlan($account['plan_id']);
        $period     = $this->di['array_get']($periods, $account['period']);

        $odata = array(
            'client_id'      => $client_id,
            'product_id'     => $product_id,
            'title'          => $account['domain'],
            'period'         => $period,
            'activate'       => false,
            'invoice_option' => 'no-invoice',
            'created_at'     => $account['created_at'],
            'meta'           => array(
                'yh_account_id' => $account['id'],
            ),
            'config'         => array(
                'skip_domain_check' => true,
                'domain_type'       => 'domain',
                'domain'            => $account['domain'],
            ),
        );

        $client  = $this->di['db']->getExistingModelById('Client', $odata['client_id'], 'Client not found');
        $product = $this->di['db']->getExistingModelById('Product', $odata['product_id'], 'Product not found');

        $orderService = $this->di['mod_service']('order');
        $id           = $orderService->createOrder($client, $product, $odata);

        $order = $this->di['db']->getExistingModelById('ClientOrder', $id, 'Order not found');

        $orderService->updateOrder($order, array('status' => 'active'));

        return $id;
    }

    public function getApi()
    {
        if (!$this->di['license']->isPro()) {
            throw new \Exception('YouHosting module can only be used by PRO license owners. Get PRO license key at http://www.boxbilling.com/order', 509);
        }

        $config = $this->di['mod_config']('serviceyouhosting');
        require_once 'Youhosting.php';
        $a = array(
            'api_key'    => $config['api_key'],
            'boxbilling' => array('license' => $this->di['config']['license']),
        );

        return new Youhosting_Api($a);
    }
}
