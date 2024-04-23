<?php

use GeoIp2\Model\Domain;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class Registrar_Adapter_Namecheap extends Registrar_AdapterAbstract
{
    public $config = [
        'api-user-id' => null,
        'api-key' => null,
        'username' => null,
        'ip' => null,
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
        if (!empty($options['api-key'])) {
            $this->config['api-key'] = $options['api-key'];
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'Namecheap', ':missing' => 'API Key'], 3001);
        }

        if (!empty($options['username'])) {
            $this->config['username'] = $options['username'];
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'Namecheap', ':missing' => 'Username'], 3001);
        }

        if (empty($options['api-user-id'])) {
            $this->config['api-user-id'] = $options['username'];
        } else {
            $this->config['api-user-id'] = $options['api-user-id'];
        }

        if (!empty($options['ip'])) {
            $this->config['ip'] = $options['ip'];
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'Namecheap', ':missing' => 'server IP address'], 3001);
        }
    }

    public static function getConfig()
    {
        return [
            'label' => 'Manages domains on Namecheap via API. Namecheap requires your server IP in order to work.',
            'form' => [
                'api-user-id' => [
                    'text', [
                        'label' => 'Reseller ID',
                        'description' => 'Namecheap Reseller ID. If you don\'t have one, leave this blank.',
                        'required' => false,
                    ],
                ],
                'api-key' => [
                    'password', [
                        'label' => 'API Key',
                        'description' => 'You can get this at Namecheap control panel.',
                        'required' => true,
                    ],
                ],
                'username' => [
                    'text', [
                        'label' => 'Namecheap Username',
                        'description' => 'The username you use to login to the Namecheap control panel.',
                        'required' => true,
                    ],
                ],
                'ip' => [
                    'text', [
                        'label' => 'Server\'s Public IP Address',
                        'description' => 'Public IP address of this server. Ensure that this IP is whitelisted under your NameCheap settings.',
                        'required' => true,
                    ],
                ],
            ],
        ];
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = [
            'DomainList' => strtolower($domain->getSld()) . $domain->getTld(),
            'Command' => 'namecheap.domains.check',
        ];

        $result = $this->_makeRequest($params);

        if (isset($result->CommandResponse->DomainCheckResult['IsPremiumName']) && $result->CommandResponse->DomainCheckResult['IsPremiumName'] == 'true') {
            throw new Registrar_Exception('Premium domains cannot be registered.');
        }

        if (isset($result->CommandResponse->DomainCheckResult['Available']) && $result->CommandResponse->DomainCheckResult['Available'] == 'true') {
            return true;
        }

        return false;
    }

    /**
     * Perform call to Api.
     *
     * @param array  $params
     * @param string $method
     *
     * @return object
     *
     * @throws Registrar_Exception
     */
    protected function _makeRequest($params = [], $method = 'GET')
    {
        $params = $this->includeAuthorizationParams($params);

        $client = $this->getHttpClient()->withOptions([
            'timeout' => 60,
            'verify_peer' => 0,
            'verify_host' => 0,
        ]);

        $callUrl = $this->_getApiUrl();

        try {
            if ($method == 'POST') {
                $response = $client->request('POST', $callUrl, [
                    'body' => $this->_formatParams($params),
                ]);
                $this->getLog()->debug('API REQUEST: ' . $callUrl . '?' . $this->_formatParams($params));
            } else {
                $response = $client->request('GET', $callUrl . '?' . $this->_formatParams($params));
                $this->getLog()->debug('API REQUEST: ' . $callUrl . '?' . $this->_formatParams($params));
            }
        } catch (HttpExceptionInterface $error) {
            $e = new Registrar_Exception(sprintf('HttpClientException: %s', $error->getMessage()));
            $this->getLog()->err($e);

            throw $e;
        }

        $data = $response->getContent();

        $this->getLog()->info('API RESULT: ' . $data);

        $result = simplexml_load_string($data);

        if (isset($result['status']) && strtolower($result['status']) == 'error') {
            error_log('Namecheap error: ' . PHP_EOL . $result['error']);
            $placeholders = [':action:' => $params['Command'], ':type:' => 'Namecheap'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }

        if (isset($result['status']) && strtolower($result['status']) == 'failed') {
            error_log('Namecheap error: ' . PHP_EOL . $result['actionstatusdesc']);
            $placeholders = [':action:' => $params['Command'], ':type:' => 'Namecheap'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }

        return $result;
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

        return http_build_query($params);
    }

    /**
     * Api URL.
     *
     * @return string
     */
    private function _getApiUrl()
    {
        if ($this->isTestEnv()) {
            return 'https://api.sandbox.namecheap.com/xml.response';
        }

        return 'https://api.namecheap.com/xml.response';
    }

    public function isTestEnv()
    {
        return $this->_testMode;
    }

    /**
     * @return array
     */
    public function includeAuthorizationParams(array $params)
    {
        return ['ApiUser' => $this->config['api-user-id'], 'ApiKey' => $this->config['api-key'], 'UserName' => $this->config['username'], 'ClientIp' => $this->config['ip'], ...$params];
    }

    /**
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function modifyNs(Registrar_Domain $domain)
    {
        // get NS info
        $params = [
            'SLD' => $domain->getSld(),
            'TLD' => $domain->getTld($with_dot = false),
            'NameServers' => $domain->getNs1() . ',' . $domain->getNs2() . ',' . $domain->getNs3() . ',' . $domain->getNs4(),
            'Command' => 'namecheap.domains.dns.setCustom',
        ];

        $result = $this->_makeRequest($params);

        // TODO
        // problem here: FOSSBilling doesn't display our error and will display 'nameservers updates' evern when this fails

        if (!isset($result->CommandResponse->DomainDNSSetCustomResult['Updated']) && $result->CommandResponse->DomainDNSSetCustomResult['Updated'] != 'true') {
            $placeholders = [':action:' => __trans('update nameservers'), ':type:' => 'Namecheap'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }

        return true;
    }

    /**
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $params = [
            'Command' => 'namecheap.domains.setContacts',
            'DomainName' => strtolower($domain->getSld()) . $domain->getTld(),
        ];

        // Set contact data
        foreach (['Registrant', 'Admin', 'Tech', 'AuxBilling'] as $contactType) {
            $c = $domain->getContactRegistrar();

            if ($contactType == 'Admin' && $domain->getContactAdmin()) {
                $c = $domain->getContactAdmin();
            }

            if ($contactType == 'Tech' && $domain->getContactTech()) {
                $c = $domain->getContactTech();
            }

            if ($contactType == 'AuxBilling' && $domain->getContactBilling()) {
                $c = $domain->getContactBilling();
            }

            $params[$contactType . 'FirstName'] = $c->getFirstName();
            $params[$contactType . 'LastName'] = $c->getLastName();
            $params[$contactType . 'EmailAddress'] = $c->getEmail();
            $params[$contactType . 'Phone'] = '+' . $c->getTelCc() . '.' . $c->getTel();
            $params[$contactType . 'Address1'] = $c->getAddress1();
            $params[$contactType . 'Address2'] = $c->getAddress2();
            $params[$contactType . 'City'] = $c->getCity();
            $params[$contactType . 'StateProvince'] = $c->getState();
            $params[$contactType . 'Country'] = $c->getCountry();
            $params[$contactType . 'PostalCode'] = $c->getZip();
        }
        $result = $this->_makeRequest($params);

        return $result->CommandResponse->DomainSetContactResult['IsSuccess'] == 'true';
    }

    /**
     * relying on domain->getEpp() to return user's input for Epp code.
     *
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        $params = [
            'DomainName' => $domain->getName(),
            'Years' => '1',
            'EPPCode' => $domain->getEpp(),
            'Command' => 'namecheap.domains.transfer.create',
        ];

        $result = $this->_makeRequest($params);
        if (isset($result->CommandResponse->DomainTransferCreateResult['Transfer']) && $result->CommandResponse->DomainTransferCreateResult['Transfer'] == 'true') {
            return true;
        }

        return false;
    }

    /**
     * Should return details of registered domain
     * If domain is not registered should throw Registrar_Exception.
     *
     * @return Registrar_Domain
     *
     * @throws Registrar_Exception
     */
    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = [
            'DomainName' => $domain->getName(),
            'Command' => 'namecheap.domains.getInfo',
        ];

        $result = $this->_makeRequest($params);

        $domain->setRegistrationTime((string) $result->CommandResponse->DomainGetInfoResult->DomainDetails->CreatedDate);
        $domain->setExpirationTime((string) $result->CommandResponse->DomainGetInfoResult->DomainDetails->ExpiredDate);
        $domain->setPrivacyEnabled((string) $result->CommandResponse->DomainGetInfoResult->Whoisguard['Enabled']);

        $params = [
            'DomainName' => $domain->getName(),
            'Command' => 'namecheap.domains.getContacts',
        ];

        $result = $this->_makeRequest($params);

        if (!isset($result->CommandResponse->DomainContactsResult)) {
            $placeholders = [':action:' => __trans('retrieve domain details'), ':type:' => 'Namecheap'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }

        $contacts = $result->CommandResponse->DomainContactsResult;
        // create new Domain obj to return
        $newDomain = new Registrar_Domain();

        // set SLD and TLD
        $newDomain->setSld($domain->getSld());
        $newDomain->setTld($domain->getTld());

        $registrarContact = new Registrar_Domain_Contact();
        $adminContact = new Registrar_Domain_Contact();
        $techContact = new Registrar_Domain_Contact();
        $billingContact = new Registrar_Domain_Contact();

        // Set contact data on our Domain obj using info from our API call
        foreach (['Registrant', 'Admin', 'Tech', 'AuxBilling'] as $contactType) {
            $contactApi = $contacts->Registrant;
            $contact = $registrarContact;

            if ($contactType == 'Admin') {
                $contactApi = $contacts->Admin;
                $contact = $adminContact;
            }
            if ($contactType == 'Tech') {
                $contactApi = $contacts->Tech;
                $contact = $techContact;
            }
            if ($contactType == 'AuxBilling') {
                $contactApi = $contacts->AuxBilling;
                $contact = $billingContact;
            }

            $contact->setFirstName((string) $contactApi->FirstName);
            $contact->setLastName((string) $contactApi->LastName);
            $contact->setEmail((string) $contactApi->EmailAddress);
            $contact->setTel((string) $contactApi->Phone);
            $contact->setAddress1((string) $contactApi->Address1);
            $contact->setAddress2((string) $contactApi->Address2);
            $contact->setCity((string) $contactApi->City);
            $contact->setState((string) $contactApi->StateProvince);
            $contact->setCountry((string) $contactApi->Country);
            $contact->setZip((string) $contactApi->PostalCode);
        }

        $newDomain->setContactRegistrar($registrarContact);
        $newDomain->setContactAdmin($adminContact);
        $newDomain->setContactTech($techContact);
        $newDomain->setContactBilling($billingContact);

        // get NS info
        $params = [
            'SLD' => $newDomain->getSld(),
            'TLD' => $newDomain->getTld($with_dot = false),
            'Command' => 'namecheap.domains.dns.getList',
        ];

        $result = $this->_makeRequest($params);

        $NsArr = [];
        $xmlNsList = $result->CommandResponse->DomainDNSGetListResult;
        foreach ($xmlNsList->Nameserver as $Nameserver) {
            $NsArr[] = $Nameserver;
        }

        for ($i = 1; $i <= 4; ++$i) {
            if (isset($NsArr[$i - 1])) {
                $newDomain->{'setNs' . $i}((string) $NsArr[$i - 1]);
            }
        }

        return $newDomain;
    }

    /**
     * Should return domain transfer code.
     *
     * @throws Registrar_Exception
     */
    public function getEpp(Registrar_Domain $domain): never
    {
        throw new Registrar_Exception(':type: does not support :action:', [':type:' => 'Namecheap', ':action:' => __trans('retrieving the transfer code')]);
    }

    /**
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function registerDomain(Registrar_Domain $domain)
    {
        $params = [
            'Command' => 'namecheap.domains.create',
            'DomainName' => strtolower($domain->getSld()) . $domain->getTld(),
            'Years' => $domain->getRegistrationPeriod(),
        ];

        if ($domain->getNs1()) {
            // Add nameservers
            $nsList = [];
            $nsList[] = $domain->getNs1();
            $nsList[] = $domain->getNs2();
            if ($domain->getNs3()) {
                $nsList[] = $domain->getNs3();
            }
            if ($domain->getNs4()) {
                $nsList[] = $domain->getNs4();
            }
            $params['Nameservers'] = implode(',', $nsList);
        }

        // Set contact data
        foreach (['Registrant', 'Admin', 'Tech', 'AuxBilling'] as $contactType) {
            $c = $domain->getContactRegistrar();

            if ($contactType == 'Admin' && $domain->getContactAdmin()) {
                $c = $domain->getContactAdmin();
            }

            if ($contactType == 'Tech' && $domain->getContactTech()) {
                $c = $domain->getContactTech();
            }

            if ($contactType == 'AuxBilling' && $domain->getContactBilling()) {
                $c = $domain->getContactBilling();
            }

            $params[$contactType . 'FirstName'] = $c->getFirstName();
            $params[$contactType . 'LastName'] = $c->getLastName();
            $params[$contactType . 'EmailAddress'] = $c->getEmail();
            $params[$contactType . 'Phone'] = '+' . $c->getTelCc() . '.' . $c->getTel();
            $params[$contactType . 'Address1'] = $c->getAddress1();
            $params[$contactType . 'Address2'] = $c->getAddress2();
            $params[$contactType . 'City'] = $c->getCity();
            $params[$contactType . 'StateProvince'] = $c->getState();
            $params[$contactType . 'Country'] = $c->getCountry();
            $params[$contactType . 'PostalCode'] = $c->getZip();
        }
        $params['AddFreeWhoisguard'] = 'yes';

        if ($domain->getTld() == '.us') {
            $params['RegistrantPurpose'] = 'P3';
            $params['RegistrantNexus'] = 'C11';
        }

        if ($domain->getTld() == '.eu') {
            $params['EUAgreeWhoisPolicy'] = 'YES';
            $params['EUAgreeDeletePolicy'] = 'YES';
        }
        // passport for swedish identification
        if ($domain->getTld() == '.nu') {
            $params['NUOrgNo'] = $domain->getContactRegistrar()->getDocumentNr();
        }

        // need to check if user is a canadian resident or if coorporation is registered in canada
        // if ($domain->getTld() == '.ca') {
        //     $params['CIRALegalType'] = 'CCT';
        //     $params['EUAgreeDeletePolicy'] = 'YES';
        // }

        if ($domain->getTld() == '.co.uk' || $domain->getTld() == '.me.uk' || $domain->getTld() == '.org.uk') {
            if ($domain->getContactRegistrar()->getCountry() == 'UK') {
                $params['COUKLegalType'] = 'IND';
            } else {
                $params['COUKLegalType'] = 'FIND';
            }

            $params['COUKRegisteredfor'] = $domain->getContactRegistrar()->getName();
        }

        // unsure how to handle this
        // if ($domain->getTld() == '.com.au' || $domain->getTld() == '.net.au' || $domain->getTld() == '.org.au') {
        //     $params['COMAURegistrantId'] = '';
        //     $params['COMAURegistrantIdType'] = '';
        // }

        // .de domains require an admin address in Germany
        // if ($domain->getTld() == '.de') {
        //     //confirm here that the admin address is in fact germany
        //     $params['DEConfirmAddress'] = 'DE';
        //     $params['DEAgreeDelete'] = 'Yes';
        // }

        if ($domain->getTld() == '.fr') {
            $params['FRLegalType'] = 'Individual';
            $params['FRRegistrantBirthDate'] = $domain->getContactRegistrar()->getBirthday();
            $params['FRRegistrantBirthplace'] = $domain->getContactRegistrar()->getCountry();
        }

        $result = $this->_makeRequest($params);

        return isset($result->CommandResponse->DomainCreateResult['Registered'])
            && ($result->CommandResponse->DomainCreateResult['Registered'] == 'true');
    }

    /**
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        $params = [
            'Command' => 'namecheap.domains.renew',
            'DomainName' => $domain->getName(),
            'Years' => $domain->getRegistrationPeriod(),
        ];

        $result = $this->_makeRequest($params);

        return isset($result->CommandResponse->DomainRenewResult['Renew'])
            && ($result->CommandResponse->DomainRenewResult['Renew'] == 'true');
    }

    /**
     * TODO: Implement this correctly.
     *
     * @throws Registrar_Exception
     */
    public function deleteDomain(Registrar_Domain $domain): never
    {
        throw new Registrar_Exception(':type: does not support :action:', [':type:' => 'Namecheap', ':action:' => __trans('deleting domains')]);
    }

    /**
     * @return string[]
     *
     * @throws Registrar_Exception
     */
    private function getPrivacyInfo(Registrar_Domain $domain)
    {
        $params = [
            'DomainName' => $domain->getName(),
            'Command' => 'namecheap.domains.getInfo',
        ];

        $result = $this->_makeRequest($params);

        if (isset($result->CommandResponse->DomainGetInfoResult->Whoisguard)) {
            return [
                'enabled' => $result->CommandResponse->DomainGetInfoResult->Whoisguard['Enabled'] == 'True',
                'id' => (string) $result->CommandResponse->DomainGetInfoResult->Whoisguard->ID,
            ];
        }
        $placeholders = [':action:' => __trans('retrieve privacy information'), ':type:' => 'Namecheap'];

        throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
    }

    /**
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $privacyInfo = $this->getPrivacyInfo($domain);

        if ($privacyInfo['enabled']) {
            return true;
        }

        $params = [
            'WhoisguardID' => $privacyInfo['id'],
            'ForwardedToEmail' => $domain->getContactRegistrar()->getEmail(),
            'Command' => 'namecheap.whoisguard.enable',
        ];

        $result = $this->_makeRequest($params);

        return isset($result->CommandResponse->WhoisguardEnableResult['isSuccess'])
            && $result->CommandResponse->WhoisguardEnableResult['isSuccess'] == 'true';
    }

    /**
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $privacyInfo = $this->getPrivacyInfo($domain);

        if (!$privacyInfo['enabled']) {
            return true;
        }

        $params = [
            'WhoisguardID' => $privacyInfo['id'],
            'Command' => 'namecheap.whoisguard.disable',
        ];

        $result = $this->_makeRequest($params);

        return isset($result->CommandResponse->WhoisguardDisableResult['isSuccess'])
            && $result->CommandResponse->WhoisguardDisableResult['isSuccess'] == 'true';
    }

    /**
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function getLock(Registrar_Domain $domain)
    {
        $params = [
            'DomainName' => $domain->getName(),
            'Command' => 'namecheap.domains.getRegistrarLock',
        ];
        $result = $this->_makeRequest($params);
        if (!isset($result->CommandResponse->DomainGetRegistrarLockResult['RegistrarLockStatus'])) {
            $placeholders = [':action:' => __trans('get the domain lock status'), ':type:' => 'Namecheap'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }

        return $result->CommandResponse->DomainGetRegistrarLockResult['RegistrarLockStatus'] == 'true';
    }

    /**
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function lock(Registrar_Domain $domain)
    {
        if ($this->getLock($domain) == 'true') {
            return true;
        }

        $params = [
            'DomainName' => $domain->getName(),
            'LockAction' => 'LOCK',
            'Command' => 'namecheap.domains.setRegistrarLock',
        ];

        $result = $this->_makeRequest($params);
        if (!isset($result->CommandResponse->DomainSetRegistrarLockResult['IsSuccess'])) {
            $placeholders = [':action:' => __trans('set the domain lock status'), ':type:' => 'Namecheap'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }
        if ($result->CommandResponse->DomainGetRegistrarLockResult['IsSuccess'] == 'true') {
            $domain->setLocked('true');

            return true;
        }

        return false;
    }

    /**
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function unlock(Registrar_Domain $domain)
    {
        if ($this->getLock($domain) == 'false') {
            return true;
        }

        $params = [
            'DomainName' => $domain->getName(),
            'LockAction' => 'UNLOCK',
            'Command' => 'namecheap.domains.setRegistrarLock',
        ];

        $result = $this->_makeRequest($params);
        if (!isset($result->CommandResponse->DomainSetRegistrarLockResult['IsSuccess'])) {
            $placeholders = [':action:' => __trans('set the domain lock status'), ':type:' => 'Namecheap'];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }
        if ($result->CommandResponse->DomainGetRegistrarLockResult['IsSuccess'] == 'true') {
            $domain->setLocked('false');

            return true;
        }

        return false;
    }

    /**
     * Checks if tld is compatible with namecheaps transfer api and
     * if the domain is not available for registration (meaning hopefully the client owns it).
     *
     * @return bool
     *
     * @throws Registrar_Exception
     */
    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        return in_array($domain->getTld(), [
            '.biz', '.ca', '.cc', '.co', '.co.uk', '.com', '.com.es',
            '.com.pe', '.es', '.in', '.info', '.me', '.me.uk', '.mobi', '.net', '.net.pe',
            '.nom.es', '.org', '.org.es', '.org.pe', '.org.uk', '.pe', '.tv', '.us',
        ])
            && !$this->isDomainAvailable($domain);
    }
}
