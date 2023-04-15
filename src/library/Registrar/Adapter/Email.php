<?php
/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This file may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

use Iodev\Whois\Factory;

class Registrar_Adapter_Email extends Registrar_AdapterAbstract
{
    protected $config;

    public function __construct($options)
    {
        if(isset($options['email']) && !empty($options['email'])) {
            $this->config['email'] = $options['email'];
            unset($options['email']);
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'Email', ':missing' => 'email']);
        }

        if(isset($options['use_whois'])) {
            $this->config['use_whois'] = (bool)$options['use_whois'];
        } else {
            $this->config['use_whois'] = false;
        }

        $this->config['from'] = $this->config['email'];
    }

    public static function getConfig()
    {
        return array(
            'label'     =>  'This registrar type sends notifications to the given email about domain management events. For example, when client registers a new domain an email with domain details will be sent to you. It is then your responsibility to register domain on real registrar.',
            'form'  => array(
                'email' => array('text', array(
                            'label' => 'Email address',
                            'description'=>'Email to send domain change notifications'
                    ),
                 ),
                'use_whois' => array('radio', array(
                            'multiOptions' => array('1'=>'Yes', '0'=>'No'),
                            'label' => 'Use WHOIS to check for domain availability',
                    ),
                 ),
            ),
        );
    }

    public function getTlds()
    {
        return array();
    }

    public function isDomainAvailable(Registrar_Domain $domain)
    {
        $this->getLog()->debug('Checking domain availability: ' . $domain->getName());

        if($this->config['use_whois']) {
            $whois = Factory::get()->createWhois();
            return $whois->isDomainAvailable($domain->getName());
        }
        throw new Registrar_Exception('Email registrar can not determine whether domain is available');
    }

    public function isDomaincanBeTransferred(Registrar_Domain $domain)
    {
        throw new Registrar_Exception('Email registrar can not determine whether domain can be transferred');
    }

    public function modifyNs(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Modify Name Servers';
        $params['content'] = 'A request to change domain nameservers has been received.';

        return $this->sendEmail($domain, $params);
    }

    public function transferDomain(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Transfer domain';
        $params['content'] = 'A request to transfer domain has been received.';

        return $this->sendEmail($domain, $params);
    }

    public function getDomainDetails(Registrar_Domain $domain)
    {
        return $domain;
    }

    public function deleteDomain(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Delete domain';
        $params['content'] = 'A request to delete domain has been received.';

        return $this->sendEmail($domain, $params);
    }

    public function registerDomain(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Register domain';
        $params['content'] = 'A request to register domain has been received.';

        return $this->sendEmail($domain, $params);
    }

    public function renewDomain(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Renew domain';
        $params['content'] = 'A request to renew domain has been received.';

        return $this->sendEmail($domain, $params);
    }

    public function modifyContact(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Modify Domain Contact';
        $params['content'] = 'A request to update domain contacts details has been received.';

        return $this->sendEmail($domain, $params);
    }

    public function enablePrivacyProtection(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Turn On Domain privacy protection';
        $params['content'] = 'A request to change domain privacy protection has been received.';

        return $this->sendEmail($domain, $params);
    }

    public function disablePrivacyProtection(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Turn Off Domain privacy protection';
        $params['content'] = 'A request to change domain privacy protection has been received.';

        return $this->sendEmail($domain, $params);
    }

    public function getEpp(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Request for Epp code was received';
        $params['content'] = 'A request for Domain Transfer code was received.';

        return $this->sendEmail($domain, $params);
    }

    public function lock(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Request to lock domain received';
        $params['content'] = 'A request to lock domain was received.';

        return $this->sendEmail($domain, $params);
    }

    public function unlock(Registrar_Domain $domain)
    {
        $params =array();
        $params['subject'] = 'Request to unlock domain received';
        $params['content'] = 'A request to unlock domain was received.';

        return $this->sendEmail($domain, $params);
    }

    private function sendEmail(Registrar_Domain $domain, array $params)
    {
        $c = $params['content'];
        $c .= PHP_EOL;
        $c .= PHP_EOL;
        $c .= 'Domain should be configured as follows:';
        $c .= PHP_EOL;
        $c .= PHP_EOL;
        $c .= $domain->__toString();

        $log = $this->getLog();
        if($this->_testMode) {
            $log->alert($params['subject'].PHP_EOL.PHP_EOL.$c);
            return true;
        }

        mail($this->config['email'], $params['subject'], $c);
        $log->info("Email sent: ".$params['subject']);
        return true;
    }
}
