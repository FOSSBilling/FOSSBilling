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

use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicehosting\Entity\ServiceHosting;
use Box\Mod\Servicehosting\Entity\ServiceHostingHp;
use Box\Mod\Servicehosting\Entity\ServiceHostingServer;
use Box\Mod\Servicehosting\Repository\ServiceHostingHpRepository;
use Box\Mod\Servicehosting\Repository\ServiceHostingRepository;
use Box\Mod\Servicehosting\Repository\ServiceHostingServerRepository;
use FOSSBilling\Exception;
use FOSSBilling\Extension\ExtensionType;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Tools;

class Service implements InjectionAwareInterface
{
    private const string PASSWORD_PLACEHOLDER = '********';

    public const string CREDENTIAL_KEEP_SENTINEL = '__KEEP__';

    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
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
            // should never occur, but in case
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

        $count = (int) $this->di['db']->getCell($query, [
            ':service_type' => \Box\Mod\Product\Service::HOSTING,
            ':sld' => $sld,
            ':tld' => $tld,
            ':canceled_status' => \Model_ClientOrder::STATUS_CANCELED,
        ]);

        if ($count > 0) {
            throw new InformationException('This free subdomain is already in use.');
        }
    }

    /**
     * @throws InformationException
     *
     * @todo
     */
    public function action_create(\Model_ClientOrder $order): ServiceHosting
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $this->validateOrderData($c);

        $server = $this->getExistingServer((int) $c['server_id'], 'Server from order configuration was not found');
        $hp = $this->getExistingHp((int) $c['hosting_plan_id'], 'Hosting plan from order configuration was not found');

        $model = new ServiceHosting();
        $model->setClientId((int) $order->client_id);
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
    public function action_activate(\Model_ClientOrder $order): array
    {
        // Retrieve the service associated with the order
        $model = $this->_getOrderService($order);

        // Retrieve the order's configuration
        $orderService = $this->di['mod_service']('order');
        $config = $orderService->getConfig($order);

        // Retrieve the server manager for the order
        $serverManager = $this->_getServerManagerForOrder($model);

        // A username is only ever persisted below once the account has
        // actually been created on the server. If one is already present,
        // a previous activation attempt already provisioned this account -
        // most likely the order's status update afterwards failed to save,
        // and this call is a retry. Re-running createAccount() in that case
        // would only fail with a "domain/account already exists" server
        // error, so treat the account as already provisioned instead.
        $alreadyProvisioned = !empty($model->getUsername());

        // Generate a password for the service
        $pass = $this->di['tools']->generatePassword($serverManager->getPasswordLength(), true);

        // If a password is already specified in the order's configuration, use that instead
        if (isset($config['password']) && !empty($config['password'])) {
            $pass = $config['password'];
        }

        // Generate a username for the service
        if ($alreadyProvisioned) {
            $username = $model->getUsername();
        } elseif (isset($config['username']) && !empty($config['username'])) {
            $username = $config['username'];
        } else {
            $username = $serverManager->generateUsername($model->getSld() . $model->getTld());
        }

        // Update the service's username and password
        $model->setUsername($username);
        $model->setPass($pass);

        // If the order's configuration does not specify that the service should be imported, create an account for the service on the server
        if (!$alreadyProvisioned && (!isset($config['import']) || !$config['import'])) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->createAccount($account);
        }

        // Update the service's password to a placeholder value for security reasons
        $model->setPass(self::PASSWORD_PLACEHOLDER);

        // Save the service
        $this->di['em']->flush();

        // Return the username for post-activation flows without exposing the password.
        return [
            'username' => $username,
        ];
    }

    /**
     * @throws Exception
     *
     * @todo
     */
    public function action_renew(\Model_ClientOrder $order): bool
    {
        // Ensures the order has an active hosting service before renewal.
        $this->_getOrderService($order);

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_suspend(\Model_ClientOrder $order, ?string $reason = null): bool
    {
        $model = $this->_getOrderService($order);
        [$adapter, $account] = $this->_getAM($model);
        $account->setNote($reason);
        $adapter->suspendAccount($account);

        $this->di['em']->flush();

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_unsuspend(\Model_ClientOrder $order): bool
    {
        $model = $this->_getOrderService($order);
        [$adapter, $account] = $this->_getAM($model);
        $adapter->unsuspendAccount($account);

        $this->di['em']->flush();

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_cancel(\Model_ClientOrder $order): bool
    {
        $model = $this->_getOrderService($order);
        [$adapter, $account] = $this->_getAM($model);
        $adapter->cancelAccount($account);

        $this->di['em']->flush();

        return true;
    }

    /**
     * @throws Exception
     */
    public function action_uncancel(\Model_ClientOrder $order): bool
    {
        $this->action_create($order);
        $model = $this->_getOrderService($order);

        // Retrieve the server manager for the order
        $serverManager = $this->_getServerManagerForOrder($model);

        // As we replace the password internally with asterisks, generate a new password
        $pass = $this->di['tools']->generatePassword($serverManager->getPasswordLength(), true);
        $model->setPass($pass);

        // Retrieve the adapter and account, then create the account on the server
        [$adapter, $account] = $this->_getAM($model);
        $adapter->createAccount($account);

        // Update the service's password to a placeholder value for security reasons
        $model->setPass(self::PASSWORD_PLACEHOLDER);

        // Save the service
        $this->di['em']->flush();

        return true;
    }

    public function action_delete(\Model_ClientOrder $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if ($service instanceof ServiceHosting) {
            // cancel if not canceled
            if ($order->status != \Model_ClientOrder::STATUS_CANCELED) {
                $this->action_cancel($order);
            }
            $this->di['em']->remove($service);
            $this->di['em']->flush();
        }
    }

    public function changeAccountPlan(\Model_ClientOrder $order, ServiceHosting $model, ServiceHostingHp $hp): bool
    {
        $model->setServiceHostingHpId($hp->getId());
        if ($this->_performOnService($order)) {
            $package = $this->getServerPackage($hp);
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountPackage($account, $package);
        }

        $this->di['em']->flush();
        $this->di['logger']->info('Changed hosting plan of account #%s', $model->getId());

        return true;
    }

    public function changeAccountUsername(\Model_ClientOrder $order, ServiceHosting $model, $data): bool
    {
        if (!isset($data['username']) || empty($data['username'])) {
            throw new InformationException('Account username is missing or is invalid');
        }

        $u = strtolower((string) $data['username']);

        if ($this->_performOnService($order)) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountUsername($account, $u);
        }

        $model->setUsername($u);
        $this->di['em']->flush();

        $this->di['logger']->info('Changed hosting account %s username', $model->getId());

        return true;
    }

    public function changeAccountIp(\Model_ClientOrder $order, ServiceHosting $model, $data): bool
    {
        if (!isset($data['ip']) || empty($data['ip'])) {
            throw new InformationException('Account IP address is missing or is invalid');
        }

        $ip = $data['ip'];

        if ($this->_performOnService($order)) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountIp($account, $ip);
        }

        $model->setIp($ip);
        $this->di['em']->flush();
        $this->di['logger']->info('Changed hosting account %s ip', $model->getId());

        return true;
    }

    public function changeAccountDomain(\Model_ClientOrder $order, ServiceHosting $model, $data): bool
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

        $model->setSld($sld);
        $model->setTld($tld);
        $this->di['em']->flush();
        $this->di['logger']->info('Changed hosting account %s domain', $model->getId());

        return true;
    }

    public function changeAccountPassword(\Model_ClientOrder $order, ServiceHosting $model, $data): bool
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

        $model->setPass(self::PASSWORD_PLACEHOLDER);
        $this->di['em']->flush();
        $this->di['logger']->info('Changed hosting account %s password', $model->getId());

        return true;
    }

    public function sync(\Model_ClientOrder $order, ServiceHosting $model): bool
    {
        [$adapter, $account] = $this->_getAM($model);
        $updated = $adapter->synchronizeAccount($account);

        if ($account->getUsername() != $updated->getUsername()) {
            $model->setUsername($updated->getUsername());
        }

        if ($account->getIp() != $updated->getIp()) {
            $model->setIp($updated->getIp());
        }

        $this->di['em']->flush();
        $this->di['logger']->info('Synchronizing hosting account %s with server', $model->getId());

        return true;
    }

    private function _getDomainOrderId(ServiceHosting $model)
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

        if (in_array($order->status, $badStatus)) {
            return false;
        }

        if ($order->expires_at !== null && strtotime((string) $order->expires_at) <= time()) {
            return false;
        }

        return true;
    }

    /**
     * @throws Exception
     */
    private function _getServerManagerForOrder(ServiceHosting $model)
    {
        $server = $this->getExistingServer((int) $model->getServiceHostingServerId(), 'Server not found');

        return $this->getServerManager($server);
    }

    public function _getAM(ServiceHosting $model, ?ServiceHostingHp $hp = null): array
    {
        if (!$hp instanceof ServiceHostingHp) {
            $hp = $this->getExistingHp((int) $model->getServiceHostingHpId(), 'Hosting plan not found');
        }

        $server = $this->getExistingServer((int) $model->getServiceHostingServerId(), 'Server not found');
        $client = $this->di['db']->getExistingModelById('Client', $model->getClientId(), 'Client not found');

        $server_client = new \FOSSBilling\Extension\Contract\Server\Client();
        $server_client
            ->setEmail($client->email)
            ->setFirstName($client->first_name)
            ->setLastName($client->last_name)
            ->setFullName($client->getFullName())
            ->setCompany($client->company)
            ->setStreet($client->address_1)
            ->setZip($client->postcode)
            ->setCity($client->city)
            ->setState($client->state)
            ->setCountry($client->country)
            ->setTelephone($client->phone);

        $package = $this->getServerPackage($hp);
        $server_account = new \FOSSBilling\Extension\Contract\Server\Account();
        $server_account
            ->setClient($server_client)
            ->setPackage($package)
            ->setUsername($model->getUsername())
            ->setReseller(Tools::normalizeBoolean($model->isReseller()))
            ->setDomain($model->getSld() . $model->getTld())
            ->setPassword($model->getPass())
            ->setNs1($server->getNs1())
            ->setNs2($server->getNs2())
            ->setNs3($server->getNs3())
            ->setNs4($server->getNs4())
            ->setIp($model->getIp());

        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);
        if ($order instanceof \Model_ClientOrder) {
            $adapter = $this->getServerManagerWithLog($server, $order);
        } else {
            $adapter = $this->getServerManager($server);
        }

        return [$adapter, $server_account];
    }

    public function toApiArray(ServiceHosting $model, $deep = false, $identity = null): array
    {
        $serviceHostingServerModel = $this->getExistingServer((int) $model->getServiceHostingServerId(), 'Server not found');
        $serviceHostingHpModel = $this->getExistingHp((int) $model->getServiceHostingHpId(), 'Hosting plan not found');
        $server = $this->toHostingServerApiArray($serviceHostingServerModel, $deep, $identity);
        $hp = $this->toHostingHpApiArray($serviceHostingHpModel, $deep, $identity);

        return [
            'ip' => $model->getIp(),
            'sld' => $model->getSld(),
            'tld' => $model->getTld(),
            'domain' => $model->getSld() . $model->getTld(),
            'username' => $model->getUsername(),
            'reseller' => $model->isReseller(),
            'server' => $server,
            'hosting_plan' => $hp,
            'domain_order_id' => $this->_getDomainOrderId($model),
        ];
    }

    public function toHostingServerApiArray(ServiceHostingServer $model, $deep = false, $identity = null): array
    {
        [$cpanel_url, $whm_url] = $this->getManagerUrls($model);
        $result = [
            'name' => $model->getName(),
            'hostname' => $model->getHostname(),
            'ip' => $model->getIp(),
            'ns1' => $model->getNs1(),
            'ns2' => $model->getNs2(),
            'ns3' => $model->getNs3(),
            'ns4' => $model->getNs4(),
            'cpanel_url' => $cpanel_url,
            'reseller_cpanel_url' => $whm_url,
        ];

        if ($identity instanceof \Model_Admin) {
            $result['id'] = $model->getId();
            $result['active'] = $model->isActive();
            $result['secure'] = $model->isSecure();
            $result['assigned_ips'] = json_decode($model->getAssignedIps() ?? '[]', true) ?? [];
            $result['status_url'] = $model->getStatusUrl();
            $result['max_accounts'] = $model->getMaxAccounts();
            $result['manager'] = $model->getManager();
            $result['config'] = json_decode($model->getConfig() ?? '', true) ?? [];
            $result['port'] = Tools::normalizePort($model->getPort());
            $result['passwordLength'] = $model->getPasswordLength();
            $result['created_at'] = $this->formatDateTime($model->getCreatedAt());
            $result['updated_at'] = $this->formatDateTime($model->getUpdatedAt());

            $secretFields = $this->getServerManagerSecretFields((string) $model->getManager());
            foreach ($secretFields as $field) {
                $raw = $this->getServerSecretField($model, $field);
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
            'id' => $model->getId(),
            'sld' => $model->getSld(),
            'tld' => $model->getTld(),
            'client_id' => $model->getClientId(),
            'server_id' => $model->getServiceHostingServerId(),
            'plan_id' => $model->getServiceHostingHpId(),
            'reseller' => $model->isReseller(),
        ];

        if ($identity instanceof \Model_Admin) {
            $result['ip'] = $model->getIp();
            $result['username'] = $model->getUsername();
            $result['created_at'] = $this->formatDateTime($model->getCreatedAt());
            $result['updated_at'] = $this->formatDateTime($model->getUpdatedAt());
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
            $orderRows = $this->di['db']->getAll(
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

        if ($identity instanceof \Model_Admin) {
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
            $model->setUsername($data['username']);
        }

        if (isset($data['ip']) && !empty($data['ip'])) {
            $model->setIp($data['ip']);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated hosting account %s without sending actions to server', $model->getId());

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
        return $this->di['extension_locator']->listInstalled(ExtensionType::Manager);
    }

    public function getServerManagerConfig($manager)
    {
        $classname = $this->resolveServerManagerClass($manager);
        if ($classname === null || !is_callable($classname . '::getForm')) {
            return [];
        }

        return call_user_func([$classname, 'getForm']);
    }

    /**
     * Resolves the class for an installed server manager, or null when the
     * manager is unknown. $manager is admin-supplied (server_create and
     * server_update), so it is never used to build a path directly.
     */
    private function resolveServerManagerClass(string $manager): ?string
    {
        try {
            return $this->di['extension_locator']->resolveClass(ExtensionType::Manager, $manager);
        } catch (InformationException) {
            return null;
        }
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

        $classname = $this->resolveServerManagerClass($manager);
        if ($classname === null) {
            return array_values(array_unique($secrets));
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

    public function createServer($name, $ip, $manager, $data): ?int
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

        $model->setActive(Tools::normalizeBoolean($data['active'] ?? true));
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
        $normalizedPort = Tools::normalizePort($data['port'] ?? null);
        $model->setPort($normalizedPort !== null ? (string) $normalizedPort : null);
        $model->setConfig(isset($data['config']) ? json_encode($data['config']) : null);
        $model->setPasswordLength(is_numeric($data['passwordLength'] ?? '') ? intval($data['passwordLength']) : null);
        $model->setSecure(Tools::normalizeBoolean($data['secure'] ?? true));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $newId = $model->getId();

        $this->di['logger']->info('Added new hosting server %s', $newId);

        return $newId;
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
            $model->setAssignedIps(self::processAssignedIPs($assigned_ips));
        }

        $model->setActive(array_key_exists('active', $data) ? Tools::normalizeBoolean($data['active']) : $model->isActive());
        $model->setStatusUrl($data['status_url'] ?? $model->getStatusUrl());
        $model->setMaxAccounts(array_key_exists('max_accounts', $data) ? ($data['max_accounts'] !== null ? (int) $data['max_accounts'] : null) : $model->getMaxAccounts());
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
        $model->setSecure(array_key_exists('secure', $data) ? Tools::normalizeBoolean($data['secure']) : $model->isSecure());
        $model->setUsername($this->normalizeCredential('username', $data['username'] ?? null, $model->getUsername(), $model->getId(), false));
        $model->setPassword($this->normalizeCredential('password', $data['password'] ?? null, $model->getPassword(), $model->getId(), true));
        $model->setAccesshash($this->normalizeCredential('accesshash', $data['accesshash'] ?? null, $model->getAccesshash(), $model->getId(), true));
        $model->setPasswordLength(is_numeric($data['passwordLength'] ?? '') ? intval($data['passwordLength']) : $model->getPasswordLength());

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
            $adminId = $this->di['loggedin_admin']->id ?? 'unknown';
            $this->di['logger']->info('Rotated %s for hosting server %s by admin %s', $field, (string) $serverId, (string) $adminId);
        }

        return $incoming;
    }

    /**
     * @throws Exception
     */
    public function getServerManager(ServiceHostingServer $model)
    {
        if (empty($model->getManager())) {
            throw new Exception('Invalid server manager. Server was not configured properly.', null, 654);
        }

        $config = [];
        $config['ip'] = $model->getIp();
        $config['host'] = $model->getHostname();
        $config['port'] = Tools::normalizePort($model->getPort());
        $config['config'] = [];
        $config['config'] = json_decode($model->getConfig() ?? '', true) ?? [];
        $config['secure'] = $model->isSecure();
        $config['username'] = $model->getUsername();
        $config['password'] = $model->getPassword();
        $config['accesshash'] = $model->getAccesshash();
        $config['passwordLength'] = $model->getPasswordLength();

        $manager = $this->di['server_manager']($model->getManager(), $config);

        if (!$manager instanceof \FOSSBilling\Extension\Contract\Server\Manager) {
            throw new Exception('Server manager :adapter is invalid.', [':adapter' => $model->getManager()]);
        }

        return $manager;
    }

    /**
     * @throws \FOSSBilling\Extension\Contract\Server\Exception
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
    public function deleteHp(ServiceHostingHp $model): bool
    {
        $id = $model->getId();
        $serviceHosting = $this->getServiceHostingRepository()->findOneBy(['serviceHostingHpId' => $id]);
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
        return [
            'id' => $model->getId(),

            'name' => $model->getName(),
            'bandwidth' => $model->getBandwidth(),
            'quota' => $model->getQuota(),

            'max_ftp' => $model->getMaxFtp(),
            'max_sql' => $model->getMaxSql(),
            'max_pop' => $model->getMaxPop(),
            'max_sub' => $model->getMaxSub(),
            'max_park' => $model->getMaxPark(),
            'max_addon' => $model->getMaxAddon(),
            'config' => json_decode($model->getConfig() ?? '', true),

            'created_at' => $this->formatDateTime($model->getCreatedAt()),
            'updated_at' => $this->formatDateTime($model->getUpdatedAt()),
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

        /* add new config value to hosting plan */
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

        $model->setConfig(json_encode($config));
        $this->di['em']->flush();

        $this->di['logger']->info('Updated hosting plan %s', $model->getId());

        return true;
    }

    public function createHp($name, $data): ?int
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

        $newId = $model->getId();

        $this->di['logger']->info('Added new hosting plan %s', $newId);

        return $newId;
    }

    public function getServerPackage(ServiceHostingHp $model): \FOSSBilling\Extension\Contract\Server\Package
    {
        $config = json_decode($model->getConfig() ?? '', true);
        if (!is_array($config)) {
            $config = [];
        }

        $p = new \FOSSBilling\Extension\Contract\Server\Package();
        $p->setCustomValues($config)
            ->setMaxFtp($model->getMaxFtp())
            ->setMaxSql($model->getMaxSql())
            ->setMaxPop($model->getMaxPop())
            ->setMaxSubdomains($model->getMaxSub())
            ->setMaxParkedDomains($model->getMaxPark())
            ->setMaxDomains($model->getMaxAddon())
            ->setBandwidth($model->getBandwidth())
            ->setQuota($model->getQuota())
            ->setName($model->getName());

        return $p;
    }

    /**
     * @throws Exception
     */
    public function getServerManagerWithLog(ServiceHostingServer $model, \Model_ClientOrder $order)
    {
        $manager = $this->getServerManager($model);

        $order_service = $this->di['mod_service']('order');
        $manager->setLog(new \FOSSBilling\PsrLogAdapter($order_service->getLogger($order)));

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
        if ($model->isReseller()) {
            return $adapter->getResellerLoginUrl($account);
        }

        return $adapter->getLoginUrl($account);
    }

    /**
     * Top-level cart-config keys a client is authorized to set when ordering
     * a hosting product. Admin-controlled fields (hosting_plan_id, server_id,
     * reseller, subdomain_base_domain, etc.) are stripped from client input
     * before the merge.
     *
     * @return list<string>
     */
    public function clientSettableConfigKeys(): array
    {
        return ['period', 'domain', 'quantity', 'multiple'];
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

        // When unset, hosting products allow all domain actions.
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
            $tlds = $this->di['em']->getRepository(\Box\Mod\Servicedomain\Entity\Tld::class)
                ->findBy(['active' => true, 'allowRegister' => true], ['id' => 'ASC']);
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
        // Split the input by any type of line break (\n, \r\n, or \r)
        $array = preg_split('/\r\n|\r|\n/', $assigned_ips);

        // Trim each entry and remove any empty strings
        $array = array_map(trim(...), $array);
        $array = array_filter($array, fn ($ip): bool => $ip !== '');

        // Validate that each entry is a valid IP address (works both with IPv4 and IPv6)
        $array = array_filter($array, fn ($ip): bool => (bool) filter_var($ip, FILTER_VALIDATE_IP));

        return json_encode(array_values($array));
    }

    private function formatDateTime(?\DateTime $dateTime): ?string
    {
        return $dateTime?->format('Y-m-d H:i:s');
    }

    private function getServerSecretField(ServiceHostingServer $model, string $field): ?string
    {
        return match ($field) {
            'password' => $model->getPassword(),
            'accesshash' => $model->getAccesshash(),
            'username' => $model->getUsername(),
            default => null,
        };
    }

    private function _getOrderService(\Model_ClientOrder $order): ServiceHosting
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceHosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }

        return $model;
    }

    private function getExistingServer(int $id, string $message): ServiceHostingServer
    {
        $server = $this->getServiceHostingServerRepository()->find($id);
        if (!$server instanceof ServiceHostingServer) {
            throw new Exception($message);
        }

        return $server;
    }

    private function getExistingHp(int $id, string $message): ServiceHostingHp
    {
        $hp = $this->getServiceHostingHpRepository()->find($id);
        if (!$hp instanceof ServiceHostingHp) {
            throw new Exception($message);
        }

        return $hp;
    }

    private function getServiceHostingRepository(): ServiceHostingRepository
    {
        return $this->di['em']->getRepository(ServiceHosting::class);
    }

    private function getServiceHostingHpRepository(): ServiceHostingHpRepository
    {
        return $this->di['em']->getRepository(ServiceHostingHp::class);
    }

    private function getServiceHostingServerRepository(): ServiceHostingServerRepository
    {
        return $this->di['em']->getRepository(ServiceHostingServer::class);
    }
}
