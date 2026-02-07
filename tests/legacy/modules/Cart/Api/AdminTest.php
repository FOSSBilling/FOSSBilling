<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Cart\Api;

use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    protected ?\Box\Mod\Cart\Api\Admin $adminApi;

    public function setUp(): void
    {
        $this->adminApi = new \Box\Mod\Cart\Api\Admin();
    }

    public function testGetList(): void
    {
        $simpleResultArr = [
            'list' => [
                ['id' => 1],
            ],
        ];

        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
        ->onlyMethods(['getPaginatedResultSet'])
        ->disableOriginalConstructor()
        ->getMock();
        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['getSearchQuery', 'toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('getSearchQuery')
            ->willReturn(['query', []]);
        $serviceMock->expects($this->atLeastOnce())
            ->method('toApiArray')
            ->willReturn([]);

        $model = new \Model_Cart();
        $model->loadBean(new \DummyBean());
        $dbMock = $this->createMock('\Box_Database');
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn($model);

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;
        $di['db'] = $dbMock;

        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [];
        $result = $this->adminApi->get_list($data);

        $this->assertIsArray($result);
    }

    public function testGet(): void
    {
        $validatorMock = $this->getMockBuilder(\FOSSBilling\Validate::class)->disableOriginalConstructor()->getMock();
        $validatorMock->expects($this->any())->method('checkRequiredParamsForArray');

        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->willReturn(new \Model_Cart());

        $serviceMock = $this->getMockBuilder(\Box\Mod\Cart\Service::class)
            ->onlyMethods(['toApiArray'])->getMock();
        $serviceMock->expects($this->atLeastOnce())->method('toApiArray')
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->adminApi->setDi($di);

        $this->adminApi->setService($serviceMock);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->get($data);

        $this->assertIsArray($result);
    }

    public function testBatchExpire(): void
    {
        $dbMock = $this->getMockBuilder('\Box_Database')->disableOriginalConstructor()->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getAssoc')
            ->willReturn([1, date('Y-m-d H:i:s')]);
        $dbMock->expects($this->atLeastOnce())
            ->method('exec')
            ->willReturn(null);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = $this->createMock('Box_Log');
        $this->adminApi->setDi($di);

        $data = [
            'id' => 1,
        ];
        $result = $this->adminApi->batch_expire($data);

        $this->assertTrue($result);
    }
}
