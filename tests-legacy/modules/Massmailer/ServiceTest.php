<?php

declare(strict_types=1);

namespace Box\Mod\Massmailer;

use Box\Mod\Massmailer\Entity\MassmailerMessage;
use Box\Mod\Massmailer\Repository\MassmailerMessageRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
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
        $di = $this->createDi();
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
        $this->service->setDi($this->createDi());

        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Mass mail filter contains invalid values for "unexpected"');

        $this->service->normalizeFilter([
            'unexpected' => ['anything'],
        ], true);
    }

    public function testNormalizeFilterRejectsUnknownIdsInStrictMode(): void
    {
        $dbal = $this->createDbalConnection();
        $dbal->executeStatement('CREATE TABLE client_group (id INTEGER PRIMARY KEY)');
        $dbal->executeStatement('INSERT INTO client_group (id) VALUES (1)');

        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Mass mail filter contains invalid values for "client_groups"');

        $this->service->normalizeFilter([
            'client_groups' => ['2', '1'],
        ], true);
    }

    public function testGetMessageReceiversBuildsParameterizedQuery(): void
    {
        $dbal = $this->createDbalConnection();
        $this->seedReceiverTables($dbal);

        $model = (new MassmailerMessage())->setFilter(json_encode([
            'client_status' => ['active'],
            'client_groups' => [1],
            'has_order' => [10],
            'has_order_with_status' => ['active'],
        ], JSON_THROW_ON_ERROR));

        $di = $this->createDi($dbal);
        $this->service->setDi($di);

        $result = $this->service->getMessageReceivers($model);

        $this->assertSame([['id' => '1']], $result);
    }

    public function testGetMessageReceiversRejectsInvalidStoredFilter(): void
    {
        $model = (new MassmailerMessage())->setFilter(json_encode([
            'client_status' => ['active', 'not-valid'],
        ], JSON_THROW_ON_ERROR));

        $di = $this->createDi($this->createDbalConnection());
        $this->service->setDi($di);

        $this->expectException(InformationException::class);
        $this->expectExceptionMessage('Mass mail filter contains invalid values for "client_status"');

        $this->service->getMessageReceivers($model);
    }

    private function createDi(?Connection $dbal = null): \Pimple\Container
    {
        $di = $this->getDi();

        $repo = $this->createStub(MassmailerMessageRepository::class);
        $em = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $em->method('getRepository')
            ->with(MassmailerMessage::class)
            ->willReturn($repo);

        $di['em'] = $em;
        $di['dbal'] = $dbal ?? $this->createDbalConnection();

        return $di;
    }

    private function createDbalConnection(): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
    }

    private function seedReceiverTables(Connection $dbal): void
    {
        $dbal->executeStatement('CREATE TABLE client_group (id INTEGER PRIMARY KEY)');
        $dbal->executeStatement('CREATE TABLE product (id INTEGER PRIMARY KEY)');
        $dbal->executeStatement('CREATE TABLE client (id INTEGER PRIMARY KEY, status TEXT, client_group_id INTEGER)');
        $dbal->executeStatement('CREATE TABLE client_order (id INTEGER PRIMARY KEY, client_id INTEGER, product_id INTEGER, status TEXT)');

        $dbal->executeStatement('INSERT INTO client_group (id) VALUES (1), (2)');
        $dbal->executeStatement('INSERT INTO product (id) VALUES (10), (11)');

        $dbal->executeStatement("INSERT INTO client (id, status, client_group_id) VALUES (1, 'active', 1), (2, 'canceled', 1), (3, 'active', 2)");
        $dbal->executeStatement("INSERT INTO client_order (id, client_id, product_id, status) VALUES (1, 1, 10, 'active'), (2, 2, 10, 'suspended'), (3, 3, 11, 'active')");
    }
}
