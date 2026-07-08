<?php

/** phpcs:ignoreFile */

/**
 * @copyright Devife (https://www.devife.com)
 * @license   MIT
 *
 * This source file is subject to the MIT License that is bundled
 * with this source code in the file LICENSE
 * 
 * Support at support@devife.com
 */

require_once __DIR__ . "/OpenProvider/API.php";

class Registrar_Adapter_OpenProvider extends Registrar_AdapterAbstract
{
    private $config = array(
        'Username'   => null,
        'Password' => null,
        'ApiUrl' => null
    );

    private const MODULE_VERSION = "0.1";
    private const DIR_LOG = "logs";
    private const FILE_LOG = "openprovider.log";

    public function __construct($options)
    {
        if (isset($options['Username']) && !empty($options['Username'])) {
            $this->config['Username'] = $options['Username'];
            unset($options['Username']);
        } else {
            throw new Registrar_Exception('OpenProvider Registrar module error. Please update configuration parameter "Reseller Username" at "Configuration -> Domain registration"', [':domain_registrar' => 'OpenProvider', ':missing' => 'OpenProvider Username'], 3001);
        }

        if (isset($options['Password']) && !empty($options['Password'])) {
            $this->config['Password'] = $options['Password'];
            unset($options['Password']);
        } else {
            throw new Registrar_Exception('OpenProvider Registrar module error. Please update configuration parameter "Reseller Password" at "Configuration -> Domain registration"', [':domain_registrar' => 'OpenProvider', ':missing' => 'OpenProvider Password'], 3001);
        }

        if (isset($options['ApiUrl']) && !empty($options['ApiUrl'])) {
            $this->config['ApiUrl'] = $options['ApiUrl'];
            unset($options['ApiUrl']);
        } else {
            throw new Registrar_Exception('OpenProvider Registrar module error. Please update configuration parameter "API url" at "Configuration -> Domain registration"', [':domain_registrar' => 'OpenProvider', ':missing' => 'OpenProvider API Url'], 3001);
        }
    }
    /**
     * Return array with configuration
     */

    public static function getConfig()
    {
        return array(
            'label'     =>  'OpenProvider registrar',
            'form'  => array(
                'Username' => array(
                    'text',
                    array(
                        'label' => 'Username',
                        'description' => '',
                        'required' => true,
                    ),
                ),
                'Password' => array(
                    'password',
                    array(
                        'label' => 'Password',
                        'description' => '',
                        'required' => true,
                    ),
                ),
                'ApiUrl' => array(
                    'text',
                    array(
                        'label' => 'Api url',
                        'description' => '',
                        'required' => true,
                    ),
                )
            ),
        );
    }

    public function getTlds(): array
    {
        return [];
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        // Step 1: Ensure a customer handle exists
        $customerHandle = $this->_getOrCreateCustomer($domain->getContactAdmin());

        // Step 2: Prepare the domain registration data
        $data = [
            'domain' => [
                'name' => $domain->getSld(),
                'extension' => $this->_stripTld($domain),
            ],
            'period' => $domain->getRegistrationPeriod(),
            'owner_handle' => $customerHandle,
            'admin_handle' => $customerHandle,
            'tech_handle' => $customerHandle,
            'billing_handle' => $customerHandle,
            'autorenew' => 'default'
        ];

        // Use the nameservers submitted with the order, if any; otherwise fall back to OpenProvider's default DNS.
        $customNs = $this->_getCustomNameservers($domain);
        if (!empty($customNs)) {
            $data['name_servers'] = $customNs;
        } else {
            $data['ns_group'] = 'dns-openprovider';
        }

        try {
            $response = $this->_request('POST', '/domains', $data);
            if ($response['code'] === 0) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            throw new Registrar_Exception('OpenProvider API Error: ' . $e->getMessage());
        }
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $data = [
            'domains' => [
                [
                    'name' => $domain->getSld(),
                    'extension' => $this->_stripTld($domain),
                ],
            ],
        ];

        try {
            $response = $this->_request('POST', '/domains/check', $data);
            if (!empty($response['data']['results']) && $response['data']['results'][0]['status'] === 'free') {
                return true;
            }
            return false;
        } catch (Exception $e) {
            throw new Registrar_Exception('OpenProvider API Error: ' . $e->getMessage());
        }
    }

