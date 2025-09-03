<?php

/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting\Api;

/**
 * Hosting service management.
 */
class Admin extends \Api_Abstract
{
    /**
     * Change hosting account plan.
     *
     * @return bool
     */
    public function change_plan($data)
    {
        if (!isset($data['plan_id'])) {
            throw new \FOSSBilling\Exception('plan_id is missing');
        }

        [$order, $s] = $this->_getService($data);
        $plan = $this->di['db']->getExistingModelById('ServiceHostingHp', $data['plan_id'], 'Hosting plan not found');

        $service = $this->getService();

        return (bool) $service->changeAccountPlan($order, $s, $plan);
    }

    /**
     * Change hosting account username.
     *
     * @return bool
     */
    public function change_username($data)
    {
        [$order, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->changeAccountUsername($order, $s, $data);
    }

    /**
     * Change hosting account ip.
     *
     * @return bool
     */
    public function change_ip($data)
    {
        [$order, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->changeAccountIp($order, $s, $data);
    }

    /**
     * Change hosting account domain.
     *
     * @return bool
     */
    public function change_domain($data)
    {
        [$order, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->changeAccountDomain($order, $s, $data);
    }

    /**
     * Change hosting account password.
     *
     * @return bool
     */
    public function change_password($data)
    {
        [$order, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->changeAccountPassword($order, $s, $data);
    }

    /**
     * Synchronize account with server values.
     *
     * @return bool
     */
    public function sync($data)
    {
        [$order, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->sync($order, $s);
    }

    /**
     * Update account information on FOSSBilling database.
     * This does not send actions to real account on hosting server.
     *
     * @optional string $username - Hosting account username
     * @optional string $ip - Hosting account ip
     *
     * @return bool
     */
    public function update($data)
    {
        [, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->update($s, $data);
    }

    /**
     * Get list of available server managers on system.
     *
     * @return array
     */
    public function manager_get_pairs($data)
    {
        return $this->getService()->getServerManagers();
    }

    /**
     * Get list of available hosting servers on system.
     *
     * @return array
     */
    public function server_get_pairs($data)
    {
        return $this->getService()->getServerPairs();
    }

    /**
     * Get a paginated list of servers.
     *
     * @return array
     */
    public function server_get_list($data)
    {
        [$sql, $params] = $this->getService()->getServersSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $result = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);

        foreach ($result['list'] as $key => $server) {
            $bean = $this->di['db']->dispense('ServiceHostingServer')->unbox();
            $bean->import($server);
            $model = $bean->box();

            $result['list'][$key] = $this->getService()->toHostingServerApiArray($model, false, $this->getIdentity());
        }

        return $result;
    }

    /**
     * Get a paginated list of hosting accounts, along with the "order" and "client" information.
     *
     * @param $data array Accepts the optional "server_id" property
     *
     * @return array
     */
    public function account_get_list($data)
    {
        [$sql, $params] = $this->getService()->getAccountsSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $result = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        $orderService = $this->di['mod_service']('order');

        foreach ($result['list'] as $key => $account) {
            $bean = $this->di['db']->dispense('ServiceHosting')->unbox();
            $bean->import($account);
            $model = $bean->box();

            $order = $this->di['db']->findOne('ClientOrder', 'service_type = "hosting" AND service_id = :service_id', [':service_id' => $model->id]);

            $result['list'][$key] = $this->getService()->toHostingAccountApiArray($model, true, $this->getIdentity());

            if ($order) {
                $result['list'][$key]['order'] = $orderService->toApiArray($order);
                $result['list'][$key]['client'] = $result['list'][$key]['order']['client'];

                unset($result['list'][$key]['order']['client']);
            } else {
                $result['list'][$key]['order'] = null;
            }
        }

        return $result;
    }

    /**
     * Create new hosting server.
     *
     * @optional string $hostname - server hostname
     * @optional string $ns1 - default nameserver 1
     * @optional string $ns2 - default nameserver 2
     * @optional string $ns3 - default nameserver 3
     * @optional string $ns4 - default nameserver 4
     * @optional string $username - server API login username
     * @optional string $password - server API login password
     * @optional string $accesshash - server API login access hash
     * @optional string $port - server API port
     * @optional string $passwordLength - password length for generated accounts
     * @optional bool $secure - flag to define whether to use secure connection (https) to server or not (http)
     * @optional bool $active - flag to enable/disable server
     *
     * @return int - server id
     *
     * @throws \FOSSBilling\Exception
     */
    public function server_create($data)
    {
        $required = [
            'name' => 'Server name is missing',
            'ip' => 'Server IP is missing',
            'manager' => 'Server manager is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        return (int) $service->createServer($data['name'], $data['ip'], $data['manager'], $data);
    }

    /**
     * Get server details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function server_get($data)
    {
        $required = [
            'id' => 'Server id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingServer', $data['id'], 'Server not found');
        $service = $this->getService();

        return $service->toHostingServerApiArray($model, true, $this->getIdentity());
    }

    /**
     * Delete server.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function server_delete($data)
    {
        $required = [
            'id' => 'Server id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingServer', $data['id'], 'Server not found');

        // check if server is not used by any service_hostings
        $hosting_services = $this->di['db']->find('ServiceHosting', 'service_hosting_server_id = :cart_id', [':cart_id' => $data['id']]);
        $count = is_array($hosting_services) ? count($hosting_services) : 0; // Handle the case where $hosting_services might be null

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Hosting server is used by :count: service hostings', [':count:' => $count], 704);
        }

        return (bool) $this->getService()->deleteServer($model);
    }

    /**
     * Update server configuration.
     *
     * @optional string $hostname - server hostname
     * @optional string $ns1 - default nameserver 1
     * @optional string $ns2 - default nameserver 2
     * @optional string $ns3 - default nameserver 3
     * @optional string $ns4 - default nameserver 4
     * @optional string $username - server API login username
     * @optional string $password - server API login password
     * @optional string $accesshash - server API login access hash
     * @optional string $userprefix - prefix for created user
     * @optional string $port - server API port
     * @optional string $passwordLength - password length for generated accounts
     * @optional bool $secure - flag to define whether to use secure connection (https) to server or not (http)
     * @optional bool $active - flag to enable/disable server
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function server_update($data)
    {
        $required = [
            'id' => 'Server id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingServer', $data['id'], 'Server not found');
        $service = $this->getService();

        $data['config'] = [
            'userprefix' => $data['userprefix'] ?? null,
        ];

        return (bool) $service->updateServer($model, $data);
    }

    /**
     * Test connection to server.
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function server_test_connection($data)
    {
        $required = [
            'id' => 'Server id is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingServer', $data['id'], 'Server not found');

        return (bool) $this->getService()->testConnection($model);
    }

    /**
     * Get hosting plan pairs.
     *
     * @return array
     */
    public function hp_get_pairs($data)
    {
        return $this->getService()->getHpPairs();
    }

    /**
     * Get hosting plans paginated list.
     *
     * @return array
     */
    public function hp_get_list($data)
    {
        [$sql, $params] = $this->getService()->getHpSearchQuery($data);
        $per_page = $data['per_page'] ?? $this->di['pager']->getDefaultPerPage();
        $pager = $this->di['pager']->getPaginatedResultSet($sql, $params, $per_page);
        foreach ($pager['list'] as $key => $item) {
            $model = $this->di['db']->getExistingModelById('ServiceHostingHp', $item['id'], 'Post not found');
            $pager['list'][$key] = $this->getService()->toHostingHpApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Delete hosting plan.
     *
     * @return bool
     *
     * @throws \FOSSBilling\InformationException
     */
    public function hp_delete($data)
    {
        $required = [
            'id' => 'Hosting plan ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingHp', $data['id'], 'Hosting plan not found');

        // check if hosting plan is not used by any service_hostings
        $hosting_services = $this->di['db']->find('ServiceHosting', 'service_hosting_hp_id = :cart_id', [':cart_id' => $data['id']]);

        // Ensure $hosting_services is an array before counting its elements
        $count = is_array($hosting_services) ? count($hosting_services) : 0; // Handle the case where $hosting_services might be null
        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Hosting plan is used by :count: service hostings', [':count:' => $count], 704);
        }

        return (bool) $this->getService()->deleteHp($model);
    }

    /**
     * Get hosting plan details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    public function hp_get($data)
    {
        $required = [
            'id' => 'Hosting plan ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingHp', $data['id'], 'Hosting plan not found');

        return $this->getService()->toHostingHpApiArray($model, true, $this->getIdentity());
    }

    /**
     * Update hosting plan details.
     *
     * @optional string $name - hosting plan name. Used as identifier on server
     *
     * @return bool
     *
     * @throws \FOSSBilling\Exception
     */
    public function hp_update($data)
    {
        $required = [
            'id' => 'Hosting plan ID is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $model = $this->di['db']->getExistingModelById('ServiceHostingHp', $data['id'], 'Hosting plan not found');

        $service = $this->getService();

        return (bool) $service->updateHp($model, $data);
    }

    /**
     * Update hosting plan details.
     *
     * @return int - new hosting plan id
     *
     * @throws \FOSSBilling\Exception
     */
    public function hp_create($data)
    {
        $required = [
            'name' => 'Hosting plan name is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->getService();

        return (int) $service->createHp($data['name'], $data);
    }

    public function _getService($data)
    {
        $required = [
            'order_id' => 'Order ID name is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->getExistingModelById('ClientOrder', $data['order_id'], 'Order not found');
        $orderService = $this->di['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof \Model_ServiceHosting) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return [$order, $s];
    }
}
