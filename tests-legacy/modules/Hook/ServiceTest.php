<?php

declare(strict_types=1);

namespace Box\Mod\Hook;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    protected ?Service $service;

    public function setUp(): void
    {
        $this->service = new Service();
    }

    public function testGetDi(): void
    {
        $di = $this->getDi();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

    public function testGetSearchQuery(): void
    {
        [$sql, $params] = $this->service->getSearchQuery([]);

        $this->assertIsString($sql);
        $this->assertIsArray($params);

        $this->assertTrue(str_contains($sql, 'SELECT id, rel_type, rel_id, meta_value as event, created_at, updated_at'));
        $this->assertSame([], $params);
    }

    public function testToApiArray(): void
    {
        $arrMock = ['testing' => 'okey'];
        $result = $this->service->toApiArray($arrMock);
        $this->assertEquals($arrMock, $result);
    }

    public function testOnAfterAdminActivateExtension(): void
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

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('load')
            ->willReturn($model);

        $hookService = $this->createMock(Service::class);
        $hookService->expects($this->atLeastOnce())
            ->method('batchConnect');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod_service'] = $di->protect(fn ($name) => $hookService);

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->setDi($di);
        $this->service->onAfterAdminActivateExtension($eventMock);
        $result = $eventMock->getReturnValue();
        $this->assertTrue($result);
    }

    public function testOnAfterAdminActivateExtensionMissingId(): void
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

    public function testOnAfterAdminDeactivateExtension(): void
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

        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('exec');

        $di = $this->getDi();
        $di['db'] = $dbMock;

        $eventMock->expects($this->atLeastOnce())
            ->method('getDi')
            ->willReturn($di);

        $this->service->setDi($di);
        $this->service->onAfterAdminDeactivateExtension($eventMock);
        $result = $eventMock->getReturnValue();
        $this->assertTrue($result);
    }

    public function testBatchConnect(): void
    {
        $mod = 'activity';

        $data['mods'] = [$mod];

        $dbMock = $this->createMock('\Box_Database');
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

        $activityServiceMock = $this->createMock(\Box\Mod\Activity\Service::class);

        $boxModMock = $this->getMockBuilder('\\' . \FOSSBilling\Module::class)->disableOriginalConstructor()->getMock();
        $boxModMock->expects($this->atLeastOnce())
            ->method('hasService')
            ->willReturn(true);
        $boxModMock->expects($this->any())
            ->method('getService')
            ->willReturn($activityServiceMock);
        $boxModMock->expects($this->any())
            ->method('getName')
            ->willReturn('activity');

        $extensionServiceMock = $this->createMock(\Box\Mod\Extension\Service::class);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['mod'] = $di->protect(fn () => $boxModMock);
        $di['mod_service'] = $di->protect(function ($name) use ($extensionServiceMock) {
            if ($name == 'extension') {
                return $extensionServiceMock;
            }
        });
        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray')
        ;
        $di['validator'] = $validatorMock;
        $this->service->setDi($di);
        $result = $this->service->batchConnect($mod);
        $this->assertTrue($result);
    }
}
