<?php

#[PHPUnit\Framework\Attributes\Group('Core')]
class Box_Mod_Servicelicense_ServerTest extends BBDbApiTestCase
{
    protected $_initialSeedFile = 'servicelicense.xml';

    public static function variations()
    {
        return [
            //            array(array(
            //                'fail'       =>  '',
            //            ), false),

            [[
                'license' => 'BOX-NOT-EXISTS',
                'host' => 'tests.com',
                'path' => __DIR__,
                'version' => '0.0.2',
            ], false, false],

            [[
                'license' => 'no_validation',
                'host' => 'tests.com',
                'path' => __DIR__,
                'version' => '0.0.2',
            ], false, false],
            [[
                'license' => 'valid',
                'host' => 'www.tests.com',
                'path' => __DIR__,
                'version' => '0.0.2',
            ], true, true],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('variations')]
    public function testLicenseServer($data, $valid, $validation): void
    {
        $service = $this->getMockBuilder(Box\Mod\Servicelicense\Service::class)->getMock();
        $service->expects($this->any())
            ->method('isLicenseActive')
            ->willReturn(true);

        if ($validation) {
            $service->expects($this->atLeastOnce())
                ->method('isValidIp')
                ->willReturn(true);
            $service->expects($this->atLeastOnce())
                ->method('isValidHost')
                ->willReturn(true);
            $service->expects($this->atLeastOnce())
                ->method('isValidVersion')
                ->willReturn(true);
            $service->expects($this->atLeastOnce())
                ->method('isValidPath')
                ->willReturn(true);
        }

        $di = new Pimple\Container();
        $di['db'] = $this->di['db'];
        $di['logger'] = new Box_Log();
        $di['mod'] = $di->protect(fn () => new Box_Mod('servicelicense'));
        $di['mod_service'] = $di->protect(fn () => $service);

        $server = new Box\Mod\Servicelicense\Server($di['logger']);
        $server->setDi($di);
        $result = $server->handle_deprecated(json_encode($data));
        $this->assertEquals($valid, $result['valid'], print_r($result, 1));
    }

    public function testLicenseServerProcessProvicer()
    {
        $this->assertTrue(true);

        return [
            [[
                'license' => 'validation_fail',
                'host' => 'tests.com',
                'path' => __DIR__,
                'version' => '0.0.2',
            ], false],
            [[
                'license' => 'valid',
                'host' => 'www.tests.com',
                'path' => __DIR__,
                'version' => '0.0.2',
            ], true],
        ];
    }

    public function testLicenseServerProcess(): void
    {
        $data = [
            'license' => 'valid',
            'host' => 'tests.com',
            'path' => __DIR__,
            'version' => '0.0.2',
        ];

        $valid = true;

        $service = $this->getMockBuilder(Box\Mod\Servicelicense\Service::class)->getMock();
        $service->expects($this->any())
            ->method('isLicenseActive')
            ->willReturn(true);
        $service->expects($this->atLeastOnce())
            ->method('isValidIp')
            ->willReturn(true);
        $service->expects($this->atLeastOnce())
            ->method('isValidHost')
            ->willReturn(true);
        $service->expects($this->atLeastOnce())
            ->method('isValidVersion')
            ->willReturn(true);
        $service->expects($this->atLeastOnce())
            ->method('isValidPath')
            ->willReturn(true);

        $di = new Pimple\Container();
        $di['db'] = $this->di['db'];
        $di['logger'] = $this->di['logger'];
        $di['mod'] = $di->protect(fn () => new Box_Mod('servicelicense'));
        $di['mod_service'] = $di->protect(fn () => $service);

        $server = new Box\Mod\Servicelicense\Server($this->di['logger']);
        $server->setDi($di);

        $result = $server->process(json_encode($data));
        $this->assertEquals($valid, $result['valid'], print_r($result, 1));
    }

    /**
     * @expectedException \LogicException
     */
    public function testLicenseServerProcessNotFound(): void
    {
        $data = [
            'license' => 'non_existing',
            'host' => 'tests.com',
            'path' => __DIR__,
            'version' => '0.0.2',
        ];

        $valid = true;

        $service = $this->getMockBuilder(Box\Mod\Servicelicense\Service::class)->getMock();
        $service->expects($this->never())
            ->method('isLicenseActive')
            ->willReturn(true);
        $service->expects($this->never())
            ->method('isValidIp')
            ->willReturn(true);
        $service->expects($this->never())
            ->method('isValidHost')
            ->willReturn(true);
        $service->expects($this->never())
            ->method('isValidVersion')
            ->willReturn(true);
        $service->expects($this->never())
            ->method('isValidPath')
            ->willReturn(true);

        $di = new Pimple\Container();
        $di['db'] = $this->di['db'];
        $di['logger'] = $this->di['logger'];
        $di['mod'] = $di->protect(fn () => new Box_Mod('servicelicense'));
        $di['mod_service'] = $di->protect(fn () => $service);

        $server = new Box\Mod\Servicelicense\Server($this->di['logger']);
        $server->setDi($di);

        $result = $server->process(json_encode($data));
        $this->assertEquals($valid, $result['valid'], print_r($result, 1));
    }

    public function testLicenseServerProcessValidationFailProvider()
    {
        $this->assertTrue(true);

        return [
            [false, false, false, false, false, [
                $this->atLeastOnce(), $this->never(), $this->never(), $this->never(), $this->never(),
            ]],
            [true, false, false, false, false, [
                $this->atLeastOnce(), $this->atLeastOnce(), $this->never(), $this->never(), $this->never(),
            ]],
            [true, true, false, false, false, [
                $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->never(), $this->never(),
            ]],
            [true, true, true, false, false, [
                $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->never(),
            ]],
            [true, true, true, true, false, [
                $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(), $this->atLeastOnce(),
            ]],
        ];
    }

    /**
     * @expectedException \LogicException
     */
    #[PHPUnit\Framework\Attributes\DataProvider('testLicenseServerProcessValidationFailProvider')]
    public function testLicenseServerProcessValidationFail($isActive, $validIp, $validHost, $validVersion, $validPath, $called): void
    {
        $data = [
            'license' => 'valid',
            'host' => 'tests.com',
            'path' => __DIR__,
            'version' => '0.0.2',
        ];

        $valid = true;

        $service = $this->getMockBuilder(Box\Mod\Servicelicense\Service::class)->getMock();
        $service->expects($called[0])
            ->method('isLicenseActive')
            ->willReturn($isActive);
        $service->expects($called[1])
            ->method('isValidIp')
            ->willReturn($validIp);
        $service->expects($called[2])
            ->method('isValidHost')
            ->willReturn($validHost);
        $service->expects($called[3])
            ->method('isValidVersion')
            ->willReturn($validVersion);
        $service->expects($called[4])
            ->method('isValidPath')
            ->willReturn($validPath);

        $di = new Pimple\Container();
        $di['db'] = $this->di['db'];
        $di['logger'] = $this->di['logger'];
        $di['mod'] = $di->protect(fn () => new Box_Mod('servicelicense'));
        $di['mod_service'] = $di->protect(fn () => $service);

        $server = new Box\Mod\Servicelicense\Server($this->di['logger']);
        $server->setDi($di);

        $result = $server->process(json_encode($data));
        $this->assertEquals($valid, $result['valid'], print_r($result, 1));
    }
}
