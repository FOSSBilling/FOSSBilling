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
        $model->setClientId($order->client_id);
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
        if (!$model instanceof ServiceDomain && !$model instanceof ServiceDomain) {
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
        if (!$model instanceof ServiceDomain && !$model instanceof ServiceDomain) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->id]);
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
        if (!$model instanceof ServiceDomain && !$model instanceof ServiceDomain) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->id]);
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

        if ($service instanceof ServiceDomain || $service instanceof ServiceDomain) {
            if ($order->status != Order::STATUS_CANCELED) {
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

        $allowTransfer = $model instanceof Tld ? $model->isAllowTransfer() : $model->allow_transfer;
        if (!$allowTransfer) {
            throw new \FOSSBilling\InformationException('Domain cannot be transferred', null, 403);
        }

        $domain = new \Registrar_Domain();
        $domain->setTld($model instanceof Tld ? $model->getTld() : $model->tld);
        $domain->setSld($sld);

        $tldRegistrarId = $model instanceof Tld ? $model->getTldRegistrarId() : $model->tld_registrar_id;
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

        $allowRegister = $model instanceof Tld ? $model->isAllowRegister() : $model->allow_register;
        if (!$allowRegister) {
            throw new \FOSSBilling\InformationException('Domain cannot be registered', null, 403);
        }

        $domain = new \Registrar_Domain();
        $domain->setTld($model instanceof Tld ? $model->getTld() : $model->tld);
        $domain->setSld($sld);

        $tldRegistrarId = $model instanceof Tld ? $model->getTldRegistrarId() : $model->tld_registrar_id;
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
            'domain' => ($isDomain ? $model->getSld() : $model->sld) . ($isDomain ? $model->getTld() : $model->tld),
            'sld' => $isDomain ? $model->getSld() : $model->sld,
            'tld' => $isDomain ? $model->getTld() : $model->tld,
            'ns1' => $isDomain ? $model->getNs1() : $model->ns1,
            'ns2' => $isDomain ? $model->getNs2() : $model->ns2,
            'ns3' => $isDomain ? $model->getNs3() : $model->ns3,
            'ns4' => $isDomain ? $model->getNs4() : $model->ns4,
            'period' => $isDomain ? $model->getPeriod() : $model->period,
            'privacy' => $isDomain ? $model->getPrivacy() : $model->privacy,
            'locked' => $isDomain ? $model->isLocked() : $model->locked,
            'registered_at' => $isDomain ? $this->formatDateForApi($model->getRegisteredAt()) : $model->registered_at,
            'expires_at' => $isDomain ? $this->formatDateForApi($model->getExpiresAt()) : $model->expires_at,
            'contact' => [
                'first_name' => $isDomain ? $model->getContactFirstName() : $model->contact_first_name,
                'last_name' => $isDomain ? $model->getContactLastName() : $model->contact_last_name,
                'email' => $isDomain ? $model->getContactEmail() : $model->contact_email,
                'company' => $isDomain ? $model->getContactCompany() : $model->contact_company,
                'address1' => $isDomain ? $model->getContactAddress1() : $model->contact_address1,
                'address2' => $isDomain ? $model->getContactAddress2() : $model->contact_address2,
                'country' => $isDomain ? $model->getContactCountry() : $model->contact_country,
                'city' => $isDomain ? $model->getContactCity() : $model->contact_city,
                'state' => $isDomain ? $model->getContactState() : $model->contact_state,
                'postcode' => $isDomain ? $model->getContactPostcode() : $model->contact_postcode,
                'phone_cc' => $isDomain ? $model->getContactPhoneCc() : $model->contact_phone_cc,
                'phone' => $isDomain ? $model->getContactPhone() : $model->contact_phone,
            ],
        ];

        if ($identity instanceof Admin) {
            $data['transfer_code'] = $isDomain ? $model->getTransferCode() : $model->transfer_code;

            $tldRegistrarId = $isDomain ? $model->getTldRegistrarId() : $model->tld_registrar_id;
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

        $tldRegistrarId = $model instanceof ServiceDomain ? $model->getTldRegistrarId() : $model->tld_registrar_id;
        $tldRegistrar = $this->getTldRegistrarRepository()->find($tldRegistrarId);

        if ($order instanceof Order) {
            $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar, $order);
        } else {
            $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);
        }

        $d = new \Registrar_Domain();

        $d->setLocked($model instanceof ServiceDomain ? $model->isLocked() : $model->locked);
        $d->setNs1($model instanceof ServiceDomain ? $model->getNs1() : $model->ns1);
        $d->setNs2($model instanceof ServiceDomain ? $model->getNs2() : $model->ns2);
        $d->setNs3($model instanceof ServiceDomain ? $model->getNs3() : $model->ns3);
        $d->setNs4($model instanceof ServiceDomain ? $model->getNs4() : $model->ns4);

        $clientId = $model instanceof ServiceDomain ? $model->getClientId() : $model->client_id;
        $client = $this->di['em']->getRepository(Client::class)->find($clientId);

        $contactEmail = $model instanceof ServiceDomain ? $model->getContactEmail() : $model->contact_email;
        $contactFirstName = $model instanceof ServiceDomain ? $model->getContactFirstName() : $model->contact_first_name;
        $contactLastName = $model instanceof ServiceDomain ? $model->getContactLastName() : $model->contact_last_name;
        $contactCity = $model instanceof ServiceDomain ? $model->getContactCity() : $model->contact_city;
        $contactPostcode = $model instanceof ServiceDomain ? $model->getContactPostcode() : $model->contact_postcode;
        $contactCountry = $model instanceof ServiceDomain ? $model->getContactCountry() : $model->contact_country;
        $contactState = $model instanceof ServiceDomain ? $model->getContactState() : $model->contact_state;
        $contactPhone = $model instanceof ServiceDomain ? $model->getContactPhone() : $model->contact_phone;
        $contactPhoneCc = $model instanceof ServiceDomain ? $model->getContactPhoneCc() : $model->contact_phone_cc;
        $contactCompany = $model instanceof ServiceDomain ? $model->getContactCompany() : $model->contact_company;
        $contactAddress1 = $model instanceof ServiceDomain ? $model->getContactAddress1() : $model->contact_address1;
        $contactAddress2 = $model instanceof ServiceDomain ? $model->getContactAddress2() : $model->contact_address2;

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

        $d->setTld($model instanceof ServiceDomain ? $model->getTld() : $model->tld);
        $d->setSld($model instanceof ServiceDomain ? $model->getSld() : $model->sld);
        $d->setRegistrationPeriod($model instanceof ServiceDomain ? $model->getPeriod() : $model->period);
        $d->setEpp($model instanceof ServiceDomain ? $model->getTransferCode() : $model->transfer_code);

        $expiresAt = $model instanceof ServiceDomain ? $model->getExpiresAt() : $model->expires_at;
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
            $model->tld_registrar_id = $data['tld_registrar_id'] ?? $model->tld_registrar_id;
            $model->price_registration = $data['price_registration'] ?? $model->price_registration;
            $model->price_renew = $data['price_renew'] ?? $model->price_renew;
            $model->price_transfer = $data['price_transfer'] ?? $model->price_transfer;
            $model->min_years = $data['min_years'] ?? $model->min_years;
            $model->allow_register = $data['allow_register'] ?? $model->allow_register;
            $model->allow_transfer = $data['allow_transfer'] ?? $model->allow_transfer;
            $model->active = $data['active'] ?? $model->active;
            $model->updated_at = date('Y-m-d H:i:s');
            $this->di['em']->persist($model);
            $this->di['logger']->info('Updated top level domain %s', $model->tld);

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
            $id = $model->id;
            $this->di['db']->trash($model);
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
            'id' => $isTld ? $model->getId() : $model->id,
            'tld' => $isTld ? $model->getTld() : $model->tld,
            'price_registration' => $isTld ? $model->getPriceRegistration() : $model->price_registration,
            'price_renew' => $isTld ? $model->getPriceRenew() : $model->price_renew,
            'price_transfer' => $isTld ? $model->getPriceTransfer() : $model->price_transfer,
            'active' => $isTld ? $model->isActive() : $model->active,
            'allow_register' => $isTld ? $model->isAllowRegister() : $model->allow_register,
            'allow_transfer' => $isTld ? $model->isAllowTransfer() : $model->allow_transfer,
            'min_years' => $isTld ? $model->getMinYears() : $model->min_years,
        ];

        if ($identity instanceof Admin) {
            $tldRegistrarId = $isTld ? $model->getTldRegistrarId() : $model->tld_registrar_id;
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
        $config = $model instanceof TldRegistrar ? $model->getConfig() : $model->config;

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
        $registrar = $model instanceof TldRegistrar ? $model->getRegistrar() : $model->registrar;
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

        $testMode = $r instanceof TldRegistrar ? $r->isTestMode() : $r->test_mode;
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
        $new->setName(($model instanceof TldRegistrar ? $model->getName() : $model->name) . ' (Copy)');
        $new->setRegistrar($model instanceof TldRegistrar ? $model->getRegistrar() : $model->registrar);
        $new->setTestMode($model instanceof TldRegistrar ? $model->isTestMode() : $model->test_mode);

        $this->di['em']->persist($new);
        $this->di['em']->flush();

        $this->di['logger']->info('Copied domain registrar %s', $model instanceof TldRegistrar ? $model->getRegistrar() : $model->registrar);

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
            $model->name = $data['title'] ?? $model->name;
            $model->test_mode = $data['test_mode'] ?? $model->test_mode;
            if (isset($data['config']) && is_array($data['config'])) {
                $model->config = json_encode($data['config']);
            }
        }

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $registrar = $model instanceof TldRegistrar ? $model->getRegistrar() : $model->registrar;
        $this->di['logger']->info('Updated domain registrar %s configuration', $registrar);

        return true;
    }

    /**
     * @param TldRegistrar|TldRegistrar $model
     */
    public function registrarRm($model): bool
    {
        $registrarId = $model instanceof TldRegistrar ? $model->getId() : $model->id;
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

        $name = $model instanceof TldRegistrar ? $model->getName() : $model->name;

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
            'id' => $model instanceof TldRegistrar ? $model->getId() : $model->id,
            'title' => $model instanceof TldRegistrar ? $model->getName() : $model->name,
            'label' => $c['label'],
            'config' => $this->registrarGetConfiguration($model),
            'form' => $c['form'],
            'test_mode' => $model instanceof TldRegistrar ? $model->isTestMode() : $model->test_mode,
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

    // -- Accessor helpers to bridge ServiceDomain Entity and RedBean model --

    private function getModelId($model): ?int
    {
        return $model instanceof ServiceDomain ? $model->getId() : $model->id;
    }

    private function getModelAction($model): ?string
    {
        return $model instanceof ServiceDomain ? $model->getAction() : $model->action;
    }

    private function setModelAction($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setAction($value);
        } else {
            $model->action = $value;
        }
    }

    private function setModelLocked($model, ?bool $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setLocked($value);
        } else {
            $model->locked = $value;
        }
    }

    private function setModelPrivacy($model, ?bool $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setPrivacy($value);
        } else {
            $model->privacy = $value;
        }
    }

    private function setModelContactFirstName($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactFirstName($value);
        } else {
            $model->contact_first_name = $value;
        }
    }

    private function setModelContactLastName($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactLastName($value);
        } else {
            $model->contact_last_name = $value;
        }
    }

    private function setModelContactEmail($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactEmail($value);
        } else {
            $model->contact_email = $value;
        }
    }

    private function setModelContactCompany($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactCompany($value);
        } else {
            $model->contact_company = $value;
        }
    }

    private function setModelContactAddress1($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactAddress1($value);
        } else {
            $model->contact_address1 = $value;
        }
    }

    private function setModelContactAddress2($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactAddress2($value);
        } else {
            $model->contact_address2 = $value;
        }
    }

    private function setModelContactCountry($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactCountry($value);
        } else {
            $model->contact_country = $value;
        }
    }

    private function setModelContactCity($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactCity($value);
        } else {
            $model->contact_city = $value;
        }
    }

    private function setModelContactState($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactState($value);
        } else {
            $model->contact_state = $value;
        }
    }

    private function setModelContactPostcode($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactPostcode($value);
        } else {
            $model->contact_postcode = $value;
        }
    }

    private function setModelContactPhoneCc($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactPhoneCc($value);
        } else {
            $model->contact_phone_cc = $value;
        }
    }

    private function setModelContactPhone($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setContactPhone($value);
        } else {
            $model->contact_phone = $value;
        }
    }

    private function setModelDetails($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setDetails($value);
        } else {
            $model->details = $value;
        }
    }

    private function setModelExpiresAt($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setExpiresAt($value !== null ? new \DateTime($value) : null);
        } else {
            $model->expires_at = $value;
        }
    }

    private function setModelRegisteredAt($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setRegisteredAt($value !== null ? new \DateTime($value) : null);
        } else {
            $model->registered_at = $value;
        }
    }

    private function setModelUpdatedAt($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setUpdatedAt($value !== null ? new \DateTime($value) : null);
        } else {
            $model->updated_at = $value;
        }
    }

    private function setModelNs1($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setNs1($value);
        } else {
            $model->ns1 = $value;
        }
    }

    private function setModelNs2($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setNs2($value);
        } else {
            $model->ns2 = $value;
        }
    }

    private function setModelNs3($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setNs3($value);
        } else {
            $model->ns3 = $value;
        }
    }

    private function setModelNs4($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setNs4($value);
        } else {
            $model->ns4 = $value;
        }
    }

    private function getModelNs1($model): ?string
    {
        return $model instanceof ServiceDomain ? $model->getNs1() : $model->ns1;
    }

    private function getModelNs2($model): ?string
    {
        return $model instanceof ServiceDomain ? $model->getNs2() : $model->ns2;
    }

    private function getModelNs3($model): ?string
    {
        return $model instanceof ServiceDomain ? $model->getNs3() : $model->ns3;
    }

    private function getModelNs4($model): ?string
    {
        return $model instanceof ServiceDomain ? $model->getNs4() : $model->ns4;
    }

    private function getModelPeriod($model): ?int
    {
        return $model instanceof ServiceDomain ? $model->getPeriod() : $model->period;
    }

    private function setModelPeriod($model, ?int $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setPeriod($value);
        } else {
            $model->period = $value;
        }
    }

    private function getModelPrivacy($model): ?bool
    {
        return $model instanceof ServiceDomain ? $model->getPrivacy() : $model->privacy;
    }

    private function getModelLocked($model): ?bool
    {
        return $model instanceof ServiceDomain ? $model->isLocked() : $model->locked;
    }

    private function getModelTransferCode($model): ?string
    {
        return $model instanceof ServiceDomain ? $model->getTransferCode() : $model->transfer_code;
    }

    private function setModelTransferCode($model, ?string $value): void
    {
        if ($model instanceof ServiceDomain) {
            $model->setTransferCode($value);
        } else {
            $model->transfer_code = $value;
        }
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
