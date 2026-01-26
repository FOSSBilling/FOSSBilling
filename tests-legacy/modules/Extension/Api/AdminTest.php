<?php

declare(strict_types=1);

namespace Box\Mod\Extension\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?\Box\Mod\Extension\Service $service;
    protected ?Admin $api;

    public function setUp(): void
    {
        $this->service = new \Box\Mod\Extension\Service();
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
        $data = [];

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getExtensionsList')
            ->willReturn([]);

        $this->api->setService($serviceMock);

        $result = $this->api->get_list($data);
        $this->assertIsArray($result);
    }

    public function testGetLatest(): void
    {
        $data = [];

        $extensionMock = $this->createMock(\FOSSBilling\ExtensionManager::class);

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtensionList')
            ->willReturn([]);

        $di = $this->getDi();
        $di['extension_manager'] = $extensionMock;

        $this->api->setDi($di);
        $result = $this->api->get_latest($data);
        $this->assertIsArray($result);
    }

    public function testGetLatestException(): void
    {
        $data = ['type' => 'mod'];

        $extensionMock = $this->createMock(\FOSSBilling\ExtensionManager::class);

        $extensionMock->expects($this->atLeastOnce())
            ->method('getExtensionList')
            ->willThrowException(new \Exception());

        $di = $this->getDi();
        $di['extension_manager'] = $extensionMock;
        $di['logger'] = $this->createMock('Box_Log');

        $this->api->setDi($di);
        $result = $this->api->get_latest($data);
        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    public function testGetNavigation(): void
    {
        $data = ['url' => 'billing'];

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getAdminNavigation')
            ->willReturn([]);

        $this->api->setService($serviceMock);
        $this->api->setIdentity(new \Model_Admin());

        $result = $this->api->get_navigation($data);

        $this->assertIsArray($result);
    }

    public function testLanguages(): void
    {
        $result = $this->api->languages([]);
        $this->assertIsArray($result);
    }

    public function testUpdateExtensionNotFound(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensioTYpe',
        ];

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturn(null);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $this->expectException(\FOSSBilling\Exception::class);
        $this->expectExceptionMessage('Extension not found');
        $this->api->update($data);
    }

    public function testActivate(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('activateExistingExtension')
            ->willReturn([]);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);

        $result = $this->api->activate($data);
        $this->assertIsArray($result);
    }

    public function testDeactivate(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('deactivate')
            ->willReturn(true);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->deactivate($data);
        $this->assertTrue($result);
    }

    public function testUninstall(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('findExtension')
            ->willReturn($model);
        $serviceMock->expects($this->atLeastOnce())
            ->method('uninstall')
            ->willReturn(true);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->uninstall($data);
        $this->assertTrue($result);
    }

    public function testInstall(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $expected = [
            'success' => true,
            'id' => $data['id'],
            'type' => $data['type'],
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('downloadAndExtract')
            ->willReturn(true);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->install($data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testInstallExceptionActivate(): void
    {
        $data = [
            'id' => 'extensionId',
            'type' => 'extensionType',
        ];

        $expected = [
            'success' => true,
            'id' => $data['id'],
            'type' => $data['type'],
        ];

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('downloadAndExtract')
            ->willReturn(true);

        $eventMock = $this->createMock('\Box_EventManager');
        $eventMock->expects($this->atLeastOnce())->
            method('fire');

        $dbMock = $this->createMock('\Box_Database');

        $di = $this->getDi();
        $di['events_manager'] = $eventMock;
        $di['logger'] = new \Box_Log();
        $di['db'] = $dbMock;

        $this->api->setService($serviceMock);
        $this->api->setDi($di);

        $result = $this->api->install($data);
        $this->assertIsArray($result);
        $this->assertEquals($expected, $result);
    }

    public function testConfigGet(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturn([]);

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->config_get($data);

        $this->assertIsArray($result);
    }

    public function testConfigSave(): void
    {
        $data = [
            'ext' => 'extensionName',
        ];

        $serviceMock = $this->createMock(\Box\Mod\Extension\Service::class);
        $serviceMock->expects($this->atLeastOnce())
            ->method('setConfig')
            ->willReturn(true);

        $serviceMock->expects($this->never())
            ->method('getConfig');

        $di = $this->getDi();
        $this->api->setDi($di);

        $this->api->setService($serviceMock);
        $result = $this->api->config_save($data);

        $this->assertTrue($result);
    }
}
