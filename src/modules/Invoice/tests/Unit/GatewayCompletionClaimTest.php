<?php

declare(strict_types=1);

use Box\Mod\Invoice\ServiceTransaction;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

use function Tests\Helpers\container;

test('MySQL completion claims preserve financial status and exclude concurrent processors', function (): void {
    $port = getenv('PAYMENT_TEST_MYSQL_PORT');
    if ($port === false || $port === '') {
        $this->markTestSkipped('Set PAYMENT_TEST_MYSQL_PORT for an isolated MySQL/MariaDB test server.');
    }
    $params = ['driver' => 'pdo_mysql', 'host' => '127.0.0.1', 'port' => (int) $port, 'user' => 'root', 'password' => getenv('PAYMENT_TEST_MYSQL_PASSWORD') ?: ''];
    $admin = DriverManager::getConnection($params);
    $database = 'completion_claim_' . bin2hex(random_bytes(6));
    $admin->executeStatement('CREATE DATABASE ' . $database);
    $connection = DriverManager::getConnection($params + ['dbname' => $database]);
    $other = DriverManager::getConnection($params + ['dbname' => $database]);
    $worker = function (Doctrine\DBAL\Connection $connection): ServiceTransaction {
        $di = container();
        $config = ORMSetup::createAttributeMetadataConfig([PATH_MODS], true);
        $config->setProxyDir(sys_get_temp_dir());
        $config->setProxyNamespace('GatewayCompletionClaimProxies');
        $di['em'] = new EntityManager($connection, $config);
        $service = new ServiceTransaction();
        $service->setDi($di);

        return $service;
    };

    try {
        $connection->executeStatement('CREATE TABLE `transaction` (id BIGINT PRIMARY KEY, status VARCHAR(30), gateway_completion_state SMALLINT NOT NULL DEFAULT 0, updated_at DATETIME, processing_token VARCHAR(32) NULL) ENGINE=InnoDB');
        foreach ([1 => 1, 2 => 2, 3 => 0] as $id => $state) {
            $connection->insert('`transaction`', ['id' => $id, 'status' => 'processed', 'gateway_completion_state' => $state, 'updated_at' => date('Y-m-d H:i:s', time() - 600)]);
        }
        expect(array_map(intval(...), array_column($worker($connection)->getReceived(), 'id')))->toBe([2, 1]);
        foreach ([1, 2] as $id) {
            expect($worker($connection)->claimForProcessing($id))->toBeTrue()
                ->and($worker($other)->claimForProcessing($id))->toBeFalse()
                ->and($connection->fetchOne('SELECT status FROM `transaction` WHERE id = ?', [$id]))->toBe('processed');
        }
        expect($worker($connection)->claimForProcessing(3))->toBeFalse()
            ->and($worker($other)->getReceived())->toBe([]);
    } finally {
        $connection->close();
        $other->close();
        $admin->executeStatement('DROP DATABASE ' . $database);
        $admin->close();
    }
});
