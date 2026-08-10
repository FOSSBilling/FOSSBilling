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
use Box\Mod\Servicehosting\Entity\ServiceHostingHp;
use Box\Mod\Servicehosting\Entity\ServiceHostingServer;
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

        [$order, $s] = $this->_getService($data);
        $plan = $this->_getHp((int) $data['plan_id']);

        $service = $this->getService();

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

        $ids = array_map(static fn (array $server): int => (int) $server['id'], $result['list']);
        $models = $this->getDi()['em']->getRepository(ServiceHostingServer::class)->findBy(['id' => $ids]);
        $modelsById = [];
        foreach ($models as $model) {
            $modelsById[$model->getId()] = $model;
        }

        foreach ($result['list'] as $key => $server) {
            $id = (int) $server['id'];
            $model = $modelsById[$id] ?? null;
            if (!$model instanceof ServiceHostingServer) {
                throw new \FOSSBilling\Exception(sprintf('Server %d not found', $id));
            }

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
        $result['list'] = $this->getService()->getAccountsBatchForApi($result['list'], $this->getIdentity());

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

        $model = $this->_getServer((int) $data['id']);
        $service = $this->getService();

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

        $model = $this->_getServer((int) $data['id']);

        $count = $this->getDi()['em']->getRepository(ServiceHosting::class)
            ->count(['serviceHostingServerId' => (int) $data['id']]);

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
     * @optional bool $tls_verify - flag to define whether to verify TLS certificates when calling server APIs
     * @optional bool $active - flag to enable/disable server
     *
     * @throws \FOSSBilling\Exception
     */
    #[RequiredParams(['id' => 'Server ID was not passed'])]
    public function server_update($data): bool
    {
        $this->checkPermissions('servicehosting', 'manage_servers');

        $model = $this->_getServer((int) $data['id']);
        $service = $this->getService();

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

        $model = $this->_getServer((int) $data['id']);

        return (bool) $this->getService()->testConnection($model);
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

        $ids = array_map(static fn (array $item): int => (int) $item['id'], $pager['list']);
        $models = $this->getDi()['em']->getRepository(ServiceHostingHp::class)->findBy(['id' => $ids]);
        $modelsById = [];
        foreach ($models as $model) {
            $modelsById[$model->getId()] = $model;
        }

        foreach ($pager['list'] as $key => $item) {
            $id = (int) $item['id'];
            $model = $modelsById[$id] ?? null;
            if (!$model instanceof ServiceHostingHp) {
                throw new \FOSSBilling\Exception(sprintf('Hosting plan %d not found', $id));
            }
            $pager['list'][$key] = $this->getService()->toHostingHpApiArray($model, false, $this->getIdentity());
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

        $model = $this->_getHp((int) $data['id']);

        $count = $this->getDi()['em']->getRepository(ServiceHosting::class)
            ->count(['serviceHostingHpId' => (int) $data['id']]);
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
    #[RequiredParams(['id' => 'Hosting plan ID was not passed'])]
    public function hp_get($data)
    {
        $this->checkPermissions('servicehosting', 'manage_plans');

        $model = $this->_getHp((int) $data['id']);

        return $this->getService()->toHostingHpApiArray($model, true, $this->getIdentity());
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

        $model = $this->_getHp((int) $data['id']);

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

        $order = $this->getDi()['em']->getRepository(Order::class)->find($data['order_id']);
        if (!$order instanceof Order) {
            throw new \FOSSBilling\Exception('Order not found');
        }
        $orderService = $this->getDi()['mod_service']('order');
        $s = $orderService->getOrderService($order);
        if (!$s instanceof ServiceHosting) {
            throw new \FOSSBilling\Exception('Order is not activated');
        }

        return [$order, $s];
    }

    private function _getServer(int $id): ServiceHostingServer
    {
        $model = $this->getDi()['em']->getRepository(ServiceHostingServer::class)->find($id);
        if (!$model instanceof ServiceHostingServer) {
            throw new \FOSSBilling\Exception('Server not found');
        }

        return $model;
    }

    private function _getHp(int $id): ServiceHostingHp
    {
        $model = $this->getDi()['em']->getRepository(ServiceHostingHp::class)->find($id);
        if (!$model instanceof ServiceHostingHp) {
            throw new \FOSSBilling\Exception('Hosting plan not found');
        }

        return $model;
    }
}
