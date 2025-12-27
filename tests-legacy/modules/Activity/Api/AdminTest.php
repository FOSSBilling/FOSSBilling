<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Activity\Api;
use PHPUnit\Framework\Attributes\DataProvider; 
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    public function testLogGetList(): void
    {
        $simpleResultArr = [
            'list' => [
                [
                    'id' => 1,
                    'staff_id' => 1,
                    'staff_name' => 'Joe',
                    'staff_email' => 'example@example.com',
                ],
            ],
        ];

        $service = $this->createMock(\Box\Mod\Activity\Service::class);
        $service->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['String', []]);

        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $model = new \Model_ActivitySystem();
        $model->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);

        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);
        $di['api_admin'] = $api;

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $activity->setService($service);
        $activity->log_get_list([]);
    }

    public function testLogGetListItemUserClient(): void
    {
        $simpleResultArr = [
            'list' => [
                [
                    'id' => 1,
                    'client_id' => 1,
                    'client_name' => 'Joe',
                    'client_email' => 'example@example.com',
                ],
            ],
        ];

        $service = $this->createMock(\Box\Mod\Activity\Service::class);
        $service->expects($this->atLeastOnce())
            ->method('getSearchQuery')
            ->willReturn(['String', []]);

        $paginatorMock = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['getPaginatedResultSet'])
            ->getMock();

        $paginatorMock->expects($this->atLeastOnce())
            ->method('getPaginatedResultSet')
            ->willReturn($simpleResultArr);

        $model = new \Model_ActivitySystem();
        $model->loadBean(new \DummyBean());

        $di = $this->getDi();
        $di['pager'] = $paginatorMock;
        $di['mod_service'] = $di->protect(fn (): \PHPUnit\Framework\MockObject\MockObject => $service);

        $api = new \Api_Handler(new \Model_Admin());
        $api->setDi($di);
        $di['api_admin'] = $api;

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $activity->setService($service);
        $activity->log_get_list([]);
    }

    public function testLogEmptyMParam(): void
    {
        $di = $this->getDi();

        $activity = new \Box\Mod\Activity\Api\Admin();
        $activity->setDi($di);
        $result = $activity->log([]);
        $this->assertFalse($result, 'Empty array key m');
    }

    public function testLogEmail(): void
    {
        $service = $this->getMockBuilder(\Box\Mod\Activity\Service::class)->onlyMethods(['logEmail'])->getMock();
        $service->expects($this->atLeastOnce())
            ->method('logEmail')
            ->willReturn(true);

        $di = $this->getDi();

        $adminApi = new \Box\Mod\Activity\Api\Admin();
        $adminApi->setService($service);
        $adminApi->setDi($di);
        $result = $adminApi->log_email(['subject' => 'Proper subject']);

        $this->assertTrue($result, 'Log_email did not returned true');
    }

    public function testLogEmailWithoutSubject(): void
    {
        $activity = new \Box\Mod\Activity\Api\Admin();
        $result = $activity->log_email([]);
        $this->assertFalse($result);
    }
}
