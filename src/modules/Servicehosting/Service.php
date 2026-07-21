<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicehosting;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicehosting\Entity\ServiceHosting;
use Box\Mod\Servicehosting\Entity\ServiceHostingHp;
use Box\Mod\Servicehosting\Entity\ServiceHostingServer;
use Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository;
use Box\Mod\Staff\Entity\Admin;
use Box\Mod\Servicehosting\Repository\ServiceHostingRepository;
use Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository;
use FOSSBilling\Exception;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Service implements InjectionAwareInterface
{
    private const string PASSWORD_PLACEHOLDER = '********';

    public const string CREDENTIAL_KEEP_SENTINEL = '__KEEP__';

    protected ?\Pimple\Container $di = null;
    private Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
        if (isset($di['filesystem'])) {
            $this->filesystem = $di['filesystem'];
        }
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    public function getModulePermissions(): array
    {
        return [
            'manage_accounts' => [
                'type' => 'bool',
                'display_name' => __trans('Manage hosting accounts'),
                'description' => __trans('Allows the staff member to manage hosting accounts (change plan, password, domain, etc.).'),
            ],
            'view_servers' => [
                'type' => 'bool',
                'display_name' => __trans('View hosting servers'),
                'description' => __trans('Allows the staff member to view hosting server details.'),
            ],
            'manage_servers' => [
                'type' => 'bool',
                'display_name' => __trans('Manage hosting servers'),
                'description' => __trans('Allows the staff member to create, update, and delete hosting servers.'),
            ],
            'manage_plans' => [
                'type' => 'bool',
                'display_name' => __trans('Manage hosting plans'),
                'description' => __trans('Allows the staff member to create, update, and delete hosting plans.'),
            ],
        ];
    }

    public function getServiceHostingRepository(): ServiceHostingRepository
    {
        return $this->di['em']->getRepository(ServiceHosting::class);
    }

    public function getServiceHostingServerRepository(): ServiceHostingServerRepository
    {
        return $this->di['em']->getRepository(ServiceHostingServer::class);
    }

    public function getServiceHostingHpRepository(): ServiceHostingHpRepository
    {
        return $this->di['em']->getRepository(ServiceHostingHp::class);
    }

    private function logInfo(string $message): void
    {
        if ($this->di !== null && isset($this->di['logger'])) {
            $this->di['logger']->info($message);
        }
    }

    public function getCartProductTitle(Product $product, array $data): ?string
    {
        try {
            $data = array_merge(json_decode($product->getConfig() ?? '', true) ?? [], $data);
            [$sld, $tld] = $this->_getDomainTuple($data);

            return __trans(':hosting for :domain', [':hosting' => $product->getTitle(), ':domain' => $sld . $tld]);
        } catch (\Exception $e) {
            $this->logInfo($e->getMessage());
        }

        return $product->getTitle();
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

        if (($data['domain']['action'] ?? null) === 'subdomain') {
            $this->assertSubdomainAvailable($data['sld'], $data['tld']);
        }
    }

    private function assertSubdomainAvailable(string $sld, string $tld): void
    {
        $query = 'SELECT COUNT(*)
            FROM service_hosting sh
            INNER JOIN client_order co ON co.service_id = sh.id AND co.service_type = :service_type
            WHERE LOWER(sh.sld) = LOWER(:sld)
                AND LOWER(sh.tld) = LOWER(:tld)
                AND co.status != :canceled_status';

        $count = (int) $this->di['em']->getConnection()->fetchOne($query, [
            'service_type' => \Box\Mod\Product\Service::HOSTING,
            'sld' => $sld,
            'tld' => $tld,
            'canceled_status' => Order::STATUS_CANCELED,
        ]);

        if ($count > 0) {
            throw new InformationException('This free subdomain is already in use.');
        }
    }

    /**
     * @throws InformationException
     */
    public function action_create(Order $order): ServiceHosting
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $this->validateOrderData($c);

        $server = $this->getServiceHostingServerRepository()->find($c['server_id'])
            ?? throw new InformationException('Server from order configuration was not found');

        $hp = $this->getServiceHostingHpRepository()->find($c['hosting_plan_id'])
            ?? throw new InformationException('Hosting plan from order configuration was not found');

        $model = new ServiceHosting();
        $model->setClientId($order->getClientId());
        $model->setServiceHostingServerId($server->getId());
        $model->setServiceHostingHpId($hp->getId());
        $model->setSld($c['sld']);
        $model->setTld($c['tld']);
        $model->setIp($server->getIp());
        $model->setReseller(Tools::normalizeBoolean($c['reseller'] ?? false));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    /**
     * @throws Exception
     */
    public function action_activate(Order $order): array
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        if (!$model instanceof ServiceHosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->getId()]);
        }

        $config = $orderService->getConfig($order);
        $serverManager = $this->_getServerManagerForOrder($model);

        $pass = $this->di['tools']->generatePassword($serverManager->getPasswordLength(), true);

        if (isset($config['password']) && !empty($config['password'])) {
            $pass = $config['password'];
        }

        if (isset($config['username']) && !empty($config['username'])) {
            $username = $config['username'];
        } else {
            $username = $serverManager->generateUsername($this->_getModelProperty($model, 'sld') . $this->_getModelProperty($model, 'tld'));
        }

        $this->_setModelProperty($model, 'username', $username);
        $this->_setModelProperty($model, 'pass', $pass);

        if (!isset($config['import']) || !$config['import']) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->createAccount($account);
        }

        $this->_setModelProperty($model, 'pass', self::PASSWORD_PLACEHOLDER);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return [
            'username' => $username,
        ];
    }

    /**
     * @throws Exception
     */
    public function action_renew(Order $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceHosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->getId()]);
        }

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_suspend(Order $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceHosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->getId()]);
        }
        [$adapter, $account] = $this->_getAM($model);
        $adapter->suspendAccount($account);

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_unsuspend(Order $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceHosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->getId()]);
        }
        [$adapter, $account] = $this->_getAM($model);
        $adapter->unsuspendAccount($account);

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_cancel(Order $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceHosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->getId()]);
        }
        [$adapter, $account] = $this->_getAM($model);
        $adapter->cancelAccount($account);

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_uncancel(Order $order): bool
    {
        $this->action_create($order);
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        $serverManager = $this->_getServerManagerForOrder($model);

        $pass = $this->di['tools']->generatePassword($serverManager->getPasswordLength(), true);
        $this->_setModelProperty($model, 'pass', $pass);

        [$adapter, $account] = $this->_getAM($model);
        $adapter->createAccount($account);

        $this->_setModelProperty($model, 'pass', self::PASSWORD_PLACEHOLDER);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return true;
    }

    public function action_delete(Order $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if ($service instanceof ServiceHosting) {
            if ($order->getStatus() != Order::STATUS_CANCELED) {
                $this->action_cancel($order);
            }
            $this->di['em']->remove($service);
            $this->di['em']->flush();
        }
    }

    public function changeAccountPlan(Order $order, ServiceHosting $model, ServiceHostingHp $hp): bool
    {
        $this->_setModelProperty($model, 'service_hosting_hp_id', $hp->getId());
        if ($this->_performOnService($order)) {
            $package = $this->getServerPackage($hp);
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountPackage($account, $package);
        }

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Changed hosting plan of account #%s', $this->_getModelProperty($model, 'id'));

        return true;
    }

    public function changeAccountUsername(Order $order, ServiceHosting $model, $data): bool
    {
        if (!isset($data['username']) || empty($data['username'])) {
            throw new InformationException('Account username is missing or is invalid');
        }

        $u = strtolower((string) $data['username']);

        if ($this->_performOnService($order)) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountUsername($account, $u);
        }

        $this->_setModelProperty($model, 'username', $u);
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Changed hosting account %s username', $this->_getModelProperty($model, 'id'));

        return true;
    }

    public function changeAccountIp(Order $order, ServiceHosting $model, $data): bool
    {
        if (!isset($data['ip']) || empty($data['ip'])) {
            throw new InformationException('Account IP address is missing or is invalid');
        }

        $ip = $data['ip'];

        if ($this->_performOnService($order)) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountIp($account, $ip);
        }

        $this->_setModelProperty($model, 'ip', $ip);
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Changed hosting account %s ip', $this->_getModelProperty($model, 'id'));

        return true;
    }

    public function changeAccountDomain(Order $order, ServiceHosting $model, $data): bool
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

        $this->_setModelProperty($model, 'sld', $sld);
        $this->_setModelProperty($model, 'tld', $tld);
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Changed hosting account %s domain', $this->_getModelProperty($model, 'id'));

        return true;
    }

    public function changeAccountPassword(Order $order, ServiceHosting $model, $data): bool
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

        $this->_setModelProperty($model, 'pass', self::PASSWORD_PLACEHOLDER);
        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Changed hosting account %s password', $this->_getModelProperty($model, 'id'));

        return true;
    }

    public function sync(Order $order, ServiceHosting $model): bool
    {
        [$adapter, $account] = $this->_getAM($model);
        $updated = $adapter->synchronizeAccount($account);

        if ($account->getUsername() != $updated->getUsername()) {
            $this->_setModelProperty($model, 'username', $updated->getUsername());
        }

        if ($account->getIp() != $updated->getIp()) {
            $this->_setModelProperty($model, 'ip', $updated->getIp());
        }

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));
        $this->di['em']->persist($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Synchronizing hosting account %s with server', $this->_getModelProperty($model, 'id'));

        return true;
    }

    private function _getDomainOrderId(ServiceHosting $model)
    {
        $orderService = $this->di['mod_service']('order');
        $o = $orderService->getServiceOrder($model);
        if ($o instanceof Order) {
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

    private function _performOnService(Order $order): bool
    {
        $badStatus = [
            Order::STATUS_FAILED_SETUP,
            Order::STATUS_PENDING_SETUP,
            Order::STATUS_SUSPENDED,
            Order::STATUS_CANCELED,
        ];

        if (in_array($order->getStatus(), $badStatus)) {
            return false;
        }

        $expiresAt = $order->getExpiresAt();
        if ($expiresAt !== null && $expiresAt <= new \DateTime()) {
            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    private function _getServerManagerForOrder($model)
    {
        $serverId = $this->_getModelProperty($model, $model instanceof ServiceHosting ? 'serviceHostingServerId' : 'service_hosting_server_id');
        $server = $this->getServiceHostingServerRepository()->find($serverId)
            ?? throw new Exception('Server not found');

        return $this->getServerManager($server);
    }

    public function _getAM($model, $hp = null): array
    {
        if ($hp instanceof ServiceHostingHp) {
            // entity
        } else {
            $hpId = $this->_getModelProperty($model, $model instanceof ServiceHosting ? 'serviceHostingHpId' : 'service_hosting_hp_id');
            $hp = $this->getServiceHostingHpRepository()->find($hpId)
                ?? throw new Exception('Hosting plan not found');
        }

        $serverId = $this->_getModelProperty($model, $model instanceof ServiceHosting ? 'serviceHostingServerId' : 'service_hosting_server_id');
        $server = $this->getServiceHostingServerRepository()->find($serverId)
            ?? throw new Exception('Server not found');

        $clientId = $this->_getModelProperty($model, $model instanceof ServiceHosting ? 'clientId' : 'client_id');
        $client = $this->di['em']->getRepository(Client::class)->find($clientId) ?? throw new Exception('Client not found');

        $hp_config = $this->_getModelProperty($hp, 'config');

        $server_client = new \Server_Client();
        $server_client
            ->setEmail($client->getEmail())
            ->setFirstName($client->getFirstName())
            ->setLastName($client->getLastName())
            ->setFullName($client->getFullName())
            ->setCompany($client->getCompany())
            ->setStreet($client->getAddress1())
            ->setZip($client->getPostcode())
            ->setCity($client->getCity())
            ->setState($client->getState())
            ->setCountry($client->getCountry())
            ->setTelephone($client->getPhone());

        $package = $this->getServerPackage($hp);
        $server_account = new \Server_Account();
        $server_account
            ->setClient($server_client)
            ->setPackage($package)
            ->setUsername($this->_getModelProperty($model, 'username'))
            ->setReseller(Tools::normalizeBoolean($this->_getModelProperty($model, 'reseller')))
            ->setDomain($this->_getModelProperty($model, 'sld') . $this->_getModelProperty($model, 'tld'))
            ->setPassword($this->_getModelProperty($model, 'pass'))
            ->setNs1($this->_getModelProperty($server, 'ns1'))
            ->setNs2($this->_getModelProperty($server, 'ns2'))
            ->setNs3($this->_getModelProperty($server, 'ns3'))
            ->setNs4($this->_getModelProperty($server, 'ns4'))
            ->setIp($this->_getModelProperty($model, 'ip'));

        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);
        if ($order instanceof Order) {
            $adapter = $this->getServerManagerWithLog($server, $order);
        } else {
            $adapter = $this->getServerManager($server);
        }

        return [$adapter, $server_account];
    }

    public function toApiArray(ServiceHosting $model, $deep = false, $identity = null): array
    {
        $serverId = $model->getServiceHostingServerId();
        $hpId = $model->getServiceHostingHpId();

        $serviceHostingServerModel = $this->getServiceHostingServerRepository()->find($serverId);
        $serviceHostingHpModel = $this->getServiceHostingHpRepository()->find($hpId);
        $server = $this->toHostingServerApiArray($serviceHostingServerModel, $deep, $identity);
        $hp = $this->toHostingHpApiArray($serviceHostingHpModel, $deep, $identity);

        return [
            'ip' => $this->_getModelProperty($model, 'ip'),
            'sld' => $this->_getModelProperty($model, 'sld'),
            'tld' => $this->_getModelProperty($model, 'tld'),
            'domain' => $this->_getModelProperty($model, 'sld') . $this->_getModelProperty($model, 'tld'),
            'username' => $this->_getModelProperty($model, 'username'),
            'reseller' => $this->_getModelProperty($model, 'reseller'),
            'server' => $server,
            'hosting_plan' => $hp,
            'domain_order_id' => $this->_getDomainOrderId($model),
        ];
    }

    public function toHostingServerApiArray(ServiceHostingServer $model, $deep = false, $identity = null): array
    {
        [$cpanel_url, $whm_url] = $this->getManagerUrls($model);
        $result = [
            'name' => $this->_getModelProperty($model, 'name'),
            'hostname' => $this->_getModelProperty($model, 'hostname'),
            'ip' => $this->_getModelProperty($model, 'ip'),
            'ns1' => $this->_getModelProperty($model, 'ns1'),
            'ns2' => $this->_getModelProperty($model, 'ns2'),
            'ns3' => $this->_getModelProperty($model, 'ns3'),
            'ns4' => $this->_getModelProperty($model, 'ns4'),
            'cpanel_url' => $cpanel_url,
            'reseller_cpanel_url' => $whm_url,
        ];

        if ($identity instanceof Admin) {
            $result['id'] = $this->_getModelProperty($model, 'id');
            $result['active'] = $this->_getModelProperty($model, 'active');
            $result['secure'] = $this->_getModelProperty($model, 'secure');
            $result['assigned_ips'] = json_decode($this->_getModelProperty($model, 'assigned_ips') ?? '[]', true) ?? [];
            $result['status_url'] = $this->_getModelProperty($model, 'status_url');
            $result['max_accounts'] = $this->_getModelProperty($model, 'max_accounts');
            $result['manager'] = $this->_getModelProperty($model, 'manager');
            $result['config'] = json_decode($this->_getModelProperty($model, 'config') ?? '', true) ?? [];
            $result['port'] = Tools::normalizePort($this->_getModelProperty($model, 'port'));
            $result['passwordLength'] = $this->_getModelProperty($model, 'passwordLength');
            $result['created_at'] = $this->_getModelProperty($model, 'created_at');
            $result['updated_at'] = $this->_getModelProperty($model, 'updated_at');

            $secretFields = $this->getServerManagerSecretFields((string) $this->_getModelProperty($model, 'manager'));
            foreach ($secretFields as $field) {
                $raw = $this->_getModelProperty($model, $field);
                $result[$field] = null;
                $result[$field . '_set'] = $raw !== null && $raw !== '';
            }
            $result['secret_fields'] = $secretFields;
        }

        return $result;
    }

    public function toHostingAccountApiArray(ServiceHosting $model, $deep = false, $identity = null): array
    {
        $result = [
            'id' => $this->_getModelProperty($model, 'id'),
            'sld' => $this->_getModelProperty($model, 'sld'),
            'tld' => $this->_getModelProperty($model, 'tld'),
            'client_id' => $this->_getModelProperty($model, 'client_id'),
            'server_id' => $model->getServiceHostingServerId(),
            'plan_id' => $model->getServiceHostingHpId(),
            'reseller' => $this->_getModelProperty($model, 'reseller'),
        ];

        if ($identity instanceof Admin) {
            $result['ip'] = $this->_getModelProperty($model, 'ip');
            $result['username'] = $this->_getModelProperty($model, 'username');
            $result['created_at'] = $this->_getModelProperty($model, 'created_at');
            $result['updated_at'] = $this->_getModelProperty($model, 'updated_at');
        }

        return $result;
    }

    /**
     * Enrich a page of hosting-account search results with orders and clients in batches.
     */
    public function getAccountsBatchForApi(array $accounts, $identity = null): array
    {
        if (empty($accounts)) {
            return [];
        }

        $serviceIds = array_values(array_unique(array_map(
            intval(...),
            array_filter(array_column($accounts, 'id'), is_numeric(...)),
        )));

        $orderIdsByServiceId = [];
        if (!empty($serviceIds)) {
            $placeholders = implode(',', array_fill(0, count($serviceIds), '?'));
            $orderRows = $this->di['em']->getConnection()->fetchAllAssociative(
                "SELECT id, service_id FROM client_order WHERE service_type = ? AND service_id IN ($placeholders) ORDER BY id ASC",
                array_merge(['hosting'], $serviceIds),
            );
            foreach ($orderRows as $orderRow) {
                $serviceId = (int) $orderRow['service_id'];
                $orderIdsByServiceId[$serviceId] ??= (int) $orderRow['id'];
            }
        }

        $ordersById = [];
        if (!empty($orderIdsByServiceId)) {
            $orderService = $this->di['mod_service']('order');
            $orders = $orderService->getBatchForApi(array_values($orderIdsByServiceId), $identity);
            foreach ($orders as $order) {
                $ordersById[(int) $order['id']] = $order;
            }
        }

        $result = [];
        foreach ($accounts as $account) {
            $accountData = $this->hostingAccountSearchResultToApiArray($account, $identity);
            $orderId = $orderIdsByServiceId[(int) $account['id']] ?? null;
            if ($orderId === null || !isset($ordersById[$orderId])) {
                $accountData['order'] = null;
                $result[] = $accountData;

                continue;
            }

            $order = $ordersById[$orderId];
            $accountData['client'] = $order['client'];
            unset($order['client']);
            $accountData['order'] = $order;
            $result[] = $accountData;
        }

        return $result;
    }

    private function hostingAccountSearchResultToApiArray(array $account, $identity = null): array
    {
        $result = [
            'id' => $account['id'],
            'sld' => $account['sld'],
            'tld' => $account['tld'],
            'client_id' => $account['client_id'],
            'server_id' => $account['service_hosting_server_id'],
            'plan_id' => $account['service_hosting_hp_id'],
            'reseller' => $account['reseller'],
        ];

        if ($identity instanceof Admin) {
            $result['ip'] = $account['ip'];
            $result['username'] = $account['username'];
            $result['created_at'] = $account['created_at'];
            $result['updated_at'] = $account['updated_at'];
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
            $tld = str_contains((string) $data['domain']['owndomain_tld'], '.') ? $data['domain']['owndomain_tld'] : '.' . $data['domain']['owndomain_tld'];
        }

        if ($data['domain']['action'] == 'subdomain') {
            $required = [
                'subdomain_sld' => 'Subdomain name is required.',
                'subdomain_base_domain' => 'Hosting product must have a subdomain base domain configured',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data['domain'] + $data);

            $subdomain = strtolower(trim((string) $data['domain']['subdomain_sld']));
            $baseDomain = strtolower(trim(trim((string) $data['subdomain_base_domain']), '.'));

            if (!$this->di['validator']->isSldValid($subdomain)) {
                throw new InformationException('Subdomain name is invalid.');
            }

            if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/i', $baseDomain)) {
                throw new InformationException('Subdomain base domain is invalid.');
            }

            $sld = $subdomain;
            $tld = '.' . $baseDomain;
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

    public function update(ServiceHosting $model, array $data): bool
    {
        if (isset($data['username']) && !empty($data['username'])) {
            $this->_setModelProperty($model, 'username', $data['username']);
        }

        if (isset($data['ip']) && !empty($data['ip'])) {
            $this->_setModelProperty($model, 'ip', $data['ip']);
        }

        $this->_setModelProperty($model, 'updated_at', date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Updated hosting account %s without sending actions to server', $this->_getModelProperty($model, 'id'));

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
        $files = [];

        $finder = new Finder();
        $finder->files()->in(Path::join(PATH_LIBRARY, 'Server', 'Manager'))->name('*.php');
        $finder->sortByName();

        foreach ($finder as $file) {
            $files[] = $file->getFilenameWithoutExtension();
        }

        return $files;
    }

    public function getServerManagerConfig($manager)
    {
        if (!in_array($manager, $this->_getServerManagers(), true)) {
            return [];
        }

        $filename = Path::join(PATH_LIBRARY, 'Server', 'Manager', "{$manager}.php");
        if (!$this->filesystem->exists($filename)) {
            return [];
        }

        $classname = 'Server_Manager_' . $manager;
        if (!class_exists($classname)) {
            try {
                require_once $filename;
            } catch (\Throwable) {
                return [];
            }
        }

        $method = 'getForm';
        if (!is_callable($classname . '::' . $method)) {
            return [];
        }

        return call_user_func([$classname, $method]);
    }

    /**
     * Returns the credential field names for a given server manager.
     * Combines the manager's own declarations with a defensive name-based
     * fallback so a future manager that forgets to mark a field is still
     * masked correctly.
     *
     * @return string[]
     */
    public function getServerManagerSecretFields(string $manager): array
    {
        $secrets = ['password', 'accesshash'];

        if (!in_array($manager, $this->_getServerManagers(), true)) {
            return array_values(array_unique($secrets));
        }

        $filename = Path::join(PATH_LIBRARY, 'Server', 'Manager', "{$manager}.php");
        if (!$this->filesystem->exists($filename)) {
            return array_values(array_unique($secrets));
        }

        $classname = 'Server_Manager_' . $manager;
        if (!class_exists($classname)) {
            try {
                require_once $filename;
            } catch (\Throwable) {
                return array_values(array_unique($secrets));
            }
        }

        if (is_callable($classname . '::getSecretFields')) {
            $declared = call_user_func([$classname, 'getSecretFields']);
            if (is_array($declared)) {
                $secrets = array_merge($secrets, $declared);
            }
        }

        $form = $this->getServerManagerConfig($manager);
        $formFields = $form['form']['credentials']['fields'] ?? [];
        foreach ($formFields as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (!empty($field['secret']) && !empty($field['name'])) {
                $secrets[] = (string) $field['name'];
            }
        }

        return array_values(array_unique($secrets));
    }

    public function getServerPairs(): array
    {
        $sql = 'SELECT id, name
                FROM service_hosting_server
                ORDER BY id ASC';
        $rows = $this->di['em']->getConnection()->fetchAllAssociative($sql);

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
                ORDER BY id ASC';

        return [$sql, []];
    }

    public function getAccountsSearchQuery($data): array
    {
        $sql = 'SELECT * FROM service_hosting';
        $params = [];

        $serverID = $data['server_id'] ?? null;

        if (!empty($serverID)) {
            $sql = $sql . ' WHERE service_hosting_server_id = :server_id';
            $params['server_id'] = $serverID;
        }

        $sql = $sql . ' ORDER BY id ASC';

        return [$sql, $params];
    }

    public function createServer($name, $ip, $manager, $data)
    {
        if (!in_array($manager, $this->_getServerManagers(), true)) {
            throw new Exception('Server manager :manager is not a valid server manager', [':manager' => $manager]);
        }

        $model = new ServiceHostingServer();
        $model->setName($name);
        $model->setIp($ip);

        $model->setHostname($data['hostname'] ?? null);
        $assigned_ips = $data['assigned_ips'] ?? '';
        if (!empty($assigned_ips)) {
            $model->setAssignedIps(self::processAssignedIPs($assigned_ips));
        }

        $model->setActive((bool) ($data['active'] ?? true));
        $model->setStatusUrl($data['status_url'] ?? null);
        $model->setMaxAccounts(isset($data['max_accounts']) ? (int) $data['max_accounts'] : null);

        $model->setNs1($data['ns1'] ?? null);
        $model->setNs2($data['ns2'] ?? null);
        $model->setNs3($data['ns3'] ?? null);
        $model->setNs4($data['ns4'] ?? null);

        $model->setManager($manager);
        $model->setUsername($data['username'] ?? null);
        $model->setPassword($data['password'] ?? null);
        $model->setAccesshash($data['accesshash'] ?? null);
        $port = Tools::normalizePort($data['port'] ?? null);
        $model->setPort($port !== null ? (string) $port : null);
        $model->setConfig(isset($data['config']) ? json_encode($data['config']) : null);
        $model->setPasswordLength(is_numeric($data['passwordLength'] ?? '') ? intval($data['passwordLength']) : null);
        $model->setSecure((bool) ($data['secure'] ?? true));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Added new hosting server %s', $model->getId());

        return $model->getId();
    }

    public function deleteServer(ServiceHostingServer $model): bool
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted hosting server %s', $id);

        return true;
    }

    public function updateServer(ServiceHostingServer $model, array $data): bool
    {
        $model->setName($data['name'] ?? $model->getName());
        $model->setIp($data['ip'] ?? $model->getIp());
        $model->setHostname($data['hostname'] ?? $model->getHostname());

        $assigned_ips = $data['assigned_ips'] ?? '';
        if (!empty($assigned_ips)) {
            $processed = self::processAssignedIPs($assigned_ips);
            $model->setAssignedIps($processed);
        }

        $model->setActive(isset($data['active']) ? (bool) $data['active'] : $model->isActive());
        $model->setStatusUrl($data['status_url'] ?? $model->getStatusUrl());
        $model->setMaxAccounts(isset($data['max_accounts']) ? (int) $data['max_accounts'] : $model->getMaxAccounts());
        $model->setNs1($data['ns1'] ?? $model->getNs1());
        $model->setNs2($data['ns2'] ?? $model->getNs2());
        $model->setNs3($data['ns3'] ?? $model->getNs3());
        $model->setNs4($data['ns4'] ?? $model->getNs4());

        if (isset($data['manager'])) {
            if (!in_array($data['manager'], $this->_getServerManagers(), true)) {
                throw new Exception('Server manager :manager is not a valid server manager', [':manager' => $data['manager']]);
            }
            $model->setManager($data['manager']);
        }

        $port = Tools::normalizePort($data['port'] ?? null);
        $model->setPort($port !== null ? (string) $port : $model->getPort());
        $model->setConfig(isset($data['config']) ? json_encode($data['config']) : $model->getConfig());
        $model->setSecure(isset($data['secure']) ? (bool) $data['secure'] : $model->isSecure());
        $model->setUsername($this->normalizeCredential('username', $data['username'] ?? null, $model->getUsername(), $model->getId(), false));
        $model->setPassword($this->normalizeCredential('password', $data['password'] ?? null, $model->getPassword(), $model->getId(), true));
        $model->setAccesshash($this->normalizeCredential('accesshash', $data['accesshash'] ?? null, $model->getAccesshash(), $model->getId(), true));
        $model->setPasswordLength(is_numeric($data['passwordLength'] ?? '') ? intval($data['passwordLength']) : $model->getPasswordLength());
        $model->setUpdatedAt(new \DateTime());

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Update hosting server %s', $model->getId());

        return true;
    }

    /**
     * Returns the value to store for a credential field. Blank, whitespace-only
     * or {@see CREDENTIAL_KEEP_SENTINEL} inputs preserve the existing value;
     * everything else replaces it. When `$audit` is true a successful rotation
     * of `password` or `accesshash` is logged (the value itself is never logged).
     */
    private function normalizeCredential(string $field, mixed $incoming, mixed $existing, mixed $serverId, bool $audit): mixed
    {
        if ($incoming === null || !is_scalar($incoming)) {
            return $existing;
        }

        $incoming = (string) $incoming;

        if (trim($incoming) === '' || $incoming === self::CREDENTIAL_KEEP_SENTINEL) {
            return $existing;
        }

        if ($audit && $incoming !== $existing) {
            $loggedinAdmin = $this->di['loggedin_admin'] ?? null;
            $adminId = $loggedinAdmin instanceof Admin ? $loggedinAdmin->getId() : ($loggedinAdmin->id ?? 'unknown');
            $this->di['logger']->info('Rotated %s for hosting server %s by admin %s', $field, (string) $serverId, (string) $adminId);
        }

        return $incoming;
    }

    /**
     * @throws Exception
     */
    public function getServerManager(ServiceHostingServer $model)
    {
        $manager = $this->_getModelProperty($model, 'manager');
        if (empty($manager)) {
            throw new Exception('Invalid server manager. Server was not configured properly.', null, 654);
        }

        $config = [];
        $config['ip'] = $this->_getModelProperty($model, 'ip');
        $config['host'] = $this->_getModelProperty($model, 'hostname');
        $config['port'] = Tools::normalizePort($this->_getModelProperty($model, 'port'));
        $config['config'] = [];
        $config['config'] = json_decode($this->_getModelProperty($model, 'config') ?? '', true) ?? [];
        $config['secure'] = $this->_getModelProperty($model, 'secure');
        $config['username'] = $this->_getModelProperty($model, 'username');
        $config['password'] = $this->_getModelProperty($model, 'password');
        $config['accesshash'] = $this->_getModelProperty($model, 'accesshash');
        $config['passwordLength'] = $this->_getModelProperty($model, 'passwordLength');

        $adapter = $this->di['server_manager']($manager, $config);

        if (!$adapter instanceof \Server_Manager) {
            throw new Exception('Server manager :adapter is invalid.', [':adapter' => $manager]);
        }

        return $adapter;
    }

    /**
     * @throws \Server_Exception
     * @throws Exception
     */
    public function testConnection(ServiceHostingServer $model)
    {
        $manager = $this->getServerManager($model);

        return $manager->testConnection();
    }

    public function getHpPairs(): array
    {
        $sql = 'SELECT id, name
                FROM service_hosting_hp';
        $rows = $this->di['em']->getConnection()->fetchAllAssociative($sql);
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
    public function deleteHp(ServiceHostingHp $model): bool
    {
        $id = $model->getId();
        $serviceHosting = $this->getServiceHostingRepository()->findOneByHpId($id);
        if ($serviceHosting) {
            throw new InformationException('Cannot remove hosting plan which has active accounts');
        }
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted hosting plan %s', $id);

        return true;
    }

    public function toHostingHpApiArray(ServiceHostingHp $model, $deep = false, $identity = null): array
    {
        $config = $this->_getModelProperty($model, 'config');
        if (is_null($config)) {
            $model->setConfig('');
        }

        return [
            'id' => $this->_getModelProperty($model, 'id'),
            'name' => $this->_getModelProperty($model, 'name'),
            'bandwidth' => $this->_getModelProperty($model, 'bandwidth'),
            'quota' => $this->_getModelProperty($model, 'quota'),
            'max_ftp' => $this->_getModelProperty($model, 'max_ftp'),
            'max_sql' => $this->_getModelProperty($model, 'max_sql'),
            'max_pop' => $this->_getModelProperty($model, 'max_pop'),
            'max_sub' => $this->_getModelProperty($model, 'max_sub'),
            'max_park' => $this->_getModelProperty($model, 'max_park'),
            'max_addon' => $this->_getModelProperty($model, 'max_addon'),
            'config' => json_decode($this->_getModelProperty($model, 'config') ?? '', true),
            'created_at' => $this->_getModelProperty($model, 'created_at'),
            'updated_at' => $this->_getModelProperty($model, 'updated_at'),
        ];
    }

    public function updateHp(ServiceHostingHp $model, array $data): bool
    {
        $model->setName($data['name'] ?? $model->getName());
        $model->setBandwidth($data['bandwidth'] ?? $model->getBandwidth());
        $model->setQuota($data['quota'] ?? $model->getQuota());
        $model->setMaxAddon($data['max_addon'] ?? $model->getMaxAddon());
        $model->setMaxFtp($data['max_ftp'] ?? $model->getMaxFtp());
        $model->setMaxSql($data['max_sql'] ?? $model->getMaxSql());
        $model->setMaxPop($data['max_pop'] ?? $model->getMaxPop());
        $model->setMaxSub($data['max_sub'] ?? $model->getMaxSub());
        $model->setMaxPark($data['max_park'] ?? $model->getMaxPark());

        $config = json_decode($model->getConfig() ?? '', true) ?? [];

        $inConfig = $data['config'] ?? null;

        if (is_array($inConfig)) {
            foreach ($inConfig as $key => $val) {
                if (empty($val)) {
                    unset($config[$key]);
                } else {
                    $config[$key] = $val;
                }
            }
        }

        $newConfigName = $data['new_config_name'] ?? null;
        $newConfigValue = $data['new_config_value'] ?? null;
        if (!empty($newConfigName) && !empty($newConfigValue)) {
            $config[$newConfigName] = $newConfigValue;
        }

        $encodedConfig = json_encode($config);
        $model->setConfig($encodedConfig);
        $model->setUpdatedAt(new \DateTime());

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Updated hosting plan %s', $model->getId());

        return true;
    }

    public function createHp($name, $data)
    {
        $model = new ServiceHostingHp();
        $model->setName($name);

        $model->setBandwidth((string) ($data['bandwidth'] ?? 1024 * 1024));
        $model->setQuota((string) ($data['quota'] ?? 1024 * 1024));

        $model->setMaxAddon((string) ($data['max_addon'] ?? 1));
        $model->setMaxPark((string) ($data['max_park'] ?? 1));
        $model->setMaxSub((string) ($data['max_sub'] ?? 1));
        $model->setMaxPop((string) ($data['max_pop'] ?? 1));
        $model->setMaxSql((string) ($data['max_sql'] ?? 1));
        $model->setMaxFtp((string) ($data['max_ftp'] ?? 1));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Added new hosting plan %s', $model->getId());

        return $model->getId();
    }

    public function getServerPackage(ServiceHostingHp $model): \Server_Package
    {
        $config = json_decode($this->_getModelProperty($model, 'config') ?? '', true);
        if (!is_array($config)) {
            $config = [];
        }

        $p = new \Server_Package();
        $p->setCustomValues($config)
            ->setMaxFtp($this->_getModelProperty($model, 'max_ftp'))
            ->setMaxSql($this->_getModelProperty($model, 'max_sql'))
            ->setMaxPop($this->_getModelProperty($model, 'max_pop'))
            ->setMaxSubdomains($this->_getModelProperty($model, 'max_sub'))
            ->setMaxParkedDomains($this->_getModelProperty($model, 'max_park'))
            ->setMaxDomains($this->_getModelProperty($model, 'max_addon'))
            ->setBandwidth($this->_getModelProperty($model, 'bandwidth'))
            ->setQuota($this->_getModelProperty($model, 'quota'))
            ->setName($this->_getModelProperty($model, 'name'));

        return $p;
    }

    /**
     * @throws Exception
     */
    public function getServerManagerWithLog(ServiceHostingServer $model, Order $order)
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
    public function getManagerUrls(ServiceHostingServer $model): array
    {
        try {
            $m = $this->getServerManager($model);

            return [$m->getLoginUrl(null), $m->getResellerLoginUrl(null)];
        } catch (\Exception $e) {
            $this->logInfo("Error while retrieving control panel url: {$e->getMessage()}.");
        }

        return [false, false];
    }

    /**
     * Generates either a reseller or standard login link for a given order.
     * If the server manager supports SSO, an SSO link will be returned.
     */
    public function generateLoginUrl(ServiceHosting $model): string
    {
        [$adapter, $account] = $this->_getAM($model);
        if ($this->_getModelProperty($model, 'reseller')) {
            return $adapter->getResellerLoginUrl($account);
        }

        return $adapter->getLoginUrl($account);
    }

    public function attachOrderConfig(Product $product, array $data): array
    {
        $c = json_decode($product->getConfig() ?? '', true) ?? [];

        $data = array_merge($c, $data);

        if (($data['domain']['action'] ?? null) === 'subdomain' && array_key_exists('subdomain_base_domain', $c)) {
            $data['subdomain_base_domain'] = $c['subdomain_base_domain'];
        }

        if (isset($data['domain']['action'])) {
            $this->validateDomainAction($data, $c);
        }

        [$sld, $tld] = $this->_getDomainTuple($data);
        $data['sld'] = $sld;
        $data['tld'] = $tld;

        return $data;
    }

    /**
     * Validates that the requested domain action is allowed for this product.
     *
     * @param array $data          The order data containing domain configuration
     * @param array $productConfig The product configuration
     *
     * @throws InformationException if the domain action is not allowed
     */
    private function validateDomainAction(array $data, array $productConfig): void
    {
        $action = $data['domain']['action'];

        $allowRegister = $productConfig['allow_domain_register'] ?? true;
        $allowTransfer = $productConfig['allow_domain_transfer'] ?? true;
        $allowOwn = $productConfig['allow_domain_own'] ?? true;
        $allowSubdomain = $productConfig['allow_subdomain'] ?? false;

        match ($action) {
            'register' => $allowRegister || throw new InformationException('Domain registration is not available for this product.'),
            'transfer' => $allowTransfer || throw new InformationException('Domain transfer is not available for this product.'),
            'owndomain' => $allowOwn || throw new InformationException('Using your own domain is not allowed for this product.'),
            'subdomain' => ($allowSubdomain && !empty($productConfig['subdomain_base_domain']))
                || throw new InformationException('Subdomain ordering is not available for this product.'),
            default => throw new InformationException('Invalid domain action specified.'),
        };
    }

    public function getDomainProductFromConfig(Product $product, array &$data): bool|array
    {
        $data = $this->attachOrderConfig($product, $data);
        $this->validateOrderData($data);

        $c = json_decode($product->getConfig() ?? '', true) ?? [];

        $dc = $data['domain'];
        $action = $dc['action'];

        if ($action == 'subdomain') {
            return false;
        }

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

        if (isset($c['free_tlds'])) {
            $dc['free_tlds'] = $c['free_tlds'];
        }

        if (isset($c['free_domain_periods'])) {
            $dc['free_domain_periods'] = $c['free_domain_periods'];
        }

        $table = $this->di['mod_service']('product');
        $d = $table->getMainDomainProduct();
        if (!$d instanceof Product) {
            throw new Exception('Could not find main domain product');
        }

        return ['product' => $d, 'config' => $dc];
    }

    public function getFreeTlds(Product $product, $identity = null): array
    {
        $config = json_decode($product->getConfig() ?? '', true) ?? [];
        $freeTlds = $config['free_tlds'] ?? [];
        $result = [];
        foreach ($freeTlds as $tld) {
            $result[] = ['tld' => $tld];
        }

        if (empty($result)) {
            $tlds = $this->di['em']->getRepository(\Box\Mod\Servicedomain\Entity\Tld::class)->findBy(['active' => true, 'allowRegister' => true]);
            $serviceDomainService = $this->di['mod_service']('Servicedomain');
            foreach ($tlds as $model) {
                $result[] = $serviceDomainService->tldToApiArray($model, $identity);
            }
        }

        return $result;
    }

    /**
     * Post-processing for the assigned IPs.
     * The data from the server management form (/admin/servicehosting/server/{id}) sends the data like this:
     * assigned_ips: "10.0.0.1\n10.0.0.2\n"
     * As you see, it isn't really an array, it also doesn't filter out empty lines and whitespaces at all.
     *
     * We can't rely on it as-is. So we need to make sure only the valid IP addresses are going inside the array.
     * We'll split on any type of line break (\n, \r\n, or \r) and make sure each IP address is valid.
     *
     * @param string $assigned_ips Raw string from the form data (example form: /admin/servicehosting/server/{ip})
     *
     * @return string JSON encoded array of filtered valid IPs
     */
    public static function processAssignedIPs(string $assigned_ips): string
    {
        $array = preg_split('/\r\n|\r|\n/', $assigned_ips);
        $array = array_map(trim(...), $array);
        $array = array_filter($array, fn ($ip): bool => $ip !== '');
        $array = array_filter($array, fn ($ip): bool => (bool) filter_var($ip, FILTER_VALIDATE_IP));

        return json_encode(array_values($array));
    }

    /**
     * Get a property value from a hosting entity using getter methods.
     */
    private function _getModelProperty(object $model, string $property): mixed
    {
        if ($model instanceof ServiceHosting) {
            return match ($property) {
                'id' => $model->getId(),
                'client_id' => $model->getClientId(),
                'service_hosting_server_id' => $model->getServiceHostingServerId(),
                'serviceHostingServerId' => $model->getServiceHostingServerId(),
                'service_hosting_hp_id' => $model->getServiceHostingHpId(),
                'serviceHostingHpId' => $model->getServiceHostingHpId(),
                'sld' => $model->getSld(),
                'tld' => $model->getTld(),
                'ip' => $model->getIp(),
                'username' => $model->getUsername(),
                'pass' => $model->getPass(),
                'reseller' => $model->isReseller(),
                'created_at' => $model->getCreatedAt(),
                'updated_at' => $model->getUpdatedAt(),
                default => null,
            };
        }

        if ($model instanceof ServiceHostingHp) {
            return match ($property) {
                'id' => $model->getId(),
                'name' => $model->getName(),
                'quota' => $model->getQuota(),
                'bandwidth' => $model->getBandwidth(),
                'max_ftp' => $model->getMaxFtp(),
                'max_sql' => $model->getMaxSql(),
                'max_pop' => $model->getMaxPop(),
                'max_sub' => $model->getMaxSub(),
                'max_park' => $model->getMaxPark(),
                'max_addon' => $model->getMaxAddon(),
                'config' => $model->getConfig(),
                'created_at' => $model->getCreatedAt(),
                'updated_at' => $model->getUpdatedAt(),
                default => null,
            };
        }

        if ($model instanceof ServiceHostingServer) {
            return match ($property) {
                'id' => $model->getId(),
                'name' => $model->getName(),
                'ip' => $model->getIp(),
                'hostname' => $model->getHostname(),
                'assigned_ips' => $model->getAssignedIps(),
                'status_url' => $model->getStatusUrl(),
                'active' => $model->isActive(),
                'max_accounts' => $model->getMaxAccounts(),
                'ns1' => $model->getNs1(),
                'ns2' => $model->getNs2(),
                'ns3' => $model->getNs3(),
                'ns4' => $model->getNs4(),
                'manager' => $model->getManager(),
                'username' => $model->getUsername(),
                'password' => $model->getPassword(),
                'accesshash' => $model->getAccesshash(),
                'passwordLength' => $model->getPasswordLength(),
                'password_length' => $model->getPasswordLength(),
                'port' => $model->getPort(),
                'config' => $model->getConfig(),
                'secure' => $model->isSecure(),
                'created_at' => $model->getCreatedAt(),
                'updated_at' => $model->getUpdatedAt(),
                default => null,
            };

        }

        return null;
    }

    /**
     * Set a property value on a hosting entity using setter methods.
     */
    private function _setModelProperty(object $model, string $property, mixed $value): void
    {
        if ($model instanceof ServiceHosting) {
            match ($property) {
                'id' => $model->setId($value),
                'client_id' => $model->setClientId($value),
                'service_hosting_server_id' => $model->setServiceHostingServerId($value),
                'service_hosting_hp_id' => $model->setServiceHostingHpId($value),
                'sld' => $model->setSld($value),
                'tld' => $model->setTld($value),
                'ip' => $model->setIp($value),
                'username' => $model->setUsername($value),
                'pass' => $model->setPass($value),
                'reseller' => $model->setReseller($value),
                'created_at' => $model->setCreatedAt(is_string($value) ? new \DateTime($value) : $value),
                'updated_at' => $model->setUpdatedAt(is_string($value) ? new \DateTime($value) : $value),
                default => null,
            };

            return;
        }

        if ($model instanceof ServiceHostingHp) {
            match ($property) {
                'id' => $model->setId($value),
                'name' => $model->setName($value),
                'quota' => $model->setQuota($value),
                'bandwidth' => $model->setBandwidth($value),
                'max_ftp' => $model->setMaxFtp($value),
                'max_sql' => $model->setMaxSql($value),
                'max_pop' => $model->setMaxPop($value),
                'max_sub' => $model->setMaxSub($value),
                'max_park' => $model->setMaxPark($value),
                'max_addon' => $model->setMaxAddon($value),
                'config' => $model->setConfig($value),
                'created_at' => $model->setCreatedAt(is_string($value) ? new \DateTime($value) : $value),
                'updated_at' => $model->setUpdatedAt(is_string($value) ? new \DateTime($value) : $value),
                default => null,
            };

            return;
        }

        if ($model instanceof ServiceHostingServer) {
            match ($property) {
                'id' => $model->setId($value),
                'name' => $model->setName($value),
                'ip' => $model->setIp($value),
                'hostname' => $model->setHostname($value),
                'assigned_ips' => $model->setAssignedIps($value),
                'status_url' => $model->setStatusUrl($value),
                'active' => $model->setActive((bool) $value),
                'max_accounts' => $model->setMaxAccounts($value),
                'ns1' => $model->setNs1($value),
                'ns2' => $model->setNs2($value),
                'ns3' => $model->setNs3($value),
                'ns4' => $model->setNs4($value),
                'manager' => $model->setManager($value),
                'username' => $model->setUsername($value),
                'password' => $model->setPassword($value),
                'accesshash' => $model->setAccesshash($value),
                'passwordLength' => $model->setPasswordLength($value),
                'password_length' => $model->setPasswordLength($value),
                'port' => $model->setPort($value),
                'config' => $model->setConfig($value),
                'secure' => $model->setSecure((bool) $value),
                'created_at' => $model->setCreatedAt(is_string($value) ? new \DateTime($value) : $value),
                'updated_at' => $model->setUpdatedAt(is_string($value) ? new \DateTime($value) : $value),
                default => null,
            };

            return;
        }
    }
}
