<?php

declare(strict_types=1);

namespace Box\Mod\Massmailer\Api;

use FOSSBilling\InformationException;
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

        $this->assertEquals($di, $this->api->getDi());
    }

    public function testUpdateStoresNormalizedFilter(): void
    {
        $model = new \Model_MassmailerMessage();
        $model->loadBean(new \DummyBean());
        $model->id = 1;
        $model->content = 'content';
        $model->subject = 'subject';
        $model->status = 'draft';

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('getExistingModelById')
            ->with('mod_massmailer', 1, 'Message not found')
            ->willReturn($model);
        $dbMock->expects($this->once())
            ->method('store')
            ->with($this->callback(static function ($storedModel): bool {
                return $storedModel->filter === '{"client_status":["active","canceled"],"has_order_with_status":["active","suspended"]}';
            }))
            ->willReturn(1);

        $service = new \Box\Mod\Massmailer\Service();
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $service->setDi($di);

        $this->api->setDi($di);
        $this->api->setService($service);

        $result = $this->api->update([
            'id' => 1,
            'filter' => [
                'client_status' => ['canceled', 'active', 'active'],
                'has_order_with_status' => ['suspended', 'active', 'active'],
            ],
        ]);

        $this->assertTrue($result);
    }

    public function testUpdateRejectsInvalidFilter(): void
    {
        $model = new \Model_MassmailerMessage();
        $model->loadBean(new \DummyBean());
        $model->id = 1;
        $model->content = 'content';
        $model->subject = 'subject';
        $model->status = 'draft';

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('getExistingModelById')
            ->with('mod_massmailer', 1, 'Message not found')
            ->willReturn($model);
        $dbMock->expects($this->never())
            ->method('store');

        $service = new \Box\Mod\Massmailer\Service();
        $di = $this->getDi();
        $di['db'] = $dbMock;
        $di['logger'] = new \Box_Log();
        $service->setDi($di);

        $this->api->setDi($di);
        $this->api->setService($service);

        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Mass mail filter contains invalid values for "client_status"');

        $this->api->update([
            'id' => 1,
            'filter' => [
                'client_status' => ['active', 'not-valid'],
            ],
        ]);
    }
}
