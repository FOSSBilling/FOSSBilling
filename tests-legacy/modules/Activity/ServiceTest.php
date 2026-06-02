<?php

declare(strict_types=1);

namespace Box\Tests\Mod\Activity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('Core')]
final class ServiceTest extends \BBTestCase
{
    public function testDi(): void
    {
        $service = new \Box\Mod\Activity\Service();
        $di = $this->createDi($this->createDbalConnection());
        $service->setDi($di);

        $this->assertEquals($di, $service->getDi());
    }

    public static function searchFilters(): array
    {
        return [
            [[], 'FROM activity_system ', true],
            [['user_filter' => 'only_clients'], 'm.client_id IS NOT NULL', true],
            [['user_filter' => 'only_staff'], 'm.admin_id IS NOT NULL', true],
            [['priority' => '2'], 'm.priority =', true],
            [['search' => 'keyword'], 'm.message LIKE ', true],
            [['min_priority' => 6], 'm.priority <= :min_priority', true],
            [['priority' => 6], 'm.priority = :priority', true],
            [['priority' => 5, 'min_priority' => 3], 'm.priority = :priority', true],
            [['priority' => 5, 'min_priority' => 3], 'm.priority <= :min_priority', false],
        ];
    }

    #[DataProvider('searchFilters')]
    public function testGetSearchQuery(array $filterKey, string $search, bool $expected): void
    {
        $service = new \Box\Mod\Activity\Service();
        $service->setDi($this->createDi($this->createDbalConnection()));

        $result = $service->getSearchQuery($filterKey);

        $this->assertIsString($result[0]);
        $this->assertIsArray($result[1]);
        $this->assertSame($expected, str_contains($result[0], $search));
    }

    public function testLogEventInsertsRow(): void
    {
        $dbal = $this->createDbalConnection();
        $service = new \Box\Mod\Activity\Service();
        $service->setDi($this->createDi($dbal));

        $service->logEvent([
            'client_id' => 3,
            'priority' => 4,
            'message' => 'Created order',
        ]);

        $row = $dbal->executeQuery('SELECT client_id, priority, message, ip FROM activity_system')->fetchAssociative();
        $this->assertSame('3', (string) $row['client_id']);
        $this->assertSame('4', (string) $row['priority']);
        $this->assertSame('Created order', $row['message']);
        $this->assertSame('127.0.0.1', $row['ip']);
    }

    public function testLogEmail(): void
    {
        $dbal = $this->createDbalConnection();
        $service = new \Box\Mod\Activity\Service();
        $service->setDi($this->createDi($dbal));

        $result = $service->logEmail('subject', 1, 'sender', 'recipients', 'html', 'text');

        $row = $dbal->executeQuery('SELECT subject, client_id FROM activity_client_email')->fetchAssociative();
        $this->assertTrue($result);
        $this->assertSame('subject', $row['subject']);
        $this->assertSame('1', (string) $row['client_id']);
    }

    public function testToApiArray(): void
    {
        $dbal = $this->createDbalConnection();
        $dbal->insert('client', [
            'id' => 1,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.com',
        ]);

        $history = new \Model_ActivityClientHistory();
        $history->loadBean(new \DummyBean());
        $history->id = 5;
        $history->client_id = 1;
        $history->ip = '127.0.0.1';
        $history->created_at = '2026-03-23 10:00:00';

        $service = new \Box\Mod\Activity\Service();
        $service->setDi($this->createDi($dbal));

        $result = $service->toApiArray($history);

        $this->assertSame(5, $result['id']);
        $this->assertSame('127.0.0.1', $result['ip']);
        $this->assertSame('Ada', $result['client']['first_name']);
        $this->assertSame('ada@example.com', $result['client']['email']);
    }

    public function testRmByClientDeletesRows(): void
    {
        $dbal = $this->createDbalConnection();
        $dbal->insert('activity_system', [
            'client_id' => 1,
            'priority' => 1,
            'message' => 'One',
            'ip' => '127.0.0.1',
            'created_at' => '2026-03-23 10:00:00',
        ]);
        $dbal->insert('activity_system', [
            'client_id' => 2,
            'priority' => 1,
            'message' => 'Two',
            'ip' => '127.0.0.1',
            'created_at' => '2026-03-23 10:00:00',
        ]);

        $client = new \Model_Client();
        $client->loadBean(new \DummyBean());
        $client->id = 1;

        $service = new \Box\Mod\Activity\Service();
        $service->setDi($this->createDi($dbal));
        $service->rmByClient($client);

        $remaining = (int) $dbal->executeQuery('SELECT COUNT(*) FROM activity_system')->fetchOne();
        $this->assertSame(1, $remaining);
    }

    private function createDi(Connection $dbal): \Pimple\Container
    {
        $di = $this->getDi();
        $di['dbal'] = $dbal;

        $extensionService = $this->createMock(\Box\Mod\Extension\Service::class);
        $extensionService->method('isExtensionActive')
            ->willReturn(false);

        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $request->method('getClientIp')
            ->willReturn('127.0.0.1');

        $di['request'] = $request;
        $di['mod_service'] = $di->protect(fn (string $service): object => match ($service) {
            'extension' => $extensionService,
            default => throw new \RuntimeException("Unexpected service $service"),
        });

        return $di;
    }

    private function createDbalConnection(): Connection
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $connection->executeStatement('CREATE TABLE activity_system (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, admin_id INTEGER, priority INTEGER, message TEXT, ip TEXT, created_at TEXT)');
        $connection->executeStatement('CREATE TABLE activity_client_email (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER, sender TEXT, recipients TEXT, subject TEXT, content_html TEXT, content_text TEXT, created_at TEXT)');
        $connection->executeStatement('CREATE TABLE client (id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT, email TEXT)');

        return $connection;
    }
}
