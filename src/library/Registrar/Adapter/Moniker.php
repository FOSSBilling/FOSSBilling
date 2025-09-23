<?php

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

class Registrar_Adapter_Moniker extends Registrar_Adapter_InternetbsBase
{
    /**
     * @var string
     */
    private static $brand = 'Moniker';

    protected function getBrandName(): string
    {
        return self::$brand;
    }

    protected function getApiBaseUrl(): string
    {
        return 'https://api.moniker.com';
    }

    protected function getTestApiBaseUrl(): string
    {
        return 'https://testapi.moniker.com';
    }

    public static function getConfig()
    {
        return [
            'label' => 'Manages domains on ' . self::$brand . ' via API',
            'form' => [
                'apikey' => ['text', [
                    'label' => self::$brand . ' API key',
                    'description' => self::$brand . ' API key',
                ],
                ],
                'password' => ['password', [
                    'label' => self::$brand . ' API password',
                    'description' => self::$brand . ' API password',
                    'renderPassword' => true,
                ],
                ],
            ],
        ];
    }

    public function getTlds()
    {
        return [
            '.co', '.com', '.net', '.eu',
            '.org', '.it', '.fr', '.info',
            '.tel', '.us', '.biz', '.co.uk',
            '.in', '.mobi', '.asia', '.tv',
            '.re', '.be', '.cc', '.com.fr',
            '.com.re', '.org.uk', '.me.uk', '.com.co',
            '.net.co', '.nom.co', '.co.in', '.net.in',
            '.org.in', '.firm.in', '.gen.in', '.ind.in',
        ];
    }
}