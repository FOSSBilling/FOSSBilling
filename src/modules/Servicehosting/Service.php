<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting;

use FOSSBilling\Exception;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;

class Service implements InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getCartProductTitle($product, array $data)
    {
        try {
            [$sld, $tld] = $this->_getDomainTuple($data);

            return __trans(':hosting for :domain', [':hosting' => $product->title, ':domain' => $sld . $tld]);
        } catch (\Exception $e) {
            // should never occur, but in case
            error_log($e->getMessage());
        }

        return $product->title;
    }

    public function validateOrderData(array &$data): void
    {
        if (!isset($data['server_id'])) {
            throw new InformationException('Hosting product is not configured completely. Configure server for hosting product.', null, 701);
        }
        if (!isset($data['hosting_plan_id'])) {
            throw new InformationException('Hosting product is not configured completely. Configure hosting plan for hosting product.', null, 702);
        }
        if (!isset($data['sld']) || empty($data['sld'])) {
            throw new InformationException('Domain name is invalid.', null, 703);
        }
        if (!isset($data['tld']) || empty($data['tld'])) {
            throw new InformationException('Domain extension is invalid.', null, 704);
        }
    }

    /**
     * @throws InformationException
     *
     * @todo
     */
    public function action_create(\Model_ClientOrder $order): \Model_ServiceHosting
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $this->validateOrderData($c);

        $server = $this->di['db']->getExistingModelById('ServiceHostingServer', $c['server_id'], 'Server from order configuration was not found');

        $hp = $this->di['db']->getExistingModelById('ServiceHostingHp', $c['hosting_plan_id'], 'Hosting plan from order configuration was not found');

        $model = $this->di['db']->dispense('ServiceHosting');
        $model->client_id = $order->client_id;
        $model->service_hosting_server_id = $server->id;
        $model->service_hosting_hp_id = $hp->id;
        $model->sld = $c['sld'];
        $model->tld = $c['tld'];
        $model->ip = $server->ip;
        $model->reseller = $c['reseller'] ?? false;
        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return $model;
    }

    /**
     * @throws Exception
     */
    public function action_activate(\Model_ClientOrder $order): array
    {
        // Retrieve the service associated with the order
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        // If the service is not found, throw an exception
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }

        // Retrieve the order's configuration
        $config = $orderService->getConfig($order);

        // Retrieve the server manager for the order
        $serverManager = $this->_getServerMangerForOrder($model);

        // Generate a password for the service
        $pass = $this->di['tools']->generatePassword($serverManager->getPasswordLength(), true);

        // If a password is already specified in the order's configuration, use that instead
        if (isset($config['password']) && !empty($config['password'])) {
            $pass = $config['password'];
        }

        // Generate a username for the service
        if (isset($config['username']) && !empty($config['username'])) {
            $username = $config['username'];
        } else {
            $username = $serverManager->generateUsername($model->sld . $model->tld);
        }

        // Update the service's username and password
        $model->username = $username;
        $model->pass = $pass;

        // If the order's configuration does not specify that the service should be imported, create an account for the service on the server
        if (!isset($config['import']) || !$config['import']) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->createAccount($account);
        }

        // Update the service's password to a placeholder value for security reasons
        $model->pass = '********';

        // Save the service
        $this->di['db']->store($model);

        // Return the username and password
        return [
            'username' => $username,
            'password' => $pass,
        ];
    }

    /**
     * @throws Exception
     *
     * @todo
     */
    public function action_renew(\Model_ClientOrder $order): bool
    {
        // move expiration period to future
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }
        // @todo ?

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_suspend(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }
        [$adapter, $account] = $this->_getAM($model);
        $adapter->suspendAccount($account);

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_unsuspend(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }
        [$adapter, $account] = $this->_getAM($model);
        $adapter->unsuspendAccount($account);

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_cancel(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }
        [$adapter, $account] = $this->_getAM($model);
        $adapter->cancelAccount($account);

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_uncancel(\Model_ClientOrder $order): bool
    {
        $this->action_create($order);
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        // Retrieve the server manager for the order
        $serverManager = $this->_getServerMangerForOrder($model);

        // As we replace the password internally with asterisks, generate a new password
        $pass = $this->di['tools']->generatePassword($serverManager->getPasswordLength(), true);
        $model->pass = $pass;

        // Retrieve the adapter and account, then create the account on the server
        [$adapter, $account] = $this->_getAM($model);
        $adapter->createAccount($account);

        // Update the service's password to a placeholder value for security reasons
        $model->pass = '********';

        // Save the service
        $this->di['db']->store($model);

        return true;
    }

    /**
     * @return void
     */
    public function action_delete(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if ($service instanceof \Model_ServiceHosting) {
            // cancel if not canceled
            if ($order->status != \Model_ClientOrder::STATUS_CANCELED) {
                $this->action_cancel($order);
            }
            $this->di['db']->trash($service);
        }
    }

    public function changeAccountPlan(\Model_ClientOrder $order, \Model_ServiceHosting $model, \Model_ServiceHostingHp $hp)
    {
        $model->service_hosting_hp_id = $hp->id;
        if ($this->_performOnService($order)) {
            $package = $this->getServerPackage($hp);
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountPackage($account, $package);
        }

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        $this->di['logger']->info('Changed hosting plan of account #%s', $model->id);

        return true;
    }

    public function changeAccountUsername(\Model_ClientOrder $order, \Model_ServiceHosting $model, $data)
    {
        if (!isset($data['username']) || empty($data['username'])) {
            throw new InformationException('Account username is missing or is invalid');
        }

        $u = strtolower($data['username']);

        if ($this->_performOnService($order)) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountUsername($account, $u);
        }

        $model->username = $u;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Changed hosting account %s username', $model->id);

        return true;
    }

    public function changeAccountIp(\Model_ClientOrder $order, \Model_ServiceHosting $model, $data)
    {
        if (!isset($data['ip']) || empty($data['ip'])) {
            throw new InformationException('Account IP address is missing or is invalid');
        }

        $ip = $data['ip'];

        if ($this->_performOnService($order)) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountIp($account, $ip);
        }

        $model->ip = $ip;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        $this->di['logger']->info('Changed hosting account %s ip', $model->id);

        return true;
    }

    public function changeAccountDomain(\Model_ClientOrder $order, \Model_ServiceHosting $model, $data)
    {
        if (
            !isset($data['tld']) || empty($data['tld'])
            || !isset($data['sld']) || empty($data['sld'])
        ) {
            throw new InformationException('Domain SLD or TLD is missing');
        }

        $sld = $data['sld'];
        $tld = $data['tld'];

        if ($this->_performOnService($order)) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountDomain($account, $sld . $tld);
        }

        $model->sld = $sld;
        $model->tld = $tld;
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        $this->di['logger']->info('Changed hosting account %s domain', $model->id);

        return true;
    }

    public function changeAccountPassword(\Model_ClientOrder $order, \Model_ServiceHosting $model, $data)
    {
        if (
            !isset($data['password']) || !isset($data['password_confirm'])
            || $data['password'] != $data['password_confirm']
        ) {
            throw new InformationException('Account password is missing or is invalid');
        }

        $newPassword = $data['password'];

        if ($this->_performOnService($order)) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountPassword($account, $newPassword);
        }

        $model->pass = '******';
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        $this->di['logger']->info('Changed hosting account %s password', $model->id);

        return true;
    }

    public function sync(\Model_ClientOrder $order, \Model_ServiceHosting $model)
    {
        [$adapter, $account] = $this->_getAM($model);
        $updated = $adapter->synchronizeAccount($account);

        if ($account->getUsername() != $updated->getUsername()) {
            $model->username = $updated->getUsername();
        }

        if ($account->getIp() != $updated->getIp()) {
            $model->ip = $updated->getIp();
        }

        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);
        $this->di['logger']->info('Synchronizing hosting account %s with server', $model->id);

        return true;
    }

    private function _getDomainOrderId(\Model_ServiceHosting $model)
    {
        $orderService = $this->di['mod_service']('order');
        $o = $orderService->getServiceOrder($model);
        if ($o instanceof \Model_ClientOrder) {
            $c = $orderService->getConfig($o);
            if (isset($c['domain']) && isset($c['domain']['action'])) {
                $action = $c['domain']['action'];
                if ($action == 'register' || $action == 'transfer') {
                    return $orderService->getRelatedOrderIdByType($o, 'domain');
                }
            }
        }

        return null;
    }

    private function _performOnService(\Model_ClientOrder $order): bool
    {
        // If the order matches any of the following status, we should prevent actions such as PW resets or username changes from being performed
        $badStatus = [
            \Model_ClientOrder::STATUS_FAILED_SETUP,
            \Model_ClientOrder::STATUS_PENDING_SETUP,
            \Model_ClientOrder::STATUS_SUSPENDED,
            \Model_ClientOrder::STATUS_CANCELED,
        ];

        return !in_array($order->status, $badStatus);
    }

    /**
     * @throws Exception
     */
    private function _getServerMangerForOrder($model)
    {
        $server = $this->di['db']->getExistingModelById('ServiceHostingServer', $model->service_hosting_server_id, 'Server not found');

        return $this->getServerManager($server);
    }

    public function _getAM(\Model_ServiceHosting $model, \Model_ServiceHostingHp $hp = null): array
    {
        if (!$hp instanceof \Model_ServiceHostingHp) {
            $hp = $this->di['db']->getExistingModelById('ServiceHostingHp', $model->service_hosting_hp_id, 'Hosting plan not found');
        }

        $server = $this->di['db']->getExistingModelById('ServiceHostingServer', $model->service_hosting_server_id, 'Server not found');
        $client = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');

        $hp_config = $hp->config;

        $server_client = new \Server_Client();
        $server_client
            ->setEmail($client->email)
            ->setFullName($client->getFullName())
            ->setCompany($client->company)
            ->setStreet($client->address_1)
            ->setZip($client->postcode)
            ->setCity($client->city)
            ->setState($client->state)
            ->setCountry($client->country)
            ->setTelephone($client->phone);

        $package = $this->getServerPackage($hp);
        $server_account = new \Server_Account();
        $server_account
            ->setClient($server_client)
            ->setPackage($package)
            ->setUsername($model->username)
            ->setReseller($model->reseller)
            ->setDomain($model->sld . $model->tld)
            ->setPassword($model->pass)
            ->setNs1($server->ns1)
            ->setNs2($server->ns2)
            ->setNs3($server->ns3)
            ->setNs4($server->ns4)
            ->setIp($model->ip);

        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);
        if ($order instanceof \Model_ClientOrder) {
            $adapter = $this->getServerManagerWithLog($server, $order);
        } else {
            $adapter = $this->getServerManager($server);
        }

        return [$adapter, $server_account];
    }

    public function toApiArray(\Model_ServiceHosting $model, $deep = false, $identity = null)
    {
        $serviceHostingServerModel = $this->di['db']->load('ServiceHostingServer', $model->service_hosting_server_id);
        $serviceHostingHpModel = $this->di['db']->load('ServiceHostingHp', $model->service_hosting_hp_id);
        $server = $this->toHostingServerApiArray($serviceHostingServerModel, $deep, $identity);
        $hp = $this->toHostingHpApiArray($serviceHostingHpModel, $deep, $identity);

        return [
            'ip' => $model->ip,
            'sld' => $model->sld,
            'tld' => $model->tld,
            'domain' => $model->sld . $model->tld,
            'username' => $model->username,
            'reseller' => $model->reseller,
            'server' => $server,
            'hosting_plan' => $hp,
            'domain_order_id' => $this->_getDomainOrderId($model),
        ];
    }

    public function toHostingServerApiArray(\Model_ServiceHostingServer $model, $deep = false, $identity = null): array
    {
        [$cpanel_url, $whm_url] = $this->getManagerUrls($model);
        $result = [
            'name' => $model->name,
            'hostname' => $model->hostname,
            'ip' => $model->ip,
            'ns1' => $model->ns1,
            'ns2' => $model->ns2,
            'ns3' => $model->ns3,
            'ns4' => $model->ns4,
            'cpanel_url' => $cpanel_url,
            'reseller_cpanel_url' => $whm_url,
        ];

        if ($identity instanceof \Model_Admin) {
            $result['id'] = $model->id;
            $result['active'] = $model->active;
            $result['secure'] = $model->secure;
            if (!is_null($model->assigned_ips)) {
                $result['assigned_ips'] = json_decode($model->assigned_ips, 1);
            } else {
                $result['assigned_ips'] = '';
            }
            $result['status_url'] = $model->status_url;
            $result['max_accounts'] = $model->max_accounts;
            $result['manager'] = $model->manager;
            if (!empty($model->config) && json_validate($model->config)) {
                $result['config'] = json_decode($model->config, true);
            } else {
                $result['config'] = [];
            }
            $result['username'] = $model->username;
            $result['password'] = $model->password;
            $result['accesshash'] = $model->accesshash;
            $result['port'] = $model->port;
            $result['passwordLength'] = $model->passwordLength;
            $result['created_at'] = $model->created_at;
            $result['updated_at'] = $model->updated_at;
        }

        return $result;
    }

    private function _getDomainTuple($data): array
    {
        $required = [
            'domain' => 'Hosting product must have domain configuration',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $required = [
            'action' => 'Domain action is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data['domain']);

        [$sld, $tld] = [null, null];

        if ($data['domain']['action'] == 'owndomain') {
            $sld = $data['domain']['owndomain_sld'];
            $tld = str_contains($data['domain']['owndomain_tld'], '.') ? $data['domain']['owndomain_tld'] : '.' . $data['domain']['owndomain_tld'];
        }

        if ($data['domain']['action'] == 'register') {
            $required = [
                'register_sld' => 'Hosting product must have defined register_sld parameter',
                'register_tld' => 'Hosting product must have defined register_tld parameter',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data['domain']);

            $sld = $data['domain']['register_sld'];
            $tld = $data['domain']['register_tld'];
        }

        if ($data['domain']['action'] == 'transfer') {
            $required = [
                'transfer_sld' => 'Hosting product must have defined transfer_sld parameter',
                'transfer_tld' => 'Hosting product must have defined transfer_tld parameter',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data['domain']);

            $sld = $data['domain']['transfer_sld'];
            $tld = $data['domain']['transfer_tld'];
        }

        return [$sld, $tld];
    }

    public function update(\Model_ServiceHosting $model, array $data): bool
    {
        if (isset($data['username']) && !empty($data['username'])) {
            $model->username = $data['username'];
        }

        if (isset($data['ip']) && !empty($data['ip'])) {
            $model->ip = $data['ip'];
        }

        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated hosting account %s without sending actions to server', $model->id);

        return true;
    }

    public function getServerManagers(): array
    {
        $serverManagers = [];

        foreach ($this->_getServerManagers() as $serverManager) {
            $serverManagers[$serverManager] = $this->getServerManagerConfig($serverManager);
        }

        return $serverManagers;
    }

    private function _getServerManagers(): array
    {
        $dir = PATH_LIBRARY . '/Server/Manager';
        $files = [];
        $directory = opendir($dir);
        while ($item = readdir($directory)) {
            if (($item != '.') && ($item != '..') && ($item != '.svn')) {
                $files[] = pathinfo($item, PATHINFO_FILENAME);
            }
        }
        sort($files);

        return $files;
    }

    public function getServerManagerConfig($manager)
    {
        $filename = PATH_LIBRARY . '/Server/Manager/' . $manager . '.php';
        if (!file_exists($filename)) {
            return [];
        }

        $classname = 'Server_Manager_' . $manager;
        $method = 'getForm';
        if (!is_callable($classname . '::' . $method)) {
            return [];
        }

        return call_user_func([$classname, $method]);
    }

    public function getServerPairs(): array
    {
        $sql = 'SELECT id, name
                FROM service_hosting_server
                ORDER BY id ASC';
        $rows = $this->di['db']->getAll($sql);

        $result = [];
        foreach ($rows as $record) {
            $result[$record['id']] = $record['name'];
        }

        return $result;
    }

    public function getServersSearchQuery($data): array
    {
        $sql = 'SELECT *
                FROM service_hosting_server
                order by id ASC';

        return [$sql, []];
    }

    public function createServer($name, $ip, $manager, $data)
    {
        $model = $this->di['db']->dispense('ServiceHostingServer');
        $model->name = $name;
        $model->ip = $ip;

        $model->hostname = $data['hostname'] ?? null;
        $model->assigned_ips = $data['assigned_ips'] ?? null;
        $model->active = $data['active'] ?? 1;
        $model->status_url = $data['status_url'] ?? null;
        $model->max_accounts = $data['max_accounts'] ?? null;

        $model->ns1 = $data['ns1'] ?? null;
        $model->ns2 = $data['ns2'] ?? null;
        $model->ns3 = $data['ns3'] ?? null;
        $model->ns4 = $data['ns4'] ?? null;

        $model->manager = $manager;
        $model->username = $data['username'] ?? null;
        $model->password = $data['password'] ?? null;
        $model->accesshash = $data['accesshash'] ?? null;
        $model->port = $data['port'] ?? null;
        $model->passwordLength = is_numeric($data['passwordLength']) ? intval($data['passwordLength']) : null;
        $model->secure = $data['secure'] ?? 0;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['logger']->info('Added new hosting server %s', $newId);

        return $newId;
    }

    public function deleteServer(\Model_ServiceHostingServer $model): bool
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted hosting server %s', $id);

        return true;
    }

    public function updateServer(\Model_ServiceHostingServer $model, array $data): bool
    {
        $model->name = $data['name'] ?? $model->name;
        $model->ip = $data['ip'] ?? $model->ip;
        $model->hostname = $data['hostname'] ?? $model->hostname;

        $assigned_ips = $data['assigned_ips'] ?? '';
        if (!empty($assigned_ips)) {
            $array = explode(PHP_EOL, $data['assigned_ips']);
            $array = array_map('trim', $array);
            $array = array_diff($array, ['']);
            $model->assigned_ips = json_encode($array);
        }

        $model->active = $data['active'] ?? $model->active;
        $model->status_url = $data['status_url'] ?? $model->status_url;
        $model->max_accounts = $data['max_accounts'] ?? $model->max_accounts;
        $model->ns1 = $data['ns1'] ?? $model->ns1;
        $model->ns2 = $data['ns2'] ?? $model->ns2;
        $model->ns3 = $data['ns3'] ?? $model->ns3;
        $model->ns4 = $data['ns4'] ?? $model->ns4;
        $model->manager = $data['manager'] ?? $model->manager;
        $model->port = is_numeric($data['port']) ? $data['port'] : $model->port;
        $model->config = json_encode($data['config']) ?? $model->config;
        $model->secure = $data['secure'] ?? $model->secure;
        $model->username = $data['username'] ?? $model->username;
        $model->password = $data['password'] ?? $model->password;
        $model->accesshash = $data['accesshash'] ?? $model->accesshash;
        $model->passwordLength = is_numeric($data['passwordLength']) ? $data['passwordLength'] : $model->passwordLength;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Update hosting server %s', $model->id);

        return true;
    }

    /**
     * @throws Exception
     */
    public function getServerManager(\Model_ServiceHostingServer $model)
    {
        if (empty($model->manager)) {
            throw new Exception('Invalid server manager. Server was not configured properly.', null, 654);
        }

        $config = [];
        $config['ip'] = $model->ip;
        $config['host'] = $model->hostname;
        $config['port'] = $model->port;
        $config['config'] = [];
        if (!empty($model->config) && json_validate($model->config)) {
            $config['config'] = json_decode($model->config, true);
        } else {
            $config['config'] = [];
        }
        $config['secure'] = $model->secure;
        $config['username'] = $model->username;
        $config['password'] = $model->password;
        $config['accesshash'] = $model->accesshash;
        $config['passwordLength'] = $model->passwordLength;

        $manager = $this->di['server_manager']($model->manager, $config);

        if (!$manager instanceof \Server_Manager) {
            throw new Exception('Server manager :adapter is invalid', [':adapter' => $model->manager]);
        }

        return $manager;
    }

    /**
     * @throws \Server_Exception
     * @throws Exception
     */
    public function testConnection(\Model_ServiceHostingServer $model)
    {
        $manager = $this->getServerManager($model);

        return $manager->testConnection();
    }

    public function getHpPairs(): array
    {
        $sql = 'SELECT id, name
                FROM service_hosting_hp';
        $rows = $this->di['db']->getAll($sql);
        $result = [];
        foreach ($rows as $record) {
            $result[$record['id']] = $record['name'];
        }

        return $result;
    }

    public function getHpSearchQuery($data): array
    {
        $sql = 'SELECT *
                FROM service_hosting_hp
                ORDER BY id asc';

        return [$sql, []];
    }

    /**
     * @throws InformationException
     */
    public function deleteHp(\Model_ServiceHostingHp $model): bool
    {
        $id = $model->id;
        $serviceHosting = $this->di['db']->findOne('ServiceHosting', 'service_hosting_hp_id = ?', [$model->id]);
        if ($serviceHosting) {
            throw new InformationException('Cannot remove hosting plan which has active accounts');
        }
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted hosting plan %s', $id);

        return true;
    }

    public function toHostingHpApiArray(\Model_ServiceHostingHp $model, $deep = false, $identity = null): array
    {
        if (is_null($model->config)) {
            $model->config = '';
        }

        return [
            'id' => $model->id,

            'name' => $model->name,
            'bandwidth' => $model->bandwidth,
            'quota' => $model->quota,

            'max_ftp' => $model->max_ftp,
            'max_sql' => $model->max_sql,
            'max_pop' => $model->max_pop,
            'max_sub' => $model->max_sub,
            'max_park' => $model->max_park,
            'max_addon' => $model->max_addon,
            'config' => json_decode($model->config, 1),

            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
        ];
    }

    public function updateHp(\Model_ServiceHostingHp $model, array $data): bool
    {
        $model->name = $data['name'] ?? $model->name;
        $model->bandwidth = $data['bandwidth'] ?? $model->bandwidth;
        $model->quota = $data['quota'] ?? $model->quota;
        $model->max_addon = $data['max_addon'] ?? $model->max_addon;
        $model->max_ftp = $data['max_ftp'] ?? $model->max_ftp;
        $model->max_sql = $data['max_sql'] ?? $model->max_sql;
        $model->max_pop = $data['max_pop'] ?? $model->max_pop;
        $model->max_sub = $data['max_sub'] ?? $model->max_sub;
        $model->max_park = $data['max_park'] ?? $model->max_park;

        /* add new config value to hosting plan */
        if (!empty($model->config) && json_validate($model->config)) {
            $config = json_decode($model->config, true);
        } else {
            $config = [];
        }

        $inConfig = $data['config'] ?? null;

        if (is_array($inConfig)) {
            foreach ($inConfig as $key => $val) {
                if (isset($config[$key])) {
                    $config[$key] = $val;
                }
                if (isset($config[$key]) && empty($val)) {
                    unset($config[$key]);
                }
            }
        }

        $newConfigName = $data['new_config_name'] ?? null;
        $newConfigValue = $data['new_config_value'] ?? null;
        if (!empty($newConfigName) && !empty($newConfigValue)) {
            $config[$newConfigName] = $newConfigValue;
        }

        $model->config = json_encode($config);
        $model->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($model);

        $this->di['logger']->info('Updated hosting plan %s', $model->id);

        return true;
    }

    public function createHp($name, $data)
    {
        $model = $this->di['db']->dispense('ServiceHostingHp');
        $model->name = $name;

        $model->bandwidth = $data['bandwidth'] ?? 1024 * 1024;
        $model->quota = $data['quota'] ?? 1024 * 1024;

        $model->max_addon = $data['max_addon'] ?? 1;
        $model->max_park = $data['max_park'] ?? 1;
        $model->max_sub = $data['max_sub'] ?? 1;
        $model->max_pop = $data['max_pop'] ?? 1;
        $model->max_sql = $data['max_sql'] ?? 1;
        $model->max_ftp = $data['max_ftp'] ?? 1;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');
        $newId = $this->di['db']->store($model);

        $this->di['logger']->info('Added new hosting plan %s', $newId);

        return $newId;
    }

    public function getServerPackage(\Model_ServiceHostingHp $model)
    {
        $config = json_decode($model->config ?? '', 1);
        if (!is_array($config)) {
            $config = [];
        }

        $p = new \Server_Package();
        $p->setCustomValues($config)
            ->setMaxFtp($model->max_ftp)
            ->setMaxSql($model->max_sql)
            ->setMaxPop($model->max_pop)
            ->setMaxSubdomains($model->max_sub)
            ->setMaxParkedDomains($model->max_park)
            ->setMaxDomains($model->max_addon)
            ->setBandwidth($model->bandwidth)
            ->setQuota($model->quota)
            ->setName($model->name);

        return $p;
    }

    /**
     * @throws Exception
     */
    public function getServerManagerWithLog(\Model_ServiceHostingServer $model, \Model_ClientOrder $order)
    {
        $manager = $this->getServerManager($model);

        $order_service = $this->di['mod_service']('order');
        $log = $order_service->getLogger($order);
        $manager->setLog($log);

        return $manager;
    }

    /**
     * Returns both the standard and reseller login URLs.
     * Will not generate SSO links.
     *
     * @return string[]|false[]
     */
    public function getManagerUrls(\Model_ServiceHostingServer $model): array
    {
        try {
            $m = $this->getServerManager($model);

            return [$m->getLoginUrl(null), $m->getResellerLoginUrl(null)];
        } catch (\Exception $e) {
            error_log('Error while retrieving control panel url: ' . $e->getMessage());
        }

        return [false, false];
    }

    /**
     * Generates either a reseller or standard login link for a given order.
     * If the server manager supports SSO, an SSO link will be returned.
     */
    public function generateLoginUrl(\Model_ServiceHosting $model): string
    {
        [$adapter, $account] = $this->_getAM($model);
        if ($model->reseller) {
            return $adapter->getResellerLoginUrl($account);
        } else {
            return $adapter->getLoginUrl($account);
        }
    }

    public function prependOrderConfig(\Model_Product $product, array $data): array
    {
        [$sld, $tld] = $this->_getDomainTuple($data);
        $data['sld'] = $sld;
        $data['tld'] = $tld;

        if (is_string($product->config) && json_validate($product->config)) {
            $c = json_decode($product->config, true);
        } else {
            $c = [];
        }

        return array_merge($c, $data);
    }

    public function getDomainProductFromConfig(\Model_Product $product, array &$data): bool|array
    {
        $data = $this->prependOrderConfig($product, $data);
        $product->getService()->validateOrderData($data);

        if (is_string($product->config) && json_validate($product->config)) {
            $c = json_decode($product->config, true);
        } else {
            $c = [];
        }

        $dc = $data['domain'];
        $action = $dc['action'];

        $drepo = $this->di['mod_service']('servicedomain');
        $drepo->validateOrderData($dc);
        if ($action == 'owndomain') {
            return false;
        }

        if (isset($c['free_domain']) && $c['free_domain']) {
            $dc['free_domain'] = true;
        }

        if (isset($c['free_transfer']) && $c['free_transfer']) {
            $dc['free_transfer'] = true;
        }

        $table = $this->di['mod_service']('product');
        $d = $table->getMainDomainProduct();
        if (!$d instanceof \Model_Product) {
            throw new Exception('Could not find main domain product');
        }

        return ['product' => $d, 'config' => $dc];
    }

    public function getFreeTlds(\Model_Product $product): array
    {
        if (is_string($product->config) && json_validate($product->config)) {
            $config = json_decode($product->config, true);
        } else {
            $config = [];
        }

        $freeTlds = $config['free_tlds'] ?? [];
        $result = [];
        foreach ($freeTlds as $tld) {
            $result[] = ['tld' => $tld];
        }

        if (empty($result)) {
            $query = 'active = 1 and allow_register = 1';
            $tlds = $this->di['db']->find('Tld', $query, []);
            $serviceDomainService = $this->di['mod_service']('Servicedomain');
            foreach ($tlds as $model) {
                $result[] = $serviceDomainService->tldToApiArray($model);
            }
        }

        return $result;
    }
}
