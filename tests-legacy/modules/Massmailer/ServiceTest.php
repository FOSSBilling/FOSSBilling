<?php

declare(strict_types=1);

namespace Box\Mod\Massmailer;

use FOSSBilling\InformationException;
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

        $this->assertEquals($di, $this->service->getDi());
    }

    public function testNormalizeFilterReturnsCanonicalEnumValues(): void
    {
        $normalized = $this->service->normalizeFilter([
            'client_status' => ['canceled', 'active', 'active'],
            'has_order_with_status' => ['suspended', 'active', 'active'],
        ], true);

        $this->assertSame([
            'client_status' => ['active', 'canceled'],
            'has_order_with_status' => ['active', 'suspended'],
        ], $normalized);
    }

    public function testNormalizeFilterRejectsUnexpectedKeysInStrictMode(): void
    {
        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Mass mail filter contains invalid values for "unexpected"');

        $this->service->normalizeFilter([
            'unexpected' => ['anything'],
        ], true);
    }

    public function testNormalizeFilterRejectsUnknownIdsInStrictMode(): void
    {
        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('getAll')
            ->with('SELECT id FROM client_group WHERE id IN (?,?)', [1, 2])
            ->willReturn([['id' => 1]]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Mass mail filter contains invalid values for "client_groups"');

        $this->service->normalizeFilter([
            'client_groups' => ['2', '1'],
        ], true);
    }

    public function testGetMessageReceiversBuildsParameterizedQuery(): void
    {
        $model = new \Model_MassmailerMessage();
        $model->loadBean(new \DummyBean());
        $model->filter = json_encode([
            'client_status' => ['active', 'canceled'],
            'has_order_with_status' => ['active', 'suspended'],
        ], JSON_THROW_ON_ERROR);

        $expectedSql = 'SELECT DISTINCT c.id
            FROM client c
            LEFT JOIN client_order co ON (co.client_id = c.id)
            WHERE 1
         AND c.status IN (?,?) AND co.status IN (?,?) ORDER BY c.id DESC';

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->once())
            ->method('getAll')
            ->with($expectedSql, ['active', 'canceled', 'active', 'suspended'])
            ->willReturn([]);

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $result = $this->service->getMessageReceivers($model);

        $this->assertSame([], $result);
    }

    public function testGetMessageReceiversRejectsInvalidStoredFilter(): void
    {
        $model = new \Model_MassmailerMessage();
        $model->loadBean(new \DummyBean());
        $model->filter = json_encode([
            'client_status' => ['active', 'not-valid'],
        ], JSON_THROW_ON_ERROR);

        $dbMock = $this->createMock(\Box_Database::class);
        $dbMock->expects($this->never())
            ->method('getAll');

        $di = $this->getDi();
        $di['db'] = $dbMock;
        $this->service->setDi($di);

        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Mass mail filter contains invalid values for "client_status"');

        $this->service->getMessageReceivers($model);
    }
}
