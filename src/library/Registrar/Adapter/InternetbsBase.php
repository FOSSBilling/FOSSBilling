<?php

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * Abstract base class for Internetbs-compatible registrar adapters.
 * This class contains all the common functionality shared between
 * Internetbs and Moniker adapters.
 */
abstract class Registrar_Adapter_InternetbsBase extends Registrar_AdapterAbstract
{
    /**
     * @var bool
     */
    protected $_testMode = false;

    /**
     * @var array{apikey:string|null,password:string|null}
     */
    protected $config = [
        'apikey' => null,
        'password' => null,
    ];

    /**
     * Abstract methods that subclasses must implement
     */
    abstract protected function getBrandName(): string;
    abstract protected function getApiBaseUrl(): string;
    abstract protected function getTestApiBaseUrl(): string;
    abstract public function getTlds(): array;

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct($options)
    {
        if (isset($options['apikey']) && !empty($options['apikey'])) {
            $this->config['apikey'] = $options['apikey'];
            unset($options['apikey']);
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => $this->getBrandName(), ':missing' => $this->getBrandName() . ' API key'], 3001);
        }

        if (isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => $this->getBrandName(), ':missing' => $this->getBrandName() . ' API password'], 3001);
        }
    }

    /**
     * Return array with configuration.
     * This method should be overridden in concrete classes to provide brand-specific labels.
     *
     * @return array
     */
    public static function getConfig()
    {
        // This is a fallback implementation. Concrete classes should override this.
        return [
            'label' => 'Manages domains via API',
            'form' => [
                'apikey' => ['text', [
                    'label' => 'API key',
                    'description' => 'API key',
                ],
                ],
                'password' => ['password', [
                    'label' => 'API password',
                    'description' => 'API password',
                    'renderPassword' => true,
                ],
                ],
            ],
        ];
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $params = [
            'domain' => $domain->getName(),
        ];

        $result = $this->_process('/Domain/Check', $params);

        return $result['status'] == 'AVAILABLE';
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        $params = [
            'domain' => $domain->getName(),
        ];

        $result = $this->_process('/Domain/Check', $params);

        //return true if status is UNAVAILABLE
        //For not supported TLDs, the status will be 'FAILURE'
        return $result['status'] == 'UNAVAILABLE';
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $params = [
            'domain' => $domain->getName(),
        ];

        $nsList = [];
        $nsList[] = $domain->getNs1();
        $nsList[] = $domain->getNs2();
        $nsList[] = $domain->getNs3();
        $nsList[] = $domain->getNs4();

        $params['ns_list'] = implode(',', $nsList);

        $result = $this->_process('/Domain/Update', $params);

        return $result['status'] == 'SUCCESS';
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $params = [
            'domain' => $domain->getName(),
        ];

        // Set contact data
        foreach (['Registrant', 'Admin', 'Technical', 'Billing'] as $contactType) {
            $params[$contactType . '_Organization'] = $c->getCompany();
            $params[$contactType . '_FirstName'] = $c->getFirstName();
            $params[$contactType . '_LastName'] = $c->getLastName();
            $params[$contactType . '_Email'] = $c->getEmail();
            $params[$contactType . '_PhoneNumber'] = '+' . $c->getTelCc() . '.' . $c->getTel();
            $params[$contactType . '_Street'] = $c->getAddress1();
            $params[$contactType . '_Street2'] = $c->getAddress2();
            $params[$contactType . '_Street3'] = $c->getAddress3();
            $params[$contactType . '_City'] = $c->getCity();
            $params[$contactType . '_CountryCode'] = $c->getCountry();
            $params[$contactType . '_PostalCode'] = $c->getZip();
            $params[$contactType . '_Language'] = 'en';
        }

        $result = $this->_process('/Domain/Update', $params);

        return $result['status'] == 'SUCCESS';
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $params = [
            'domain' => $domain->getName(),
        ];

        // Set contact data
        foreach (['Registrant', 'Admin', 'Technical', 'Billing'] as $contactType) {
            $params[$contactType . '_Organization'] = $c->getCompany();
            $params[$contactType . '_FirstName'] = $c->getFirstName();
            $params[$contactType . '_LastName'] = $c->getLastName();
            $params[$contactType . '_Email'] = $c->getEmail();
            $params[$contactType . '_PhoneNumber'] = '+' . $c->getTelCc() . '.' . $c->getTel();
            $params[$contactType . '_Street'] = $c->getAddress1();
            $params[$contactType . '_Street2'] = $c->getAddress2();
            $params[$contactType . '_Street3'] = $c->getAddress3();
            $params[$contactType . '_City'] = $c->getCity();
            $params[$contactType . '_CountryCode'] = $c->getCountry();
            $params[$contactType . '_PostalCode'] = $c->getZip();
            $params[$contactType . '_Language'] = 'en';
        }

        $result = $this->_process('/Domain/Transfer/Initiate', $params);

        return $result['status'] == 'SUCCESS';
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        $params = [
            'domain' => $domain->getName(),
        ];

        $result = $this->_process('/Domain/Info', $params);

        if ($result['status'] == 'SUCCESS') {
            return $this->_createDomainObj($result, $domain);
        } else {
            $placeholders = [':action:' => __trans('get domain details'), ':type:' => $this->getBrandName()];

            throw new Registrar_Exception('Failed to :action: with the :type: registrar, check the error logs for further details', $placeholders);
        }
    }

    public function deleteDomain(Registrar_Domain $domain): never
    {
        throw new Registrar_Exception(':type: does not support :action:', [':type:' => $this->getBrandName(), ':action:' => __trans('deleting domains')]);
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $c = $domain->getContactRegistrar();

        $params = [
            'domain' => $domain->getName(),
            'period' => $domain->getRegistrationPeriod() . 'Y',
        ];

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
        $params['ns_list'] = implode(',', $nsList);

        // Set contact data
        foreach (['Registrant', 'Admin', 'Technical', 'Billing'] as $contactType) {
            $params[$contactType . '_Organization'] = $c->getCompany();
            $params[$contactType . '_FirstName'] = $c->getFirstName();
            $params[$contactType . '_LastName'] = $c->getLastName();
            $params[$contactType . '_Email'] = $c->getEmail();
            $params[$contactType . '_PhoneNumber'] = '+' . $c->getTelCc() . '.' . $c->getTel();
            $params[$contactType . '_Street'] = $c->getAddress1();
            $params[$contactType . '_Street2'] = $c->getAddress2();
            $params[$contactType . '_Street3'] = $c->getAddress3();
            $params[$contactType . '_City'] = $c->getCity();
            $params[$contactType . '_CountryCode'] = $c->getCountry();
            $params[$contactType . '_PostalCode'] = $c->getZip();
            $params[$contactType . '_Language'] = 'en';
        }

        $result = $this->_process('/Domain/Create', $params);

        return $result['status'] == 'SUCCESS';
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params = [
            'domain' => $domain->getName(),
            'period' => $domain->getRegistrationPeriod() . 'Y',
        ];

        $result = $this->_process('/Domain/Renew', $params);

        return $result['status'] == 'SUCCESS';
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $cmd = '/Domain/PrivateWhois/Enable';

        $params = [
            'domain' => $domain->getName(),
        ];

        $result = $this->_process($cmd, $params);

        return $result['status'] == 'SUCCESS';
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $cmd = '/Domain/PrivateWhois/Disable';

        $params = [
            'domain' => $domain->getName(),
        ];

        $result = $this->_process($cmd, $params);

        return $result['status'] == 'SUCCESS';
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $d = $this->getDomainDetails($domain);

        return $d->getEpp();
    }

    public function lock(Registrar_Domain $domain)
    {
        $cmd = '/Domain/RegistrarLock/Enable';
        $params = [
            'domain' => $domain->getName(),
        ];
        $result = $this->_process($cmd, $params);

        return $result['status'] == 'SUCCESS';
    }

    public function unlock(Registrar_Domain $domain)
    {
        $cmd = '/Domain/RegistrarLock/Disable';
        $params = [
            'domain' => $domain->getName(),
        ];
        $result = $this->_process($cmd, $params);

        return $result['status'] == 'SUCCESS';
    }

    /**
     * Runs an api command and returns parsed data.
     *
     * @param string $command
     *
     * @return array
     */
    private function _process($command, $params)
    {
        // Set authentication params
        $params['apikey'] = $this->config['apikey'];
        $params['password'] = $this->config['password'];

        $client = $this->getHttpClient()->withOptions([
            'verify_peer' => false,
            'verify_host' => false,
        ]);

        try {
            $response = $client->request('POST', $this->_getApiUrl() . $command, [
                'body' => $params,
            ]);
        } catch (HttpExceptionInterface $error) {
            $e = new Registrar_Exception("HttpClientException: {$error->getMessage()}.");
            $this->getLog()->err($e->getMessage());

            throw $e;
        }

        $data = $response->getContent();

        return $this->_parseResult($data);
    }

    /**
     * Parses data returned by request.
     *
     * @param string $data
     */
    private function _parseResult($data): array
    {
        $lines = explode("\n", $data);
        $result = [];

        foreach ($lines as $line) {
            [$varName, $value] = explode('=', $line);
            $result[strtolower(trim($varName))] = trim($value);
        }

        if (array_key_exists('status', $result)
            && ($result['status'] == 'FAILURE')) {
            throw new Registrar_Exception($result['message']);
        }

        if ($this->isTestEnv()) {
            error_log(print_r($result, 1));
        }

        return $result;
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
            return $this->getTestApiBaseUrl();
        }

        return $this->getApiBaseUrl();
    }

    /**
     * Creates domain object from received data array.
     *
     * @return Registrar_Domain
     */
    private function _createDomainObj($result, Registrar_Domain $domain)
    {
        $type = 'contacts_registrant_';
        $tel = explode('.', $result[$type . 'phonenumber']);
        $name = '';

        // domain specific
        if (array_key_exists($type . 'firstname', $result)) {
            $name = $result[$type . 'firstname'];
        }
        if (array_key_exists($type . 'lastname', $result)) {
            $name .= ' ' . $result[$type . 'lastname'];
        }

        if (!array_key_exists($type . 'organization', $result)) {
            $result[$type . 'organization'] = '';
        }
        if ($domain->getTld() == 'fr') {
            $name = $result[$type . 'dotfrcontactentityname'];
        }
        if ($domain->getTld() == 'it') {
            $result['transferauthinfo'] = '';
        }

        $c = new Registrar_Domain_Contact();
        $c->setName($name)
          ->setEmail($result[$type . 'email'])
          ->setCompany($result[$type . 'organization'])
          ->setTel($tel[1])
          ->setTelCc($tel[0])
          ->setAddress1($result[$type . 'street'])
          ->setAddress2($result[$type . 'street2'])
          ->setAddress3($result[$type . 'street3'])
          ->setCity($result[$type . 'city'])
          ->setCountry($result[$type . 'country'])
          ->setZip($result[$type . 'postalcode']);

        if (isset($result['nameserver_0'])) {
            $domain->setNs1($result['nameserver_0']);
        }

        if (isset($result['nameserver_1'])) {
            $domain->setNs2($result['nameserver_1']);
        }

        if (isset($result['nameserver_2'])) {
            $domain->setNs3($result['nameserver_2']);
        }

        if (isset($result['nameserver_3'])) {
            $domain->setNs4($result['nameserver_3']);
        }

        $privacy = 0;
        if (array_key_exists('privatewhois', $result)) {
            $privacy = ($result['privatewhois'] == 'FULL')
                        || ($result['privatewhois'] == 'PARTIAL');
        }

        $domain->setExpirationTime(strtotime($result['expirationdate']));
        $domain->setPrivacyEnabled($privacy);
        $domain->setEpp($result['transferauthinfo']);
        $domain->setContactRegistrar($c);

        return $domain;
    }

    /**
     * Checks whether privacy is enabled.
     *
     * @return bool
     */
    private function _isPrivacyEnabled(Registrar_Domain $domain)
    {
        $params = [
            'domain' => $domain->getName(),
        ];

        $result = $this->_process('/Domain/PrivateWhois/Status', $params);

        return ($result['status'] == 'SUCCESS')
                && (($result['privatewhoisstatus'] == 'FULL')
                || ($result['privatewhoisstatus'] == 'PARTIAL'));
    }
}