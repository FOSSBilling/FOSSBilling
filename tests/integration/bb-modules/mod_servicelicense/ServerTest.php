<?php

/**
 * @group Core
 */
class Box_Mod_Servicelicense_ServerTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'servicelicense.xml';

    public static function variations()
    {
        return array(
//            array(array(
//                'fail'       =>  '',
//            ), false),

            array(array(
                'license' => 'BOX-NOT-EXISTS',
                'host'    => 'tests.com',
                'path'    => dirname(__FILE__),
                'version' => '0.0.2',
            ), false, false),

            array(array(
                'license' => 'no_validation',
                'host'    => 'tests.com',
                'path'    => dirname(__FILE__),
                'version' => '0.0.2',
            ), false, false),
            array(array(
                'license' => 'valid',
                'host'    => 'www.tests.com',
                'path'    => dirname(__FILE__),
                'version' => '0.0.2',
            ), true, true),
        );
    }

    /**
     * @dataProvider variations
     */
    public function testLicenseServer($data, $valid, $validation)
    {
        $service = $this->getMockBuilder('Box\Mod\Servicelicense\Service')->getMock();
        $service->expects($this->any())
            ->method('isLicenseActive')
            ->will($this->returnValue(true));

        if ($validation) {
            $service->expects($this->atLeastOnce())
                ->method('isValidIp')
                ->will($this->returnValue(true));
            $service->expects($this->atLeastOnce())
                ->method('isValidHost')
                ->will($this->returnValue(true));
            $service->expects($this->atLeastOnce())
                ->method('isValidVersion')
                ->will($this->returnValue(true));
            $service->expects($this->atLeastOnce())
                ->method('isValidPath')
                ->will($this->returnValue(true));
        }

        $di                = new Box_Di();
        $di['db']          = $this->di['db'];
        $di['logger']      = new Box_Log();
        $di['mod']         = $di->protect(function () use ($service) {
                return new Box_Mod('servicelicense');
            });
        $di['mod_service'] = $di->protect(function () use ($service) {
                return $service;
            });

        $server = new \Box\Mod\Servicelicense\Server($di['logger']);
        $server->setDi($di);
        $result = $server->handle_deprecated(json_encode($data));
        $this->assertEquals($valid, $result['valid'], print_r($result, 1));
    }

    public function testLicenseServerProcessProvicer()
    {
        $this->assertTrue(true);

        return array(
            array(array(
                'license' => 'validation_fail',
                'host'    => 'tests.com',
                'path'    => dirname(__FILE__),
                'version' => '0.0.2',
            ), false),
            array(array(
                'license' => 'valid',
                'host'    => 'www.tests.com',
                'path'    => dirname(__FILE__),
                'version' => '0.0.2',
            ), true),
        );
    }

    public function testLicenseServerProcess()
    {
        $data = array(
            'license' => 'valid',
            'host'    => 'tests.com',
            'path'    => dirname(__FILE__),
            'version' => '0.0.2',
        );

        $valid = true;

        $service = $this->getMockBuilder('Box\Mod\Servicelicense\Service')->getMock();
        $service->expects($this->any())
            ->method('isLicenseActive')
            ->will($this->returnValue(true));
        $service->expects($this->atLeastOnce())
            ->method('isValidIp')
            ->will($this->returnValue(true));
        $service->expects($this->atLeastOnce())
            ->method('isValidHost')
            ->will($this->returnValue(true));
        $service->expects($this->atLeastOnce())
            ->method('isValidVersion')
            ->will($this->returnValue(true));
        $service->expects($this->atLeastOnce())
            ->method('isValidPath')
            ->will($this->returnValue(true));

        $di                = new Box_Di();
        $di['db']          = $this->di['db'];
        $di['logger']      = $this->di['logger'];
        $di['mod']         = $di->protect(function () use ($service) {
                return new Box_Mod('servicelicense');
            });
        $di['mod_service'] = $di->protect(function () use ($service) {
                return $service;
            });

        $server = new \Box\Mod\Servicelicense\Server($this->di['logger']);
        $server->setDi($di);

        $result = $server->process(json_encode($data));
        $this->assertEquals($valid, $result['valid'], print_r($result, 1));
    }


    /**
     * @expectedException LogicException
     */
    public function testLicenseServerProcessNotFound()
    {
        $data = array(
            'license' => 'non_existing',
            'host'    => 'tests.com',
            'path'    => dirname(__FILE__),
            'version' => '0.0.2',
        );

        $valid = true;

        $service = $this->getMockBuilder('Box\Mod\Servicelicense\Service')->getMock();
        $service->expects($this->never())
            ->method('isLicenseActive')
            ->will($this->returnValue(true));
        $service->expects($this->never())
            ->method('isValidIp')
            ->will($this->returnValue(true));
        $service->expects($this->never())
            ->method('isValidHost')
            ->will($this->returnValue(true));
        $service->expects($this->never())
            ->method('isValidVersion')
            ->will($this->returnValue(true));
        $service->expects($this->never())
            ->method('isValidPath')
            ->will($this->returnValue(true));

        $di                = new Box_Di();
        $di['db']          = $this->di['db'];
        $di['logger']      = $this->di['logger'];
        $di['mod']         = $di->protect(function () use ($service) {
                return new Box_Mod('servicelicense');
            });
        $di['mod_service'] = $di->protect(function () use ($service) {
                return $service;
            });

        $server = new \Box\Mod\Servicelicense\Server($this->di['logger']);
        $server->setDi($di);

        $result = $server->process(json_encode($data));
        $this->assertEquals($valid, $result['valid'], print_r($result, 1));
    }

    public function testLicenseServerProcessValidationFailProvider()
    {
        $this->assertTrue(true);

        return array(
            array(false, false, false, false, false, array(
                $this->atLeastOnce(), $this->never(), $this->never(), $this->never(), $this->never()
            )),
            array(true, false, false, false, false, array(
                $this->atLeastOnce(), $this->atLeastOnce(), $this->never(), $this->never(), $this->never()
            )),
            array(true, true, false, false, false, array(
                $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->never(), $this->never()
            )),
            array(true, true, true, false, false, array(
                $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->never()
            )),
            array(true, true, true, true, false, array(
                $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce()
            )),

        );
    }


    /**
     * @expectedException LogicException
     * @dataProvider testLicenseServerProcessValidationFailProvider
     */
    public function testLicenseServerProcessValidationFail($isActive, $validIp, $validHost, $validVersion, $validPath, $called)
    {
        $data = array(
            'license' => 'valid',
            'host'    => 'tests.com',
            'path'    => dirname(__FILE__),
            'version' => '0.0.2',
        );

        $valid = true;

        $service = $this->getMockBuilder('Box\Mod\Servicelicense\Service')->getMock();
        $service->expects($called[0])
            ->method('isLicenseActive')
            ->will($this->returnValue($isActive));
        $service->expects($called[1])
            ->method('isValidIp')
            ->will($this->returnValue($validIp));
        $service->expects($called[2])
            ->method('isValidHost')
            ->will($this->returnValue($validHost));
        $service->expects($called[3])
            ->method('isValidVersion')
            ->will($this->returnValue($validVersion));
        $service->expects($called[4])
            ->method('isValidPath')
            ->will($this->returnValue($validPath));

        $di                = new Box_Di();
        $di['db']          = $this->di['db'];
        $di['logger']      = $this->di['logger'];
        $di['mod']         = $di->protect(function () use ($service) {
                return new Box_Mod('servicelicense');
            });
        $di['mod_service'] = $di->protect(function () use ($service) {
                return $service;
            });

        $server = new \Box\Mod\Servicelicense\Server($this->di['logger']);
        $server->setDi($di);

        $result = $server->process(json_encode($data));
        $this->assertEquals($valid, $result['valid'], print_r($result, 1));
    }

    /*
    public function testLicense()
    {
        return ;
        
        $data = array();
        $data['license']    = 'BOX-NOT-EXISTS';
        $data['host']       = 'tests.com';
        $data['path']       = dirname(__FILE__);
        $data['version']    = '0.0.2';

        $k = base64_encode(json_encode($data));
        $url = 'http://www.box.local/licenses';
        $params = 'key='.$k;

        
        $process = curl_init($url);
        $params = trim( preg_replace( '/\s+/', '', $params ) );
        curl_setopt($process, CURLOPT_HEADER, 0);
        curl_setopt($process, CURLOPT_TIMEOUT, 30);
        curl_setopt($process, CURLOPT_POSTFIELDS, $params);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off'))
            curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($process, CURLOPT_POST, 1);
        $response = curl_exec($process);
        curl_close($process);
        

        $json= json_decode($response);
        $this->assertTrue(is_object($json));
        $this->assertEquals('License key not found', $json->error);
    }
    */
}
