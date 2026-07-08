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

use Box\Mod\Product\Entity\Product;
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
            return $data['register_sld'] . $data['register_tld'];
        }

        if (
            isset($data['action']) && $data['action'] == 'transfer'
            && isset($data['transfer_tld']) && isset($data['transfer_sld'])
        ) {
            return $data['transfer_sld'] . $data['transfer_tld'];
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
            if (!$tld instanceof \Model_Tld) {
                throw new \FOSSBilling\InformationException('TLD not found');
            }

            $domain = $data['transfer_sld'] . $tld->tld;
            if (!$this->canBeTransferred($tld, $data['transfer_sld'])) {
                throw new \FOSSBilling\InformationException(':domain cannot be transferred!', [':domain' => $domain]);
            }

            if ($tld->requires_transfer_code && empty($data['transfer_code'])) {
                throw new \FOSSBilling\InformationException(':tld domains require an auth/EPP code to transfer', [':tld' => $tld->tld]);
            }

            $lockState = $this->getTransferLockState($domain);
            if ($lockState !== null) {
                throw new \FOSSBilling\InformationException(':domain is locked at its current registrar (:state). Ask them to unlock the domain (and disable any transfer lock in their control panel) before starting a transfer.', [':domain' => $domain, ':state' => $lockState]);
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
            if (!$tld instanceof \Model_Tld) {
                throw new \FOSSBilling\InformationException('TLD not found');
            }

            $years = (int) $data['register_years'];
            if ($years < $tld->min_years) {
                throw new \FOSSBilling\Exception(':tld can be registered for at least :years years', [':tld' => $tld->tld, ':years' => $tld->min_years]);
            }

            $domain = $data['register_sld'] . $tld->tld;
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

        // Re-check availability/transferability right before the real (paid, committal)
        // registrar call. validateOrderData() only ran once, at cart-add time - an arbitrary
        // amount of time can pass before payment clears and we get here (cart abandonment,
        // an invoice sitting unpaid, manual admin approval), during which someone else may
        // have grabbed the domain or it may have dropped out of transfer eligibility. Catching
        // that here gives a clear, specific failure instead of a generic registrar rejection.
        $tld = $this->tldFindOneByTld($model->tld);
        if ($tld instanceof \Model_Tld) {
            if ($model->action == 'register' && !$this->isDomainAvailable($tld, $model->sld)) {
                throw new \FOSSBilling\Exception('Domain :domain is no longer available to register', [':domain' => $model->sld . $model->tld]);
            }

            if ($model->action == 'transfer' && !$this->canBeTransferred($tld, $model->sld)) {
                throw new \FOSSBilling\Exception('Domain :domain is no longer eligible for transfer', [':domain' => $model->sld . $model->tld]);
            }

            if ($model->action == 'transfer') {
                $lockState = $this->getTransferLockState($model->sld . $model->tld);
                if ($lockState !== null) {
                    throw new \FOSSBilling\Exception('Domain :domain is locked at its current registrar (:state)', [':domain' => $model->sld . $model->tld, ':state' => $lockState]);
                }
            }
        }

        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        if ($model->action == 'register') {
            $accepted = $adapter->registerDomain($domain);
        }

        if ($model->action == 'transfer') {
            $accepted = $adapter->transferDomain($domain);
        }

        // The registrar rejected the request outright (e.g. bad auth code, domain not
        // transferable). Don't let the order go active on a domain we never actually got.
        if (isset($accepted) && $accepted === false) {
            throw new \FOSSBilling\Exception('Registrar rejected the :action request for :domain', [':action' => $model->action, ':domain' => $model->sld . $model->tld]);
        }

        // reset action
        $model->action = null;
        $this->di['db']->store($model);

        try {
            // Order/Service.php::createFromOrder() extends $order->expires_at by one period
            // right after this action runs, starting from whatever it's currently set to.
            // Reconciling it to the registrar's expiry here would just get overwritten by
            // that extension anyway - skip it to avoid stacking a period on top of a period.
            $this->syncWhois($model, $order, reconcileOrderExpiry: false);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        // Registrar accepted the request initially but the immediate status check already
        // shows it was dropped/rejected (e.g. failed payment on the registrar's end).
        if ($this->isRejectedRegistrarStatus($model->registrar_status)) {
            throw new \FOSSBilling\Exception('Registrar rejected the :action request for :domain (status: :status)', [':action' => $model->action, ':domain' => $model->sld . $model->tld, ':status' => $model->registrar_status]);
        }

        // The registrar accepted the request but hasn't finished processing it yet (e.g.
        // OpenProvider's "REQ" status). Park the order rather than marking it active - the
        // cron sync in batchSyncDomainStatuses() will promote it once the registrar confirms.
        if ($this->isPendingRegistrarStatus($model->registrar_status)) {
            throw new \FOSSBilling\OrderPendingRegistrarConfirmationException('Registrar has not confirmed the :action yet for :domain (status: :status)', [':action' => $model->action, ':domain' => $model->sld . $model->tld, ':status' => $model->registrar_status]);
        }

        return $model;
    }

    /**
     * Registrar status codes that mean "still processing" - not yet confirmed active, and
     * not (yet) rejected. Currently modelled on OpenProvider's status codes; a null/unknown
     * status is treated as confirmed so registrars/adapters that don't report one don't get
     * stuck pending forever.
     */
    protected function isPendingRegistrarStatus(?string $status): bool
    {
        return in_array($status, ['REQ', 'ACP'], true);
    }

    /**
     * Registrar status codes that mean the registrar rejected/dropped the request.
     */
    protected function isRejectedRegistrarStatus(?string $status): bool
    {
        return in_array($status, ['FAI', 'CAN', 'DEL'], true);
    }

    public function action_renew(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \Model_ServiceDomain) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->id]);
        }
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->renewDomain($domain);

        // Same reasoning as action_activate() above: Order/Service.php::renewFromOrder()
        // extends $order->expires_at by one period right after this returns.
        $this->syncWhois($model, $order, reconcileOrderExpiry: false);

        return true;
    }

    /**
     * @todo
     */
    public function action_suspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    /**
     * @todo
     */
    public function action_unsuspend(\Model_ClientOrder $order): bool
    {
        return true;
    }

    public function action_cancel(\Model_ClientOrder $order): bool
    {
        $orderService = $this->di['mod_service']('order');
        $model = $orderService->getOrderService($order);
        if (!$model instanceof \Model_ServiceDomain) {
            throw new \FOSSBilling\Exception('Order :id has no active service', [':id' => $order->id]);
        }
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        $adapter->deleteDomain($domain);

        return true;
    }

    public function action_uncancel(\Model_ClientOrder $order): bool
    {
        $this->action_activate($order);

        return true;
    }

    public function action_delete(\Model_ClientOrder $order): void
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

    protected function syncWhois(\Model_ServiceDomain $model, \Model_ClientOrder $order, bool $reconcileOrderExpiry = true)
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);

        // update whois
        $whois = $adapter->getDomainDetails($domain);

        $locked = $whois->getLocked();
        if ($locked !== null) {
            $model->locked = $locked;
        }

        $privacy = $whois->getPrivacyEnabled();
        if ($privacy !== null) {
            $model->privacy = $privacy;
        }

        $autorenew = $whois->getAutoRenew();
        if ($autorenew !== null) {
            $model->autorenew = $autorenew;
        }

        // Registrar's own status (e.g. OpenProvider's "REQ" while a transfer is still being
        // processed) - separate from FOSSBilling's order status, which goes active as soon as
        // the transfer/registration request is accepted, not when the registrar finishes it.
        $status = $whois->getStatus();
        if ($status !== null) {
            $model->registrar_status = $status;
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
        $model->expires_at = $this->formatRegistrarTimestamp($whois->getExpirationTime());
        $model->registered_at = $this->formatRegistrarTimestamp($whois->getRegistrationTime());
        $model->synced_at = date('Y-m-d H:i:s');
        $model->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($model);

        if ($reconcileOrderExpiry) {
            $this->reconcileOrderExpirationDate($order, $model);
        }
    }

    /**
     * The order's expiry date (which drives renewal invoicing) and the domain's registrar
     * expiry date (just pulled from the registrar above) are stored separately and can
     * drift apart - e.g. if the domain was renewed directly at the registrar. Since the
     * registrar is always the source of truth, bring the order's billing date back in line
     * with it whenever they disagree, so invoices are never generated against a stale date.
     */
    protected function reconcileOrderExpirationDate(\Model_ClientOrder $order, \Model_ServiceDomain $model): void
    {
        if (empty($model->expires_at)) {
            return;
        }

        if ($order->expires_at !== null && date('Y-m-d', strtotime($order->expires_at)) === date('Y-m-d', strtotime($model->expires_at))) {
            return;
        }

        $this->di['logger']->info('Order #%s billing expiry (%s) did not match registrar expiry (%s) for domain #%s - updating order to match registrar', $order->id, $order->expires_at, $model->expires_at, $model->id);

        $order->expires_at = date('Y-m-d H:i:s', strtotime($model->expires_at));
        $order->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($order);
    }

    /**
     * Re-pull a single domain's status (lock, privacy, auto-renew, expiry, contact) from
     * the registrar and overwrite the local copy with it - the registrar is always the
     * source of truth. Retries a few times before giving up, since this is also used by
     * the unattended daily batch sync where nobody is around to click "try again".
     */
    public function refreshDomainStatus(\Model_ServiceDomain $model, int $maxAttempts = 3): bool
    {
        $orderService = $this->di['mod_service']('order');
        $order = $orderService->getServiceOrder($model);

        if (!$order instanceof \Model_ClientOrder) {
            return false;
        }

        $attempt = 0;
        while ($attempt < $maxAttempts) {
            ++$attempt;

            try {
                $this->syncWhois($model, $order);
                $this->reconcilePendingRegistrarOrder($model, $order, $orderService);

                return true;
            } catch (\Exception $e) {
                $this->di['logger']->warning('Attempt %s of %s to sync domain #%s status with registrar failed: %s', $attempt, $maxAttempts, $model->id, $e->getMessage());
            }
        }

        return false;
    }

    /**
     * If the order is parked in STATUS_PENDING_REGISTRAR (registrar had accepted the
     * register/transfer request but hadn't confirmed it yet at activation time), check
     * whether the freshly-synced registrar_status has since resolved it one way or the
     * other, and promote or fail the order accordingly. No-op for orders in any other state.
     */
    protected function reconcilePendingRegistrarOrder(\Model_ServiceDomain $model, \Model_ClientOrder $order, $orderService): void
    {
        if ($order->status !== \Model_ClientOrder::STATUS_PENDING_REGISTRAR) {
            return;
        }

        if ($this->isRejectedRegistrarStatus($model->registrar_status)) {
            $order->status = \Model_ClientOrder::STATUS_FAILED_SETUP;
            $order->updated_at = date('Y-m-d H:i:s');
            $this->di['db']->store($order);
            $orderService->saveStatusChange($order, "Registrar rejected the domain request (status: {$model->registrar_status})");
            $this->notifyStaffOfRegistrarRejection($order, $model);

            return;
        }

        if (!$this->isPendingRegistrarStatus($model->registrar_status)) {
            // Confirmed (or an adapter that doesn't report a pending code at all) - activate.
            $orderService->finalizeActivation($order);
        }
    }

    /**
     * Staff alert for a rejected domain order. This is a plain error-level log entry, not an
     * email - wiring up a proper staff email template (see mod_staff_client_order in the
     * Staff module for the pattern) is a natural follow-up, but needs an actual DB-seeded
     * template + install migration, which is out of scope here. The rejection is also
     * recorded on the order's own status history via saveStatusChange() either way.
     */
    protected function notifyStaffOfRegistrarRejection(\Model_ClientOrder $order, \Model_ServiceDomain $model): void
    {
        $this->di['logger']->error('Domain order #%s (%s) rejected by registrar - status: %s', $order->id, $model->sld . $model->tld, $model->registrar_status);
    }

    /**
     * Daily (by default) sweep that re-checks every active domain's status against its
     * registrar, so lock/privacy/auto-renew/expiry data can never silently drift out of
     * date beyond the configured interval. Also reachable on demand via the admin
     * "Sync All Domains Now" action, which passes $force to bypass the interval check.
     */
    public function batchSyncDomainStatuses(bool $force = false): bool
    {
        $key = 'servicedomain_status_sync_last_run';
        $ss = $this->di['mod_service']('system');

        if (!$force) {
            $lastRun = $ss->getParamValue($key);
            $frequencyHours = (int) ($ss->getParamValue('servicedomain_status_sync_frequency_hours') ?: 24);
            if ($lastRun && (time() - strtotime((string) $lastRun)) < $frequencyHours * 3600) {
                return false;
            }
        }

        $list = $this->di['db']->find('ServiceDomain');

        $failures = 0;
        foreach ($list as $domain) {
            if (!$this->refreshDomainStatus($domain)) {
                ++$failures;
            }
        }

        $ss->setParamValue($key, date('Y-m-d H:i:s'));
        $ss->setParamValue('servicedomain_status_sync_last_failures', $failures);
        $this->di['logger']->info('Executed registrar status sync for all domains (%s failed)', $failures);

        return true;
    }

    public function updateNameservers(\Model_ServiceDomain $model, $data): bool
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

    public function updateContacts(\Model_ServiceDomain $model, $data): bool
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

    public function lock(\Model_ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        if (!$adapter->lock($domain)) {
            throw new \FOSSBilling\Exception('Registrar rejected the request to lock this domain. The local record was not changed.');
        }

        $model->locked = true;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Locking domain #%s', $id);

        return true;
    }

    public function unlock(\Model_ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        if (!$adapter->unlock($domain)) {
            throw new \FOSSBilling\Exception('Registrar rejected the request to unlock this domain. The local record was not changed.');
        }

        $model->locked = false;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Unlocking domain #%s', $id);

        return true;
    }

    public function enablePrivacyProtection(\Model_ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        if (!$adapter->enablePrivacyProtection($domain)) {
            throw new \FOSSBilling\Exception('Registrar rejected the request to enable privacy protection on this domain. The local record was not changed.');
        }

        $model->privacy = true;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Enabled privacy protection of #%s domain', $id);

        return true;
    }

    public function disablePrivacyProtection(\Model_ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        if (!$adapter->disablePrivacyProtection($domain)) {
            throw new \FOSSBilling\Exception('Registrar rejected the request to disable privacy protection on this domain. The local record was not changed.');
        }

        $model->privacy = false;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Disabled privacy protection of #%s domain', $id);

        return true;
    }

    public function enableAutoRenew(\Model_ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        if (!$adapter->enableAutoRenew($domain)) {
            throw new \FOSSBilling\Exception('Registrar rejected the request to enable auto-renew on this domain. The local record was not changed.');
        }

        $model->autorenew = true;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Enabled auto-renew of #%s domain', $id);

        return true;
    }

    public function disableAutoRenew(\Model_ServiceDomain $model): bool
    {
        // @adapterAction
        [$domain, $adapter] = $this->_getD($model);
        if (!$adapter->disableAutoRenew($domain)) {
            throw new \FOSSBilling\Exception('Registrar rejected the request to disable auto-renew on this domain. The local record was not changed.');
        }

        $model->autorenew = false;
        $model->updated_at = date('Y-m-d H:i:s');

        $id = $this->di['db']->store($model);

        $this->di['logger']->info('Disabled auto-renew of #%s domain', $id);

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

    /**
     * Public WHOIS lookup for a transfer-lock status (e.g. clientTransferProhibited) on a
     * domain that isn't in our account yet. This is registry-level data, visible regardless
     * of which registrar currently holds the domain - unlike OpenProvider's own
     * /domains/check, which only reports free/registered and can't see lock state on a
     * domain it doesn't control. Surfacing this before checkout lets the customer unlock the
     * domain at their current registrar first, instead of paying and then hitting a
     * transfer rejection.
     *
     * Returns the matched WHOIS status string if the domain looks locked, or null if it
     * doesn't (including when the lookup itself fails/times out - a failed WHOIS lookup is
     * treated as "unknown", not "locked", since this is a convenience pre-check rather than
     * a hard gate; the registrar's own transfer attempt is still the authority).
     */
    public function getTransferLockState(string $domain): ?string
    {
        try {
            $info = \Iodev\Whois\Factory::get()->createWhois()->loadDomainInfo($domain);
        } catch (\Exception $e) {
            $this->di['logger']->warning('WHOIS lock lookup failed for %s: %s', $domain, $e->getMessage());

            return null;
        }

        foreach ($info->states as $state) {
            if (stripos((string) $state, 'transferprohibited') !== false) {
                return $state;
            }
        }

        return null;
    }

    /**
     * Scan the current nameservers for a domain via DNS, so an admin/client can
     * confirm or edit them before a transfer (rather than silently applying them).
     *
     * @return array<int, string> up to 4 nameserver hostnames, in order
     */
    public function lookupNameservers(string $fqdn): array
    {
        $records = @dns_get_record($fqdn, DNS_NS);
        if (empty($records)) {
            return [];
        }

        $ns = [];
        foreach ($records as $record) {
            if (!empty($record['target'])) {
                $ns[] = rtrim((string) $record['target'], '.');
            }
        }

        return array_slice($ns, 0, 4);
    }

    public function isDomainAvailable(\Model_Tld $model, $sld)
    {
        if (empty($sld)) {
            throw new \FOSSBilling\InformationException('Domain name is invalid');
        }

        $validator = $this->di['validator'];
        if (!$validator->isSldValid($sld)) {
            $safe_dom = htmlspecialchars((string) $sld, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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

    public function syncExpirationDate($model): void
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
            'autorenew' => $model->autorenew,
            'registrar_status' => $model->registrar_status,
            'registered_at' => $model->registered_at,
            'expires_at' => $model->expires_at,
            'synced_at' => $model->synced_at,
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
            $data['registrar'] = $tldRegistrar instanceof \Model_TldRegistrar ? $tldRegistrar->name : null;
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

    protected function _getD(\Model_ServiceDomain $model): array
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
        // Some domain records have a missing or stale client_id on the service_domain
        // row itself (e.g. pointing at a deleted/merged client); fall back to the
        // owning order's client in that case so this doesn't blow up for
        // lock/privacy/autorenew actions.
        $client = $this->di['db']->load('Client', $model->client_id);
        if (!$client instanceof \Model_Client) {
            $client = $this->di['db']->load('Client', $order->client_id ?? null);
        }

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

        $d->setTld($model->tld);
        $d->setSld($model->sld);
        $d->setRegistrationPeriod($model->period);
        $d->setEpp($model->transfer_code);

        if ($model->expires_at) {
            $d->setExpirationTime(strtotime($model->expires_at));
        }

        return [$d, $adapter];
    }

    public static function onBeforeAdminCronRun(\Box_Event $event): bool
    {
        $di = $event->getDi();
        $domainService = $di['mod_service']('servicedomain');

        try {
            $domainService->batchSyncExpirationDates();
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        try {
            $domainService->batchSyncDomainStatuses();
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

    public function tldUpdate(\Model_Tld $model, $data): bool
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

    public function tldAlreadyRegistered($tld): bool
    {
        $tld = $this->di['db']->findOne('Tld', 'tld = :tld ORDER by id ASC', [':tld' => $tld]);

        return $tld instanceof \Model_Tld;
    }

    public function tldRm(\Model_Tld $model): bool
    {
        $id = $model->id;
        $this->di['db']->trash($model);
        $this->di['logger']->info('Deleted top level domain %s', $id);

        return true;
    }

    public function tldToApiArray(\Model_Tld $model, $identity = null): array
    {
        $result = [
            'id' => $model->id,
            'tld' => $model->tld,
            'price_registration' => $model->price_registration,
            'price_renew' => $model->price_renew,
            'price_transfer' => $model->price_transfer,
            'active' => $model->active,
            'allow_register' => $model->allow_register,
            'allow_transfer' => $model->allow_transfer,
            'requires_transfer_code' => $model->requires_transfer_code,
            'min_years' => $model->min_years,
        ];

        if ($identity instanceof \Model_Admin) {
            $tldRegistrar = $this->di['db']->load('TldRegistrar', $model->tld_registrar_id);

            $result['registrar'] = [
                'id' => $model->tld_registrar_id,
                'title' => $tldRegistrar instanceof \Model_TldRegistrar ? $tldRegistrar->name : null,
            ];
        }

        return $result;
    }

    /**
     * @return \Model_Tld|null
     */
    public function tldFindOneByTld($tld)
    {
        $tld = '.' . ltrim((string) $tld, '.');

        return $this->di['db']->findOne('Tld', 'tld = :tld ORDER by id ASC', [':tld' => $tld]);
    }

    public function tldFindOneById($id)
    {
        return $this->di['db']->findOne('Tld', 'id = :id ORDER by id ASC', [':id' => $id]);
    }

    public function registrarGetSearchQuery($data): array
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
        return json_decode($model->config ?? '', true) ?? [];
    }

    public function registrarGetRegistrarAdapterConfig(\Model_TldRegistrar $model)
    {
        $class = $this->registrarGetRegistrarAdapterClassName($model);

        return call_user_func([$class, 'getConfig']);
    }

    private function registrarGetRegistrarAdapterClassName(\Model_TldRegistrar $model): string
    {
        $file = Path::join(PATH_LIBRARY, 'Registrar', 'Adapter', "{$model->registrar}.php");
        if (!$this->filesystem->exists($file)) {
            throw new \FOSSBilling\InformationException('Domain registrar :adapter was not found', [':adapter' => $model->registrar]);
        }

        $class = sprintf('Registrar_Adapter_%s', $model->registrar);
        if (!class_exists($class)) {
            require_once $file;
        }

        if (!class_exists($class)) {
            throw new \FOSSBilling\InformationException('Registrar :adapter was not found', [':adapter' => $class]);
        }

        return $class;
    }

    public function registrarGetRegistrarAdapter(\Model_TldRegistrar $r, ?\Model_ClientOrder $order = null)
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

    public function registrarCreate($code): bool
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

    public function registrarUpdate(\Model_TldRegistrar $model, $data): bool
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

    public function registrarRm(\Model_TldRegistrar $model): bool
    {
        $domains = $this->di['db']->find('ServiceDomain', 'tld_registrar_id = :registrar_id', [':registrar_id' => $model->id]);
        $count = \FOSSBilling\Tools::safeCount($domains);

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Registrar is used by :count: domains', [':count:' => $count], 707);
        }

        $tlds = $this->di['db']->find('Tld', 'tld_registrar_id = :registrar_id', [':registrar_id' => $model->id]);
        $count = \FOSSBilling\Tools::safeCount($tlds);

        if ($count > 0) {
            throw new \FOSSBilling\InformationException('Registrar is used by :count: TLDs', [':count:' => $count], 707);
        }

        $name = $model->name;

        $this->di['db']->trash($model);

        $this->di['logger']->info('Removed domain registrar %s', $name);

        return true;
    }

    public function registrarToApiArray(\Model_TldRegistrar $model): array
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

    public function updateDomain(\Model_ServiceDomain $s, $data): bool
    {
        $s->ns1 = $data['ns1'] ?? $s->ns1;
        $s->ns2 = $data['ns2'] ?? $s->ns2;
        $s->ns3 = $data['ns3'] ?? $s->ns3;
        $s->ns4 = $data['ns4'] ?? $s->ns4;

        $s->period = (int) ($data['period'] ?? $s->period);
        $s->privacy = (bool) ($data['privacy'] ?? $s->privacy);
        $s->locked = (bool) ($data['locked'] ?? $s->locked);
        $s->transfer_code = $data['transfer_code'] ?? $s->transfer_code;
        $s->updated_at = date('Y-m-d H:i:s');

        $this->di['db']->store($s);

        $this->di['logger']->info('Updated domain #%s without sending actions to server', $s->id);

        return true;
    }

    private function formatRegistrarTimestamp(mixed $timestamp): ?string
    {
        if (!is_numeric($timestamp) || (int) $timestamp <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', (int) $timestamp);
    }
}
