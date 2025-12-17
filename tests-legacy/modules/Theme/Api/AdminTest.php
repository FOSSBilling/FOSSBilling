<?php

declare(strict_types=1);

namespace Box\Mod\Theme\Api;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->api = new Admin();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetList(): void
    {
        $systemServiceMock = $this->createMock(\Box\Mod\Theme\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('getThemes')
            ->willReturn([]);

        $this->api->setService($systemServiceMock);

        $result = $this->api->get_list([]);
        $this->assertIsArray($result);
    }

    public function testGet(): void
    {
        $data = [
            'code' => 'themeCode',
        ];

        $systemServiceMock = $this->createMock(\Box\Mod\Theme\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('loadTheme')
            ->willReturn([]);

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = $this->getDi();
        $this->api->setDi($di);
        $this->api->setService($systemServiceMock);

        $result = $this->api->get($data);
        $this->assertIsArray($result);
    }

    public function testSelectNotAdminTheme(): void
    {
        $data = [
            'code' => 'pjw',
        ];

        $themeMock = $this->getMockBuilder(\Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('isAdminAreaTheme')
            ->willReturn(false);

        $serviceMock = $this->createMock(\Box\Mod\Theme\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);

        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('setParamValue')
            ->with($this->equalTo('theme'));

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $loggerMock = $this->createMock('\Box_Log');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = $loggerMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->select($data);
        $this->assertTrue($result);
    }

    public function testSelectAdminTheme(): void
    {
        $data = [
            'code' => 'pjw',
        ];

        $themeMock = $this->getMockBuilder(\Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();
        $themeMock->expects($this->atLeastOnce())
            ->method('isAdminAreaTheme')
            ->willReturn(true);

        $serviceMock = $this->createMock(\Box\Mod\Theme\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);

        $systemServiceMock = $this->createMock(\Box\Mod\System\Service::class);
        $systemServiceMock->expects($this->atLeastOnce())
            ->method('setParamValue')
            ->with($this->equalTo('admin_theme'));

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $loggerMock = $this->createMock('\Box_Log');

        $di = $this->getDi();
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $systemServiceMock);
        $di['logger'] = $loggerMock;
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->select($data);
        $this->assertTrue($result);
    }

    public function testPresetDelete(): void
    {
        $data = [
            'code' => 'themeCode',
            'preset' => 'themePreset',
        ];

        $themeMock = $this->getMockBuilder(\Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();

        $serviceMock = $this->createMock(\Box\Mod\Theme\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);
        $serviceMock->expects($this->atLeastOnce())
            ->method('deletePreset');

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = $this->getDi();
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->preset_delete($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }

    public function testPresetSelect(): void
    {
        $data = [
            'code' => 'themeCode',
            'preset' => 'themePreset',
        ];

        $themeMock = $this->getMockBuilder(\Box\Mod\Theme\Model\Theme::class)->disableOriginalConstructor()->getMock();

        $serviceMock = $this->createMock(\Box\Mod\Theme\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getTheme')
            ->willReturn($themeMock);
        $serviceMock->expects($this->atLeastOnce())
            ->method('setCurrentThemePreset');

        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->preset_select($data);
        $this->assertIsBool($result);
        $this->assertTrue($result);
    }
}
