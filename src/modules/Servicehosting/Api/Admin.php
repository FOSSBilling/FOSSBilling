<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting\Api;

use Box\Mod\Order\Entity\Order;
use Box\Mod\Servicehosting\Entity\ServiceHosting;
use Box\Mod\Servicehosting\Entity\ServiceHostingServer;
use FOSSBilling\InformationException;
use FOSSBilling\PaginationOptions;
use FOSSBilling\Tools;
use FOSSBilling\Validation\Api\RequiredParams;

/**
 * Hosting service management.
 */
class Admin extends \FOSSBilling\Api\AbstractApi
{
    /**
     * Change hosting account plan.
     */
    #[RequiredParams(['plan_id' => 'plan_id is missing'])]
    public function change_plan($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_accounts');

        if (!isset($data['plan_id'])) {
            throw new \FOSSBilling\Exception('plan_id is missing');
        }

        [$order, $s] = $this->_getService($data);
        $service = $this->getService();
        $plan = $service->getServiceHostingHpRepository()->find($data['plan_id'])
            ?? throw new \FOSSBilling\InformationException('Hosting plan not found');

        return (bool) $service->changeAccountPlan($order, $s, $plan);
    }

    /**
     * Change hosting account username.
     */
    public function change_username($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_accounts');

        [$order, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->changeAccountUsername($order, $s, $data);
    }

    /**
     * Change hosting account ip.
     */
    public function change_ip($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_accounts');

        [$order, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->changeAccountIp($order, $s, $data);
    }

    /**
     * Change hosting account domain.
     */
    public function change_domain($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_accounts');

        [$order, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->changeAccountDomain($order, $s, $data);
    }

    /**
     * Change hosting account password.
     */
    public function change_password($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_accounts');

        [$order, $s] = $this->_getService($data);
        $service = $this->getService();

        return (bool) $service->changeAccountPassword($order, $s, $data);
    }

    /**
     * Synchronize account with server values.
     */
    public function sync($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_accounts');

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
     */
    public function update($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_accounts');

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
        $this->checkPermissions('servicehosting', 'view_servers');

        return $this->getService()->getServerManagers();
    }

    /**
     * Get list of available hosting servers on system.
     *
     * @return array
     */
    public function server_get_pairs($data)
    {
        $this->checkPermissions('servicehosting', 'view_servers');

        return $this->getService()->getServerPairs();
    }

    /**
     * Get a paginated list of servers.
     *
     * @return array
     */
    public function server_get_list($data)
    {
        $this->checkPermissions('servicehosting', 'view_servers');
        [$sql, $params] = $this->getService()->getServersSearchQuery($data);
        $result = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));

        foreach ($result['list'] as $key => $server) {
            $model = $this->_serverFromRow($server);
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
        $this->checkPermissions('servicehosting', 'view_servers');
        [$sql, $params] = $this->getService()->getAccountsSearchQuery($data);
        $result = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));
        $orderService = $this->getDi()['mod_service']('order');

        foreach ($result['list'] as $key => $account) {
            $model = $this->_hostingFromRow($account);

            $order = $this->getDi()['em']->getRepository(Order::class)->findOneBy(['serviceType' => 'hosting', 'serviceId' => $model->getId()]);

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
     * @optional bool $tls_verify - flag to define whether to verify TLS certificates when calling server APIs
     * @optional bool $active - flag to enable/disable server
     *
     * @return int - server id
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams([
        'name' => 'Server name was not passed',
        'ip' => 'Server IP was not passed',
        'manager' => 'Server manager was not specified',
    ])]
    public function server_create($data): int
    {
        $this->checkPermissions('servicehosting', 'manage_servers');

        $service = $this->getService();

        $data['config'] = [
            'userprefix' => $data['userprefix'] ?? null,
            'tls_verify' => Tools::normalizeBoolean($data['tls_verify'] ?? true, true),
        ];

        return (int) $service->createServer($data['name'], $data['ip'], $data['manager'], $data);
    }

    /**
     * Get server details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Server ID was not passed'])]
    public function server_get($data)
    {
        $this->checkPermissions('servicehosting', 'view_servers');

        $service = $this->getService();
        $model = $service->getServiceHostingServerRepository()->find($data['id'])
            ?? throw new \FOSSBilling\InformationException('Server not found');

        return $service->toHostingServerApiArray($model, true, $this->getIdentity());
    }

    /**
     * Delete server.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Server ID was not passed'])]
    public function server_delete($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_servers');

        $service = $this->getService();
        $model = $service->getServiceHostingServerRepository()->find($data['id'])
            ?? throw new \FOSSBilling\InformationException('Server not found');

        $hosting_services = $service->getServiceHostingRepository()->findBy(['serviceHostingServerId' => $data['id']]);
        $count = count($hosting_services);

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Hosting server is used by :count: service hostings', [':count:' => $count], 704);
        }

        return (bool) $service->deleteServer($model);
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
     * @optional bool $tls_verify - flag to define whether to verify TLS certificates when calling server APIs
     * @optional bool $active - flag to enable/disable server
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Server ID was not passed'])]
    public function server_update($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_servers');

        $service = $this->getService();
        $model = $service->getServiceHostingServerRepository()->find($data['id'])
            ?? throw new \FOSSBilling\InformationException('Server not found');

        $existingConfig = json_decode($model->getConfig() ?? '', true) ?? [];

        $data['config'] = $existingConfig;
        $data['config']['userprefix'] = $data['userprefix'] ?? ($existingConfig['userprefix'] ?? null);
        $data['config']['tls_verify'] = Tools::normalizeBoolean($data['tls_verify'] ?? ($existingConfig['tls_verify'] ?? true), true);

        $updated = (bool) $service->updateServer($model, $data);

        if ($updated) {
            $this->validateServerConfig($model);
        }

        return $updated;
    }

    private function validateServerConfig(ServiceHostingServer $model): void
    {
        try {
            $this->getService()->getServerManager($model);
        } catch (\Server_Exception|\FOSSBilling\Exception $e) {
            throw new \FOSSBilling\InformationException($e->getMessage(), [], $e->getCode() ?: 719);
        }
    }

    /**
     * Test connection to server.
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Server ID was not passed'])]
    public function server_test_connection($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_servers');

        $service = $this->getService();
        $model = $service->getServiceHostingServerRepository()->find($data['id'])
            ?? throw new \FOSSBilling\InformationException('Server not found');

        return (bool) $service->testConnection($model);
    }

    /**
     * Get hosting plan pairs.
     *
     * @return array
     */
    public function hp_get_pairs($data)
    {
        $this->checkPermissions('servicehosting', 'manage_plans');

        return $this->getService()->getHpPairs();
    }

    /**
     * Get hosting plans paginated list.
     *
     * @return array
     */
    public function hp_get_list($data)
    {
        $this->checkPermissions('servicehosting', 'manage_plans');
        [$sql, $params] = $this->getService()->getHpSearchQuery($data);
        $pager = $this->getDi()['pager']->getPaginatedResultSet($sql, $params, PaginationOptions::fromArray($data));
        $service = $this->getService();

        foreach ($pager['list'] as $key => $item) {
            $model = $service->getServiceHostingHpRepository()->find($item['id'])
                ?? throw new \FOSSBilling\InformationException('Post not found');
            $pager['list'][$key] = $service->toHostingHpApiArray($model, false, $this->getIdentity());
        }

        return $pager;
    }

    /**
     * Delete hosting plan.
     *
     * @throws \FOSSBilling\InformationException
     */
    #[RequiredParams(['id' => 'Hosting plan ID was not passed'])]
    public function hp_delete($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_plans');

        $service = $this->getService();
        $model = $service->getServiceHostingHpRepository()->find($data['id'])
            ?? throw new \FOSSBilling\InformationException('Hosting plan not found');

        $hosting_services = $service->getServiceHostingRepository()->findBy(['serviceHostingHpId' => $data['id']]);
        $count = count($hosting_services);
        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Hosting plan is used by :count: service hostings', [':count:' => $count], 704);
        }

        return (bool) $service->deleteHp($model);
    }

    /**
     * Get hosting plan details.
     *
     * @return array
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Hosting plan ID was not passed'])]
    public function hp_get($data)
    {
        $this->checkPermissions('servicehosting', 'manage_plans');

        $service = $this->getService();
        $model = $service->getServiceHostingHpRepository()->find($data['id'])
            ?? throw new \FOSSBilling\InformationException('Hosting plan not found');

        return $service->toHostingHpApiArray($model, true, $this->getIdentity());
    }

    /**
     * Update hosting plan details.
     *
     * @optional string $name - hosting plan name. Used as identifier on server
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Hosting plan ID was not passed'])]
    public function hp_update($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_plans');

        $service = $this->getService();
        $model = $service->getServiceHostingHpRepository()->find($data['id'])
            ?? throw new \FOSSBilling\InformationException('Hosting plan not found');

        return (bool) $service->updateHp($model, $data);
    }

    /**
     * Update hosting plan details.
     *
     * @return int - new hosting plan id
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['name' => 'Hosting plan name was not passed'])]
    public function hp_create($data): int
    {
        $this->checkPermissions('servicehosting', 'manage_plans');

        $service = $this->getService();

        return (int) $service->createHp($data['name'], $data);
    }

    public function _getService($data): array
    {
        $required = [
            'order_id' => 'Order ID name is missing',
        ];
        $this->getDi()['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->getDi()['em']->getRepository(Order::class)->find($data['order_id']) ?? throw new InformationException('Order not found');
        $orderService = $this->getDi()['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof ServiceHosting) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return [$order, $s];
    }

    private function _serverFromRow(array $row): ServiceHostingServer
    {
        $model = new ServiceHostingServer();
        $model->setId($row['id'] ?? null);
        $model->setName($row['name'] ?? null);
        $model->setIp($row['ip'] ?? null);
        $model->setHostname($row['hostname'] ?? null);
        $model->setAssignedIps($row['assigned_ips'] ?? null);
        $model->setStatusUrl($row['status_url'] ?? null);
        $model->setActive(isset($row['active']) ? (bool) $row['active'] : null);
        $model->setMaxAccounts(isset($row['max_accounts']) ? (int) $row['max_accounts'] : null);
        $model->setNs1($row['ns1'] ?? null);
        $model->setNs2($row['ns2'] ?? null);
        $model->setNs3($row['ns3'] ?? null);
        $model->setNs4($row['ns4'] ?? null);
        $model->setManager($row['manager'] ?? null);
        $model->setUsername($row['username'] ?? null);
        $model->setPassword($row['password'] ?? null);
        $model->setAccesshash($row['accesshash'] ?? null);
        $model->setPasswordLength(isset($row['password_length']) ? (int) $row['password_length'] : null);
        $model->setPort($row['port'] ?? null);
        $model->setConfig($row['config'] ?? null);
        $model->setSecure(isset($row['secure']) ? (bool) $row['secure'] : null);

        if (isset($row['created_at']) && !empty($row['created_at'])) {
            $model->setCreatedAt(new \DateTime($row['created_at']));
        }
        if (isset($row['updated_at']) && !empty($row['updated_at'])) {
            $model->setUpdatedAt(new \DateTime($row['updated_at']));
        }

        return $model;
    }

    private function _hostingFromRow(array $row): ServiceHosting
    {
        $model = new ServiceHosting();
        $model->setId($row['id'] ?? null);
        $model->setClientId(isset($row['client_id']) ? (int) $row['client_id'] : null);
        $model->setServiceHostingServerId(isset($row['service_hosting_server_id']) ? (int) $row['service_hosting_server_id'] : null);
        $model->setServiceHostingHpId(isset($row['service_hosting_hp_id']) ? (int) $row['service_hosting_hp_id'] : null);
        $model->setSld($row['sld'] ?? null);
        $model->setTld($row['tld'] ?? null);
        $model->setIp($row['ip'] ?? null);
        $model->setUsername($row['username'] ?? null);
        $model->setPass($row['pass'] ?? null);
        $model->setReseller(isset($row['reseller']) ? (bool) $row['reseller'] : null);

        if (isset($row['created_at']) && !empty($row['created_at'])) {
            $model->setCreatedAt(new \DateTime($row['created_at']));
        }
        if (isset($row['updated_at']) && !empty($row['updated_at'])) {
            $model->setUpdatedAt(new \DateTime($row['updated_at']));
        }

        return $model;
    }
}
