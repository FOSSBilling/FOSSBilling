<?php

namespace Box\Mod\Hook;

class ServiceTest extends \BBTestCase
{
    /**
     * @var Service
     */
    protected $service;

    public function setup(): void
    {
        $this->service = new Service();
    }

    public function testgetDi(): void
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testgetSearchQuery(): void
    {
        [$sql, $params] = $this->service->getSearchQuery([]);

        $this->assertIsString($sql);
        $this->assertIsArray($params);

        $this->assertTrue(str_contains($sql, 'SELECT id, rel_type, rel_id, meta_value as event, created_at, updated_at'));
        $this->assertEquals($params, []);
    }

    public function testtoApiArray(): void
    {
        $arrMock = ['testing' => 'okey'];
        $result = $this->service->toApiArray($arrMock);
        $this->assertEquals($arrMock, $result);
    }

    public function testonAfterAdminActivateExtension(): void
    {
        $eventParams = [
            'id' => 1,
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->onlyMethods(['getParameters', 'getDi'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($eventParams);

        $model = new \Model_Extension();
        $model->loadBean(new \DummyBean());
        $model->id = 1;
        $model->type = 'mod';

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($model);

        $hookService = $this->getMockBuilder('\\' . Service::class)->getMock();
        $hookService->expects($this->atLeastOnce())
            ->method('batchConnect');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name): \PHPUnit\Framework\MockObject\MockObject => $hookService);

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->setDi($di);
        $this->service->onAfterAdminActivateExtension($eventMock);
        $result = $eventMock->getReturnValue();
        $this->assertTrue($result);
    }

    public function testonAfterAdminActivateExtensionMissingId(): void
    {
        $eventParams = [];

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->onlyMethods(['getParameters'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($eventParams);

        $this->service->onAfterAdminActivateExtension($eventMock);
        $result = $eventMock->getReturnValue();
        $this->assertFalse($result);
    }

    public function testonAfterAdminDeactivateExtension(): void
    {
        $eventParams = [
            'type' => 'mod',
            'id' => 1,
        ];

        $eventMock = $this->getMockBuilder('\Box_Event')
            ->onlyMethods(['getParameters', 'getDi'])
            ->disableOriginalConstructor()
            ->getMock();
        $eventMock->expects($this->atLeastOnce())
            ->method('getParameters')
            ->willReturn($eventParams);

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->setDi($di);
        $this->service->onAfterAdminDeactivateExtension($eventMock);
        $result = $eventMock->getReturnValue();
        $this->assertTrue($result);
    }

    public function testbatchConnect(): void
    {
        $mod = 'activity';

        $data['mods'] = [$mod];

        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getCell')
            ->willReturn(false);

        $extensionModel = new \Model_ExtensionMeta();
        $extensionModel->loadBean(new \DummyBean());

        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->willReturn($extensionModel);

        $dbMock->expects($this->atLeastOnce())
            ->method('store');

        $returnArr = [
            [
                'id' => 2,
                'rel_id' => 1,
                'meta_value' => 'testValue',
            ],
        ];
        $dbMock->expects($this->atLeastOnce())
            ->method('getAll')
            ->willReturn($returnArr);

        $activityServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Activity\Service::class)->getMock();

        $boxModMock = $this->getMockBuilder('\Box_Mod')->disableOriginalConstructor()->getMock();
        $boxModMock->expects($this->atLeastOnce())
            ->method('hasService')
            ->willReturn(true);
        $boxModMock->expects($this->any())
            ->method('getService')
            ->willReturn($activityServiceMock);
        $boxModMock->expects($this->any())
            ->method('getName')
            ->willReturn('activity');

        $extensionServiceMock = $this->getMockBuilder('\\' . \Box\Mod\Extension\Service::class)->getMock();

        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $boxModMock);
        $di['mod_service'] = $di->protect(function ($name) use ($extensionServiceMock) {
            if ($name == 'extension') {
                return $extensionServiceMock;
            }
        });
        $validatorMock = $this->getMockBuilder('\\' . \FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->atLeastOnce())
            ->method('checkRequiredParamsForArray');
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);
        $result = $this->service->batchConnect($mod);
        $this->assertTrue($result);
    }
}
