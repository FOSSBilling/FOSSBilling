<?php

namespace Box\Mod\Extension\Api;

class GuestTest extends \BBTestCase
{
    /**
     * @var \Box\Mod\Extension\Service
     */
    protected $service;

    /**
     * @var Guest
     */
    protected $api;

    public function setup(): void
    {
        $this->service = new \Box\Mod\Extension\Service();
        $this->api = new Guest();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public static function is_onProvider()
    {
        return [
            [
                ['mod' => 'testModule'],
                [],
            ],
            [
                [
                    'id' => 'extensionId',
                    'type' => 'extensionType',
                ],
                [],
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('is_onProvider')]
    public function testisOn($data, $expected)
    {
        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('isExtensionActive')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->is_on($data);
        $this->assertEquals($expected, $result);
    }

    public function testisOnInvalidDataArray()
    {
        $data = [];
        $result = $this->api->is_on($data);
        $this->assertTrue($result);
    }

    public function testtheme()
    {
        $themeServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Theme\Service::class)->getMock();
        $themeServiceMock->expects($this->atLeastOnce())
            ->method('getThemeConfig')
            ->willReturn([]);

        $di = new \Pimple\Container();
        $di['mod_service'] = $di->protect(fn ($name) => $themeServiceMock);

        $this->api->setDi($di);
        $result = $this->api->theme();
        $this->assertIsArray($result);
    }

    public function testsettings()
    {
        $data['ext'] = 'testExtension';

        $serviceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $result = $this->api->settings($data);
        $this->assertIsArray($result);
    }

    public function testsettingsExtExtension()
    {
        $data = [];

        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Parameter ext is missing');
        $this->api->settings($data);
    }

    public function testlanguages()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);

        $result = $this->api->languages();
        $this->assertIsArray($result);
    }
}
