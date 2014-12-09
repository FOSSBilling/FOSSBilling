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

class Guest extends \Api_Abstract
{
    /**
     * Youhosting webhooks listener
     *
     * @return bool
     */
    public function webhook($data)
    {
        $this->di['logger']->info('YouHosting WebHook: ' . print_r($data, 1));

        $config = $this->_mod->getConfig();
        if(isset($config['yh_ips'])) {
            $allowed_ips = explode(PHP_EOL, $config['yh_ips']);
            array_walk($allowed_ips, create_function('&$val', '$val = trim($val);'));
        } else {
            $allowed_ips = array(
                '212.1.209.2',
            );
        }

        if(!in_array($this->_ip, $allowed_ips)) {
            throw new Exception('IP not allowed');
        }

        if(!isset($data['yh_event'])) {
            throw new Exception('Event name is missing');
        }

        $account_id = isset($data['yh_account_id']) ? $data['yh_account_id'] : null;
        $client_id = isset($data['yh_client_id']) ? $data['yh_client_id'] : null;

        $service = $this->getService();
        $api_admin = $this->getApiAdmin();

        switch ($data['yh_event']) {
            case 'CREATE_ACCOUNT':
                $bb_order_id = $service->isYHAccountImported($account_id);
                if(!$bb_order_id) {
                    $account = $service->getApi()->call('Account.get', array('id'=>$account_id));
                    $service->importYouhostingAccount($account, $api_admin);

                    $this->di['logger']->info('Imported YouHosting account. Invoked by WebHook');
                }

                break;

            case 'SUSPEND_ACCOUNT':
                $bb_order_id = $service->isYHAccountImported($account_id);
                $order = $api_admin->order_get(array('id'=>$bb_order_id));
                if($order['status'] == 'active') {
                    $api_admin->order_suspend(array('id'=>$bb_order_id, 'reason'=>'Auto-Suspend'));
                    $this->di['logger']->info('Suspended YouHosting account. Invoked by WebHook');
                }
                break;

            case 'UNSUSPEND_ACCOUNT':
                $bb_order_id = $service->isYHAccountImported($account_id);
                $order = $api_admin->order_get(array('id'=>$bb_order_id));
                if($order['status'] == 'suspended') {
                    $api_admin->order_unsuspend(array('id'=>$bb_order_id));
                    $this->di['logger']->info('Unsuspended YouHosting account. Invoked by WebHook');
                }
                break;

            case 'REMOVE_ACCOUNT':
                $bb_order_id = $service->isYHAccountImported($account_id);
                $order = $api_admin->order_get(array('id'=>$bb_order_id));
                if($order['status'] == 'active') {
                    $api_admin->order_cancel(array('id'=>$bb_order_id, 'reason'=>'Auto-Suspend'));
                    $this->di['logger']->info('Canceled YouHosting account. Invoked by WebHook');
                }
                break;

            default:
                break;
        }

        return true;
    }

    /**
     * Return master_domains
     *
     * @return array
     */
    public function master_domains($data)
    {
        $key = 'settings_subdomains';

        $cache = new FileCache();
        if($cache->exists($key)) {
            return $cache->get($key);
        }

        try {
            $result = $this->getService()->getApi()->call('Settings.subdomains');
            $cache->set($key, $result);
        } catch(\Exception $e) {
            error_log($e->getMessage());
            $result = array();
        }
        return $result;
    }
}