<?php

class Registrar_Adapter_Netearthone extends Registrar_Adapter_Resellerclub
{
    public function __construct($options)
    {
        if (isset($options['userid']) && !empty($options['userid'])) {
            $this->config['userid'] = $options['userid'];
            unset($options['userid']);
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'NetEarthOne', ':missing' => 'NetEarthOne Reseller ID'], 3001);
        }

        if (isset($options['api-key']) && !empty($options['api-key'])) {
            $this->config['api-key'] = $options['api-key'];
            unset($options['api-key']);
        } else {
            throw new Registrar_Exception('The ":domain_registrar" domain registrar is not fully configured. Please configure the :missing', [':domain_registrar' => 'NetEarthOne', ':missing' => 'NetEarthOne API Key'], 3001);
        }
    }

    public static function getConfig()
    {
        return [
            'label' => 'Manages domains on NetEarthOne via API. NetEarthOne requires your server IP in order to work. Login to the NetEarthOne control panel (the url will be in the email you received when you signed up with them) and then go to Settings > API and enter the IP address of the server where FOSSBilling is installed to authorize it for API access.',
            'form' => [
                'userid' => ['text', [
                    'label' => 'Reseller ID. You can get this at NetEarthOne control panel Settings > Personal information > Primary profile > Reseller ID',
                    'description' => 'NetEarthOne Reseller ID',
                ],
                ],
                'api-key' => ['password', [
                    'label' => 'NetEarthOne API Key',
                    'description' => 'You can get this at NetEarthOne control panel, go to Settings -> API',
                    'required' => false,
                ],
                ],
            ],
        ];
    }
}
