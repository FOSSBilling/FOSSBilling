<?php

class Registrar_Adapter_Resellerid extends Registrar_Adapter_Resellerclub
{
    public function __construct($options)
    {
        if (!extension_loaded('curl')) {
            throw new Registrar_Exception('CURL extension is not enabled');
        }

        if(isset($options['userid']) && !empty($options['userid'])) {
            $this->config['userid'] = $options['userid'];
            unset($options['userid']);
        } else {
            throw new Registrar_Exception('Domain registrar "ResellerID" is not configured properly. Please update configuration parameter "ResellerID Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "ResellerID" is not configured properly. Please update configuration parameter "ResellerID Pasword" at "Configuration -> Domain registration".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on ResellerID via API',
            'form'  => array(
                'userid' => array('text', array(
                            'label' => 'Reseller ID',
                        ),
                     ),
                'password' => array('password', array(
                            'label' => 'Reseller Pasword',
                        ),
                     ),
            ),
        );
    }
}