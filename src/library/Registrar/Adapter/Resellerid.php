<?php

class Registrar_Adapter_Resellerid extends Registrar_Adapter_Resellerclub
{
    public function __construct($options)
    {
        if(isset($options['userid']) && !empty($options['userid'])) {
            $this->config['userid'] = $options['userid'];
            unset($options['userid']);
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'ResellerID', ':missing' => 'ResellerID Reseller ID']);
        }

        if(isset($options['api-key']) && !empty($options['api-key'])) {
            $this->config['api-key'] = $options['api-key'];
            unset($options['api-key']);
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'ResellerID', ':missing' => 'ResellerID API Key']);
        }
    }

    public static function getConfig()
    {
        return array(
            'label'     =>  'Manages domains on ResellerID via API. ResellerID requires your server IP in order to work. Login to the ResellerID control panel (the url will be in the email you received when you signed up with them) and then go to Settings > API and enter the IP address of the server where FOSSBilling is installed to authorize it for API access.',
            'form'  => array(
                'userid' => array('text', array(
                    'label' => 'Reseller ID. You can get this at ResellerID control panel Settings > Personal information > Primary profile > Reseller ID',
                    'description'=> 'ResellerID Reseller ID'
                ),
                ),
                'api-key' => array('password', array(
                    'label' => 'ResellerID API Key',
                    'description'=> 'You can get this at ResellerID control panel, go to Settings -> API',
                    'required' => false,
                ),
                ),
            ),
        );
    }
}