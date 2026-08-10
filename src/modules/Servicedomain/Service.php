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

use Box\Mod\Order\Entity\Order;
use Box\Mod\Product\Entity\Product;
use Box\Mod\Servicedomain\Entity\ServiceDomain;
use Box\Mod\Servicedomain\Entity\Tld;
use Box\Mod\Servicedomain\Entity\TldRegistrar;
use Box\Mod\Servicedomain\Repository\DomainRepository;
use Box\Mod\Servicedomain\Repository\TldRegistrarRepository;
use Box\Mod\Servicedomain\Repository\TldRepository;
use Doctrine\ORM\QueryBuilder;
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

            $data['owndomain_tld'] = $this->normalizeTld($data['owndomain_tld']);
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
            if (!$tld->isActive()) {
                throw new \FOSSBilling\InformationException('TLD is not active');
            }
            $data['transfer_tld'] = $tld->getTld();

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
            if (!$tld->isActive()) {
                throw new \FOSSBilling\InformationException('TLD is not active');
            }
            $data['register_tld'] = $tld->getTld();

            $years = filter_var($data['register_years'], FILTER_VALIDATE_INT);
            if ($years === false || $years < 1) {
                throw new \FOSSBilling\InformationException('Domain registration period must be a positive integer');
            }

            $allowedPeriods = $tld->getPeriodsArray();
            if ($allowedPeriods !== null) {
                if (!in_array($years, $allowedPeriods, true)) {
                    throw new \FOSSBilling\Exception(':tld can only be registered for :periods years', [':tld' => $tld->getTld(), ':periods' => implode(', ', $allowedPeriods)]);
                }
            } elseif ($years < ($tld->getMinYears() ?? 1)) {
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

        // @todo ?
        $systemService = $this->di['mod_service']('system');
        $ns = $systemService->getNameservers();
        if (empty($ns)) {
            throw new \FOSSBilling\InformationException('Default domain nameservers are not configured');
        }

        $tldModel = $this->tldFindOneByTld($tld);

        $model = new ServiceDomain();
        $model->setClientId((int) $order->getClientId());
        $model->setTldRegistrarId($tldModel instanceof Tld ? $tldModel->getTldRegistrarId() : null);
        $model->setSld($sld);
        $model->setTld($tld);
        $model->setPeriod($years);
        $model->setTransferCode($c['transfer_code'] ?? null);
        $model->setPrivacy(false);
        $model->setAction($c['action']);
        $model->setNs1(isset($c['ns1']) && !empty($c['ns1']) ? $c['ns1'] : $ns['nameserver_1']);
        $model->setNs2(!empty($c['ns2']) ? $c['ns2'] : $ns['nameserver_2']);
        $model->setNs3(!empty($c['ns3']) ? $c['ns3'] : $ns['nameserver_3']);
        $model->setNs4(!empty($c['ns4']) ? $c['ns4'] : $ns['nameserver_4']);

        $client = $this->di['db']->getExistingModelById('Client', $model->getClientId(), 'Client not found');

        $model->setContactFirstName($client->first_name);
        $model->setContactLastName($client->last_name);
        $model->setContactEmail($client->email);
        $model->setContactCompany($client->company);
        $model->setContactAddress1($client->address_1);
        $model->setContactAddress2($client->address_2);
        $model->setContactCountry($client->country);
        $model->setContactCity($client->city);
        $model->setContactState($client->state);
        $model->setContactPostcode($client->postcode);
        $model->setContactPhoneCc($client->phone_cc);
        $model->setContactPhone($client->phone);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        return $model;
    }

    public function action_activate(Order $order): ServiceDomain
    {
        $model = $this->_getOrderService($order);

        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        if ($model->getAction() == 'register') {
            $adapter->registerDomain($domain);
        }

        if ($model->getAction() == 'transfer') {
            $adapter->transferDomain($domain);
        }

        // reset action
        $model->setAction(null);
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
        $model = $this->_getOrderService($order);
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->renewDomain($domain);

        $this->syncWhois($model, $order);

        return true;
    }

    /**
     * @todo
     */
    public function action_suspend(Order $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_unsuspend(Order $order): bool
    {
        return true;
    }

    public function action_cancel(Order $order): bool
    {
        $model = $this->_getOrderService($order);
        // @adapterAction
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
        $service = $this->_getOrderService($order, false);

        if ($service instanceof ServiceDomain) {
            // cancel if not canceled
            if ($order->getStatus() != Order::STATUS_CANCELED) {
                $this->action_cancel($order);
            }
            $this->di['em']->remove($service);
            $this->di['em']->flush();
        }
    }

    public function syncWhois(ServiceDomain $model, Order $order): void
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);

        // update whois
        $whois = $adapter->getDomainDetails($domain);

        $locked = $whois->getLocked();
        if ($locked !== null) {
            $model->setLocked($locked);
        }

        $privacy = $whois->getPrivacyEnabled();
        if ($privacy !== null) {
            $model->setPrivacy($privacy);
        }

        // sync whois
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

        $model->setDetails(serialize($whois));
        $model->setExpiresAt($this->formatRegistrarTimestamp($whois->getExpirationTime()));
        $model->setRegisteredAt($this->formatRegistrarTimestamp($whois->getRegistrationTime()));

        $this->di['em']->flush();
    }

    public function updateNameservers(ServiceDomain $model, $data): bool
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

        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $domain->setNs1($ns1);
        $domain->setNs2($ns2);
        $domain->setNs3($ns3);
        $domain->setNs4($ns4);
        $adapter->modifyNs($domain);

        $model->setNs1($ns1);
        $model->setNs2($ns2);
        $model->setNs3($ns3);
        $model->setNs4($ns4);

        $this->di['em']->flush();

        $this->di['logger']->info('Updated domain #%s nameservers', $model->getId());

        return true;
    }

    public function updateContacts(ServiceDomain $model, $data): bool
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

        $model->setContactFirstName($contact['first_name']);
        $model->setContactLastName($contact['last_name']);
        $model->setContactEmail($contact['email']);
        $model->setContactCompany($contact['company'] ?? null);
        $model->setContactAddress1($contact['address1']);
        $model->setContactAddress2($contact['address2'] ?? null);
        $model->setContactCountry($contact['country']);
        $model->setContactCity($contact['city']);
        $model->setContactState($contact['state']);
        $model->setContactPostcode($contact['postcode']);
        $model->setContactPhoneCc($contact['phone_cc']);
        $model->setContactPhone($contact['phone']);

        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->modifyContact($domain);

        $this->di['em']->flush();

        $this->di['logger']->info('Updated domain #%s WHOIS details', $model->getId());

        return true;
    }

    public function getTransferCode(ServiceDomain $model)
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);

        return $adapter->getEpp($domain);
    }

    public function lock(ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->lock($domain);

        $model->setLocked(true);

        $this->di['em']->flush();

        $this->di['logger']->info('Locking domain #%s', $model->getId());

        return true;
    }

    public function unlock(ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->unlock($domain);

        $model->setLocked(false);

        $this->di['em']->flush();

        $this->di['logger']->info('Unlocking domain #%s', $model->getId());

        return true;
    }

    public function enablePrivacyProtection(ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->enablePrivacyProtection($domain);

        $model->setPrivacy(true);

        $this->di['em']->flush();

        $this->di['logger']->info('Enabled privacy protection of #%s domain', $model->getId());

        return true;
    }

    public function disablePrivacyProtection(ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->disablePrivacyProtection($domain);

        $model->setPrivacy(false);

        $this->di['em']->flush();

        $this->di['logger']->info('Disabled privacy protection of #%s domain', $model->getId());

        return true;
    }

    public function canBeTransferred(Tld $model, $sld)
    {
        if (empty($sld)) {
            throw new \FOSSBilling\InformationException('Domain name is invalid');
        }

        if (!$model->isAllowTransfer()) {
            throw new \FOSSBilling\InformationException('Domain cannot be transferred', null, 403);
        }

        // @adapterAction
        $domain = new \Registrar_Domain();
        $domain->setTld($model->getTld());
        $domain->setSld($sld);

        $tldRegistrar = $this->getExistingRegistrar($model->getTldRegistrarId());
        $this->registrarValidateConfiguration($tldRegistrar);
        $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);

        return $adapter->isDomaincanBeTransferred($domain);
    }

    public function isDomainAvailable(Tld $model, $sld)
    {
        if (empty($sld)) {
            throw new \FOSSBilling\InformationException('Domain name is invalid');
        }

        $validator = $this->di['validator'];
        if (!$validator->isSldValid($sld)) {
            $safe_dom = htmlspecialchars((string) $sld, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
        }

        if (!$model->isAllowRegister()) {
            throw new \FOSSBilling\InformationException('Domain cannot be registered', null, 403);
        }

        // @adapterAction
        $domain = new \Registrar_Domain();
        $domain->setTld($model->getTld());
        $domain->setSld($sld);

        $tldRegistrar = $this->getExistingRegistrar($model->getTldRegistrarId());
        $this->registrarValidateConfiguration($tldRegistrar);
        $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);

        return $adapter->isDomainAvailable($domain);
    }

    public function syncExpirationDate($model): void
    {
        // @todo
    }

    public function toApiArray(ServiceDomain $model, $deep = false, $identity = null): array
    {
        $data = [
            'domain' => $model->getSld() . $model->getTld(),
            'sld' => $model->getSld(),
            'tld' => $model->getTld(),
            'ns1' => $model->getNs1(),
            'ns2' => $model->getNs2(),
            'ns3' => $model->getNs3(),
            'ns4' => $model->getNs4(),
            'period' => $model->getPeriod(),
            'privacy' => $model->getPrivacy(),
            'locked' => $model->isLocked(),
            'registered_at' => $this->formatDateTime($model->getRegisteredAt()),
            'expires_at' => $this->formatDateTime($model->getExpiresAt()),
            'contact' => [
                'first_name' => $model->getContactFirstName(),
                'last_name' => $model->getContactLastName(),
                'email' => $model->getContactEmail(),
                'company' => $model->getContactCompany(),
                'address1' => $model->getContactAddress1(),
                'address2' => $model->getContactAddress2(),
                'country' => $model->getContactCountry(),
                'city' => $model->getContactCity(),
                'state' => $model->getContactState(),
                'postcode' => $model->getContactPostcode(),
                'phone_cc' => $model->getContactPhoneCc(),
                'phone' => $model->getContactPhone(),
            ],
        ];

        if ($identity instanceof \Model_Admin) {
            $data['transfer_code'] = $model->getTransferCode();

            $tldRegistrarId = $model->getTldRegistrarId();
            $tldRegistrar = $tldRegistrarId !== null ? $this->getTldRegistrarRepository()->find($tldRegistrarId) : null;
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

    protected function _getD(ServiceDomain $model): array
    {
        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);

        $tldRegistrar = $this->getExistingRegistrar($model->getTldRegistrarId());

        if ($order instanceof Order) {
            $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar, $order);
        } else {
            $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);
        }

        $d = new \Registrar_Domain();

        $d->setLocked($model->isLocked());
        $d->setNs1($model->getNs1());
        $d->setNs2($model->getNs2());
        $d->setNs3($model->getNs3());
        $d->setNs4($model->getNs4());

        // merge info with current profile
        $client = $this->di['db']->load('Client', $model->getClientId());

        $email = empty($model->getContactEmail()) ? $client->email : $model->getContactEmail();
        $first_name = empty($model->getContactFirstName()) ? $client->first_name : $model->getContactFirstName();
        $last_name = empty($model->getContactLastName()) ? $client->last_name : $model->getContactLastName();
        $city = empty($model->getContactCity()) ? $client->city : $model->getContactCity();
        $zip = empty($model->getContactPostcode()) ? $client->postcode : $model->getContactPostcode();
        $country = empty($model->getContactCountry()) ? $client->country : $model->getContactCountry();
        $state = empty($model->getContactState()) ? $client->state : $model->getContactState();
        $phone = empty($model->getContactPhone()) ? $client->phone : $model->getContactPhone();
        $phone_cc = empty($model->getContactPhoneCc()) ? $client->phone_cc : $model->getContactPhoneCc();
        $company = empty($model->getContactCompany()) ? $client->company : $model->getContactCompany();
        $address1 = empty($model->getContactAddress1()) ? $client->address_1 : $model->getContactAddress1();
        $address2 = empty($model->getContactAddress2()) ? $client->address_2 : $model->getContactAddress2();
        $birthday = !empty($client->birthday) ? $client->birthday : '';
        $company_number = !empty($client->company_number) ? $client->company_number : '';
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

        $d->setTld($model->getTld());
        $d->setSld($model->getSld());
        $d->setRegistrationPeriod($model->getPeriod());
        $d->setEpp($model->getTransferCode());

        $expiresAt = $model->getExpiresAt();
        if ($expiresAt !== null) {
            $d->setExpirationTime($expiresAt->getTimestamp());
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

    public function tldCreate($data): ?int
    {
        $data = $this->validateTldConfiguration($data);
        $normalizedTld = $this->normalizeTld($data['tld']);
        $registrar = $this->getExistingRegistrar((int) $data['tld_registrar_id']);
        $this->registrarValidateConfiguration($registrar);

        $model = new Tld();
        $model->setTld($normalizedTld);
        $model->setTldRegistrarId((int) $data['tld_registrar_id']);
        $model->setPriceRegistration((string) $data['price_registration']);
        $model->setPriceRenew((string) $data['price_renew']);
        $model->setPriceTransfer((string) $data['price_transfer']);
        $model->setMinYears(isset($data['min_years']) ? (int) $data['min_years'] : 1);
        $model->setPeriods(array_key_exists('periods', $data) ? $data['periods'] : null);
        $model->setAllowRegister(isset($data['allow_register']) ? (bool) $data['allow_register'] : true);
        $model->setAllowTransfer(isset($data['allow_transfer']) ? (bool) $data['allow_transfer'] : true);
        $model->setActive(isset($data['active']) ? (bool) $data['active'] : true);

        $this->di['em']->persist($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Created new top level domain %s', $model->getTld());

        return $model->getId();
    }

    public function tldUpdate(Tld $model, $data): bool
    {
        $data = $this->validateTldConfiguration($data);
        $model->setTld($this->normalizeTld((string) $model->getTld()));

        if (array_key_exists('tld_registrar_id', $data)) {
            $registrar = $this->getExistingRegistrar((int) $data['tld_registrar_id']);
            $this->registrarValidateConfiguration($registrar);
        }

        $model->setTldRegistrarId(isset($data['tld_registrar_id']) ? (int) $data['tld_registrar_id'] : $model->getTldRegistrarId());
        $model->setPriceRegistration(isset($data['price_registration']) ? (string) $data['price_registration'] : $model->getPriceRegistration());
        $model->setPriceRenew(isset($data['price_renew']) ? (string) $data['price_renew'] : $model->getPriceRenew());
        $model->setPriceTransfer(isset($data['price_transfer']) ? (string) $data['price_transfer'] : $model->getPriceTransfer());
        $model->setMinYears(isset($data['min_years']) ? (int) $data['min_years'] : $model->getMinYears());
        $model->setPeriods(array_key_exists('periods', $data) ? $data['periods'] : $model->getPeriods());
        $model->setAllowRegister(array_key_exists('allow_register', $data) ? (bool) $data['allow_register'] : $model->isAllowRegister());
        $model->setAllowTransfer(array_key_exists('allow_transfer', $data) ? (bool) $data['allow_transfer'] : $model->isAllowTransfer());
        $model->setActive(array_key_exists('active', $data) ? (bool) $data['active'] : $model->isActive());

        $this->di['em']->flush();

        $this->di['logger']->info('Updated top level domain %s', $model->getTld());

        return true;
    }

    private function validateTldConfiguration(array $data): array
    {
        foreach (['price_registration', 'price_renew', 'price_transfer'] as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if (!is_numeric($data[$field])) {
                throw new \FOSSBilling\InformationException('Domain price must be a non-negative number');
            }

            $price = (float) $data[$field];
            if (!is_finite($price) || $price < 0) {
                throw new \FOSSBilling\InformationException('Domain price must be a non-negative number');
            }
            $data[$field] = $price;
        }

        if (array_key_exists('min_years', $data)) {
            $minimumYears = filter_var($data['min_years'], FILTER_VALIDATE_INT);
            if ($minimumYears === false || $minimumYears < 1) {
                throw new \FOSSBilling\InformationException('Minimum registration period must be a positive integer');
            }
            $data['min_years'] = $minimumYears;
        }

        if (array_key_exists('periods', $data)) {
            $data['periods'] = $this->normalizePeriods($data['periods']);
        }

        return $data;
    }

    /**
     * Normalize a raw comma-separated "periods" string into a sorted, deduplicated
     * comma-separated string of positive integers, or null when empty.
     */
    private function normalizePeriods(?string $periods): ?string
    {
        if ($periods === null || trim($periods) === '') {
            return null;
        }

        $years = array_filter(array_map(trim(...), explode(',', $periods)), fn ($year): bool => $year !== '');

        $normalized = [];
        foreach ($years as $year) {
            $value = filter_var($year, FILTER_VALIDATE_INT);
            if ($value === false || $value < 1) {
                throw new \FOSSBilling\InformationException('Registration periods must be a comma-separated list of positive integers');
            }
            $normalized[] = $value;
        }

        if ($normalized === []) {
            return null;
        }

        $normalized = array_unique($normalized);
        sort($normalized);

        return implode(',', $normalized);
    }

    public function tldGetSearchQuery($data): QueryBuilder
    {
        $query = $this->getTldRepository()->createQueryBuilder('t');

        $hide_inactive = (bool) ($data['hide_inactive'] ?? false);
        $allow_register = $data['allow_register'] ?? null;
        $allow_transfer = $data['allow_transfer'] ?? null;

        if ($hide_inactive) {
            $query->andWhere('t.active = :active')
                ->setParameter('active', true);
        }

        if ($allow_register !== null) {
            $query->andWhere('t.allowRegister = :allowRegister')
                ->setParameter('allowRegister', (bool) $allow_register);
        }

        if ($allow_transfer !== null) {
            $query->andWhere('t.allowTransfer = :allowTransfer')
                ->setParameter('allowTransfer', (bool) $allow_transfer);
        }

        return $query->orderBy('t.id', 'ASC');
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
        return $this->getTldRepository()->findOneActiveById((int) $id);
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
        return $this->tldFindOneByTld($tld) !== null;
    }

    public function tldRm(Tld $model): bool
    {
        $id = $model->getId();
        $this->di['em']->remove($model);
        $this->di['em']->flush();
        $this->di['logger']->info('Deleted top level domain %s', $id);

        return true;
    }

    public function tldToApiArray(Tld $model, $identity = null): array
    {
        $result = [
            'id' => $model->getId(),
            'tld' => $model->getTld(),
            'price_registration' => $model->getPriceRegistration(),
            'price_renew' => $model->getPriceRenew(),
            'price_transfer' => $model->getPriceTransfer(),
            'active' => $model->isActive(),
            'allow_register' => $model->isAllowRegister(),
            'allow_transfer' => $model->isAllowTransfer(),
            'min_years' => $model->getMinYears(),
            'periods' => $model->getPeriodsArray(),
        ];

        if ($identity instanceof \Model_Admin) {
            $tldRegistrarId = $model->getTldRegistrarId();
            $tldRegistrar = $tldRegistrarId !== null ? $this->getTldRegistrarRepository()->find($tldRegistrarId) : null;

            $result['registrar'] = [
                'id' => $tldRegistrarId,
                'title' => $tldRegistrar instanceof TldRegistrar ? $tldRegistrar->getName() : null,
            ];
        }

        return $result;
    }

    public function tldFindOneByTld($tld): ?Tld
    {
        $normalizedTld = $this->normalizeTld($tld);
        $model = $this->getTldRepository()->findOneByTld($normalizedTld);
        if (!$model instanceof Tld) {
            $model = $this->getTldRepository()->findOneByTld(ltrim($normalizedTld, '.'));
        }

        return $model;
    }

    /**
     * Convert a TLD or public suffix to the representation used throughout
     * FOSSBilling and registrar adapters.
     */
    public function normalizeTld(string $tld): string
    {
        $tld = trim($tld);
        $tld = trim($tld, '.');
        if ($tld === '') {
            throw new \FOSSBilling\InformationException('TLD is invalid.');
        }

        $asciiTld = idn_to_ascii($tld, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($asciiTld === false || strlen($asciiTld) > 253) {
            throw new \FOSSBilling\InformationException('TLD is invalid.');
        }

        foreach (explode('.', strtolower($asciiTld)) as $label) {
            if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
                throw new \FOSSBilling\InformationException('TLD is invalid.');
            }
        }

        return '.' . strtolower($asciiTld);
    }

    public function tldFindOneById($id): ?Tld
    {
        return $this->getTldRepository()->find((int) $id);
    }

    public function registrarGetSearchQuery($data): QueryBuilder
    {
        // Registrar listings currently have no filters.
        unset($data);

        return $this->getTldRegistrarRepository()
            ->createQueryBuilder('tr')
            ->orderBy('tr.name', 'ASC');
    }

    /**
     * @return string[]
     */
    public function registrarGetAvailable(): array
    {
        $query = 'SELECT registrar FROM tld_registrar GROUP BY registrar';
        $exists = $this->di['em']->getConnection()->fetchAllAssociative($query);
        $existing = array_column($exists, 'registrar');

        $adapters = [];

        $finder = new Finder();
        $finder->files()->in(Path::join(PATH_LIBRARY, 'Registrar', 'Adapter'))->name('*.php')->depth('== 0');
        foreach ($finder as $file) {
            $adapter = $file->getFilenameWithoutExtension();
            if (!in_array($adapter, $existing, true)) {
                $adapters[] = $adapter;
            }
        }

        return $adapters;
    }

    public function registrarGetPairs()
    {
        return $this->getTldRegistrarRepository()->getIdNamePairs();
    }

    public function registrarGetActiveRegistrar(): ?TldRegistrar
    {
        return $this->getTldRegistrarRepository()->findActiveRegistrar();
    }

    public function registrarGetConfiguration(TldRegistrar $model): array
    {
        return json_decode($model->getConfig() ?? '', true) ?? [];
    }

    public function registrarGetRegistrarAdapterConfig(TldRegistrar $model)
    {
        $class = $this->registrarGetRegistrarAdapterClassName($model);

        return call_user_func([$class, 'getConfig']);
    }

    private function registrarGetRegistrarAdapterClassName(TldRegistrar $model): string
    {
        $file = Path::join(PATH_LIBRARY, 'Registrar', 'Adapter', "{$model->getRegistrar()}.php");
        if (!$this->filesystem->exists($file)) {
            throw new \FOSSBilling\InformationException('Domain registrar :adapter was not found', [':adapter' => $model->getRegistrar()]);
        }

        $class = sprintf('Registrar_Adapter_%s', $model->getRegistrar());
        if (!class_exists($class)) {
            require_once $file;
        }

        if (!class_exists($class)) {
            throw new \FOSSBilling\InformationException('Registrar :adapter was not found', [':adapter' => $class]);
        }

        return $class;
    }

    public function registrarGetRegistrarAdapter(TldRegistrar $r, ?Order $order = null)
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

        if ($r->isTestMode()) {
            $registrar->enableTestMode();
        }

        return $registrar;
    }

    public function registrarValidateConfiguration(TldRegistrar $model): void
    {
        $configuration = $this->registrarGetConfiguration($model);
        $adapterConfiguration = $this->registrarGetRegistrarAdapterConfig($model);

        foreach ($adapterConfiguration['form'] ?? [] as $field => $element) {
            $options = $element[1] ?? [];
            $required = isset($options['required']) && $options['required'] !== false && $options['required'] !== 'false';

            if (isset($options['required_when']) && is_array($options['required_when'])) {
                $required = true;
                foreach ($options['required_when'] as $modelField => $expectedValue) {
                    $property = str_replace('_', '', ucwords((string) $modelField, '_'));
                    $currentValue = null;
                    foreach (['get' . $property, 'is' . $property] as $method) {
                        if (method_exists($model, $method)) {
                            $currentValue = $model->$method();

                            break;
                        }
                    }
                    if ($currentValue != $expectedValue) {
                        $required = false;

                        break;
                    }
                }
            }

            $value = $configuration[$field] ?? null;
            if ($required && ($value === null || $value === '' || $value === [])) {
                $name = $model->getName() ?: $model->getRegistrar();

                throw new \FOSSBilling\InformationException('Registrar :registrar is missing required configuration: :field', [':registrar' => $name, ':field' => $options['label'] ?? $field]);
            }
        }
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

    public function registrarCopy(TldRegistrar $model): ?int
    {
        $new = new TldRegistrar();
        $new->setName($model->getName() . ' (Copy)');
        $new->setRegistrar($model->getRegistrar());
        $new->setTestMode($model->isTestMode());

        $this->di['em']->persist($new);
        $this->di['em']->flush();

        $this->di['logger']->info('Copied domain registrar %s', $model->getRegistrar());

        return $new->getId();
    }

    public function registrarUpdate(TldRegistrar $model, $data): bool
    {
        $model->setName($data['title'] ?? $model->getName());
        $model->setTestMode(array_key_exists('test_mode', $data) ? (bool) $data['test_mode'] : $model->isTestMode());
        if (isset($data['config']) && is_array($data['config'])) {
            $configuration = array_replace($this->registrarGetConfiguration($model), $data['config']);
            $adapterConfiguration = $this->registrarGetRegistrarAdapterConfig($model);

            foreach ($adapterConfiguration['form'] ?? [] as $field => $element) {
                $options = $element[1] ?? [];
                if (!array_key_exists($field, $configuration) && array_key_exists('default', $options)) {
                    $configuration[$field] = $options['default'];
                }
            }

            $model->setConfig(json_encode($configuration, JSON_THROW_ON_ERROR));
            $this->registrarValidateConfiguration($model);
        }

        $this->di['em']->flush();

        $this->di['logger']->info('Updated domain registrar %s configuration', $model->getRegistrar());

        return true;
    }

    public function registrarRm(TldRegistrar $model): bool
    {
        $domains = $this->getDomainRepository()->findByTldRegistrarId((int) $model->getId());
        $count = \FOSSBilling\Tools::safeCount($domains);

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Registrar is used by :count: domains', [':count:' => $count], 707);
        }

        $tlds = $this->getTldRepository()->findBy(['tldRegistrarId' => (int) $model->getId()]);
        $count = \FOSSBilling\Tools::safeCount($tlds);

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Registrar is used by :count: TLDs', [':count:' => $count], 707);
        }

        $name = $model->getName();

        $this->di['em']->remove($model);
        $this->di['em']->flush();

        $this->di['logger']->info('Removed domain registrar %s', $name);

        return true;
    }

    public function registrarToApiArray(TldRegistrar $model): array
    {
        $c = $this->registrarGetRegistrarAdapterConfig($model);

        return [
            'id' => $model->getId(),
            'title' => $model->getName(),
            'label' => $c['label'],
            'config' => $this->registrarGetConfiguration($model),
            'form' => $c['form'],
            'test_mode' => $model->isTestMode(),
        ];
    }

    public function updateDomain(ServiceDomain $s, $data): bool
    {
        $s->setNs1($data['ns1'] ?? $s->getNs1());
        $s->setNs2($data['ns2'] ?? $s->getNs2());
        $s->setNs3($data['ns3'] ?? $s->getNs3());
        $s->setNs4($data['ns4'] ?? $s->getNs4());

        $s->setPeriod((int) ($data['period'] ?? $s->getPeriod()));
        $s->setPrivacy((bool) ($data['privacy'] ?? $s->getPrivacy()));
        $s->setLocked((bool) ($data['locked'] ?? $s->isLocked()));
        $s->setTransferCode($data['transfer_code'] ?? $s->getTransferCode());

        $this->di['em']->flush();

        $this->di['logger']->info('Updated domain #%s without sending actions to server', $s->getId());

        return true;
    }

    private function formatRegistrarTimestamp(mixed $timestamp): ?\DateTime
    {
        if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
            return null;
        }

        return new \DateTime('@' . (int) $timestamp);
    }

    private function formatDateTime(?\DateTime $dateTime): ?string
    {
        return $dateTime?->format('Y-m-d H:i:s');
    }

    private function getExistingRegistrar(?int $id): TldRegistrar
    {
        $registrar = $id === null ? null : $this->getTldRegistrarRepository()->find($id);
        if (!$registrar instanceof TldRegistrar) {
            throw new \FOSSBilling\Exception('Registrar not found');
        }

        return $registrar;
    }

    private function _getOrderService(Order $order, bool $required = true): ?ServiceDomain
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof ServiceDomain) {
            if ($required) {
                throw new \FOSSBilling\Exception('Could not find associated service domain');
            }

            return null;
        }

        return $model;
    }

    private function getDomainRepository(): DomainRepository
    {
        return $this->di['em']->getRepository(ServiceDomain::class);
    }

    private function getTldRepository(): TldRepository
    {
        return $this->di['em']->getRepository(Tld::class);
    }

    private function getTldRegistrarRepository(): TldRegistrarRepository
    {
        return $this->di['em']->getRepository(TldRegistrar::class);
    }
}
