<?php
/**
 * Copyright 2022-2025 FOSSBilling
 * Copyright 2025 AXYNUK
 * SPDX-License-Identifier: Apache-2.0
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @copyright AXYNUK (https://www.axyn.co.uk)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

class Registrar_Adapter_Opensrs extends Registrar_AdapterAbstract
{
    protected array $config = [];

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is required for OpenSRS registrar adapter');
        }

        if (!extension_loaded('simplexml')) {
            throw new Registrar_Exception('SimpleXML extension is required for OpenSRS registrar adapter');
        }

        if (isset($options['username']) && !empty($options['username'])) {
            $this->config['username'] = $options['username'];
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'OpenSRS', ':missing' => 'OpenSRS Username'], 4001);
        }

        if (isset($options['api_key']) && !empty($options['api_key'])) {
            $this->config['api_key'] = $options['api_key'];
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'OpenSRS', ':missing' => 'API Key'], 4001);
        }
    }

    public static function getConfig(): array
    {
        return [
            'label' => 'Manages domains on OpenSRS via XML API. OpenSRS is a domain registrar and reseller platform supporting 1000+ TLDs.',
            'form' => [
                'username' => [
                    'text',
                    [
                        'label' => 'OpenSRS Username',
                        'description' => 'Your OpenSRS reseller username',
                        'required' => true,
                    ],
                ],
                'api_key' => [
                    'password',
                    [
                        'label' => 'API Key',
                        'description' => 'Your OpenSRS API key from Account Settings',
                        'required' => true,
                        'renderPassword' => true,
                    ],
                ],
            ],
        ];
    }

    public function isDomainAvailable(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Checking availability for: ' . $domain->getName());

        $params = [
            'protocol' => 'XCP',
            'action' => 'LOOKUP',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
            ],
        ];

        $result = $this->_makeRequest($params);

        if (!isset($result['attributes']['status'])) {
            throw new Registrar_Exception('Invalid response from OpenSRS API');
        }

        // Status: available, taken, or invalid
        return strtolower((string) $result['attributes']['status']) === 'available';
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Checking transferability for: ' . $domain->getName());

        // Check if domain is registered (not available)
        if ($this->isDomainAvailable($domain)) {
            return false;
        }

        // Additional check via transfer check
        $params = [
            'protocol' => 'XCP',
            'action' => 'CHECK_TRANSFER',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'req_from' => 'owner',
            ],
        ];

        try {
            $result = $this->_makeRequest($params);
            
            if (isset($result['attributes']['transferrable'])) {
                return (int)$result['attributes']['transferrable'] === 1;
            }

            return true; // Assume transferrable if status unknown
        } catch (Registrar_Exception $e) {
            $this->getLog()->err('Transfer check failed: ' . $e->getMessage());
            return true; // Assume can be transferred
        }
    }

    public function modifyNs(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Modifying nameservers for: ' . $domain->getName());

        $nameservers = [];
        if ($domain->getNs1()) {
            $nameservers[] = ['name' => $domain->getNs1()];
        }
        if ($domain->getNs2()) {
            $nameservers[] = ['name' => $domain->getNs2()];
        }
        if ($domain->getNs3()) {
            $nameservers[] = ['name' => $domain->getNs3()];
        }
        if ($domain->getNs4()) {
            $nameservers[] = ['name' => $domain->getNs4()];
        }

        if (empty($nameservers)) {
            throw new Registrar_Exception('At least one nameserver is required');
        }

        $params = [
            'protocol' => 'XCP',
            'action' => 'MODIFY',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'affect_domains' => 0,
                'data' => 'nameserver_list',
                'nameserver_list' => $nameservers,
            ],
        ];

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    public function modifyContact(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Modifying contacts for: ' . $domain->getName());

        $c = $domain->getContactRegistrar();

        $contactSet = $this->_buildContactSet($c);

        $params = [
            'protocol' => 'XCP',
            'action' => 'UPDATE_CONTACTS',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'contact_set' => $contactSet,
                'types' => ['owner', 'admin', 'billing', 'tech'],
            ],
        ];

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    public function transferDomain(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Transferring domain: ' . $domain->getName());

        $c = $domain->getContactRegistrar();
        $contactSet = $this->_buildContactSet($c);

        $params = [
            'protocol' => 'XCP',
            'action' => 'SW_REGISTER',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'custom_tech_contact' => 1,
                'custom_nameservers' => 1,
                'f_lock_domain' => 1,
                'reg_type' => 'transfer',
                'auth_info' => $domain->getEpp(),
                'contact_set' => $contactSet,
            ],
        ];

        // Add nameservers if provided
        $nameservers = [];
        if ($domain->getNs1()) {
            $nameservers[] = ['name' => $domain->getNs1()];
        }
        if ($domain->getNs2()) {
            $nameservers[] = ['name' => $domain->getNs2()];
        }

        if (!empty($nameservers)) {
            $params['attributes']['nameserver_list'] = $nameservers;
        }

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    public function getDomainDetails(Registrar_Domain $domain): Registrar_Domain
    {
        $this->getLog()->info('Getting domain details for: ' . $domain->getName());

        $params = [
            'protocol' => 'XCP',
            'action' => 'GET',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'type' => 'all_info',
            ],
        ];

        $result = $this->_makeRequest($params);

        if (!isset($result['attributes'])) {
            throw new Registrar_Exception('Could not retrieve domain details from OpenSRS');
        }

        $attrs = $result['attributes'];

        // Set domain details
        if (isset($attrs['registry_createdate'])) {
            $domain->setRegistrationTime(strtotime((string) $attrs['registry_createdate']));
        }

        if (isset($attrs['registry_expiredate'])) {
            $domain->setExpirationTime(strtotime((string) $attrs['registry_expiredate']));
        }

        // Set nameservers
        if (isset($attrs['nameserver_list']) && is_array($attrs['nameserver_list'])) {
            $i = 1;
            foreach ($attrs['nameserver_list'] as $ns) {
                $nsName = is_array($ns) && isset($ns['name']) ? $ns['name'] : $ns;
                $method = 'setNs' . $i;
                if (method_exists($domain, $method) && $i <= 4) {
                    $domain->$method($nsName);
                    $i++;
                }
            }
        }

        // Set locked status
        if (isset($attrs['f_lock_domain'])) {
            $domain->setLocked((int)$attrs['f_lock_domain'] === 1);
        }

        return $domain;
    }

    public function getEpp(Registrar_Domain $domain): string
    {
        $this->getLog()->info('Getting EPP code for: ' . $domain->getName());

        $params = [
            'protocol' => 'XCP',
            'action' => 'SEND_AUTHCODE',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain_name' => $domain->getName(),
            ],
        ];

        $result = $this->_makeRequest($params);

        if (isset($result['attributes']['auth_code'])) {
            return (string) $result['attributes']['auth_code'];
        }

        throw new Registrar_Exception('Could not retrieve EPP code from OpenSRS');
    }

    public function registerDomain(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Registering domain: ' . $domain->getName());

        $c = $domain->getContactRegistrar();
        $contactSet = $this->_buildContactSet($c);

        $params = [
            'protocol' => 'XCP',
            'action' => 'SW_REGISTER',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'custom_tech_contact' => 1,
                'custom_nameservers' => 1,
                'f_lock_domain' => 1,
                'reg_username' => $this->config['username'],
                'reg_password' => $this->_generatePassword(),
                'reg_type' => 'new',
                'period' => $domain->getRegistrationPeriod(),
                'contact_set' => $contactSet,
            ],
        ];

        // Add nameservers
        $nameservers = [];
        if ($domain->getNs1()) {
            $nameservers[] = ['name' => $domain->getNs1()];
        }
        if ($domain->getNs2()) {
            $nameservers[] = ['name' => $domain->getNs2()];
        }
        if ($domain->getNs3()) {
            $nameservers[] = ['name' => $domain->getNs3()];
        }
        if ($domain->getNs4()) {
            $nameservers[] = ['name' => $domain->getNs4()];
        }

        if (!empty($nameservers)) {
            $params['attributes']['nameserver_list'] = $nameservers;
        }

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    public function renewDomain(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Renewing domain: ' . $domain->getName());

        $params = [
            'protocol' => 'XCP',
            'action' => 'RENEW',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'period' => $domain->getRegistrationPeriod(),
                'currentexpirationyear' => date('Y', $domain->getExpirationTime()),
            ],
        ];

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    public function deleteDomain(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Deleting/Revoking domain: ' . $domain->getName());

        $params = [
            'protocol' => 'XCP',
            'action' => 'REVOKE',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
            ],
        ];

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    public function enablePrivacyProtection(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Enabling privacy protection for: ' . $domain->getName());

        $params = [
            'protocol' => 'XCP',
            'action' => 'MODIFY',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'affect_domains' => 0,
                'data' => 'whois_privacy_state',
                'state' => 'enabled',
            ],
        ];

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    public function disablePrivacyProtection(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Disabling privacy protection for: ' . $domain->getName());

        $params = [
            'protocol' => 'XCP',
            'action' => 'MODIFY',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'affect_domains' => 0,
                'data' => 'whois_privacy_state',
                'state' => 'disabled',
            ],
        ];

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    public function lock(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Locking domain: ' . $domain->getName());

        $params = [
            'protocol' => 'XCP',
            'action' => 'MODIFY',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'affect_domains' => 0,
                'data' => 'f_lock_domain',
                'f_lock_domain' => 1,
            ],
        ];

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    public function unlock(Registrar_Domain $domain): bool
    {
        $this->getLog()->info('Unlocking domain: ' . $domain->getName());

        $params = [
            'protocol' => 'XCP',
            'action' => 'MODIFY',
            'object' => 'DOMAIN',
            'attributes' => [
                'domain' => $domain->getName(),
                'affect_domains' => 0,
                'data' => 'f_lock_domain',
                'f_lock_domain' => 0,
            ],
        ];

        $result = $this->_makeRequest($params);

        return isset($result['is_success']) && (int)$result['is_success'] === 1;
    }

    /**
     * Build OpenSRS contact set from Registrar_Domain_Contact
     */
    private function _buildContactSet(Registrar_Domain_Contact $c): array
    {
        $phone = $c->getTel();
        $phoneFormatted = preg_replace('/[^0-9]/', '', (string) $phone);
        $telCc = $c->getTelCc() ?: '1';
        
        // OpenSRS expects phone format: +CC.PHONENUMBER
        $fullPhone = '+' . $telCc . '.' . $phoneFormatted;

        $contact = [
            'first_name' => $c->getFirstName(),
            'last_name' => $c->getLastName(),
            'org_name' => $c->getCompany() ?: $c->getFirstName() . ' ' . $c->getLastName(),
            'address1' => $c->getAddress1(),
            'city' => $c->getCity(),
            'state' => $c->getState(),
            'postal_code' => $c->getZip(),
            'country' => $c->getCountry(),
            'phone' => $fullPhone,
            'email' => $c->getEmail(),
        ];

        if ($c->getAddress2()) {
            $contact['address2'] = $c->getAddress2();
        }

        // Return contact set with all contact types
        return [
            'owner' => $contact,
            'admin' => $contact,
            'billing' => $contact,
            'tech' => $contact,
        ];
    }

    /**
     * Make authenticated request to OpenSRS API
     */
    private function _makeRequest(array $params): array
    {
        // Build XML request
        $xml = $this->_buildXML($params);

        // Calculate MD5 signature
        $signature = $this->_calculateSignature($xml);

        // Determine API endpoint
        $apiUrl = $this->isTestEnv() 
            ? 'https://horizon.opensrs.net:55443'
            : 'https://rr-n1-tor.opensrs.net:55443';

        $this->getLog()->debug('OpenSRS API Request to: ' . $apiUrl);
        $this->getLog()->debug('XML Request: ' . $xml);

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$this->isTestEnv());
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, !$this->isTestEnv() ? 2 : 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml',
            'X-Username: ' . $this->config['username'],
            'X-Signature: ' . $signature,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            $this->getLog()->err('OpenSRS API Error: HTTP ' . $httpCode . ' - ' . $curlError);
            throw new Registrar_Exception('OpenSRS API request failed: ' . ($curlError ?: 'HTTP ' . $httpCode));
        }

        $this->getLog()->debug('OpenSRS API Response: ' . $response);

        // Parse XML response
        $result = $this->_parseXML($response);

        // Check for API errors
        if (!isset($result['is_success']) || (int)$result['is_success'] !== 1) {
            $errorMsg = 'OpenSRS API Error';
            if (isset($result['response_text'])) {
                $errorMsg .= ': ' . $result['response_text'];
            } elseif (isset($result['response_code'])) {
                $errorMsg .= ' (Code: ' . $result['response_code'] . ')';
            }
            $this->getLog()->err($errorMsg);
            throw new Registrar_Exception($errorMsg);
        }

        return $result;
    }

    /**
     * Calculate OpenSRS MD5 signature
     * Uses double MD5 hash: MD5(MD5(xml + key) + key)
     */
    private function _calculateSignature(string $xml): string
    {
        $apiKey = $this->config['api_key'];
        
        // First MD5 hash
        $hash1 = md5($xml . $apiKey);
        
        // Second MD5 hash
        $signature = md5($hash1 . $apiKey);
        
        return $signature;
    }

    /**
     * Build OpenSRS XML request
     */
    private function _buildXML(array $params): string
    {
        $xml = '<?xml version=\'1.0\' encoding=\'UTF-8\' standalone=\'no\' ?>' . "\n";
        $xml .= '<!DOCTYPE OPS_envelope SYSTEM \'ops.dtd\'>' . "\n";
        $xml .= '<OPS_envelope>' . "\n";
        $xml .= '<header>' . "\n";
        $xml .= '    <version>0.9</version>' . "\n";
        $xml .= '</header>' . "\n";
        $xml .= '<body>' . "\n";
        $xml .= '<data_block>' . "\n";
        $xml .= $this->_arrayToXML($params);
        $xml .= '</data_block>' . "\n";
        $xml .= '</body>' . "\n";
        $xml .= '</OPS_envelope>';

        return $xml;
    }

    /**
     * Convert array to OpenSRS XML format
     */
    private function _arrayToXML(array $data, int $level = 1): string
    {
        $indent = str_repeat('    ', $level);
        $xml = $indent . '<dt_assoc>' . "\n";

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Check if it's an indexed array (dt_array) or associative (dt_assoc)
                if (array_keys($value) === range(0, count($value) - 1)) {
                    // Indexed array
                    $xml .= $indent . '    <item key="' . htmlspecialchars((string) $key) . '">' . "\n";
                    $xml .= $indent . '        <dt_array>' . "\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $xml .= $this->_arrayToXML($item, $level + 3);
                        } else {
                            $xml .= $indent . '            <item>' . htmlspecialchars((string) $item) . '</item>' . "\n";
                        }
                    }
                    $xml .= $indent . '        </dt_array>' . "\n";
                    $xml .= $indent . '    </item>' . "\n";
                } else {
                    // Associative array
                    $xml .= $indent . '    <item key="' . htmlspecialchars((string) $key) . '">' . "\n";
                    $xml .= $this->_arrayToXML($value, $level + 2);
                    $xml .= $indent . '    </item>' . "\n";
                }
            } else {
                // Scalar value
                $xml .= $indent . '    <item key="' . htmlspecialchars((string) $key) . '">' . htmlspecialchars((string) $value) . '</item>' . "\n";
            }
        }

        $xml .= $indent . '</dt_assoc>' . "\n";

        return $xml;
    }

    /**
     * Parse OpenSRS XML response
     */
    private function _parseXML(string $xmlString): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlString);

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $errorMsg = 'Failed to parse XML response';
            if (!empty($errors)) {
                $errorMsg .= ': ' . $errors[0]->message;
            }
            throw new Registrar_Exception($errorMsg);
        }

        $result = [];

        // Parse body data_block
        if (isset($xml->body->data_block->dt_assoc)) {
            $result = $this->_xmlToArray($xml->body->data_block->dt_assoc);
        }

        return $result;
    }

    /**
     * Convert OpenSRS XML to array
     */
    private function _xmlToArray(\SimpleXMLElement $xml): array
    {
        $result = [];

        foreach ($xml->item as $item) {
            $key = (string) $item['key'];
            
            if (isset($item->dt_assoc)) {
                // Nested associative array
                $result[$key] = $this->_xmlToArray($item->dt_assoc);
            } elseif (isset($item->dt_array)) {
                // Indexed array
                $result[$key] = [];
                foreach ($item->dt_array->item as $arrayItem) {
                    if (isset($arrayItem->dt_assoc)) {
                        $result[$key][] = $this->_xmlToArray($arrayItem->dt_assoc);
                    } else {
                        $result[$key][] = (string) $arrayItem;
                    }
                }
            } else {
                // Scalar value
                $result[$key] = (string) $item;
            }
        }

        return $result;
    }

    /**
     * Generate random password for domain registration
     */
    private function _generatePassword(int $length = 16): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        
        return $password;
    }

    /**
     * Check if we're in test mode
     */
    private function isTestEnv(): bool
    {
        return $this->_testMode;
    }
}
