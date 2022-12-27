<?php

use GeoIp2\Model\Domain;

class Registrar_Adapter_Namecheap extends Registrar_AdapterAbstract
{
    public $config = array(
        'api-user-id' => null,
        'api-key' => null,
        'username' => null,
        'ip' => null,
    );

    public function isKeyValueNotEmpty($array, $key)
    {
        $value = isset($array[$key]) ? $array[$key] : '';
        if (strlen(trim($value)) == 0) {
            return false;
        }
        return true;
    }

    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }

        if (isset($options['api-user-id']) && !empty($options['api-user-id'])) {
            $this->config['api-user-id'] = $options['api-user-id'];
            unset($options['api-user-id']);
        } else {
            throw new Registrar_Exception('Domain registrar "Namecheap" is not configured properly. Please update configuration parameter "Reseller ID" at "Configuration -> Domain registration".');
        }

        if (isset($options['api-key']) && !empty($options['api-key'])) {
            $this->config['api-key'] = $options['api-key'];
            unset($options['api-key']);
        } else {
            throw new Registrar_Exception('Domain registrar "Namecheap" is not configured properly. Please update configuration parameter "API Key" at "Configuration -> Domain registration".');
        }

        if (isset($options['username']) && !empty($options['username'])) {
            $this->config['username'] = $options['username'];
            unset($options['username']);
        } else {
            throw new Registrar_Exception('Domain registrar "Namecheap" is not configured properly. Please update configuration parameter "Username" at "Configuration -> Domain registration".');
        }

        if (isset($options['ip']) && !empty($options['ip'])) {
            $this->config['ip'] = $options['ip'];
            unset($options['ip']);
        } else {
            throw new Registrar_Exception('Domain registrar "Namecheap" is not configured properly. Please update configuration parameter "Server IP Address" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label' =>  'Manages domains on Namecheap via API. Namecheap requires your server IP in order to work.',
            'form'  => array(
                'api-user-id' => array(
                    'text', array(
                        'label' => 'Reseller ID',
                        'description' => 'Namecheap Reseller ID. You can get this at Namecheap control panel'
                    ),
                ),
                'api-key' => array(
                    'password', array(
                        'label' => 'API Key',
                        'description' => 'You can get this at Namecheap control panel',
                        'required' => false,
                    ),
                ),
                'username' => array(
                    'text', array(
                        'label' => 'Namecheap UserName',
                        'description' => 'You can get this at Namecheap control panel',
                        'required' => false,
                    ),
                ),
                'ip' => array(
                    'text', array(
                        'label' => 'Server IP address',
                        'description' => 'IP address of this server. Ensure that this IP is whitelisted under your NameCheap settings',
                        'required' => false,
                    ),
                ),
            ),
        );
    }

    /**
     * Tells what TLDs can be registered via this adapter
     * @return string[]
     */
    public function getTlds()
    {
        $params = array(
            'Command' => 'namecheap.domains.getTldList'
        );

        $result = $this->_makeRequest($params);
        $strTlds = array();
        $xmlTlds = $result->CommandResponse->Tlds;
        foreach ($xmlTlds->tld as $tld) {
            array_push($strTlds, '.' . $tld['Name']);
        }
        return $strTlds;
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = array(
            'DomainList' => strtolower($domain->getSld()) . $domain->getTld(),
            'Command' => 'namecheap.domains.check'
        );

        $result = $this->_makeRequest($params);

        error_log('AVAILABLE ATTR: ' . $result->CommandResponse->DomainCheckResult['Available']);

        if (isset($result->CommandResponse->DomainCheckResult['Available']) && $result->CommandResponse->DomainCheckResult['Available'] == 'true') {
            error_log('DOMAIN AVAILABLE: true');
            return true;
        }
        error_log('DOMAIN AVAILABLE: false');
        return false;
    }

    /**
     * Perform call to Api
     * @param array $params
     * @param string $method
     * @return object
     * @throws Registrar_Exception
     */
    protected function _makeRequest($params = array(), $method = 'GET')
    {
        $params = $this->includeAuthorizationParams($params);

        $opts = array(
            CURLOPT_CONNECTTIMEOUT  => 30,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 60,
            CURLOPT_URL             => $this->_getApiUrl(),
            CURLOPT_SSL_VERIFYHOST  =>  0,
            CURLOPT_SSL_VERIFYPEER  =>  0,
        );

        if ($method == 'POST') {
            $opts[CURLOPT_POST]         = 1;
            $opts[CURLOPT_POSTFIELDS]   = $this->_formatParams($params);
            $this->getLog()->debug('API REQUEST: ' . $opts[CURLOPT_URL] . '?' . $opts[CURLOPT_POSTFIELDS]);
        } else {
            $opts[CURLOPT_URL]  = $opts[CURLOPT_URL] . '?' . $this->_formatParams($params);
            $this->getLog()->debug('API REQUEST: ' . $opts[CURLOPT_URL]);
        }

        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        $data = curl_exec($ch);
        if ($data === false) {
            $e = new Registrar_Exception(sprintf('CurlException: "%s"', curl_error($ch)));
            $this->getLog()->err($e);
            curl_close($ch);
            throw $e;
        }
        curl_close($ch);

        $this->getLog()->info('API RESULT: ' . $data);

        error_log('API RESULT: ' . $data);

        $result = simplexml_load_string($data);

        // error_log('RESULT ARRAY 1: '.implode("\n", $result));

        if (isset($result['status']) && $result['status'] == 'error') {
            throw new Registrar_Exception($result['error'], 102);
        }

        if (isset($result['status']) && $result['status'] == 'Failed') {
            throw new Registrar_Exception($result['actionstatusdesc'], 103);
        }

        return $result;
    }

    /**
     * Convert params to resellerClub format
     * @see http://manage.resellerclub.com/kb/answer/755
     * @param array $params
     * @return string
     */
    private function _formatParams($params)
    {
        foreach ($params as $key => &$param) {
            if (is_bool($param)) {
                $param = ($param) ? 'true' : 'false';
            }
        }

        $params = http_build_query($params);
        return $params;
    }

    /**
     * Api URL
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
     * @param array $params
     * @return array
     */
    public function includeAuthorizationParams(array $params)
    {
        return array_merge(array(
            'ApiUser' => $this->config['api-user-id'],
            'ApiKey' => $this->config['api-key'],
            'UserName' => $this->config['username'],
            'ClientIp' => $this->config['ip'],
        ), $params);
    }


    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyNs(Registrar_Domain $domain)
    {
        // get NS info
        $params = array(
            'SLD' => $domain->getSld(),
            'TLD' => $domain->getTld($with_dot = false),
            'NameServers' => $domain->getNs1() . ',' . $domain->getNs2() . ',' . $domain->getNs3() . ',' . $domain->getNs4(),
            'Command' => 'namecheap.domains.dns.setCustom',
        );

        $result = $this->_makeRequest($params);

        error_log('AFTER NS MODIFY: ' . $domain->__toString());

        // TODO
        // problem here: fossbilling doesn't display our error and will display 'nameservers updates' evern when this fails

        if (!isset($result->CommandResponse->DomainDNSSetCustomResult['Updated']) && $result->CommandResponse->DomainDNSSetCustomResult['Updated'] != 'true') {
            throw new Registrar_Exception($message = 'Could not update NameServers');
        }
        return True;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function modifyContact(Registrar_Domain $domain)
    {
        $params = array(
            'Command' => 'namecheap.domains.setContacts',
            'DomainName' => strtolower($domain->getSld()) . $domain->getTld(),
        );

        // Set contact data
        foreach (array('Registrant', 'Admin', 'Tech', 'AuxBilling') as $contactType) {
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

            $params[$contactType . 'FirstName']        = $c->getFirstName();
            $params[$contactType . 'LastName']         = $c->getLastName();
            $params[$contactType . 'EmailAddress']     = $c->getEmail();
            $params[$contactType . 'Phone']            = '+' . $c->getTelCc() . '.' . $c->getTel();
            $params[$contactType . 'Address1']         = $c->getAddress1();
            $params[$contactType . 'Address2']         = $c->getAddress2();
            $params[$contactType . 'City']             = $c->getCity();
            $params[$contactType . 'StateProvince']    = $c->getState();
            $params[$contactType . 'Country']          = $c->getCountry();
            $params[$contactType . 'PostalCode']       = $c->getZip();
        }
        $result = $this->_makeRequest($params);
        return $result->CommandResponse->DomainSetContactResult['IsSuccess'] == 'true';
    }

    /**
     * relying on domain->getEpp() to return user's input for Epp code
     * @return bool
     * @throws Registrar_Exception
     */
    public function transferDomain(Registrar_Domain $domain)
    {
        $params = array(
            'DomainName' => $domain->getName(),
            'Years' => '1',
            'EPPCode' => $domain->getEpp(),
            'Command' => 'namecheap.domains.transfer.create'
        );

        $result = $this->_makeRequest($params);

        error_log('TRANSFER ATTR: ' . $result->CommandResponse->DomainTransferCreateResult['Transfer']);

        if (isset($result->CommandResponse->DomainTransferCreateResult['Transfer']) && $result->CommandResponse->DomainTransferCreateResult['Transfer'] == 'true') {
            error_log('DOMAIN TRANSFER: true');
            return true;
        }
        error_log('DOMAIN TRANSFER: false');
        return false;
    }

    /**
     * Should return details of registered domain
     * If domain is not registered should throw Registrar_Exception
     * @return Registrar_Domain
     * @throws Registrar_Exception
     */
    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = array(
            'DomainName' => $domain->getName(),
            'Command' => 'namecheap.domains.getInfo'
        );

        $result = $this->_makeRequest($params);

        $domain->setRegistrationTime((string)$result->CommandResponse->DomainGetInfoResult->DomainDetails->CreatedDate);
        $domain->setExpirationTime((string)$result->CommandResponse->DomainGetInfoResult->DomainDetails->ExpiredDate);
        $domain->setPrivacyEnabled((string)$result->CommandResponse->DomainGetInfoResult->Whoisguard['Enabled']);

        $params = array(
            'DomainName' => $domain->getName(),
            'Command' => 'namecheap.domains.getContacts'
        );

        $result = $this->_makeRequest($params);

        error_log('INFO ATTR: ' . (string)$result->CommandResponse->DomainContactsResult);

        if (!isset($result->CommandResponse->DomainContactsResult)) {
            throw new Registrar_Exception($message = "API ERROR: could not retrieve domain details");
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
        foreach (array('Registrant', 'Admin', 'Tech', 'AuxBilling') as $contactType) {
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

            $contact->setFirstName((string)$contactApi->FirstName);
            $contact->setLastName((string)$contactApi->LastName);
            $contact->setEmail((string)$contactApi->EmailAddress);
            $contact->setTel((string)$contactApi->Phone);
            $contact->setAddress1((string)$contactApi->Address1);
            $contact->setAddress2((string)$contactApi->Address2);
            $contact->setCity((string)$contactApi->City);
            $contact->setState((string)$contactApi->StateProvince);
            $contact->setCountry((string)$contactApi->Country);
            $contact->setZip((string)$contactApi->PostalCode);
        }

        $newDomain->setContactRegistrar($registrarContact);
        $newDomain->setContactAdmin($adminContact);
        $newDomain->setContactTech($techContact);
        $newDomain->setContactBilling($billingContact);

        // get NS info
        $params = array(
            'SLD' => $newDomain->getSld(),
            'TLD' => $newDomain->getTld($with_dot = false),
            'Command' => 'namecheap.domains.dns.getList',
        );

        $result = $this->_makeRequest($params);

        $NsArr = array();
        $xmlNsList = $result->CommandResponse->DomainDNSGetListResult;
        foreach ($xmlNsList->Nameserver as $Nameserver) {
            $NsArr[] = $Nameserver;
        }

        for ($i = 1; $i <= 4; $i++) {
            if (isset($NsArr[$i - 1])) {
                $newDomain->{'setNs' . $i}((string)$NsArr[$i - 1]);
            }
        }

        error_log('DOMAIN OBJ FIELDS: ' . $newDomain->__toString());

        return $newDomain;
    }


    /**
     * Should return domain transfer code
     *
     * @return string
     * @throws Registrar_Exception
     */
    public function getEpp(Registrar_Domain $domain)
    {
        throw new Registrar_Exception($message = 'This feature is unavailable through the Namecheap API. Please contact your administrator.');
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function registerDomain(Registrar_Domain $domain)
    {

        $params = array(
            'Command' => 'namecheap.domains.create',
            'DomainName' => strtolower($domain->getSld()) . $domain->getTld(),
            'Years' => $domain->getRegistrationPeriod(),
        );

        if ($domain->getNs1()) {
            // Add nameservers
            $nsList = array();
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
        foreach (array('Registrant', 'Admin', 'Tech', 'AuxBilling') as $contactType) {
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

            $params[$contactType . 'FirstName']        = $c->getFirstName();
            $params[$contactType . 'LastName']         = $c->getLastName();
            $params[$contactType . 'EmailAddress']     = $c->getEmail();
            $params[$contactType . 'Phone']            = '+' . $c->getTelCc() . '.' . $c->getTel();
            $params[$contactType . 'Address1']         = $c->getAddress1();
            $params[$contactType . 'Address2']         = $c->getAddress2();
            $params[$contactType . 'City']             = $c->getCity();
            $params[$contactType . 'StateProvince']    = $c->getState();
            $params[$contactType . 'Country']          = $c->getCountry();
            $params[$contactType . 'PostalCode']       = $c->getZip();
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

        error_log('REGISTER API PARAMS: ' . implode("\n", $params));

        $result = $this->_makeRequest($params);

        error_log('REGISTER API RESULT: ' . $result);

        return (isset($result->CommandResponse->DomainCreateResult['Registered'])
            && ($result->CommandResponse->DomainCreateResult['Registered'] == 'true'));
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function renewDomain(Registrar_Domain $domain)
    {
        $params = array(
            'Command' => 'namecheap.domains.renew',
            'DomainName' => $domain->getName(),
            'Years' => $domain->getRegistrationPeriod(),
        );

        $result = $this->_makeRequest($params);

        return (isset($result->CommandResponse->DomainRenewResult['Renew'])
            && ($result->CommandResponse->DomainRenewResult['Renew'] == 'true'));
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function deleteDomain(Registrar_Domain $domain)
    {
    }

    /**
     * @return string[]
     * @throws Registrar_Exception
     */
    private function getPrivacyInfo(Registrar_Domain $domain)
    {
        $params = array(
            'DomainName' => $domain->getName(),
            'Command' => 'namecheap.domains.getInfo'
        );

        $result = $this->_makeRequest($params);

        if (isset($result->CommandResponse->DomainGetInfoResult->Whoisguard)) {
            return array(
                    'enabled' => $result->CommandResponse->DomainGetInfoResult->Whoisguard['Enabled'] == 'True', 
                    'id' => (string)$result->CommandResponse->DomainGetInfoResult->Whoisguard->ID
                );
        }
        throw new Registrar_Exception($message = 'Could not retrieve privacy info on this domain.');
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $privacyInfo = $this->getPrivacyInfo($domain);

        if($privacyInfo['enabled']) {
            return True;
        }

        $params = array(
            'WhoisguardID' => $privacyInfo['id'],
            'ForwardedToEmail' => $domain->getContactRegistrar()->getEmail(),
            'Command' => 'namecheap.whoisguard.enable'
        );

        $result = $this->_makeRequest($params);

        return isset($result->CommandResponse->WhoisguardEnableResult['isSuccess']) 
            && $result->CommandResponse->WhoisguardEnableResult['isSuccess'] == 'true';
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $privacyInfo = $this->getPrivacyInfo($domain);

        if(!$privacyInfo['enabled']) {
            return True;
        }

        $params = array(
            'WhoisguardID' => $privacyInfo['id'],
            'Command' => 'namecheap.whoisguard.disable'
        );

        $result = $this->_makeRequest($params);

        return isset($result->CommandResponse->WhoisguardDisableResult['isSuccess']) 
            && $result->CommandResponse->WhoisguardDisableResult['isSuccess'] == 'true';
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function getLock(Registrar_Domain $domain)
    {
        $params = array(
            'DomainName' => $domain->getName(),
            'Command' => 'namecheap.domains.getRegistrarLock'
        );
        $result = $this->_makeRequest($params);
        if (!isset($result->CommandResponse->DomainGetRegistrarLockResult['RegistrarLockStatus'])) {
            throw new Registrar_Exception($message = 'API ERROR: could not get lock status on domain');
        }
        return $result->CommandResponse->DomainGetRegistrarLockResult['RegistrarLockStatus'] == 'true';
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function lock(Registrar_Domain $domain)
    {
        if ($this->getLock($domain) == 'true') {
            return True;
        }

        $params = array(
            'DomainName' => $domain->getName(),
            'LockAction' => 'LOCK',
            'Command' => 'namecheap.domains.setRegistrarLock'
        );

        $result = $this->_makeRequest($params);
        if (!isset($result->CommandResponse->DomainSetRegistrarLockResult['IsSuccess'])) {
            throw new Registrar_Exception($message = 'API ERROR: could not set lock status on domain');
        }
        if ($result->CommandResponse->DomainGetRegistrarLockResult['IsSuccess'] == 'true') {
            $domain->setLocked('true');
            return True;
        }
        return False;
    }

    /**
     * @return bool
     * @throws Registrar_Exception
     */
    public function unlock(Registrar_Domain $domain)
    {
        if ($this->getLock($domain) == 'false') {
            return True;
        }

        $params = array(
            'DomainName' => $domain->getName(),
            'LockAction' => 'UNLOCK',
            'Command' => 'namecheap.domains.setRegistrarLock'
        );

        $result = $this->_makeRequest($params);
        if (!isset($result->CommandResponse->DomainSetRegistrarLockResult['IsSuccess'])) {
            throw new Registrar_Exception($message = 'API ERROR: could not set lock status on domain');
        }
        if ($result->CommandResponse->DomainGetRegistrarLockResult['IsSuccess'] == 'true') {
            $domain->setLocked('false');
            return True;
        }
        return False;
    }

    /**
     * Checks if tld is compatible with namecheaps transfer api and
     * if the domain is not available for registration (meaning hopefully the client owns it)
     * @return bool
     * @throws Registrar_Exception
     */
    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        return in_array($domain->getTld(), array(
            '.biz', '.ca', '.cc', '.co', '.co.uk', '.com', '.com.es',
            '.com.pe', '.es', '.in', '.info', '.me', '.me.uk', '.mobi', '.net', '.net.pe',
            '.nom.es', '.org', '.org.es', '.org.pe', '.org.uk', '.pe', '.tv', '.us'
        ))
            && !$this->isDomainAvailable($domain);
    }
}
