<?php

class Registrar_Adapter_Netearthone extends Registrar_Adapter_Resellerclub
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
            throw new Registrar_Exception('Domain registrar "NetEarthOne" is not configured properly. Please update configuration parameter "NetEarthOne Username" at "Configuration -> Domain registration".');
        }

        if(isset($options['password']) && !empty($options['password'])) {
            $this->config['password'] = $options['password'];
            unset($options['password']);
        } else {
            throw new Registrar_Exception('Domain registrar "NetEarthOne" is not configured properly. Please update configuration parameter "NetEarthOne Pasword" at "Configuration -> Domain registration".');
        }
    }
    
    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on NetEarthOne via API',
            'form'  => array(
                'userid' => array('text', array(
                            'label' => 'NetEarthOne Username', 
                            'description'=>'NetEarthOne Username'
                        ),
                     ),
                'password' => array('password', array(
                            'label' => 'NetEarthOne Pasword', 
                            'description'=>'NetEarthOne Password',
                            'renderPassword'    =>  true, 
                        ),
                     ),
            ),
        );
    }
}