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
            throw new Registrar_Exception('Domain registrar "NetEarthOne" is not configured properly. Please update configuration parameter "NetEarthOne Reseller ID" at "Configuration -> Domain registration".');
        }

        if(isset($options['api-key']) && !empty($options['api-key'])) {
            $this->config['api-key'] = $options['api-key'];
            unset($options['api-key']);
        } else {
            throw new Registrar_Exception('Domain registrar "NetEarthOne" is not configured properly. Please update configuration parameter "NetEarthOne API Key" at "Configuration -> Domain registration".');
        }
    }

    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on NetEarthOne via API. NetEarthOne requires your server IP in order to work. Login to the NetEarthOne control panel (the url will be in the email you received when you signed up with them) and then go to Settings > API and enter the IP address of the server where BoxBilling is installed to authorize it for API access.',
            'form'  => array(
                'userid' => array('text', array(
                    'label' => 'Reseller ID. You can get this at NetEarthOne control panel Settings > Personal information > Primary profile > Reseller ID',
                    'description'=> 'NetEarthOne Reseller ID'
                ),
                ),
                'api-key' => array('password', array(
                    'label' => 'NetEarthOne API Key',
                    'description'=> 'You can get this at NetEarthOne control panel, go to Settings -> API',
                    'required' => false,
                ),
                ),
            ),
        );
    }
}