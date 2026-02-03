<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace FOSSBilling\ProductType\Hosting;

use FOSSBilling\Exception;
use FOSSBilling\InformationException;
use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Interfaces\ProductTypeHandlerInterface;
use FOSSBilling\ProductType\Hosting\Entity\Hosting;
use FOSSBilling\ProductType\Hosting\Entity\HostingPlan;
use FOSSBilling\ProductType\Hosting\Entity\HostingServer;
use FOSSBilling\ProductType\Hosting\Repository\HostingPlanRepository;
use FOSSBilling\ProductType\Hosting\Repository\HostingRepository;
use FOSSBilling\ProductType\Hosting\Repository\HostingServerRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class HostingHandler implements ProductTypeHandlerInterface, InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private readonly Filesystem $filesystem;
    private ?HostingRepository $hostingRepository = null;
    private ?HostingServerRepository $serverRepository = null;
    private ?HostingPlanRepository $planRepository = null;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    protected function getHostingRepository(): HostingRepository
    {
        if ($this->hostingRepository === null) {
            $this->hostingRepository = $this->di['em']->getRepository(Hosting::class);
        }

        return $this->hostingRepository;
    }

    protected function getServerRepository(): HostingServerRepository
    {
        if ($this->serverRepository === null) {
            $this->serverRepository = $this->di['em']->getRepository(HostingServer::class);
        }

        return $this->serverRepository;
    }

    protected function getPlanRepository(): HostingPlanRepository
    {
        if ($this->planRepository === null) {
            $this->planRepository = $this->di['em']->getRepository(HostingPlan::class);
        }

        return $this->planRepository;
    }

    protected function loadEntity(int $id): Hosting
    {
        $entity = $this->getHostingRepository()->find($id);
        if (!$entity instanceof Hosting) {
            throw new Exception('Hosting entity not found');
        }

        return $entity;
    }

    public function getCartProductTitle($product, array $data)
    {
        try {
            [$sld, $tld] = $this->_getDomainTuple($data);

            return __trans(':hosting for :domain', [':hosting' => $product->title, ':domain' => $sld . $tld]);
        } catch (\Exception $e) {
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

    public function create(\Model_ClientOrder $order): Hosting
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);
        $this->validateOrderData($c);

        $server = $this->loadServerEntity((int) $c['server_id']);

        $plan = $this->loadPlanEntity((int) $c['hosting_plan_id']);

        $em = $this->di['em'];
        $hosting = new Hosting($order->client_id);
        $hosting->setServer($server);
        $hosting->setPlan($plan);
        $hosting->setSld($c['sld']);
        $hosting->setTld($c['tld']);
        $hosting->setIp($server->getIp());
        $hosting->setReseller($c['reseller'] ?? false);

        $em->persist($hosting);
        $em->flush();

        return $hosting;
    }

    public function activate(\Model_ClientOrder $order): array
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        if (!$model instanceof Hosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }

        $config = $orderService->getConfig($order);

        $serverManager = $this->_getServerMangerForOrder($model);

        $pass = $this->di['tools']->generatePassword($serverManager->getPasswordLength(), true);

        if (isset($config['password']) && !empty($config['password'])) {
            $pass = $config['password'];
        }

        if (isset($config['username']) && !empty($config['username'])) {
            $username = $config['username'];
        } else {
            $username = $serverManager->generateUsername($model->getSld() . $model->getTld());
        }

        $model->setUsername($username);
        $model->setPass($pass);

        if (!isset($config['import']) || !$config['import']) {
            [$adapter, $account] = $this->_getAM($model);
            $adapter->createAccount($account);
        }

        $model->setPass('********');

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return [
            'username' => $username,
            'password' => $pass,
        ];
    }

    public function renew(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof Hosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return true;
    }

    public function suspend(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof Hosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }
        [$adapter, $account] = $this->_getAM($model);
        $adapter->suspendAccount($account);

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return true;
    }

    public function unsuspend(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof Hosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }
        [$adapter, $account] = $this->_getAM($model);
        $adapter->unsuspendAccount($account);

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return true;
    }

    public function cancel(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof Hosting) {
            throw new Exception('Order :id has no active service', [':id' => $order->id]);
        }
        [$adapter, $account] = $this->_getAM($model);
        $adapter->cancelAccount($account);

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return true;
    }

    public function uncancel(\Model_ClientOrder $order): bool
    {
        $this->create($order);
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        $serverManager = $this->_getServerMangerForOrder($model);

        $pass = $this->di['tools']->generatePassword($serverManager->getPasswordLength(), true);
        $model->setPass($pass);

        [$adapter, $account] = $this->_getAM($model);
        $adapter->createAccount($account);

        $model->setPass('********');

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return true;
    }

    public function delete(\Model_ClientOrder $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);
        if ($service instanceof Hosting) {
            if ($order->status != \Model_ClientOrder::STATUS_CANCELED) {
                $this->cancel($order);
            }
            $em = $this->di['em'];
            $em->remove($service);
            $em->flush();
        }
    }

    public function changeAccountPlan(\Model_ClientOrder $order, Hosting $model, HostingPlan $hp): bool
    {
        $model->setPlan($hp);
        if ($this->_performOnService($order)) {
            $package = $this->getServerPackage($hp);
            [$adapter, $account] = $this->_getAM($model);
            $adapter->changeAccountPackage($account, $package);
        }

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();
        $this->di['logger']->info('Changed hosting plan of account #%s', $model->getId());

        return true;
    }

    public function changeAccountUsername(\Model_ClientOrder $order, Hosting $model, $data): bool
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

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();
        $this->di['logger']->info('Changed hosting account %s username', $model->getId());

        return true;
    }

    public function changeAccountIp(\Model_ClientOrder $order, Hosting $model, $data): bool
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

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();
        $this->di['logger']->info('Changed hosting account %s ip', $model->getId());

        return true;
    }

    public function changeAccountDomain(\Model_ClientOrder $order, Hosting $model, $data): bool
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

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();
        $this->di['logger']->info('Changed hosting account %s domain', $model->getId());

        return true;
    }

    public function changeAccountPassword(\Model_ClientOrder $order, Hosting $model, $data): bool
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

        $model->setPass('******');

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();
        $this->di['logger']->info('Changed hosting account %s password', $model->getId());

        return true;
    }

    public function sync(\Model_ClientOrder $order, Hosting $model): bool
    {
        [$adapter, $account] = $this->_getAM($model);
        $updated = $adapter->synchronizeAccount($account);

        if ($account->getUsername() != $updated->getUsername()) {
            $model->setUsername($updated->getUsername());
        }

        if ($account->getIp() != $updated->getIp()) {
            $model->setIp($updated->getIp());
        }

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();
        $this->di['logger']->info('Synchronizing hosting account %s with server', $model->getId());

        return true;
    }

    private function _getDomainOrderId(Hosting $model)
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
        $badStatus = [
            \Model_ClientOrder::STATUS_FAILED_SETUP,
            \Model_ClientOrder::STATUS_PENDING_SETUP,
            \Model_ClientOrder::STATUS_SUSPENDED,
            \Model_ClientOrder::STATUS_CANCELED,
        ];

        return !in_array($order->status, $badStatus);
    }

    private function loadServerEntity(int $id): HostingServer
    {
        $entity = $this->getServerRepository()->find($id);
        if (!$entity instanceof HostingServer) {
            throw new Exception('Server not found');
        }

        return $entity;
    }

    private function loadPlanEntity(int $id): HostingPlan
    {
        $entity = $this->getPlanRepository()->find($id);
        if (!$entity instanceof HostingPlan) {
            throw new Exception('Hosting plan not found');
        }

        return $entity;
    }

    private function _getServerMangerForOrder(Hosting $model)
    {
        $server = $model->getServer();
        if ($server === null) {
            throw new Exception('Server not found');
        }

        return $this->getServerManager($server);
    }

    public function _getAM(Hosting $model, ?HostingPlan $hp = null): array
    {
        $server = $model->getServer();
        if ($server === null) {
            throw new Exception('Server not found');
        }

        if (!$hp instanceof HostingPlan) {
            $plan = $model->getPlan();
            if ($plan === null) {
                throw new Exception('Hosting plan not found');
            }
            $hp = $plan;
        }

        $client = $this->di['db']->load('Client', $model->getClientId());

        $hp_config = $hp->getConfig();

        $server_client = new \Server_Client();
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
        $server_account = new \Server_Account();
        $server_account
            ->setClient($server_client)
            ->setPackage($package)
            ->setUsername($model->getUsername())
            ->setReseller($model->isReseller())
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

    public function toApiArray(Hosting $model, $deep = false, $identity = null): array
    {
        $server = $model->getServer();
        $plan = $model->getPlan();

        $serverData = $server !== null ? $this->toHostingServerApiArray($server, $deep, $identity) : null;
        $hpData = $plan !== null ? $this->toHostingHpApiArray($plan, $deep, $identity) : null;

        return [
            'ip' => $model->getIp(),
            'sld' => $model->getSld(),
            'tld' => $model->getTld(),
            'domain' => $model->getSld() . $model->getTld(),
            'username' => $model->getUsername(),
            'reseller' => $model->isReseller(),
            'server' => $serverData,
            'hosting_plan' => $hpData,
            'domain_order_id' => $this->_getDomainOrderId($model),
        ];
    }

    public function toHostingServerApiArray(HostingServer $model, $deep = false, $identity = null): array
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
            $result['assigned_ips'] = $model->getAssignedIps();
            $result['status_url'] = $model->getStatusUrl();
            $result['max_accounts'] = $model->getMaxAccounts();
            $result['manager'] = $model->getManager();
            $result['config'] = json_decode($model->getConfig() ?? '', true) ?? [];
            $result['username'] = $model->getUsername();
            $result['password'] = $model->getPassword();
            $result['accesshash'] = $model->getAccessHash();
            $result['port'] = $model->getPort();
            $result['passwordLength'] = $model->getPasswordLength();
            $result['created_at'] = $model->getCreatedAt()?->format('Y-m-d H:i:s');
            $result['updated_at'] = $model->getUpdatedAt()?->format('Y-m-d H:i:s');
        }

        return $result;
    }

    public function toHostingAccountApiArray(Hosting $model, $deep = false, $identity = null): array
    {
        $result = [
            'id' => $model->getId(),
            'sld' => $model->getSld(),
            'tld' => $model->getTld(),
            'client_id' => $model->getClientId(),
            'server_id' => $model->getServer()?->getId(),
            'plan_id' => $model->getPlan()?->getId(),
            'reseller' => $model->isReseller(),
        ];

        if ($identity instanceof \Model_Admin) {
            $result['ip'] = $model->getIp();
            $result['username'] = $model->getUsername();
            $result['created_at'] = $model->getCreatedAt()?->format('Y-m-d H:i:s');
            $result['updated_at'] = $model->getUpdatedAt()?->format('Y-m-d H:i:s');
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

    public function update(Hosting $model, array $data): bool
    {
        if (isset($data['username']) && !empty($data['username'])) {
            $model->setUsername($data['username']);
        }

        if (isset($data['ip']) && !empty($data['ip'])) {
            $model->setIp($data['ip']);
        }

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

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
        $filename = Path::join(PATH_LIBRARY, 'Server', 'Manager', "{$manager}.php");
        if (!$this->filesystem->exists($filename)) {
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
        return $this->getServerRepository()->getPairs();
    }

    public function getServersSearchQuery($data): array
    {
        $qb = $this->getServerRepository()->getSearchQueryBuilder($data);

        return [$qb->getDQL(), $qb->getParameters()];
    }

    public function getAccountsSearchQuery($data): array
    {
        $qb = $this->getHostingRepository()->getSearchQueryBuilder($data);

        return [$qb->getDQL(), $qb->getParameters()];
    }

    public function createServer($name, $ip, $manager, $data): int
    {
        $em = $this->di['em'];
        $server = new HostingServer();
        $server->setName($name);
        $server->setIp($ip);

        $server->setHostname($data['hostname'] ?? null);
        $assigned_ips = $data['assigned_ips'] ?? '';
        if (!empty($assigned_ips)) {
            $server->setAssignedIps(self::processAssignedIPs($assigned_ips));
        }

        $server->setActive($data['active'] ?? true);
        $server->setStatusUrl($data['status_url'] ?? null);
        $server->setMaxAccounts($data['max_accounts'] ?? null);

        $server->setNs1($data['ns1'] ?? null);
        $server->setNs2($data['ns2'] ?? null);
        $server->setNs3($data['ns3'] ?? null);
        $server->setNs4($data['ns4'] ?? null);

        $server->setManager($manager);
        $server->setUsername($data['username'] ?? null);
        $server->setPassword($data['password'] ?? null);
        $server->setAccessHash($data['accesshash'] ?? null);
        $server->setPort($data['port'] ?? null);
        $server->setPasswordLength(is_numeric($data['passwordLength']) ? intval($data['passwordLength']) : null);
        $server->setSecure($data['secure'] ?? false);

        $em->persist($server);
        $em->flush();

        $newId = $server->getId();
        $this->di['logger']->info('Added new hosting server %s', $newId);

        return $newId;
    }

    public function deleteServer(HostingServer $model): bool
    {
        $id = $model->getId();
        $em = $this->di['em'];
        $em->remove($model);
        $em->flush();
        $this->di['logger']->info('Deleted hosting server %s', $id);

        return true;
    }

    public function updateServer(HostingServer $model, array $data): bool
    {
        if (isset($data['name'])) {
            $model->setName($data['name']);
        }
        if (isset($data['ip'])) {
            $model->setIp($data['ip']);
        }
        if (isset($data['hostname'])) {
            $model->setHostname($data['hostname']);
        }

        $assigned_ips = $data['assigned_ips'] ?? '';
        if (!empty($assigned_ips)) {
            $model->setAssignedIps(self::processAssignedIPs($assigned_ips));
        }

        if (isset($data['active'])) {
            $model->setActive($data['active']);
        }
        if (isset($data['status_url'])) {
            $model->setStatusUrl($data['status_url']);
        }
        if (isset($data['max_accounts'])) {
            $model->setMaxAccounts($data['max_accounts']);
        }
        if (isset($data['ns1'])) {
            $model->setNs1($data['ns1']);
        }
        if (isset($data['ns2'])) {
            $model->setNs2($data['ns2']);
        }
        if (isset($data['ns3'])) {
            $model->setNs3($data['ns3']);
        }
        if (isset($data['ns4'])) {
            $model->setNs4($data['ns4']);
        }
        if (isset($data['manager'])) {
            $model->setManager($data['manager']);
        }
        if (isset($data['port'])) {
            $model->setPort(is_numeric($data['port']) ? $data['port'] : null);
        }
        if (isset($data['config'])) {
            $model->setConfig(json_encode($data['config']));
        }
        if (isset($data['secure'])) {
            $model->setSecure($data['secure']);
        }
        if (isset($data['username'])) {
            $model->setUsername($data['username']);
        }
        if (isset($data['password'])) {
            $model->setPassword($data['password']);
        }
        if (isset($data['accesshash'])) {
            $model->setAccessHash($data['accesshash']);
        }
        if (is_numeric($data['passwordLength'] ?? null)) {
            $model->setPasswordLength($data['passwordLength']);
        }

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Update hosting server %s', $model->getId());

        return true;
    }

    public function getServerManager(HostingServer $model)
    {
        if (empty($model->getManager())) {
            throw new Exception('Invalid server manager. Server was not configured properly.', null, 654);
        }

        $config = [];
        $config['ip'] = $model->getIp();
        $config['host'] = $model->getHostname();
        $config['port'] = $model->getPort();
        $config['config'] = [];
        $config['config'] = json_decode($model->getConfig() ?? '', true) ?? [];
        $config['secure'] = $model->isSecure();
        $config['username'] = $model->getUsername();
        $config['password'] = $model->getPassword();
        $config['accesshash'] = $model->getAccessHash();
        $config['passwordLength'] = $model->getPasswordLength();

        $manager = $this->di['server_manager']($model->getManager(), $config);

        if (!$manager instanceof \Server_Manager) {
            throw new Exception('Server manager :adapter is invalid.', [':adapter' => $model->getManager()]);
        }

        return $manager;
    }

    public function testConnection(HostingServer $model)
    {
        $manager = $this->getServerManager($model);

        return $manager->testConnection();
    }

    public function getHpPairs(): array
    {
        return $this->getPlanRepository()->getPairs();
    }

    public function getHpSearchQuery($data): array
    {
        $qb = $this->getPlanRepository()->getSearchQueryBuilder($data);

        return [$qb->getDQL(), $qb->getParameters()];
    }

    public function deleteHp(HostingPlan $model): bool
    {
        $id = $model->getId();
        $hostings = $this->getHostingRepository()->findBy(['plan' => $model]);
        if (!empty($hostings)) {
            throw new InformationException('Cannot remove hosting plan which has active accounts');
        }
        $em = $this->di['em'];
        $em->remove($model);
        $em->flush();
        $this->di['logger']->info('Deleted hosting plan %s', $id);

        return true;
    }

    public function toHostingHpApiArray(HostingPlan $model, $deep = false, $identity = null): array
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
            'created_at' => $model->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $model->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function updateHp(HostingPlan $model, array $data): bool
    {
        if (isset($data['name'])) {
            $model->setName($data['name']);
        }
        if (isset($data['bandwidth'])) {
            $model->setBandwidth($data['bandwidth']);
        }
        if (isset($data['quota'])) {
            $model->setQuota($data['quota']);
        }
        if (isset($data['max_addon'])) {
            $model->setMaxAddon($data['max_addon']);
        }
        if (isset($data['max_ftp'])) {
            $model->setMaxFtp($data['max_ftp']);
        }
        if (isset($data['max_sql'])) {
            $model->setMaxSql($data['max_sql']);
        }
        if (isset($data['max_pop'])) {
            $model->setMaxPop($data['max_pop']);
        }
        if (isset($data['max_sub'])) {
            $model->setMaxSub($data['max_sub']);
        }
        if (isset($data['max_park'])) {
            $model->setMaxPark($data['max_park']);
        }

        $config = json_decode($model->getConfig() ?? '', true) ?? [];

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

        $model->setConfig(json_encode($config));

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Updated hosting plan %s', $model->getId());

        return true;
    }

    public function createHp($name, $data): int
    {
        $em = $this->di['em'];
        $plan = new HostingPlan();
        $plan->setName($name);

        $plan->setBandwidth($data['bandwidth'] ?? '1048576');
        $plan->setQuota($data['quota'] ?? '1048576');

        $plan->setMaxAddon($data['max_addon'] ?? 1);
        $plan->setMaxPark($data['max_park'] ?? 1);
        $plan->setMaxSub($data['max_sub'] ?? 1);
        $plan->setMaxPop($data['max_pop'] ?? 1);
        $plan->setMaxSql($data['max_sql'] ?? 1);
        $plan->setMaxFtp($data['max_ftp'] ?? 1);

        $em->persist($plan);
        $em->flush();

        $newId = $plan->getId();
        $this->di['logger']->info('Added new hosting plan %s', $newId);

        return $newId;
    }

    public function getServerPackage(HostingPlan $model): \Server_Package
    {
        $config = json_decode($model->getConfig() ?? '', true);
        if (!is_array($config)) {
            $config = [];
        }

        $p = new \Server_Package();
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

    public function getServerManagerWithLog(HostingServer $model, \Model_ClientOrder $order)
    {
        $manager = $this->getServerManager($model);

        $order_service = $this->di['mod_service']('order');
        $log = $order_service->getLogger($order);
        $manager->setLog($log);

        return $manager;
    }

    public function getManagerUrls(HostingServer $model): array
    {
        try {
            $m = $this->getServerManager($model);

            return [$m->getLoginUrl(null), $m->getResellerLoginUrl(null)];
        } catch (\Exception $e) {
            error_log("Error while retrieving control panel url: {$e->getMessage()}.");
        }

        return [false, false];
    }

    public function generateLoginUrl(Hosting $model): string
    {
        [$adapter, $account] = $this->_getAM($model);
        if ($model->isReseller()) {
            return $adapter->getResellerLoginUrl($account);
        }

        return $adapter->getLoginUrl($account);
    }

    public function prependOrderConfig(\Model_Product $product, array $data): array
    {
        $c = json_decode($product->config ?? '', true) ?? [];

        if (isset($data['domain']['action'])) {
            $this->validateDomainAction($data, $c);
        }

        [$sld, $tld] = $this->_getDomainTuple($data);
        $data['sld'] = $sld;
        $data['tld'] = $tld;

        return array_merge($c, $data);
    }

    private function validateDomainAction(array $data, array $productConfig): void
    {
        $action = $data['domain']['action'];

        $allowRegister = $productConfig['allow_domain_register'] ?? true;
        $allowTransfer = $productConfig['allow_domain_transfer'] ?? true;
        $allowOwn = $productConfig['allow_domain_own'] ?? true;

        match ($action) {
            'register' => $allowRegister || throw new InformationException('Domain registration is not available for this product.'),
            'transfer' => $allowTransfer || throw new InformationException('Domain transfer is not available for this product.'),
            'owndomain' => $allowOwn || throw new InformationException('Using your own domain is not allowed for this product.'),
            default => throw new InformationException('Invalid domain action specified.'),
        };
    }

    public function getDomainProductFromConfig(\Model_Product $product, array &$data): bool|array
    {
        $data = $this->prependOrderConfig($product, $data);
        $product->getService()->validateOrderData($data);

        $c = json_decode($product->config ?? '', true) ?? [];

        $dc = $data['domain'];
        $action = $dc['action'];

        $domainHandler = null;
        if (isset($this->di['product_type_registry'])) {
            $registry = $this->di['product_type_registry'];
            if ($registry instanceof \FOSSBilling\ProductTypeRegistry && $registry->has('domain')) {
                $domainHandler = $registry->getHandler('domain');
            }
        }
        if ($domainHandler === null) {
            throw new Exception('Domain product type is not available');
        }

        if (method_exists($domainHandler, 'validateOrderData')) {
            $domainHandler->validateOrderData($dc);
        }
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
        $config = json_decode($product->config ?? '', true) ?? [];
        $freeTlds = $config['free_tlds'] ?? [];
        $result = [];
        foreach ($freeTlds as $tld) {
            $result[] = ['tld' => $tld];
        }

        if (empty($result)) {
            $tlds = $this->di['db']->find('Tld', 'active = 1 and allow_register = 1', []);
            $domainHandler = $this->di['product_type_registry']->getHandler('domain');
            foreach ($tlds as $model) {
                $result[] = $domainHandler->tldToApiArray($model);
            }
        }

        return $result;
    }

    public static function processAssignedIPs(string $assigned_ips): string
    {
        $array = preg_split('/\r\n|\r|\n/', $assigned_ips);
        $array = array_map(trim(...), $array);
        $array = array_filter($array, fn ($ip): bool => $ip !== '');
        $array = array_filter($array, fn ($ip): mixed => filter_var($ip, FILTER_VALIDATE_IP));

        return json_encode(array_values($array));
    }
}
