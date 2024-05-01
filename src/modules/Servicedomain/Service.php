<?php

/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

namespace Box\Mod\Servicedomain;

class Service implements \FOSSBilling\InjectionAwareInterface
{
    protected ?\Pimple\Container $di = null;

    public function setDi(\Pimple\Container $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
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

    public function validateOrderData(&$data)
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
                $safe_dom = htmlspecialchars($data['owndomain_sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

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
                $safe_dom = htmlspecialchars($data['transfer_sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
            }

            $tld = $this->tldFindOneByTld($data['transfer_tld']);
            if (!$tld instanceof \Model_Tld) {
                throw new \FOSSBilling\Exception('TLD not found');
            }

            $domain = $data['transfer_sld'] . $tld->tld;
            if (!$this->canBeTransferred($tld, $data['transfer_sld'])) {
                throw new \FOSSBilling\InformationException(':domain cannot be transferred!', [':domain' => $domain]);
            }

            // return by reference
            $data['period'] = '1Y';
            $data['quantity'] = 1;
        }

        if ($action == 'register') {
            $required = [
                'register_tld' => 'Domain registration tld parameter missing.',
                'register_sld' => 'Domain registration sld parameter missing.',
                'register_years' => 'Years parameter is missing for domain configuration.',
            ];
            $this->di['validator']->checkRequiredParamsForArray($required, $data);

            if (!$validator->isSldValid($data['register_sld'])) {
                $safe_dom = htmlspecialchars($data['register_sld'], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
            }

            $tld = $this->tldFindOneByTld($data['register_tld']);
            if (!$tld instanceof \Model_Tld) {
                throw new \FOSSBilling\Exception('TLD not found');
            }

            $years = (int) $data['register_years'];
            if ($years < $tld->min_years) {
                throw new \FOSSBilling\Exception(':tld can be registered for at least :years years', [':tld' => $tld->tld, ':years' => $tld->min_years]);
            }

            $domain = $data['register_sld'] . $tld->tld;
            if (!$this->isDomainAvailable($tld, $data['register_sld'])) {
                throw new \FOSSBilling\InformationException(':domain is already registered!', [':domain' => $domain]);
            }

            // return by reference
            $data['period'] = $years . 'Y';
            $data['quantity'] = $years;
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

    /**
     * Creates domain service object from order.
     *
     * @return \Model_ServiceDomain
     */
    public function action_create(\Model_ClientOrder $order)
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

        $model = $this->di['db']->dispense('ServiceDomain');
        $model->client_id = $order->client_id;
        $model->tld_registrar_id = $tldModel->tld_registrar_id;
        $model->sld = $sld;
        $model->tld = $tld;
        $model->period = $years;
        $model->transfer_code = $c['transfer_code'] ?? null;
        $model->privacy = false;
        $model->action = $c['action'];
        $model->ns1 = (isset($c['ns1']) && !empty($c['ns1'])) ? $c['ns1'] : $ns['nameserver_1'];
        $model->ns2 = (isset($c['ns2']) && !empty($c['ns1'])) ? $c['ns2'] : $ns['nameserver_2'];
        $model->ns3 = (isset($c['ns3']) && !empty($c['ns1'])) ? $c['ns3'] : $ns['nameserver_3'];
        $model->ns4 = (isset($c['ns4']) && !empty($c['ns1'])) ? $c['ns4'] : $ns['nameserver_4'];

        $client = $this->di['db']->getExistingModelById('Client', $model->client_id, 'Client not found');

        $model->contact_first_name = $client->first_name;
        $model->contact_last_name = $client->last_name;
        $model->contact_email = $client->email;
        $model->contact_company = $client->company;
        $model->contact_address1 = $client->address_1;
        $model->contact_address2 = $client->address_2;
        $model->contact_country = $client->country;
        $model->contact_city = $client->city;
        $model->contact_state = $client->state;
        $model->contact_postcode = $client->postcode;
        $model->contact_phone_cc = $client->phone_cc;
        $model->contact_phone = $client->phone;

        $model->created_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        return $model;
    }

    /**
     * Register or transfer domain on activation.
     *
     * @return \Model_ServiceDomain
     */
    public function action_activate(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \Model_ServiceDomain) {
            throw new \FOSSBilling\Exception('Could not activate order. Service was not created');
        }

        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        if ($model->action == 'register') {
            $adapter->registerDomain($domain);
        }

        if ($model->action == 'transfer') {
            $adapter->transferDomain($domain);
        }

        // reset action
        $model->action = null;
        $this->di['db']->store($model);

        try {
            $this->syncWhois($model, $order);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        return $model;
    }

    /**
     * @return bool
     */
    public function action_renew(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->id]);
        }
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->renewDomain($domain);

        $this->syncWhois($model, $order);

        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_suspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @todo
     *
     * @return bool
     */
    public function action_unsuspend(\Model_ClientOrder $order)
    {
        return true;
    }

    /**
     * @return bool
     */
    public function action_cancel(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \RedBeanPHP\SimpleModel) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->id]);
        }
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->deleteDomain($domain);

        return true;
    }

    /**
     * @return bool
     */
    public function action_uncancel(\Model_ClientOrder $order)
    {
        $this->action_activate($order);

        return true;
    }

    /**
     * @return void
     */
    public function action_delete(\Model_ClientOrder $order)
    {
        $orderService = $this->di['mod_service']('order');
        $service = $orderService->getOrderService($order);

        if ($service instanceof \Model_ServiceDomain) {
            // cancel if not canceled
            if ($order->status != \Model_ClientOrder::STATUS_CANCELED) {
                $this->action_cancel($order);
            }
            $this->di['db']->trash($service);
        }
    }

    protected function syncWhois(\Model_ServiceDomain $model, \Model_ClientOrder $order)
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);

        // update whois
        $whois = $adapter->getDomainDetails($domain);

        $locked = $whois->getLocked();
        if ($locked !== null) {
            $model->locked = $locked;
        }

        // sync whois
        $contact = $whois->getContactRegistrar();

        $model->contact_first_name = $contact->getFirstName();
        $model->contact_last_name = $contact->getLastName();
        $model->contact_email = $contact->getEmail();
        $model->contact_company = $contact->getCompany();
        $model->contact_address1 = $contact->getAddress1();
        $model->contact_address2 = $contact->getAddress2();
        $model->contact_country = $contact->getCountry();
        $model->contact_city = $contact->getCity();
        $model->contact_state = $contact->getState();
        $model->contact_postcode = $contact->getZip();
        $model->contact_phone_cc = $contact->getTelCc();
        $model->contact_phone = $contact->getTel();

        $model->details = serialize($whois);
        $model->expires_at = date('Y-m-d H:i:s', $whois->getExpirationTime());
        $model->registered_at = date('Y-m-d H:i:s', $whois->getRegistrationTime());
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);
    }

    public function updateNameservers(\Model_ServiceDomain $model, $data)
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

        $model->ns1 = $ns1;
        $model->ns2 = $ns2;
        $model->ns3 = $ns3;
        $model->ns4 = $ns4;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Updated domain #%s nameservers', $id);

        return true;
    }

    public function updateContacts(\Model_ServiceDomain $model, $data)
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

        $model->contact_first_name = $contact['first_name'];
        $model->contact_last_name = $contact['last_name'];
        $model->contact_email = $contact['email'];
        $model->contact_company = $contact['company'];
        $model->contact_address1 = $contact['address1'];
        $model->contact_address2 = $contact['address2'];
        $model->contact_country = $contact['country'];
        $model->contact_city = $contact['city'];
        $model->contact_state = $contact['state'];
        $model->contact_postcode = $contact['postcode'];
        $model->contact_phone_cc = $contact['phone_cc'];
        $model->contact_phone = $contact['phone'];

        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->modifyContact($domain);

        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Updated domain #%s WHOIS details', $id);

        return true;
    }

    public function getTransferCode(\Model_ServiceDomain $model)
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);

        return $adapter->getEpp($domain);
    }

    public function lock(\Model_ServiceDomain $model)
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $epp = $adapter->lock($domain);

        $model->locked = true;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Locking domain #%s', $id);

        return true;
    }

    public function unlock(\Model_ServiceDomain $model)
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $epp = $adapter->unlock($domain);

        $model->locked = false;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Unlocking domain #%s', $id);

        return true;
    }

    public function enablePrivacyProtection(\Model_ServiceDomain $model)
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->enablePrivacyProtection($domain);

        $model->privacy = true;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Enabled privacy protection of #%s domain', $id);

        return true;
    }

    public function disablePrivacyProtection(\Model_ServiceDomain $model)
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->disablePrivacyProtection($domain);

        $model->privacy = false;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Disabled privacy protection of #%s domain', $id);

        return true;
    }

    public function canBeTransferred(\Model_Tld $model, $sld)
    {
        if (empty($sld)) {
            throw new \FOSSBilling\InformationException('Domain name is invalid');
        }

        if (!$model->allow_transfer) {
            throw new \FOSSBilling\InformationException('Domain cannot be transferred', null, 403);
        }

        // @adapterAction
        $domain = new \Registrar_Domain();
        $domain->setTld($model->tld);
        $domain->setSld($sld);

        $tldRegistrar = $this->di['db']->load('TldRegistrar', $model->tld_registrar_id);
        $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);

        return $adapter->isDomaincanBeTransferred($domain);
    }

    public function isDomainAvailable(\Model_Tld $model, $sld)
    {
        if (empty($sld)) {
            throw new \FOSSBilling\InformationException('Domain name is invalid');
        }

        $validator = $this->di['validator'];
        if (!$validator->isSldValid($sld)) {
            $safe_dom = htmlspecialchars($sld, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            throw new \FOSSBilling\InformationException('Domain name :domain is invalid', [':domain' => $safe_dom]);
        }

        if (!$model->allow_register) {
            throw new \FOSSBilling\InformationException('Domain cannot be registered', null, 403);
        }

        // @adapterAction
        $domain = new \Registrar_Domain();
        $domain->setTld($model->tld);
        $domain->setSld($sld);

        $tldRegistrar = $this->di['db']->load('TldRegistrar', $model->tld_registrar_id);
        $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);

        return $adapter->isDomainAvailable($domain);
    }

    public function syncExpirationDate($model)
    {
        // @todo
    }

    public function toApiArray(\Model_ServiceDomain $model, $deep = false, $identity = null): array
    {
        $data = [
            'domain' => $model->sld . $model->tld,
            'sld' => $model->sld,
            'tld' => $model->tld,
            'ns1' => $model->ns1,
            'ns2' => $model->ns2,
            'ns3' => $model->ns3,
            'ns4' => $model->ns4,
            'period' => $model->period,
            'privacy' => $model->privacy,
            'locked' => $model->locked,
            'registered_at' => $model->registered_at,
            'expires_at' => $model->expires_at,
            'contact' => [
                'first_name' => $model->contact_first_name,
                'last_name' => $model->contact_last_name,
                'email' => $model->contact_email,
                'company' => $model->contact_company,
                'address1' => $model->contact_address1,
                'address2' => $model->contact_address2,
                'country' => $model->contact_country,
                'city' => $model->contact_city,
                'state' => $model->contact_state,
                'postcode' => $model->contact_postcode,
                'phone_cc' => $model->contact_phone_cc,
                'phone' => $model->contact_phone,
            ],
        ];

        if ($identity instanceof \Model_Admin) {
            $data['transfer_code'] = $model->transfer_code;

            $tldRegistrar = $this->di['db']->load('TldRegistrar', $model->tld_registrar_id);
            $data['registrar'] = $tldRegistrar->name;
        }

        return $data;
    }

    private function _getTuple($data)
    {
        $action = $data['action'];
        [$sld, $tld] = [null, null];

        if ($action == 'owndomain') {
            $sld = $data['owndomain_sld'];
            $tld = str_contains($data['domain']['owndomain_tld'], '.') ? $data['domain']['owndomain_tld'] : '.' . $data['domain']['owndomain_tld'];
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

    protected function _getD(\Model_ServiceDomain $model)
    {
        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);

        $tldRegistrar = $this->di['db']->load('TldRegistrar', $model->tld_registrar_id);

        if ($order instanceof \Model_ClientOrder) {
            $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar, $order);
        } else {
            $adapter = $this->registrarGetRegistrarAdapter($tldRegistrar);
        }

        $d = new \Registrar_Domain();

        $d->setLocked($model->locked);
        $d->setNs1($model->ns1);
        $d->setNs2($model->ns2);
        $d->setNs3($model->ns3);
        $d->setNs4($model->ns4);

        // merge info with current profile
        $client = $this->di['db']->load('Client', $model->client_id);

        $email = empty($model->contact_email) ? $client->email : $model->contact_email;
        $first_name = empty($model->contact_first_name) ? $client->first_name : $model->contact_first_name;
        $last_name = empty($model->contact_last_name) ? $client->last_name : $model->contact_last_name;
        $city = empty($model->contact_city) ? $client->city : $model->contact_city;
        $zip = empty($model->contact_postcode) ? $client->postcode : $model->contact_postcode;
        $country = empty($model->contact_country) ? $client->country : $model->contact_country;
        $state = empty($model->contact_state) ? $client->state : $model->contact_state;
        $phone = empty($model->contact_phone) ? $client->phone : $model->contact_phone;
        $phone_cc = empty($model->contact_phone_cc) ? $client->phone_cc : $model->contact_phone_cc;
        $company = empty($model->contact_company) ? $client->company : $model->contact_company;
        $address1 = empty($model->contact_address1) ? $client->address_1 : $model->contact_address1;
        $address2 = empty($model->contact_address2) ? $client->address_2 : $model->contact_address2;
        $birthday = !empty($client->birthday) ? $client->birthday : '';
        $company_number = !empty($client->company_number) ? $client->company_number : '';
        $document_nr = !empty($client->document_nr) ? $client->document_nr : '';

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

        $d->setTld($model->tld);
        $d->setSld($model->sld);
        $d->setRegistrationPeriod($model->period);
        $d->setEpp($model->transfer_code);

        if ($model->expires_at) {
            $d->setExpirationTime(strtotime($model->expires_at));
        }

        return [$d, $adapter];
    }

    public static function onBeforeAdminCronRun(\Box_Event $event)
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

    public function batchSyncExpirationDates()
    {
        $key = 'servicedomain_last_sync';

        $ss = $this->di['mod_service']('system');
        $last_time = $ss->getParamValue($key);
        if ($last_time && (time() - strtotime($last_time)) < 86400 * 30) {
            return false;
        }

        $list = $this->di['db']->find('ServiceDomain');

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

    public function tldCreate($data)
    {
        $model = $this->di['db']->dispense('Tld');
        $model->tld = $data['tld'];
        $model->tld_registrar_id = $data['tld_registrar_id'];
        $model->price_registration = $data['price_registration'];
        $model->price_renew = $data['price_renew'];
        $model->price_transfer = $data['price_transfer'];
        $model->min_years = isset($data['min_years']) ? (int) $data['min_years'] : 1;
        $model->allow_register = isset($data['allow_register']) ? (bool) $data['allow_register'] : true;
        $model->allow_transfer = isset($data['allow_transfer']) ? (bool) $data['allow_transfer'] : true;
        $model->active = isset($data['active']) && (bool) $data['active'];
        $model->updated_at = date('Y-m-d H:i:s');
        $model->created_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Created new top level domain %s', $model->tld);

        return $id;
    }

    public function tldUpdate(\Model_Tld $model, $data)
    {
        $model->tld_registrar_id = $data['tld_registrar_id'] ?? $model->tld_registrar_id;
        $model->price_registration = $data['price_registration'] ?? $model->price_registration;
        $model->price_renew = $data['price_renew'] ?? $model->price_renew;
        $model->price_transfer = $data['price_transfer'] ?? $model->price_transfer;
        $model->min_years = $data['min_years'] ?? $model->min_years;
        $model->allow_register = $data['allow_register'] ?? $model->allow_register;
        $model->allow_transfer = $data['allow_transfer'] ?? $model->allow_transfer;
        $model->active = $data['active'] ?? $model->active;
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated top level domain %s', $model->tld);

        return true;
    }

    public function tldGetSearchQuery($data)
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

    public function tldFindAllActive()
    {
        return $this->di['db']->find('Tld', 'active = 1 ORDER by id ASC');
    }

    public function tldFindOneActiveById($id)
    {
        return $this->di['db']->findOne('Tld', 'id = :id AND active = 1 ORDER by id ASC', [':id' => $id]);
    }

    public function tldGetPairs()
    {
        return $this->di['db']->getAssoc('SELECT id, tld from tld WHERE active = 1 ORDER by id ASC');
    }

    public function tldAlreadyRegistered($tld)
    {
        $tld = $this->di['db']->findOne('Tld', 'tld = :tld ORDER by id ASC', [':tld' => $tld]);

        return $tld instanceof \Model_Tld;
    }

    public function tldRm(\Model_Tld $model)
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted top level domain %s', $id);

        return true;
    }

    public function tldToApiArray(\Model_Tld $model)
    {
        $tldRegistrar = $this->di['db']->load('TldRegistrar', $model->tld_registrar_id);

        return [
            'id' => $model->id,
            'tld' => $model->tld,
            'price_registration' => $model->price_registration,
            'price_renew' => $model->price_renew,
            'price_transfer' => $model->price_transfer,
            'active' => $model->active,
            'allow_register' => $model->allow_register,
            'allow_transfer' => $model->allow_transfer,
            'min_years' => $model->min_years,
            'registrar' => [
                'id' => $model->tld_registrar_id,
                'title' => $tldRegistrar->name,
            ],
        ];
    }

    /**
     * @return \Model_Tld
     */
    public function tldFindOneByTld($tld)
    {
        return $this->di['db']->findOne('Tld', 'tld = :tld ORDER by id ASC', [':tld' => $tld]);
    }

    public function tldFindOneById($id)
    {
        return $this->di['db']->findOne('Tld', 'id = :id ORDER by id ASC', [':id' => $id]);
    }

    public function registrarGetSearchQuery($data)
    {
        $query = 'SELECT * FROM tld_registrar ORDER BY name ASC';
        $bindings = [];

        return [$query, $bindings];
    }

    /**
     * @return mixed[][]|string[]
     */
    public function registrarGetAvailable(): array
    {
        $query = "SELECT 'registrar', 'name' FROM tld_registrar GROUP BY registrar";

        $exists = $this->di['db']->getAssoc($query);

        $pattern = PATH_LIBRARY . '/Registrar/Adapter/*.php';
        $adapters = [];
        foreach (glob($pattern) as $path) {
            $adapter = pathinfo($path, PATHINFO_FILENAME);
            if (!array_key_exists($adapter, $exists)) {
                $adapters[] = $adapter;
            }
        }

        return $adapters;
    }

    public function registrarGetPairs()
    {
        $query = 'SELECT tr.id, tr.name FROM tld_registrar tr ORDER BY tr.id DESC';

        return $this->di['db']->getAssoc($query);
    }

    public function registrarGetActiveRegistrar()
    {
        return $this->di['db']->findOne('TldRegistrar', 'config IS NOT NULL LIMIT 1');
    }

    public function registrarGetConfiguration(\Model_TldRegistrar $model): array
    {
        if (is_string($model->config) && json_validate($model->config)) {
            return json_decode($model->config, true);
        }

        return [];
    }

    public function registrarGetRegistrarAdapterConfig(\Model_TldRegistrar $model)
    {
        $class = $this->registrarGetRegistrarAdapterClassName($model);

        return call_user_func([$class, 'getConfig']);
    }

    private function registrarGetRegistrarAdapterClassName(\Model_TldRegistrar $model)
    {
        if (!file_exists(PATH_LIBRARY . '/Registrar/Adapter/' . $model->registrar . '.php')) {
            throw new \FOSSBilling\Exception('Domain registrar :adapter was not found', [':adapter' => $model->registrar]);
        }

        $class = sprintf('Registrar_Adapter_%s', $model->registrar);
        if (!class_exists($class)) {
            throw new \FOSSBilling\Exception('Registrar :adapter was not found', [':adapter' => $class]);
        }

        return $class;
    }

    public function registrarGetRegistrarAdapter(\Model_TldRegistrar $r, \Model_ClientOrder $order = null)
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

        if (isset($r->test_mode) && $r->test_mode) {
            $registrar->enableTestMode();
        }

        return $registrar;
    }

    public function registrarCreate($code)
    {
        $model = $this->di['db']->dispense('TldRegistrar');
        $model->name = $code;
        $model->registrar = $code;
        $model->test_mode = 0;

        $this->di['db']->store($model);

        $this->di['logger']->info('Installed new domain registrar %s', $code);

        return true;
    }

    public function registrarCopy(\Model_TldRegistrar $model)
    {
        $new = $this->di['db']->dispense('TldRegistrar');
        $new->name = $model->name . ' (Copy)';
        $new->registrar = $model->registrar;
        $new->test_mode = $model->test_mode;

        $id = $this->di['db']->store($new);

        $this->di['logger']->info('Copied domain registrar %s', $model->registrar);

        return $id;
    }

    public function registrarUpdate(\Model_TldRegistrar $model, $data)
    {
        $model->name = $data['title'] ?? $model->name;
        $model->test_mode = $data['test_mode'] ?? $model->test_mode;
        if (isset($data['config']) && is_array($data['config'])) {
            $model->config = json_encode($data['config']);
        }

        $this->di['db']->store($model);

        $this->di['logger']->info('Updated domain registrar %s configuration', $model->registrar);

        return true;
    }

    public function registrarRm(\Model_TldRegistrar $model)
    {
        $domains = $this->di['db']->find('ServiceDomain', 'tld_registrar_id = :registrar_id', [':registrar_id' => $model->id]);
        $count = is_countable($domains) ? count($domains) : 0;

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Registrar is used by :count: domains', [':count:' => $count], 707);
        }

        $name = $model->name;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed domain registrar %s', $name);

        return true;
    }

    public function registrarToApiArray(\Model_TldRegistrar $model)
    {
        $c = $this->registrarGetRegistrarAdapterConfig($model);

        return [
            'id' => $model->id,
            'title' => $model->name,
            'label' => $c['label'],
            'config' => $this->registrarGetConfiguration($model),
            'form' => $c['form'],
            'test_mode' => $model->test_mode,
        ];
    }

    public function updateDomain(\Model_ServiceDomain $s, $data)
    {
        $s->ns1 = $data['ns1'] ?? $s->ns1;
        $s->ns2 = $data['ns2'] ?? $s->ns2;
        $s->ns3 = $data['ns3'] ?? $s->ns3;
        $s->ns4 = $data['ns4'] ?? $s->ns4;

        $s->period = (int) $data['period'] ?? $s->period;
        $s->privacy = (bool) ($data['privacy'] ?? $s->privacy);
        $s->locked = (bool) ($data['locked'] ?? $s->locked);
        $s->transfer_code = $data['transfer_code'] ?? $s->transfer_code;
        $s->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($s);

        $this->di['logger']->info('Updated domain #%s without sending actions to server', $s->id);

        return true;
    }
}
