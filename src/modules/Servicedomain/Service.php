<?php

declare(strict_types=1);
/**
 * Copyright 2022-2025 FOSSBilling
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedomain;

use Box\Mod\Client\Entity\Client;
use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicedomain\Entity\ServiceDomain;
use Box\Mod\Servicedomain\Entity\Tld;
use Box\Mod\Servicedomain\Entity\TldRegistrar;
use Box\Mod\Servicedomain\Repository\DomainRepository;
use Box\Mod\Servicedomain\Repository\TldRegistrarRepository;
use Box\Mod\Servicedomain\Repository\TldRepository as TldRepo;
use Box\Mod\Staff\Entity\Admin;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

class Service implements \FOSSBilling\InjectionAwareInterface
{
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
            'manage_domains' => [
                'type' => 'bool',
                'display_name' => __trans('Manage domains'),
                'description' => __trans('Allows the staff member to manage domain services (nameservers, contacts, privacy, transfers).'),
            ],
            'manage_tlds' => [
                'type' => 'bool',
                'display_name' => __trans('Manage TLDs'),
                'description' => __trans('Allows the staff member to create, update, and delete TLDs and their pricing.'),
            ],
            'manage_registrars' => [
                'type' => 'bool',
                'display_name' => __trans('Manage registrars'),
                'description' => __trans('Allows the staff member to install, update, and delete domain registrars.'),
            ],
        ];
    }

    public function getCartProductTitle(Product $product, array $data): ?string
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

        return $product->getTitle();
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

            $tld = $this->tldFindOneByTld($data['transfer_tld']);
            if (!$tld instanceof Tld) {
                throw new \FOSSBilling\InformationException('TLD not found');
            }

            $domain = $data['transfer_sld'] . $tld->getTld();
            if (!$this->canBeTransferred($tld, $data['transfer_sld'])) {
                throw new \FOSSBilling\InformationException(':domain cannot be transferred!', [':domain' => $domain]);
            }

            $data['period'] = '1Y';
            $data['quantity'] = 1;
        }

        if ($action == 'register') {
            $required = [
                'register_tld' => 'Domain registration tld parameter missing.',
                'register_sld' => 'Domain registration sld parameter missing.',
                'register_years' => 'Domain registration period is missing. Please check domain availability before proceeding.',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data);

            if (!$validator->isSldValid($data['register_sld'])) {
                $safe_dom = htmlspecialchars((string) $data['register_sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
            }

            $tld = $this->tldFindOneByTld($data['register_tld']);
            if (!$tld instanceof Tld) {
                throw new \FOSSBilling\InformationException('TLD not found');
            }

            $years = (int) $data['register_years'];
            if ($years < $tld->getMinYears()) {
                throw new \FOSSBilling\Exception(':tld can be registered for at least :years years', [':tld' => $tld->getTld(), ':years' => $tld->getMinYears()]);
            }

            $domain = $data['register_sld'] . $tld->getTld();
            if (!$this->isDomainAvailable($tld, $data['register_sld'])) {
                throw new \FOSSBilling\InformationException(':domain is already registered!', [':domain' => $domain]);
            }

            $data['period'] = $years . 'Y';
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

    public function action_create(Order $order): ServiceDomain
    {
        $orderService = $this->di['mod_service']('order');
        $c = $orderService->getConfig($order);

        $this->validateOrderData($c);

        [$sld, $tld] = $this->_getTuple($c);
        $years = $c['register_years'] ?? 1;

        $systemService = $this->di['mod_service']('system');
        $ns = $systemService->getNameservers();
        if (empty($ns)) {
            throw new \FOSSBilling\InformationException('Default domain nameservers are not configured');
        }

        $tldModel = $this->tldFindOneByTld($tld);

        $model = new ServiceDomain();
        $model->setClientId($order->getClientId());
        $model->setTldRegistrarId($tldModel->getTldRegistrarId());
        $model->setSld($sld);
        $model->setTld($tld);
        $model->setPeriod($years);
        $model->setTransferCode($c['transfer_code'] ?? null);
        $model->setPrivacy(false);
        $model->setAction($c['action']);
        $model->setNs1((isset($c['ns1']) && !empty($c['ns1'])) ? $c['ns1'] : $ns['nameserver_1']);
        $model->setNs2((isset($c['ns2']) && !empty($c['ns1'])) ? $c['ns2'] : $ns['nameserver_2']);
        $model->setNs3((isset($c['ns3']) && !empty($c['ns1'])) ? $c['ns3'] : $ns['nameserver_3']);
        $model->setNs4((isset($c['ns4']) && !empty($c['ns1'])) ? $c['ns4'] : $ns['nameserver_4']);

        $client = $this->di['em']->getRepository(Client::class)->find($model->getClientId()) ?? throw new \Exception('Client not found');

        $model->setContactFirstName($client->getFirstName());
        $model->setContactLastName($client->getLastName());
        $model->setContactEmail($client->getEmail());
        $model->setContactCompany($client->getCompany());
        $model->setContactAddress1($client->getAddress1());
        $model->setContactAddress2($client->getAddress2());
        $model->setContactCountry($client->getCountry());
        $model->setContactCity($client->getCity());
        $model->setContactState($client->getState());
        $model->setContactPostcode($client->getPostcode());
        $model->setContactPhoneCc($client->getPhoneCc());
        $model->setContactPhone($client->getPhone());

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    /**
     * @return ServiceDomain|ServiceDomain
     */
    public function action_activate(Order $order)
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceDomain) {
            throw new \FOSSBilling\Exception('Could not activate order. Service was not created');
        }

        [$domain, $adapter] = $this->_getD($model);
        if ($this->getModelAction($model) == 'register') {
            $adapter->registerDomain($domain);
        }

        if ($this->getModelAction($model) == 'transfer') {
            $adapter->transferDomain($domain);
        }

        $this->setModelAction($model, null);
        $this->di['em']->persist($model);
        $this->di['em']->flush();

        try {
            $this->syncWhois($model, $order);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return $model;
    }

    public function action_renew(Order $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceDomain) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->getId()]);
        }

        [$domain, $adapter] = $this->_getD($model);
        $adapter->renewDomain($domain);

        $this->syncWhois($model, $order);

        return true;
    }

    public function action_suspend(Order $order): bool
    {
        return true;
    }

    public function action_unsuspend(Order $order): bool
    {
        return true;
    }

    public function action_cancel(Order $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceDomain) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->getId()]);
        }

        [$domain, $adapter] = $this->_getD($model);
        $adapter->deleteDomain($domain);

        return true;
    }

    public function action_uncancel(Order $order): bool
    {
        $this->action_activate($order);

        return true;
    }

    public function action_delete(Order $order): void
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);

        if ($service instanceof ServiceDomain) {
            if ($order->getStatus() != Order::STATUS_CANCELED) {
                $this->action_cancel($order);
            }
            $this->di['em']->remove($service);
            $this->di['em']->flush();
        }
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    protected function syncWhois($model, Order $order)
    {
        [$domain, $adapter] = $this->_getD($model);

        $whois = $adapter->getDomainDetails($domain);

        $locked = $whois->getLocked();
        if ($locked !== null) {
            $this->setModelLocked($model, $locked);
        }

        $privacy = $whois->getPrivacyEnabled();
        if ($privacy !== null) {
            $this->setModelPrivacy($model, $privacy);
        }

        $contact = $whois->getContactRegistrar();

        $this->setModelContactFirstName($model, $contact->getFirstName());
        $this->setModelContactLastName($model, $contact->getLastName());
        $this->setModelContactEmail($model, $contact->getEmail());
        $this->setModelContactCompany($model, $contact->getCompany());
        $this->setModelContactAddress1($model, $contact->getAddress1());
        $this->setModelContactAddress2($model, $contact->getAddress2());
        $this->setModelContactCountry($model, $contact->getCountry());
        $this->setModelContactCity($model, $contact->getCity());
        $this->setModelContactState($model, $contact->getState());
        $this->setModelContactPostcode($model, $contact->getZip());
        $this->setModelContactPhoneCc($model, $contact->getTelCc());
        $this->setModelContactPhone($model, $contact->getTel());

        $this->setModelDetails($model, serialize($whois));
        $this->setModelExpiresAt($model, $this->formatRegistrarTimestamp($whois->getExpirationTime()));
        $this->setModelRegisteredAt($model, $this->formatRegistrarTimestamp($whois->getRegistrationTime()));
        $this->setModelUpdatedAt($model, date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    public function updateNameservers($model, $data): bool
    {
        if (!isset($data['ns1'])) {
            throw new \FOSSBilling\InformationException('Nameserver 1 is required');
        }
        if (!isset($data['ns2'])) {
            throw new \FOSSBilling\InformationException('Nameserver 2 is required');
        }

        $ns1 = $data['ns1'];
        $ns2 = $data['ns2'];
        $ns3 = $data['ns3'] ?? null;
        $ns4 = $data['ns4'] ?? null;

        [$domain, $adapter] = $this->_getD($model);
        $domain->setNs1($ns1);
        $domain->setNs2($ns2);
        $domain->setNs3($ns3);
        $domain->setNs4($ns4);
        $adapter->modifyNs($domain);

        $this->setModelNs1($model, $ns1);
        $this->setModelNs2($model, $ns2);
        $this->setModelNs3($model, $ns3);
        $this->setModelNs4($model, $ns4);
        $this->setModelUpdatedAt($model, date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = $this->getModelId($model);
        $this->di['logger']->info('Updated domain #%s nameservers', $id);

        return true;
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    public function updateContacts($model, $data): bool
    {
        $required = [
            'contact' => 'Required field contact is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $contact = $data['contact'];

        $required = [
            'first_name' => 'Required field first_name is missing',
            'last_name' => 'Required field last_name is missing',
            'email' => 'Required field email is missing',
            'address1' => 'Required field address1 is missing',
            'country' => 'Required field country is missing',
            'city' => 'Required field city is missing',
            'state' => 'Required field state is missing',
            'postcode' => 'Required field postcode is missing',
            'phone_cc' => 'Required field phone_cc is missing',
            'phone' => 'Required field phone is missing',
        ];
        $this->di['validator']->checkRequiredParamsForArray($required, $contact);

        $this->setModelContactFirstName($model, $contact['first_name']);
        $this->setModelContactLastName($model, $contact['last_name']);
        $this->setModelContactEmail($model, $contact['email']);
        $this->setModelContactCompany($model, $contact['company']);
        $this->setModelContactAddress1($model, $contact['address1']);
        $this->setModelContactAddress2($model, $contact['address2']);
        $this->setModelContactCountry($model, $contact['country']);
        $this->setModelContactCity($model, $contact['city']);
        $this->setModelContactState($model, $contact['state']);
        $this->setModelContactPostcode($model, $contact['postcode']);
        $this->setModelContactPhoneCc($model, $contact['phone_cc']);
        $this->setModelContactPhone($model, $contact['phone']);

        [$domain, $adapter] = $this->_getD($model);
        $adapter->modifyContact($domain);

        $this->setModelUpdatedAt($model, date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = $this->getModelId($model);
        $this->di['logger']->info('Updated domain #%s WHOIS details', $id);

        return true;
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    public function getTransferCode($model)
    {
        [$domain, $adapter] = $this->_getD($model);

        return $adapter->getEpp($domain);
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    public function lock($model): bool
    {
        [$domain, $adapter] = $this->_getD($model);
        $adapter->lock($domain);

        $this->setModelLocked($model, true);
        $this->setModelUpdatedAt($model, date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = $this->getModelId($model);
        $this->di['logger']->info('Locking domain #%s', $id);

        return true;
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    public function unlock($model): bool
    {
        [$domain, $adapter] = $this->_getD($model);
        $adapter->unlock($domain);

        $this->setModelLocked($model, false);
        $this->setModelUpdatedAt($model, date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = $this->getModelId($model);
        $this->di['logger']->info('Unlocking domain #%s', $id);

        return true;
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    public function enablePrivacyProtection($model): bool
    {
        [$domain, $adapter] = $this->_getD($model);
        $adapter->enablePrivacyProtection($domain);

        $this->setModelPrivacy($model, true);
        $this->setModelUpdatedAt($model, date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = $this->getModelId($model);
        $this->di['logger']->info('Enabled privacy protection of #%s domain', $id);

        return true;
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    public function disablePrivacyProtection($model): bool
    {
        [$domain, $adapter] = $this->_getD($model);
        $adapter->disablePrivacyProtection($domain);

        $this->setModelPrivacy($model, false);
        $this->setModelUpdatedAt($model, date('Y-m-d H:i:s'));

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $id = $this->getModelId($model);
        $this->di['logger']->info('Disabled privacy protection of #%s domain', $id);

        return true;
    }

    /**
     * @param Tld|Tld $model
     */
    public function canBeTransferred($model, $sld)
    {
        if (empty($sld)) {
            throw new \FOSSBilling\InformationException('Domain name is invalid');
        }

        $allowTransfer = $model instanceof Tld ? $model->isAllowTransfer() : $model->isAllowTransfer();
        if (!$allowTransfer) {
            throw new \FOSSBilling\InformationException('Domain cannot be transferred', null, 403);
        }

        $domain = new \Registrar_Domain();
        $domain->setTld($model instanceof Tld ? $model->getTld() : $model->getTld());
        $domain->setSld($sld);

        $tldRegistrarId = $model instanceof Tld ? $model->getTldRegistrarId() : $model->getTldRegistrarId();
        $tldRegistrar = $this->getTldRegistrarRepository()->find($tldRegistrarId);
        $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);

        return $adapter->isDomaincanBeTransferred($domain);
    }

    /**
     * @param Tld|Tld $model
     */
    public function isDomainAvailable($model, $sld)
    {
        if (empty($sld)) {
            throw new \FOSSBilling\InformationException('Domain name is invalid');
        }

        $validator = $this->di['validator'];
        if (!$validator->isSldValid($sld)) {
            $safe_dom = htmlspecialchars((string) $sld, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
        }

        $allowRegister = $model instanceof Tld ? $model->isAllowRegister() : $model->isAllowRegister();
        if (!$allowRegister) {
            throw new \FOSSBilling\InformationException('Domain cannot be registered', null, 403);
        }

        $domain = new \Registrar_Domain();
        $domain->setTld($model instanceof Tld ? $model->getTld() : $model->getTld());
        $domain->setSld($sld);

        $tldRegistrarId = $model instanceof Tld ? $model->getTldRegistrarId() : $model->getTldRegistrarId();
        $tldRegistrar = $this->getTldRegistrarRepository()->find($tldRegistrarId);
        $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);

        return $adapter->isDomainAvailable($domain);
    }

    public function syncExpirationDate($model): void
    {
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    public function toApiArray($model, $deep = false, $identity = null): array
    {
        $isDomain = $model instanceof ServiceDomain;

        $data = [
            'domain' => ($isDomain ? $model->getSld() : $model->getSld()) . ($isDomain ? $model->getTld() : $model->getTld()),
            'sld' => $isDomain ? $model->getSld() : $model->getSld(),
            'tld' => $isDomain ? $model->getTld() : $model->getTld(),
            'ns1' => $isDomain ? $model->getNs1() : $model->getNs1(),
            'ns2' => $isDomain ? $model->getNs2() : $model->getNs2(),
            'ns3' => $isDomain ? $model->getNs3() : $model->getNs3(),
            'ns4' => $isDomain ? $model->getNs4() : $model->getNs4(),
            'period' => $isDomain ? $model->getPeriod() : $model->getPeriod(),
            'privacy' => $isDomain ? $model->getPrivacy() : $model->getPrivacy(),
            'locked' => $isDomain ? $model->isLocked() : $model->isLocked(),
            'registered_at' => $isDomain ? $this->formatDateForApi($model->getRegisteredAt()) : $model->getRegisteredAt(),
            'expires_at' => $isDomain ? $this->formatDateForApi($model->getExpiresAt()) : $model->getExpiresAt(),
            'contact' => [
                'first_name' => $isDomain ? $model->getContactFirstName() : $model->getContactFirstName(),
                'last_name' => $isDomain ? $model->getContactLastName() : $model->getContactLastName(),
                'email' => $isDomain ? $model->getContactEmail() : $model->getContactEmail(),
                'company' => $isDomain ? $model->getContactCompany() : $model->getContactCompany(),
                'address1' => $isDomain ? $model->getContactAddress1() : $model->getContactAddress1(),
                'address2' => $isDomain ? $model->getContactAddress2() : $model->getContactAddress2(),
                'country' => $isDomain ? $model->getContactCountry() : $model->getContactCountry(),
                'city' => $isDomain ? $model->getContactCity() : $model->getContactCity(),
                'state' => $isDomain ? $model->getContactState() : $model->getContactState(),
                'postcode' => $isDomain ? $model->getContactPostcode() : $model->getContactPostcode(),
                'phone_cc' => $isDomain ? $model->getContactPhoneCc() : $model->getContactPhoneCc(),
                'phone' => $isDomain ? $model->getContactPhone() : $model->getContactPhone(),
            ],
        ];

        if ($identity instanceof Admin) {
            $data['transfer_code'] = $isDomain ? $model->getTransferCode() : $model->getTransferCode();

            $tldRegistrarId = $isDomain ? $model->getTldRegistrarId() : $model->getTldRegistrarId();
            $tldRegistrar = $this->getTldRegistrarRepository()->find($tldRegistrarId);
            $data['registrar'] = $tldRegistrar instanceof TldRegistrar ? $tldRegistrar->getName() : null;
        }

        return $data;
    }

    private function _getTuple($data): array
    {
        $action = $data['action'];
        [$sld, $tld] = [null, null];

        if ($action == 'owndomain') {
            $sld = $data['owndomain_sld'];
            $tld = str_contains((string) $data['domain']['owndomain_tld'], '.') ? $data['domain']['owndomain_tld'] : '.' . $data['domain']['owndomain_tld'];
        }

        if ($action == 'transfer') {
            $sld = $data['transfer_sld'];
            $tld = $data['transfer_tld'];
        }

        if ($action == 'register') {
            $sld = $data['register_sld'];
            $tld = $data['register_tld'];
        }

        return [$sld, $tld];
    }

    /**
     * @param ServiceDomain|ServiceDomain $model
     */
    protected function _getD($model): array
    {
        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);

        $tldRegistrarId = $model instanceof ServiceDomain ? $model->getTldRegistrarId() : $model->getTldRegistrarId();
        $tldRegistrar = $this->getTldRegistrarRepository()->find($tldRegistrarId);

        if ($order instanceof Order) {
            $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar, $order);
        } else {
            $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);
        }

        $d = new \Registrar_Domain();

        $d->setLocked($model instanceof ServiceDomain ? $model->isLocked() : $model->isLocked());
        $d->setNs1($model instanceof ServiceDomain ? $model->getNs1() : $model->getNs1());
        $d->setNs2($model instanceof ServiceDomain ? $model->getNs2() : $model->getNs2());
        $d->setNs3($model instanceof ServiceDomain ? $model->getNs3() : $model->getNs3());
        $d->setNs4($model instanceof ServiceDomain ? $model->getNs4() : $model->getNs4());

        $clientId = $model instanceof ServiceDomain ? $model->getClientId() : $model->getClientId();
        $client = $this->di['em']->getRepository(Client::class)->find($clientId);

        $contactEmail = $model instanceof ServiceDomain ? $model->getContactEmail() : $model->getContactEmail();
        $contactFirstName = $model instanceof ServiceDomain ? $model->getContactFirstName() : $model->getContactFirstName();
        $contactLastName = $model instanceof ServiceDomain ? $model->getContactLastName() : $model->getContactLastName();
        $contactCity = $model instanceof ServiceDomain ? $model->getContactCity() : $model->getContactCity();
        $contactPostcode = $model instanceof ServiceDomain ? $model->getContactPostcode() : $model->getContactPostcode();
        $contactCountry = $model instanceof ServiceDomain ? $model->getContactCountry() : $model->getContactCountry();
        $contactState = $model instanceof ServiceDomain ? $model->getContactState() : $model->getContactState();
        $contactPhone = $model instanceof ServiceDomain ? $model->getContactPhone() : $model->getContactPhone();
        $contactPhoneCc = $model instanceof ServiceDomain ? $model->getContactPhoneCc() : $model->getContactPhoneCc();
        $contactCompany = $model instanceof ServiceDomain ? $model->getContactCompany() : $model->getContactCompany();
        $contactAddress1 = $model instanceof ServiceDomain ? $model->getContactAddress1() : $model->getContactAddress1();
        $contactAddress2 = $model instanceof ServiceDomain ? $model->getContactAddress2() : $model->getContactAddress2();

        $email = empty($contactEmail) ? $client->getEmail() : $contactEmail;
        $first_name = empty($contactFirstName) ? $client->getFirstName() : $contactFirstName;
        $last_name = empty($contactLastName) ? $client->getLastName() : $contactLastName;
        $city = empty($contactCity) ? $client->getCity() : $contactCity;
        $zip = empty($contactPostcode) ? $client->getPostcode() : $contactPostcode;
        $country = empty($contactCountry) ? $client->getCountry() : $contactCountry;
        $state = empty($contactState) ? $client->getState() : $contactState;
        $phone = empty($contactPhone) ? $client->getPhone() : $contactPhone;
        $phone_cc = empty($contactPhoneCc) ? $client->getPhoneCc() : $contactPhoneCc;
        $company = empty($contactCompany) ? $client->getCompany() : $contactCompany;
        $address1 = empty($contactAddress1) ? $client->getAddress1() : $contactAddress1;
        $address2 = empty($contactAddress2) ? $client->getAddress2() : $contactAddress2;
        $birthday = !empty($client->getBirthday()) ? $client->getBirthday() : '';
        $company_number = !empty($client->getCompanyNumber()) ? $client->getCompanyNumber() : '';
        $document_nr = (string) ($this->di['mod_service']('client')->resolveDocumentNumber($client) ?? '');

        $contact = new \Registrar_Domain_Contact();
        $contact
            ->setEmail($email)
            ->setUsername($email)
            ->setPassword($this->di['tools']->generatePassword(10))
            ->setFirstname($first_name)
            ->setLastname($last_name)
            ->setCity($city)
            ->setZip($zip)
            ->setCountry($country)
            ->setState($state)
            ->setTel($phone)
            ->setTelCC($phone_cc)
            ->setCompany($company)
            ->setCompanyNumber($company_number)
            ->setAddress1($address1)
            ->setAddress2($address2)
            ->setFax($phone)
            ->setFaxCC($phone_cc)
            ->setBirthday($birthday)
            ->setDocumentNr($document_nr);

        $d->setContactRegistrar($contact);
        $d->setContactAdmin($contact);
        $d->setContactTech($contact);
        $d->setContactBilling($contact);

        $d->setTld($model instanceof ServiceDomain ? $model->getTld() : $model->getTld());
        $d->setSld($model instanceof ServiceDomain ? $model->getSld() : $model->getSld());
        $d->setRegistrationPeriod($model instanceof ServiceDomain ? $model->getPeriod() : $model->getPeriod());
        $d->setEpp($model instanceof ServiceDomain ? $model->getTransferCode() : $model->getTransferCode());

        $expiresAt = $model instanceof ServiceDomain ? $model->getExpiresAt() : $model->getExpiresAt();
        if ($expiresAt) {
            $timestamp = $model instanceof ServiceDomain ? $expiresAt->getTimestamp() : strtotime($expiresAt);
            $d->setExpirationTime($timestamp);
        }

        return [$d, $adapter];
    }

    public static function onBeforeAdminCronRun(\Box_Event $event): bool
    {
        try {
            $di = $event->getDi();
            $domainService = $di['mod_service']('servicedomain');
            $domainService->batchSyncExpirationDates();
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return true;
    }

    public function batchSyncExpirationDates(): bool
    {
        $key = 'servicedomain_last_sync';

        $ss = $this->di['mod_service']('system');
        $last_time = $ss->getParamValue($key);
        if ($last_time && (time() - strtotime((string) $last_time)) < 86400 * 30) {
            return false;
        }

        $list = $this->getDomainRepository()->findAll();

        foreach ($list as $domain) {
            try {
                $this->syncExpirationDate($domain);
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        $ss->setParamValue($key, date('Y-m-d H:i:s'));
        $this->di['logger']->info('Executed action to synchronize domain expiration dates with registrar');

        return true;
    }

    public function tldCreate($data): int
    {
        $model = new Tld();
        $model->setTld($data['tld']);
        $model->setTldRegistrarId($data['tld_registrar_id']);
        $model->setPriceRegistration($data['price_registration']);
        $model->setPriceRenew($data['price_renew']);
        $model->setPriceTransfer($data['price_transfer']);
        $model->setMinYears(isset($data['min_years']) ? (int) $data['min_years'] : 1);
        $model->setAllowRegister(isset($data['allow_register']) ? (bool) $data['allow_register'] : true);
        $model->setAllowTransfer(isset($data['allow_transfer']) ? (bool) $data['allow_transfer'] : true);
        $model->setActive(isset($data['active']) && (bool) $data['active']);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Created new top level domain %s', $model->getTld());

        return $model->getId();
    }

    /**
     * @param Tld|Tld $model
     */
    public function tldUpdate($model, $data): bool
    {
        if ($model instanceof Tld) {
            $model->setTldRegistrarId($data['tld_registrar_id'] ?? $model->getTldRegistrarId());
            $model->setPriceRegistration($data['price_registration'] ?? $model->getPriceRegistration());
            $model->setPriceRenew($data['price_renew'] ?? $model->getPriceRenew());
            $model->setPriceTransfer($data['price_transfer'] ?? $model->getPriceTransfer());
            $model->setMinYears($data['min_years'] ?? $model->getMinYears());
            $model->setAllowRegister($data['allow_register'] ?? $model->isAllowRegister());
            $model->setAllowTransfer($data['allow_transfer'] ?? $model->isAllowTransfer());
            $model->setActive($data['active'] ?? $model->isActive());
        } else {
            $model->tld_registrar_id = $data['tld_registrar_id'] ?? $model->getTldRegistrarId();
            $model->price_registration = $data['price_registration'] ?? $model->getPriceRegistration();
            $model->price_renew = $data['price_renew'] ?? $model->getPriceRenew();
            $model->price_transfer = $data['price_transfer'] ?? $model->getPriceTransfer();
            $model->min_years = $data['min_years'] ?? $model->getMinYears();
            $model->allow_register = $data['allow_register'] ?? $model->isAllowRegister();
            $model->allow_transfer = $data['allow_transfer'] ?? $model->isAllowTransfer();
            $model->active = $data['active'] ?? $model->isActive();
            $model->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($model);
            $this->di['logger']->info('Updated top level domain %s', $model->getTld());

            return true;
        }

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Updated top level domain %s', $model->getTld());

        return true;
    }

    public function tldGetSearchQuery($data): array
    {
        $query = 'SELECT * FROM tld';

        $hide_inactive = (bool) ($data['hide_inactive'] ?? false);
        $allow_register = $data['allow_register'] ?? null;
        $allow_transfer = $data['allow_transfer'] ?? null;

        $where = [];
        $bindings = [];

        if ($hide_inactive) {
            $where[] = 'active = 1';
        }

        if ($allow_register !== null) {
            $where[] = 'allow_register = 1';
        }

        if ($allow_transfer !== null) {
            $where[] = 'allow_transfer = 1';
        }

        if (!empty($where)) {
            $query = $query . ' WHERE ' . implode(' AND ', $where);
        }

        $query .= ' ORDER BY id ASC';

        return [$query, $bindings];
    }

    /**
     * @return Tld[]
     */
    public function tldFindAllActive(): array
    {
        return $this->getTldRepository()->findAllActive();
    }

    public function tldFindOneActiveById($id): ?Tld
    {
        return $this->getTldRepository()->findOneActiveById($id);
    }

    /**
     * @return array<int, string>
     */
    public function tldGetPairs(): array
    {
        return $this->getTldRepository()->getIdTldPairs();
    }

    public function tldAlreadyRegistered($tld): bool
    {
        return $this->getTldRepository()->findOneByTld($tld) !== null;
    }

    /**
     * @param Tld|Tld $model
     */
    public function tldRm($model): bool
    {
        if ($model instanceof Tld) {
            $id = $model->getId();
            $this->di['em']->remove($model);
            $this->di['em']->flush();
        } else {
            $id = $model->getId();
            $this->di['em']->remove($model);
        }
        $this->di['logger']->info('Deleted top level domain %s', $id);

        return true;
    }

    /**
     * @param Tld|Tld $model
     */
    public function tldToApiArray($model, $identity = null): array
    {
        $isTld = $model instanceof Tld;

        $result = [
            'id' => $isTld ? $model->getId() : $model->getId(),
            'tld' => $isTld ? $model->getTld() : $model->getTld(),
            'price_registration' => $isTld ? $model->getPriceRegistration() : $model->getPriceRegistration(),
            'price_renew' => $isTld ? $model->getPriceRenew() : $model->getPriceRenew(),
            'price_transfer' => $isTld ? $model->getPriceTransfer() : $model->getPriceTransfer(),
            'active' => $isTld ? $model->isActive() : $model->isActive(),
            'allow_register' => $isTld ? $model->isAllowRegister() : $model->isAllowRegister(),
            'allow_transfer' => $isTld ? $model->isAllowTransfer() : $model->isAllowTransfer(),
            'min_years' => $isTld ? $model->getMinYears() : $model->getMinYears(),
        ];

        if ($identity instanceof Admin) {
            $tldRegistrarId = $isTld ? $model->getTldRegistrarId() : $model->getTldRegistrarId();
            $tldRegistrar = $this->getTldRegistrarRepository()->find($tldRegistrarId);

            $result['registrar'] = [
                'id' => $tldRegistrarId,
                'title' => $tldRegistrar instanceof TldRegistrar ? $tldRegistrar->getName() : null,
            ];
        }

        return $result;
    }

    public function tldFindOneByTld($tld): ?Tld
    {
        return $this->getTldRepository()->findOneByTld($tld);
    }

    public function tldFindOneById($id): ?Tld
    {
        return $this->getTldRepository()->find($id);
    }

    public function registrarGetSearchQuery($data): array
    {
        $query = 'SELECT * FROM tld_registrar ORDER BY name ASC';
        $bindings = [];

        return [$query, $bindings];
    }

    /**
     * @return string[]
     */
    public function registrarGetAvailable(): array
    {
        $query = "SELECT 'registrar', 'name' FROM tld_registrar GROUP BY registrar";

        $exists = $this->di['em']->getConnection()->fetchAllKeyValue($query);

        $adapters = [];

        $finder = new Finder();
        $finder->files()->in(Path::join(PATH_LIBRARY, 'Registrar', 'Adapter'))->name('*.php')->depth('== 0');
        foreach ($finder as $file) {
            $adapter = $file->getFilenameWithoutExtension();
            if (!array_key_exists($adapter, $exists)) {
                $adapters[] = $adapter;
            }
        }

        return $adapters;
    }

    /**
     * @return array<int, string>
     */
    public function registrarGetPairs(): array
    {
        return $this->getTldRegistrarRepository()->getIdNamePairs();
    }

    public function registrarGetActiveRegistrar(): ?TldRegistrar
    {
        return $this->getTldRegistrarRepository()->findActiveRegistrar();
    }

    /**
     * @param TldRegistrar|TldRegistrar $model
     */
    public function registrarGetConfiguration($model): array
    {
        $config = $model instanceof TldRegistrar ? $model->getConfig() : $model->getConfig();

        return json_decode($config ?? '', true) ?? [];
    }

    /**
     * @param TldRegistrar|TldRegistrar $model
     */
    public function registrarGetRegistrarAdapterConfig($model)
    {
        $class = $this->registrarGetRegistrarAdapterClassName($model);

        return call_user_func([$class, 'getConfig']);
    }

    /**
     * @param TldRegistrar|TldRegistrar $model
     */
    private function registrarGetRegistrarAdapterClassName($model): string
    {
        $registrar = $model instanceof TldRegistrar ? $model->getRegistrar() : $model->getRegistrar();
        $file = Path::join(PATH_LIBRARY, 'Registrar', 'Adapter', "{$registrar}.php");
        if (!$this->filesystem->exists($file)) {
            throw new \FOSSBilling\InformationException('Domain registrar :adapter was not found', [':adapter' => $registrar]);
        }

        $class = sprintf('Registrar_Adapter_%s', $registrar);
        if (!class_exists($class)) {
            require_once $file;
        }

        if (!class_exists($class)) {
            throw new \FOSSBilling\InformationException('Registrar :adapter was not found', [':adapter' => $class]);
        }

        return $class;
    }

    /**
     * @param TldRegistrar|TldRegistrar $r
     */
    public function registrarGetRegistrarAdapter($r, ?Order $order = null)
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

        $testMode = $r instanceof TldRegistrar ? $r->isTestMode() : $r->isTestMode();
        if ($testMode) {
            $registrar->enableTestMode();
        }

        return $registrar;
    }

    public function registrarCreate($code): bool
    {
        $model = new TldRegistrar();
        $model->setName($code);
        $model->setRegistrar($code);
        $model->setTestMode(false);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Installed new domain registrar %s', $code);

        return true;
    }

    /**
     * @param TldRegistrar|TldRegistrar $model
     */
    public function registrarCopy($model): int
    {
        $new = new TldRegistrar();
        $new->setName(($model instanceof TldRegistrar ? $model->getName() : $model->getName()) . ' (Copy)');
        $new->setRegistrar($model instanceof TldRegistrar ? $model->getRegistrar() : $model->getRegistrar());
        $new->setTestMode($model instanceof TldRegistrar ? $model->isTestMode() : $model->isTestMode());

        $this->di['em']->persist($new);
        $this->di['em']->flush();

        $this->di['logger']->info('Copied domain registrar %s', $model instanceof TldRegistrar ? $model->getRegistrar() : $model->getRegistrar());

        return $new->getId();
    }

    /**
     * @param TldRegistrar|TldRegistrar $model
     */
    public function registrarUpdate($model, $data): bool
    {
        if ($model instanceof TldRegistrar) {
            $model->setName($data['title'] ?? $model->getName());
            $model->setTestMode(isset($data['test_mode']) ? (bool) $data['test_mode'] : $model->isTestMode());
            if (isset($data['config']) && is_array($data['config'])) {
                $model->setConfig(json_encode($data['config']));
            }
        } else {
            $model->name = $data['title'] ?? $model->getName();
            $model->test_mode = $data['test_mode'] ?? $model->isTestMode();
            if (isset($data['config']) && is_array($data['config'])) {
                $model->config = json_encode($data['config']);
            }
        }

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $registrar = $model instanceof TldRegistrar ? $model->getRegistrar() : $model->getRegistrar();
        $this->di['logger']->info('Updated domain registrar %s configuration', $registrar);

        return true;
    }

    /**
     * @param TldRegistrar|TldRegistrar $model
     */
    public function registrarRm($model): bool
    {
        $registrarId = $model instanceof TldRegistrar ? $model->getId() : $model->getId();
        $domains = $this->getDomainRepository()->findByTldRegistrarId($registrarId);
        $count = count($domains);

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Registrar is used by :count: domains', [':count:' => $count], 707);
        }

        $tlds = $this->getTldRepository()->findBy(['tldRegistrarId' => $registrarId]);
        $count = count($tlds);

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Registrar is used by :count: TLDs', [':count:' => $count], 707);
        }

        $name = $model instanceof TldRegistrar ? $model->getName() : $model->getName();

        $this->di['em']->remove($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed domain registrar %s', $name);

        return true;
    }

    /**
     * @param TldRegistrar|TldRegistrar $model
     */
    public function registrarToApiArray($model): array
    {
        $c = $this->registrarGetRegistrarAdapterConfig($model);

        return [
            'id' => $model instanceof TldRegistrar ? $model->getId() : $model->getId(),
            'title' => $model instanceof TldRegistrar ? $model->getName() : $model->getName(),
            'label' => $c['label'],
            'config' => $this->registrarGetConfiguration($model),
            'form' => $c['form'],
            'test_mode' => $model instanceof TldRegistrar ? $model->isTestMode() : $model->isTestMode(),
        ];
    }

    /**
     * @param ServiceDomain|ServiceDomain $s
     */
    public function updateDomain($s, $data): bool
    {
        $this->setModelNs1($s, $data['ns1'] ?? $this->getModelNs1($s));
        $this->setModelNs2($s, $data['ns2'] ?? $this->getModelNs2($s));
        $this->setModelNs3($s, $data['ns3'] ?? $this->getModelNs3($s));
        $this->setModelNs4($s, $data['ns4'] ?? $this->getModelNs4($s));

        $this->setModelPeriod($s, (int) ($data['period'] ?? $this->getModelPeriod($s)));
        $this->setModelPrivacy($s, (bool) ($data['privacy'] ?? $this->getModelPrivacy($s)));
        $this->setModelLocked($s, (bool) ($data['locked'] ?? $this->getModelLocked($s)));
        $this->setModelTransferCode($s, $data['transfer_code'] ?? $this->getModelTransferCode($s));
        $this->setModelUpdatedAt($s, date('Y-m-d H:i:s'));

        $this->di['em']->persist($s);
        $this->di['em']->flush();

        $id = $this->getModelId($s);
        $this->di['logger']->info('Updated domain #%s without sending actions to server', $id);

        return true;
    }

    private function formatRegistrarTimestamp(mixed $timestamp): ?string
    {
        if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', (int) $timestamp);
    }

    private function formatDateForApi(?\DateTime $date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }

    private function getModelId(ServiceDomain $model): ?int
    {
        return $model->getId();
    }

    private function getModelAction(ServiceDomain $model): ?string
    {
        return $model->getAction();
    }

    private function setModelAction(ServiceDomain $model, ?string $value): void
    {
        $model->setAction($value);
    }

    private function setModelLocked(ServiceDomain $model, ?bool $value): void
    {
        $model->setLocked($value);
    }

    private function setModelPrivacy(ServiceDomain $model, ?bool $value): void
    {
        $model->setPrivacy($value);
    }

    private function setModelContactFirstName(ServiceDomain $model, ?string $value): void
    {
        $model->setContactFirstName($value);
    }

    private function setModelContactLastName(ServiceDomain $model, ?string $value): void
    {
        $model->setContactLastName($value);
    }

    private function setModelContactEmail(ServiceDomain $model, ?string $value): void
    {
        $model->setContactEmail($value);
    }

    private function setModelContactCompany(ServiceDomain $model, ?string $value): void
    {
        $model->setContactCompany($value);
    }

    private function setModelContactAddress1(ServiceDomain $model, ?string $value): void
    {
        $model->setContactAddress1($value);
    }

    private function setModelContactAddress2(ServiceDomain $model, ?string $value): void
    {
        $model->setContactAddress2($value);
    }

    private function setModelContactCountry(ServiceDomain $model, ?string $value): void
    {
        $model->setContactCountry($value);
    }

    private function setModelContactCity(ServiceDomain $model, ?string $value): void
    {
        $model->setContactCity($value);
    }

    private function setModelContactState(ServiceDomain $model, ?string $value): void
    {
        $model->setContactState($value);
    }

    private function setModelContactPostcode(ServiceDomain $model, ?string $value): void
    {
        $model->setContactPostcode($value);
    }

    private function setModelContactPhoneCc(ServiceDomain $model, ?string $value): void
    {
        $model->setContactPhoneCc($value);
    }

    private function setModelContactPhone(ServiceDomain $model, ?string $value): void
    {
        $model->setContactPhone($value);
    }

    private function setModelDetails(ServiceDomain $model, ?string $value): void
    {
        $model->setDetails($value);
    }

    private function setModelExpiresAt(ServiceDomain $model, ?string $value): void
    {
        $model->setExpiresAt($value !== null ? new \DateTime($value) : null);
    }

    private function setModelRegisteredAt(ServiceDomain $model, ?string $value): void
    {
        $model->setRegisteredAt($value !== null ? new \DateTime($value) : null);
    }

    private function setModelUpdatedAt(ServiceDomain $model, ?string $value): void
    {
        $model->setUpdatedAt($value !== null ? new \DateTime($value) : null);
    }

    private function setModelNs1(ServiceDomain $model, ?string $value): void
    {
        $model->setNs1($value);
    }

    private function setModelNs2(ServiceDomain $model, ?string $value): void
    {
        $model->setNs2($value);
    }

    private function setModelNs3(ServiceDomain $model, ?string $value): void
    {
        $model->setNs3($value);
    }

    private function setModelNs4(ServiceDomain $model, ?string $value): void
    {
        $model->setNs4($value);
    }

    private function getModelNs1(ServiceDomain $model): ?string
    {
        return $model->getNs1();
    }

    private function getModelNs2(ServiceDomain $model): ?string
    {
        return $model->getNs2();
    }

    private function getModelNs3(ServiceDomain $model): ?string
    {
        return $model->getNs3();
    }

    private function getModelNs4(ServiceDomain $model): ?string
    {
        return $model->getNs4();
    }

    private function getModelPeriod(ServiceDomain $model): ?int
    {
        return $model->getPeriod();
    }

    private function setModelPeriod(ServiceDomain $model, ?int $value): void
    {
        $model->setPeriod($value);
    }

    private function getModelPrivacy(ServiceDomain $model): ?bool
    {
        return $model->getPrivacy();
    }

    private function getModelLocked(ServiceDomain $model): ?bool
    {
        return $model->isLocked();
    }

    private function getModelTransferCode(ServiceDomain $model): ?string
    {
        return $model->getTransferCode();
    }

    private function setModelTransferCode(ServiceDomain $model, ?string $value): void
    {
        $model->setTransferCode($value);
    }

    // -- Repository accessors --

    public function getDomainRepository(): DomainRepository
    {
        return $this->di['em']->getRepository(ServiceDomain::class);
    }

    public function getTldRepository(): TldRepo
    {
        return $this->di['em']->getRepository(Tld::class);
    }

    public function getTldRegistrarRepository(): TldRegistrarRepository
    {
        return $this->di['em']->getRepository(TldRegistrar::class);
    }
}
