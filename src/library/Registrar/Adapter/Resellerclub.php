<?php
/**
 * Copyright 2022-2024 FOSSBilling
 * Copyright 2011-2021 BoxBilling, Inc.
 * SPDX-License-Identifier: Apache-2.0.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache-2.0
 */

/*
 * HTTP API documentation http://cp.onlyfordemo.net/kb/answer/744
 */

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class Registrar_Adapter_Resellerclub extends Registrar_AdapterAbstract
{
    public $config = [
        'userid' => null,
        'password' => null,
        'api-key' => null,
    ];

    public function isKeyValueNotEmpty($array, $key)
    {
        $value = $array[$key] ?? '';
        if (strlen(trim($value)) == 0) {
            return false;
        }

        return true;
    }

    public function __construct($options)
    {
        if (isset($options['userid']) && !empty($options['userid'])) {
            $this->config['userid'] = $options['userid'];
            unset($options['userid']);
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'ResellerClub', ':missing' => 'ResellerClub Reseller ID'], 3001);
        }

        if (isset($options['api-key']) && !empty($options['api-key'])) {
            $this->config['api-key'] = $options['api-key'];
            unset($options['api-key']);
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'ResellerClub', ':missing' => 'ResellerClub API Key'], 3001);
        }
    }

    public static function getConfig()
    {
        return [
            'label' => 'Manages domains on ResellerClub via API. ResellerClub requires your server IP in order to work. Login to the ResellerClub control panel (the url will be in the email you received when you signed up with them) and then go to Settings > API and enter the IP address of the server where FOSSBilling is installed to authorize it for API access.',
            'form' => [
                'userid' => [
                    'text',
                    [
                        'label' => 'Reseller ID. You can get this at ResellerClub control panel Settings > Personal information > Primary profile > Reseller ID',
                        'description' => 'ResellerClub Reseller ID',
                    ],
                ],
                'api-key' => [
                    'password',
                    [
                        'label' => 'ResellerClub API Key',
                        'description' => 'You can get this at ResellerClub control panel, go to Settings -> API',
                        'required' => false,
                    ],
                ],
            ],
        ];
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = [
            'domain-name' => $domain->getSld(),
            'tlds' => [$domain->getTld(false)],
            'suggest-alternative' => false,
        ];

        $result = $this->_makeRequest('domains/available', $params);
        if (!isset($result[$domain->getName()])) {
            return true;
        }

        $check = $result[$domain->getName()];
        if ($check && $check['status'] == 'available') {
            return true;
        }

        return false;
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        $params = [
            'domain-name' => $domain->getName(),
        ];
        $result = $this->_makeRequest('domains/validate-transfer', $params, 'GET');

        return strtolower($result) == 'true';
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $ns = [];
        $ns[] = $domain->getNs1();
        $ns[] = $domain->getNs2();
        if ($domain->getNs3()) {
            $ns[] = $domain->getNs3();
        }
        if ($domain->getNs4()) {
            $ns[] = $domain->getNs4();
        }

        $params = [
            'order-id' => $this->_getDomainOrderId($domain),
            'ns' => $ns,
        ];

        $result = $this->_makeRequest('domains/modify-ns', $params, 'POST');

        return $result['status'] == 'Success';
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $customer = $this->_getCustomerDetails($domain);
        $cdetails = $this->_getDefaultContactDetails($domain, $customer['customerid']);
        $contact_id = $cdetails['Contact']['registrant'];

        $c = $domain->getContactRegistrar();

        $required_params = [
            'contact-id' => $contact_id,
            'name' => $c->getName(),
            'company' => $c->getCompany(),
            'email' => $c->getEmail(),
            'address-line-1' => $c->getAddress1(),
            'city' => $c->getCity(),
            'zipcode' => $c->getZip(),
            'phone-cc' => $c->getTelCc(),
            'phone' => $c->getTel(),
            'country' => $c->getCountry(),
        ];

        $optional_params = [
            'address-line-2' => $c->getAddress2(),
            'address-line-3' => $c->getAddress3(),
            'state' => $c->getState(),
        ];

        $params = [...$optional_params, ...$required_params];
        $result = $this->_makeRequest('contacts/modify', $params, 'POST');

        return $result['status'] == 'Success';
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $customer = $this->_getCustomerDetails($domain);
        $contacts = $this->_getDefaultContactDetails($domain, $customer['customerid']);
        $contact_id = $contacts['Contact']['registrant'];

        $ns = [];
        $ns[] = $domain->getNs1();
        $ns[] = $domain->getNs2();
        if ($domain->getNs3()) {
            $ns[] = $domain->getNs3();
        }
        if ($domain->getNs4()) {
            $ns[] = $domain->getNs4();
        }

        $required_params = [
            'domain-name' => $domain->getName(),
            'auth-code' => $domain->getEpp(),
            'ns' => $ns,
            'customer-id' => $customer['customerid'],
            'reg-contact-id' => $contact_id,
            'admin-contact-id' => $contact_id,
            'tech-contact-id' => $contact_id,
            'billing-contact-id' => $contact_id,
            'invoice-option' => 'NoInvoice',
            'protect-privacy' => false,
        ];

        if ($domain->getTld() == '.asia') {
            $required_params['attr-name1'] = 'cedcontactid';
            $required_params['attr-value1'] = 'default';
        }

        $result = $this->_makeRequest('domains/transfer', $required_params, 'POST');

        return $result['status'] == 'Success';
    }

    private function _getDomainOrderId(Registrar_Domain $d)
    {
        $required_params = [
            'domain-name' => $d->getName(),
        ];

        return $this->_makeRequest('domains/orderid', $required_params);
    }

    public function getDomainDetails(Registrar_Domain $d)
    {
        $orderid = $this->_getDomainOrderId($d);
        $params = [
            'order-id' => $orderid,
            'options' => 'All',
        ];
        $data = $this->_makeRequest('domains/details', $params);

        $d->setRegistrationTime($data['creationtime']);
        $d->setExpirationTime($data['endtime']);
        $d->setEpp($data['domsecret']);
        $d->setPrivacyEnabled($data['isprivacyprotected'] == 'true');

        /* Contact details */
        $wc = $data['admincontact'];
        $c = new Registrar_Domain_Contact();
        $c->setId($wc['contactid'])
            ->setName($wc['name'])
            ->setEmail($wc['emailaddr'])
            ->setCompany($wc['company'])
            ->setTel($wc['telno'])
            ->setTelCc($wc['telnocc'])
            ->setAddress1($wc['address1'])
            ->setCity($wc['city'])
            ->setCountry($wc['country'])
            ->setState($wc['state'])
            ->setZip($wc['zip']);

        if (isset($wc['address2'])) {
            $c->setAddress2($wc['address2']);
        }

        if (isset($wc['address3'])) {
            $c->setAddress3($wc['address3']);
        }

        $d->setContactRegistrar($c);

        if (isset($data['ns1'])) {
            $d->setNs1($data['ns1']);
        }
        if (isset($data['ns2'])) {
            $d->setNs2($data['ns2']);
        }
        if (isset($data['ns3'])) {
            $d->setNs3($data['ns3']);
        }
        if (isset($data['ns4'])) {
            $d->setNs4($data['ns4']);
        }

        return $d;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        $required_params = [
            'order-id' => $this->_getDomainOrderId($domain),
        ];
        $result = $this->_makeRequest('domains/delete', $required_params, 'POST');

        return strtolower($result['status']) == 'success';
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        if ($this->_hasCompletedOrder($domain)) {
            return true;
        }

        $tld = $domain->getTld();
        $customer = $this->_getCustomerDetails($domain);
        $customer_id = $customer['customerid'];

        $ns = [];
        $ns[] = $domain->getNs1();
        $ns[] = $domain->getNs2();
        if ($domain->getNs3()) {
            $ns[] = $domain->getNs3();
        }
        if ($domain->getNs4()) {
            $ns[] = $domain->getNs4();
        }

        [$reg_contact_id, $admin_contact_id, $tech_contact_id, $billing_contact_id] = $this->_getAllContacts($tld, $customer_id, $domain->getContactRegistrar());

        $params = [
            'domain-name' => $domain->getName(),
            'years' => $domain->getRegistrationPeriod(),
            'ns' => $ns,
            'customer-id' => $customer_id,
            'reg-contact-id' => $reg_contact_id,
            'admin-contact-id' => $admin_contact_id,
            'tech-contact-id' => $tech_contact_id,
            'billing-contact-id' => $billing_contact_id,
            'invoice-option' => 'NoInvoice',
            'protect-privacy' => false,
        ];

        if ($tld == '.asia') {
            $params['attr-name1'] = 'cedcontactid';
            $params['attr-value1'] = 'default';
        }

        if ($tld == '.de') {
            $params['ns'] = ['dns1.directi.com', 'dns2.directi.com', 'dns3.directi.com', 'dns4.directi.com'];
        }

        if ($tld == '.au' || $tld == '.net.au' || $tld == '.com.au') {
            $contact = $domain->getContactRegistrar();

            if (strlen(trim($contact->getCompanyNumber())) == 0) {
                throw new Registrar_Exception('A valid contact company number is mandatory for registering an .AU domain name');
            }
            $params['attr-name1'] = 'id-type';
            $params['attr-value1'] = 'ACN';
            $params['attr-name2'] = 'id';
            $params['attr-value2'] = $contact->getCompanyNumber();
            $params['attr-name3'] = 'policyReason';
            $params['attr-value3'] = '1';
            $params['attr-name4'] = 'isAUWarranty';
            $params['attr-value4'] = '1';
        }

        $result = $this->_makeRequest('domains/register', $params, 'POST');

        return $result['status'] == 'Success';
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = [
            'order-id' => $this->_getDomainOrderId($domain),
            'years' => $domain->getRegistrationPeriod(),
            'exp-date' => $domain->getExpirationTime(),
            'invoice-option' => 'NoInvoice',
        ];

        $result = $this->_makeRequest('domains/renew', $params, 'POST');

        return $result['actionstatus'] == 'Success';
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $order_id = $this->_getDomainOrderId($domain);
        $params = [
            'order-id' => $order_id,
            'protect-privacy' => true,
            'reason' => 'Owners decision',
        ];

        $result = $this->_makeRequest('domains/modify-privacy-protection', $params, 'POST');

        return strtolower($result['actionstatus']) == 'success';
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $order_id = $this->_getDomainOrderId($domain);
        $params = [
            'order-id' => $order_id,
            'protect-privacy' => false,
            'reason' => 'Owners decision',
        ];

        $result = $this->_makeRequest('domains/modify-privacy-protection', $params, 'POST');

        return strtolower($result['actionstatus']) == 'success';
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $params = [
            'order-id' => $this->_getDomainOrderId($domain),
            'options' => 'OrderDetails',
        ];
        $data = $this->_makeRequest('domains/details', $params);
        if (!isset($data['domsecret'])) {
            $placeholders = [':action:' => __trans('get the transfer code'), ':type:' => 'ResellerClub'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }

        return $data['domsecret'];
    }

    public function lock(Registrar_Domain $domain)
    {
        $params = [
            'order-id' => $this->_getDomainOrderId($domain),
        ];
        $result = $this->_makeRequest('domains/enable-theft-protection', $params, 'POST');

        return strtolower($result['status']) == 'success';
    }

    public function unlock(Registrar_Domain $domain)
    {
        $params = [
            'order-id' => $this->_getDomainOrderId($domain),
        ];
        $result = $this->_makeRequest('domains/disable-theft-protection', $params, 'POST');

        return strtolower($result['status']) == 'success';
    }

    private function _getCustomerDetails(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        $params = [
            'username' => $c->getEmail(),
        ];

        try {
            $result = $this->_makeRequest('customers/details', $params);
        } catch (Registrar_Exception) {
            $this->_createCustomer($domain);
            $result = $this->_makeRequest('customers/details', $params);
        }

        return (array) $result;
    }

    private function _createCustomer(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        $company = $c->getCompany();
        if (!isset($company) || strlen(trim($company)) == 0) {
            $company = 'N/A';
        }
        $phoneNum = $c->getTel();
        $phoneNum = preg_replace('/[^0-9]/', '', $phoneNum);
        $phoneNum = substr($phoneNum, 0, 12);
        $params = [
            'username' => $c->getEmail(),
            'passwd' => $c->getPassword(),
            'name' => $c->getName(),
            'company' => $company,
            'address-line-1' => $c->getAddress1(),
            'address-line-2' => $c->getAddress2(),
            'city' => $c->getCity(),
            'state' => $c->getState(),
            'country' => $c->getCountry(),
            'zipcode' => $c->getZip(),
            'phone-cc' => $c->getTelCc(),
            'phone' => $phoneNum,
            'lang-pref' => 'en',
            'sales-contact-id' => '',
            'accounting-currency-symbol' => 'USD',
            'selling-currency-symbol' => 'USD',
            'request-headers' => '',
        ];

        $optional_params = [
            'address-line-3' => '',
            'alt-phone-cc' => '',
            'alt-phone' => '',
            'fax-cc' => '',
            'fax' => '',
            'mobile-cc' => '',
            'mobile' => '',
        ];

        $params = [...$optional_params, ...$params];

        return $this->_makeRequest('customers/signup', $params, 'POST');
    }

    public function getContactIdForDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();
        $customer = $this->_getCustomerDetails($domain);
        $customer_id = $customer['customerid'];

        $tld = $domain->getTld();
        $contact = [
            'customer-id' => $customer_id,
            'type' => 'Contact',
            'email' => $c->getEmail(),
            'name' => $c->getName(),
            'company' => $c->getCompany(),
            'address-line-1' => $c->getAddress1(),
            'address-line-2' => $c->getAddress2(),
            'city' => $c->getCity(),
            'state' => $c->getState(),
            'country' => $c->getCountry(),
            'zipcode' => $c->getZip(),
            'phone-cc' => $c->getTelCc(),
            'phone' => $c->getTel(),
        ];

        if ($tld == '.uk') {
            $contact['type'] = 'UkContact';
        }

        if ($tld == '.eu') {
            $contact['type'] = 'EuContact';
        }

        if ($tld == '.cn') {
            $contact['type'] = 'CnContact';
        }

        if ($tld == '.ca') {
            $contact['type'] = 'CaContact';
        }

        if ($tld == '.de') {
            $contact['type'] = 'DeContact';
        }

        if ($tld == '.es') {
            $contact['type'] = 'EsContact';
        }

        if ($tld == '.ru') {
            $contact['type'] = 'RuContact';
        }

        return $this->_makeRequest('contacts/add', $contact, 'POST');
    }

    private function getResellerDetails()
    {
        return $this->_makeRequest('resellers/details');
    }

    private function getPromoPrices()
    {
        return $this->_makeRequest('resellers/promo-details');
    }

    /**
     * @see http://manage.resellerclub.com/kb/answer/808
     *
     * @param array $params
     *
     * @return stdClass
     */
    private function addSubReseller($params)
    {
        // default values
        $required_params = [
            'username' => '',
            'passwd' => '',
            'name' => '',
            'company' => '',
            'address-line-1' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'zipcode' => '',
            'phone-cc' => '',
            'phone' => '',
            'lang-pref' => 'en',
            'sales-contact-id' => '',
            'accounting-currency-symbol' => 'USD',
            'selling-currency-symbol' => 'USD',
            'request-headers' => '',
        ];

        $optional_params = [
            'address-line-2' => '',
            'address-line-3' => '',
            'alt-phone-cc' => '',
            'alt-phone' => '',
            'fax-cc' => '',
            'fax' => '',
            'mobile-cc' => '',
            'mobile' => '',
        ];

        $params = $this->_checkRequiredParams($required_params, $params);
        $params = array_merge($optional_params, $params);
        $result = $this->_makeRequest('resellers/signup', $params, 'POST');

        if (isset($result['status']) && $result['status'] == 'AlreadyReseller') {
            throw new Registrar_Exception('You are already registered as reseller');
        }

        return $result;
    }

    private function _getDefaultContactDetails(Registrar_Domain $domain, $customerid)
    {
        $params = [
            'customer-id' => $customerid,
            'type' => 'Contact',
        ];

        return $this->_makeRequest('contacts/default', $params, 'POST');
    }

    private function removeCustomer($params)
    {
        $required_params = [
            'customer-id' => '',
        ];
        $params = $this->_checkRequiredParams($required_params, $params);
        $result = $this->_makeRequest('customers/delete', $params, 'POST');

        return $result == 'true';
    }

    private function _hasCompletedOrder(Registrar_Domain $domain)
    {
        try {
            $orderid = $this->_getDomainOrderId($domain);
            $params = [
                'order-id' => $orderid,
                'options' => 'All',
            ];
            $data = $this->_makeRequest('domains/details', $params);
        } catch (Exception) {
            return false;
        }

        return $data['currentstatus'] == 'Active';
    }

    public function isTestEnv()
    {
        return $this->_testMode;
    }

    /**
     * Api URL.
     *
     * @return string
     */
    private function _getApiUrl()
    {
        if ($this->isTestEnv()) {
            return 'https://test.httpapi.com/api/';
        }

        return 'https://httpapi.com/api/';
    }

    /**
     * @return array
     */
    public function includeAuthorizationParams(array $params)
    {
        return [...$params, 'auth-userid' => $this->config['userid'], 'api-key' => $this->config['api-key']];
    }

    /**
     * Perform call to Api.
     *
     * @param string $url
     * @param array  $params
     * @param string $method
     *
     * @throws Registrar_Exception
     */
    protected function _makeRequest($url, $params = [], $method = 'GET', $type = 'json'): array|string
    {
        $params = $this->includeAuthorizationParams($params);

        $client = $this->getHttpClient()->withOptions([
            'timeout' => 60,
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        $callUrl = $this->_getApiUrl() . $url . '.' . $type;

        try {
            if ($method == 'POST') {
                $result = $client->request('POST', $callUrl, [
                    'body' => $this->_formatParams($params),
                ]);
            } else {
                $result = $client->request('GET', $callUrl . '?' . $this->_formatParams($params));
                $this->getLog()->debug('API REQUEST: ' . $callUrl . '?' . $this->_formatParams($params));
            }
            $this->getLog()->info('API RESULT: ' . $result->getContent(false));
        } catch (HttpExceptionInterface $error) {
            $e = new Registrar_Exception(sprintf('HttpClientException: %s', $error->getMessage()));
            $this->getLog()->err($e);

            throw $e;
        }

        if ($result->getContent(false) == 'true') {
            return $result->getContent(false);
        }
        if (is_numeric($result->getContent(false))) {
            return $data = $result->getContent(false);
        }
        $json = $result->toArray(false);
        if (!is_array($json)) {
            return $data = $result->getContent(false);
        }

        if (isset($json['status']) && $json['status'] == 'ERROR') {
            error_log('ResellerClub error: ' . $json['message']);
            $placeholders = [':action:' => $url, ':type:' => 'ResellerClub'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }

        if (isset($json['status']) && $json['status'] == 'error') {
            error_log('ResellerClub error: ' . $json['error']);
            $placeholders = [':action"' => $url, ':type:' => 'ResellerClub'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }

        if (isset($json['status']) && $json['status'] == 'Failed') {
            error_log('ResellerClub error: ' . $json['actionstatusdesc']);
            $placeholders = [':action:' => $url, ':type:' => 'ResellerClub'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }

        return $json;
    }

    /**
     * Convert params to resellerClub format.
     *
     * @see http://manage.resellerclub.com/kb/answer/755
     *
     * @param array $params
     *
     * @return string
     */
    private function _formatParams($params)
    {
        foreach ($params as &$param) {
            if (is_bool($param)) {
                $param = ($param) ? 'true' : 'false';
            }
        }

        $params = http_build_query($params);

        return preg_replace('~%5B(\d+)%5D~', '', $params);
    }

    /**
     * Check if all required params are present, if not add default values.
     *
     * @param array $required_params - list of required params with default values
     * @param array $params          - given params
     *
     * @return array
     *
     * @throws Registrar_Exception
     */
    private function _checkRequiredParams($required_params, $params)
    {
        foreach ($required_params as $param => $value) {
            if (!isset($params[$param])) {
                $params[$param] = $value;
            }
        }

        return $params;
    }

    private function _getAllContacts($tld, $customer_id, Registrar_Domain_Contact $client)
    {
        if ($tld[0] != '.') {
            $tld = '.' . $tld; // $tld must start with a dot(.)
        }

        $company = $client->getCompany();
        if (!isset($company) || strlen(trim($company)) == 0) {
            $company = $client->getFirstName() . ' ' . $client->getLastName();
        }

        $contact = [
            'customer-id' => $customer_id,
            'type' => 'Contact',
            'email' => $client->getEmail(),
            'name' => $client->getFirstName() . ' ' . $client->getLastName(),
            'company' => $company,
            'address-line-1' => $client->getAddress1(),
            'city' => $client->getCity(),
            'state' => $client->getState(),
            'country' => $client->getCountry(),
            'zipcode' => $client->getZip(),
            'phone-cc' => $client->getTelCc(),
            'phone' => substr($client->getTel(), 0, 12), // phone must be 4-12 digits
        ];

        // @see http://manage.resellerclub.com/kb/answer/790 for us contact details
        if ($tld == '.us') {
            $contact['attr-name1'] = 'purpose';
            $contact['attr-value1'] = 'P3';

            $contact['attr-name2'] = 'category';
            $contact['attr-value2'] = 'C12';
        }

        // create general contact id
        $reg_contact_id = $this->_getContact($contact, $customer_id, $contact['type']);

        if ($tld == '.nl') {
            $contact['type'] = 'NlContact';
            $contact['attr-name1'] = 'legalForm';
            $contact['attr-value1'] = 'PERSOON';
        }

        if ($tld == '.uk' || $tld == '.co.uk' || $tld == '.org.uk') {
            $contact['type'] = 'UkContact';
        }

        if ($tld == '.eu') {
            $contact['type'] = 'EuContact';
        }

        if ($tld == '.cn') {
            $contact['type'] = 'CnContact';
        }

        if ($tld == '.ca') {
            $contact['type'] = 'CaContact';

            $contact['attr-name1'] = 'CPR';
            $contact['attr-value1'] = 'LGR';

            $contact['attr-name2'] = 'AgreementVersion';
            $contact['attr-value2'] = $this->getCARegistrantAgreementVersion();

            $contact['attr-name3'] = 'AgreementValue';
            $contact['attr-value3'] = 'y';
        }

        if ($tld == '.de') {
            $contact['type'] = 'DeContact';
        }

        if ($tld == '.es') {
            if (strlen(trim($client->getDocumentNr())) == 0) {
                throw new Registrar_Exception('Valid contact passport information is required while registering ES domain name');
            }

            // @see http://manage.directi.com/kb/answer/790
            $contact['type'] = 'EsContact';
            $contact['attr-name1'] = 'es_form_juridica';
            $contact['attr-value1'] = '1';
            $contact['attr-name2'] = 'es_tipo_identificacion';
            $contact['attr-value2'] = '0';
            $contact['attr-name3'] = 'es_identificacion';
            $contact['attr-value3'] = $client->getDocumentNr();
        }

        if ($tld == '.co' || str_ends_with($tld, '.co')) {
            $contact['type'] = 'CoContact';
        }

        if ($tld == '.asia') {
            if (strlen(trim($client->getDocumentNr())) == 0) {
                throw new Registrar_Exception('Valid contact passport information is required while registering ASIA domain name');
            }

            $contact['attr-name1'] = 'locality';
            $contact['attr-value1'] = 'TH'; // {Two-lettered Country code}

            $contact['attr-name2'] = 'legalentitytype';
            $contact['attr-value2'] = 'naturalPerson'; // {naturalPerson | corporation | cooperative | partnership | government | politicalParty | society | institution | other}

            $contact['attr-name3'] = 'otherlegalentitytype';
            $contact['attr-value3'] = 'naturalPerson'; // {Mention legal entity type. Mandatory if legalentitytype chosen as 'other'}

            $contact['attr-name4'] = 'identform';
            $contact['attr-value4'] = 'passport'; // {passport | certificate | legislation | societyRegistry | politicalPartyRegistry | other}

            $contact['attr-name5'] = 'otheridentform';
            $contact['attr-value5'] = 'passport'; // {Mention Identity form. Mandatory if identform chosen as 'other'}

            $contact['attr-name6'] = 'identnumber';
            $contact['attr-value6'] = $client->getDocumentNr(); // {Mention Identification Number}]
        }

        if ($tld == '.ru' || $tld == '.com.ru' || $tld == '.org.ru' || $tld == '.net.ru') {
            if (strlen(trim($client->getBirthday())) === 0 || strtotime($client->getBirthday()) === false) {
                throw new Registrar_Exception('Valid contact birthdate is required while registering RU domain name');
            }

            if (strlen(trim($client->getDocumentNr())) === 0) {
                throw new Registrar_Exception('Valid contact passport information is required while registering RU domain name');
            }

            if (str_word_count($contact['company']) < 2) {
                $contact['company'] .= ' Inc';
            }

            $contact['type'] = 'RuContact';
            $contact['attr-name1'] = 'contract-type';
            $contact['attr-value1'] = 'PRS';
            $contact['attr-name2'] = 'birth-date';
            $contact['attr-value2'] = date('d.m.Y', strtotime($client->getBirthday()));
            $contact['attr-name3'] = 'person-r';
            $contact['attr-value3'] = $client->getFirstName() . ' ' . $client->getLastName();
            $contact['attr-name4'] = 'address-r';
            $contact['attr-value4'] = $client->getAddress1();
            $contact['attr-name5'] = 'passport';
            $contact['attr-value5'] = $client->getDocumentNr();
        }

        if ($tld == '.ca') {
            $client->setIdnLanguageCode('fr');
        }
        if ($tld == '.de') {
            $client->setIdnLanguageCode('de');
        }
        if ($tld == '.es') {
            $client->setIdnLanguageCode('es');
        }
        if ($tld == '.eu') {
            $client->setIdnLanguageCode('latin');
        }

        $param_exists = true;
        $attr_number = 1;
        while ($param_exists) {
            if (!array_key_exists('attr-name' . $attr_number, $contact)) {
                $contact['attr-name' . $attr_number] = 'idnLanguageCode';
                $contact['attr-value' . $attr_number] = strtolower($client->getIdnLanguageCode());
                $param_exists = false;
            }
            ++$attr_number;
        }

        $special_contact_id = null;
        if ($contact['type'] != 'Contact') {
            $special_contact_id = $this->_getContact($contact, $customer_id, $contact['type']);
        }

        // by default special contact is also admin, tech and billing contact, but not always
        $admin_contact_id = $special_contact_id ?? $reg_contact_id;
        $tech_contact_id = $special_contact_id ?? $reg_contact_id;
        $billing_contact_id = $special_contact_id ?? $reg_contact_id;

        // override some parameters
        if (in_array($tld, ['.uk', '.co.uk', '.org.uk', '.nz', '.ru', '.com.ru', '.org.ru', '.net.ru', '.eu'])) {
            $admin_contact_id = -1;
        }

        if (in_array($tld, ['.uk', '.co.uk', '.org.uk', '.nz', '.ru', '.com.ru', '.org.ru', '.net.ru', '.eu'])) {
            $tech_contact_id = -1;
        }

        if (in_array($tld, ['.uk', '.co.uk', '.org.uk', '.nz', '.ru', '.com.ru', '.org.ru', '.net.ru', '.eu', '.ca', '.nl'])) {
            $billing_contact_id = -1;
        }

        // general contact is special contact for these TLD'S
        if (in_array($tld, ['.de', '.nl', '.ru', '.es', '.uk', '.co.uk', '.org.uk', '.eu', '.com.ru', '.net.ru', '.org.ru', '.co'])) {
            $reg_contact_id = $special_contact_id;
        }

        return [$reg_contact_id, $admin_contact_id, $tech_contact_id, $billing_contact_id];
    }

    private function _getContact($contact, $customer_id, $type = 'Contact')
    {
        try {
            $params = [
                'customer-id' => $customer_id,
                'no-of-records' => 20,
                'page-no' => 1,
                'status' => 'Active',
                'type' => $type,
            ];
            $result = $this->_makeRequest('contacts/search', $params, 'GET', 'json');
            if ($result['recsonpage'] < 1) {
                throw new Registrar_Exception('Contact not found');
            }
            $existing_contact_id = $result['result'][0]['entity.entityid'];
            $this->_makeRequest('contacts/delete', ['contact-id' => $existing_contact_id], 'POST');
        } catch (Registrar_Exception $e) {
            $this->getLog()->info($e->getMessage());
        }

        return $this->_makeRequest('contacts/add', $contact, 'POST');
    }

    private function getCARegistrantAgreementVersion()
    {
        $agreement = $this->_makeRequest('contacts/dotca/registrantagreement', [], 'GET', 'json');

        return $agreement['version'];
    }
}
