<?php

declare(strict_types=1);

namespace Box\Mod\Notification\Api;

use Box\Mod\Extension\Entity\ExtensionMeta;
use FOSSBilling\PaginationOptions;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class AdminTest extends \BBTestCase
{
    public function testGetListUsesDoctrinePagination(): void
    {
        $queryBuilder = $this->getMockBuilder(\Doctrine\ORM\QueryBuilder::class)
            ->disableOriginalConstructor()
            ->getMock();

        $service = $this->createMock(\Box\Mod\Notification\Service::class);
        $service->expects($this->once())
            ->method('getSearchQueryBuilder')
            ->with(['per_page' => 10])
            ->willReturn($queryBuilder);

        $pager = $this->getMockBuilder(\FOSSBilling\Pagination::class)
            ->onlyMethods(['paginateDoctrineQuery'])
            ->getMock();
        $pager->expects($this->once())
            ->method('paginateDoctrineQuery')
            ->with(
                $queryBuilder,
                $this->callback(fn ($pagination): bool => $pagination instanceof PaginationOptions && $pagination->perPage === 10)
            )
            ->willReturn(['list' => []]);

        $di = $this->getDi();
        $di['pager'] = $pager;

        $api = new Admin();
        $api->setDi($di);
        $api->setService($service);

        $result = $api->get_list(['per_page' => 10]);
        $this->assertSame(['list' => []], $result);
    }

    public function testGetReturnsMappedNotification(): void
    {
        $meta = (new ExtensionMeta())
            ->setExtension('mod_notification')
            ->setMetaKey('message')
            ->setMetaValue('Test');

        $service = $this->createMock(\Box\Mod\Notification\Service::class);
        $service->expects($this->once())
            ->method('get')
            ->with(5)
            ->willReturn($meta);
        $service->expects($this->once())
            ->method('toApiArray')
            ->with($meta)
            ->willReturn(['id' => 5, 'meta_value' => 'Test']);

        $api = new Admin();
        $api->setService($service);

        $result = $api->get(['id' => 5]);
        $this->assertSame(['id' => 5, 'meta_value' => 'Test'], $result);
    }

    public function testDeleteDelegatesToService(): void
    {
        $service = $this->createMock(\Box\Mod\Notification\Service::class);
        $service->expects($this->once())
            ->method('delete')
            ->with(9)
            ->willReturn(true);

        $api = new Admin();
        $api->setService($service);

        $this->assertTrue($api->delete(['id' => 9]));
    }
}
