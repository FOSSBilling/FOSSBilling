<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Api_Guest_ServiceLicenseTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'licensing-server.xml';

    public static function variations()
    {
        return [
            [[
                'fail' => '',
                'legacy' => 1,
            ], false],

            [[
                'license' => 'BOX-NOT-EXISTS',
                'host' => 'tests.com',
                'path' => __DIR__,
                'version' => '0.0.2',
                'legacy' => 1,
            ], false],

            [[
                'license' => 'no_validation',
                'host' => 'tests.com',
                'path' => __DIR__,
                'version' => '0.0.2',
                'legacy' => 1,
            ], false],

            [[
                'license' => 'valid',
                'host' => 'www.tests.com',
                'path' => __DIR__,
                'version' => '0.0.2',
                'legacy' => 1,
            ], true],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('variations')]
    public function testGuestServiceLicense($data, $valid): void
    {
        $result = $this->api_guest->servicelicense_check($data);
        $this->assertEquals($valid, $result['valid']);
    }
}
