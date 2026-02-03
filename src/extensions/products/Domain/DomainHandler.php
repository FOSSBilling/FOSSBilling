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

namespace FOSSBilling\ProductType\Domain;

use FOSSBilling\InjectionAwareInterface;
use FOSSBilling\Interfaces\ProductTypeHandlerInterface;
use FOSSBilling\ProductType\Domain\Entity\Domain;
use FOSSBilling\ProductType\Domain\Entity\Tld;
use FOSSBilling\ProductType\Domain\Entity\TldRegistrar;
use FOSSBilling\ProductType\Domain\Repository\DomainRepository;
use FOSSBilling\ProductType\Domain\Repository\TldRegistrarRepository;
use FOSSBilling\ProductType\Domain\Repository\TldRepository;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

class DomainHandler implements ProductTypeHandlerInterface, InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;
    private $domainRepository = null;
    private $tldRepository = null;
    private $registrarRepository = null;
    private readonly Filesystem $filesystem;

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

    protected function getDomainRepository(): DomainRepository
    {
        if ($this->domainRepository === null) {
            $this->domainRepository = $this->di['em']->getRepository(Domain::class);
        }

        return $this->domainRepository;
    }

    protected function getTldRepository()
    {
        if ($this->tldRepository === null) {
            /** @var TldRepository $repo */
            $repo = $this->di['em']->getRepository(Tld::class);
            $this->tldRepository = $repo;
        }

        return $this->tldRepository;
    }

    protected function getRegistrarRepository()
    {
        if ($this->registrarRepository === null) {
            /** @var TldRegistrarRepository $repo */
            $repo = $this->di['em']->getRepository(TldRegistrar::class);
            $this->registrarRepository = $repo;
        }

        return $this->registrarRepository;
    }

    protected function loadDomainEntity(int $id): Domain
    {
        $entity = $this->getDomainRepository()->find($id);
        if (!$entity instanceof Domain) {
            throw new \FOSSBilling\Exception('Domain not found');
        }

        return $entity;
    }

    protected function loadTldEntity(int $id): Tld
    {
        $entity = $this->getTldRepository()->find($id);
        if (!$entity instanceof Tld) {
            throw new \FOSSBilling\Exception('TLD not found');
        }

        return $entity;
    }

    protected function loadRegistrarEntity(int $id): TldRegistrar
    {
        $entity = $this->getRegistrarRepository()->find($id);
        if (!$entity instanceof TldRegistrar) {
            throw new \FOSSBilling\Exception('Registrar not found');
        }

        return $entity;
    }

    public function getCartProductTitle($product, array $data)
    {
        if (
            isset($data['action']) && $data['action'] == 'register'
            && isset($data['register_tld']) && isset($data['register_sld'])
        ) {
            return __trans('Domain :domain registration', [':domain' => $data['register_sld'] . $data['register_tld']]);
        }

        if (
            isset($data['action']) && $data['action'] == 'transfer'
            && isset($data['transfer_tld']) && isset($data['transfer_sld'])
        ) {
            return __trans('Domain :domain transfer', [':domain' => $data['transfer_sld'] . $data['transfer_tld']]);
        }

        return $product->title;
    }

    public function validateOrderData(&$data): void
    {
        $validator = $this->di['validator'];

        $required = [
            'action' => 'Are you registering new domain or transferring existing? Action parameter missing',
        ];
        $validator->checkRequiredParamsForArray($required, $data);

        $action = $data['action'];
        if (!in_array($action, ['register', 'transfer', 'owndomain'])) {
            throw new \FOSSBilling\Exception('Invalid domain action.');
        }

        if ($action == 'owndomain') {
            $required = [
                'owndomain_tld' => 'Domain TLD is required.',
                'owndomain_sld' => 'Domain name is required.',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data);

            if (!$validator->isSldValid($data['owndomain_sld'])) {
                $safe_dom = htmlspecialchars((string) $data['owndomain_sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
            }

            if (!$validator->isTldValid($data['owndomain_tld'])) {
                throw new \FOSSBilling\InformationException('TLD is invalid');
            }
        }

        if ($action == 'transfer') {
            $required = [
                'transfer_tld' => 'Transfer domain type (TLD) is required.',
                'transfer_sld' => 'Transfer domain name (SLD) is required.',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data);

            if (!$validator->isSldValid($data['transfer_sld'])) {
                $safe_dom = htmlspecialchars((string) $data['transfer_sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
            }

            if (!$validator->isTldValid($data['transfer_tld'])) {
                throw new \FOSSBilling\InformationException('TLD is invalid');
            }

            $tld = $this->tldFindOneByTld($data['transfer_tld']);

            if (!$tld instanceof Tld) {
                throw new \FOSSBilling\InformationException('TLD :tld is not available for transfer', [':tld' => $data['transfer_tld']]);
            }

            if ($tld->getAllowTransfer() !== true) {
                throw new \FOSSBilling\InformationException('TLD :tld is not available for transfer', [':tld' => $data['transfer_tld']]);
            }

            if (!$this->canBeTransferred($tld, $data['transfer_sld'])) {
                throw new \FOSSBilling\InformationException('Domain cannot be transferred.');
            }
        }

        if ($action == 'register') {
            $required = [
                'register_tld' => 'Register domain type (TLD) is required.',
                'register_sld' => 'Register domain name (SLD) is required.',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data);

            if (!$validator->isSldValid($data['register_sld'])) {
                $safe_dom = htmlspecialchars((string) $data['register_sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
            }

            if (!$validator->isTldValid($data['register_tld'])) {
                throw new \FOSSBilling\InformationException('TLD is invalid');
            }

            $tld = $this->tldFindOneByTld($data['register_tld']);

            if (!$tld instanceof Tld) {
                throw new \FOSSBilling\InformationException('TLD :tld is not available for registration', [':tld' => $data['register_tld']]);
            }

            if ($tld->getAllowRegister() !== true) {
                throw new \FOSSBilling\InformationException('TLD :tld is not available for registration', [':tld' => $data['register_tld']]);
            }

            $data['register_years'] = $data['register_years'] ?? $tld->getMinYears() ?? 1;
            $years = (int) $data['register_years'];
            if ($years < $tld->getMinYears()) {
                throw new \FOSSBilling\InformationException('Minimum registration period is :min years', [':min' => $tld->getMinYears()]);
            }

            $data['register_years'] = $years;

            if (!$this->isDomainAvailable($tld, $data['register_sld'])) {
                throw new \FOSSBilling\InformationException('Domain :domain is not available for registration', [':domain' => $data['register_sld'] . $data['register_tld']]);
            }
        }
    }

    public function generateOrderTitle(array $config): ?string
    {
        return match ($config['action']) {
            'transfer' => $config['transfer_sld'] . $config['transfer_tld'],
            'register' => $config['register_sld'] . $config['register_tld'],
            default => null,
        };
    }

    public function create(\Model_ClientOrder $order): Domain
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);

        $this->validateOrderData($c);

        [$sld, $tldStr] = $this->_getTuple($c);
        $years = $c['register_years'] ?? 1;

        $systemService = $this->di['mod_service']('system');
        $ns = $systemService->getNameservers();
        if (empty($ns)) {
            throw new \FOSSBilling\InformationException('Default domain nameservers are not configured');
        }

        $tldModel = $this->tldFindOneByTld($tldStr);

        $domain = new Domain($order->client_id);
        $domain->setSld($sld);
        $domain->setTld($tldStr);
        $domain->setPeriod($years);
        $domain->setTransferCode($c['transfer_code'] ?? null);
        $domain->setPrivacy(null);
        $domain->setAction($c['action']);
        $domain->setNs1((isset($c['ns1']) && !empty($c['ns1'])) ? $c['ns1'] : $ns['nameserver_1']);
        $domain->setNs2((isset($c['ns2']) && !empty($c['ns2'])) ? $c['ns2'] : $ns['nameserver_2']);
        $domain->setNs3((isset($c['ns3']) && !empty($c['ns3'])) ? $c['ns3'] : $ns['nameserver_3']);
        $domain->setNs4((isset($c['ns4']) && !empty($c['ns4'])) ? $c['ns4'] : $ns['nameserver_4']);

        if ($tldModel instanceof Tld) {
            $domain->setRegistrar($tldModel->getRegistrar());
        }

        $client = $this->di['db']->load('Client', $order->client_id);

        $domain->setContactFirstName($client->first_name);
        $domain->setContactLastName($client->last_name);
        $domain->setContactEmail($client->email);
        $domain->setContactCompany($client->company);
        $domain->setContactAddress1($client->address_1);
        $domain->setContactAddress2($client->address_2);
        $domain->setContactCountry($client->country);
        $domain->setContactCity($client->city);
        $domain->setContactState($client->state);
        $domain->setContactPostcode($client->postcode);
        $domain->setContactPhoneCc($client->phone_cc);
        $domain->setContactPhone($client->phone);

        $em = $this->di['em'];
        $em->persist($domain);
        $em->flush();

        return $domain;
    }

    public function activate(\Model_ClientOrder $order): Domain
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        if (!$model instanceof Domain) {
            throw new \FOSSBilling\Exception('Could not activate order. Service was not created');
        }

        [$domain, $adapter] = $this->_getD($model);

        if ($model->getAction() == 'register') {
            $adapter->registerDomain($domain);
        }

        if ($model->getAction() == 'transfer') {
            $adapter->transferDomain($domain);
        }

        $model->setAction(null);

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        try {
            $this->syncWhois($model, $order);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return $model;
    }

    public function renew(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        if (!$model instanceof Domain) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->id]);
        }

        [$domain, $adapter] = $this->_getD($model);
        $adapter->renewDomain($domain);

        $this->syncWhois($model, $order);

        return true;
    }

    public function suspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function unsuspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function onBeforeAdminCronRun(): void
    {
    }

    public function cancel(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);

        if (!$model instanceof Domain) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->id]);
        }

        [$domain, $adapter] = $this->_getD($model);
        $adapter->deleteDomain($domain);

        return true;
    }

    public function uncancel(\Model_ClientOrder $order): bool
    {
        $this->activate($order);

        return true;
    }

    public function delete(\Model_ClientOrder $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);

        if ($service instanceof Domain) {
            if ($order->status != \Model_ClientOrder::STATUS_CANCELED) {
                $this->cancel($order);
            }

            $em = $this->di['em'];
            $em->remove($service);
            $em->flush();
        }
    }

    public function tldFindOneByTld(string $tld): ?Tld
    {
        return $this->getTldRepository()->findOneByTld($tld);
    }

    public function tldCreate(array $data): int
    {
        $model = new Tld();
        $model->setTld($data['tld']);
        if (isset($data['tld_registrar_id'])) {
            $registrar = $this->loadRegistrarEntity($data['tld_registrar_id']);
            $model->setRegistrar($registrar);
        }
        $model->setPriceRegistration($data['price_registration'] ?? 0);
        $model->setPriceRenew($data['price_renew'] ?? 0);
        $model->setPriceTransfer($data['price_transfer'] ?? 0);
        $model->setMinYears($data['min_years'] ?? 1);
        $model->setAllowRegister($data['allow_register'] ?? false);
        $model->setAllowTransfer($data['allow_transfer'] ?? false);
        $model->setActive($data['active'] ?? true);

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Created new top level domain %s', $model->getTld());

        return $model->getId();
    }

    protected function syncWhois(Domain $model, \Model_ClientOrder $order)
    {
        [$domain, $adapter] = $this->_getD($model);

        $whois = $adapter->getDomainDetails($domain);

        $locked = $whois->getLocked();
        if ($locked !== null) {
            $model->setLocked($locked);
        }

        $contact = $whois->getContactRegistrar();

        $model->setContactFirstName($contact->getFirstName());
        $model->setContactLastName($contact->getLastName());
        $model->setContactEmail($contact->getEmail());
        $model->setContactCompany($contact->getCompany());
        $model->setContactAddress1($contact->getAddress1());
        $model->setContactAddress2($contact->getAddress2());
        $model->setContactCountry($contact->getCountry());
        $model->setContactCity($contact->getCity());
        $model->setContactState($contact->getState());
        $model->setContactPostcode($contact->getZip());
        $model->setContactPhoneCc($contact->getTelCc());
        $model->setContactPhone($contact->getTel());

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $now = date('Y-m-d H:i:s');
        $order->updated_at = $now;
        $this->di['db']->store($order);
    }

    public function toApiArray(Domain $model, $deep = false, $identity = null): array
    {
        $data = $model->toApiArray();
        if ($identity instanceof \Model_Admin) {
            $data['tld_registrar_id'] = $model->getRegistrar()?->getId();
        }

        return $data;
    }

    private function _getTuple(array $data): array
    {
        $sld = match ($data['action']) {
            'register' => $data['register_sld'] ?? null,
            'transfer' => $data['transfer_sld'] ?? null,
            'owndomain' => $data['owndomain_sld'] ?? null,
            default => null,
        };

        $tld = match ($data['action']) {
            'register' => $data['register_tld'] ?? null,
            'transfer' => $data['transfer_tld'] ?? null,
            'owndomain' => $data['owndomain_tld'] ?? null,
            default => null,
        };

        return [$sld, $tld];
    }

    protected function _getD(Domain $model): array
    {
        $registrar = $model->getRegistrar();
        if ($registrar === null) {
            throw new \FOSSBilling\Exception('Domain registrar is not set');
        }

        $adapter = $this->getRegistrarAdapter($registrar);

        $domain = new \Registrar_Domain();
        $domain->setSld($model->getSld());
        $domain->setTld($model->getTld());
        $domain->setRegistrationPeriod($model->getPeriod());
        $domain->setEpp($model->getTransferCode());
        $domain->setNs1($model->getNs1());
        $domain->setNs2($model->getNs2());
        $domain->setNs3($model->getNs3());
        $domain->setNs4($model->getNs4());
        /* @phpstan-ignore-next-line */
        $domain->setContactRegistrar(new \Registrar_Domain_Contact([
            'firstName' => $model->getContactFirstName(),
            'lastName' => $model->getContactLastName(),
            'email' => $model->getContactEmail(),
            'company' => $model->getContactCompany(),
            'address1' => $model->getContactAddress1(),
            'address2' => $model->getContactAddress2(),
            'country' => $model->getContactCountry(),
            'city' => $model->getContactCity(),
            'state' => $model->getContactState(),
            'postcode' => $model->getContactPostcode(),
            'phoneCc' => $model->getContactPhoneCc(),
            'phone' => $model->getContactPhone(),
        ]));

        return [$domain, $adapter];
    }

    protected function getRegistrarAdapter(TldRegistrar $registrar): \Registrar_AdapterAbstract
    {
        $config = json_decode($registrar->getConfig() ?? '', true) ?? [];

        $class = 'Registrar_Adapter_' . $registrar->getRegistrar();
        if (!class_exists($class)) {
            $file = Path::join(PATH_LIBRARY, 'Server', 'Manager', ucfirst($registrar->getRegistrar()) . '.php');
            if (!is_file($file)) {
                throw new \FOSSBilling\Exception(' Registrar module :registrar was not found', [':registrar' => $registrar->getRegistrar()]);
            }

            require_once $file;
        }

        return new $class($config);
    }

    public function canBeTransferred(Tld $model, string $sld): bool
    {
        if (empty($sld)) {
            throw new \FOSSBilling\InformationException('Domain name is invalid');
        }

        if (!$model->getAllowTransfer()) {
            throw new \FOSSBilling\InformationException('Domain cannot be transferred', null, 403);
        }

        $domain = new \Registrar_Domain();
        $domain->setTld($model->getTld());
        $domain->setSld($sld);

        $registrar = $model->getRegistrar();
        if ($registrar === null) {
            throw new \FOSSBilling\Exception('TLD registrar is not set');
        }
        $adapter = $this->getRegistrarAdapter($registrar);

        return $adapter->isDomaincanBeTransferred($domain);
    }

    public function isDomainAvailable(Tld $model, string $sld): bool
    {
        if (empty($sld)) {
            throw new \FOSSBilling\InformationException('Domain name is invalid');
        }

        $validator = $this->di['validator'];
        if (!$validator->isSldValid($sld)) {
            $safe_dom = htmlspecialchars((string) $sld, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
        }

        if (!$model->getAllowRegister()) {
            throw new \FOSSBilling\InformationException('Domain cannot be registered', null, 403);
        }

        $domain = new \Registrar_Domain();
        $domain->setTld($model->getTld());
        $domain->setSld($sld);

        $registrar = $model->getRegistrar();
        if ($registrar === null) {
            throw new \FOSSBilling\Exception('TLD registrar is not set');
        }
        $adapter = $this->getRegistrarAdapter($registrar);

        return $adapter->isDomainAvailable($domain);
    }

    public function lock(Domain $model): bool
    {
        $registrar = $model->getRegistrar();
        if ($registrar === null) {
            throw new \FOSSBilling\Exception('TLD registrar is not set');
        }

        $adapter = $this->getRegistrarAdapter($registrar);

        $domain = new \Registrar_Domain();
        $domain->setTld($model->getTld());
        $domain->setSld($model->getSld());

        $adapter->lock($domain);

        $model->setLocked(true);

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return true;
    }

    public function unlock(Domain $model): bool
    {
        $registrar = $model->getRegistrar();
        if ($registrar === null) {
            throw new \FOSSBilling\Exception('TLD registrar is not set');
        }

        $adapter = $this->getRegistrarAdapter($registrar);

        $domain = new \Registrar_Domain();
        $domain->setTld($model->getTld());
        $domain->setSld($model->getSld());

        $adapter->unlock($domain);

        $model->setLocked(false);

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return true;
    }

    public function registrarGetRegistrarAdapterConfig(TldRegistrar $model): array
    {
        $class = $this->registrarGetRegistrarAdapterClassName($model);

        return call_user_func([$class, 'getConfig']);
    }

    private function registrarGetRegistrarAdapterClassName(TldRegistrar $model): string
    {
        if (!$this->filesystem->exists(Path::join(PATH_LIBRARY, 'Registrar', 'Adapter', "{$model->getRegistrar()}.php"))) {
            throw new \FOSSBilling\Exception('Domain registrar :adapter was not found', [':adapter' => $model->getRegistrar()]);
        }

        $class = sprintf('Registrar_Adapter_%s', $model->getRegistrar());
        if (!class_exists($class)) {
            throw new \FOSSBilling\Exception('Registrar :adapter was not found', [':adapter' => $class]);
        }

        return $class;
    }

    public function registrarRm(TldRegistrar $model): bool
    {
        $domains = $this->di['db']->find('ServiceDomain', 'tld_registrar_id = :registrar_id', [':registrar_id' => $model->getId()]);
        $count = is_countable($domains) ? count($domains) : 0;

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Registrar is used by :count domains', [':count' => $count], 707);
        }

        $em = $this->di['em'];
        $em->remove($model);
        $em->flush();

        $this->di['logger']->info('Removed domain registrar %s', $model->getName());

        return true;
    }

    public function registrarGetConfiguration(TldRegistrar $model): array
    {
        return json_decode($model->getConfig() ?? '', true) ?? [];
    }

    public function tldFindAllActive(): array
    {
        return $this->getTldRepository()->findBy(['active' => true], ['id' => 'ASC']);
    }

    public function tldFindOneActiveById(int $id): ?Tld
    {
        return $this->getTldRepository()->findOneBy(['id' => $id, 'active' => true], ['id' => 'ASC']);
    }

    public function tldGetPairs(): array
    {
        $tlds = $this->getTldRepository()->findBy(['active' => true], ['id' => 'ASC']);
        $pairs = [];
        foreach ($tlds as $tld) {
            $pairs[$tld->getId()] = $tld->getTld();
        }

        return $pairs;
    }

    public function tldAlreadyRegistered(string $tld): bool
    {
        $tldModel = $this->tldFindOneByTld($tld);

        return $tldModel instanceof Tld;
    }

    public function tldRm(Tld $model): bool
    {
        $id = $model->getId();
        $em = $this->di['em'];
        $em->remove($model);
        $em->flush();

        $this->di['logger']->info('Deleted top level domain %s', $id);

        return true;
    }

    public function tldToApiArray(Tld $model): array
    {
        $registrar = $model->getRegistrar();

        return [
            'id' => $model->getId(),
            'tld' => $model->getTld(),
            'price_registration' => $model->getPriceRegistration(),
            'price_renew' => $model->getPriceRenew(),
            'price_transfer' => $model->getPriceTransfer(),
            'active' => $model->isActive(),
            'allow_register' => $model->getAllowRegister(),
            'allow_transfer' => $model->getAllowTransfer(),
            'min_years' => $model->getMinYears(),
            'registrar' => [
                'id' => $registrar?->getId(),
                'title' => $registrar?->getName(),
            ],
        ];
    }

    public function tldFindOneById(int $id): ?Tld
    {
        return $this->getTldRepository()->find($id);
    }

    public function registrarGetSearchQuery(array $data): array
    {
        $query = 'SELECT * FROM tld_registrar ORDER BY name ASC';
        $bindings = [];

        return [$query, $bindings];
    }

    public function registrarGetAvailable(): array
    {
        $query = "SELECT 'registrar', 'name' FROM tld_registrar GROUP BY registrar";

        $exists = $this->di['db']->getAssoc($query);

        $adapters = [];

        $finder = new \Symfony\Component\Finder\Finder();
        $finder->files()->in(Path::join(PATH_LIBRARY, 'Registrar', 'Adapter'))->name('*.php')->depth('== 0');
        foreach ($finder as $file) {
            $adapter = $file->getFilenameWithoutExtension();
            if (!array_key_exists($adapter, $exists)) {
                $adapters[] = $adapter;
            }
        }

        return $adapters;
    }

    public function registrarGetPairs(): array
    {
        $registrars = $this->getRegistrarRepository()->findAll();
        $pairs = [];
        foreach ($registrars as $registrar) {
            $pairs[$registrar->getId()] = $registrar->getName();
        }

        return $pairs;
    }

    public function registrarGetActiveRegistrar(): ?TldRegistrar
    {
        $registrars = $this->getRegistrarRepository()->findAll();
        foreach ($registrars as $registrar) {
            $config = $registrar->getConfig();
            if (!empty($config)) {
                return $registrar;
            }
        }

        return null;
    }

    public function registrarCreate(string $code): bool
    {
        $model = new TldRegistrar();
        $model->setName($code);
        $model->setRegistrar($code);
        $model->setTestMode(0);

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Installed new domain registrar %s', $code);

        return true;
    }

    public function registrarCopy(TldRegistrar $model): int
    {
        $new = new TldRegistrar();
        $new->setName($model->getName() . ' (Copy)');
        $new->setRegistrar($model->getRegistrar());
        $new->setTestMode($model->getTestMode());

        $em = $this->di['em'];
        $em->persist($new);
        $em->flush();

        $this->di['logger']->info('Copied domain registrar %s', $model->getRegistrar());

        return $new->getId();
    }

    public function registrarUpdate(TldRegistrar $model, array $data): bool
    {
        $model->setName($data['title'] ?? $model->getName());
        $model->setTestMode($data['test_mode'] ?? $model->getTestMode());
        if (isset($data['config']) && is_array($data['config'])) {
            $model->setConfig(json_encode($data['config']));
        }

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Updated domain registrar %s configuration', $model->getRegistrar());

        return true;
    }

    public function registrarToApiArray(TldRegistrar $model): array
    {
        $c = $this->registrarGetRegistrarAdapterConfig($model);

        return [
            'id' => $model->getId(),
            'title' => $model->getName(),
            'label' => $c['label'] ?? null,
            'config' => $this->registrarGetConfiguration($model),
            'form' => $c['form'] ?? null,
            'test_mode' => $model->getTestMode(),
        ];
    }

    public function updateDomain(Domain $s, array $data): bool
    {
        $s->setNs1($data['ns1'] ?? $s->getNs1());
        $s->setNs2($data['ns2'] ?? $s->getNs2());
        $s->setNs3($data['ns3'] ?? $s->getNs3());
        $s->setNs4($data['ns4'] ?? $s->getNs4());
        $s->setPeriod((int) ($data['period'] ?? $s->getPeriod()));
        $s->setPrivacy($data['privacy'] ?? $s->getPrivacy());
        $s->setLocked((bool) ($data['locked'] ?? $s->isLocked()));
        $s->setTransferCode($data['transfer_code'] ?? $s->getTransferCode());

        $em = $this->di['em'];
        $em->persist($s);
        $em->flush();

        $this->di['logger']->info('Updated domain #%s without sending actions to server', $s->getId());

        return true;
    }

    public function tldUpdate(Tld $model, array $data): bool
    {
        $model->setTld($data['tld'] ?? $model->getTld());
        if (isset($data['tld_registrar_id'])) {
            $registrar = $this->loadRegistrarEntity($data['tld_registrar_id']);
            $model->setRegistrar($registrar);
        }
        $model->setPriceRegistration($data['price_registration'] ?? $model->getPriceRegistration());
        $model->setPriceRenew($data['price_renew'] ?? $model->getPriceRenew());
        $model->setPriceTransfer($data['price_transfer'] ?? $model->getPriceTransfer());
        $model->setMinYears($data['min_years'] ?? $model->getMinYears());
        $model->setAllowRegister($data['allow_register'] ?? $model->getAllowRegister());
        $model->setAllowTransfer($data['allow_transfer'] ?? $model->getAllowTransfer());
        $model->setActive($data['active'] ?? $model->isActive());

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        $this->di['logger']->info('Updated top level domain %s', $model->getTld());

        return true;
    }

    public function tldGetSearchQuery(array $data): array
    {
        $query = 'SELECT * FROM tld';

        $hideInactive = (bool) ($data['hide_inactive'] ?? false);
        $allowRegister = $data['allow_register'] ?? null;
        $allowTransfer = $data['allow_transfer'] ?? null;

        $where = [];
        $bindings = [];

        if ($hideInactive) {
            $where[] = 'active = 1';
        }

        if ($allowRegister !== null) {
            $where[] = 'allow_register = 1';
        }

        if ($allowTransfer !== null) {
            $where[] = 'allow_transfer = 1';
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY id ASC';

        return [$query, $bindings];
    }

    public function registrarGetRegistrarAdapter(TldRegistrar $r, ?\Model_ClientOrder $order = null): \Registrar_AdapterAbstract
    {
        $config = $this->registrarGetConfiguration($r);
        $class = $this->registrarGetRegistrarAdapterClassName($r);
        $registrar = new $class($config);
        if (!$registrar instanceof \Registrar_AdapterAbstract) {
            throw new \FOSSBilling\Exception('Registrar adapter :adapter should extend Registrar_AdapterAbstract', [':adapter' => $class]);
        }

        $registrar->setLog($this->di['logger']);

        if ($order) {
            $registrar->setOrder($order);
        }

        if ($r->getTestMode()) {
            $registrar->enableTestMode();
        }

        return $registrar;
    }

    public function syncExpirationDate(Domain $model): void
    {
    }

    public function batchSyncExpirationDates(): bool
    {
        return true;
    }

    public function enablePrivacyProtection(Domain $model): bool
    {
        return true;
    }

    public function disablePrivacyProtection(Domain $model): bool
    {
        return true;
    }

    public function getTransferCode(Domain $model): ?string
    {
        return $model->getTransferCode();
    }

    public function updateNameservers(Domain $model, array $data): bool
    {
        $model->setNs1($data['ns1'] ?? $model->getNs1());
        $model->setNs2($data['ns2'] ?? $model->getNs2());
        $model->setNs3($data['ns3'] ?? $model->getNs3());
        $model->setNs4($data['ns4'] ?? $model->getNs4());

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return true;
    }

    public function updateContacts(Domain $model, array $data): array
    {
        $model->setContactFirstName($data['first_name'] ?? $model->getContactFirstName());
        $model->setContactLastName($data['last_name'] ?? $model->getContactLastName());
        $model->setContactEmail($data['email'] ?? $model->getContactEmail());
        $model->setContactCompany($data['company'] ?? $model->getContactCompany());
        $model->setContactAddress1($data['address_1'] ?? $model->getContactAddress1());
        $model->setContactAddress2($data['address_2'] ?? $model->getContactAddress2());
        $model->setContactCountry($data['country'] ?? $model->getContactCountry());
        $model->setContactCity($data['city'] ?? $model->getContactCity());
        $model->setContactState($data['state'] ?? $model->getContactState());
        $model->setContactPostcode($data['postcode'] ?? $model->getContactPostcode());
        $model->setContactPhoneCc($data['phone_cc'] ?? $model->getContactPhoneCc());
        $model->setContactPhone($data['phone'] ?? $model->getContactPhone());

        $em = $this->di['em'];
        $em->persist($model);
        $em->flush();

        return $model->toApiArray();
    }
}