    public function isDomainCanBeTransferred(Registrar_Domain $domain)
    {
        $data = [
            'domains' => [
                [
                    'name' => $domain->getSld(),
                    'extension' => $this->_stripTld($domain),
                ],
            ],
        ];

        $response = $this->_request('POST', '/domains/check', $data);
        $result = $response['data']['results'][0] ?? [];
        return isset($result['status']) && $result['status'] === 'active';
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        // Step 1: Ensure a customer handle exists
        $customerHandle = $this->_getOrCreateCustomer($domain->getContactAdmin());

        // Step 2: Use the nameservers submitted with the order (typically pre-filled by a DNS
        // scan at checkout/order time); fall back to a live DNS lookup if none were submitted.
        $existingNs = $this->_getCustomNameservers($domain);
        if (empty($existingNs)) {
            $existingNs = $this->_lookupNameservers($domain->getName());
        }

        // Step 3: Prepare the domain transfer data
        $data = [
            'domain' => [
                'name' => $domain->getSld(),
                'extension' => $this->_stripTld($domain),
            ],
            'period' => $domain->getRegistrationPeriod(),
            'owner_handle' => $customerHandle,
            'admin_handle' => $customerHandle,
            'tech_handle' => $customerHandle,
            'billing_handle' => $customerHandle,
            'autorenew' => 'default',
            'auth_code' => $domain->getEpp(),
        ];

        if (!empty($existingNs)) {
            $data['name_servers'] = $existingNs;
        } else {
            $data['ns_group'] = 'dns-openprovider';
        }

        $response = $this->_request('POST', '/domains/transfer', $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    /**
     * Read the nameservers submitted with the order (ns1-ns4 on the domain).
     * Returns an array of ['name' => 'ns1.example.com'] entries, or empty array if none were set.
     */
    private function _getCustomNameservers(Registrar_Domain $domain): array
    {
        $ns = [];
        foreach ([$domain->getNs1(), $domain->getNs2(), $domain->getNs3(), $domain->getNs4()] as $hostname) {
            if (!empty($hostname)) {
                $ns[] = ['name' => $hostname];
            }
        }

        return $ns;
    }

    /**
     * Look up the current nameservers for a domain via DNS.
     * Returns an array of ['name' => 'ns1.example.com'] entries, or empty array on failure.
     */
    private function _lookupNameservers(string $fqdn): array
    {
        $records = @dns_get_record($fqdn, DNS_NS);
        if (empty($records)) {
            return [];
        }

        $ns = [];
        foreach ($records as $record) {
            if (!empty($record['target'])) {
                $ns[] = ['name' => rtrim($record['target'], '.')];
            }
        }

        return $ns;
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $data = [
            'domain' => [
                'name' => $domain->getSld(),
                'extension' => $this->_stripTld($domain),
            ],
            'period' => $domain->getRegistrationPeriod(),
        ];

        $response = $this->_request('POST', "/domains/{$domainId}/renew", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $data = [
            'skip_soft_quarantine' => false,
            'force_delete' => false,
            'type' => 'By user'
        ];

        $response = $this->_request('DELETE', "/domains/{$domainId}", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $response = $this->_request('GET', "/domains/{$domainId}/authcode");
        return $response['data']['auth_code'] ?? null;
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $response = $this->_request('GET', "/domains/{$domainId}");
        $opDomain = $response['data'];

        $domain->setRegistrationTime(strtotime($opDomain['creation_date']));
        $domain->setExpirationTime(strtotime($opDomain['expiration_date']));
        $domain->setPrivacyEnabled($opDomain['is_private_whois_enabled']);
        $domain->setLocked($opDomain['is_locked']);
        $domain->setStatus($opDomain['status'] ?? null);
        // OpenProvider's GET /domains response can report 'default' instead of a resolved
        // on/off value for domains that were registered/transferred with autorenew=default
        // (i.e. "follow the account-wide setting"). There is no documented API endpoint to
        // read that account-wide default - even OpenProvider's own WHMCS module doesn't
        // attempt it - so we hardcode the known current account setting here. If the
        // account-wide default is ever changed in the OpenProvider control panel
        // (Account > Settings > Auto-renew), this must be updated to match.
        if (isset($opDomain['autorenew'])) {
            $domain->setAutoRenew(in_array($opDomain['autorenew'], ['on', 'default'], true));
        }

        $nameservers = $opDomain['name_servers'];
        if (isset($nameservers[0])) {
            $domain->setNs1($nameservers[0]['name']);
        }
        if (isset($nameservers[1])) {
            $domain->setNs2($nameservers[1]['name']);
        }
        if (isset($nameservers[2])) {
            $domain->setNs3($nameservers[2]['name']);
        }
        if (isset($nameservers[3])) {
            $domain->setNs4($nameservers[3]['name']);
        }

        $registrarContact = new Registrar_Domain_Contact();
        $adminContact = new Registrar_Domain_Contact();
        $techContact = new Registrar_Domain_Contact();
        $billingContact = new Registrar_Domain_Contact();

        // Get customer info from the api
        $customer = $this->_getCustomer($opDomain['admin_handle']);

        // Set contact data on our Domain obj using info from our API call
        foreach (['Registrant', 'Admin', 'Tech', 'Billing'] as $contactType) {
            $contact = $registrarContact;

            if ($contactType == 'Admin') {
                $contact = $adminContact;
            }
            if ($contactType == 'Tech') {
                $contact = $techContact;
            }
            if ($contactType == 'Billing') {
                $contact = $billingContact;
            }

            $contact->setFirstName($customer['name']['first_name']);
            $contact->setLastName($customer['name']['last_name']);
            $contact->setEmail($customer['email']);
            $contact->setTelCc($customer['phone']['country_code']);
            $contact->setTel($customer['phone']['subscriber_number']);
            $contact->setAddress1($customer['address']['street']);
            $contact->setCity($customer['address']['city']);
            $contact->setState($customer['address']['state']);
            $contact->setCountry($customer['address']['country']);
            $contact->setZip($customer['address']['zipcode']);
            $contact->setCompany(isset($customer['company_name']) ? $customer['company_name'] : '');
        }

        $domain->setContactRegistrar($registrarContact);
        $domain->setContactAdmin($adminContact);
        $domain->setContactTech($techContact);
        $domain->setContactBilling($billingContact);

        return $domain;
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        // Step 1: Fetch the OpenProvider domain ID
        $domainId = $this->_getDomainId($domain);

        $ns = [];
        $ns[] = ["name" => $domain->getNs1()];
        $ns[] = ["name" => $domain->getNs2()];
        if ($domain->getNs3()) {
            $ns[] = ["name" => $domain->getNs3()];
        }
        if ($domain->getNs4()) {
            $ns[] = ["name" => $domain->getNs4()];
        }

        // Step 2: Prepare the request data
        $data = [
            'name_servers' => $ns,
        ];

        // Step 3: Send the PUT request to update nameservers
        $response = $this->_request('PUT', "/domains/{$domainId}", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        // Step 1: Fetch the OpenProvider domain ID
        $domainId = $this->_getDomainId($domain);

        // Step 2: Get or create the customer handle
        $customerHandle = $this->_getOrCreateCustomer($domain->getContactAdmin(), true);

        // Step 3: Prepare the request data
        $data = [
            'owner_handle' => $customerHandle,
            'admin_handle' => $customerHandle,
            'tech_handle' => $customerHandle,
            'billing_handle' => $customerHandle,
        ];

        // Step 4: Send the PUT request to update contact
        $response = $this->_request('PUT', "/domains/{$domainId}", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    public function lock(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $data = [
            'is_locked' => true,
        ];

        $response = $this->_request('PUT', "/domains/{$domainId}", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    public function unlock(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $data = [
            'is_locked' => false,
        ];

        $response = $this->_request('PUT', "/domains/{$domainId}", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $data = [
            'is_private_whois_enabled' => true,
        ];


        $response = $this->_request('PUT', "/domains/{$domainId}", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $data = [
            'is_private_whois_enabled' => false,
        ];

        $response = $this->_request('PUT', "/domains/{$domainId}", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    public function enableAutoRenew(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $data = [
            'autorenew' => 'on',
        ];

        $response = $this->_request('PUT', "/domains/{$domainId}", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    public function disableAutoRenew(Registrar_Domain $domain)
    {
        $domainId = $this->_getDomainId($domain);

        $data = [
            'autorenew' => 'off',
        ];

        $response = $this->_request('PUT', "/domains/{$domainId}", $data);
        if ($response['code'] === 0) {
            return true;
        }

        return false;
    }

    private function _stripTld(Registrar_Domain $domain)
    {
        $tld = $domain->getTld();
        return preg_replace("/^\.+|\.+$/", "", $tld);
    }

    private function _getDomainId(Registrar_Domain $domain)
    {
        $data = [
            'full_name' => $domain->getName(),
        ];

        try {
            $response = $this->_request('GET', '/domains', $data);
            if (!empty($response['data']['results']) && count($response['data']['results']) > 0) {
                return $response['data']['results'][0]['id']; // Return the OpenProvider domain ID
            }
            throw new Registrar_Exception('Domain not found in OpenProvider: ' . $domain->getName());
        } catch (Exception $e) {
            throw new Registrar_Exception('Failed to fetch domain ID: ' . $e->getMessage());
        }
    }

    private function _getOrCreateCustomer(Registrar_Domain_Contact $contact, $updateExisting = false)
    {
        $data = [
            'email' => $contact->getEmail(),
            'phone' => [
                'country_code' => $contact->getTelCc(),
                'area_code' => "6",
                'subscriber_number' => $contact->getTel()
            ],
            'company_name' => $contact->getCompany() ?? '',
            'address' => [
                'street' => $contact->getAddress1(),
                'zipcode' => $contact->getZip(),
                'city' => $contact->getCity(),
                'state' => $contact->getState(),
                'country' => $contact->getCountry(),
            ],
            'name' => [
                'first_name' => $contact->getFirstName(),
                'last_name' => $contact->getLastName(),
            ]
        ];

        // Step 1: Check if the customer already exists by email
        $existingCustomerHandle = $this->_findCustomerByEmail($contact->getEmail());
        if ($existingCustomerHandle) {
            if ($updateExisting) {
                $response = $this->_request('PUT', "/customers/{$existingCustomerHandle}", $data);
                if ($response['code'] !== 0) {
                    throw new Registrar_Exception('Failed to update contact: ' . $response['msg']);
                }
            }

            return $existingCustomerHandle;
        }

        // Step 2: Create a new customer if not found
        try {
            $response = $this->_request('POST', '/customers', $data);
            if (isset($response['data']['handle'])) {
                return $response['data']['handle'];
            }
            throw new Registrar_Exception('Failed to create customer: ' . $response['msg']);
        } catch (Exception $e) {
            throw new Registrar_Exception('OpenProvider API Error: ' . $e->getMessage());
        }
    }

    private function _findCustomerByEmail($email)
    {
        $data = [
            'email_pattern' => $email, // Search by email pattern
        ];

        try {
            $response = $this->_request('GET', '/customers', $data);
            if (!empty($response['data']['results']) && count($response['data']['results']) > 0) {
                return $response['data']['results'][0]['handle']; // Return the customer handle
            }
            return null; // No matching customer found
        } catch (Exception $e) {
            throw new Registrar_Exception('Failed to find customer by email: ' . $e->getMessage());
        }
    }


    private function _getCustomer($handle)
    {
        try {
            $response = $this->_request('GET', "/customers/{$handle}");
            if ($response['code'] === 0 && !empty($response['data'])) {
                return $response['data']; // Return the customer data
            }
            return null; // No matching customer found
        } catch (Exception $e) {
            throw new Registrar_Exception('Failed to find customer by email: ' . $e->getMessage());
        }
    }

    /**
     * Send OpenProvider request
     */
    private function _request($method, $url, $data = []): array
    {
        try {
            $username   = $this->config['Username'];
            $password   = $this->config['Password'];
            $apiUrl     = $this->config['ApiUrl'];

            $op     = new OpenProvider_API();
            $op->setApi_login($username, $password, $apiUrl);

            $response = $op->request($method, $url, $data);
            $this->_logResponse($method, $url, $data, $response);

            return $response;
        } catch (Exception $e) {
            $this->_logError($method, $url, $data, $e->getMessage());
            throw $e;
        }
    }

    private function _logResponse($method, $url, $data, $response)
    {
        file_put_contents(__DIR__ . '/' . self::DIR_LOG . '/' . self::FILE_LOG, json_encode([
            'method' => $method,
            'url' => $url,
            'data' => $data,
            'response' => $response,
        ], JSON_PRETTY_PRINT), FILE_APPEND);
    }

    private function _logError($method, $url, $data, $error)
    {
        file_put_contents(__DIR__ . '/' . self::DIR_LOG . '/' . self::FILE_LOG, json_encode([
            'method' => $method,
            'url' => $url,
            'data' => $data,
            'error' => $error,
        ], JSON_PRETTY_PRINT), FILE_APPEND);
    }
}
